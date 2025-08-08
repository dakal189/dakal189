<?php

/**
 * Single-file Telegram Bot in PHP with MySQL
 * Fully inline (inline keyboards), Persian UI, admin panel, user registration, bans, submissions,
 * roles with cost confirmation, assets, button settings, admin management with permissions,
 * wheel of fortune, alliances, and automatic cleanup of old support messages.
 *
 * IMPORTANT: Fill the configuration constants below before deploying.
 */

// --------------------- CONFIGURATION ---------------------

// Telegram bot token
const BOT_TOKEN = 'PASTE_YOUR_BOT_TOKEN_HERE';
const API_URL   = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';

// Main (owner) admin numeric ID
const MAIN_ADMIN_ID = 123456789; // Replace with your Telegram numeric ID

// Channel ID for posting statements/war announcements and wheel winners (e.g., -1001234567890)
const CHANNEL_ID = -1001234567890; // Replace with your channel ID

// Database credentials
const DB_HOST = '127.0.0.1';
const DB_NAME = 'telegram_bot';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// Security: optional secret path token for webhook URL validation (set to '' to disable)
const WEBHOOK_SECRET = '';

// Misc
date_default_timezone_set('Asia/Tehran');

// --------------------- INITIALIZATION ---------------------

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        bootstrapDatabase($pdo);
    }
    return $pdo;
}

function bootstrapDatabase(PDO $pdo): void {
    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        telegram_id BIGINT UNIQUE,
        username VARCHAR(64) NULL,
        first_name VARCHAR(128) NULL,
        last_name VARCHAR(128) NULL,
        is_registered TINYINT(1) NOT NULL DEFAULT 0,
        country VARCHAR(64) NULL,
        banned TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Admin users
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_telegram_id BIGINT UNIQUE,
        is_owner TINYINT(1) NOT NULL DEFAULT 0,
        permissions TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure main admin exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (admin_telegram_id, is_owner, permissions) VALUES (?, 1, ?)");
    $stmt->execute([MAIN_ADMIN_ID, json_encode(["all"]) ]);

    // User states (user-side wizards)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_states (
        user_id BIGINT PRIMARY KEY,
        state_key VARCHAR(64) NOT NULL,
        state_data TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Admin states (admin-side wizards)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_states (
        admin_id BIGINT PRIMARY KEY,
        state_key VARCHAR(64) NOT NULL,
        state_data TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Support messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        text TEXT NULL,
        photo_file_id VARCHAR(256) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('open','deleted') NOT NULL DEFAULT 'open',
        INDEX(user_id),
        CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Submissions (army, missile, defense, statement, war, role)
    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('army','missile','defense','statement','war','role') NOT NULL,
        text TEXT NULL,
        photo_file_id VARCHAR(256) NULL,
        attacker_country VARCHAR(64) NULL,
        defender_country VARCHAR(64) NULL,
        status ENUM('pending','approved','rejected','cost_proposed','user_confirmed','user_declined') NOT NULL DEFAULT 'pending',
        cost_amount INT NULL,
        processed_by_admin_id BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(type),
        CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Assets by country
    $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        country VARCHAR(64) UNIQUE,
        content TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Button settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS button_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(64) UNIQUE,
        title VARCHAR(64) NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed default buttons if not present
    $defaults = [
        ['army','لشکر کشی'],
        ['missile','حمله موشکی'],
        ['defense','دفاع'],
        ['roles','رول ها'],
        ['statement','بیانیه'],
        ['war','اعلام جنگ'],
        ['assets','لیست دارایی'],
        ['support','پشتیبانی'],
        ['alliance','اتحاد'],
        ['admin_panel','پنل مدیریت'],
    ];
    foreach ($defaults as [$key,$title]) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO button_settings (`key`, title, enabled) VALUES (?, ?, 1)");
        $stmt->execute([$key, $title]);
    }

    // Wheel settings (single row)
    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_settings (
        id INT PRIMARY KEY,
        current_prize VARCHAR(256) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO wheel_settings (id, current_prize) VALUES (1, NULL)");

    // Alliances
    $pdo->exec("CREATE TABLE IF NOT EXISTS alliances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(64) NOT NULL,
        leader_user_id INT NOT NULL,
        slogan TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_alliance_leader FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS alliance_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alliance_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('leader','member') NOT NULL DEFAULT 'member',
        display_name VARCHAR(128) NULL,
        UNIQUE KEY unique_member (alliance_id, user_id),
        CONSTRAINT fk_member_alliance FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE,
        CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS alliance_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alliance_id INT NOT NULL,
        invitee_user_id INT NOT NULL,
        inviter_user_id INT NOT NULL,
        status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_invite_alliance FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE,
        CONSTRAINT fk_invite_invitee FOREIGN KEY (invitee_user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_invite_inviter FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// --------------------- TELEGRAM HELPERS ---------------------

function apiRequest(string $method, array $params = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML', $replyToMessageId = null) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($replyMarkup) $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
    if ($replyToMessageId) $params['reply_to_message_id'] = $replyToMessageId;
    return apiRequest('sendMessage', $params);
}

function editMessageText($chatId, $messageId, $text, $replyMarkup = null, $parseMode = 'HTML') {
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($replyMarkup) $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
    return apiRequest('editMessageText', $params);
}

function sendPhoto($chatId, $fileIdOrUrl, $caption = '', $replyMarkup = null, $parseMode = 'HTML') {
    $params = [
        'chat_id' => $chatId,
        'photo' => $fileIdOrUrl,
        'caption' => $caption,
        'parse_mode' => $parseMode,
    ];
    if ($replyMarkup) $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
    return apiRequest('sendPhoto', $params);
}

function answerCallback($callbackId, $text = '', $alert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $alert ? true : false,
    ]);
}

function sendToChannel($text, $parseMode = 'HTML') {
    return apiRequest('sendMessage', [
        'chat_id' => CHANNEL_ID,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ]);
}

function sendPhotoToChannel($fileIdOrUrl, $caption = '', $parseMode = 'HTML') {
    return apiRequest('sendPhoto', [
        'chat_id' => CHANNEL_ID,
        'photo' => $fileIdOrUrl,
        'caption' => $caption,
        'parse_mode' => $parseMode,
    ]);
}

// --------------------- UTILS ---------------------

function e($str): string { return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function isOwner(int $telegramId): bool {
    return $telegramId === MAIN_ADMIN_ID;
}

function getAdminPermissions(int $telegramId): array {
    $stmt = db()->prepare("SELECT is_owner, permissions FROM admin_users WHERE admin_telegram_id = ?");
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    if ((int)$row['is_owner'] === 1) return ['all'];
    $perms = $row['permissions'] ? json_decode($row['permissions'], true) : [];
    if (!is_array($perms)) $perms = [];
    return $perms;
}

function hasPerm(int $telegramId, string $perm): bool {
    $perms = getAdminPermissions($telegramId);
    if (in_array('all', $perms, true)) return true;
    return in_array($perm, $perms, true);
}

function userByTelegramId(int $telegramId): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function ensureUser(array $from): array {
    $telegramId = (int)$from['id'];
    $username = isset($from['username']) ? $from['username'] : null;
    $first = isset($from['first_name']) ? $from['first_name'] : null;
    $last = isset($from['last_name']) ? $from['last_name'] : null;
    $u = userByTelegramId($telegramId);
    if ($u) {
        $stmt = db()->prepare("UPDATE users SET username=?, first_name=?, last_name=?, updated_at=NOW() WHERE telegram_id=?");
        $stmt->execute([$username, $first, $last, $telegramId]);
        return userByTelegramId($telegramId);
    } else {
        $stmt = db()->prepare("INSERT INTO users (telegram_id, username, first_name, last_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$telegramId, $username, $first, $last]);
        return userByTelegramId($telegramId);
    }
}

function getInlineButtonTitle(string $key): string {
    $stmt = db()->prepare("SELECT title FROM button_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['title'] : $key;
}

function isButtonEnabled(string $key): bool {
    $stmt = db()->prepare("SELECT enabled FROM button_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (int)$row['enabled'] === 1 : true;
}

function mainMenuKeyboard(bool $isRegistered, bool $isAdmin): array {
    $btn = function($key, $cb) {
        return ['text' => getInlineButtonTitle($key), 'callback_data' => $cb];
    };
    $rows = [];
    if ($isRegistered) {
        $line = [];
        if (isButtonEnabled('army')) $line[] = $btn('army', 'nav:army');
        if (isButtonEnabled('missile')) $line[] = $btn('missile', 'nav:missile');
        if ($line) $rows[] = $line;
        $line = [];
        if (isButtonEnabled('defense')) $line[] = $btn('defense', 'nav:defense');
        if (isButtonEnabled('roles')) $line[] = $btn('roles', 'nav:roles');
        if ($line) $rows[] = $line;
        $line = [];
        if (isButtonEnabled('statement')) $line[] = $btn('statement', 'nav:statement');
        if (isButtonEnabled('war')) $line[] = $btn('war', 'nav:war');
        if ($line) $rows[] = $line;
        $line = [];
        if (isButtonEnabled('assets')) $line[] = $btn('assets', 'nav:assets');
        if (isButtonEnabled('support')) $line[] = $btn('support', 'nav:support');
        if ($line) $rows[] = $line;
        $line = [];
        if (isButtonEnabled('alliance')) $line[] = $btn('alliance', 'nav:alliance');
        if ($line) $rows[] = $line;
    } else {
        $line = [];
        if (isButtonEnabled('support')) $line[] = $btn('support', 'nav:support');
        $rows[] = $line;
    }
    if ($isAdmin) {
        $rows[] = [ ['text' => getInlineButtonTitle('admin_panel'), 'callback_data' => 'nav:admin'] ];
    }
    return ['inline_keyboard' => $rows];
}

function backButton(string $to): array { return ['inline_keyboard' => [ [ ['text' => 'بازگشت', 'callback_data' => $to] ] ]]; }

function usernameLink(?string $username, int $tgId): string {
    if ($username) {
        return '<a href="https://t.me/' . e($username) . '">@' . e($username) . '</a>';
    }
    return '<a href="tg://user?id=' . $tgId . '">کاربر</a>';
}

function notifySectionAdmins(string $sectionKey, string $text): void {
    $pdo = db();
    $q = $pdo->query("SELECT admin_telegram_id, is_owner, permissions FROM admin_users");
    foreach ($q as $row) {
        $adminId = (int)$row['admin_telegram_id'];
        $perms = (int)$row['is_owner'] === 1 ? ['all'] : ( ($row['permissions'] ? json_decode($row['permissions'], true) : []) ?: [] );
        if (in_array('all', $perms, true) || in_array($sectionKey, $perms, true)) {
            sendMessage($adminId, $text);
        }
    }
}

function notifyNewSupportMessage(int $supportId): void {
    $stmt = db()->prepare("SELECT sm.*, u.telegram_id, u.username, u.country, u.created_at AS user_created FROM support_messages sm JOIN users u ON u.id=sm.user_id WHERE sm.id=?");
    $stmt->execute([$supportId]);
    $r = $stmt->fetch();
    if (!$r) return;
    $hdr = 'یک پیام پشتیبانی تازه دارید' . "\n" . usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: " . (int)$r['telegram_id'] . "\nزمان: " . iranDateTime($r['created_at']);
    $body = $hdr . "\n\n" . ($r['text'] ? e($r['text']) : '');
    $kb = [ [ ['text'=>'کپی ایدی','callback_data'=>'admin:copyid|id='.(int)$r['telegram_id']], ['text'=>'مشاهده در پنل','callback_data'=>'admin:support_view|id='.$supportId.'|page=1'] ] ];
    $q = db()->query("SELECT admin_telegram_id, is_owner, permissions FROM admin_users");
    foreach ($q as $row) {
        $adminId = (int)$row['admin_telegram_id'];
        $perms = (int)$row['is_owner'] === 1 ? ['all'] : ( ($row['permissions'] ? json_decode($row['permissions'], true) : []) ?: [] );
        if (in_array('all', $perms, true) || in_array('support', $perms, true)) {
            if ($r['photo_file_id']) {
                sendPhoto($adminId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]);
            } else {
                sendMessage($adminId, $body, ['inline_keyboard'=>$kb]);
            }
        }
    }
}

function purgeOldSupportMessages(): void {
    $stmt = db()->prepare("DELETE FROM support_messages WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    $stmt->execute();
}

function setUserState(int $tgId, string $key, array $data = []): void {
    $stmt = db()->prepare("INSERT INTO user_states (user_id, state_key, state_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE state_key=VALUES(state_key), state_data=VALUES(state_data), updated_at=NOW()");
    $stmt->execute([$tgId, $key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function clearUserState(int $tgId): void {
    $stmt = db()->prepare("DELETE FROM user_states WHERE user_id = ?");
    $stmt->execute([$tgId]);
}

function getUserState(int $tgId): ?array {
    $stmt = db()->prepare("SELECT state_key, state_data FROM user_states WHERE user_id = ?");
    $stmt->execute([$tgId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $data = $row['state_data'] ? json_decode($row['state_data'], true) : [];
    if (!is_array($data)) $data = [];
    return ['key' => $row['state_key'], 'data' => $data];
}

function setAdminState(int $tgId, string $key, array $data = []): void {
    $stmt = db()->prepare("INSERT INTO admin_states (admin_id, state_key, state_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE state_key=VALUES(state_key), state_data=VALUES(state_data), updated_at=NOW()");
    $stmt->execute([$tgId, $key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function clearAdminState(int $tgId): void {
    $stmt = db()->prepare("DELETE FROM admin_states WHERE admin_id = ?");
    $stmt->execute([$tgId]);
}

function getAdminState(int $tgId): ?array {
    $stmt = db()->prepare("SELECT state_key, state_data FROM admin_states WHERE admin_id = ?");
    $stmt->execute([$tgId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $data = $row['state_data'] ? json_decode($row['state_data'], true) : [];
    if (!is_array($data)) $data = [];
    return ['key' => $row['state_key'], 'data' => $data];
}

function iranDateTime(string $datetime): string {
    return date('Y-m-d H:i', strtotime($datetime));
}

function paginate(int $page, int $perPage): array {
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    return [$offset, $perPage];
}

function paginationKeyboard(string $baseCb, int $page, bool $hasMore, string $backCb): array {
    $buttons = [];
    $nav = [];
    if ($page > 1) $nav[] = ['text' => 'قبلی', 'callback_data' => $baseCb . '|page=' . ($page - 1)];
    if ($hasMore) $nav[] = ['text' => 'بعدی', 'callback_data' => $baseCb . '|page=' . ($page + 1)];
    if ($nav) $buttons[] = $nav;
    $buttons[] = [ ['text' => 'بازگشت', 'callback_data' => $backCb] ];
    return ['inline_keyboard' => $buttons];
}

function cbParse(string $data): array {
    // Format: action:route|k=v|k2=v2
    $parts = explode('|', $data);
    $action = array_shift($parts);
    $params = [];
    foreach ($parts as $p) {
        $kv = explode('=', $p, 2);
        if (count($kv) === 2) $params[$kv[0]] = $kv[1];
    }
    return [$action, $params];
}

// --------------------- CORE HANDLERS ---------------------

function handleStart(array $userRow): void {
    $chatId = (int)$userRow['telegram_id'];
    if ((int)$userRow['banned'] === 1) {
        sendMessage($chatId, 'شما از ربات بن هستید.');
        return;
    }
    $isRegistered = (int)$userRow['is_registered'] === 1;
    $isAdmin = getAdminPermissions($chatId) ? true : false;
    $text = $isRegistered ? 'به ربات خوش آمدید. از منو گزینه مورد نظر را انتخاب کنید.' : 'به ربات خوش آمدید. تا زمان ثبت شما توسط ادمین فقط می‌توانید با پشتیبانی در ارتباط باشید.';
    sendMessage($chatId, $text, mainMenuKeyboard($isRegistered, $isAdmin));
}

function handleNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {
    if ((int)$userRow['banned'] === 1) {
        editMessageText($chatId, $messageId, 'شما از ربات بن هستید.');
        return;
    }
    $isRegistered = (int)$userRow['is_registered'] === 1;
    $isAdmin = getAdminPermissions($chatId) ? true : false;

    switch ($route) {
        case 'home':
            editMessageText($chatId, $messageId, $isRegistered ? 'منوی اصلی' : 'فقط پشتیبانی در دسترس است.', mainMenuKeyboard($isRegistered, $isAdmin));
            break;
        case 'support':
            setUserState($chatId, 'await_support', []);
            editMessageText($chatId, $messageId, 'پیام خود را برای پشتیبانی ارسال کنید.', backButton('nav:home'));
            break;
        case 'army':
        case 'missile':
        case 'defense':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            setUserState($chatId, 'await_submission', ['type' => $route]);
            editMessageText($chatId, $messageId, 'متن یا عکس خود را ارسال کنید.', backButton('nav:home'));
            break;
        case 'statement':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            setUserState($chatId, 'await_submission', ['type' => 'statement']);
            editMessageText($chatId, $messageId, 'بیانیه خود را به صورت متن یا همراه با عکس ارسال کنید.', backButton('nav:home'));
            break;
        case 'war':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            setUserState($chatId, 'await_war_format', []);
            $msg = "فرمت اعلام جنگ:\nنام کشور حمله کننده : ...\nنام کشور دفاع کننده : ...\nسپس می‌توانید متن یا عکس نیز بفرستید.";
            editMessageText($chatId, $messageId, $msg, backButton('nav:home'));
            break;
        case 'roles':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            setUserState($chatId, 'await_role_text', []);
            editMessageText($chatId, $messageId, 'متن رول خود را ارسال کنید. (فقط متن)', backButton('nav:home'));
            break;
        case 'assets':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            $country = $userRow['country'];
            $stmt = db()->prepare("SELECT content FROM assets WHERE country = ?");
            $stmt->execute([$country]);
            $row = $stmt->fetch();
            $content = $row && $row['content'] ? $row['content'] : 'دارایی برای کشور شما ثبت نشده است.';
            editMessageText($chatId, $messageId, 'دارایی های شما (' . e($country) . "):\n\n" . e($content), backButton('nav:home'));
            break;
        case 'alliance':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            renderAllianceHome($chatId, $messageId, $userRow);
            break;
        case 'admin':
            if (!getAdminPermissions($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید.', true); return; }
            renderAdminHome($chatId, $messageId, $userRow);
            break;
        default:
            answerCallback($_POST['callback_query']['id'] ?? '', 'دستور ناشناخته', true);
    }
}

function renderAdminHome(int $chatId, int $messageId, array $userRow): void {
    $perms = getAdminPermissions($chatId);
    $rows = [];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'support')) $rows[] = [ ['text' => 'پیام های پشتیبانی', 'callback_data' => 'admin:support|page=1'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'army') || hasPerm($chatId, 'missile') || hasPerm($chatId, 'defense')) $rows[] = [ ['text' => 'لشکر/موشکی/دفاع', 'callback_data' => 'admin:amd'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'statement') || hasPerm($chatId, 'war')) $rows[] = [ ['text' => 'اعلام جنگ / بیانیه', 'callback_data' => 'admin:sw'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'roles')) $rows[] = [ ['text' => 'رول ها', 'callback_data' => 'admin:roles|page=1'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'assets')) $rows[] = [ ['text' => 'دارایی ها', 'callback_data' => 'admin:assets'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'settings')) $rows[] = [ ['text' => 'تنظیمات دکمه ها', 'callback_data' => 'admin:buttons'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'users')) $rows[] = [ ['text' => 'کاربران ثبت شده', 'callback_data' => 'admin:users'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'bans')) $rows[] = [ ['text' => 'مدیریت بن', 'callback_data' => 'admin:bans'] ];
    if (in_array('all', $perms, true) || hasPerm($chatId, 'wheel')) $rows[] = [ ['text' => 'گردونه شانس', 'callback_data' => 'admin:wheel'] ];
    if (isOwner($chatId)) $rows[] = [ ['text' => 'مدیریت ادمین ها', 'callback_data' => 'admin:admins'] ];
    $rows[] = [ ['text' => 'بازگشت', 'callback_data' => 'nav:home'] ];
    editMessageText($chatId, $messageId, 'پنل مدیریت', ['inline_keyboard' => $rows]);
}

// --------------------- ADMIN SECTIONS ---------------------

function handleAdminNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {
    switch ($route) {
        case 'support':
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $perPage = 10; [$offset,$limit] = paginate($page,$perPage);
            $total = db()->query("SELECT COUNT(*) c FROM support_messages WHERE status='open'")->fetch()['c'] ?? 0;
            $stmt = db()->prepare("SELECT sm.id, sm.created_at, u.username, u.telegram_id FROM support_messages sm JOIN users u ON u.id=sm.user_id WHERE sm.status='open' ORDER BY sm.created_at ASC LIMIT ?,?");
            $stmt->bindValue(1, $offset, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $text = "لیست پیام های پشتیبانی (قدیمی ترین اول):\n";
            $kbRows = [];
            foreach ($rows as $r) {
                $label = iranDateTime($r['created_at']) . ' - ' . ($r['username'] ? '@'.$r['username'] : $r['telegram_id']);
                $kbRows[] = [ ['text' => $label, 'callback_data' => 'admin:support_view|id='.$r['id'].'|page='.$page] ];
            }
            $hasMore = ($offset + count($rows)) < $total;
            $navKb = paginationKeyboard('admin:support', $page, $hasMore, 'nav:admin');
            $kb = array_merge($kbRows, $navKb['inline_keyboard']);
            editMessageText($chatId, $messageId, $text, ['inline_keyboard' => $kb]);
            break;
        case 'support_view':
            $id = (int)$params['id']; $page = (int)($params['page'] ?? 1);
            $stmt = db()->prepare("SELECT sm.*, u.telegram_id, u.username FROM support_messages sm JOIN users u ON u.id=sm.user_id WHERE sm.id=?");
            $stmt->execute([$id]); $r = $stmt->fetch();
            if (!$r) { answerCallback($_POST['callback_query']['id'] ?? '', 'پیدا نشد', true); return; }
            $hdr = usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: " . (int)$r['telegram_id'] . "\nزمان: " . iranDateTime($r['created_at']);
            $kb = [
                [ ['text'=>'کپی ایدی','callback_data'=>'admin:copyid|id='.$r['telegram_id']], ['text'=>'حذف','callback_data'=>'admin:support_del|id='.$id.'|page='.$page] ],
                [ ['text'=>'بازگشت','callback_data'=>'admin:support|page='.$page] ]
            ];
            $body = $hdr . "\n\n" . ($r['text'] ? e($r['text']) : '');
            if ($r['photo_file_id']) {
                sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]);
            } else {
                editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$kb]);
            }
            break;
        case 'copyid':
            $tid = (int)$params['id'];
            answerCallback($_POST['callback_query']['id'] ?? '', 'ID: ' . $tid, true);
            break;
        case 'support_del':
            $id = (int)$params['id']; $page = (int)($params['page'] ?? 1);
            $stmt = db()->prepare("UPDATE support_messages SET status='deleted' WHERE id=?");
            $stmt->execute([$id]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');
            handleAdminNav($chatId, $messageId, 'support', ['page'=>$page], $userRow);
            break;
        case 'amd':
            $kb = [
                [ ['text'=>'لشکر کشی','callback_data'=>'admin:amd_list|type=army|page=1'], ['text'=>'حمله موشکی','callback_data'=>'admin:amd_list|type=missile|page=1'] ],
                [ ['text'=>'دفاع','callback_data'=>'admin:amd_list|type=defense|page=1'] ],
                [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ]
            ];
            editMessageText($chatId, $messageId, 'انتخاب بخش', ['inline_keyboard'=>$kb]);
            break;
        case 'amd_list':
            $type = $params['type'] ?? 'army'; $page = (int)($params['page'] ?? 1);
            $perPage=10; [$offset,$limit]=paginate($page,$perPage);
            $total = db()->prepare("SELECT COUNT(*) c FROM submissions WHERE type=?"); $total->execute([$type]); $ttl=$total->fetch()['c']??0;
            $stmt = db()->prepare("SELECT s.id, s.created_at, u.username, u.telegram_id, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.type=? ORDER BY u.country ASC, s.created_at ASC LIMIT ?,?");
            $stmt->bindValue(1, $type); $stmt->bindValue(2, $offset, PDO::PARAM_INT); $stmt->bindValue(3, $limit, PDO::PARAM_INT); $stmt->execute();
            $rows = $stmt->fetchAll();
            $kbRows=[]; foreach($rows as $r){
                $label = e($r['country']).' | '.iranDateTime($r['created_at']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']);
                $kbRows[] = [ ['text'=>$label,'callback_data'=>'admin:amd_view|id='.$r['id'].'|type='.$type.'|page='.$page] ];
            }
            $hasMore = ($offset + count($rows)) < $ttl;
            $kb = array_merge($kbRows, paginationKeyboard('admin:amd_list|type='.$type, $page, $hasMore, 'admin:amd')['inline_keyboard']);
            $title = $type==='army'?'لشکرکشی':($type==='missile'?'حمله موشکی':'دفاع');
            editMessageText($chatId, $messageId, 'لیست ' . $title, ['inline_keyboard'=>$kb]);
            break;
        case 'amd_view':
            $id=(int)$params['id']; $type=$params['type']??'army'; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.username, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");
            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $hdr = usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: " . (int)$r['telegram_id'] . "\nکشور: " . e($r['country']) . "\nزمان: " . iranDateTime($r['created_at']);
            $kb = [ [ ['text'=>'کپی ایدی','callback_data'=>'admin:copyid|id='.$r['telegram_id']], ['text'=>'حذف','callback_data'=>'admin:amd_del|id='.$id.'|type='.$type.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:amd_list|type='.$type.'|page='.$page] ] ];
            $body = $hdr . "\n\n" . ($r['text']?e($r['text']):'');
            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]);
            else editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$kb]);
            break;
        case 'amd_del':
            $id=(int)$params['id']; $type=$params['type']??'army'; $page=(int)($params['page']??1);
            $stmt = db()->prepare("DELETE FROM submissions WHERE id=?"); $stmt->execute([$id]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');
            handleAdminNav($chatId,$messageId,'amd_list',['type'=>$type,'page'=>$page],$userRow);
            break;
        case 'sw':
            $kb = [ [ ['text'=>'بیانیه ها','callback_data'=>'admin:sw_list|type=statement|page=1'], ['text'=>'اعلام جنگ ها','callback_data'=>'admin:sw_list|type=war|page=1'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId, $messageId, 'انتخاب بخش', ['inline_keyboard'=>$kb]);
            break;
        case 'sw_list':
            $type = $params['type']??'statement'; $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);
            $total = db()->prepare("SELECT COUNT(*) c FROM submissions WHERE type=?"); $total->execute([$type]); $ttl=$total->fetch()['c']??0;
            $stmt = db()->prepare("SELECT s.id, s.created_at, u.username, u.telegram_id, COALESCE(s.attacker_country, u.country) AS country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.type=? ORDER BY s.created_at ASC LIMIT ?,?");
            $stmt->bindValue(1,$type); $stmt->bindValue(2,$offset,PDO::PARAM_INT); $stmt->bindValue(3,$limit,PDO::PARAM_INT); $stmt->execute();
            $rows=$stmt->fetchAll(); $kbRows=[]; foreach($rows as $r){ $label = e($r['country']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.iranDateTime($r['created_at']); $kbRows[] = [ ['text'=>$label,'callback_data'=>'admin:sw_view|id='.$r['id'].'|type='.$type.'|page='.$page] ]; }
            $hasMore = ($offset + count($rows)) < $ttl;
            $kb = array_merge($kbRows, paginationKeyboard('admin:sw_list|type='.$type, $page, $hasMore, 'admin:sw')['inline_keyboard']);
            $title = $type==='statement'?'بیانیه ها':'اعلام جنگ ها';
            editMessageText($chatId,$messageId,$title,['inline_keyboard'=>$kb]);
            break;
        case 'sw_view':
            $id=(int)$params['id']; $type=$params['type']??'statement'; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.username, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");
            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $countryLine = $type==='war' ? ('کشور حمله کننده: '.e($r['attacker_country'])."\n".'کشور دفاع کننده: '.e($r['defender_country'])) : ('کشور: '.e($r['country']));
            $hdr = 'فرستنده: ' . usernameLink($r['username'],(int)$r['telegram_id'])."\n".$countryLine."\nزمان: ".iranDateTime($r['created_at']);
            $kb = [ [ ['text'=>'فرستادن به کانال','callback_data'=>'admin:sw_send|id='.$id.'|type='.$type.'|page='.$page], ['text'=>'حذف','callback_data'=>'admin:sw_del|id='.$id.'|type='.$type.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:sw_list|type='.$type.'|page='.$page] ] ];
            $body = $hdr . "\n\n" . ($r['text']?e($r['text']):'');
            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]); else editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$kb]);
            break;
        case 'sw_send':
            $id=(int)$params['id']; $type=$params['type']??'statement'; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.*, u.username FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");
            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $title = $type==='war' ? ('اعلام جنگ\nحمله کننده: '.e($r['attacker_country'])."\nدفاع کننده: ".e($r['defender_country'])) : 'بیانیه';
            $text = $title . "\n\n" . ($r['text']?e($r['text']):'') . "\n\n" . 'فرستنده: ' . ($r['username'] ? '@'.e($r['username']) : '');
            if ($r['photo_file_id']) sendPhotoToChannel($r['photo_file_id'], $text); else sendToChannel($text);
            answerCallback($_POST['callback_query']['id'] ?? '', 'ارسال شد');
            break;
        case 'sw_del':
            $id=(int)$params['id']; $type=$params['type']??'statement'; $page=(int)($params['page']??1);
            $stmt = db()->prepare("DELETE FROM submissions WHERE id=?"); $stmt->execute([$id]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');
            handleAdminNav($chatId,$messageId,'sw_list',['type'=>$type,'page'=>$page],$userRow);
            break;
        case 'roles':
            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);
            $total = db()->query("SELECT COUNT(*) c FROM submissions WHERE type='role' AND status IN ('pending','cost_proposed')")->fetch()['c']??0;
            $stmt = db()->prepare("SELECT s.id, s.created_at, u.username, u.telegram_id, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.type='role' AND s.status IN ('pending','cost_proposed') ORDER BY s.created_at ASC LIMIT ?,?");
            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
            $kbRows=[]; foreach($rows as $r){ $label = e($r['country']).' | '.iranDateTime($r['created_at']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kbRows[] = [ ['text'=>$label,'callback_data'=>'admin:role_view|id='.$r['id'].'|page='.$page] ]; }
            $hasMore = ($offset + count($rows)) < $total;
            $kb = array_merge($kbRows, paginationKeyboard('admin:roles', $page, $hasMore, 'nav:admin')['inline_keyboard']);
            editMessageText($chatId,$messageId,'رول ها',['inline_keyboard'=>$kb]);
            break;
        case 'role_view':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.username, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");
            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $hdr = usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: " . (int)$r['telegram_id'] . "\nکشور: " . e($r['country']);
            $buttons = [
                [ ['text'=>'تایید رول','callback_data'=>'admin:role_ok|id='.$id.'|page='.$page], ['text'=>'رد رول','callback_data'=>'admin:role_reject|id='.$id.'|page='.$page] ],
                [ ['text'=>'هزینه رول شما','callback_data'=>'admin:role_cost|id='.$id.'|page='.$page] ],
                [ ['text'=>'بازگشت','callback_data'=>'admin:roles|page='.$page] ]
            ];
            $body = $hdr . "\n\n" . ($r['text']?e($r['text']):'');
            editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$buttons]);
            break;
        case 'role_ok':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.user_id, u.telegram_id FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $pdo=db(); $pdo->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);
            sendMessage((int)$r['telegram_id'], 'رول شما تایید شد.');
            answerCallback($_POST['callback_query']['id'] ?? '', 'انجام شد');
            handleAdminNav($chatId,$messageId,'roles',['page'=>$page],$userRow);
            break;
        case 'role_reject':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            $stmt = db()->prepare("SELECT s.user_id, u.telegram_id FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);
            sendMessage((int)$r['telegram_id'], 'رول شما رد شد و شکست خورد.');
            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');
            handleAdminNav($chatId,$messageId,'roles',['page'=>$page],$userRow);
            break;
        case 'role_cost':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            setAdminState($chatId,'await_role_cost',['submission_id'=>$id,'page'=>$page]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'هزینه را ارسال کنید (عدد)');
            break;
        case 'assets':
            // Show country list from assets + users countries
            $rows = db()->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND is_registered=1 ORDER BY country ASC")->fetchAll();
            $kb=[]; foreach($rows as $r){ $country=$r['country']; if(!$country) continue; $kb[] = [ ['text'=>$country, 'callback_data'=>'admin:asset_edit|country='.urlencode($country)] ]; }
            $kb[] = [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ];
            editMessageText($chatId,$messageId,'انتخاب کشور برای ویرایش دارایی',['inline_keyboard'=>$kb]);
            break;
        case 'asset_edit':
            $country = urldecode($params['country'] ?? ''); if(!$country){ answerCallback($_POST['callback_query']['id']??'','کشور نامعتبر',true); return; }
            setAdminState($chatId,'await_asset_text',['country'=>$country]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'متن دارایی را ارسال کنید');
            break;
        case 'buttons':
            $rows = db()->query("SELECT `key`, title, enabled FROM button_settings WHERE `key` IN ('army','missile','defense','roles','statement','war','assets','support','alliance') ORDER BY id ASC")->fetchAll();
            $kb=[]; foreach($rows as $r){ $txt = ($r['enabled']? 'روشن':'خاموش').' - '.$r['title']; $kb[] = [ ['text'=>$txt, 'callback_data'=>'admin:btn_toggle|key='.$r['key']] , ['text'=>'تغییر نام','callback_data'=>'admin:btn_rename|key='.$r['key']] ]; }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ];
            editMessageText($chatId,$messageId,'تنظیمات دکمه ها',['inline_keyboard'=>$kb]);
            break;
        case 'btn_toggle':
            $key=$params['key']??''; if(!$key){ answerCallback($_POST['callback_query']['id']??'','نامعتبر',true); return; }
            db()->prepare("UPDATE button_settings SET enabled = 1 - enabled WHERE `key`=?")->execute([$key]);
            handleAdminNav($chatId,$messageId,'buttons',[],$userRow);
            break;
        case 'btn_rename':
            $key=$params['key']??''; if(!$key){ answerCallback($_POST['callback_query']['id']??'','نامعتبر',true); return; }
            setAdminState($chatId,'await_btn_rename',['key'=>$key]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'نام جدید دکمه را ارسال کنید');
            break;
        case 'users':
            $kb=[ [ ['text'=>'ثبت کاربر','callback_data'=>'admin:user_register'] , ['text'=>'لیست کاربران','callback_data'=>'admin:user_list|page=1'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId,$messageId,'مدیریت کاربران',['inline_keyboard'=>$kb]);
            break;
        case 'user_register':
            setAdminState($chatId,'await_user_ident',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');
            break;
        case 'user_list':
            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);
            $total = db()->query("SELECT COUNT(*) c FROM users WHERE is_registered=1")->fetch()['c']??0;
            $stmt = db()->prepare("SELECT id, telegram_id, username, country FROM users WHERE is_registered=1 ORDER BY country ASC, id ASC LIMIT ?,?");
            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
            $kbRows=[]; foreach($rows as $r){ $label = e($r['country']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kbRows[]=[ ['text'=>$label, 'callback_data'=>'admin:user_view|id='.$r['id'].'|page='.$page] ]; }
            $hasMore = ($offset + count($rows)) < $total;
            $kb = array_merge($kbRows, paginationKeyboard('admin:user_list', $page, $hasMore, 'admin:users')['inline_keyboard']);
            editMessageText($chatId,$messageId,'کاربران ثبت شده',['inline_keyboard'=>$kb]);
            break;
        case 'user_view':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            $stmt=db()->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $hdr = usernameLink($r['username'], (int)$r['telegram_id'])."\nID: ".$r['telegram_id']."\nکشور: ".e($r['country'])."\nثبت: ".((int)$r['is_registered']?'بله':'خیر')."\nبن: ".((int)$r['banned']?'بله':'خیر');
            $kb=[ [ ['text'=>'حذف کاربر','callback_data'=>'admin:user_del|id='.$id.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:user_list|page='.$page] ] ];
            editMessageText($chatId,$messageId,$hdr,['inline_keyboard'=>$kb]);
            break;
        case 'user_del':
            $id=(int)$params['id']; $page=(int)($params['page']??1);
            db()->prepare("UPDATE users SET is_registered=0, country=NULL WHERE id=?")->execute([$id]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');
            handleAdminNav($chatId,$messageId,'user_list',['page'=>$page],$userRow);
            break;
        case 'bans':
            $kb=[ [ ['text'=>'بن کاربر','callback_data'=>'admin:ban_add'], ['text'=>'حذف بن','callback_data'=>'admin:ban_remove'] ], [ ['text'=>'لیست بن ها','callback_data'=>'admin:ban_list'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId,$messageId,'مدیریت بن',['inline_keyboard'=>$kb]);
            break;
        case 'ban_add':
            setAdminState($chatId,'await_ban_ident',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');
            break;
        case 'ban_remove':
            setAdminState($chatId,'await_unban_ident',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');
            break;
        case 'ban_list':
            $rows = db()->query("SELECT username, telegram_id FROM users WHERE banned=1 ORDER BY id ASC LIMIT 100")->fetchAll();
            $lines = array_map(function($r){ return ($r['username']?'@'.$r['username']:$r['telegram_id']); }, $rows);
            editMessageText($chatId,$messageId, $lines ? ("لیست بن ها:\n".implode("\n",$lines)) : 'لیستی وجود ندارد', backButton('admin:bans'));
            break;
        case 'wheel':
            $kb=[ [ ['text'=>'ثبت جایزه گردونه شانس','callback_data'=>'admin:wheel_set'] ], [ ['text'=>'شروع گردونه شانس','callback_data'=>'admin:wheel_start'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId,$messageId,'گردونه شانس',['inline_keyboard'=>$kb]);
            break;
        case 'wheel_set':
            setAdminState($chatId,'await_wheel_prize',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'نام جایزه را ارسال کنید');
            break;
        case 'wheel_start':
            $row = db()->query("SELECT current_prize FROM wheel_settings WHERE id=1")->fetch();
            $prize = $row ? $row['current_prize'] : null;
            if (!$prize) { answerCallback($_POST['callback_query']['id'] ?? '', 'ابتدا جایزه را ثبت کنید', true); return; }
            $u = db()->query("SELECT id, telegram_id, username, country FROM users WHERE is_registered=1 AND banned=0 ORDER BY RAND() LIMIT 1")->fetch();
            if (!$u) { answerCallback($_POST['callback_query']['id'] ?? '', 'کاربری یافت نشد', true); return; }
            $msg = 'برنده: ' . ($u['username'] ? ('@'.$u['username']) : $u['telegram_id']) . "\n" . 'کشور: ' . e($u['country']) . "\n" . 'جایزه: ' . e($prize);
            sendToChannel($msg);
            sendMessage((int)$u['telegram_id'], 'تبریک! شما برنده گردونه شانس شدید.\nجایزه: ' . e($prize));
            answerCallback($_POST['callback_query']['id'] ?? '', 'اعلام شد');
            break;
        case 'admins':
            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }
            $kb=[ [ ['text'=>'افزودن ادمین','callback_data'=>'admin:adm_add'] ], [ ['text'=>'لیست ادمین ها','callback_data'=>'admin:adm_list'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId,$messageId,'مدیریت ادمین ها',['inline_keyboard'=>$kb]);
            break;
        case 'adm_add':
            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }
            setAdminState($chatId,'await_admin_ident',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده ادمین را ارسال کنید');
            break;
        case 'adm_list':
            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }
            $rows = db()->query("SELECT admin_telegram_id, is_owner FROM admin_users ORDER BY id ASC")->fetchAll();
            $kb=[]; foreach($rows as $r){ $label = ($r['is_owner']?'[Owner] ':'').'ID: '.$r['admin_telegram_id']; $kb[]=[ ['text'=>$label,'callback_data'=>'admin:adm_edit|id='.$r['admin_telegram_id']] ]; }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:admins'] ];
            editMessageText($chatId,$messageId,'لیست ادمین ها',['inline_keyboard'=>$kb]);
            break;
        case 'adm_edit':
            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }
            $aid=(int)$params['id'];
            renderAdminPermsEditor($chatId, $messageId, $aid);
            break;
        default:
            answerCallback($_POST['callback_query']['id'] ?? '', 'بخش ناشناخته', true);
    }
}

function renderAdminPermsEditor(int $chatId, int $messageId, int $adminTid): void {
    $row = db()->prepare("SELECT is_owner, permissions FROM admin_users WHERE admin_telegram_id=?");
    $row->execute([$adminTid]); $r=$row->fetch(); if(!$r){ editMessageText($chatId,$messageId,'ادمین پیدا نشد', backButton('admin:admins')); return; }
    if ((int)$r['is_owner']===1) { editMessageText($chatId,$messageId,'این اکانت Owner است.', backButton('admin:admins')); return; }
    $allPerms = ['support','army','missile','defense','statement','war','roles','assets','settings','wheel','users','bans','admins'];
    $cur = $r['permissions'] ? (json_decode($r['permissions'], true) ?: []) : [];
    $kb=[]; foreach($allPerms as $p){ $on = in_array($p,$cur,true); $kb[]=[ ['text'=>($on?'✅ ':'⬜️ ').$p, 'callback_data'=>'admin:adm_toggle|id='.$adminTid.'|perm='.$p] ]; }
    $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:adm_list'] ];
    editMessageText($chatId,$messageId,'دسترسی ها برای '.$adminTid,['inline_keyboard'=>$kb]);
}

// --------------------- ALLIANCE ---------------------

function renderAllianceHome(int $chatId, int $messageId, array $userRow): void {
    // Check membership
    $stmt = db()->prepare("SELECT a.id, a.name, a.leader_user_id FROM alliances a JOIN alliance_members m ON m.alliance_id=a.id JOIN users u ON u.id=m.user_id WHERE u.telegram_id=?");
    $stmt->execute([$chatId]); $a=$stmt->fetch();
    if (!$a) {
        $kb=[ [ ['text'=>'ساخت اتحاد جدید','callback_data'=>'alli:new'] ], [ ['text'=>'لیست اتحادها','callback_data'=>'alli:list|page=1'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:home'] ] ];
        editMessageText($chatId,$messageId,'بخش اتحاد', ['inline_keyboard'=>$kb]);
        return;
    }
    $isLeader = isAllianceLeader($chatId, (int)$a['id']);
    renderAllianceView($chatId, $messageId, (int)$a['id'], $isLeader, true);
}

function isAllianceLeader(int $tgId, int $allianceId): bool {
    $stmt = db()->prepare("SELECT 1 FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=? AND u.telegram_id=?");
    $stmt->execute([$allianceId,$tgId]); return (bool)$stmt->fetch();
}

function renderAllianceView(int $chatId, int $messageId, int $allianceId, bool $isLeader, bool $fromHome=false): void {
    $stmt = db()->prepare("SELECT a.*, u.telegram_id AS leader_tid, u.username AS leader_username, u.country AS leader_country FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=?");
    $stmt->execute([$allianceId]); $a=$stmt->fetch(); if(!$a){ editMessageText($chatId,$messageId,'اتحاد یافت نشد', backButton('nav:home')); return; }
    $members = db()->prepare("SELECT m.user_id, m.role, m.display_name, u.telegram_id, u.username, u.country FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE m.alliance_id=? ORDER BY m.role='leader' DESC, m.id ASC");
    $members->execute([$allianceId]); $ms=$members->fetchAll();
    $lines=[]; $lines[]='رهبر: '. e($a['leader_country']).' - '.($a['leader_username']?'@'.$a['leader_username']:$a['leader_tid']);
    $lines[]='اعضا:';
    // up to 4 members
    $count=0; foreach($ms as $m){ if($m['role']!=='leader'){ $count++; $disp = $m['display_name'] ?: $m['country']; $lines[]='- '.e($disp).' - '.($m['username']?'@'.$m['username']:$m['telegram_id']); }}
    for($i=$count; $i<3; $i++){ $lines[]='- خالی'; }
    $lines[]='شعار اتحاد: ' . ($a['slogan'] ? e($a['slogan']) : '—');
    $text = "اتحاد: ".e($a['name'])."\n".implode("\n", $lines);
    $kb=[];
    if ($isLeader) {
        $kb[] = [ ['text'=>'دعوت عضو','callback_data'=>'alli:invite|id='.$allianceId] , ['text'=>'ویرایش شعار','callback_data'=>'alli:editslogan|id='.$allianceId] ];
        $kb[] = [ ['text'=>'ویرایش نام اتحاد','callback_data'=>'alli:editname|id='.$allianceId], ['text'=>'ویرایش نام اعضا','callback_data'=>'alli:editmembers|id='.$allianceId] ];
        $kb[] = [ ['text'=>'حذف/انحلال اتحاد','callback_data'=>'alli:delete|id='.$allianceId] ];
    } else {
        $kb[] = [ ['text'=>'ترک اتحاد','callback_data'=>'alli:leave|id='.$allianceId] ];
    }
    $kb[] = [ ['text'=>$fromHome?'بازگشت':'بازگشت به منو', 'callback_data'=>$fromHome?'nav:alliance':'nav:home'] ];
    editMessageText($chatId,$messageId,$text,['inline_keyboard'=>$kb]);
}

function handleAllianceNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {
    switch ($route) {
        case 'new':
            setUserState($chatId,'await_alliance_name',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'نام اتحاد را ارسال کنید');
            break;
        case 'list':
            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);
            $total = db()->query("SELECT COUNT(*) c FROM alliances")->fetch()['c']??0;
            $stmt = db()->prepare("SELECT a.id, a.name, u.username, u.telegram_id, u.country FROM alliances a JOIN users u ON u.id=a.leader_user_id ORDER BY a.created_at DESC LIMIT ?,?");
            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
            $kbRows=[]; foreach($rows as $r){ $label = e($r['name']).' | رهبر: '.e($r['country']).' - '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kbRows[]=[ ['text'=>$label,'callback_data'=>'alli:view|id='.$r['id'].'|from=list|page='.$page] ]; }
            $hasMore = ($offset + count($rows)) < $total;
            $kb = array_merge($kbRows, paginationKeyboard('alli:list', $page, $hasMore, 'nav:alliance')['inline_keyboard']);
            editMessageText($chatId,$messageId,'لیست اتحادها',['inline_keyboard'=>$kb]);
            break;
        case 'view':
            $id=(int)$params['id']; $from=$params['from']??'list'; $page=(int)($params['page']??1);
            $stmt=db()->prepare("SELECT a.*, u.telegram_id AS leader_tid FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=?"); $stmt->execute([$id]); $a=$stmt->fetch(); if(!$a){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $isLeader = isAllianceLeader($chatId, $id);
            renderAllianceView($chatId, $messageId, $id, $isLeader, false);
            break;
        case 'invite':
            $id=(int)$params['id']; setUserState($chatId,'await_invite_ident',['alliance_id'=>$id]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');
            break;
        case 'editslogan':
            $id=(int)$params['id']; setUserState($chatId,'await_slogan',['alliance_id'=>$id]); answerCallback($_POST['callback_query']['id'] ?? '', 'شعار جدید را ارسال کنید');
            break;
        case 'editname':
            $id=(int)$params['id']; setUserState($chatId,'await_alliance_rename',['alliance_id'=>$id]); answerCallback($_POST['callback_query']['id'] ?? '', 'نام جدید اتحاد را ارسال کنید');
            break;
        case 'editmembers':
            $id=(int)$params['id'];
            $ms = db()->prepare("SELECT m.user_id, u.username, u.telegram_id, u.country FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE m.alliance_id=? AND m.role='member'");
            $ms->execute([$id]); $rows=$ms->fetchAll();
            $kb=[]; foreach($rows as $r){ $label = e($r['country']).' - '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kb[]=[ ['text'=>$label,'callback_data'=>'alli:editmember|aid='.$id.'|uid='.$r['user_id']] ]; }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'alli:view|id='.$id] ];
            editMessageText($chatId,$messageId,'ویرایش نام اعضا',['inline_keyboard'=>$kb]);
            break;
        case 'editmember':
            $aid=(int)$params['aid']; $uid=(int)$params['uid']; setUserState($chatId,'await_member_display',['alliance_id'=>$aid,'user_id'=>$uid]); answerCallback($_POST['callback_query']['id'] ?? '', 'نام نمایشی جدید عضو را ارسال کنید');
            break;
        case 'delete':
            $id=(int)$params['id']; disbandAlliance($id, $chatId, $messageId); break;
        case 'leave':
            $id=(int)$params['id']; leaveAlliance($chatId, $id, $messageId); break;
        default:
            answerCallback($_POST['callback_query']['id'] ?? '', 'ناشناخته', true);
    }
}

function disbandAlliance(int $allianceId, int $chatId, int $messageId): void {
    // only leader can disband. Validate
    if (!isAllianceLeader($chatId, $allianceId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط رهبر', true); return; }
    db()->prepare("DELETE FROM alliances WHERE id=?")->execute([$allianceId]);
    editMessageText($chatId,$messageId,'اتحاد منحل شد', backButton('nav:alliance'));
}

function leaveAlliance(int $tgId, int $allianceId, int $messageId): void {
    // if leader leaves => disband
    if (isAllianceLeader($tgId, $allianceId)) { disbandAlliance($allianceId, $tgId, $messageId); return; }
    $u = userByTelegramId($tgId); if(!$u){ return; }
    db()->prepare("DELETE FROM alliance_members WHERE alliance_id=? AND user_id=?")->execute([$allianceId, (int)$u['id']]);
    editMessageText($tgId,$messageId,'از اتحاد خارج شدید', backButton('nav:home'));
}

// --------------------- MESSAGE PROCESSING ---------------------

function processUserMessage(array $message): void {
    $from = $message['from'];
    $u = ensureUser($from);
    $chatId = (int)$u['telegram_id'];
    purgeOldSupportMessages();

    if ((int)$u['banned'] === 1) {
        sendMessage($chatId, 'شما از ربات بن هستید.');
        return;
    }

    if (isset($message['text']) && trim($message['text']) === '/start') {
        clearUserState($chatId);
        handleStart($u);
        return;
    }

    // Handle admin/user states first
    $adminPerms = getAdminPermissions($chatId);
    if ($adminPerms) {
        $st = getAdminState($chatId);
        if ($st) { handleAdminStateMessage($u, $message, $st); return; }
    }

    $st = getUserState($chatId);
    if ($st) { handleUserStateMessage($u, $message, $st); return; }

    // Default: show menu
    handleStart($u);
}

function handleAdminStateMessage(array $userRow, array $message, array $state): void {
    $chatId = (int)$userRow['telegram_id'];
    $key = $state['key']; $data = $state['data'];
    $text = $message['text'] ?? '';

    switch ($key) {
        case 'await_role_cost':
            $id = (int)$data['submission_id']; $page=(int)$data['page'];
            $cost = (int)preg_replace('/\D+/', '', (string)$text);
            if ($cost <= 0) { sendMessage($chatId, 'مقدار معتبر ارسال کنید (عدد)'); return; }
            $stmt = db()->prepare("UPDATE submissions SET status='cost_proposed', cost_amount=? WHERE id=?"); $stmt->execute([$cost,$id]);
            // Notify user with confirm buttons
            $r = db()->prepare("SELECT s.id, s.user_id, s.cost_amount, u.telegram_id FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $r->execute([$id]); $row=$r->fetch();
            if ($row) {
                $kb = [ [ ['text'=>'تایید','callback_data'=>'rolecost:accept|id='.$id], ['text'=>'رد','callback_data'=>'rolecost:reject|id='.$id] ] ];
                sendMessage((int)$row['telegram_id'], 'هزینه رول شما: ' . $cost . "\nآیا تایید می‌کنید؟", ['inline_keyboard'=>$kb]);
                sendMessage($chatId, 'هزینه درخواست شد.');
            }
            clearAdminState($chatId);
            break;
        case 'await_asset_text':
            $country = $data['country']; $content = $text ?: ($message['caption'] ?? '');
            $stmt = db()->prepare("INSERT INTO assets (country, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()"); $stmt->execute([$country, $content]);
            sendMessage($chatId, 'ذخیره شد برای کشور: ' . e($country));
            clearAdminState($chatId);
            break;
        case 'await_btn_rename':
            $key = $data['key']; $title = trim((string)$text);
            if ($title===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            db()->prepare("UPDATE button_settings SET title=? WHERE `key`=?")->execute([$title,$key]);
            sendMessage($chatId,'نام دکمه تغییر کرد.'); clearAdminState($chatId);
            break;
        case 'await_user_ident':
            $tgid = extractTelegramIdFromMessage($message);
            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر. مجدد ارسال کنید یا پیام کاربر را فوروارد کنید.'); return; }
            setAdminState($chatId,'await_user_country',['tgid'=>$tgid]);
            sendMessage($chatId,'نام کشور کاربر را ارسال کنید.');
            break;
        case 'await_user_country':
            $tgid = (int)$data['tgid']; $country = trim((string)$text);
            if ($country===''){ sendMessage($chatId,'نام کشور نامعتبر.'); return; }
            $u = ensureUser(['id'=>$tgid]);
            db()->prepare("UPDATE users SET is_registered=1, country=? WHERE telegram_id=?")->execute([$country,$tgid]);
            sendMessage($chatId,'کاربر ثبت شد.');
            sendMessage($tgid,'ثبت شما تکمیل شد.');
            clearAdminState($chatId);
            break;
        case 'await_ban_ident':
            $tgid = extractTelegramIdFromMessage($message);
            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر.'); return; }
            db()->prepare("UPDATE users SET banned=1 WHERE telegram_id=?")->execute([$tgid]);
            sendMessage($chatId,'کاربر بن شد: '.$tgid);
            clearAdminState($chatId);
            break;
        case 'await_unban_ident':
            $tgid = extractTelegramIdFromMessage($message);
            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر.'); return; }
            db()->prepare("UPDATE users SET banned=0 WHERE telegram_id=?")->execute([$tgid]);
            sendMessage($chatId,'بن کاربر حذف شد: '.$tgid);
            clearAdminState($chatId);
            break;
        case 'await_wheel_prize':
            $prize = trim((string)$text);
            if ($prize===''){ sendMessage($chatId,'نامعتبر'); return; }
            db()->prepare("INSERT INTO wheel_settings (id, current_prize) VALUES (1, ?) ON DUPLICATE KEY UPDATE current_prize=VALUES(current_prize)")->execute([$prize]);
            sendMessage($chatId,'جایزه ثبت شد.');
            clearAdminState($chatId);
            break;
        case 'await_admin_ident':
            $tgid = extractTelegramIdFromMessage($message);
            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر'); return; }
            if ($tgid === MAIN_ADMIN_ID) { sendMessage($chatId,'این اکانت Owner است.'); clearAdminState($chatId); return; }
            db()->prepare("INSERT IGNORE INTO admin_users (admin_telegram_id, is_owner, permissions) VALUES (?, 0, ?)")->execute([$tgid, json_encode([])]);
            setAdminState($chatId,'await_admin_perms',['tgid'=>$tgid]);
            // render perms editor
            $fakeMsgId = $message['message_id'] ?? 0;
            renderAdminPermsEditor($chatId, $fakeMsgId, $tgid);
            break;
        case 'await_admin_perms':
            // handled via buttons (adm_toggle)
            break;
        default:
            sendMessage($chatId,'حالت ناشناخته'); clearAdminState($chatId);
    }
}

function extractTelegramIdFromMessage(array $message): ?int {
    if (!empty($message['text']) && preg_match('/\d{5,}/', $message['text'], $m)) {
        return (int)$m[0];
    }
    if (!empty($message['forward_from']['id'])) {
        return (int)$message['forward_from']['id'];
    }
    if (!empty($message['forward_sender_name'])) {
        // cannot resolve id from hidden forwards
        return null;
    }
    return null;
}

function handleUserStateMessage(array $userRow, array $message, array $state): void {
    $chatId = (int)$userRow['telegram_id'];
    $key = $state['key']; $data=$state['data'];
    $text = $message['text'] ?? null;
    $photo = null; $caption = $message['caption'] ?? null;
    if (!empty($message['photo'])) {
        $photos = $message['photo'];
        $largest = end($photos);
        $photo = $largest['file_id'] ?? null;
    }

    switch ($key) {
        case 'await_support':
            if (!$text && !$photo) { sendMessage($chatId,'فقط متن یا عکس بفرستید.'); return; }
            // Save
            $u = userByTelegramId($chatId);
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO support_messages (user_id, text, photo_file_id) VALUES (?, ?, ?)");
            $stmt->execute([(int)$u['id'], $text ?: $caption, $photo]);
            $supportId = (int)$pdo->lastInsertId();
            sendMessage($chatId, 'پیام شما ثبت شد.');
            // immediate detailed notify to admins
            notifyNewSupportMessage($supportId);
            clearUserState($chatId);
            break;
        case 'await_submission':
            $type = $data['type'] ?? 'army';
            if (!$text && !$photo && !$caption) { sendMessage($chatId,'متن یا عکس ارسال کنید.'); return; }
            $u = userByTelegramId($chatId);
            db()->prepare("INSERT INTO submissions (user_id, type, text, photo_file_id) VALUES (?, ?, ?, ?)")->execute([(int)$u['id'], $type, $text ?: $caption, $photo]);
            sendMessage($chatId,'ارسال شما ثبت شد.');
            notifySectionAdmins($type, 'پیام جدید در بخش '.$type);
            clearUserState($chatId);
            break;
        case 'await_war_format':
            // Expect text with attacker/defender names; optionally photo
            $content = $text ?: $caption;
            if (!$content) { sendMessage($chatId,'ابتدا متن با فرمت موردنظر را ارسال کنید.'); return; }
            $att = null; $def = null;
            if (preg_match('/نام\s*کشور\s*حمله\s*کننده\s*:\s*(.+)/u', $content, $m1)) { $att = trim($m1[1]); }
            if (preg_match('/نام\s*کشور\s*دفاع\s*کننده\s*:\s*(.+)/u', $content, $m2)) { $def = trim($m2[1]); }
            if (!$att || !$def) { sendMessage($chatId,'فرمت نامعتبر. هر دو نام کشور لازم است.'); return; }
            $u = userByTelegramId($chatId);
            db()->prepare("INSERT INTO submissions (user_id, type, text, photo_file_id, attacker_country, defender_country) VALUES (?, 'war', ?, ?, ?, ?)")->execute([(int)$u['id'], $content, $photo, $att, $def]);
            sendMessage($chatId,'اعلام جنگ ثبت شد.');
            notifySectionAdmins('war', 'پیام جدید در بخش اعلام جنگ');
            clearUserState($chatId);
            break;
        case 'await_role_text':
            if (!$text) { sendMessage($chatId,'فقط متن مجاز است.'); return; }
            $u = userByTelegramId($chatId);
            db()->prepare("INSERT INTO submissions (user_id, type, text) VALUES (?, 'role', ?)")->execute([(int)$u['id'], $text]);
            sendMessage($chatId,'رول شما ثبت شد و در انتظار بررسی است.');
            notifySectionAdmins('roles', 'رول جدید ارسال شد');
            clearUserState($chatId);
            break;
        case 'await_alliance_name':
            $name = trim((string)$text);
            if ($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            $u = userByTelegramId($chatId);
            // Check not already in an alliance
            $x = db()->prepare("SELECT 1 FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE u.telegram_id=?"); $x->execute([$chatId]); if($x->fetch()){ sendMessage($chatId,'شما در اتحاد هستید.'); clearUserState($chatId); return; }
            db()->beginTransaction();
            try {
                db()->prepare("INSERT INTO alliances (name, leader_user_id) VALUES (?, ?)")->execute([$name, (int)$u['id']]);
                $aid = (int)db()->lastInsertId();
                db()->prepare("INSERT INTO alliance_members (alliance_id, user_id, role) VALUES (?, ?, 'leader')")->execute([$aid, (int)$u['id']]);
                db()->commit();
                sendMessage($chatId,'اتحاد ایجاد شد.');
            } catch (Exception $e) { db()->rollBack(); sendMessage($chatId,'خطا: '.$e->getMessage()); }
            clearUserState($chatId);
            break;
        case 'await_invite_ident':
            $aid=(int)$data['alliance_id']; $tgid = extractTelegramIdFromMessage($message); if(!$tgid){ sendMessage($chatId,'آیدی نامعتبر'); return; }
            $inviter = userByTelegramId($chatId); $invitee = ensureUser(['id'=>$tgid]);
            // Capacity (max 4 total: 1 leader + 3 members)
            $cnt = db()->prepare("SELECT COUNT(*) c FROM alliance_members WHERE alliance_id=?"); $cnt->execute([$aid]); $c=(int)($cnt->fetch()['c']??0);
            if ($c >= 4) { sendMessage($chatId,'ظرفیت اتحاد تکمیل است.'); clearUserState($chatId); return; }
            db()->prepare("INSERT INTO alliance_invites (alliance_id, invitee_user_id, inviter_user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status='pending'")->execute([$aid, (int)$invitee['id'], (int)$inviter['id']]);
            $kb=[ [ ['text'=>'بله','callback_data'=>'alli_inv:accept|aid='.$aid], ['text'=>'خیر','callback_data'=>'alli_inv:reject|aid='.$aid] ] ];
            sendMessage((int)$invitee['telegram_id'], 'شما به یک اتحاد دعوت شدید. آیا می‌پذیرید؟', ['inline_keyboard'=>$kb]);
            sendMessage($chatId,'دعوت ارسال شد.');
            clearUserState($chatId);
            break;
        case 'await_slogan':
            $aid=(int)$data['alliance_id']; $slogan = trim((string)($text ?: ''));
            db()->prepare("UPDATE alliances SET slogan=? WHERE id=?")->execute([$slogan, $aid]);
            sendMessage($chatId,'شعار به‌روزرسانی شد.');
            clearUserState($chatId);
            break;
        case 'await_alliance_rename':
            $aid=(int)$data['alliance_id']; $name=trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            db()->prepare("UPDATE alliances SET name=? WHERE id=?")->execute([$name,$aid]); sendMessage($chatId,'نام اتحاد به‌روزرسانی شد.'); clearUserState($chatId);
            break;
        case 'await_member_display':
            $aid=(int)$data['alliance_id']; $uid=(int)$data['user_id']; $disp=trim((string)$text);
            db()->prepare("UPDATE alliance_members SET display_name=? WHERE alliance_id=? AND user_id=?")->execute([$disp,$aid,$uid]);
            sendMessage($chatId,'نام نمایشی عضو به‌روزرسانی شد.'); clearUserState($chatId);
            break;
        default:
            sendMessage($chatId,'حالت ناشناخته'); clearUserState($chatId);
    }
}

// --------------------- CALLBACK PROCESSING ---------------------

function processCallback(array $callback): void {
    $from = $callback['from']; $u = ensureUser($from); $chatId=(int)$u['telegram_id'];
    $message = $callback['message'] ?? null; $messageId = $message['message_id'] ?? 0;
    $data = $callback['data'] ?? '';

    list($action, $params) = cbParse($data);

    if (strpos($action, 'nav:') === 0) {
        $route = substr($action, 4);
        handleNav($chatId, $messageId, $route, $params, $u);
        return;
    }
    if (strpos($action, 'admin:') === 0) {
        $route = substr($action, 6);
        if (!getAdminPermissions($chatId)) { answerCallback($callback['id'], 'دسترسی ندارید', true); return; }
        if (strpos($route, 'adm_toggle') === 0) {
            // toggle a permission
            parse_str(str_replace('|','&',$data)); // $id $perm
            $aid = (int)($params['id'] ?? 0); $perm = $params['perm'] ?? '';
            $row = db()->prepare("SELECT permissions FROM admin_users WHERE admin_telegram_id=?"); $row->execute([$aid]); $r=$row->fetch(); if($r){ $cur = $r['permissions']? (json_decode($r['permissions'],true)?:[]):[]; if(in_array($perm,$cur,true)){ $cur=array_values(array_filter($cur,function($x)use($perm){return $x!==$perm;})); } else { $cur[]=$perm; } db()->prepare("UPDATE admin_users SET permissions=? WHERE admin_telegram_id=?")->execute([json_encode($cur,JSON_UNESCAPED_UNICODE),$aid]); }
            answerCallback($callback['id'],'به‌روزرسانی شد');
            renderAdminPermsEditor($chatId, $messageId, $aid);
            return;
        }
        handleAdminNav($chatId, $messageId, $route, $params, $u);
        return;
    }
    if (strpos($action, 'rolecost:') === 0) {
        $route = substr($action, 9); $id=(int)($params['id']??0);
        $stmt = db()->prepare("SELECT s.*, u.telegram_id FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($callback['id'],'یافت نشد',true); return; }
        if ($route==='accept') {
            db()->prepare("UPDATE submissions SET status='user_confirmed' WHERE id=?")->execute([$id]);
            // notify admins with roles perm
            notifySectionAdmins('roles', 'کاربر هزینه رول را تایید کرد: ID '.$r['telegram_id']);
            sendMessage((int)$r['telegram_id'],'تایید ثبت شد.');
            answerCallback($callback['id'],'تایید شد');
        } else {
            db()->prepare("UPDATE submissions SET status='user_declined' WHERE id=?")->execute([$id]);
            notifySectionAdmins('roles', 'کاربر هزینه رول را رد کرد: ID '.$r['telegram_id']);
            sendMessage((int)$r['telegram_id'],'رد ثبت شد.');
            // remove from list per requirement
            db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);
            answerCallback($callback['id'],'رد شد');
        }
        return;
    }
    if (strpos($action, 'alli_inv:') === 0) {
        $route = substr($action, 9); $aid=(int)($params['aid']??0);
        $invitee = userByTelegramId($chatId); if(!$invitee){ answerCallback($callback['id'],'خطا',true); return; }
        $inv = db()->prepare("SELECT * FROM alliance_invites WHERE alliance_id=? AND invitee_user_id=? AND status='pending'"); $inv->execute([$aid,(int)$invitee['id']]); $row=$inv->fetch(); if(!$row){ answerCallback($callback['id'],'دعوتی یافت نشد',true); return; }
        if ($route==='accept') {
            // capacity check
            $cnt = db()->prepare("SELECT COUNT(*) c FROM alliance_members WHERE alliance_id=?"); $cnt->execute([$aid]); $c=(int)($cnt->fetch()['c']??0);
            if ($c >= 4) { answerCallback($callback['id'],'اتحاد تکمیل است', true); return; }
            db()->beginTransaction();
            try {
                db()->prepare("INSERT IGNORE INTO alliance_members (alliance_id, user_id, role) VALUES (?, ?, 'member')")->execute([$aid, (int)$invitee['id']]);
                db()->prepare("UPDATE alliance_invites SET status='accepted' WHERE id=?")->execute([$row['id']]);
                db()->commit();
                answerCallback($callback['id'],'به اتحاد پیوستید');
            } catch (Exception $e) { db()->rollBack(); answerCallback($callback['id'],'خطا',true); }
        } else {
            db()->prepare("UPDATE alliance_invites SET status='declined' WHERE id=?")->execute([$row['id']]);
            answerCallback($callback['id'],'رد شد');
        }
        return;
    }

    // Fallback
    answerCallback($callback['id'], 'دستور ناشناخته');
}

// --------------------- ENTRYPOINT ---------------------

// Optional webhook secret check
if (WEBHOOK_SECRET !== '' && (!isset($_GET['token']) || $_GET['token'] !== WEBHOOK_SECRET)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) { echo 'OK'; exit; }

if (isset($update['message'])) {
    processUserMessage($update['message']);
} elseif (isset($update['callback_query'])) {
    processCallback($update['callback_query']);
}

echo 'OK';
?>