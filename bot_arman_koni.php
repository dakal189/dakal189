<?php

// Single-file Telegram Bot: Referral + Points + Item Shop (PHP + MySQL)
// Encoding: UTF-8
// Minimum PHP: 7.4

// Optional dotenv loader for local/server env files
$__env = __DIR__ . '/.env';
if (is_file($__env)) {
    $lines = @file($__env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if ($v !== '' && $v[0] === '"' && substr($v, -1) === '"') {
                    $v = substr($v, 1, -1);
                }
                if ($k !== '') {
                    $current = getenv($k);
                    if ($current === false || $current === '') {
                        putenv($k . '=' . $v);
                    }
                }
            }
        }
    }
}

// ==========================
// Configuration
// ==========================

define('BOT_TOKEN', '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ');
if (!BOT_TOKEN) {
    http_response_code(500);
    echo 'BOT_TOKEN is not set';
    exit;
}

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

define('BOT_USERNAME', 'samp_info_bot');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'dakallli_Test2');
define('DB_USER', 'dakallli_Test2');
define('DB_PASS', 'hosyarww123');

define('ADMIN_IDS', [5641303137]);

define('ADMIN_GROUP_ID', -1002987179440); // Group ID to receive item requests

define('PUBLIC_ANNOUNCE_CHANNEL_ID', -1002798392543); // Optional public channel for announcements

define('REFERRAL_REWARD_POINTS', 10);


define('LOTTERY_TICKET_COST', 10);

define('LOTTERY_PRIZE_POINTS', 200);

define('ANTI_SPAM_MIN_INTERVAL_MS', 700);

define('CRON_SECRET', 'CHANGE_ME');

define('WEEKLY_TOP_REWARDS', (function () {
    $defaults = [1 => 300, 2 => 200, 3 => 100];
    return $defaults;
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
            total_tickets INT NOT NULL DEFAULT 0,
            referral_required_count INT NOT NULL DEFAULT 0,
            prize_text VARCHAR(255) NULL,
            photo_file_id VARCHAR(255) NULL
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

        // Custom lottery prizes (multi-tier)
        "CREATE TABLE IF NOT EXISTS custom_lottery_prizes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lottery_id INT NOT NULL,
            rank INT NOT NULL,
            prize_points INT NULL,
            prize_text VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_lottery_rank (lottery_id, rank),
            FOREIGN KEY (lottery_id) REFERENCES custom_lotteries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Key-Value settings
        "CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(64) PRIMARY KEY,
            `value` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Admin states per user
        "CREATE TABLE IF NOT EXISTS admin_states (
            user_id BIGINT PRIMARY KEY,
            state VARCHAR(64) NOT NULL,
            data TEXT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Per-lottery channels
        "CREATE TABLE IF NOT EXISTS custom_lottery_channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lottery_id INT NOT NULL,
            chat_id BIGINT NOT NULL,
            username VARCHAR(128) NULL,
            title VARCHAR(128) NULL,
            added_at DATETIME NOT NULL,
            UNIQUE KEY uniq_lottery_chat (lottery_id, chat_id),
            FOREIGN KEY (lottery_id) REFERENCES custom_lotteries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    $pdo = pdo();
    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }

    // Attempt to add revoked_at to referrals if missing
    try { $pdo->exec("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS revoked_at DATETIME NULL"); } catch (Throwable $e) { /* ignore */ }
    // Extend custom_lotteries for wizard features
    try { $pdo->exec("ALTER TABLE custom_lotteries ADD COLUMN referral_required_count INT NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE custom_lotteries ADD COLUMN prize_text VARCHAR(255) NULL"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE custom_lotteries ADD COLUMN photo_file_id VARCHAR(255) NULL"); } catch (Throwable $e) { /* ignore */ }
}

function getSetting(string $key, ?string $default = null): ?string {
    $stmt = pdo()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row && isset($row['value'])) return (string)$row['value'];
    return $default;
}

function setSetting(string $key, string $value): void {
    $stmt = pdo()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->execute([$key, $value]);
}

function getBotEnabled(): bool {
    return getSetting('bot_enabled', '1') === '1';
}

function setBotEnabled(bool $enabled): void {
    setSetting('bot_enabled', $enabled ? '1' : '0');
}

function buildAdminPanelInlineKeyboard(bool $enabled): array {
    $toggleText = $enabled ? '🔴 خاموش کردن ربات' : '🟢 روشن کردن ربات';
    $toggleCb = $enabled ? 'bot_off' : 'bot_on';
    return [
        'inline_keyboard' => [
            [ [ 'text' => $toggleText, 'callback_data' => $toggleCb ] ],
            [ [ 'text' => '🎁 مدیریت آیتم‌ها', 'callback_data' => 'admin_items' ] ],
            [ [ 'text' => '📢 مدیریت کانال‌ها', 'callback_data' => 'admin_channels' ] ],
            [ [ 'text' => '👥 مدیریت کاربران', 'callback_data' => 'admin_users' ] ],
            [ [ 'text' => '💰 مدیریت امتیاز', 'callback_data' => 'admin_points' ] ],
            [ [ 'text' => '🚫 مدیریت بن', 'callback_data' => 'admin_ban' ] ],
            [ [ 'text' => '🎲 مدیریت قرعه‌کشی', 'callback_data' => 'admin_lottery' ] ],
            [ [ 'text' => '⚙️ تنظیمات', 'callback_data' => 'admin_settings' ] ],
            [ [ 'text' => '🏆 گزارش‌ها و کرون‌جاب', 'callback_data' => 'admin_reports' ] ],
            [ [ 'text' => '❎ بستن پنل', 'callback_data' => 'admin_close' ] ],
        ],
    ];
}

function buildAdminPromptKeyboard(): array {
    return [
        'inline_keyboard' => [
            [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ],
        ],
    ];
}

function handleAdminBack(int $chatId, int $messageId, int $userId): void {
    $st = getAdminState($userId);
    $s = $st['state'] ?? '';
    // Wizard: add item
    if ($s === 'await_item_add') {
        setAdminState($userId, 'await_item_add_name');
        tgEditMessageText($chatId, $messageId, '➕ نام آیتم را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
        return;
    }
    if ($s === 'await_item_add_name') {
        clearAdminState($userId);
        tgEditMessageText($chatId, $messageId, '🎁 مدیریت آیتم‌ها', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن آیتم', 'callback_data' => 'admin_items_add' ], [ 'text' => '❌ حذف آیتم', 'callback_data' => 'admin_items_del' ] ], [ [ 'text' => '📋 لیست آیتم‌ها', 'callback_data' => 'admin_items_list' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
        return;
    }
    // Map generic await_* to their menus
    $menuMap = [
        'await_item_del' => 'admin_items',
        'await_channel_add' => 'admin_channels',
        'await_channel_del' => 'admin_channels',
        'await_users_search' => 'admin_users',
        'await_points_set' => 'admin_points',
        'await_points_add' => 'admin_points',
        'await_points_sub' => 'admin_points',
        'await_ban' => 'admin_ban',
        'await_unban' => 'admin_ban',
        'await_lottery_new' => 'admin_lottery',
        'await_lottery_close' => 'admin_lottery',
        'await_lottery_draw' => 'admin_lottery',
    ];
    if (isset($menuMap[$s])) {
        $menu = $menuMap[$s];
        if ($menu === 'admin_items') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '🎁 مدیریت آیتم‌ها', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن آیتم', 'callback_data' => 'admin_items_add' ], [ 'text' => '❌ حذف آیتم', 'callback_data' => 'admin_items_del' ] ], [ [ 'text' => '📋 لیست آیتم‌ها', 'callback_data' => 'admin_items_list' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
        if ($menu === 'admin_channels') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '📢 مدیریت کانال‌ها', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن کانال اجباری', 'callback_data' => 'admin_channels_add' ] ], [ [ 'text' => '📋 لیست کانال‌ها', 'callback_data' => 'admin_channels_list' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
        if ($menu === 'admin_users') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '👥 مدیریت کاربران', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '📋 لیست کاربران', 'callback_data' => 'admin_users_list' ], [ 'text' => '🔍 جستجوی user_id', 'callback_data' => 'admin_users_search' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
        if ($menu === 'admin_points') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '💰 مدیریت امتیاز', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '📝 تنظیم امتیاز', 'callback_data' => 'admin_points_set' ], [ 'text' => '➕ افزودن', 'callback_data' => 'admin_points_add' ], [ 'text' => '➖ کم کردن', 'callback_data' => 'admin_points_sub' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
        if ($menu === 'admin_ban') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '🚫 مدیریت بن', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '🚷 بن کردن', 'callback_data' => 'admin_ban_user' ], [ 'text' => '✅ آزاد کردن', 'callback_data' => 'admin_unban_user' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
        if ($menu === 'admin_lottery') {
            clearAdminState($userId);
            tgEditMessageText($chatId, $messageId, '🎲 مدیریت قرعه‌کشی', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '🎯 ساخت جدید', 'callback_data' => 'admin_lottery_new' ], [ 'text' => '📋 لیست', 'callback_data' => 'admin_lottery_list' ] ], [ [ 'text' => '⛔ بستن', 'callback_data' => 'admin_lottery_close' ], [ 'text' => '🎟 انجام قرعه‌کشی', 'callback_data' => 'admin_lottery_draw' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            return;
        }
    }
    // Default: go to main panel
    clearAdminState($userId);
    tgEditMessageText($chatId, $messageId, '🛠 پنل ادمین', [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]);
}

function showAdminPanel(int $chatId): void {
    $enabled = getBotEnabled();
    tgSendMessage($chatId, '🛠 پنل ادمین', [ 'reply_markup' => buildAdminPanelInlineKeyboard($enabled) ]);
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

function tgSendPhoto(int $chatId, string $fileIdOrUrl, string $caption = '', array $opts = []): array {
    $params = array_merge([
        'chat_id' => $chatId,
        'photo' => $fileIdOrUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ], $opts);
    if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
        $params['reply_markup'] = json_encode($params['reply_markup']);
    }
    return apiRequest('sendPhoto', $params);
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

function tgDeleteMessage(int $chatId, int $messageId): array {
    return apiRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
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

function tgExportChatInviteLink(int $chatId): array {
    return apiRequest('exportChatInviteLink', [
        'chat_id' => $chatId,
    ]);
}

function tgGetUserProfilePhotos(int $userId, int $limit = 1): array {
    return apiRequest('getUserProfilePhotos', [
        'user_id' => $userId,
        'limit' => $limit,
    ]);
}

function getBotUserId(): ?int {
    $cached = getSetting('bot_user_id', null);
    if ($cached !== null && $cached !== '') {
        return (int) $cached;
    }
    $res = apiRequest('getMe');
    if (($res['ok'] ?? false) && isset($res['result']['id'])) {
        $botId = (int) $res['result']['id'];
        setSetting('bot_user_id', (string) $botId);
        return $botId;
    }
    return null;
}

function buildMainMenuKeyboard(bool $isAdmin): array {
    $keyboard = [
        ['📊 امتیاز من', '📎 لینک دعوت من'],
        ['🛒 فروشگاه آیتم‌ها', '📤 درخواست‌های من'],
        ['👤 پروفایل', '🎲 قرعه‌کشی'],
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
            $invite = tgExportChatInviteLink((int)$ch['chat_id']);
            if (($invite['ok'] ?? false) && !empty($invite['result'])) {
                $rows[] = [ [ 'text' => $text, 'url' => $invite['result'] ] ];
            } else {
                $rows[] = [ [ 'text' => $text, 'callback_data' => 'noop' ] ];
            }
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
    $stmt = $pdo->prepare('SELECT referrer_id, pending_referrer_id, joined_at FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return;

    if (!empty($row['referrer_id'])) return;

    // Only allow referrals for new users: joined within the last 10 minutes
    $joinedAt = isset($row['joined_at']) ? strtotime($row['joined_at']) : 0;
    if ($joinedAt > 0 && (time() - $joinedAt) > 600) { // older than 10 minutes
        return;
    }

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

function enforceMembershipGate(int $chatId, int $userId, bool $isAdmin, bool $forcePrompt = false): bool {
    if ($isAdmin) return true;
    if (isMemberAllRequiredChannels($userId)) return true;

    // Per-user throttle: remind at most once every 2 minutes (always show on forced prompts)
    $now = time();
    $key = 'gate_prompt_ts_' . $userId;
    $last = (int) (getSetting($key, '0') ?? '0');
    if ($forcePrompt || ($now - $last) >= 120) {
        tgSendMessage($chatId, formatChannelsJoinMessage(), [ 'reply_markup' => buildVerifyChannelsInlineKeyboard() ]);
        setSetting($key, (string)$now);
    }

    tryRevokeReferralIfNecessary($userId);
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

function getChannelById(int $id): ?array {
    $stmt = pdo()->prepare('SELECT * FROM channels WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buildAdminChannelsDeleteKeyboard(): array {
    $chs = listRequiredChannels();
    $rows = [];
    foreach ($chs as $c) {
        $label = ($c['title'] ?: ($c['username'] ? '@' . $c['username'] : (string)$c['chat_id'])) . ' [' . $c['chat_id'] . ']';
        $rows[] = [ [ 'text' => '🗑 ' . $label, 'callback_data' => 'ch_del_' . $c['id'] ] ];
    }
    if (empty($rows)) {
        $rows[] = [ [ 'text' => 'لیستی تنظیم نشده است.', 'callback_data' => 'noop' ] ];
    }
    $rows[] = [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_channels' ] ];
    return [ 'inline_keyboard' => $rows ];
}

function cleanupChannelsIfBotRemoved(): void {
    $botId = getBotUserId();
    if (!$botId) return;
    $channels = listRequiredChannels();
    if (empty($channels)) return;

    // Throttle notifications to once per 10 minutes
    $now = time();
    $lastTs = (int) (getSetting('cleanup_notify_ts', '0') ?? '0');
    $canNotify = ($now - $lastTs) >= 600; // 10 minutes

    $removed = [];
    foreach ($channels as $ch) {
        $chatId = (int) $ch['chat_id'];
        $res = tgGetChatMember($chatId, $botId);
        $shouldRemove = false;
        if (!($res['ok'] ?? false)) {
            $shouldRemove = true;
        } else {
            $status = $res['result']['status'] ?? '';
            if (!in_array($status, ['administrator', 'creator'], true)) {
                $shouldRemove = true;
            }
        }
        if ($shouldRemove) {
            pdo()->prepare('DELETE FROM channels WHERE id = ?')->execute([$ch['id']]);
            $label = $ch['title'] ?: ($ch['username'] ? '@' . $ch['username'] : (string)$chatId);
            $removed[] = $label . ' [' . $chatId . ']';
        }
    }

    if (!empty($removed)) {
        // Deduplicate: if same content as last time, skip notifying
        $payload = implode("\n", array_map(function($x){ return '• ' . $x; }, $removed));
        $lastPayload = getSetting('cleanup_notify_payload', '');
        if ($payload !== $lastPayload || $canNotify) {
            $msg = '⚠️ کانال/گروه‌های زیر به دلیل عدم ادمین بودن یا حذف ربات پاک شدند:' . "\n" . $payload;
            if (defined('ADMIN_GROUP_ID') && ADMIN_GROUP_ID) { tgSendMessage(ADMIN_GROUP_ID, $msg); }
            foreach (ADMIN_IDS as $aid) { tgSendMessage($aid, $msg); }
            setSetting('cleanup_notify_payload', $payload);
            setSetting('cleanup_notify_ts', (string)$now);
        }
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
// Business Logic: Weekly Top Referrals
// ==========================

// ==========================
// Weekly Top Referrals feature removed per requirements
// ==========================

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
    // Load prize tiers
    $pstmt = pdo()->prepare('SELECT * FROM custom_lottery_prizes WHERE lottery_id = ? ORDER BY rank ASC');
    $pstmt->execute([$lotteryId]);
    $prizes = $pstmt->fetchAll();
    $total = 0; foreach ($rows as $r) { $total += (int)$r['t']; }
    $winners = [];
    $usedUserIds = [];
    if (!empty($prizes)) {
        foreach ($prizes as $pz) {
            if ($total <= 0) break;
            $rand = random_int(1, $total);
            $acc = 0; $winner = 0; $winIdx = -1;
            foreach ($rows as $idx => $r) { $acc += (int)$r['t']; if ($acc >= $rand) { $winner = (int)$r['user_id']; $winIdx = $idx; break; } }
            if ($winner <= 0) continue;
            if (in_array($winner, $usedUserIds, true)) { continue; }
            $usedUserIds[] = $winner;
            $winners[] = [ 'user_id' => $winner, 'rank' => (int)$pz['rank'], 'prize_points' => $pz['prize_points'], 'prize_text' => $pz['prize_text'] ];
            $total -= (int)$rows[$winIdx]['t'];
            array_splice($rows, $winIdx, 1);
        }
    } else {
        $rand = random_int(1, $total);
        $acc = 0; $winner = 0;
        foreach ($rows as $r) { $acc += (int)$r['t']; if ($acc >= $rand) { $winner = (int)$r['user_id']; break; } }
        if ($winner <= 0) return 'خطا در انتخاب برنده.';
        $winners[] = [ 'user_id' => $winner, 'rank' => 1, 'prize_points' => (int)$lot['prize_points'], 'prize_text' => $lot['prize_text'] ?? null ];
    }
    // Award and record
    $pdo = pdo();
    foreach ($winners as $w) { if (!empty($w['prize_points'])) { addUserPoints((int)$w['user_id'], (int)$w['prize_points']); } }
    $pdo->prepare('UPDATE custom_lotteries SET drawn_at = ?, total_tickets = ? WHERE id = ?')->execute([nowUtc(), $total, $lotteryId]);
    $lines = [ '🎲 نتایج قرعه‌کشی #' . $lotteryId . ' («' . $lot['title'] . '»):' ];
    foreach ($winners as $w) { $lines[] = '#' . $w['rank'] . ' — ' . $w['user_id'] . (!empty($w['prize_points']) ? (' | +' . $w['prize_points'] . ' امتیاز') : '') . (!empty($w['prize_text']) ? (' | ' . $w['prize_text']) : ''); }
    $msg = implode("\n", $lines);
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

    $botId = getBotUserId();
    if (!$botId) return 'خطا در دریافت آیدی ربات. لطفاً بعداً تلاش کنید.';

    // If username like @channel
    if ($identifier[0] === '@') {
        $username = ltrim($identifier, '@');
        $res = tgGetChat('@' . $username);
        if (!($res['ok'] ?? false)) return 'عدم دسترسی یا کانال یافت نشد. ابتدا ربات را ادمین کانال کنید.';
        $chat = $res['result'];
        $chatId = (int) ($chat['id'] ?? 0);
        $title = $chat['title'] ?? $username;

        $mem = tgGetChatMember($chatId, $botId);
        $status = $mem['result']['status'] ?? '';
        if (!($mem['ok'] ?? false) || !in_array($status, ['administrator','creator'], true)) {
            return '❌ ربات در این چت ادمین نیست. ابتدا ربات را ادمین کنید سپس دوباره تلاش کنید.';
        }

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

        $mem = tgGetChatMember($chatId, $botId);
        $status = $mem['result']['status'] ?? '';
        if (!($mem['ok'] ?? false) || !in_array($status, ['administrator','creator'], true)) {
            return '❌ ربات در این چت ادمین نیست. ابتدا ربات را ادمین کنید سپس دوباره تلاش کنید.';
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

function getAdminState(int $userId): ?array {
    $stmt = pdo()->prepare('SELECT state, data FROM admin_states WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $data = null;
    if (!empty($row['data'])) {
        $decoded = json_decode($row['data'], true);
        if (is_array($decoded)) $data = $decoded;
    }
    return ['state' => $row['state'], 'data' => $data];
}

function setAdminState(int $userId, string $state, $data = null): void {
    $payload = $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
    $stmt = pdo()->prepare('INSERT INTO admin_states (user_id, state, data, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data), updated_at = VALUES(updated_at)');
    $stmt->execute([$userId, $state, $payload, nowUtc()]);
}

function clearAdminState(int $userId): void {
    $stmt = pdo()->prepare('DELETE FROM admin_states WHERE user_id = ?');
    $stmt->execute([$userId]);
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
    return '📎 لینک دعوت شما:' . "\n" . 'https://t.me/' . BOT_USERNAME . '?start=' . $userId;
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

function buildLotteryDetailKeyboard(array $lottery): array {
    return [
        'inline_keyboard' => [
            [ [ 'text' => '🎟 شرکت در قرعه‌کشی', 'callback_data' => 'lot_join_' . $lottery['id'] ], [ 'text' => '👥 تعداد شرکت‌کنندگان', 'callback_data' => 'lot_count_' . $lottery['id'] ] ],
            [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ] ],
        ],
    ];
}

function buildLotteryDetailText(array $lottery, int $userId): string {
    $title = $lottery['title'];
    $cost = is_null($lottery['entry_cost_points']) ? 'ref' : (string)$lottery['entry_cost_points'];
    $entry = ($cost === 'ref') ? 'ورود با رفرال' : ('هزینه شرکت: ' . $cost . ' امتیاز');
    // compute user referral stats if needed
    $user = getUser($userId);
    $refCnt = (int) ($user['referrals_count'] ?? 0);
    $prize = (int) ($lottery['prize_points'] ?? 0);
    $prizeText = $prize > 0 ? ('جایزه: ' . $prize . ' امتیاز') : 'جایزه: شخصی‌سازی شده';
    return '🎲 ' . $title . "\n" . $entry . "\n" . 'رفرال‌های شما: ' . $refCnt . "\n" . $prizeText;
}

// ==========================
// Update Handling
// ==========================

ensureTables();

// Ensure bot_enabled default
if (getSetting('bot_enabled', null) === null) { setBotEnabled(true); }


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
        if (!getBotEnabled()) { tgAnswerCallbackQuery($callbackId, 'ربات توسط مدیریت خاموش شده است.', true); echo 'OK'; exit; }
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

    // Lottery detail and actions
    if (strpos($data, 'lot_info_') === 0) {
        $lotId = (int) substr($data, strlen('lot_info_'));
        $lot = getCustomLottery($lotId);
        if (!$lot) { tgAnswerCallbackQuery($callbackId, 'یافت نشد', true); exit; }
        tgAnswerCallbackQuery($callbackId, '');
        tgEditMessageText($chatId, $messageId, buildLotteryDetailText($lot, $userId), [ 'reply_markup' => buildLotteryDetailKeyboard($lot) ]);
        exit;
    }
    if (strpos($data, 'lot_buy_') === 0) {
        $lotId = (int) substr($data, strlen('lot_buy_'));
        $lot = getCustomLottery($lotId);
        if (!$lot) { tgAnswerCallbackQuery($callbackId, 'یافت نشد', true); exit; }
        tgAnswerCallbackQuery($callbackId, '');
        tgEditMessageText($chatId, $messageId, buildLotteryDetailText($lot, $userId), [ 'reply_markup' => buildLotteryDetailKeyboard($lot) ]);
        exit;
    }
    if (strpos($data, 'lot_count_') === 0) {
        $lotId = (int) substr($data, strlen('lot_count_'));
        $stmt = pdo()->prepare('SELECT COUNT(DISTINCT user_id) as c FROM custom_lottery_tickets WHERE lottery_id = ? AND num_tickets > 0');
        $stmt->execute([$lotId]);
        $cnt = (int) ($stmt->fetch()['c'] ?? 0);
        tgAnswerCallbackQuery($callbackId, 'شرکت‌کننده: ' . $cnt);
        exit;
    }
    if (strpos($data, 'lot_join_') === 0) {
        $lotId = (int) substr($data, strlen('lot_join_'));
        $lot = getCustomLottery($lotId);
        if (!$lot) { tgAnswerCallbackQuery($callbackId, 'یافت نشد', true); exit; }
        if (!enforceMembershipGate($chatId, $userId, $isAdminUser)) { echo 'OK'; exit; }
        list($okElig, $msgElig) = isUserEligibleForLottery($lot, $userId);
        if (!$okElig) { tgAnswerCallbackQuery($callbackId, $msgElig, true); exit; }
        // Enforce one ticket per user
        $stmt = pdo()->prepare('SELECT COALESCE(SUM(num_tickets),0) as t FROM custom_lottery_tickets WHERE lottery_id = ? AND user_id = ?');
        $stmt->execute([$lotId, $userId]);
        $has = (int) ($stmt->fetch()['t'] ?? 0);
        if ($has > 0) { tgAnswerCallbackQuery($callbackId, 'قبلاً شرکت کرده‌اید.', true); exit; }
        if (is_null($lot['entry_cost_points'])) {
            // referral-based entry: grant one ticket without cost
            pdo()->prepare('INSERT INTO custom_lottery_tickets (lottery_id, user_id, num_tickets, created_at) VALUES (?, ?, ?, ?)')->execute([$lotId, $userId, 1, nowUtc()]);
            tgAnswerCallbackQuery($callbackId, 'ثبت شد');
            tgSendMessage($chatId, '🎟 شرکت شما با موفقیت ثبت شد.', [ 'reply_markup' => buildLotteryDetailKeyboard($lot) ]);
            exit;
        }
        list($ok, $msg) = buyCustomLotteryTicket($userId, $lot);
        tgAnswerCallbackQuery($callbackId, $ok ? 'ثبت شد' : 'خطا', !$ok);
        tgSendMessage($chatId, $msg, [ 'reply_markup' => buildLotteryDetailKeyboard($lot) ]);
        exit;
    }

    // Admin inline: show help/items/channels
    if ($isAdminUser && $data === 'admin_help') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, adminHelpText(), [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }
    if ($isAdminUser && $data === 'admin_items_list') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, adminItemsList(), [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }
    if ($isAdminUser && $data === 'admin_channels_list') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, adminChannelsList(), [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }

    // Admin: bot on/off confirmations
    if ($isAdminUser && $data === 'bot_off') {
        tgAnswerCallbackQuery($callbackId, '');
        tgEditMessageText($chatId, $messageId, 'آیا مطمئنید می‌خواهید ربات را خاموش کنید؟', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '✅ بله', 'callback_data' => 'bot_off_yes' ], [ 'text' => '❌ خیر', 'callback_data' => 'admin_back' ] ] ] ] ]);
        exit;
    }
    if ($isAdminUser && $data === 'bot_on') {
        tgAnswerCallbackQuery($callbackId, '');
        tgEditMessageText($chatId, $messageId, 'آیا مطمئنید می‌خواهید ربات را روشن کنید؟', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '✅ بله', 'callback_data' => 'bot_on_yes' ], [ 'text' => '❌ خیر', 'callback_data' => 'admin_back' ] ] ] ] ]);
        exit;
    }
    if ($isAdminUser && $data === 'bot_off_yes') { setBotEnabled(false); tgAnswerCallbackQuery($callbackId, 'ربات خاموش شد'); tgEditMessageText($chatId, $messageId, 'ربات خاموش شد.', [ 'reply_markup' => buildAdminPanelInlineKeyboard(false) ]); exit; }
    if ($isAdminUser && $data === 'bot_on_yes') { setBotEnabled(true); resetAllUserPointsAndReferrals(); tgAnswerCallbackQuery($callbackId, 'ربات روشن شد'); tgEditMessageText($chatId, $messageId, 'ربات روشن شد.', [ 'reply_markup' => buildAdminPanelInlineKeyboard(true) ]); exit; }
    if ($isAdminUser && $data === 'admin_back') { tgAnswerCallbackQuery($callbackId, ''); handleAdminBack($chatId, $messageId, $userId); exit; }
    if ($isAdminUser && $data === 'admin_cancel') { clearAdminState($userId); tgAnswerCallbackQuery($callbackId, 'انصراف'); tgEditMessageText($chatId, $messageId, '🛠 پنل ادمین', [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }
    if ($isAdminUser && $data === 'admin_settings') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, '⚙️ تنظیمات قابلیت‌ها', [ 'reply_markup' => buildAdminSettingsKeyboard() ]); exit; }
    if ($isAdminUser && strpos($data, 'f_tog_') === 0) { $k = substr($data, strlen('f_tog_')); $new = isFeatureEnabled($k) ? '0' : '1'; setSetting('feature_' . $k, $new); tgAnswerCallbackQuery($callbackId, 'به‌روزرسانی شد'); tgEditMessageText($chatId, $messageId, '⚙️ تنظیمات قابلیت‌ها', [ 'reply_markup' => buildAdminSettingsKeyboard() ]); exit; }

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
        $text = "درخواست شما ثبت شد و به ادمین ارسال گردید.\n" . 'آیتم: ' . $item['name'] . ' | هزینه: ' . $item['cost_points'] . ' امتیاز';
        tgAnswerCallbackQuery($callbackId, 'درخواست ثبت شد.');
        tgSendMessage($chatId, $text);

        if (ADMIN_GROUP_ID) {
            $user = getUser($userId);
            $uname = $user['username'] ? '@' . $user['username'] : '-';
            $adminText = "درخواست جدید آیتم:\n" .
                'کاربر: ' . $uname . ' (' . $userId . ")\n" .
                'آیتم: 🎁 ' . $item['name'] . "\n" .
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
                tgEditMessageText((int) $row['admin_chat_id'], (int) $row['admin_message_id'], "درخواست تایید شد.\nکاربر: @" . ($row['username'] ?: '-') . ' (' . $row['user_id'] . ")\n" . 'آیتم: ' . $row['item_name'] . "\n" . 'وضعیت: ✅ تایید');
            }
        } else {
            $row = setItemRequestStatus($requestId, 'rejected');
            tgAnswerCallbackQuery($callbackId, 'درخواست رد شد.');
            // Notify user
            tgSendMessage((int) $row['user_id'], '❌ درخواست شما برای آیتم: ' . $row['item_name'] . ' رد شد.');
            // Update admin message
            if (!empty($row['admin_chat_id']) && !empty($row['admin_message_id'])) {
                tgEditMessageText((int) $row['admin_chat_id'], (int) $row['admin_message_id'], "درخواست رد شد.\nکاربر: @" . ($row['username'] ?: '-') . ' (' . $row['user_id'] . ")\n" . 'آیتم: ' . $row['item_name'] . "\n" . 'وضعیت: ❌ رد');
            }
        }
        exit;
    }

    if ($data === 'noop') {
        tgAnswerCallbackQuery($callbackId, '');
        exit;
    }

    // Admin nested menus and actions
    if ($isAdminUser) {
        if ($data === 'admin_items') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '🎁 مدیریت آیتم‌ها', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن آیتم', 'callback_data' => 'admin_items_add' ], [ 'text' => '❌ حذف آیتم', 'callback_data' => 'admin_items_del' ] ], [ [ 'text' => '📋 لیست آیتم‌ها', 'callback_data' => 'admin_items_list' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_channels') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '📢 مدیریت کانال‌ها', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن کانال اجباری', 'callback_data' => 'admin_channels_add' ] ], [ [ 'text' => '📋 لیست کانال‌ها', 'callback_data' => 'admin_channels_list' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        
        if ($data === 'admin_users') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '👥 مدیریت کاربران', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '📋 لیست کاربران', 'callback_data' => 'admin_users_list' ], [ 'text' => '🔍 جستجوی user_id', 'callback_data' => 'admin_users_search' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_points') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '💰 مدیریت امتیاز', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '📝 تنظیم امتیاز', 'callback_data' => 'admin_points_set' ], [ 'text' => '➕ افزودن', 'callback_data' => 'admin_points_add' ], [ 'text' => '➖ کم کردن', 'callback_data' => 'admin_points_sub' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_ban') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '🚫 مدیریت بن', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '🚷 بن کردن', 'callback_data' => 'admin_ban_user' ], [ 'text' => '✅ آزاد کردن', 'callback_data' => 'admin_unban_user' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_lottery') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '🎲 مدیریت قرعه‌کشی', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '🎯 ساخت جدید', 'callback_data' => 'admin_lottery_new' ], [ 'text' => '📋 لیست', 'callback_data' => 'admin_lottery_list' ] ], [ [ 'text' => '⛔ بستن', 'callback_data' => 'admin_lottery_close' ], [ 'text' => '🎟 انجام قرعه‌کشی', 'callback_data' => 'admin_lottery_draw' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_reports') {
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, '🏆 گزارش‌ها و کرون‌جاب', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '🎰 قرعه‌کشی هفتگی', 'callback_data' => 'admin_cron_lottery' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ] ] ] ]);
            exit;
        }
        if ($data === 'admin_main') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, '🛠 پنل ادمین', [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }
        if ($data === 'admin_close') { tgAnswerCallbackQuery($callbackId, ''); tgEditMessageText($chatId, $messageId, 'پنل بسته شد.', []); exit; }

        // Prompt states
        if ($data === 'admin_items_add') { setAdminState($userId, 'await_item_add_name'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '➕ نام آیتم را بفرستید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_items_del') { setAdminState($userId, 'await_item_del'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '❌ لطفاً ID آیتم را ارسال کنید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_items_list') { tgAnswerCallbackQuery($callbackId, ''); tgSendMessage($chatId, adminItemsList()); exit; }

        if ($data === 'admin_channels_add') { setAdminState($userId, 'await_channel_add'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '➕ شناسه کانال (مثل @username یا -100...) را بفرستید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_channels_del') { setAdminState($userId, 'await_channel_del'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '❌ chat_id کانال را بفرستید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_channels_list') { tgAnswerCallbackQuery($callbackId, ''); tgSendMessage($chatId, adminChannelsList(), [ 'reply_markup' => buildAdminPanelInlineKeyboard(getBotEnabled()) ]); exit; }

        if ($data === 'admin_users_list') { tgAnswerCallbackQuery($callbackId, ''); tgSendMessage($chatId, adminUsersList(1)); exit; }
        if ($data === 'admin_users_search') { setAdminState($userId, 'await_users_search'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '🔍 user_id را بفرستید.', buildAdminPromptKeyboard()); exit; }

        if ($data === 'admin_points_set') { setAdminState($userId, 'await_points_set'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '📝 به صورت «user_id amount» ارسال کنید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_points_add') { setAdminState($userId, 'await_points_add'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '➕ به صورت «user_id amount» ارسال کنید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_points_sub') { setAdminState($userId, 'await_points_sub'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '➖ به صورت «user_id amount» ارسال کنید.', buildAdminPromptKeyboard()); exit; }

        if ($data === 'admin_ban_user') { setAdminState($userId, 'await_ban'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '🚷 user_id کاربر برای بن کردن؟', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_unban_user') { setAdminState($userId, 'await_unban'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '✅ user_id کاربر برای آزاد کردن؟', buildAdminPromptKeyboard()); exit; }

        if ($data === 'admin_lottery_new') { setAdminState($userId, 'lot_w_title'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '📝 عنوان قرعه‌کشی را بفرستید.', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_lottery_list') { tgAnswerCallbackQuery($callbackId, ''); tgSendMessage($chatId, adminLotteryList()); exit; }
        if ($data === 'admin_lottery_close') { setAdminState($userId, 'await_lottery_close'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '⛔ ID قرعه‌کشی برای بستن؟', buildAdminPromptKeyboard()); exit; }
        if ($data === 'admin_lottery_draw') { setAdminState($userId, 'await_lottery_draw'); tgAnswerCallbackQuery($callbackId, ''); adminPrompt($chatId, $userId, '🎟 ID قرعه‌کشی برای انجام قرعه‌کشی؟', buildAdminPromptKeyboard()); exit; }

        if ($data === 'admin_cron_lottery') { tgAnswerCallbackQuery($callbackId, ''); tgSendMessage($chatId, runWeeklyLotteryDrawCron()); exit; }

        // Wizard callbacks
        if ($isAdminUser && $data === 'lotw_entry_points') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_entry_points', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'عدد امتیاز ورود را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
            exit;
        }
        if ($isAdminUser && $data === 'lotw_entry_ref') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_entry_ref', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'تعداد رفرال موردنیاز را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
            exit;
        }
        if ($isAdminUser && $data === 'lotw_prize_points') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_prize_points', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'مقدار امتیاز جایزه را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
            exit;
        }
        if ($isAdminUser && $data === 'lotw_prize_text') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_prize_text', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'متن جایزه را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
            exit;
        }
        if ($isAdminUser && $data === 'lotw_prize_add_more') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_prize_type', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'نوع جایزه بعدی را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => 'امتیازی', 'callback_data' => 'lotw_prize_points' ], [ 'text' => 'شخصی‌سازی', 'callback_data' => 'lotw_prize_text' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ] ]);
            exit;
        }
        if ($isAdminUser && $data === 'lotw_prize_continue') {
            $st = getAdminState($userId); $dataSt = $st['data'] ?? [];
            setAdminState($userId, 'lot_w_photo', $dataSt);
            tgAnswerCallbackQuery($callbackId, '');
            tgEditMessageText($chatId, $messageId, 'در صورت تمایل عکس قرعه‌کشی را بفرستید یا «رد» را ارسال کنید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
            exit;
        }
    }

    // Unknown callback
    tgAnswerCallbackQuery($callbackId, 'دستور نامعتبر');
    exit;
}

// Process text messages
if ($messageText !== null) {
    cleanupChannelsIfBotRemoved();
    // Handle /start with optional parameter
    if (strpos($messageText, '/start') === 0) {
        $parts = explode(' ', $messageText, 2);
        if (isset($parts[1])) {
            $refParam = trim($parts[1]);
            if (preg_match('/^-?\d+$/', $refParam)) {
                setPendingReferrerIfApplicable($userId, (int) $refParam);
            }
        }

        if (!enforceMembershipGate($chatId, $userId, $isAdminUser, true)) { exit; }
        recordReferralIfEligibleAfterVerification($userId);
        tgSendMessage($chatId, 'به ربات خوش آمدید! از منو انتخاب کنید.', [ 'reply_markup' => buildMainMenuKeyboard($isAdminUser) ]);
        exit;
    }

    // Admin panel
    if ($messageText === '🛠 پنل ادمین' && $isAdminUser) {
        showAdminPanel($chatId);
        exit;
    }

    // Admin commands (legacy)
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
        // Do not auto-send admin help on unknown slash commands
    }

    // Admin state inputs
    if ($isAdminUser) {
        $st = getAdminState($userId);
        if ($st && isset($st['state'])) {
            $s = $st['state'];
            if ($s === 'await_item_add') {
                if (preg_match('/^(.+)\|(\s*\d+)$/u', $messageText, $m)) { tgSendMessage($chatId, adminAddItem(trim($m[1]), (int)trim($m[2]))); clearAdminState($userId); } else { tgSendMessage($chatId, 'فرمت نامعتبر. «نام | هزینه»'); }
                exit;
            }
            if ($s === 'await_item_add_name') {
                $name = trim($messageText);
                if ($name === '') { adminPrompt($chatId, $userId, 'نام آیتم نامعتبر است.', buildAdminPromptKeyboard()); exit; }
                setAdminState($userId, 'await_item_add_cost', [ 'name' => $name ]);
                adminPrompt($chatId, $userId, 'مقدار امتیاز مورد نیاز را بفرستید.', buildAdminPromptKeyboard());
                exit;
            }
            if ($s === 'await_item_add_cost') {
                if (!preg_match('/^\d+$/', $messageText)) { adminPrompt($chatId, $userId, 'عدد معتبر بفرستید.', buildAdminPromptKeyboard()); exit; }
                $cost = (int) $messageText;
                $data = $st['data'] ?? [];
                $name = $data['name'] ?? '';
                if ($name === '') { clearAdminState($userId); tgSendMessage($chatId, 'انصراف به دلیل داده ناقص.'); exit; }
                tgSendMessage($chatId, adminAddItem($name, $cost));
                clearAdminState($userId);
                exit;
            }
            // Lottery wizard
            if ($s === 'lot_w_title') {
                $title = trim($messageText);
                if ($title === '') { adminPrompt($chatId, $userId, 'عنوان نامعتبر است.', buildAdminPromptKeyboard()); exit; }
                setAdminState($userId, 'lot_w_entry_type', [ 'title' => $title ]);
                adminPrompt($chatId, $userId, 'نوع ورود را انتخاب کنید:', [ 'inline_keyboard' => [ [ [ 'text' => 'با امتیاز', 'callback_data' => 'lotw_entry_points' ], [ 'text' => 'با رفرال', 'callback_data' => 'lotw_entry_ref' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ]);
                exit;
            }
            if ($s === 'lot_w_entry_points') {
                if (!preg_match('/^\d+$/', $messageText)) { adminPrompt($chatId, $userId, 'عدد امتیاز ورود را بفرستید.', buildAdminPromptKeyboard()); exit; }
                $data = $st['data'] ?? [];
                $data['entry_cost_points'] = (int)$messageText;
                setAdminState($userId, 'lot_w_prize_type', $data);
                tgSendMessage($chatId, 'نوع جایزه را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => 'امتیازی', 'callback_data' => 'lotw_prize_points' ], [ 'text' => 'شخصی‌سازی', 'callback_data' => 'lotw_prize_text' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ] ]);
                exit;
            }
            if ($s === 'lot_w_entry_ref') {
                if (!preg_match('/^\d+$/', $messageText)) { adminPrompt($chatId, $userId, 'تعداد رفرال موردنیاز را بفرستید.', buildAdminPromptKeyboard()); exit; }
                $data = $st['data'] ?? [];
                $data['entry_cost_points'] = null;
                $data['referral_required_count'] = (int)$messageText;
                setAdminState($userId, 'lot_w_prize_type', $data);
                tgSendMessage($chatId, 'نوع جایزه را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => 'امتیازی', 'callback_data' => 'lotw_prize_points' ], [ 'text' => 'شخصی‌سازی', 'callback_data' => 'lotw_prize_text' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ] ]);
                exit;
            }
            if ($s === 'lot_w_prize_points') {
                if (!preg_match('/^\d+$/', $messageText)) { tgSendMessage($chatId, 'مقدار امتیاز جایزه را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]); exit; }
                $data = $st['data'] ?? [];
                $data['prize_points'] = (int)$messageText;
                $data['prize_text'] = null;
                // ask to add more prizes or continue
                $data['prizes'] = $data['prizes'] ?? [];
                $data['prizes'][] = [ 'rank' => count($data['prizes']) + 1, 'prize_points' => $data['prize_points'], 'prize_text' => null ];
                unset($data['prize_points']);
                setAdminState($userId, 'lot_w_prize_next', $data);
                tgSendMessage($chatId, 'آیا جایزه رتبه بعدی را اضافه می‌کنید یا ادامه دهیم؟', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن جایزه بعدی', 'callback_data' => 'lotw_prize_add_more' ], [ 'text' => 'ادامه', 'callback_data' => 'lotw_prize_continue' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ] ]);
                exit;
            }
            if ($s === 'lot_w_prize_text') {
                $data = $st['data'] ?? [];
                $ptxt = sanitizeText($messageText, 255);
                $data['prizes'] = $data['prizes'] ?? [];
                $data['prizes'][] = [ 'rank' => count($data['prizes']) + 1, 'prize_points' => null, 'prize_text' => $ptxt ];
                setAdminState($userId, 'lot_w_prize_next', $data);
                tgSendMessage($chatId, 'آیا جایزه رتبه بعدی را اضافه می‌کنید یا ادامه دهیم؟', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '➕ افزودن جایزه بعدی', 'callback_data' => 'lotw_prize_add_more' ], [ 'text' => 'ادامه', 'callback_data' => 'lotw_prize_continue' ] ], [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_back' ], [ 'text' => '❌ انصراف', 'callback_data' => 'admin_cancel' ] ] ] ] ]);
                exit;
            }
            if ($s === 'lot_w_photo') {
                $data = $st['data'] ?? [];
                $photoId = null;
                if (isset($update['message']['photo'])) {
                    $photos = $update['message']['photo'];
                    $largest = end($photos);
                    $photoId = $largest['file_id'] ?? null;
                } elseif (trim(mb_strtolower($messageText)) === 'رد') {
                    $photoId = null;
                }
                if (!array_key_exists('photo_file_id', $data)) { $data['photo_file_id'] = $photoId; }
                else { $data['photo_file_id'] = $photoId; }
                setAdminState($userId, 'lot_w_channels', $data);
                tgSendMessage($chatId, 'می‌توانید کانال‌های اجباری مخصوص این قرعه‌کشی را با ارسال @username یا chat_id یکی‌یکی اضافه کنید. برای پایان «تمام» را بفرستید.', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
                exit;
            }
            if ($s === 'lot_w_channels') {
                $data = $st['data'] ?? [];
                if (trim(mb_strtolower($messageText)) !== 'تمام') {
                    $ident = trim($messageText);
                    $botId = getBotUserId();
                    if ($ident !== '' && $botId) {
                        if ($ident[0] === '@') {
                            $username = ltrim($ident, '@');
                            $res = tgGetChat('@' . $username);
                            if ($res['ok'] ?? false) {
                                $chat = $res['result'];
                                $cid = (int) ($chat['id'] ?? 0);
                                $mem = tgGetChatMember($cid, $botId);
                                $stt = $mem['result']['status'] ?? '';
                                if (($mem['ok'] ?? false) && in_array($stt, ['administrator','creator'], true)) {
                                    pdo()->prepare('INSERT INTO custom_lottery_channels (lottery_id, chat_id, username, title, added_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), title=VALUES(title)')->execute([ -1, $cid, $username, ($chat['title'] ?? $username), nowUtc() ]);
                                    tgSendMessage($chatId, 'کانال افزوده شد. می‌توانید کانال دیگری بفرستید یا «تمام».', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
                                } else { tgSendMessage($chatId, 'ربات در این کانال ادمین نیست.'); }
                            } else { tgSendMessage($chatId, 'کانال یافت نشد.'); }
                        } elseif (preg_match('/^-?\d+$/', $ident)) {
                            $cid = (int) $ident;
                            $res = tgGetChat($cid);
                            $title = $res['ok'] ? ($res['result']['title'] ?? null) : null;
                            $mem = tgGetChatMember($cid, $botId);
                            $stt = $mem['result']['status'] ?? '';
                            if (($mem['ok'] ?? false) && in_array($stt, ['administrator','creator'], true)) {
                                pdo()->prepare('INSERT INTO custom_lottery_channels (lottery_id, chat_id, username, title, added_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), title=VALUES(title)')->execute([ -1, $cid, null, $title, nowUtc() ]);
                                tgSendMessage($chatId, 'کانال افزوده شد. می‌توانید کانال دیگری بفرستید یا «تمام».', [ 'reply_markup' => buildAdminPromptKeyboard() ]);
                            } else { tgSendMessage($chatId, 'ربات در این چت ادمین نیست.'); }
                        }
                    }
                    exit;
                }
                // finalize creation
                $title = $data['title'] ?? '';
                $entryCost = $data['entry_cost_points'] ?? null;
                $refNeed = $data['referral_required_count'] ?? 0;
                $prizePoints = $data['prize_points'] ?? 0;
                $prizeText = $data['prize_text'] ?? null;
                $photoId = $data['photo_file_id'] ?? null;
                $pdo = pdo();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('INSERT INTO custom_lotteries (title, entry_cost_points, entry_requires_referral, referral_bonus_per_invite, prize_points, is_active, created_at, referral_required_count, prize_text, photo_file_id) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');
                    $stmt->execute([$title, $entryCost, $entryCost === null ? 1 : 0, 0, (int)$prizePoints, nowUtc(), (int)$refNeed, $prizeText, $photoId]);
                    $lotId = (int) $pdo->lastInsertId();
                    // insert prize tiers if provided
                    $prizes = $data['prizes'] ?? [];
                    foreach ($prizes as $pz) {
                        $pdo->prepare('INSERT INTO custom_lottery_prizes (lottery_id, rank, prize_points, prize_text, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$lotId, (int)$pz['rank'], $pz['prize_points'], $pz['prize_text'], nowUtc()]);
                    }
                    // move channels (-1 placeholders) to this lot
                    $pdo->prepare('UPDATE custom_lottery_channels SET lottery_id = ? WHERE lottery_id = -1')->execute([$lotId]);
                    $pdo->commit();
                    clearAdminState($userId);
                    tgSendMessage($chatId, '🎲 قرعه‌کشی ایجاد شد: #' . $lotId);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    tgSendMessage($chatId, 'خطا در ایجاد قرعه‌کشی.');
                }
                exit;
            }
        }
    }

    // For non-admin users, enforce membership before using the bot
    if (!$isAdminUser) {
        if (!getBotEnabled()) { tgSendMessage($chatId, '⛔️ ربات توسط مدیریت خاموش شده است.'); exit; }
        if (!enforceMembershipGate($chatId, $userId, false)) exit;
    }

    switch ($messageText) {
        case '📊 امتیاز من':
            if (!isFeatureEnabled('points')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
            tgSendMessage($chatId, 'امتیاز شما: ' . getUserPoints($userId));
            break;
        case '📎 لینک دعوت من':
            if (!isFeatureEnabled('invite')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
            tgSendMessage($chatId, myInviteLink($userId));
            break;
        case '🛒 فروشگاه آیتم‌ها':
            if (!isFeatureEnabled('shop')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
            $items = listActiveItems();
            tgSendMessage($chatId, shopText(), [ 'reply_markup' => buildShopItemKeyboard($items) ]);
            break;
        case '📤 درخواست‌های من':
            if (!isFeatureEnabled('requests')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
            $reqs = listUserRequests($userId, 10);
            if (empty($reqs)) { tgSendMessage($chatId, 'درخواستی ثبت نکرده‌اید.'); break; }
            $lines = ['📤 درخواست‌های شما:'];
            foreach ($reqs as $r) {
                $lines[] = '#' . $r['id'] . ' | ' . $r['item_name'] . ' | ' . $r['cost_points'] . ' امتیاز | ' . ($r['status'] === 'pending' ? 'در حال بررسی' : ($r['status'] === 'approved' ? 'تایید شده' : 'رد شده'));
            }
            tgSendMessage($chatId, implode("\n", $lines));
            break;
        case '👤 پروفایل':
            if (!isFeatureEnabled('profile')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
            $u = getUser($userId);
            $photos = tgGetUserProfilePhotos($userId, 1);
            $caption = userProfileText($u);
            if (($photos['ok'] ?? false) && !empty($photos['result']['photos'][0][0]['file_id'])) {
                $fileId = $photos['result']['photos'][0][0]['file_id'];
                tgSendPhoto($chatId, $fileId, $caption);
            } else {
                tgSendMessage($chatId, $caption);
            }
            break;
        case '🏆 برترین‌ها':
            tgSendMessage($chatId, 'یک گزینه را انتخاب کنید:', [ 'reply_markup' => [ 'inline_keyboard' => [ [ [ 'text' => '👥 برترین‌های رفرال', 'callback_data' => 'top_ref' ], [ 'text' => '⭐ برترین‌های امتیاز', 'callback_data' => 'top_pts' ] ] ] ] ]);
            break;
        case '🎲 قرعه‌کشی':
            if (!isFeatureEnabled('lottery')) { tgSendMessage($chatId, 'این بخش غیرفعال است.'); break; }
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

function listLotteryChannels(int $lotteryId): array {
    $stmt = pdo()->prepare('SELECT * FROM custom_lottery_channels WHERE lottery_id = ? ORDER BY id ASC');
    $stmt->execute([$lotteryId]);
    return $stmt->fetchAll();
}

function isUserMemberAllLotteryChannels(int $userId, int $lotteryId): bool {
    $chs = listLotteryChannels($lotteryId);
    foreach ($chs as $c) {
        $res = tgGetChatMember((int)$c['chat_id'], $userId);
        if (!($res['ok'] ?? false)) return false;
        $status = $res['result']['status'] ?? '';
        if (!in_array($status, ['member','administrator','creator'], true)) return false;
    }
    return true;
}

function ensureBotAdminLotteryChannels(): void {
    $botId = getBotUserId(); if (!$botId) return;
    $rows = pdo()->query('SELECT DISTINCT l.id FROM custom_lottery_channels c JOIN custom_lotteries l ON l.id = c.lottery_id')->fetchAll();
    foreach ($rows as $r) {
        $lotId = (int) $r['id'];
        foreach (listLotteryChannels($lotId) as $c) {
            $res = tgGetChatMember((int)$c['chat_id'], $botId);
            $ok = ($res['ok'] ?? false) && in_array(($res['result']['status'] ?? ''), ['administrator','creator'], true);
            if (!$ok) { pdo()->prepare('DELETE FROM custom_lottery_channels WHERE id = ?')->execute([$c['id']]); }
        }
    }
}

function countUserReferrals(int $userId): int {
    $stmt = pdo()->prepare('SELECT referrals_count FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) ($stmt->fetch()['referrals_count'] ?? 0);
}

function isUserEligibleForLottery(array $lottery, int $userId): array {
    // Check per-lottery channels
    if (!isUserMemberAllLotteryChannels($userId, (int)$lottery['id'])) {
        return [false, 'برای شرکت، ابتدا در کانال‌های قرعه‌کشی عضو شوید.'];
    }
    // Referral-based entry
    if (is_null($lottery['entry_cost_points'])) {
        $need = max(0, (int)($lottery['referral_required_count'] ?? 0));
        if ($need > 0) {
            $have = countUserReferrals($userId);
            if ($have < $need) return [false, 'تعداد رفرال کافی ندارید.'];
        }
    }
    return [true, ''];
}

function adminPrompt(int $chatId, int $userId, string $text, array $replyMarkup = null): void {
    $st = getAdminState($userId);
    $data = $st['data'] ?? [];
    if (isset($data['last_msg_id'])) {
        try { tgDeleteMessage($chatId, (int)$data['last_msg_id']); } catch (Throwable $e) { /* ignore */ }
        unset($data['last_msg_id']);
    }
    $opts = [];
    if ($replyMarkup !== null) { $opts['reply_markup'] = $replyMarkup; }
    $sent = tgSendMessage($chatId, $text, $opts);
    if (($sent['ok'] ?? false) && isset($sent['result']['message_id']) && $st && isset($st['state'])) {
        $data['last_msg_id'] = (int)$sent['result']['message_id'];
        setAdminState($userId, $st['state'], $data);
    }
}

function resetAllUserPointsAndReferrals(): void {
    try {
        $pdo = pdo();
        $pdo->beginTransaction();
        $pdo->exec('UPDATE users SET points = 0, referrals_count = 0, referrer_id = NULL, pending_referrer_id = NULL, level = 1');
        $pdo->exec('DELETE FROM referrals');
        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    }
}

function isFeatureEnabled(string $feature): bool {
    return getSetting('feature_' . $feature, '1') === '1';
}

function buildAdminSettingsKeyboard(): array {
    $features = [
        'points' => '📊 امتیاز من',
        'invite' => '📎 لینک دعوت من',
        'shop' => '🛒 فروشگاه',
        'requests' => '📤 درخواست‌ها',
        'profile' => '👤 پروفایل',
        'lottery' => '🎲 قرعه‌کشی',
    ];
    $rows = [];
    foreach ($features as $k => $label) {
        $on = isFeatureEnabled($k);
        $txt = ($on ? '🔵 روشن' : '⚪ خاموش') . ' — ' . $label;
        $rows[] = [ [ 'text' => $txt, 'callback_data' => 'f_tog_' . $k ] ];
    }
    $rows[] = [ [ 'text' => '🔙 بازگشت', 'callback_data' => 'admin_main' ] ];
    return [ 'inline_keyboard' => $rows ];
}