<?php

// Single-file Telegram Bot: Referral + Points + Item Shop (PHP + MySQL)
// Encoding: UTF-8
// Minimum PHP: 7.4

// ==========================
// Configuration
// ==========================

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
if (!BOT_TOKEN) {
    http_response_code(500);
    echo 'BOT_TOKEN is not set';
    exit;
}

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'telegram_referral_bot');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('ADMIN_IDS', (function () {
    $env = getenv('ADMIN_IDS') ?: '';
    $adminIds = [];
    foreach (array_filter(array_map('trim', explode(',', $env))) as $id) {
        if (ctype_digit($id) || preg_match('/^-?\d+$/', $id)) {
            $adminIds[] = (int) $id;
        }
    }
    return $adminIds;
})());

define('ADMIN_GROUP_ID', (int) (getenv('ADMIN_GROUP_ID') ?: 0)); // Group ID to receive item requests

define('PUBLIC_ANNOUNCE_CHANNEL_ID', (int) (getenv('PUBLIC_ANNOUNCE_CHANNEL_ID') ?: 0)); // Optional public channel for announcements

define('REFERRAL_REWARD_POINTS', (int) (getenv('REFERRAL_REWARD_POINTS') ?: 10));

define('DAILY_BONUS_POINTS', (int) (getenv('DAILY_BONUS_POINTS') ?: 5));

define('LOTTERY_TICKET_COST', (int) (getenv('LOTTERY_TICKET_COST') ?: 10));

define('LOTTERY_PRIZE_POINTS', (int) (getenv('LOTTERY_PRIZE_POINTS') ?: 200));

define('ANTI_SPAM_MIN_INTERVAL_MS', (int) (getenv('ANTI_SPAM_MIN_INTERVAL_MS') ?: 700));

define('CRON_SECRET', getenv('CRON_SECRET') ?: '');

define('WEEKLY_TOP_REWARDS', (function () {
    // position => points; can be overridden with env WEEKLY_TOP_REWARDS like "1:300,2:200,3:100"
    $env = getenv('WEEKLY_TOP_REWARDS') ?: '';
    $defaults = [1 => 300, 2 => 200, 3 => 100];
    if (!$env) return $defaults;
    $out = [];
    foreach (explode(',', $env) as $pair) {
        $parts = array_map('trim', explode(':', $pair));
        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            $out[(int) $parts[0]] = (int) $parts[1];
        }
    }
    return $out ?: $defaults;
})());

// ==========================
// Utilities
// ==========================

function nowUtc(): string {
    return gmdate('Y-m-d H:i:s');
}

function todayUtc(): string {
    return gmdate('Y-m-d');
}

function msTimestamp(): int {
    $mt = microtime(true);
    return (int) round($mt * 1000);
}

function isAdmin(int $userId): bool {
    return in_array($userId, ADMIN_IDS, true);
}

function weekStartMondayUtc(?int $ts = null): string {
    // Returns Monday 00:00:00 of the week containing the timestamp, in UTC date string (Y-m-d)
    $ts = $ts ?? time();
    $dow = (int) gmdate('N', $ts); // 1..7, Monday=1
    $mondayTs = $ts - ($dow - 1) * 86400;
    return gmdate('Y-m-d', strtotime(gmdate('Y-m-d 00:00:00', $mondayTs)));
}

function previousWeekStartMondayUtc(?int $ts = null): string {
    $ts = $ts ?? time();
    $current = weekStartMondayUtc($ts);
    $prevTs = strtotime($current . ' 00:00:00') - 7 * 86400;
    return gmdate('Y-m-d', $prevTs);
}

function weekEndSundayUtcByStart(string $weekStartDate): string {
    $startTs = strtotime($weekStartDate . ' 00:00:00');
    $endTs = $startTs + 6 * 86400;
    return gmdate('Y-m-d', $endTs);
}

function sanitizeText(string $text, int $maxLen = 2000): string {
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    if (mb_strlen($text) > $maxLen) {
        $text = mb_substr($text, 0, $maxLen);
    }
    return $text;
}

// ==========================
// Database
// ==========================

function pdo(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

function ensureTables(): void {
    $sqls = [
        // Users table
        "CREATE TABLE IF NOT EXISTS users (
            user_id BIGINT PRIMARY KEY,
            username VARCHAR(64) NULL,
            first_name VARCHAR(64) NULL,
            last_name VARCHAR(64) NULL,
            points INT NOT NULL DEFAULT 0,
            referrals_count INT NOT NULL DEFAULT 0,
            referrer_id BIGINT NULL,
            pending_referrer_id BIGINT NULL,
            is_banned TINYINT NOT NULL DEFAULT 0,
            joined_at DATETIME NOT NULL,
            last_bonus_date DATE NULL,
            last_action_ts BIGINT NULL,
            level INT NOT NULL DEFAULT 1,
            INDEX idx_referrer_id (referrer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Channels (required subscriptions)
        "CREATE TABLE IF NOT EXISTS channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL UNIQUE,
            username VARCHAR(128) NULL,
            title VARCHAR(128) NULL,
            added_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Items (shop)
        "CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            cost_points INT NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Item requests
        "CREATE TABLE IF NOT EXISTS item_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            item_id INT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            admin_message_id BIGINT NULL,
            admin_chat_id BIGINT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Referrals
        "CREATE TABLE IF NOT EXISTS referrals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inviter_id BIGINT NOT NULL,
            invited_id BIGINT NOT NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            UNIQUE KEY unique_invited (invited_id),
            INDEX idx_inviter_id (inviter_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Lottery tickets (weekly legacy)
        "CREATE TABLE IF NOT EXISTS lottery_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            week_start_date DATE NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_week_user (week_start_date, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Lottery draws per week (legacy)
        "CREATE TABLE IF NOT EXISTS lottery_draws (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_start_date DATE NOT NULL UNIQUE,
            week_end_date DATE NOT NULL,
            winner_user_id BIGINT NULL,
            drawn_at DATETIME NULL,
            total_tickets INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Weekly referral rewards marker (legacy)
        "CREATE TABLE IF NOT EXISTS weekly_referral_rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_start_date DATE NOT NULL UNIQUE,
            rewarded_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Custom lotteries (admin-defined)
        "CREATE TABLE IF NOT EXISTS custom_lotteries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            entry_cost_points INT NULL,
            entry_requires_referral TINYINT NOT NULL DEFAULT 0,
            referral_bonus_per_invite INT NOT NULL DEFAULT 0,
            prize_points INT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            drawn_at DATETIME NULL,
            winner_user_id BIGINT NULL,
            total_tickets INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Custom lottery tickets (positive/negative deltas)
        "CREATE TABLE IF NOT EXISTS custom_lottery_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lottery_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            num_tickets INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_lottery_user (lottery_id, user_id),
            FOREIGN KEY (lottery_id) REFERENCES custom_lotteries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Logs to map referral tickets to invited users for revocation
        "CREATE TABLE IF NOT EXISTS custom_lottery_referral_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lottery_id INT NOT NULL,
            inviter_id BIGINT NOT NULL,
            invited_id BIGINT NOT NULL,
            tickets_awarded INT NOT NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            INDEX idx_inviter_invited (inviter_id, invited_id),
            FOREIGN KEY (lottery_id) REFERENCES custom_lotteries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    $pdo = pdo();
    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }

    // Attempt to add revoked_at to referrals if missing
    try { $pdo->exec("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS revoked_at DATETIME NULL"); } catch (Throwable $e) { /* ignore */ }
}

// ==========================
// Telegram API helpers
// ==========================

function apiRequest(string $method, array $params = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($errno) {
        return ['ok' => false, 'error' => $err];
    }
    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid JSON from Telegram'];
    }
    return $decoded;
}

function tgSendMessage(int $chatId, string $text, array $opts = []): array {
    $params = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], $opts);
    if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
        $params['reply_markup'] = json_encode($params['reply_markup']);
    }
    return apiRequest('sendMessage', $params);
}

function tgEditMessageText(int $chatId, int $messageId, string $text, array $opts = []): array {
    $params = array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], $opts);
    if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
        $params['reply_markup'] = json_encode($params['reply_markup']);
    }
    return apiRequest('editMessageText', $params);
}

function tgAnswerCallbackQuery(string $callbackId, string $text = '', bool $showAlert = false): array {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $showAlert,
    ]);
}

function tgGetChatMember($chatId, int $userId): array {
    return apiRequest('getChatMember', [
        'chat_id' => $chatId,
        'user_id' => $userId,
    ]);
}

function tgGetChat($chatIdOrUsername): array {
    return apiRequest('getChat', [
        'chat_id' => $chatIdOrUsername,
    ]);
}

function buildMainMenuKeyboard(bool $isAdmin): array {
    $keyboard = [
        ['📊 امتیاز من', '📎 لینک دعوت من'],
        ['🛒 فروشگاه آیتم‌ها', '📤 درخواست‌های من'],
        ['🎁 جایزه روزانه', '👤 پروفایل'],
        ['🏆 برترین‌ها', '🎲 قرعه‌کشی'],
    ];
    if ($isAdmin) {
        $keyboard[] = ['🛠 پنل ادمین'];
    }
    return [
        'keyboard' => array_map(function ($row) {
            return array_map(function ($btn) { return ['text' => $btn]; }, $row);
        }, $keyboard),
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
    ];
}

function buildVerifyChannelsInlineKeyboard(): array {
    $rows = [];
    $channels = listRequiredChannels();
    foreach ($channels as $ch) {
        $text = $ch['title'] ?: ($ch['username'] ? '@' . $ch['username'] : (string) $ch['chat_id']);
        if (!empty($ch['username'])) {
            $rows[] = [ [ 'text' => $text, 'url' => 'https://t.me/' . $ch['username'] ] ];
        } else {
            $rows[] = [ [ 'text' => $text, 'callback_data' => 'noop' ] ];
        }
    }
    $rows[] = [ [ 'text' => '✅ تایید عضویت', 'callback_data' => 'verify_sub' ] ];
    return [ 'inline_keyboard' => $rows ];
}

function buildShopItemKeyboard(array $items): array {
    $rows = [];
    foreach ($items as $item) {
        $rows[] = [
            [
                'text' => 'درخواست: ' . $item['name'] . ' (' . $item['cost_points'] . ' امتیاز)',
                'callback_data' => 'req_item_' . $item['id'],
            ]
        ];
    }
    if (empty($rows)) {
        $rows[] = [ ['text' => 'به‌روزرسانی', 'callback_data' => 'noop'] ];
    }
    return ['inline_keyboard' => $rows];
}

function buildAdminApproveRejectKeyboard(int $requestId): array {
    return [
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید', 'callback_data' => 'req_app_' . $requestId],
                ['text' => '❌ رد', 'callback_data' => 'req_rej_' . $requestId],
            ],
        ],
    ];
}

// ==========================
// Business Logic: Users
// ==========================

function upsertUserFromTelegram(array $tgUser): array {
    $pdo = pdo();
    $userId = (int) $tgUser['id'];
    $username = isset($tgUser['username']) ? sanitizeText($tgUser['username'], 64) : null;
    $firstName = isset($tgUser['first_name']) ? sanitizeText($tgUser['first_name'], 64) : null;
    $lastName = isset($tgUser['last_name']) ? sanitizeText($tgUser['last_name'], 64) : null;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, first_name = ?, last_name = ? WHERE user_id = ?');
        $stmt->execute([$username, $firstName, $lastName, $userId]);
        return $existing;
    }

    $stmt = $pdo->prepare('INSERT INTO users (user_id, username, first_name, last_name, points, referrals_count, joined_at, level) VALUES (?, ?, ?, ?, 0, 0, ?, 1)');
    $stmt->execute([$userId, $username, $firstName, $lastName, nowUtc()]);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function setPendingReferrerIfApplicable(int $userId, ?int $referrerId): void {
    if (!$referrerId || $referrerId === $userId) return;
    $pdo = pdo();
    // Only set pending if no permanent referrer already recorded and no referral exists
    $stmt = $pdo->prepare('SELECT referrer_id, pending_referrer_id FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return;

    if (!empty($row['referrer_id'])) return;

    // Ensure no prior referral credit exists
    $stmt = $pdo->prepare('SELECT 1 FROM referrals WHERE invited_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) return;

    if ((int) ($row['pending_referrer_id'] ?? 0) === $referrerId) return; // already pending same inviter

    $stmt = $pdo->prepare('UPDATE users SET pending_referrer_id = ? WHERE user_id = ?');
    $stmt->execute([$referrerId, $userId]);

    // Notify inviter about a new pending referral
    $invited = getUser($userId);
    $uname = ($invited && $invited['username']) ? '@' . $invited['username'] : (string) $userId;
    tgSendMessage($referrerId, 'ℹ️ کاربر ' . $uname . ' با لینک رفرال شما وارد شد. پس از تایید عضویت در کانال‌ها، امتیاز به شما اضافه خواهد شد.');
}

function ensureUserNotBanned(int $userId): bool {
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT is_banned FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int) $row['is_banned'] === 0;
}

function isRateLimited(int $userId): bool {
    if (ANTI_SPAM_MIN_INTERVAL_MS <= 0) return false;
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT last_action_ts FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $now = msTimestamp();
    $last = isset($row['last_action_ts']) ? (int) $row['last_action_ts'] : 0;
    if ($last > 0 && ($now - $last) < ANTI_SPAM_MIN_INTERVAL_MS) {
        return true;
    }
    $stmt = $pdo->prepare('UPDATE users SET last_action_ts = ? WHERE user_id = ?');
    $stmt->execute([$now, $userId]);
    return false;
}

function getUser(int $userId): ?array {
    $stmt = pdo()->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function addUserPoints(int $userId, int $points): void {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE users SET points = points + ? WHERE user_id = ?');
    $stmt->execute([$points, $userId]);
    updateUserLevel($userId);
}

function setUserPoints(int $userId, int $points): void {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE users SET points = ? WHERE user_id = ?');
    $stmt->execute([$points, $userId]);
    updateUserLevel($userId);
}

function getUserPoints(int $userId): int {
    $stmt = pdo()->prepare('SELECT points FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['points'] : 0;
}

function incUserReferralCount(int $userId): void {
    $stmt = pdo()->prepare('UPDATE users SET referrals_count = referrals_count + 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
}

function calculateLevelByPoints(int $points): int {
    // Example: 0-50 => 1, 51-200 => 2, 201-500 => 3, >500 => 4
    if ($points <= 50) return 1;
    if ($points <= 200) return 2;
    if ($points <= 500) return 3;
    return 4;
}

function updateUserLevel(int $userId): void {
    $points = getUserPoints($userId);
    $level = calculateLevelByPoints($points);
    $stmt = pdo()->prepare('UPDATE users SET level = ? WHERE user_id = ?');
    $stmt->execute([$level, $userId]);
}

function recordReferralIfEligibleAfterVerification(int $userId): void {
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT referrer_id, pending_referrer_id FROM users WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return; }

        if (!empty($row['referrer_id'])) { $pdo->rollBack(); return; }

        $pending = isset($row['pending_referrer_id']) ? (int) $row['pending_referrer_id'] : 0;
        if ($pending <= 0 || $pending === $userId) { $pdo->rollBack(); return; }

        // Ensure no prior referral recorded
        $stmt = $pdo->prepare('SELECT 1 FROM referrals WHERE invited_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) { $pdo->rollBack(); return; }

        // Finalize referral: set referrer_id, clear pending, credit points and count, insert referral record
        $stmt = $pdo->prepare('UPDATE users SET referrer_id = ?, pending_referrer_id = NULL WHERE user_id = ?');
        $stmt->execute([$pending, $userId]);

        $stmt = $pdo->prepare('INSERT INTO referrals (inviter_id, invited_id, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$pending, $userId, nowUtc()]);

        $stmt = $pdo->prepare('UPDATE users SET points = points + ?, referrals_count = referrals_count + 1 WHERE user_id = ?');
        $stmt->execute([REFERRAL_REWARD_POINTS, $pending]);

        updateUserLevel($pending);

        $pdo->commit();

        // Award tickets in active custom lotteries (if configured)
        awardReferralTicketsForActiveLotteries($pending, $userId);

        // Notify inviter about successful credit
        $invited = getUser($userId);
        $uname = ($invited && $invited['username']) ? '@' . $invited['username'] : (string) $userId;
        tgSendMessage($pending, '✅ عضویت ' . $uname . ' تایید شد و ' . REFERRAL_REWARD_POINTS . ' امتیاز به شما اضافه شد.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// ==========================
// Business Logic: Channels
// ==========================

function listRequiredChannels(): array {
    $stmt = pdo()->query('SELECT * FROM channels ORDER BY id ASC');
    return $stmt->fetchAll();
}

function isMemberAllRequiredChannels(int $userId): bool {
    $channels = listRequiredChannels();
    if (empty($channels)) return true; // No mandatory channels configured
    foreach ($channels as $ch) {
        $chatId = (int) $ch['chat_id'];
        $res = tgGetChatMember($chatId, $userId);
        if (!($res['ok'] ?? false)) return false;
        $status = $res['result']['status'] ?? '';
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) {
            return false;
        }
    }
    return true;
}

function formatChannelsJoinMessage(): string {
    $channels = listRequiredChannels();
    if (empty($channels)) return "هیچ کانال اجباری تنظیم نشده است.";
    $lines = ["لطفا ابتدا در کانال‌های زیر عضو شوید و سپس دکمه \"تایید عضویت\" را بزنید:"];
    return implode("\n", $lines);
}

function enforceMembershipGate(int $chatId, int $userId, bool $isAdmin): bool {
    if ($isAdmin) return true;
    if (isMemberAllRequiredChannels($userId)) return true;
    tryRevokeReferralIfNecessary($userId);
    tgSendMessage($chatId, formatChannelsJoinMessage(), [ 'reply_markup' => buildVerifyChannelsInlineKeyboard() ]);
    return false;
}

function tryRevokeReferralIfNecessary(int $invitedUserId): void {
    try {
        $pdo = pdo();
        // Check if referral was previously credited and not revoked yet
        $stmt = $pdo->prepare('SELECT inviter_id FROM referrals WHERE invited_id = ? AND (revoked_at IS NULL)');
        $stmt->execute([$invitedUserId]);
        $ref = $stmt->fetch();
        if (!$ref) return;
        $inviterId = (int) $ref['inviter_id'];

        $pdo->beginTransaction();
        // Mark referral revoked
        $upd = $pdo->prepare('UPDATE referrals SET revoked_at = ? WHERE invited_id = ? AND revoked_at IS NULL');
        $upd->execute([nowUtc(), $invitedUserId]);
        // Deduct points and decrement referral count (not below zero)
        $pdo->prepare('UPDATE users SET points = points - ?, referrals_count = GREATEST(referrals_count - 1, 0) WHERE user_id = ?')->execute([REFERRAL_REWARD_POINTS, $inviterId]);
        updateUserLevel($inviterId);
        $pdo->commit();

        // Revoke lottery referral tickets if any
        revokeReferralTicketsForActiveLotteries($inviterId, $invitedUserId);

        // Notify inviter
        $invited = getUser($invitedUserId);
        $uname = ($invited && $invited['username']) ? '@' . $invited['username'] : (string) $invitedUserId;
        tgSendMessage($inviterId, '⚠️ کاربر ' . $uname . ' عضویت خود را در کانال‌ها لغو کرد. امتیاز رفرال شما (' . REFERRAL_REWARD_POINTS . ' امتیاز) کسر شد.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    }
}

// ==========================
// Business Logic: Items & Requests
// ==========================

function listActiveItems(): array {
    $stmt = pdo()->prepare('SELECT * FROM items WHERE is_active = 1 ORDER BY id ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function getItemById(int $itemId): ?array {
    $stmt = pdo()->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function createItemRequest(int $userId, int $itemId): ?int {
    $pdo = pdo();
    $item = getItemById($itemId);
    if (!$item || (int) $item['is_active'] !== 1) return null;
    $cost = (int) $item['cost_points'];

    $pdo->beginTransaction();
    try {
        // Ensure user has enough points
        $stmt = $pdo->prepare('SELECT points FROM users WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) { $pdo->rollBack(); return null; }
        if ((int) $u['points'] < $cost) { $pdo->rollBack(); return -1; }

        // Deduct points immediately (reserve). Refund on reject
        $stmt = $pdo->prepare('UPDATE users SET points = points - ? WHERE user_id = ?');
        $stmt->execute([$cost, $userId]);

        $stmt = $pdo->prepare('INSERT INTO item_requests (user_id, item_id, status, created_at, updated_at) VALUES (?, ?, \'pending\', ?, ?)');
        $now = nowUtc();
        $stmt->execute([$userId, $itemId, $now, $now]);
        $requestId = (int) pdo()->lastInsertId();

        updateUserLevel($userId);

        $pdo->commit();
        return $requestId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }
}

function updateItemRequestAdminMessage(int $requestId, int $chatId, int $messageId): void {
    $stmt = pdo()->prepare('UPDATE item_requests SET admin_chat_id = ?, admin_message_id = ? WHERE id = ?');
    $stmt->execute([$chatId, $messageId, $requestId]);
}

function setItemRequestStatus(int $requestId, string $status): ?array {
    $status = in_array($status, ['pending','approved','rejected'], true) ? $status : 'pending';
    $stmt = pdo()->prepare('UPDATE item_requests SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$status, nowUtc(), $requestId]);
    $stmt = pdo()->prepare('SELECT r.*, i.name as item_name, i.cost_points, u.username FROM item_requests r JOIN items i ON i.id = r.item_id JOIN users u ON u.user_id = r.user_id WHERE r.id = ?');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchItemRequest(int $requestId): ?array {
    $stmt = pdo()->prepare('SELECT r.*, i.name as item_name, i.cost_points, u.username FROM item_requests r JOIN items i ON i.id = r.item_id JOIN users u ON u.user_id = r.user_id WHERE r.id = ?');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listUserRequests(int $userId, int $limit = 10): array {
    $stmt = pdo()->prepare('SELECT r.*, i.name as item_name, i.cost_points FROM item_requests r JOIN items i ON i.id = r.item_id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ==========================
// Business Logic: Daily Bonus
// ==========================

function tryGrantDailyBonus(int $userId): array {
    $stmt = pdo()->prepare('SELECT last_bonus_date FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $today = todayUtc();
    if ($row && $row['last_bonus_date'] === $today) {
        return [false, 'شما امروز جایزه روزانه را دریافت کرده‌اید.'];
    }
    $stmt = pdo()->prepare('UPDATE users SET points = points + ?, last_bonus_date = ? WHERE user_id = ?');
    $stmt->execute([DAILY_BONUS_POINTS, $today, $userId]);
    updateUserLevel($userId);
    return [true, '🎁 جایزه روزانه شما (' . DAILY_BONUS_POINTS . ' امتیاز) واریز شد.'];
}

// ==========================
// Business Logic: Weekly Top Referrals
// ==========================

function computeTopReferrersForWeek(string $weekStartDate, int $limit = 10): array {
    $weekEndDate = weekEndSundayUtcByStart($weekStartDate);
    $stmt = pdo()->prepare('SELECT inviter_id, COUNT(*) as invites FROM referrals WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) + INTERVAL 6 DAY + INTERVAL 1 SECOND GROUP BY inviter_id ORDER BY invites DESC, inviter_id ASC LIMIT ?');
    // Using date range: [weekStart 00:00:00, weekEnd 23:59:59]
    $stmt->bindValue(1, $weekStartDate . ' 00:00:00');
    $stmt->bindValue(2, $weekStartDate . ' 00:00:00');
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function runWeeklyReferralRewardsCron(): string {
    $weekStartPrev = previousWeekStartMondayUtc();
    // Check if rewarded already
    $stmt = pdo()->prepare('SELECT 1 FROM weekly_referral_rewards WHERE week_start_date = ?');
    $stmt->execute([$weekStartPrev]);
    if ($stmt->fetch()) {
        return 'پاداش‌های هفتگی قبلاً برای این هفته پرداخت شده است.';
    }

    $top = computeTopReferrersForWeek($weekStartPrev, 10);
    if (empty($top)) {
        // Record to avoid repeat checks
        $stmt = pdo()->prepare('INSERT INTO weekly_referral_rewards (week_start_date, rewarded_at) VALUES (?, ?)');
        $stmt->execute([$weekStartPrev, nowUtc()]);
        return 'هیچ دعوتی برای هفته قبل ثبت نشده است.';
    }

    $rewardsText = [];
    $pos = 1;
    foreach ($top as $row) {
        if (!isset(WEEKLY_TOP_REWARDS[$pos])) break;
        $userId = (int) $row['inviter_id'];
        $prize = (int) WEEKLY_TOP_REWARDS[$pos];
        addUserPoints($userId, $prize);
        $rewardsText[] = "#{$pos}) {$userId} ➜ +{$prize} امتیاز";
        $pos++;
    }

    $stmt = pdo()->prepare('INSERT INTO weekly_referral_rewards (week_start_date, rewarded_at) VALUES (?, ?)');
    $stmt->execute([$weekStartPrev, nowUtc()]);

    $announce = '🏆 نتایج مسابقه هفتگی (شروع: ' . $weekStartPrev . ")\n" . implode("\n", $rewardsText);

    if (PUBLIC_ANNOUNCE_CHANNEL_ID) {
        tgSendMessage(PUBLIC_ANNOUNCE_CHANNEL_ID, $announce);
    }
    if (ADMIN_GROUP_ID) {
        tgSendMessage(ADMIN_GROUP_ID, $announce);
    }

    return 'پاداش‌های هفتگی پرداخت شد.\n' . $announce;
}

// ==========================
// Business Logic: Lottery
// ==========================

function buyLotteryTicket(int $userId): array {
    $weekStart = weekStartMondayUtc();
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT points FROM users WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) { $pdo->rollBack(); return [false, 'کاربر یافت نشد.']; }
        if ((int) $u['points'] < LOTTERY_TICKET_COST) { $pdo->rollBack(); return [false, 'امتیاز کافی برای خرید بلیت ندارید.']; }
        $stmt = $pdo->prepare('UPDATE users SET points = points - ? WHERE user_id = ?');
        $stmt->execute([LOTTERY_TICKET_COST, $userId]);
        $stmt = $pdo->prepare('INSERT INTO lottery_tickets (user_id, week_start_date, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $weekStart, nowUtc()]);
        updateUserLevel($userId);
        $pdo->commit();
        return [true, '🎟 یک بلیت قرعه‌کشی برای هفته جاری خریداری شد.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'خطا در خرید بلیت.'];
    }
}

function runWeeklyLotteryDrawCron(): string {
    $weekStartPrev = previousWeekStartMondayUtc();
    $weekEndPrev = weekEndSundayUtcByStart($weekStartPrev);

    // Check if draw already done
    $stmt = pdo()->prepare('SELECT 1 FROM lottery_draws WHERE week_start_date = ? AND drawn_at IS NOT NULL');
    $stmt->execute([$weekStartPrev]);
    if ($stmt->fetch()) return 'قرعه‌کشی هفته گذشته قبلاً انجام شده است.';

    // Count tickets
    $stmt = pdo()->prepare('SELECT COUNT(*) as cnt FROM lottery_tickets WHERE week_start_date = ?');
    $stmt->execute([$weekStartPrev]);
    $count = (int) ($stmt->fetch()['cnt'] ?? 0);

    $pdo = pdo();
    if ($count <= 0) {
        // Record empty draw
        $stmt = $pdo->prepare('INSERT INTO lottery_draws (week_start_date, week_end_date, winner_user_id, drawn_at, total_tickets) VALUES (?, ?, NULL, ?, 0) ON DUPLICATE KEY UPDATE drawn_at = VALUES(drawn_at), total_tickets = VALUES(total_tickets)');
        $stmt->execute([$weekStartPrev, $weekEndPrev, nowUtc()]);
        return 'هیچ بلیتی برای هفته گذشته ثبت نشده است.';
    }

    // Pick random winner
    $stmt = $pdo->prepare('SELECT user_id FROM lottery_tickets WHERE week_start_date = ? ORDER BY RAND() LIMIT 1');
    $stmt->execute([$weekStartPrev]);
    $winner = (int) ($stmt->fetch()['user_id'] ?? 0);

    if ($winner > 0) {
        addUserPoints($winner, LOTTERY_PRIZE_POINTS);
        $stmt = $pdo->prepare('INSERT INTO lottery_draws (week_start_date, week_end_date, winner_user_id, drawn_at, total_tickets) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE winner_user_id = VALUES(winner_user_id), drawn_at = VALUES(drawn_at), total_tickets = VALUES(total_tickets)');
        $stmt->execute([$weekStartPrev, $weekEndPrev, $winner, nowUtc(), $count]);
        $msg = '🎲 قرعه‌کشی هفته ' . $weekStartPrev . ' تا ' . $weekEndPrev . "\n" . 'برنده: ' . $winner . ' (+ ' . LOTTERY_PRIZE_POINTS . ' امتیاز)';
        if (PUBLIC_ANNOUNCE_CHANNEL_ID) tgSendMessage(PUBLIC_ANNOUNCE_CHANNEL_ID, $msg);
        if (ADMIN_GROUP_ID) tgSendMessage(ADMIN_GROUP_ID, $msg);
        return 'قرعه‌کشی انجام شد. ' . $msg;
    }

    return 'قرعه‌کشی با خطا مواجه شد.';
}

// Custom lotteries (admin-defined)
function listActiveCustomLotteries(): array {
    $stmt = pdo()->prepare('SELECT * FROM custom_lotteries WHERE is_active = 1 AND drawn_at IS NULL ORDER BY id DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCustomLottery(int $lotteryId): ?array {
    $stmt = pdo()->prepare('SELECT * FROM custom_lotteries WHERE id = ?');
    $stmt->execute([$lotteryId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buildLotteriesKeyboard(array $lots): array {
    $rows = [];
    foreach ($lots as $l) {
        $title = $l['title'];
        if (!is_null($l['entry_cost_points']) && (int)$l['entry_cost_points'] > 0) {
            $rows[] = [ [ 'text' => '🎟 ' . $title . ' (هزینه ' . $l['entry_cost_points'] . ')', 'callback_data' => 'lot_buy_' . $l['id'] ] ];
        } else {
            $rows[] = [ [ 'text' => 'ℹ️ ' . $title . ' (ورود با رفرال)', 'callback_data' => 'lot_info_' . $l['id'] ] ];
        }
    }
    if (empty($rows)) $rows[] = [ [ 'text' => 'به‌روزرسانی', 'callback_data' => 'noop' ] ];
    return [ 'inline_keyboard' => $rows ];
}

function buyCustomLotteryTicket(int $userId, array $lottery): array {
    $cost = (int) ($lottery['entry_cost_points'] ?? 0);
    if ($cost <= 0) return [false, 'ورود این قرعه‌کشی از طریق رفرال است.'];
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT points FROM users WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) { $pdo->rollBack(); return [false, 'کاربر یافت نشد.']; }
        if ((int) $u['points'] < $cost) { $pdo->rollBack(); return [false, 'امتیاز کافی ندارید.']; }
        $pdo->prepare('UPDATE users SET points = points - ? WHERE user_id = ?')->execute([$cost, $userId]);
        $pdo->prepare('INSERT INTO custom_lottery_tickets (lottery_id, user_id, num_tickets, created_at) VALUES (?, ?, ?, ?)')->execute([$lottery['id'], $userId, 1, nowUtc()]);
        updateUserLevel($userId);
        $pdo->commit();
        return [true, '🎟 یک بلیت برای «' . $lottery['title'] . '» خریداری شد.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'خطا در خرید بلیت.'];
    }
}

function awardReferralTicketsForActiveLotteries(int $inviterId, int $invitedId): void {
    try {
        $lots = listActiveCustomLotteries();
        foreach ($lots as $l) {
            $tickets = 0;
            if ((int)$l['entry_requires_referral'] === 1) { $tickets += 1; }
            if ((int)$l['referral_bonus_per_invite'] > 0) { $tickets += (int)$l['referral_bonus_per_invite']; }
            if ($tickets <= 0) continue;
            $pdo = pdo();
            $pdo->prepare('INSERT INTO custom_lottery_tickets (lottery_id, user_id, num_tickets, created_at) VALUES (?, ?, ?, ?)')->execute([$l['id'], $inviterId, $tickets, nowUtc()]);
            $pdo->prepare('INSERT INTO custom_lottery_referral_logs (lottery_id, inviter_id, invited_id, tickets_awarded, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$l['id'], $inviterId, $invitedId, $tickets, nowUtc()]);
        }
    } catch (Throwable $e) { /* ignore */ }
}

function revokeReferralTicketsForActiveLotteries(int $inviterId, int $invitedId): void {
    try {
        $pdo = pdo();
        $stmt = $pdo->prepare('SELECT * FROM custom_lottery_referral_logs WHERE inviter_id = ? AND invited_id = ? AND revoked_at IS NULL');
        $stmt->execute([$inviterId, $invitedId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $tickets = (int) $r['tickets_awarded'];
            if ($tickets <= 0) continue;
            $pdo->prepare('INSERT INTO custom_lottery_tickets (lottery_id, user_id, num_tickets, created_at) VALUES (?, ?, ?, ?)')->execute([$r['lottery_id'], $inviterId, -$tickets, nowUtc()]);
            $pdo->prepare('UPDATE custom_lottery_referral_logs SET revoked_at = ? WHERE id = ?')->execute([nowUtc(), $r['id']]);
        }
    } catch (Throwable $e) { /* ignore */ }
}

function adminLotteryCreate(string $title, $costSpec, int $prizePoints, int $bonus): string {
    $title = sanitizeText($title, 255);
    $entryCostPoints = null;
    $requiresReferral = 0;
    if ($costSpec === 'ref') {
        $requiresReferral = 1;
    } else {
        $entryCostPoints = max(0, (int) $costSpec);
    }
    $stmt = pdo()->prepare('INSERT INTO custom_lotteries (title, entry_cost_points, entry_requires_referral, referral_bonus_per_invite, prize_points, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
    $stmt->execute([$title, $entryCostPoints, $requiresReferral, max(0,$bonus), $prizePoints, nowUtc()]);
    $id = (int) pdo()->lastInsertId();
    return '🎲 قرعه‌کشی #' . $id . ' با عنوان «' . $title . '» ایجاد شد.';
}

function adminLotteryList(): string {
    $stmt = pdo()->prepare('SELECT * FROM custom_lotteries ORDER BY id DESC LIMIT 20');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (empty($rows)) return 'هیچ قرعه‌کشی‌ای ثبت نشده است.';
    $lines = ['🎲 لیست قرعه‌کشی‌ها:'];
    foreach ($rows as $r) {
        $cost = is_null($r['entry_cost_points']) ? 'ref' : $r['entry_cost_points'];
        $status = ((int)$r['is_active'] === 1 && is_null($r['drawn_at'])) ? 'فعال' : (is_null($r['drawn_at']) ? 'بسته' : 'پایان یافته');
        $lines[] = '#' . $r['id'] . ' | ' . $r['title'] . ' | cost=' . $cost . ' | prize=' . $r['prize_points'] . ' | bonus=' . $r['referral_bonus_per_invite'] . ' | ' . $status;
    }
    return implode("\n", $lines);
}

function adminLotteryClose(int $lotteryId): string {
    $stmt = pdo()->prepare('UPDATE custom_lotteries SET is_active = 0, closed_at = ? WHERE id = ?');
    $stmt->execute([nowUtc(), $lotteryId]);
    return 'قرعه‌کشی #' . $lotteryId . ' بسته شد.';
}

function adminLotteryDraw(int $lotteryId): string {
    $lot = getCustomLottery($lotteryId);
    if (!$lot) return 'قرعه‌کشی یافت نشد.';
    // Sum tickets by user
    $stmt = pdo()->prepare('SELECT user_id, SUM(num_tickets) as t FROM custom_lottery_tickets WHERE lottery_id = ? GROUP BY user_id HAVING t > 0 ORDER BY user_id ASC');
    $stmt->execute([$lotteryId]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) return 'هیچ بلیتی برای این قرعه‌کشی ثبت نشده است.';
    $total = 0; foreach ($rows as $r) { $total += (int)$r['t']; }
    $rand = random_int(1, $total);
    $acc = 0; $winner = 0;
    foreach ($rows as $r) { $acc += (int)$r['t']; if ($acc >= $rand) { $winner = (int)$r['user_id']; break; } }
    if ($winner <= 0) return 'خطا در انتخاب برنده.';
    // Prize points
    if ((int)$lot['prize_points'] > 0) { addUserPoints($winner, (int)$lot['prize_points']); }
    pdo()->prepare('UPDATE custom_lotteries SET drawn_at = ?, winner_user_id = ?, total_tickets = ? WHERE id = ?')->execute([nowUtc(), $winner, $total, $lotteryId]);
    $msg = '🎲 برنده قرعه‌کشی #' . $lotteryId . ' («' . $lot['title'] . '») کاربر ' . $winner . ' است.';
    if (PUBLIC_ANNOUNCE_CHANNEL_ID) tgSendMessage(PUBLIC_ANNOUNCE_CHANNEL_ID, $msg);
    if (ADMIN_GROUP_ID) tgSendMessage(ADMIN_GROUP_ID, $msg);
    return $msg;
}

// ==========================
// Business Logic: Admin Ops
// ==========================

function adminHelpText(): string {
    return implode("\n", [
        '🛠 راهنمای ادمین:',
        '/add_item نام | هزینه',
        '/del_item ID',
        '/items_list',
        '/channels_add @username یا -100...',
        '/channels_list',
        '/channels_del chat_id',
        '/users_list [page]',
        '/set_points user_id amount',
        '/add_points user_id amount',
        '/sub_points user_id amount',
        '/ban user_id',
        '/unban user_id',
        '/cron_weekly  (پاداش تاپ رفرال هفته قبل)',
        '/cron_lottery (قرعه‌کشی هفته قبل)',
        '/lottery_create عنوان | cost=10|ref | prize=200 | bonus=0',
        '/lottery_list',
        '/lottery_close ID',
        '/lottery_draw ID',
    ]);
}

function adminAddItem(string $name, int $cost): string {
    $stmt = pdo()->prepare('INSERT INTO items (name, cost_points, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute([sanitizeText($name, 128), $cost, nowUtc(), nowUtc()]);
    return 'آیتم با موفقیت افزوده شد.';
}

function adminDeleteItem(int $id): string {
    $stmt = pdo()->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$id]);
    return 'آیتم حذف شد (اگر وجود داشت).';
}

function adminItemsList(): string {
    $items = listActiveItems();
    if (empty($items)) return 'هیچ آیتم فعالی وجود ندارد.';
    $lines = ['🛒 آیتم‌ها:'];
    foreach ($items as $i) {
        $lines[] = $i['id'] . ') ' . $i['name'] . ' - ' . $i['cost_points'] . ' امتیاز';
    }
    return implode("\n", $lines);
}

function adminChannelsAdd(string $identifier): string {
    $identifier = trim($identifier);
    if ($identifier === '') return 'ورودی نامعتبر.';

    // If username like @channel
    if ($identifier[0] === '@') {
        $username = ltrim($identifier, '@');
        $res = tgGetChat('@' . $username);
        if (!($res['ok'] ?? false)) return 'عدم دسترسی یا کانال یافت نشد. ابتدا ربات را ادمین کانال کنید.';
        $chat = $res['result'];
        $chatId = (int) ($chat['id'] ?? 0);
        $title = $chat['title'] ?? $username;
        $stmt = pdo()->prepare('INSERT INTO channels (chat_id, username, title, added_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE username = VALUES(username), title = VALUES(title)');
        $stmt->execute([$chatId, $username, $title, nowUtc()]);
        return 'کانال افزوده شد: ' . $title . ' (' . $chatId . ')';
    }

    // Numeric chat id
    if (preg_match('/^-?\d+$/', $identifier)) {
        $chatId = (int) $identifier;
        $title = null;
        $res = tgGetChat($chatId);
        if ($res['ok'] ?? false) {
            $title = $res['result']['title'] ?? null;
        }
        $stmt = pdo()->prepare('INSERT INTO channels (chat_id, username, title, added_at) VALUES (?, NULL, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title)');
        $stmt->execute([$chatId, $title, nowUtc()]);
        return 'کانال/گروه افزوده شد: ' . ($title ?: $chatId) . ' (' . $chatId . ')';
    }

    return 'شناسه کانال نامعتبر است.';
}

function adminChannelsList(): string {
    $chs = listRequiredChannels();
    if (empty($chs)) return 'لیستی تنظیم نشده است.';
    $lines = ['📢 کانال‌های اجباری:'];
    foreach ($chs as $c) {
        $lines[] = ($c['id']) . ') ' . ($c['title'] ?: ($c['username'] ? '@' . $c['username'] : $c['chat_id'])) . ' [' . $c['chat_id'] . ']';
    }
    return implode("\n", $lines);
}

function adminChannelsDel($chatId): string {
    if (!preg_match('/^-?\d+$/', (string) $chatId)) return 'chat_id نامعتبر است.';
    $stmt = pdo()->prepare('DELETE FROM channels WHERE chat_id = ?');
    $stmt->execute([(int) $chatId]);
    return 'حذف شد (اگر وجود داشت).';
}

function adminUsersList(int $page = 1, int $pageSize = 20): string {
    $offset = max(0, ($page - 1) * $pageSize);
    $stmt = pdo()->prepare('SELECT user_id, username, points, referrals_count, level FROM users ORDER BY points DESC, user_id ASC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (empty($rows)) return 'کاربری یافت نشد.';
    $lines = ["📊 لیست کاربران (صفحه {$page}):"];
    foreach ($rows as $r) {
        $uname = $r['username'] ? '@' . $r['username'] : '-';
        $lines[] = $r['user_id'] . ' | ' . $uname . ' | امتیاز: ' . $r['points'] . ' | زیرمجموعه: ' . $r['referrals_count'] . ' | لول: ' . $r['level'];
    }
    return implode("\n", $lines);
}

function adminSetPoints(int $userId, int $amount): string {
    setUserPoints($userId, $amount);
    return 'امتیاز کاربر ' . $userId . ' تنظیم شد به ' . $amount . '.';
}

function adminAddPoints(int $userId, int $amount): string {
    addUserPoints($userId, $amount);
    return 'به کاربر ' . $userId . ' ' . $amount . ' امتیاز افزوده شد.';
}

function adminSubPoints(int $userId, int $amount): string {
    addUserPoints($userId, -abs($amount));
    return 'از کاربر ' . $userId . ' ' . $amount . ' امتیاز کسر شد.';
}

function adminBanUser(int $userId): string {
    $stmt = pdo()->prepare('UPDATE users SET is_banned = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
    return 'کاربر بن شد.';
}

function adminUnbanUser(int $userId): string {
    $stmt = pdo()->prepare('UPDATE users SET is_banned = 0 WHERE user_id = ?');
    $stmt->execute([$userId]);
    return 'کاربر آنبن شد.';
}

// ==========================
// Message Builders
// ==========================

function userProfileText(array $user): string {
    $uname = $user['username'] ? '@' . $user['username'] : '-';
    return '👤 پروفایل شما' . "\n" .
        'شناسه: ' . $user['user_id'] . "\n" .
        'نام کاربری: ' . $uname . "\n" .
        'امتیاز: ' . $user['points'] . "\n" .
        'لول: ' . $user['level'] . "\n" .
        'تعداد دعوتی: ' . $user['referrals_count'];
}

function myInviteLink(int $userId): string {
    return '📎 لینک دعوت شما:' . "\n" . 'https://t.me/' . (getenv('BOT_USERNAME') ?: 'YourBot') . '?start=' . $userId;
}

function shopText(): string {
    return '🛒 فروشگاه آیتم‌ها (برای درخواست روی دکمه‌ها بزنید):';
}

function channelsText(): string {
    return formatChannelsJoinMessage();
}

function lotteryInfoText(): string {
    $weekStart = weekStartMondayUtc();
    return '🎲 قرعه‌کشی هفتگی' . "\n" .
        'هزینه هر بلیت: ' . LOTTERY_TICKET_COST . ' امتیاز' . "\n" .
        'جایزه: ' . LOTTERY_PRIZE_POINTS . ' امتیاز' . "\n" .
        'هفته جاری شروع: ' . $weekStart;
}

// ==========================
// Update Handling
// ==========================

ensureTables();

if (isset($_GET['cron'])) {
    $secret = $_GET['secret'] ?? '';
    if (CRON_SECRET !== '' && $secret !== CRON_SECRET) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
    $cron = $_GET['cron'];
    if ($cron === 'weekly_referrals') {
        echo runWeeklyReferralRewardsCron();
    } elseif ($cron === 'weekly_lottery') {
        echo runWeeklyLotteryDrawCron();
    } else {
        echo 'unknown cron';
    }
    exit;
}

$updateRaw = file_get_contents('php://input');
if (!$updateRaw) {
    echo 'OK';
    exit;
}
$update = json_decode($updateRaw, true);
if (!is_array($update)) { echo 'OK'; exit; }

$pdo = pdo();

$chatId = null;
$userId = null;
$messageId = null;
$callbackId = null;
$data = null;
$messageText = null;
$tgUser = null;

if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $callbackId = $cb['id'];
    $message = $cb['message'] ?? [];
    $chatId = isset($message['chat']['id']) ? (int) $message['chat']['id'] : null;
    $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;
    $data = $cb['data'] ?? null;
    $tgUser = $cb['from'] ?? null;
} elseif (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = isset($msg['chat']['id']) ? (int) $msg['chat']['id'] : null;
    $messageId = isset($msg['message_id']) ? (int) $msg['message_id'] : null;
    $messageText = isset($msg['text']) ? trim((string) $msg['text']) : null;
    $tgUser = $msg['from'] ?? null;
}

if (!$tgUser || !$chatId) { echo 'OK'; exit; }

$userId = (int) $tgUser['id'];
$userRow = upsertUserFromTelegram($tgUser);

if (!ensureUserNotBanned($userId)) {
    tgSendMessage($chatId, 'شما بن شده‌اید.');
    exit;
}

if (isRateLimited($userId)) {
    echo 'OK';
    exit;
}

$isAdminUser = isAdmin($userId);

// Process callback queries
if ($callbackId && $data !== null) {
    // Gating for user callbacks in private chat (except verify/noop)
    if ($chatId === $userId && !$isAdminUser && $data !== 'verify_sub' && $data !== 'noop') {
        if (!enforceMembershipGate($chatId, $userId, false)) { echo 'OK'; exit; }
    }
    if ($data === 'verify_sub') {
        if (isMemberAllRequiredChannels($userId)) {
            recordReferralIfEligibleAfterVerification($userId);
            tgAnswerCallbackQuery($callbackId, 'عضویت تایید شد ✅');
            tgSendMessage($chatId, '✅ عضویت شما تایید شد. از منو یکی از گزینه‌ها را انتخاب کنید.', [
                'reply_markup' => buildMainMenuKeyboard($isAdminUser),
            ]);
        } else {
            tgAnswerCallbackQuery($callbackId, 'هنوز عضو همه کانال‌ها نشده‌اید.', true);
        }
        echo 'OK';
        exit;
    }

    if (strpos($data, 'req_item_') === 0) {
        $itemId = (int) substr($data, strlen('req_item_'));
        $reqId = createItemRequest($userId, $itemId);
        if ($reqId === -1) {
            tgAnswerCallbackQuery($callbackId, 'امتیاز کافی ندارید.', true);
            exit;
        } elseif (!$reqId) {
            tgAnswerCallbackQuery($callbackId, 'خطا در ثبت درخواست.', true);
            exit;
        }
        $item = getItemById($itemId);
        $text = 'درخواست شما ثبت شد و به ادمین ارسال گردید.\n' . 'آیتم: ' . $item['name'] . ' | هزینه: ' . $item['cost_points'] . ' امتیاز';
        tgAnswerCallbackQuery($callbackId, 'درخواست ثبت شد.');
        tgSendMessage($chatId, $text);

        if (ADMIN_GROUP_ID) {
            $user = getUser($userId);
            $uname = $user['username'] ? '@' . $user['username'] : '-';
            $adminText = 'درخواست جدید آیتم:\n' .
                'کاربر: ' . $uname . ' (' . $userId . ")\n" .
                'آیتم: 🎁 ' . $item['name'] . "+\n" .
                'وضعیت: در حال بررسی';
            $sent = tgSendMessage(ADMIN_GROUP_ID, $adminText, [ 'reply_markup' => buildAdminApproveRejectKeyboard($reqId) ]);
            if (($sent['ok'] ?? false) && isset($sent['result']['message_id'])) {
                updateItemRequestAdminMessage($reqId, ADMIN_GROUP_ID, (int) $sent['result']['message_id']);
            }
        }
        exit;
    }

    if (strpos($data, 'req_app_') === 0 || strpos($data, 'req_rej_') === 0) {
        $isApprove = strpos($data, 'req_app_') === 0;
        $requestId = (int) substr($data, $isApprove ? strlen('req_app_') : strlen('req_rej_'));
        if (!$isAdminUser) {
            tgAnswerCallbackQuery($callbackId, 'فقط ادمین می‌تواند این کار را انجام دهد.', true);
            exit;
        }
        $req = fetchItemRequest($requestId);
        if (!$req) { tgAnswerCallbackQuery($callbackId, 'درخواست یافت نشد.', true); exit; }
        if ($req['status'] !== 'pending') { tgAnswerCallbackQuery($callbackId, 'این درخواست قبلا بررسی شده است.', true); exit; }

        if ($isApprove) {
            $row = setItemRequestStatus($requestId, 'approved');
            tgAnswerCallbackQuery($callbackId, 'درخواست تایید شد.');
            // Notify user
            tgSendMessage((int) $row['user_id'], '✅ درخواست شما برای آیتم: ' . $row['item_name'] . ' تایید شد.');
            // Update admin message
            if (!empty($row['admin_chat_id']) && !empty($row['admin_message_id'])) {
                tgEditMessageText((int) $row['admin_chat_id'], (int) $row['admin_message_id'], 'درخواست تایید شد.\nکاربر: @' . ($row['username'] ?: '-') . ' (' . $row['user_id'] . ")\n" . 'آیتم: ' . $row['item_name'] . "\n" . 'وضعیت: ✅ تایید');
            }
        } else {
            $row = setItemRequestStatus($requestId, 'rejected');
            tgAnswerCallbackQuery($callbackId, 'درخواست رد شد.');
            // Refund points
            addUserPoints((int) $row['user_id'], (int) $row['cost_points']);
            // Notify user
            tgSendMessage((int) $row['user_id'], '❌ درخواست شما برای آیتم: ' . $row['item_name'] . ' رد شد. امتیاز شما بازگشت داده شد.');
            // Update admin message
            if (!empty($row['admin_chat_id']) && !empty($row['admin_message_id'])) {
                tgEditMessageText((int) $row['admin_chat_id'], (int) $row['admin_message_id'], 'درخواست رد شد.\nکاربر: @' . ($row['username'] ?: '-') . ' (' . $row['user_id'] . ")\n" . 'آیتم: ' . $row['item_name'] . "\n" . 'وضعیت: ❌ رد');
            }
        }
        exit;
    }

    if ($data === 'noop') {
        tgAnswerCallbackQuery($callbackId, '');
        exit;
    }

    // Top menus
    if ($data === 'top_ref') {
        $stmt = pdo()->prepare('SELECT user_id, username, referrals_count FROM users ORDER BY referrals_count DESC, user_id ASC LIMIT 10');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) { tgAnswerCallbackQuery($callbackId, 'داده‌ای نیست'); exit; }
        $lines = ['👥 برترین‌های رفرال (همیشگی):'];
        $rank = 1;
        foreach ($rows as $r) {
            $uname = $r['username'] ? '@' . $r['username'] : (string)$r['user_id'];
            $lines[] = $rank . ' - ' . $uname . ' : ' . $r['referrals_count'] . ' 👤';
            $rank++;
        }
        tgEditMessageText($chatId, $messageId, implode("\n", $lines), [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '⬅️ بازگشت', 'callback_data' => 'top_back' ] ] ] ] ]);
        exit;
    }
    if ($data === 'top_pts') {
        $stmt = pdo()->prepare('SELECT user_id, username, points FROM users ORDER BY points DESC, user_id ASC LIMIT 10');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) { tgAnswerCallbackQuery($callbackId, 'داده‌ای نیست'); exit; }
        $lines = ['⭐ برترین‌های امتیاز (همیشگی):'];
        $rank = 1;
        foreach ($rows as $r) {
            $uname = $r['username'] ? '@' . $r['username'] : (string)$r['user_id'];
            $lines[] = $rank . ' - ' . $uname . ' : ' . $r['points'] . ' ⭐';
            $rank++;
        }
        tgEditMessageText($chatId, $messageId, implode("\n", $lines), [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '⬅️ بازگشت', 'callback_data' => 'top_back' ] ] ] ] ]);
        exit;
    }
    if ($data === 'top_back') {
        tgEditMessageText($chatId, $messageId, 'یک گزینه را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '👥 برترین‌های رفرال', 'callback_data' => 'top_ref' ], [ 'text' => '⭐ برترین‌های امتیاز', 'callback_data' => 'top_pts' ] ] ] ] ]);
        exit;
    }

    // Custom lottery callbacks
    if (strpos($data, 'lot_buy_') === 0) {
        $lotId = (int) substr($data, strlen('lot_buy_'));
        $lot = getCustomLottery($lotId);
        if (!$lot) { tgAnswerCallbackQuery($callbackId, 'قرعه‌کشی یافت نشد.', true); exit; }
        [$ok, $msg] = buyCustomLotteryTicket($userId, $lot);
        tgAnswerCallbackQuery($callbackId, $ok ? 'انجام شد' : $msg, !$ok);
        if ($ok) tgSendMessage($chatId, $msg);
        exit;
    }
    if (strpos($data, 'lot_info_') === 0) {
        $lotId = (int) substr($data, strlen('lot_info_'));
        $lot = getCustomLottery($lotId);
        if (!$lot) { tgAnswerCallbackQuery($callbackId, 'قرعه‌کشی یافت نشد.', true); exit; }
        $cost = is_null($lot['entry_cost_points']) ? 'ورود با رفرال' : ('هزینه: ' . $lot['entry_cost_points']);
        $bonus = (int)$lot['referral_bonus_per_invite'];
        $txt = '🎲 ' . $lot['title'] . "\n" . $cost . "\n" . 'جایزه: ' . $lot['prize_points'] . ' امتیاز' . ($bonus > 0 ? "\n" . '🎁 بلیط اضافه به ازای هر دعوت: ' . $bonus : '');
        tgAnswerCallbackQuery($callbackId, '');
        tgSendMessage($chatId, $txt);
        exit;
    }

    // Unknown callback
    tgAnswerCallbackQuery($callbackId, 'دستور نامعتبر');
    exit;
}

// Process text messages
if ($messageText !== null) {
    // Handle /start with optional parameter
    if (strpos($messageText, '/start') === 0) {
        $parts = explode(' ', $messageText, 2);
        if (isset($parts[1])) {
            $refParam = trim($parts[1]);
            if (preg_match('/^-?\d+$/', $refParam)) {
                setPendingReferrerIfApplicable($userId, (int) $refParam);
            }
        }

        if (!isMemberAllRequiredChannels($userId)) {
            tgSendMessage($chatId, formatChannelsJoinMessage(), [ 'reply_markup' => buildVerifyChannelsInlineKeyboard() ]);
        } else {
            recordReferralIfEligibleAfterVerification($userId);
            tgSendMessage($chatId, 'به ربات خوش آمدید! از منو انتخاب کنید.', [ 'reply_markup' => buildMainMenuKeyboard($isAdminUser) ]);
        }
        exit;
    }

    // Admin panel
    if ($messageText === '🛠 پنل ادمین' && $isAdminUser) {
        tgSendMessage($chatId, adminHelpText());
        exit;
    }

    // Admin commands
    if ($isAdminUser && substr($messageText, 0, 1) === '/') {
        $reply = null;
        if (preg_match('/^\/add_item\s+(.+)\|(\s*\d+)$/u', $messageText, $m)) {
            $name = trim($m[1]);
            $cost = (int) trim($m[2]);
            $reply = adminAddItem($name, $cost);
        } elseif (preg_match('/^\/del_item\s+(\d+)/', $messageText, $m)) {
            $reply = adminDeleteItem((int) $m[1]);
        } elseif (preg_match('/^\/items_list$/', $messageText)) {
            $reply = adminItemsList();
        } elseif (preg_match('/^\/channels_add\s+(.+)/', $messageText, $m)) {
            $reply = adminChannelsAdd(trim($m[1]));
        } elseif (preg_match('/^\/channels_list$/', $messageText)) {
            $reply = adminChannelsList();
        } elseif (preg_match('/^\/channels_del\s+(-?\d+)/', $messageText, $m)) {
            $reply = adminChannelsDel($m[1]);
        } elseif (preg_match('/^\/users_list(?:\s+(\d+))?$/', $messageText, $m)) {
            $page = isset($m[1]) ? (int) $m[1] : 1;
            $reply = adminUsersList($page);
        } elseif (preg_match('/^\/set_points\s+(\d+)\s+(-?\d+)/', $messageText, $m)) {
            $reply = adminSetPoints((int) $m[1], (int) $m[2]);
        } elseif (preg_match('/^\/add_points\s+(\d+)\s+(-?\d+)/', $messageText, $m)) {
            $reply = adminAddPoints((int) $m[1], (int) $m[2]);
        } elseif (preg_match('/^\/sub_points\s+(\d+)\s+(-?\d+)/', $messageText, $m)) {
            $reply = adminSubPoints((int) $m[1], (int) $m[2]);
        } elseif (preg_match('/^\/ban\s+(\d+)/', $messageText, $m)) {
            $reply = adminBanUser((int) $m[1]);
        } elseif (preg_match('/^\/unban\s+(\d+)/', $messageText, $m)) {
            $reply = adminUnbanUser((int) $m[1]);
        } elseif (preg_match('/^\/cron_weekly$/', $messageText)) {
            $reply = runWeeklyReferralRewardsCron();
        } elseif (preg_match('/^\/cron_lottery$/', $messageText)) {
            $reply = runWeeklyLotteryDrawCron();
        } elseif (preg_match('/^\/lottery_create\s+(.+)\|\s*cost=(ref|\d+)\s*\|\s*prize=(\d+)\s*(?:\|\s*bonus=(\d+))?$/u', $messageText, $m)) {
            $title = trim($m[1]);
            $costSpec = $m[2] === 'ref' ? 'ref' : (int)$m[2];
            $prize = (int)$m[3];
            $bonus = isset($m[4]) ? (int)$m[4] : 0;
            $reply = adminLotteryCreate($title, $costSpec, $prize, $bonus);
        } elseif (preg_match('/^\/lottery_list$/', $messageText)) {
            $reply = adminLotteryList();
        } elseif (preg_match('/^\/lottery_close\s+(\d+)/', $messageText, $m)) {
            $reply = adminLotteryClose((int)$m[1]);
        } elseif (preg_match('/^\/lottery_draw\s+(\d+)/', $messageText, $m)) {
            $reply = adminLotteryDraw((int)$m[1]);
        }

        if ($reply) {
            tgSendMessage($chatId, $reply);
            exit;
        }
        if ($messageText[0] === '/') {
            tgSendMessage($chatId, adminHelpText());
            exit;
        }
    }

    // For non-admin users, enforce membership before using the bot
    if (!$isAdminUser) {
        if (!enforceMembershipGate($chatId, $userId, false)) exit;
    }

    switch ($messageText) {
        case '📊 امتیاز من':
            tgSendMessage($chatId, 'امتیاز شما: ' . getUserPoints($userId));
            break;
        case '📎 لینک دعوت من':
            tgSendMessage($chatId, myInviteLink($userId));
            break;
        case '🛒 فروشگاه آیتم‌ها':
            $items = listActiveItems();
            tgSendMessage($chatId, shopText(), [ 'reply_markup' => buildShopItemKeyboard($items) ]);
            break;
        case '📤 درخواست‌های من':
            $reqs = listUserRequests($userId, 10);
            if (empty($reqs)) { tgSendMessage($chatId, 'درخواستی ثبت نکرده‌اید.'); break; }
            $lines = ['📤 درخواست‌های شما:'];
            foreach ($reqs as $r) {
                $lines[] = '#' . $r['id'] . ' | ' . $r['item_name'] . ' | ' . $r['cost_points'] . ' امتیاز | ' . ($r['status'] === 'pending' ? 'در حال بررسی' : ($r['status'] === 'approved' ? 'تایید شده' : 'رد شده'));
            }
            tgSendMessage($chatId, implode("\n", $lines));
            break;
        case '🎁 جایزه روزانه':
            [$ok, $msg] = tryGrantDailyBonus($userId);
            tgSendMessage($chatId, $msg);
            break;
        case '👤 پروفایل':
            $u = getUser($userId);
            tgSendMessage($chatId, userProfileText($u));
            break;
        case '🏆 برترین‌ها':
            tgSendMessage($chatId, 'یک گزینه را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '👥 برترین‌های رفرال', 'callback_data' => 'top_ref' ], [ 'text' => '⭐ برترین‌های امتیاز', 'callback_data' => 'top_pts' ] ] ] ] ]);
            break;
        case '🎲 قرعه‌کشی':
            $lots = listActiveCustomLotteries();
            tgSendMessage($chatId, 'قرعه‌کشی‌های فعال:', [ 'reply_markup' => buildLotteriesKeyboard($lots) ]);
            break;
        default:
            tgSendMessage($chatId, 'یکی از گزینه‌های منو را انتخاب کنید.', [ 'reply_markup' => buildMainMenuKeyboard($isAdminUser) ]);
            break;
    }

    exit;
}

echo 'OK';

@unlink('error_log');