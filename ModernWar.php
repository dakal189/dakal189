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

const BOT_TOKEN = '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ';

const API_URL   = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';



// Main (owner) admin numeric ID

const MAIN_ADMIN_ID = 5641303137; // Replace with your Telegram numeric ID



// Channel ID for posting statements/war announcements and wheel winners (e.g., -1001234567890)

const CHANNEL_ID = -1002647850307; // Replace with your channel ID



// Database credentials

const DB_HOST = 'localhost';

const DB_NAME = 'dakallli_Test2';

const DB_USER = 'dakallli_Test2';

const DB_PASS = 'hosyarww123';

const DB_CHARSET = 'utf8mb4';



// Debugging

const DEBUG = true;



// Security: optional secret path token for webhook URL validation (set to '' to disable)

const WEBHOOK_SECRET = '';



// Misc

date_default_timezone_set('Asia/Tehran');



// --------------------- INITIALIZATION ---------------------



ini_set('log_errors', 1);

ini_set('error_log', sys_get_temp_dir() . '/bot_php_error.log');

if (DEBUG) {

    ini_set('display_errors', 1);

    error_reporting(E_ALL);

} else {

    ini_set('display_errors', 0);

    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

}



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

        money BIGINT NOT NULL DEFAULT 0,

        daily_profit BIGINT NOT NULL DEFAULT 0,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Optional columns for assets/money (idempotent)

    try { $pdo->exec("ALTER TABLE users ADD COLUMN assets_text TEXT NULL"); } catch (Exception $e) {}

    // Per-user toggle to enable/disable daily profit accrual and profit-yielding purchases

    try { $pdo->exec("ALTER TABLE users ADD COLUMN daily_profit_enabled TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}

    // M Coin system for users (admin configurable token)
    try { $pdo->exec("ALTER TABLE users ADD COLUMN m_coins BIGINT NOT NULL DEFAULT 0"); } catch (Exception $e) {}



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



    // Settings (key-value)

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (

        `key` VARCHAR(64) PRIMARY KEY,

        `value` TEXT NULL

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // Country flags

    $pdo->exec("CREATE TABLE IF NOT EXISTS country_flags (

        country VARCHAR(64) PRIMARY KEY,

        photo_file_id VARCHAR(256) NOT NULL

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



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



    // Support replies

    $pdo->exec("CREATE TABLE IF NOT EXISTS support_replies (

        id INT AUTO_INCREMENT PRIMARY KEY,

        support_id INT NOT NULL,

        admin_id BIGINT NOT NULL,

        text TEXT NULL,

        photo_file_id VARCHAR(256) NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_supprep_support FOREIGN KEY (support_id) REFERENCES support_messages(id) ON DELETE CASCADE

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



    // Approved roles

    $pdo->exec("CREATE TABLE IF NOT EXISTS approved_roles (

        id INT AUTO_INCREMENT PRIMARY KEY,

        submission_id INT NOT NULL,

        user_id INT NOT NULL,

        text TEXT NULL,

        cost_amount INT NULL,

        username VARCHAR(64) NULL,

        telegram_id BIGINT NULL,

        country VARCHAR(64) NULL,

        approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

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

        enabled TINYINT(1) NOT NULL DEFAULT 1,

        days VARCHAR(32) NULL,

        time_start CHAR(5) NULL,

        time_end CHAR(5) NULL

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Backfill columns for older deployments (ignore errors if already exists)

    try { $pdo->exec("ALTER TABLE button_settings ADD COLUMN days VARCHAR(32) NULL"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE button_settings ADD COLUMN time_start CHAR(5) NULL"); } catch (Exception $e) {}

        try { $pdo->exec("ALTER TABLE button_settings ADD COLUMN time_end CHAR(5) NULL"); } catch (Exception $e) {}

 

     // Discount codes

     $pdo->exec("CREATE TABLE IF NOT EXISTS discount_codes (

         id INT AUTO_INCREMENT PRIMARY KEY,

         code VARCHAR(64) UNIQUE,

         percent TINYINT NOT NULL,

         max_uses INT NOT NULL DEFAULT 0,

         used_count INT NOT NULL DEFAULT 0,

         per_user_limit INT NOT NULL DEFAULT 1,

         expires_at DATETIME NULL,

        disabled TINYINT(1) NOT NULL DEFAULT 0,

        shop_type ENUM('regular','vip','both') NOT NULL DEFAULT 'regular',

        created_by BIGINT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

     $pdo->exec("CREATE TABLE IF NOT EXISTS discount_usages (

         id INT AUTO_INCREMENT PRIMARY KEY,

         code_id INT NOT NULL,

         user_id INT NOT NULL,

         used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

         FOREIGN KEY (code_id) REFERENCES discount_codes(id) ON DELETE CASCADE

     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

     $pdo->exec("CREATE TABLE IF NOT EXISTS discount_code_blocked_countries (

         id INT AUTO_INCREMENT PRIMARY KEY,

         code_id INT NOT NULL,

         country VARCHAR(128) NOT NULL,

         UNIQUE KEY uq_code_country (code_id, country),

         FOREIGN KEY (code_id) REFERENCES discount_codes(id) ON DELETE CASCADE

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

        ['shop','فروشگاه'],

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

        banner_file_id VARCHAR(256) NULL,

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



    // Shop categories

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_categories (

        id INT AUTO_INCREMENT PRIMARY KEY,

        name VARCHAR(128) NOT NULL,

        sort_order INT NOT NULL DEFAULT 0,

        UNIQUE KEY uq_cat_name (name)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // Shop items

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_items (

        id INT AUTO_INCREMENT PRIMARY KEY,

        category_id INT NOT NULL,

        name VARCHAR(128) NOT NULL,

        unit_price BIGINT NOT NULL DEFAULT 0,

        pack_size INT NOT NULL DEFAULT 1,

        per_user_limit INT NOT NULL DEFAULT 0,

        daily_profit_per_pack INT NOT NULL DEFAULT 0,

        enabled TINYINT(1) NOT NULL DEFAULT 1,

        UNIQUE KEY uq_item_cat_name (category_id, name),

        CONSTRAINT fk_item_cat FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // User carts (simple: keyed by user_id)

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_carts (

        user_id INT PRIMARY KEY,

        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    $pdo->exec("CREATE TABLE IF NOT EXISTS user_cart_items (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        item_id INT NOT NULL,

        quantity INT NOT NULL DEFAULT 0,

        UNIQUE KEY uq_cart_item (user_id, item_id),

        CONSTRAINT fk_uci_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

        CONSTRAINT fk_uci_item FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // User owned items (inventory)

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_items (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        item_id INT NOT NULL,

        quantity BIGINT NOT NULL DEFAULT 0,

        UNIQUE KEY uq_user_item (user_id, item_id),

        CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

        CONSTRAINT fk_ui_item FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // Track packs purchased per user per item to enforce per_user_limit

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_item_purchases (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        item_id INT NOT NULL,

        packs_bought BIGINT NOT NULL DEFAULT 0,

        UNIQUE KEY uq_user_item_purchase (user_id, item_id),

        CONSTRAINT fk_uip_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

        CONSTRAINT fk_uip_item FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // Factories

    $pdo->exec("CREATE TABLE IF NOT EXISTS factories (

        id INT AUTO_INCREMENT PRIMARY KEY,

        name VARCHAR(128) NOT NULL,

        price_l1 BIGINT NOT NULL DEFAULT 0,

        price_l2 BIGINT NOT NULL DEFAULT 0,

        UNIQUE KEY uq_factory_name (name)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // VIP Shop tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS vip_shop_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL,
        type ENUM('factory','item') NOT NULL DEFAULT 'item',
        sort_order INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_vip_cat_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vip_shop_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(128) NOT NULL,
        mcoin_price BIGINT NOT NULL DEFAULT 0,
        pack_size INT NOT NULL DEFAULT 1,
        per_user_limit INT NOT NULL DEFAULT 0,
        daily_profit_per_pack INT NOT NULL DEFAULT 0,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_vip_item_cat_name (category_id, name),
        CONSTRAINT fk_vip_item_cat FOREIGN KEY (category_id) REFERENCES vip_shop_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vip_user_carts (
        user_id INT PRIMARY KEY,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_vip_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vip_user_cart_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_vip_cart_item (user_id, item_id),
        CONSTRAINT fk_vip_uci_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vip_uci_item FOREIGN KEY (item_id) REFERENCES vip_shop_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vip_user_item_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        packs_bought BIGINT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_vip_user_item_purchase (user_id, item_id),
        CONSTRAINT fk_vip_uip_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vip_uip_item FOREIGN KEY (item_id) REFERENCES vip_shop_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Custom Factory Request tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_factory_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        factory_name VARCHAR(128) NOT NULL,
        product_name VARCHAR(128) NOT NULL,
        daily_output INT NOT NULL DEFAULT 1,
        status ENUM('pending','approved','rejected','cost_set','accepted','declined') NOT NULL DEFAULT 'pending',
        admin_cost_money BIGINT NOT NULL DEFAULT 0,
        admin_cost_mcoin BIGINT NOT NULL DEFAULT 0,
        admin_message TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cfr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_custom_factories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_id INT NOT NULL,
        factory_name VARCHAR(128) NOT NULL,
        product_name VARCHAR(128) NOT NULL,
        daily_output INT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ucf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ucf_request FOREIGN KEY (request_id) REFERENCES custom_factory_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_factory_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        factory_id INT NOT NULL,
        product_name VARCHAR(128) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        last_claim_date DATE,
        CONSTRAINT fk_ufp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ufp_factory FOREIGN KEY (factory_id) REFERENCES user_custom_factories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Trade system tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_trades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        trade_type ENUM('money','item','both') NOT NULL,
        money_amount BIGINT NOT NULL DEFAULT 0,
        item_id INT,
        item_quantity INT NOT NULL DEFAULT 0,
        status ENUM('pending','accepted','rejected','admin_approved','admin_rejected') NOT NULL DEFAULT 'pending',
        admin_id INT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pt_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_pt_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_pt_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_pt_item FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS trade_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trade_id INT NOT NULL,
        admin_id INT NOT NULL,
        action ENUM('approved','rejected') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_tl_trade FOREIGN KEY (trade_id) REFERENCES player_trades(id) ON DELETE CASCADE,
        CONSTRAINT fk_tl_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_products (

        id INT AUTO_INCREMENT PRIMARY KEY,

        factory_id INT NOT NULL,

        item_id INT NOT NULL,

        qty_l1 INT NOT NULL DEFAULT 0,

        qty_l2 INT NOT NULL DEFAULT 0,

        CONSTRAINT fk_fp_factory FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,

        CONSTRAINT fk_fp_item FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    $pdo->exec("CREATE TABLE IF NOT EXISTS user_factories (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        factory_id INT NOT NULL,

        level TINYINT NOT NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        UNIQUE KEY uq_user_factory (user_id, factory_id),

        CONSTRAINT fk_uf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

        CONSTRAINT fk_uf_factory FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");



    // Daily production grants

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_factory_grants (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_factory_id INT NOT NULL,

        for_date DATE NOT NULL,

        granted TINYINT(1) NOT NULL DEFAULT 0,

        chosen_item_id INT NULL,

        UNIQUE KEY uq_ufd (user_factory_id, for_date),

        CONSTRAINT fk_ufg_uf FOREIGN KEY (user_factory_id) REFERENCES user_factories(id) ON DELETE CASCADE,

        CONSTRAINT fk_ufg_item FOREIGN KEY (chosen_item_id) REFERENCES shop_items(id) ON DELETE SET NULL

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



function rebuildDatabase(bool $dropAll = false): void {

    $pdo = db();

    if ($dropAll) {

        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $tables = ['support_replies','support_messages','admin_states','user_states','alliance_invites','alliance_members','alliances','wheel_settings','button_settings','assets','submissions','country_flags','admin_users','users','settings'];

        foreach ($tables as $t) { try { $pdo->exec("DROP TABLE IF EXISTS `{$t}`"); } catch (Exception $e) {} }

        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    }

    bootstrapDatabase($pdo);

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



function apiRequestMultipart(string $method, array $params = []) {

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



function deleteMessage($chatId, $messageId) {

    return apiRequest('deleteMessage', [ 'chat_id' => $chatId, 'message_id' => $messageId ]);

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

    $res = apiRequest('editMessageText', $params);

    if (!$res || !($res['ok'] ?? false)) {

        // fallback to caption edit (for photo messages)

        $cap = [

            'chat_id' => $chatId,

            'message_id' => $messageId,

            'caption' => $text,

            'parse_mode' => $parseMode,

        ];

        if ($replyMarkup) $cap['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);

        return apiRequest('editMessageCaption', $cap);

    }

    return $res;

}



function editMessageCaption($chatId, $messageId, $caption, $replyMarkup = null, $parseMode = 'HTML') {

    $params = [

        'chat_id' => $chatId,

        'message_id' => $messageId,

        'caption' => $caption,

        'parse_mode' => $parseMode,

    ];

    if ($replyMarkup) $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);

    return apiRequest('editMessageCaption', $params);

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



function sendPhotoFile($chatId, $filePath, $caption = '', $replyMarkup = null, $parseMode = 'HTML') {

    $params = [

        'chat_id' => $chatId,

        'photo' => new CURLFile($filePath),

        'caption' => $caption,

        'parse_mode' => $parseMode,

    ];

    if ($replyMarkup) $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);

    return apiRequestMultipart('sendPhoto', $params);

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



function buildWarCaption(array $submission, string $attCountry, string $defCountry): string {

    $epics = [

        'کشور '.e($attCountry).' به کشور '.e($defCountry).' یورش برد! شعله‌های جنگ زبانه کشید...',

        'آتش جنگ میان '.e($attCountry).' و '.e($defCountry).' برافروخته شد! آسمان‌ها لرزید...',

        'ناقوس نبرد به صدا درآمد؛ '.e($attCountry).' در برابر '.e($defCountry).' ایستاد!',

        e($attCountry).' حمله را آغاز کرد و '.e($defCountry).' دفاع می‌کند! سرنوشت رقم می‌خورد...',

        'زمین از قدم‌های سربازان '.e($attCountry).' تا '.e($defCountry).' می‌لرزد!',

        'نبرد بزرگ میان '.e($attCountry).' و '.e($defCountry).' شروع شد!',

        'مرزها به لرزه افتاد؛ '.e($attCountry).' بر فراز '.e($defCountry).' پیشروی می‌کند.',

        'باد جنگ وزیدن گرفت؛ '.e($attCountry).' در برابر '.e($defCountry).' قد علم کرد.',

        'شمشیرها از غلاف بیرون آمد؛ '.e($attCountry).' علیه '.e($defCountry).'.',

        'پیکان‌های نبرد رها شدند؛ '.e($attCountry).' و '.e($defCountry).' در میدان!'

    ];

    $headline = $epics[array_rand($epics)];

    return $headline . "\n\n" . ($submission['text'] ? e($submission['text']) : '');

}



function sendWarWithMode(int $submissionId, int $attTid, int $defTid, string $mode='auto'): bool {

    $stmt = db()->prepare("SELECT * FROM submissions WHERE id=? AND type='war'");

    $stmt->execute([$submissionId]); $s=$stmt->fetch(); if(!$s) return false;

    $att = ensureUser(['id'=>$attTid]); $def = ensureUser(['id'=>$defTid]);

    $attCountry = $att['country'] ?: $s['attacker_country'] ?: '—';

    $defCountry = $def['country'] ?: $s['defender_country'] ?: '—';

    $caption = buildWarCaption($s, $attCountry, $defCountry);

    $attFlag = null; $defFlag = null;

    $f1 = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?"); $f1->execute([$attCountry]); $r1=$f1->fetch(); if($r1) $attFlag=$r1['photo_file_id'];

    $f2 = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?"); $f2->execute([$defCountry]); $r2=$f2->fetch(); if($r2) $defFlag=$r2['photo_file_id'];



    if ($mode === 'text') { $r=sendToChannel($caption); return $r && ($r['ok']??false); }

    if ($mode === 'att') { if ($attFlag) { $r=sendPhotoToChannel($attFlag,$caption); return $r && ($r['ok']??false);} $r=sendToChannel($caption); return $r && ($r['ok']??false);} 

    if ($mode === 'def') { if ($defFlag) { $r=sendPhotoToChannel($defFlag,$caption); return $r && ($r['ok']??false);} $r=sendToChannel($caption); return $r && ($r['ok']??false);} 

    if ($attFlag && $defFlag) { $r1=sendPhotoToChannel($attFlag,$caption); $r2=sendPhotoToChannel($defFlag,''); return ($r1 && ($r1['ok']??false)) || ($r2 && ($r2['ok']??false)); }

    if ($attFlag || $defFlag) { $fid=$attFlag?:$defFlag; $r=sendPhotoToChannel($fid,$caption); return $r && ($r['ok']??false);} 

    $r=sendToChannel($caption); return $r && ($r['ok']??false);

}



function isOwner(int $telegramId): bool {

    return $telegramId === MAIN_ADMIN_ID;

}



function getSetting(string $key, $default = null) {

    $stmt = db()->prepare("SELECT `value` FROM settings WHERE `key`=?");

    $stmt->execute([$key]);

    $row = $stmt->fetch();

    if (!$row) return $default;

    return $row['value'];

}



function setSetting(string $key, $value): void {

    $stmt = db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");

    $stmt->execute([$key, $value]);

}



function isMaintenanceEnabled(): bool {

    return getSetting('maintenance','0') === '1';

}



function maintenanceMessage(): string {

    return getSetting('maintenance_message','ربات در حالت نگهداری است. لطفاً بعداً مراجعه کنید.');

}



// Fallback guards

if (!function_exists('clearHeaderPhoto')) {

    function clearHeaderPhoto(int $chatId, ?int $excludeMessageId = null): void {}

}

if (!function_exists('setHeaderPhoto')) {

    function setHeaderPhoto(int $chatId, int $messageId): void {}

}



function clearGuideMessage(int $chatId): void {

    try {

        $mid = getSetting('guide_msg_'.$chatId, '');

        if ($mid !== '') {

            @apiRequest('deleteMessage', ['chat_id'=>$chatId, 'message_id'=>(int)$mid]);

            setSetting('guide_msg_'.$chatId, '');

        }

    } catch (Throwable $e) {

        // ignore

    }

}



function sendGuide(int $chatId, string $text): void {

    try {

        clearGuideMessage($chatId);

        $res = sendMessage($chatId, $text);

        if ($res && ($res['ok'] ?? false)) {

            $mid = $res['result']['message_id'] ?? null;

            if ($mid) setSetting('guide_msg_'.$chatId, (string)$mid);

        }

    } catch (Throwable $e) {

        // ignore

    }

}



function clearHeaderPhoto(int $chatId, ?int $excludeMessageId = null): void {

    try {

        $mid = getSetting('header_msg_'.$chatId, '');

        if ($mid !== '' && (int)$mid !== (int)$excludeMessageId) {

            @apiRequest('deleteMessage', ['chat_id'=>$chatId, 'message_id'=>(int)$mid]);

            setSetting('header_msg_'.$chatId, '');

        }

    } catch (Throwable $e) {}

}



function setHeaderPhoto(int $chatId, int $messageId): void {

    setSetting('header_msg_'.$chatId, (string)$messageId);

}



function widenKeyboard(array $kb): array {

    if (!isset($kb['inline_keyboard'])) return $kb;

    $buttons = [];

    foreach ($kb['inline_keyboard'] as $row) {

        foreach ($row as $btn) { $buttons[] = $btn; }

    }

    $paired = [];

    for ($i=0; $i<count($buttons); $i+=2) {

        if (isset($buttons[$i+1])) $paired[] = [ $buttons[$i], $buttons[$i+1] ];

        else $paired[] = [ $buttons[$i] ];

    }

    return ['inline_keyboard' => $paired];

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

    $stmt = db()->prepare("SELECT enabled, days, time_start, time_end FROM button_settings WHERE `key` = ?");

    $stmt->execute([$key]);

    $row = $stmt->fetch();

    if (!$row) return true;

    if ((int)$row['enabled'] !== 1) return false;

    // Check schedule if defined

    $days = $row['days'] ?? null; $t1 = $row['time_start'] ?? null; $t2 = $row['time_end'] ?? null;

    // Day check

    if ($days && strtolower($days) !== 'all') {

        $map = ['su'=>0,'mo'=>1,'tu'=>2,'we'=>3,'th'=>4,'fr'=>5,'sa'=>6];

        $todayIdx = (int)gmdate('w'); // 0=Sun ... 6=Sat in GMT

        // Convert to Tehran local

        $tz = new DateTimeZone('Asia/Tehran'); $now = new DateTime('now',$tz); $todayIdx = (int)$now->format('w');

        $allowed = array_map('trim', explode(',', strtolower($days)));

        $allowedIdx = [];

        foreach ($allowed as $d) { if (isset($map[$d])) $allowedIdx[] = $map[$d]; }

        if ($allowedIdx && !in_array($todayIdx, $allowedIdx, true)) return false;

    }

    // Time range check (Tehran time). 00:00-00:00 means always

    if ($t1 && $t2 && !($t1==='00:00' && $t2==='00:00')) {

        $tz = new DateTimeZone('Asia/Tehran'); $now = new DateTime('now',$tz); $cur = (int)$now->format('H')*60 + (int)$now->format('i');

        list($h1,$m1) = explode(':',$t1); $s = (int)$h1*60 + (int)$m1;

        list($h2,$m2) = explode(':',$t2); $e = (int)$h2*60 + (int)$m2;

        if ($s <= $e) { // same-day window

            if ($cur < $s || $cur > $e) return false;

        } else { // overnight (e.g., 22:00-06:00)

            if (!($cur >= $s || $cur <= $e)) return false;

        }

    }

    return true;

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

        if (isButtonEnabled('shop')) $line[] = $btn('shop', 'nav:shop');

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

    $hdr = 'یک پیام پشتیبانی تازه دارید' . "\n" . usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: <code>" . (int)$r['telegram_id'] . "</code>\nزمان: " . iranDateTime($r['created_at']);

    $body = $hdr . "\n\n" . ($r['text'] ? e($r['text']) : '');

    $kb = [ [ ['text'=>'مشاهده در پنل','callback_data'=>'admin:support_view|id='.$supportId.'|page=1'] ] ];

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

    $ts = strtotime($datetime);

    return jalaliDate('Y/m/d H:i', $ts);

}



function jalaliDate($format, $timestamp=null, $timezone='Asia/Tehran') {

    if ($timestamp === null) $timestamp = time();

    $dt = new DateTime('@'.$timestamp);

    $dt->setTimezone(new DateTimeZone($timezone));

    $gy = (int)$dt->format('Y');

    $gm = (int)$dt->format('n');

    $gd = (int)$dt->format('j');

    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);

    $replacements = [

        'Y' => sprintf('%04d', $jy),

        'y' => substr(sprintf('%04d', $jy),2,2),

        'm' => sprintf('%02d', $jm),

        'n' => $jm,

        'd' => sprintf('%02d', $jd),

        'j' => $jd,

        'H' => $dt->format('H'),

        'i' => $dt->format('i'),

        's' => $dt->format('s')

    ];

    $out='';

    $len = strlen($format);

    for ($i=0; $i<$len; $i++) {

        $ch = $format[$i];

        $out .= $replacements[$ch] ?? $ch;

    }

    return $out;

}



function gregorian_to_jalali($g_y, $g_m, $g_d) {

    $g_days_in_month = [31,28,31,30,31,30,31,31,30,31,30,31];

    $j_days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];

    $gy = $g_y-1600;

    $gm = $g_m-1;

    $gd = $g_d-1;

    $g_day_no = 365*$gy + (int)(($gy+3)/4) - (int)(($gy+99)/100) + (int)(($gy+399)/400);

    for ($i=0; $i<$gm; ++$i)

        $g_day_no += $g_days_in_month[$i];

    if ($gm>1 && (($g_y%4==0 && $g_y%100!=0) || ($g_y%400==0)))

        $g_day_no++;

    $g_day_no += $gd;

    $j_day_no = $g_day_no-79;

    $j_np = (int)($j_day_no/12053);

    $j_day_no %= 12053;

    $jy = 979+33*$j_np+4*(int)($j_day_no/1461);

    $j_day_no %= 1461;

    if ($j_day_no >= 366) {

        $jy += (int)(($j_day_no-366)/365);

        $j_day_no = ($j_day_no-366)%365;

    }

    for ($i=0; $i<11 && $j_day_no >= $j_days_in_month[$i]; ++$i)

        $j_day_no -= $j_days_in_month[$i];

    $jm = $i+1;

    $jd = $j_day_no+1;

    return [$jy, $jm, $jd];

}



function applyDailyProfitsIfDue(): void {

    // apply at 09:00 Asia/Tehran once per day

    $now = new DateTime('now', new DateTimeZone('Asia/Tehran'));

    $today = $now->format('Y-m-d');

    $hour = (int)$now->format('H');

    $last = getSetting('last_profit_apply_date', '');

    if ($hour >= 9 && $last !== $today) {

        db()->exec("UPDATE users SET money = money + daily_profit WHERE daily_profit > 0 AND daily_profit_enabled = 1");

        setSetting('last_profit_apply_date', $today);

    }

function applyUserCleanupIfDue(): void {
    $enabled = (int)(getSetting('user_cleanup_enabled', '0') ?: 0);
    if (!$enabled) return;
    
    $days = (int)(getSetting('user_cleanup_days', '0') ?: 0);
    if ($days <= 0) return;
    
    // Check once per day at 10:00 Asia/Tehran
    $now = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    $today = $now->format('Y-m-d');
    $hour = (int)$now->format('H');
    $lastCheck = getSetting('last_user_cleanup_date', '');
    
    if ($hour >= 10 && $lastCheck !== $today) {
        // Find users who haven't been active for specified days
        $cutoffDate = $now->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
        
        $stmt = db()->prepare("SELECT id, telegram_id, username, country FROM users WHERE is_registered=1 AND (updated_at IS NULL OR updated_at < ?) AND country IS NOT NULL");
        $stmt->execute([$cutoffDate]);
        $inactiveUsers = $stmt->fetchAll();
        
        foreach ($inactiveUsers as $user) {
            // Reset registration instead of hard delete (same as manual deletion)
            db()->prepare("UPDATE users SET is_registered=0, country=NULL WHERE id=?")->execute([$user['id']]);
            
            // Send notification to channel
            $header = '🚨 𝗪𝗼𝗿𝗹𝗱 𝗡𝗲𝘄𝘀 | اخبار جهانی 🚨';
            $countryName = $user['country'] ?: 'کشور';
            $username = $user['username'] ? ('@' . $user['username']) : '';
            $reason = 'عدم فعالیت به مدت ' . $days . ' روز';
            
            $msg = $header . "

" . e($countryName) . ' خالی شد ❌' . "

" . $username . "

" . 'دلیل: ' . $reason;
            sendToChannel($msg);
        }
        
        setSetting('last_user_cleanup_date', $today);
    }
}

}



function paginate(int $page, int $perPage): array {

    $page = max(1, $page);

    $offset = ($page - 1) * $perPage;

    return [$offset, $perPage];

}



function formatPrice(int $amount): string { return number_format($amount, 0, '.', ','); }



function getCartTotalForUser(int $userId): int {

    $sql = "SELECT SUM(uci.quantity * si.unit_price) total FROM user_cart_items uci JOIN shop_items si ON si.id=uci.item_id WHERE uci.user_id=?";

    $st = db()->prepare($sql); $st->execute([$userId]); $row=$st->fetch(); return (int)($row['total']??0);

}



function addInventoryForUser(int $userId, int $itemId, int $packs, int $packSize): void {

    $pdo = db();

    $pdo->prepare("INSERT INTO user_items (user_id, item_id, quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity")->execute([$userId,$itemId]);

    $pdo->prepare("UPDATE user_items SET quantity = quantity + ? WHERE user_id=? AND item_id=?")->execute([$packs * $packSize, $userId, $itemId]);

}



function increaseUserDailyProfit(int $userId, int $delta): void {

    db()->prepare("UPDATE users SET daily_profit = GREATEST(0, daily_profit + ?) WHERE id=?")->execute([$delta, $userId]);

}



function addUnitsForUser(int $userId, int $itemId, int $units): void {

    $pdo = db();

    $pdo->prepare("INSERT INTO user_items (user_id, item_id, quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity")->execute([$userId,$itemId]);

    $pdo->prepare("UPDATE user_items SET quantity = quantity + ? WHERE user_id=? AND item_id=?")->execute([$units, $userId, $itemId]);

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

    // cancel any ongoing states on navigation

    clearUserState($chatId);

    clearAdminState($chatId);

    clearGuideMessage($chatId);

    clearHeaderPhoto($chatId, $messageId);

    $isRegistered = (int)$userRow['is_registered'] === 1;

    $isAdmin = getAdminPermissions($chatId) ? true : false;



    switch ($route) {

        case 'home':

            deleteMessage($chatId, $messageId);

            setSetting('header_msg_'.$chatId, '');

            $text = $isRegistered ? 'منوی اصلی' : 'فقط پشتیبانی در دسترس است.';

            sendMessage($chatId, $text, mainMenuKeyboard($isRegistered, $isAdmin));

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

            // Prefer user-specific assets_text, else country assets

            $stmtU = db()->prepare("SELECT assets_text, money, daily_profit, id FROM users WHERE id=?");

            $stmtU->execute([(int)$userRow['id']]);

            $ur = $stmtU->fetch();

            $content = '';

            if ($ur && $ur['assets_text']) {

                $content = $ur['assets_text'];

            } else {

                $stmt = db()->prepare("SELECT content FROM assets WHERE country = ?");

                $stmt->execute([$country]);

                $row = $stmt->fetch();

                $content = $row && $row['content'] ? $row['content'] : 'دارایی برای کشور شما ثبت نشده است.';

            }

            // Append shop items grouped by category

            $lines = [];

            $cats = db()->query("SELECT id,name FROM shop_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

            foreach($cats as $c){

                $st = db()->prepare("SELECT si.name, ui.quantity FROM user_items ui JOIN shop_items si ON si.id=ui.item_id WHERE ui.user_id=? AND si.category_id=? AND ui.quantity>0 ORDER BY si.name ASC");

                $st->execute([(int)$ur['id'], (int)$c['id']]); $items=$st->fetchAll();

                if ($items){

                    $lines[] = $c['name'];

                    foreach($items as $it){ $lines[] = e($it['name']).' : '.$it['quantity']; }

                    $lines[]='';

                }

            }

            if ($lines) { $content = trim($content) . "\n\n" . implode("\n", array_filter($lines)); }

            $wallet = '';
            if ($ur) { $wallet = "\n\nپول: ".$ur['money']." | سود روزانه: ".$ur['daily_profit']." | M Coin: ".($ur['m_coins']??0); }

            $kb = [
                [ ['text'=>'تجارت','callback_data'=>'nav:trade'] ],
                [ ['text'=>'بازگشت','callback_data'=>'nav:home'] ]
            ];

            editMessageText($chatId, $messageId, 'دارایی های شما (' . e($country) . "):\n\n" . e($content) . $wallet, ['inline_keyboard'=>$kb]);

            break;

        case 'trade':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            
            $kb = [
                [ ['text'=>'ارسال درخواست تجارت','callback_data'=>'trade:send'] ],
                [ ['text'=>'درخواست‌های دریافتی','callback_data'=>'trade:received'] ],
                [ ['text'=>'درخواست‌های ارسالی','callback_data'=>'trade:sent'] ],
                [ ['text'=>'بازگشت','callback_data'=>'nav:assets'] ]
            ];
            editMessageText($chatId, $messageId, 'سیستم تجارت', ['inline_keyboard'=>$kb]);
            break;

        case 'shop':

            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }

            // list categories + cart button

            $cats = db()->query("SELECT id, name FROM shop_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

            $kb=[]; foreach($cats as $c){ $kb[]=[ ['text'=>$c['name'], 'callback_data'=>'user_shop:cat|id='.$c['id']] ]; }

            $kb[]=[ ['text'=>'سبد خرید','callback_data'=>'user_shop:cart'] ];

            $kb[]=[ ['text'=>'کارخانه‌های نظامی','callback_data'=>'user_shop:factories'] ];
            // Add VIP Shop button if enabled
            $vipEnabled = (int)(getSetting('vip_shop_enabled', '0') ?: 0);
            if ($vipEnabled) {
                $kb[]=[ ['text'=>'VIP Shop','callback_data'=>'user_shop:vip_shop'] ];
            }

            $kb[]=[ ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,'فروشگاه',['inline_keyboard'=>$kb]);

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

    if (in_array('all', $perms, true) || hasPerm($chatId, 'shop')) $rows[] = [ ['text' => 'فروشگاه', 'callback_data' => 'admin:shop'] ];

    if (in_array('all', $perms, true) || hasPerm($chatId, 'settings')) $rows[] = [ ['text' => 'تنظیمات دکمه ها', 'callback_data' => 'admin:buttons'] ];

    if (in_array('all', $perms, true) || hasPerm($chatId, 'users')) $rows[] = [ ['text' => 'کاربران ثبت شده', 'callback_data' => 'admin:users'] ];

    if (in_array('all', $perms, true) || hasPerm($chatId, 'bans')) $rows[] = [ ['text' => 'مدیریت بن', 'callback_data' => 'admin:bans'] ];

    if (in_array('all', $perms, true) || hasPerm($chatId, 'wheel')) $rows[] = [ ['text' => 'گردونه شانس', 'callback_data' => 'admin:wheel'] ];

    if (in_array('all', $perms, true) || hasPerm($chatId, 'alliances')) $rows[] = [ ['text' => 'مدیریت اتحادها', 'callback_data' => 'admin:alliances|page=1'] ];

    if (isOwner($chatId)) $rows[] = [ ['text' => 'مدیریت ادمین ها', 'callback_data' => 'admin:admins'] ];

    $rows[] = [ ['text' => 'بازگشت', 'callback_data' => 'nav:home'] ];

    $backRow = array_pop($rows);

    $kb = widenKeyboard(['inline_keyboard' => $rows]);

    $kb['inline_keyboard'][] = $backRow;

    editMessageText($chatId, $messageId, 'پنل مدیریت', $kb);

}



// --------------------- ADMIN SECTIONS ---------------------



function handleAdminNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {

    // cancel ongoing admin state upon any admin navigation

    clearAdminState($chatId);

    clearGuideMessage($chatId);

    clearHeaderPhoto($chatId, $messageId);

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

            sendGuide($chatId,'راهنما: برای دیدن جزئیات، پاسخ یا حذف روی هر مورد کلیک کنید.');

            break;

        case 'support_view':

            $id = (int)$params['id']; $page = (int)($params['page'] ?? 1);

            $stmt = db()->prepare("SELECT sm.*, u.telegram_id, u.username FROM support_messages sm JOIN users u ON u.id=sm.user_id WHERE sm.id=?");

            $stmt->execute([$id]); $r = $stmt->fetch();

            if (!$r) { answerCallback($_POST['callback_query']['id'] ?? '', 'پیدا نشد', true); return; }

            $hdr = usernameLink($r['username'], (int)$r['telegram_id']) . "\nID: <code>" . (int)$r['telegram_id'] . "</code>\nزمان: " . iranDateTime($r['created_at']);

            $kb = [

                [ ['text'=>'پاسخ','callback_data'=>'admin:support_reply|id='.$id.'|page='.$page] ],

                [ ['text'=>'حذف','callback_data'=>'admin:support_del|id='.$id.'|page='.$page] ],

                [ ['text'=>'بازگشت','callback_data'=>'admin:support|page='.$page] ]

            ];

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            $body = $hdr . "\n\n" . ($r['text'] ? e($r['text']) : '');

            if ($r['photo_file_id']) {

                sendPhoto($chatId, $r['photo_file_id'], $body, $kb);

            } else {

                editMessageText($chatId, $messageId, $body, $kb);

            }

            break;

        case 'support_close':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            db()->prepare("UPDATE support_messages SET status='deleted' WHERE id=?")->execute([$id]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'بسته شد');

            handleAdminNav($chatId,$messageId,'support',['page'=>$page],$userRow);

            break;

        case 'support_reply':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            setAdminState($chatId,'await_support_reply',['support_id'=>$id,'page'=>$page]);

            sendGuide($chatId,'پاسخ خود را به کاربر بفرستید.');

            answerCallback($_POST['callback_query']['id'] ?? '', '');

            break;

        case 'buttons':

            $rows = db()->query("SELECT `key`, title, enabled FROM button_settings WHERE `key` IN ('army','missile','defense','roles','statement','war','assets','support','alliance','shop') ORDER BY id ASC")->fetchAll();

            $kb=[]; foreach($rows as $r){ $txt = ($r['enabled']? 'روشن':'خاموش').' - '.$r['title']; $kb[] = [ ['text'=>$txt, 'callback_data'=>'admin:btn_toggle|key='.$r['key']] , ['text'=>'تغییر نام','callback_data'=>'admin:btn_rename|key='.$r['key']], ['text'=>'زمان‌بندی','callback_data'=>'admin:btn_sched|key='.$r['key']] ]; }

            $kb[]=[ ['text'=>'حالت نگهداری','callback_data'=>'admin:maint'] ];

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ];

            editMessageText($chatId,$messageId,'تنظیمات دکمه ها',['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: برای تغییر نام یا روشن/خاموش کردن هر دکمه، روی گزینه‌ها کلیک کنید.');

            break;

        case 'maint':

            $on = isMaintenanceEnabled();

            $status = $on ? 'روشن' : 'خاموش';

            $kb=[ [ ['text'=>$on?'خاموش کردن':'روشن کردن','callback_data'=>'admin:maint_toggle'] , ['text'=>'تنظیم پیام','callback_data'=>'admin:maint_msg'] ], [ ['text'=>'بازگشت','callback_data'=>'admin:buttons'] ] ];

            editMessageText($chatId,$messageId,'حالت نگهداری: '.$status,['inline_keyboard'=>$kb]);

            break;

        case 'maint_toggle':

            $on = isMaintenanceEnabled(); setSetting('maintenance', $on?'0':'1');

            handleAdminNav($chatId,$messageId,'maint',[],$userRow);

            break;

        case 'maint_msg':

            setAdminState($chatId,'await_maint_msg',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'متن پیام نگهداری را ارسال کنید');

            break;

        case 'amd':

            $kb = [

                [ ['text'=>'لشکر کشی','callback_data'=>'admin:amd_list|type=army|page=1'], ['text'=>'حمله موشکی','callback_data'=>'admin:amd_list|type=missile|page=1'] ],

                [ ['text'=>'دفاع','callback_data'=>'admin:amd_list|type=defense|page=1'] ],

                [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ]

            ];

            editMessageText($chatId, $messageId, 'انتخاب بخش', ['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: بخش موردنظر را انتخاب کنید و سپس از لیست، مورد را برای نمایش/حذف انتخاب کنید.');

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

            sendGuide($chatId,'راهنما: برای مشاهده یا حذف روی آیتم کلیک کنید.');

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

            sendGuide($chatId,'راهنما: لیست را باز کنید، هر مورد را برای ارسال به کانال یا حذف بازبینی کنید.');

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

            sendGuide($chatId,'راهنما: مورد را انتخاب کنید تا به کانال ارسال یا حذف نمایید.');

            break;

        case 'sw_view':

            $id=(int)$params['id']; $type=$params['type']??'statement'; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.username, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");

            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $countryLine = $type==='war' ? ('کشور حمله کننده: '.e($r['attacker_country'])."\n".'کشور دفاع کننده: '.e($r['defender_country'])) : ('کشور: '.e($r['country']));

            $hdr = 'فرستنده: ' . usernameLink($r['username'],(int)$r['telegram_id'])."\n".$countryLine."\nزمان: ".iranDateTime($r['created_at']);

            $btnSend = $type==='war' ? ['text'=>'ارسال (با تعیین مهاجم/مدافع)','callback_data'=>'admin:war_prepare|id='.$id.'|page='.$page] : ['text'=>'فرستادن به کانال','callback_data'=>'admin:sw_send|id='.$id.'|type='.$type.'|page='.$page];

            $kb = [ [ $btnSend, ['text'=>'حذف','callback_data'=>'admin:sw_del|id='.$id.'|type='.$type.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:sw_list|type='.$type.'|page='.$page] ] ];

            $body = $hdr . "\n\n" . ($r['text']?e($r['text']):'');

            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]); else editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$kb]);

            break;

        case 'war_prepare':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            setAdminState($chatId,'await_war_attacker',['submission_id'=>$id,'page'=>$page]);

            sendMessage($chatId,'آیدی عددی حمله کننده را ارسال کنید.');

            break;

        case 'sw_send':

            $id=(int)$params['id']; $type=$params['type']??'statement'; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT s.*, u.username, u.country FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?");

            $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            if ($type==='war') {

                $epics = [

                    'کشور '.e($r['attacker_country']).' به کشور '.e($r['defender_country']).' یورش برد! شعله‌های جنگ زبانه کشید...',

                    'آتش جنگ میان '.e($r['attacker_country']).' و '.e($r['defender_country']).' برافروخته شد! آسمان‌ها لرزید...',

                    'ناقوس نبرد به صدا درآمد؛ '.e($r['attacker_country']).' در برابر '.e($r['defender_country']).' ایستاد!',

                    e($r['attacker_country']).' حمله را آغاز کرد و '.e($r['defender_country']).' دفاع می‌کند! سرنوشت رقم می‌خورد...',

                    'زمین از قدم‌های سربازان '.e($r['attacker_country']).' تا '.e($r['defender_country']).' می‌لرزد!',

                    'نبرد بزرگ میان '.e($r['attacker_country']).' و '.e($r['defender_country']).' شروع شد!'

                ];

                $headline = $epics[array_rand($epics)];

                $caption = $headline."\n\n".($r['text']?e($r['text']):'');

                // only attacker flag

                $flag = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?"); $flag->execute([$r['attacker_country']]); $fr=$flag->fetch();

                if ($fr && $fr['photo_file_id']) { sendPhotoToChannel($fr['photo_file_id'], $caption); }

                else if ($r['photo_file_id']) { sendPhotoToChannel($r['photo_file_id'], $caption); }

                else { sendToChannel($caption); }

                // cleanup: delete UI and remove from list

                deleteMessage($chatId, $messageId);

                db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);

                answerCallback($_POST['callback_query']['id'] ?? '', 'ارسال شد');

            } else {

                $header = '🚨 𝗪𝗼𝗿𝗹𝗱 𝗡𝗲𝘄𝘀 | اخبار جهانی 🚨';

                $title = 'بیانیه ' . e($r['country']?:'');

                $pv = 'Pv | ' . ($r['username'] ? '@'.e($r['username']) : ('ID: '.(int)$r['telegram_id']));

                $body = $r['text'] ? e($r['text']) : '';

                $text = $header . "\n\n" . $title . "\n\n" . $pv . "\n\n" . $body;

                if ($r['photo_file_id']) sendPhotoToChannel($r['photo_file_id'], $text); else sendToChannel($text);

                // cleanup: delete UI and remove from list

                deleteMessage($chatId, $messageId);

                db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);

                answerCallback($_POST['callback_query']['id'] ?? '', 'ارسال شد');

            }

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

            $stmt = db()->prepare("SELECT s.id, s.created_at, u.username, u.telegram_id, u.country, s.status FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.type='role' AND s.status IN ('pending','cost_proposed') ORDER BY s.created_at ASC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = e($r['country']).' | '.iranDateTime($r['created_at']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.$r['status']; $kbRows[] = [ ['text'=>$label,'callback_data'=>'admin:role_view|id='.$r['id'].'|page='.$page] ]; }

            $hasMore = ($offset + count($rows)) < $total;

            $kb = array_merge($kbRows, paginationKeyboard('admin:roles', $page, $hasMore, 'nav:admin')['inline_keyboard']);

            $kb[] = [ ['text'=>'رول‌های تایید شده','callback_data'=>'admin:roles_approved|page=1'] ];

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            editMessageText($chatId,$messageId,'رول ها',['inline_keyboard'=>$kb['inline_keyboard']]);

            break;

        case 'roles_approved':

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $stmt = db()->prepare("SELECT * FROM approved_roles ORDER BY approved_at DESC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kb=[]; foreach($rows as $r){ $label = iranDateTime($r['approved_at']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.e($r['country']); if((int)($r['cost_amount']?:0)>0){ $label.=' | هزینه: '.(int)$r['cost_amount']; } $kb[]=[ ['text'=>$label, 'callback_data'=>'admin:roles_approved_view|id='.$r['id'].'|page='.$page] ]; }

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:roles|page=1'] ];

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            editMessageText($chatId,$messageId,'رول‌های تایید شده',['inline_keyboard'=>$kb['inline_keyboard']]);

            break;

        case 'roles_approved_view':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT * FROM approved_roles WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $body = 'کاربر: '.($r['username']?'@'.$r['username']:$r['telegram_id'])."\nکشور: ".e($r['country']); if((int)($r['cost_amount']?:0)>0){ $body .= "\nهزینه: ".(int)$r['cost_amount']; } $body .= "\n\n".e($r['text']);

            $kb=[ ['text'=>'بازگشت','callback_data'=>'admin:roles_approved|page='.$page] ];

            editMessageText($chatId,$messageId,$body,['inline_keyboard'=>[$kb]]);

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

            $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.username, u.country, u.id AS uid FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            // insert into approved_roles

            db()->prepare("INSERT INTO approved_roles (submission_id, user_id, text, cost_amount, username, telegram_id, country) VALUES (?,?,?,?,?,?,?)")

              ->execute([$id, (int)$r['uid'], $r['text'], $r['cost_amount'], $r['username'], $r['telegram_id'], $r['country']]);

            db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);

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

            answerCallback($_POST['callback_query']['id'] ?? '', '');

            sendMessage($chatId,'هزینه رول را ارسال کنید (فقط عدد).');

            break;

        case 'assets':

            if (!hasPerm($chatId,'assets') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $total = db()->query("SELECT COUNT(*) c FROM users WHERE is_registered=1")->fetch()['c']??0;

            $stmt = db()->prepare("SELECT id, telegram_id, username, country FROM users WHERE is_registered=1 ORDER BY id DESC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = ($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.($r['country']?:'—'); $kbRows[]=[ ['text'=>$label,'callback_data'=>'admin:asset_user_view|id='.$r['id'].'|page='.$page] ]; }

            $extra=[[ ['text'=>'مقدار ثروت بازیکن‌ها','callback_data'=>'admin:assets_wealth'] ]];

            $kb = array_merge($extra, $kbRows, paginationKeyboard('admin:assets', $page, ($offset+count($rows))<$total, 'nav:admin')['inline_keyboard']);

            editMessageText($chatId,$messageId,'انتخاب کاربر برای مشاهده/ویرایش دارایی',['inline_keyboard'=>$kb]);

            break;

        case 'asset_edit':

            $country = urldecode($params['country'] ?? ''); if(!$country){ answerCallback($_POST['callback_query']['id']??'','کشور نامعتبر',true); return; }

            setAdminState($chatId,'await_asset_text',['country'=>$country]);

            // show flag if exists

            $flag = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?"); $flag->execute([$country]); $fr=$flag->fetch();

            if ($fr && $fr['photo_file_id']) { sendPhoto($chatId, $fr['photo_file_id'], 'پرچم کشور '.e($country).'\nلطفاً متن دارایی را ارسال کنید.'); }

            else { sendMessage($chatId,'متن دارایی را ارسال کنید.'); }

            break;

        case 'assets_wealth':

            // List all players wealth and daily profits, with totals

            $rows = db()->query("SELECT id, username, telegram_id, country, money, daily_profit FROM users WHERE is_registered=1 ORDER BY id ASC")->fetchAll();

            if (!$rows) { answerCallback($_POST['callback_query']['id'] ?? '', 'کاربری ثبت نشده است', true); return; }

            $totalMoney = 0; $totalProfit = 0; $lines=[]; $idx=1;

            foreach($rows as $r){ $totalMoney += (int)$r['money']; $totalProfit += (int)$r['daily_profit']; $country = $r['country']?:'—'; $name = $r['username']?'@'.$r['username']:$r['telegram_id']; $lines[] = '#'.$idx.' '+$country+' - مقدار پول: '.(int)$r['money'].' | سود روزانه: '.(int)$r['daily_profit'].' - '+$name; $idx++; }

            $header = 'جمع پول: '.(int)$totalMoney.' | جمع سود روزانه: '.(int)$totalProfit;

            $kb=[ [ ['text'=>'بازگشت','callback_data'=>'admin:assets'] ] ];

            editMessageText($chatId,$messageId, $header."\n\n".implode("\n", $lines), ['inline_keyboard'=>$kb]);

            break;

        case 'asset_user_view':

            $uid=(int)($params['id']??0); $page=(int)($params['page']??1);

            $u = db()->prepare("SELECT username, telegram_id, country, assets_text, money, daily_profit, daily_profit_enabled FROM users WHERE id=?"); $u->execute([$uid]); $ur=$u->fetch(); if(!$ur){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }

            $hdr = 'کاربر: '.($ur['username']?'@'.$ur['username']:$ur['telegram_id'])."\nکشور: ".($ur['country']?:'—')."\nپول: ".$ur['money']." | سود روزانه: ".$ur['daily_profit']." | وضعیت سود روزانه: ".(((int)$ur['daily_profit_enabled']===1)?'فعال':'غیرفعال');

            $text = $ur['assets_text'] ?: '—';

            $toggleLbl = ((int)$ur['daily_profit_enabled']===1)?'غیرفعال کردن سود روزانه':'فعال کردن سود روزانه';

            $kb=[ [ ['text'=>'تغییر دارایی متنی','callback_data'=>'admin:asset_user_edit|id='.$uid.'|page='.$page], ['text'=>$toggleLbl,'callback_data'=>'admin:asset_user_toggle_profit|id='.$uid.'|page='.$page] ], [ ['text'=>'کپی دارایی','copy_text'=>['text'=>$text]] ], [ ['text'=>'بازگشت','callback_data'=>'admin:assets|page='.$page] ] ];

            if (!empty($messageId)) { @deleteMessage($chatId,$messageId); }

            $body = $hdr."\n\n".e($text);

            $resp = sendMessage($chatId, $body, ['inline_keyboard'=>$kb]);

            if ($resp && ($resp['ok']??false)) { setSetting('asset_msg_'.$chatId, (string)($resp['result']['message_id']??0)); }

            break;

        case 'asset_user_toggle_profit':

            $uid=(int)($params['id']??0); $page=(int)($params['page']??1);

            $r = db()->prepare("SELECT daily_profit_enabled FROM users WHERE id=?"); $r->execute([$uid]); $ur=$r->fetch(); if(!$ur){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }

            $new = ((int)$ur['daily_profit_enabled']===1)?0:1;

            db()->prepare("UPDATE users SET daily_profit_enabled=? WHERE id=?")->execute([$new,$uid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'به‌روزرسانی شد');

            handleAdminNav($chatId,$messageId,'asset_user_view',['id'=>$uid,'page'=>$page],$userRow);

            break;

        case 'asset_user_edit':

            $uid=(int)($params['id']??0); $page=(int)($params['page']??1);

            setAdminState($chatId,'await_asset_user_text',['id'=>$uid,'page'=>$page]);

            // Clean previous combined asset message so only prompt remains

            $mm = getSetting('asset_msg_'.$chatId); if ($mm) { @deleteMessage($chatId, (int)$mm); setSetting('asset_msg_'.$chatId,''); }

            sendMessage($chatId,'متن جدید دارایی کاربر را ارسال کنید.');

            break;

        case 'buttons':

            $rows = db()->query("SELECT `key`, title, enabled FROM button_settings WHERE `key` IN ('army','missile','defense','roles','statement','war','assets','support','alliance','shop') ORDER BY id ASC")->fetchAll();

            $kb=[]; foreach($rows as $r){ $txt = ($r['enabled']? 'روشن':'خاموش').' - '.$r['title']; $kb[] = [ ['text'=>$txt, 'callback_data'=>'admin:btn_toggle|key='.$r['key']] , ['text'=>'تغییر نام','callback_data'=>'admin:btn_rename|key='.$r['key']], ['text'=>'زمان‌بندی','callback_data'=>'admin:btn_sched|key='.$r['key']] ]; }

            $kb[]=[ ['text'=>'حالت نگهداری','callback_data'=>'admin:maint'] ];

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

        case 'btn_sched':

            $key=$params['key']??''; if(!$key){ answerCallback($_POST['callback_query']['id']??'','نامعتبر',true); return; }

            // fetch current

            $r = db()->prepare("SELECT days,time_start,time_end FROM button_settings WHERE `key`=?"); $r->execute([$key]); $row=$r->fetch();

            $days = $row && $row['days'] ? $row['days'] : 'all';

            $t1 = $row && $row['time_start'] ? $row['time_start'] : '00:00';

            $t2 = $row && $row['time_end'] ? $row['time_end'] : '00:00';

            $txt = "زمان‌بندی دکمه: ".$key."\nروزها: ".$days."\nبازه ساعت: ".$t1." تا ".$t2."\n\n- روزها: یکی از all یا ترکیب حروف: su,mo,tu,we,th,fr,sa (مثلاً mo,we,fr)\n- ساعت: فرم HH:MM. اگر 00:00 تا 00:00 باشد یعنی همیشه روشن";

            $kb=[ [ ['text'=>'تنظیم روزها','callback_data'=>'admin:btn_sched_days|key='.$key], ['text'=>'تنظیم ساعت','callback_data'=>'admin:btn_sched_time|key='.$key] ], [ ['text'=>'بازگشت','callback_data'=>'admin:buttons'] ] ];

            editMessageText($chatId,$messageId,$txt,['inline_keyboard'=>$kb]);

            break;

        case 'btn_sched_days':

            $key=$params['key']??''; if(!$key){ answerCallback($_POST['callback_query']['id']??'','نامعتبر',true); return; }

            setAdminState($chatId,'await_btn_days',['key'=>$key]);

            sendMessage($chatId,'روزها را ارسال کنید: all یا مثل: mo,tu,we (حروف کوچک، با کاما)');

            break;

        case 'btn_sched_time':

            $key=$params['key']??''; if(!$key){ answerCallback($_POST['callback_query']['id']??'','نامعتبر',true); return; }

            setAdminState($chatId,'await_btn_time',['key'=>$key]);

            sendMessage($chatId,'بازه ساعت را ارسال کنید به فرم HH:MM-HH:MM (مثلاً 09:00-22:00). برای همیشه 00:00-00:00 بفرستید.');

            break;

        case 'users':

            $kb=[ [ ['text'=>'ثبت کاربر','callback_data'=>'admin:user_register'] , ['text'=>'لیست کاربران','callback_data'=>'admin:user_list|page=1'] ], [ ['text'=>'تنظیم استارتر پک','callback_data'=>'admin:starter_pack'], [ ['text'=>'تنظیمات خالی شدن خودکار','callback_data'=>'admin:user_cleanup_settings'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];

            editMessageText($chatId,$messageId,'مدیریت کاربران',['inline_keyboard'=>$kb]);

            break;

        case 'user_register':

            setAdminState($chatId,'await_user_ident',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');

            sendMessage($chatId,'آیدی عددی یا پیام فوروارد کاربر را ارسال کنید تا ثبت شود. سپس نام کشور را بفرستید.');

            break;

        case 'starter_pack':

            // Show current defaults and options to set

            $defMoney = (int)(getSetting('starter_default_money','0') ?: 0);

            $defProfit = (int)(getSetting('starter_default_daily_profit','0') ?: 0);

            $txt = "استارتر پک پیش‌فرض:\n\n- مقدار پول: ".$defMoney."\n- سود روزانه: ".$defProfit;

            $kb=[ [ ['text'=>'تنظیم مقدار پول','callback_data'=>'admin:starter_set_money'], ['text'=>'تنظیم سود روزانه','callback_data'=>'admin:starter_set_profit'] ], [ ['text'=>'بازگشت','callback_data'=>'admin:users'] ] ];

            editMessageText($chatId,$messageId,$txt,['inline_keyboard'=>$kb]);

            break;

        case 'starter_set_money':

            setAdminState($chatId,'await_starter_money',[]);

            sendMessage($chatId,'عدد مقدار پول پیش‌فرض برای کاربران جدید را ارسال کنید (فقط عدد). مقدار فعلی: '.((int)(getSetting('starter_default_money','0')?:0)));

            break;

        case 'starter_set_profit':

            setAdminState($chatId,'await_starter_profit',[]);

            sendMessage($chatId,'عدد سود روزانه پیش‌فرض برای کاربران جدید را ارسال کنید (فقط عدد). مقدار فعلی: '.((int)(getSetting('starter_default_daily_profit','0')?:0)));

            break;

        case 'user_cleanup_settings':
            $enabled = (int)(getSetting('user_cleanup_enabled', '0') ?: 0);
            $days = (int)(getSetting('user_cleanup_days', '0') ?: 0);
            $status = $enabled ? 'فعال' : 'غیرفعال';
            $txt = "تنظیمات خالی شدن خودکار کاربران:\n\n- وضعیت: ".$status."\n- مقدار روز: ".$days;
            $kb=[ [ ['text'=>$enabled?'غیرفعال کردن':'فعال کردن','callback_data'=>'admin:user_cleanup_toggle'], ['text'=>'تنظیم مقدار روز','callback_data'=>'admin:user_cleanup_set_days'] ], [ ['text'=>'بازگشت به منو قبلی','callback_data'=>'admin:users'] ] ];
            editMessageText($chatId,$messageId,$txt,['inline_keyboard'=>$kb]);
            break;

        case 'user_cleanup_toggle':
            $enabled = (int)(getSetting('user_cleanup_enabled', '0') ?: 0);
            setSetting('user_cleanup_enabled', $enabled ? '0' : '1');
            handleAdminNav($chatId,$messageId,'user_cleanup_settings',[],$userRow);
            break;

        case 'user_cleanup_set_days':
            setAdminState($chatId,'await_cleanup_days',[]);
            sendMessage($chatId,'مقدار روز برای خالی شدن خودکار کاربران را ارسال کنید (عدد). مقدار فعلی: '.((int)(getSetting('user_cleanup_days','0')?:0)));
            break;

        case 'user_list':

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $total = db()->query("SELECT COUNT(*) c FROM users WHERE is_registered=1")->fetch()['c']??0;

            $stmt = db()->prepare("SELECT id, telegram_id, username, country FROM users WHERE is_registered=1 ORDER BY country ASC, id ASC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = e($r['country']).' | '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kbRows[]=[ ['text'=>$label, 'callback_data'=>'admin:user_view|id='.$r['id'].'|page='.$page] ]; }

            $hasMore = ($offset + count($rows)) < $total;

            $kb = array_merge($kbRows, paginationKeyboard('admin:user_list', $page, $hasMore, 'admin:users')['inline_keyboard']);

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            editMessageText($chatId,$messageId,'کاربران ثبت شده',['inline_keyboard'=>$kb['inline_keyboard']]);

            break;

        case 'bans':

            if (!hasPerm($chatId,'bans') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $kb = [

                [ ['text'=>'بن کردن کاربر','callback_data'=>'admin:ban_add'], ['text'=>'حذف بن','callback_data'=>'admin:ban_remove'] ],

                [ ['text'=>'لیست کاربران بن‌شده','callback_data'=>'admin:ban_list'] ],

                [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ]

            ];

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            editMessageText($chatId,$messageId,'مدیریت بن',['inline_keyboard'=>$kb['inline_keyboard']]);

            break;

        case 'ban_add':

            if (!hasPerm($chatId,'bans') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            setAdminState($chatId,'await_ban_ident',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');

            sendMessage($chatId,'آیدی عددی یا پیام فوروارد کاربر را برای بن ارسال کنید.');

            break;

        case 'ban_remove':

            if (!hasPerm($chatId,'bans') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            setAdminState($chatId,'await_unban_ident',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');

            sendMessage($chatId,'آیدی عددی یا پیام فوروارد کاربر را برای حذف بن ارسال کنید.');

            break;

        case 'ban_list':

            if (!hasPerm($chatId,'bans') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $rows = db()->query("SELECT username, telegram_id FROM users WHERE banned=1 ORDER BY id ASC LIMIT 100")->fetchAll();

            $lines = array_map(function($r){ return ($r['username']?'@'.$r['username']:$r['telegram_id']); }, $rows);

            editMessageText($chatId,$messageId, $lines ? ("لیست بن ها:\n".implode("\n",$lines)) : 'لیستی وجود ندارد', backButton('admin:bans'));

            break;

        case 'wheel':

            $kb=[ [ ['text'=>'ثبت جایزه گردونه شانس','callback_data'=>'admin:wheel_set'] ], [ ['text'=>'شروع گردونه شانس','callback_data'=>'admin:wheel_start'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];

            editMessageText($chatId,$messageId,'گردونه شانس',['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: ابتدا جایزه را ثبت کنید، سپس شروع را بزنید تا یک برنده تصادفی اعلام شود.');

            break;

        case 'wheel_set':

            setAdminState($chatId,'await_wheel_prize',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'نام جایزه را ارسال کنید');

            sendMessage($chatId,'نام جایزه گردونه شانس را ارسال کنید.');

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

        case 'alliances':

            if (!hasPerm($chatId,'alliances') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $total = db()->query("SELECT COUNT(*) c FROM alliances")->fetch()['c']??0;

            $stmt = db()->prepare("SELECT a.id, a.name, a.created_at, u.username, u.telegram_id, u.country FROM alliances a JOIN users u ON u.id=a.leader_user_id ORDER BY a.created_at DESC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = e($r['name']).' | رهبر: '.e($r['country']).' - '.($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.iranDateTime($r['created_at']); $kbRows[]=[ ['text'=>$label,'callback_data'=>'admin:alli_view|id='.$r['id'].'|page='.$page] ]; }

            $hasMore = ($offset + count($rows)) < $total;

            $kb = array_merge($kbRows, paginationKeyboard('admin:alliances', $page, $hasMore, 'nav:admin')['inline_keyboard']);

            editMessageText($chatId,$messageId,'مدیریت اتحادها',['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: برای مشاهده جزئیات، حذف اتحاد یا مدیریت اعضا، یک اتحاد را انتخاب کنید.');

            break;

        case 'alli_view':

            if (!hasPerm($chatId,'alliances') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $id=(int)($params['id']??0); $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT a.*, u.username AS leader_username, u.telegram_id AS leader_tid, u.country AS leader_country FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=?");

            $stmt->execute([$id]); $a=$stmt->fetch(); if(!$a){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $members = db()->prepare("SELECT m.user_id, m.role, m.display_name, u.telegram_id, u.username, u.country FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE m.alliance_id=? ORDER BY m.role='leader' DESC, m.id ASC");

            $members->execute([$id]); $ms=$members->fetchAll();

            $lines=[]; $lines[]='اتحاد: '.e($a['name']);

            $lines[]='رهبر: '.e($a['leader_country']).' - '.($a['leader_username']?'@'.$a['leader_username']:$a['leader_tid']);

            $lines[]='شعار: ' . ($a['slogan']?e($a['slogan']):'—');

            $lines[]='اعضا:';

            foreach($ms as $m){ if($m['role']!=='leader'){ $disp = $m['display_name'] ?: $m['country']; $lines[]='- '.e($disp).' - '.($m['username']?'@'.$m['username']:$m['telegram_id']); } }

            $kb=[ [ ['text'=>'اعضا','callback_data'=>'admin:alli_members|id='.$id.'|page='.$page], ['text'=>'حذف اتحاد','callback_data'=>'admin:alli_del|id='.$id.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:alliances|page='.$page] ] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'alli_members':

            if (!hasPerm($chatId,'alliances') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $id=(int)($params['id']??0); $page=(int)($params['page']??1);

            $ms = db()->prepare("SELECT m.user_id, m.role, m.display_name, u.telegram_id, u.username, u.country FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE m.alliance_id=? ORDER BY m.role='leader' DESC, m.id ASC");

            $ms->execute([$id]); $rows=$ms->fetchAll();

            $kb=[]; foreach($rows as $r){ if($r['role']==='leader') continue; $label = e($r['country']).' - '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kb[]=[ ['text'=>$label, 'callback_data'=>'admin:alli_mem_del|aid='.$id.'|uid='.$r['user_id'].'|page='.$page] ]; }

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:alli_view|id='.$id.'|page='.$page] ];

            editMessageText($chatId,$messageId,'اعضای اتحاد (برای حذف عضو کلیک کنید)',['inline_keyboard'=>$kb]);

            break;

        case 'alli_mem_del':

            if (!hasPerm($chatId,'alliances') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $aid=(int)($params['aid']??0); $uid=(int)($params['uid']??0); $page=(int)($params['page']??1);

            // prevent removing leader

            $isLeader = db()->prepare("SELECT 1 FROM alliances a JOIN alliance_members m ON m.user_id=a.leader_user_id WHERE a.id=? AND m.user_id=?");

            $isLeader->execute([$aid,$uid]); if($isLeader->fetch()){ answerCallback($_POST['callback_query']['id']??'','نمی‌توان رهبر را حذف کرد', true); return; }

            db()->prepare("DELETE FROM alliance_members WHERE alliance_id=? AND user_id=?")->execute([$aid,$uid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'عضو حذف شد');

            handleAdminNav($chatId,$messageId,'alli_members',['id'=>$aid,'page'=>$page],$userRow);

            break;

        case 'alli_del':

            if (!hasPerm($chatId,'alliances') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $id=(int)($params['id']??0); $page=(int)($params['page']??1);

            db()->prepare("DELETE FROM alliances WHERE id=?")->execute([$id]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'اتحاد حذف شد');

            handleAdminNav($chatId,$messageId,'alliances',['page'=>$page],$userRow);

            break;

        case 'admins':

            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }

            $kb=[ [ ['text'=>'افزودن ادمین','callback_data'=>'admin:adm_add'] ], [ ['text'=>'لیست ادمین ها','callback_data'=>'admin:adm_list'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];

            editMessageText($chatId,$messageId,'مدیریت ادمین ها',['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: پس از افزودن، وارد پروفایل ادمین شوید و دسترسی بخش‌ها را تنظیم کنید.');

            break;

        case 'adm_add':

            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }

            setAdminState($chatId,'await_admin_ident',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده ادمین را ارسال کنید');

            sendMessage($chatId,'آیدی عددی ادمین جدید را ارسال کنید.');

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

        case 'adm_delete':

            if (!isOwner($chatId)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط ادمین اصلی', true); return; }

            $aid=(int)($params['id']??0);

            if ($aid === MAIN_ADMIN_ID) { answerCallback($_POST['callback_query']['id'] ?? '', 'حذف Owner مجاز نیست', true); return; }

            db()->prepare("DELETE FROM admin_users WHERE admin_telegram_id=?")->execute([$aid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'ادمین حذف شد');

            handleAdminNav($chatId,$messageId,'adm_list',[],$userRow);

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

        case 'await_war_defender':

            $sid=(int)$params['submission_id']; $page=(int)($params['page']??1); $attTid=(int)$params['att_tid'];

            $defTid = extractTelegramIdFromMessage($message);

            if (!$defTid) { sendMessage($chatId,'آیدی نامعتبر. دوباره آیدی عددی دفاع کننده را بفرستید.'); return; }

            // Show confirm with attacker/defender info

            $att = ensureUser(['id'=>$attTid]); $def = ensureUser(['id'=>$defTid]);

            $info = 'حمله کننده: '.($att['username']?'@'.$att['username']:$attTid).' | کشور: '.($att['country']?:'—')."\n".

                    'دفاع کننده: '.($def['username']?'@'.$def['username']:$defTid).' | کشور: '.($def['country']?:'—');

            $kb = [ [ ['text'=>'ارسال','callback_data'=>'admin:war_send_confirm|id='.$sid.'|att='.$attTid.'|def='.$defTid], ['text'=>'لغو','callback_data'=>'admin:sw_view|id='.$sid.'|type=war|page='.$page] ] ];

            sendMessage($chatId,$info,['inline_keyboard'=>$kb]);

            clearAdminState($chatId);

            break;

        case 'war_send':

            $sid=(int)($params['id']??0); $attTid=(int)($params['att']??0); $defTid=(int)($params['def']??0); $mode=$params['mode']??'auto';

            $ok = sendWarWithMode($sid,$attTid,$defTid,$mode);

            answerCallback($_POST['callback_query']['id'] ?? '', $ok?'ارسال شد':'ارسال ناموفق', !$ok);

            break;

        case 'war_send_confirm':

            $sid=(int)($params['id']??0); $attTid=(int)($params['att']??0); $defTid=(int)($params['def']??0);

            $ok = sendWarWithMode($sid,$attTid,$defTid,'att');

            if ($ok) {

                // delete confirm UI and remove from list

                deleteMessage($chatId, $messageId);

                db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$sid]);

                answerCallback($_POST['callback_query']['id'] ?? '', 'ارسال شد');

            } else {

                answerCallback($_POST['callback_query']['id'] ?? '', 'ارسال ناموفق', true);

            }

            break;

        case 'user_del':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            setAdminState($chatId,'await_user_delete_reason',['id'=>$id,'page'=>$page]);

            sendMessage($chatId,'دلیل حذف کاربر را ارسال کنید.');

            break;

        case 'user_view':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $stmt=db()->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $hdr = usernameLink($r['username'], (int)$r['telegram_id'])."\nID: ".$r['telegram_id']."\nکشور: ".e($r['country'])."\nثبت: ".((int)$r['is_registered']?'بله':'خیر')."\nبن: ".((int)$r['banned']?'بله':'خیر');

            $kb=[

                [ ['text'=>'مدیریت دارایی کاربر','callback_data'=>'admin:user_assets|id='.$id.'|page='.$page], ['text'=>'تنظیم پرچم کشور','callback_data'=>'admin:set_flag|id='.$id.'|page='.$page] ],

                [ ['text'=>'حذف کاربر','callback_data'=>'admin:user_del|id='.$id.'|page='.$page] ],

                [ ['text'=>'بازگشت','callback_data'=>'admin:user_list|page='.$page] ]

            ];

            $kb = widenKeyboard(['inline_keyboard'=>$kb]);

            $flagFid = null;

            if ($r['country']) { $flag = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?"); $flag->execute([$r['country']]); $fr=$flag->fetch(); if ($fr && $fr['photo_file_id']) { $flagFid=$fr['photo_file_id']; } }

            deleteMessage($chatId, $messageId);

            if ($flagFid) { $resp = sendPhoto($chatId, $flagFid, $hdr, $kb); if ($resp && ($resp['ok']??false)) setHeaderPhoto($chatId, (int)($resp['result']['message_id']??0)); }

            else { $resp = sendMessage($chatId, $hdr, $kb); if ($resp && ($resp['ok']??false)) setHeaderPhoto($chatId, (int)($resp['result']['message_id']??0)); }

            break;

        case 'user_assets':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $stmt=db()->prepare("SELECT username, telegram_id, country, assets_text, money, daily_profit, m_coins FROM users WHERE id=?"); $stmt->execute([$id]); $u=$stmt->fetch(); if(!$u){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }

            $text = 'دارایی کاربر: '.($u['username']?'@'.$u['username']:$u['telegram_id'])."\nکشور: ".e($u['country'])."\n\n".($u['assets_text']?e($u['assets_text']):'—')."\n\nپول: ".$u['money']." | سود روزانه: ".$u['daily_profit']." | M Coin: ".($u['m_coins']??0);

            $kb=[

                [ ['text'=>'تغییر متن دارایی','callback_data'=>'admin:user_assets_text|id='.$id.'|page='.$page] ],

                [ ['text'=>'+100','callback_data'=>'admin:user_money_delta|id='.$id.'|d=100'], ['text'=>'+1000','callback_data'=>'admin:user_money_delta|id='.$id.'|d=1000'], ['text'=>'-100','callback_data'=>'admin:user_money_delta|id='.$id.'|d=-100'], ['text'=>'-1000','callback_data'=>'admin:user_money_delta|id='.$id.'|d=-1000'] ],

                [ ['text'=>'+10 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=10'], ['text'=>'+100 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=100'], ['text'=>'-10 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=-10'], ['text'=>'-100 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=-100'] ],

                [ ['text'=>'+10 M-Coin','callback_data'=>'admin:user_mcoin_delta|id='.$id.'|d=10'], ['text'=>'+100 M-Coin','callback_data'=>'admin:user_mcoin_delta|id='.$id.'|d=100'], ['text'=>'-10 M-Coin','callback_data'=>'admin:user_mcoin_delta|id='.$id.'|d=-10'], ['text'=>'-100 M-Coin','callback_data'=>'admin:user_mcoin_delta|id='.$id.'|d=-100'] ],

                [ ['text'=>'تنظیم مستقیم پول','callback_data'=>'admin:user_money_set|id='.$id.'|page='.$page], ['text'=>'تنظیم مستقیم سود','callback_data'=>'admin:user_profit_set|id='.$id.'|page='.$page] ],

                [ ['text'=>'تنظیم مستقیم M-Coin','callback_data'=>'admin:user_mcoin_set|id='.$id.'|page='.$page] ],

                [ ['text'=>'مدیریت آیتم‌های فروشگاه','callback_data'=>'admin:user_items|id='.$id.'|page='.$page] ],

                [ ['text'=>'بازگشت','callback_data'=>'admin:user_view|id='.$id.'|page='.$page] ]

            ];

            deleteMessage($chatId, $messageId);

            sendMessage($chatId,$text,['inline_keyboard'=>$kb]);

            break;

        case 'user_assets_text':

            $id=(int)$params['id']; setAdminState($chatId,'await_user_assets_text',['id'=>$id]); sendMessage($chatId,'متن جدید دارایی کاربر را ارسال کنید.'); break;

        case 'user_money_delta':

            $id=(int)$params['id']; $d=(int)($params['d']??0);

            db()->prepare("UPDATE users SET money = GREATEST(0, money + ?) WHERE id=?")->execute([$d,$id]);

            handleAdminNav($chatId,$messageId,'user_assets',['id'=>$id],$userRow);

            break;

        case 'user_profit_delta':

            $id=(int)$params['id']; $d=(int)($params['d']??0);

            db()->prepare("UPDATE users SET daily_profit = GREATEST(0, daily_profit + ?) WHERE id=?")->execute([$d,$id]);

            handleAdminNav($chatId,$messageId,'user_assets',['id'=>$id],$userRow);

            break;

        case 'user_money_set':

            $id=(int)$params['id']; setAdminState($chatId,'await_user_money',['id'=>$id]); sendMessage($chatId,'عدد پول را ارسال کنید.'); break;

        case 'user_profit_set':

            $id=(int)$params['id']; setAdminState($chatId,'await_user_profit',['id'=>$id]); sendMessage($chatId,'عدد سود روزانه را ارسال کنید.'); break;

        case 'user_mcoin_delta':

            $id=(int)$params['id']; $d=(int)($params['d']??0);

            db()->prepare("UPDATE users SET m_coins = GREATEST(0, m_coins + ?) WHERE id=?")->execute([$d,$id]);

            handleAdminNav($chatId,$messageId,'user_assets',['id'=>$id],$userRow);

            break;

        case 'user_mcoin_set':

            $id=(int)$params['id']; setAdminState($chatId,'await_user_mcoin',['id'=>$id]); sendMessage($chatId,'عدد M-Coin را ارسال کنید.'); break;

        case 'set_flag':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT country FROM users WHERE id=?");

            $stmt->execute([$id]);

            $urow = $stmt->fetch();

            if (!$urow || !$urow['country']) {

                answerCallback($_POST['callback_query']['id'] ?? '', 'ابتدا کشور کاربر را تنظیم کنید', true);

                return;

            }

            setAdminState($chatId, 'await_country_flag', ['country' => $urow['country']]);

            $flag = db()->prepare("SELECT photo_file_id FROM country_flags WHERE country=?");

            $flag->execute([$urow['country']]);

            $fr = $flag->fetch();

            if ($fr && $fr['photo_file_id']) {

                sendPhoto($chatId, $fr['photo_file_id'], 'پرچم فعلی ' . e($urow['country']) . "\nعکس جدید را ارسال کنید.");

            } else {

                sendMessage($chatId, 'عکس پرچم برای ' . e($urow['country']) . ' را ارسال کنید.');

            }

            break;

        case 'shop':

            if (!hasPerm($chatId,'shop') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $kb = [

                [ ['text'=>'دسته‌بندی‌ها','callback_data'=>'admin:shop_cats|page=1'] ],

                [ ['text'=>'کارخانه‌های نظامی','callback_data'=>'admin:shop_factories|page=1'] ],

                [ ['text'=>'کدهای تخفیف','callback_data'=>'admin:disc_list|page=1'] ],
                [ ['text'=>'VIP Shop','callback_data'=>'admin:vip_shop'] ],
                [ ['text'=>'تجارت بازیکن‌ها','callback_data'=>'admin:player_trades|page=1'] ],

                [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ]

            ];

            editMessageText($chatId,$messageId,'مدیریت فروشگاه',['inline_keyboard'=>$kb]);

            sendGuide($chatId,'راهنما: از اینجا می‌توانید دسته‌بندی اضافه/حذف کنید، آیتم بسازید و کارخانه‌ها را مدیریت کنید.');

            break;

        case 'shop_cats':

            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);

            $tot = db()->query("SELECT COUNT(*) c FROM shop_categories")->fetch()['c']??0;

            $st = db()->prepare("SELECT id,name,sort_order FROM shop_categories ORDER BY sort_order ASC, name ASC LIMIT ?,?"); $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

            $kb=[]; foreach($rows as $r){ $kb[]=[ ['text'=>$r['sort_order'].' - '.$r['name'],'callback_data'=>'admin:shop_cat_view|id='.$r['id'].'|page='.$page] ]; }

            $kb[]=[ ['text'=>'افزودن دسته','callback_data'=>'admin:shop_cat_add'] ];

            foreach(paginationKeyboard('admin:shop_cats',$page, ($offset+count($rows))<$tot, 'admin:shop')['inline_keyboard'] as $row){ $kb[]=$row; }

            editMessageText($chatId,$messageId,'دسته‌بندی‌ها',['inline_keyboard'=>$kb]);

            break;

        case 'shop_cat_add':

            setAdminState($chatId,'await_shop_cat_name',[]);

            sendGuide($chatId,'نام دسته‌بندی را ارسال کنید. سپس عدد ترتیب (اختیاری) را بفرستید.');

            break;

        case 'shop_cat_view':

            $cid=(int)($params['id']??0); $page=(int)($params['page']??1);

            $c = db()->prepare("SELECT id,name,sort_order FROM shop_categories WHERE id=?"); $c->execute([$cid]); $cat=$c->fetch(); if(!$cat){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $items = db()->prepare("SELECT id,name,unit_price,pack_size,per_user_limit,daily_profit_per_pack,enabled FROM shop_items WHERE category_id=? ORDER BY name ASC"); $items->execute([$cid]); $rows=$items->fetchAll();

            $lines = ['دسته‌بندی: '.e($cat['name']).' (ترتیب: '.$cat['sort_order'].')','آیتم‌ها:']; if(!$rows){ $lines[]='—'; }

            $kb=[]; foreach($rows as $r){

                $lbl = e($r['name']).' | قیمت: '.formatPrice((int)$r['unit_price']).' | بسته: '.$r['pack_size'].' | محدودیت: '.((int)$r['per_user_limit']===0?'∞':$r['per_user_limit']).' | سود/بسته: '.$r['daily_profit_per_pack'].' | '.($r['enabled']?'روشن':'خاموش');

                $kb[]=[ ['text'=>$lbl, 'callback_data'=>'admin:shop_item_view|id='.$r['id'].'|cid='.$cid.'|page='.$page] ];

            }

            $kb[]=[ ['text'=>'افزودن آیتم','callback_data'=>'admin:shop_item_add|cid='.$cid] ];

            $kb[]=[ ['text'=>'ویرایش دسته','callback_data'=>'admin:shop_cat_edit|id='.$cid], ['text'=>'حذف دسته','callback_data'=>'admin:shop_cat_del|id='.$cid.'|page='.$page] ];

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:shop_cats|page='.$page] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'shop_cat_edit':

            $cid=(int)($params['id']??0); setAdminState($chatId,'await_shop_cat_edit',['id'=>$cid]); sendMessage($chatId,'نام جدید و سپس عدد ترتیب را ارسال کنید.'); break;

        case 'shop_cat_del':

            $cid=(int)($params['id']??0); $page=(int)($params['page']??1);

            db()->prepare("DELETE FROM shop_categories WHERE id=?")->execute([$cid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');

            handleAdminNav($chatId,$messageId,'shop_cats',['page'=>$page],$userRow);

            break;

        case 'shop_item_add':

            $cid=(int)($params['cid']??0); setAdminState($chatId,'await_shop_item_name',['cid'=>$cid]); sendMessage($chatId,'نام آیتم را ارسال کنید.'); break;

        case 'shop_item_view':

            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);

            $it = db()->prepare("SELECT * FROM shop_items WHERE id=?"); $it->execute([$iid]); $r=$it->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $body = 'نام: '.e($r['name'])."\nقیمت واحد: ".formatPrice((int)$r['unit_price'])."\nاندازه بسته: ".$r['pack_size']."\nمحدودیت هر کاربر: ".((int)$r['per_user_limit']===0?'∞':$r['per_user_limit'])."\nسود روزانه هر بسته: ".$r['daily_profit_per_pack']."\nوضعیت: ".($r['enabled']?'روشن':'خاموش');

            $kb=[ [ ['text'=>$r['enabled']?'خاموش کردن':'روشن کردن','callback_data'=>'admin:shop_item_toggle|id='.$iid.'|cid='.$cid.'|page='.$page] , ['text'=>'حذف','callback_data'=>'admin:shop_item_del|id='.$iid.'|cid='.$cid.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:shop_cat_view|id='.$cid.'|page='.$page] ] ];

            editMessageText($chatId,$messageId,$body,['inline_keyboard'=>$kb]);

            break;

        case 'shop_item_toggle':

            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);

            db()->prepare("UPDATE shop_items SET enabled = 1 - enabled WHERE id=?")->execute([$iid]);

            handleAdminNav($chatId,$messageId,'shop_item_view',['id'=>$iid,'cid'=>$cid,'page'=>$page],$userRow);

            break;

        case 'shop_item_del':

            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);

            db()->prepare("DELETE FROM shop_items WHERE id=?")->execute([$iid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');

            handleAdminNav($chatId,$messageId,'shop_cat_view',['id'=>$cid,'page'=>$page],$userRow);

            break;

        case 'user_items':

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $st = db()->prepare("SELECT ui.item_id, ui.quantity, si.name, sc.name AS cat FROM user_items ui JOIN shop_items si ON si.id=ui.item_id JOIN shop_categories sc ON sc.id=si.category_id WHERE ui.user_id=? AND ui.quantity>0 ORDER BY sc.sort_order ASC, sc.name ASC, si.name ASC");

            $st->execute([$id]); $rows=$st->fetchAll();

            $kb=[]; $lines=['آیتم‌های فروشگاه کاربر:']; foreach($rows as $r){ $lines[] = e($r['cat']).' | '.e($r['name']).' : '.$r['quantity']; $kb[]=[ ['text'=>e($r['name']).' +1','callback_data'=>'admin:user_item_delta|id='.$id.'|item='.$r['item_id'].'|d=1'], ['text'=>'-1','callback_data'=>'admin:user_item_delta|id='.$id.'|item='.$r['item_id'].'|d=-1'], ['text'=>'تنظیم سریع','callback_data'=>'admin:user_item_set|id='.$id.'|item='.$r['item_id'].'|page='.$page] ]; }

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:user_assets|id='.$id.'|page='.$page] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'user_item_delta':

            $id=(int)$params['id']; $item=(int)($params['item']??0); $d=(int)($params['d']??0);

            db()->prepare("INSERT INTO user_items (user_id,item_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity")->execute([$id,$item]);

            db()->prepare("UPDATE user_items SET quantity = GREATEST(0, quantity + ?) WHERE user_id=? AND item_id=?")->execute([$d,$id,$item]);

            handleAdminNav($chatId,$messageId,'user_items',['id'=>$id],$userRow);

            break;

        case 'user_item_set':

            $id=(int)$params['id']; $item=(int)($params['item']??0); $page=(int)($params['page']??1);

            setAdminState($chatId,'await_user_item_set',['id'=>$id,'item'=>$item,'page'=>$page]);

            sendMessage($chatId,'عدد مقدار جدید آیتم را ارسال کنید (مثلاً 1000). برای حذف، 0 بفرستید.');

            break;

        case 'shop_factories':

            if (!hasPerm($chatId,'shop') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);

            $tot = db()->query("SELECT COUNT(*) c FROM factories")->fetch()['c']??0;

            $st = db()->prepare("SELECT id,name,price_l1,price_l2 FROM factories ORDER BY id DESC LIMIT ?,?"); $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

            $kb=[]; foreach($rows as $r){ $kb[]=[ ['text'=>e($r['name']).' | L1: '.formatPrice((int)$r['price_l1']).' | L2: '.formatPrice((int)$r['price_l2']), 'callback_data'=>'admin:shop_factory_view|id='.$r['id'].'|page='.$page] ]; }

            $kb[]=[ ['text'=>'افزودن کارخانه','callback_data'=>'admin:shop_factory_add'] ];

            foreach(paginationKeyboard('admin:shop_factories',$page, ($offset+count($rows))<$tot, 'admin:shop')['inline_keyboard'] as $row){ $kb[]=$row; }

            editMessageText($chatId,$messageId,'کارخانه‌های نظامی',['inline_keyboard'=>$kb]);

            break;

        case 'shop_factory_add':

            setAdminState($chatId,'await_factory_name',[]);

            sendGuide($chatId,'نام کارخانه را ارسال کنید. سپس قیمت لول ۱ و لول ۲ را در دو خط جدا بفرستید.');

            break;

        case 'shop_factory_view':

            $fid=(int)($params['id']??0); $page=(int)($params['page']??1);

            $f = db()->prepare("SELECT * FROM factories WHERE id=?"); $f->execute([$fid]); $fr=$f->fetch(); if(!$fr){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $prods = db()->prepare("SELECT fp.id, si.name, fp.qty_l1, fp.qty_l2 FROM factory_products fp JOIN shop_items si ON si.id=fp.item_id WHERE fp.factory_id=? ORDER BY si.name ASC"); $prods->execute([$fid]); $ps=$prods->fetchAll();

            $lines = ['کارخانه: '.e($fr['name']), 'قیمت لول ۱: '.formatPrice((int)$fr['price_l1']), 'قیمت لول ۲: '.formatPrice((int)$fr['price_l2']), '', 'محصولات:']; if(!$ps){ $lines[]='—'; }

            $kb=[]; foreach($ps as $p){ $lines[]='- '.e($p['name']).' | L1: '.$p['qty_l1'].' | L2: '.$p['qty_l2']; $kb[]=[ ['text'=>'حذف ' . e($p['name']), 'callback_data'=>'admin:shop_factory_prod_del|id='.$p['id'].'|fid='.$fid.'|page='.$page] ]; }

            $kb[]=[ ['text'=>'افزودن محصول','callback_data'=>'admin:shop_factory_prod_add|fid='.$fid] ];

            $kb[]=[ ['text'=>'حذف کارخانه','callback_data'=>'admin:shop_factory_del|id='.$fid.'|page='.$page] ];

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:shop_factories|page='.$page] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'shop_factory_del':

            $fid=(int)($params['id']??0); $page=(int)($params['page']??1);

            db()->prepare("DELETE FROM factories WHERE id=?")->execute([$fid]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');

            handleAdminNav($chatId,$messageId,'shop_factories',['page'=>$page],$userRow);

            break;

        case 'shop_factory_prod_add':

            $fid=(int)($params['fid']??0);

            // List items to pick

            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);

            $tot = db()->query("SELECT COUNT(*) c FROM shop_items")->fetch()['c']??0;

            $st = db()->prepare("SELECT id,name FROM shop_items ORDER BY name ASC LIMIT ?,?"); $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

            $kb=[]; foreach($rows as $r){ $kb[]=[ ['text'=>e($r['name']), 'callback_data'=>'admin:shop_factory_prod_pick|fid='.$fid.'|item='.$r['id'].'|page='.$page] ]; }

            foreach(paginationKeyboard('admin:shop_factory_prod_add|fid='.$fid,$page, ($offset+count($rows))<$tot, 'admin:shop_factory_view|id='.$fid)['inline_keyboard'] as $row){ $kb[]=$row; }

            editMessageText($chatId,$messageId,'انتخاب آیتم برای محصول کارخانه',['inline_keyboard'=>$kb]);

            break;

        case 'shop_factory_prod_pick':

            $fid=(int)($params['fid']??0); $item=(int)($params['item']??0); setAdminState($chatId,'await_factory_prod_qty',['fid'=>$fid,'item'=>$item]);

            sendMessage($chatId,'مقادیر تولید روزانه را در دو خط بفرستید: خط اول لول ۱، خط دوم لول ۲ (مثلاً 5\n10).');

            break;

        case 'shop_factory_prod_del':

            $id=(int)($params['id']??0); $fid=(int)($params['fid']??0); $page=(int)($params['page']??1);

            db()->prepare("DELETE FROM factory_products WHERE id=?")->execute([$id]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'حذف شد');

            handleAdminNav($chatId,$messageId,'shop_factory_view',['id'=>$fid,'page'=>$page],$userRow);

            break;

        case 'disc_list':

            $page=(int)($params['page']??1); $per=10; $off=($page-1)*$per;

            $tot = db()->query("SELECT COUNT(*) c FROM discount_codes")->fetch()['c']??0;

            $st = db()->prepare("SELECT id, code, percent, max_uses, used_count, per_user_limit, expires_at, disabled, shop_type FROM discount_codes ORDER BY id DESC LIMIT ?,?"); $st->bindValue(1,$off,PDO::PARAM_INT); $st->bindValue(2,$per,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

            $kb=[]; foreach($rows as $r){ 
                $shopTypeText = ['regular'=>'عادی','vip'=>'VIP','both'=>'هر دو'][$r['shop_type']] ?? $r['shop_type'];
                $label = ((int)$r['disabled']?'[خاموش] ':'').$r['code'].' | '.$r['percent'].'% | '.$r['used_count'].'/'.$r['max_uses'].' | هرکاربر: '.$r['per_user_limit'].' | تا: '.($r['expires_at']?:'∞').' | '.$shopTypeText; 
                $kb[]=[ ['text'=>$label, 'callback_data'=>'admin:disc_view|id='.$r['id'].'|page='.$page] ]; 
            }

            $kb[]=[ ['text'=>'افزودن کد جدید','callback_data'=>'admin:disc_add'] ];

            foreach(paginationKeyboard('admin:disc_list',$page, ($off+count($rows))<$tot, 'admin:shop')['inline_keyboard'] as $row){ $kb[]=$row; }

            editMessageText($chatId,$messageId,'کدهای تخفیف',['inline_keyboard'=>$kb]);

            break;

        case 'disc_add':

            setAdminState($chatId,'await_disc_new',[]);

            sendMessage($chatId,"فرمت را در 6 خط بفرستید:\n1) کد یا random\n2) درصد تخفیف (1 تا 100)\n3) سقف کل استفاده (0 = نامحدود)\n4) سقف هر کاربر (>=1)\n5) تاریخ انقضا به صورت YYYY-MM-DD HH:MM یا خالی بگذارید\n6) نوع فروشگاه: regular, vip, both");

            break;

        case 'disc_view':

            $id=(int)($params['id']??0); $page=(int)($params['page']??1);

            $r = db()->prepare("SELECT * FROM discount_codes WHERE id=?"); $r->execute([$id]); $dc=$r->fetch(); if(!$dc){ answerCallback($_POST['callback_query']['id']??'','یافت نشد',true); return; }

            $lines=['کد: '.$dc['code'],'درصد: '.$dc['percent'],'مصرف: '.$dc['used_count'].'/'.$dc['max_uses'],'هرکاربر: '.$dc['per_user_limit'],'انقضا: '.($dc['expires_at']?:'∞'),'وضعیت: '.((int)$dc['disabled']?'خاموش':'روشن')];

            $kb=[ [ ['text'=>$dc['disabled']?'روشن کردن':'خاموش کردن','callback_data'=>'admin:disc_toggle|id='.$id.'|page='.$page], ['text'=>'ویرایش','callback_data'=>'admin:disc_edit|id='.$id.'|page='.$page] ], [ ['text'=>'حذف','callback_data'=>'admin:disc_del|id='.$id.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:disc_list|page='.$page] ] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'disc_toggle':

            $id=(int)($params['id']??0); db()->prepare("UPDATE discount_codes SET disabled=1-disabled WHERE id=?")->execute([$id]); handleAdminNav($chatId,$messageId,'disc_view',['id'=>$id],$userRow); break;

        case 'disc_del':

            $id=(int)($params['id']??0); db()->prepare("DELETE FROM discount_codes WHERE id=?")->execute([$id]); answerCallback($_POST['callback_query']['id']??'','حذف شد'); handleAdminNav($chatId,$messageId,'disc_list',[],$userRow); break;

        case 'disc_edit':

            $id=(int)($params['id']??0); setAdminState($chatId,'await_disc_edit',['id'=>$id]);

            sendMessage($chatId,"ویرایش اختیاری در 4 خط (هر خط یکی از این موارد است؛ اگر نمی‌خواهید تغییر کند خالی بگذارید):\n1) درصد تخفیف (1 تا 100)\n2) سقف کل استفاده (0 = نامحدود)\n3) سقف هر کاربر (>=1)\n4) تاریخ انقضا (YYYY-MM-DD HH:MM) یا خالی");

            break;

        case 'vip_shop':
            if (!hasPerm($chatId,'shop') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }
            $enabled = (int)(getSetting('vip_shop_enabled', '0') ?: 0);
            $status = $enabled ? 'فعال' : 'غیرفعال';
            $kb = [
                [ ['text'=>$enabled?'غیرفعال کردن':'فعال کردن','callback_data'=>'admin:vip_shop_toggle'] ],
                [ ['text'=>'دسته‌بندی‌ها','callback_data'=>'admin:vip_shop_cats|page=1'] ],
                [ ['text'=>'درخواست‌های کارخانه','callback_data'=>'admin:factory_requests|page=1'] ],
                [ ['text'=>'بازگشت','callback_data'=>'admin:shop'] ]
            ];
            editMessageText($chatId,$messageId,'VIP Shop (وضعیت: '.$status.')',['inline_keyboard'=>$kb]);
            break;

        case 'vip_shop_toggle':
            $enabled = (int)(getSetting('vip_shop_enabled', '0') ?: 0);
            setSetting('vip_shop_enabled', $enabled ? '0' : '1');
            handleAdminNav($chatId,$messageId,'vip_shop',[],$userRow);
            break;

        case 'vip_shop_cats':
            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);
            $tot = db()->query("SELECT COUNT(*) c FROM vip_shop_categories")->fetch()['c']??0;
            $st = db()->prepare("SELECT id,name,type,sort_order FROM vip_shop_categories ORDER BY sort_order ASC, name ASC LIMIT ?,?"); 
            $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
            $kb=[]; foreach($rows as $r){ $kb[]=[ ['text'=>$r['sort_order'].' - '.$r['name'].' ('.$r['type'].')','callback_data'=>'admin:vip_shop_cat_view|id='.$r['id'].'|page='.$page] ]; }
            $kb[]=[ ['text'=>'افزودن دسته آیتم','callback_data'=>'admin:vip_shop_cat_add|type=item'] ];
            foreach(paginationKeyboard('admin:vip_shop_cats',$page, ($offset+count($rows))<$tot, 'admin:vip_shop')['inline_keyboard'] as $row){ $kb[]=$row; }
            editMessageText($chatId,$messageId,'دسته‌بندی‌های VIP Shop',['inline_keyboard'=>$kb]);
            break;

        case 'vip_shop_cat_add':
            $type = $params['type'] ?? 'item';
            setAdminState($chatId,'await_vip_shop_cat_name',['type'=>$type]);
            sendMessage($chatId,'نام دسته‌بندی VIP Shop ('.$type.') را ارسال کنید.');
            break;

        case 'info_users':

            if (!hasPerm($chatId,'user_info') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $total = db()->query("SELECT COUNT(*) c FROM users WHERE is_registered=1")->fetch()['c']??0;

            $stmt = db()->prepare("SELECT id, telegram_id, username, country, created_at FROM users WHERE is_registered=1 ORDER BY id DESC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = ($r['username']?'@'.$r['username']:$r['telegram_id']).' | '.e($r['country']).' | '.iranDateTime($r['created_at']); $kbRows[]=[ ['text'=>$label, 'callback_data'=>'admin:info_user_view|id='.$r['id'].'|page='.$page] ]; }

            $backCb = 'admin:close_panel'; if (!empty($params['close'])) { $backCb = 'admin:close_panel'; }

            $kb = array_merge($kbRows, paginationKeyboard('admin:info_users', $page, ($offset+count($rows))<$total, $backCb)['inline_keyboard']);

            if (!empty($messageId)) deleteMessage($chatId,$messageId);

            sendMessage($chatId,'کاربران ثبت‌شده (برای مشاهده اطلاعات کلیک کنید)',['inline_keyboard'=>$kb]);

            break;

        case 'info_user_view':

            if (!hasPerm($chatId,'user_info') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $id=(int)$params['id']; $page=(int)($params['page']??1);

            $u = db()->prepare("SELECT id, telegram_id, username, first_name, last_name, country, created_at, assets_text, money, daily_profit FROM users WHERE id=?"); $u->execute([$id]); $ur=$u->fetch(); if(!$ur){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }

            $counts = db()->prepare("SELECT type, COUNT(*) c FROM submissions WHERE user_id=? GROUP BY type"); $counts->execute([$id]); $map=[]; foreach($counts->fetchAll() as $r){ $map[$r['type']] = (int)$r['c']; }

            $fullName = trim(($ur['first_name']?:'').' '.($ur['last_name']?:''));

            $lines = [

                'یوزرنیم: '.($ur['username']?'@'.$ur['username']:'—'),

                'ID: '.$ur['telegram_id'],

                'نام: '.($fullName?:'—'),

                'کشور: '.($ur['country']?:'—'),

                'تاریخ عضویت: '.iranDateTime($ur['created_at']),

                'پول: '.$ur['money'].' | سود روزانه: '.$ur['daily_profit'],

                '',

                'تعداد پیام‌ها:',

                'پشتیبانی: ' . ((int)(db()->prepare("SELECT COUNT(*) c FROM support_messages WHERE user_id=?")->execute([$id]) || true) ? (int)(db()->query("SELECT COUNT(*) c FROM support_messages WHERE user_id={$id}")->fetch()['c']??0) : 0),

                'رول    : '.($map['role']??0), 'حمله موشکی: '.($map['missile']??0), 'دفاع: '.($map['defense']??0), 'بیانیه: '.($map['statement']??0), 'اعلام جنگ: '.($map['war']??0), 'لشکرکشی: '.($map['army']??0)

            ];

            $kb = [

                [ ['text'=>'پیام‌های پشتیبانی','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=support|page=1'] ],

                [ ['text'=>'رول‌ها','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=role|page=1'], ['text'=>'حمله موشکی','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=missile|page=1'] ],

                [ ['text'=>'دفاع','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=defense|page=1'], ['text'=>'بیانیه','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=statement|page=1'] ],

                [ ['text'=>'اعلام جنگ','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=war|page=1'], ['text'=>'لشکرکشی','callback_data'=>'admin:info_user_msgs|id='.$id.'|cat=army|page=1'] ],

                [ ['text'=>'دارایی‌ها','callback_data'=>'admin:info_user_assets|id='.$id] ],

                [ ['text'=>'بازگشت','callback_data'=>'admin:info_users|page='.$page.'|close=1'] ]

            ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            break;

        case 'info_user_msgs':

            if (!hasPerm($chatId,'user_info') && !in_array('all', getAdminPermissions($chatId), true)) { answerCallback($_POST['callback_query']['id'] ?? '', 'دسترسی ندارید', true); return; }

            $id=(int)$params['id']; $cat=$params['cat']??'support'; $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $labelsMap = ['role'=>'رول‌ها','missile'=>'حمله موشکی','defense'=>'دفاع','statement'=>'بیانیه','war'=>'اعلام جنگ','army'=>'لشکرکشی'];

            if ($cat==='support') {

                $total = db()->prepare("SELECT COUNT(*) c FROM support_messages WHERE user_id=?"); $total->execute([$id]); $ttl=(int)($total->fetch()['c']??0);

                $st = db()->prepare("SELECT id, created_at, text FROM support_messages WHERE user_id=? ORDER BY created_at DESC LIMIT ?,?"); $st->bindValue(1,$id,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->bindValue(3,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

                $kbRows=[]; foreach($rows as $r){ $label = iranDateTime($r['created_at']).' | '.mb_substr($r['text']?:'—',0,32); $kbRows[]=[ ['text'=>$label,'callback_data'=>'admin:info_user_support_view|uid='.$id.'|sid='.$r['id'].'|page='.$page] ]; }

                $kb = array_merge($kbRows, paginationKeyboard('admin:info_user_msgs|id='.$id.'|cat='.$cat, $page, ($offset+count($rows))<$ttl, 'admin:info_user_view|id='.$id)['inline_keyboard']);

                editMessageText($chatId,$messageId,'پیام‌های پشتیبانی',['inline_keyboard'=>$kb]);

            } else {

                $total = db()->prepare("SELECT COUNT(*) c FROM submissions WHERE user_id=? AND type=?"); $total->execute([$id,$cat]); $ttl=(int)($total->fetch()['c']??0);

                $st = db()->prepare("SELECT id, created_at, text FROM submissions WHERE user_id=? AND type=? ORDER BY created_at DESC LIMIT ?,?"); $st->bindValue(1,$id,PDO::PARAM_INT); $st->bindValue(2,$cat); $st->bindValue(3,$offset,PDO::PARAM_INT); $st->bindValue(4,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();

                $kbRows=[]; foreach($rows as $r){ $label = iranDateTime($r['created_at']).' | '.mb_substr($r['text']?:'—',0,32); $kbRows[]=[ ['text'=>$label,'callback_data'=>'admin:info_user_subm_view|uid='.$id.'|sid='.$r['id'].'|page='.$page.'|cat='.$cat] ]; }

                $kb = array_merge($kbRows, paginationKeyboard('admin:info_user_msgs|id='.$id.'|cat='.$cat, $page, ($offset+count($rows))<$ttl, 'admin:info_user_view|id='.$id)['inline_keyboard']);

                $title = $labelsMap[$cat] ?? 'پیام‌ها';

                editMessageText($chatId,$messageId,$title,['inline_keyboard'=>$kb]);

            }

            break;

        case 'info_user_support_view':

            $uid=(int)$params['uid']; $sid=(int)$params['sid']; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT id, text, photo_file_id, created_at FROM support_messages WHERE id=? AND user_id=?"); $stmt->execute([$sid,$uid]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','یافت نشد',true); return; }

            $kb=[ [ ['text'=>'بازگشت','callback_data'=>'admin:info_user_msgs|id='.$uid.'|cat=support|page='.$page] ] ];

            $body = iranDateTime($r['created_at'])."\n\n".($r['text']?e($r['text']):'—');

            deleteMessage($chatId,$messageId);

            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]); else sendMessage($chatId,$body, ['inline_keyboard'=>$kb]);

            break;

        case 'info_user_subm_view':

            $uid=(int)$params['uid']; $sid=(int)$params['sid']; $cat=$params['cat']??''; $page=(int)($params['page']??1);

            $stmt = db()->prepare("SELECT id, text, photo_file_id, created_at FROM submissions WHERE id=? AND user_id=?"); $stmt->execute([$sid,$uid]); $r=$stmt->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','یافت نشد',true); return; }

            $kb=[ [ ['text'=>'بازگشت','callback_data'=>'admin:info_user_msgs|id='.$uid.'|cat='.$cat.'|page='.$page] ] ];

            $body = iranDateTime($r['created_at'])."\n\n".($r['text']?e($r['text']):'—');

            deleteMessage($chatId,$messageId);

            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body, ['inline_keyboard'=>$kb]); else sendMessage($chatId,$body, ['inline_keyboard'=>$kb]);

            break;

        case 'info_user_assets':

            $id=(int)$params['id'];

            $stmtU = db()->prepare("SELECT assets_text, money, daily_profit, id, country FROM users WHERE id=?");

            $stmtU->execute([$id]); $ur = $stmtU->fetch(); if(!$ur){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }

            $content = $ur['assets_text'] ?: '';

            $lines = [];

            $cats = db()->query("SELECT id,name FROM shop_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

            foreach($cats as $c){

                $st = db()->prepare("SELECT si.name, ui.quantity FROM user_items ui JOIN shop_items si ON si.id=ui.item_id WHERE ui.user_id=? AND si.category_id=? AND ui.quantity>0 ORDER BY si.name ASC");

                $st->execute([(int)$ur['id'], (int)$c['id']]); $items=$st->fetchAll();

                if ($items){ $lines[] = $c['name']; foreach($items as $it){ $lines[] = e($it['name']).' : '.$it['quantity']; } $lines[]=''; }

            }

            if ($lines) { $content = trim($content) . "\n\n" . implode("\n", array_filter($lines)); }

            $wallet = "\n\nپول: ".$ur['money']." | سود روزانه: ".$ur['daily_profit'];

            editMessageText($chatId,$messageId,'دارایی‌های کاربر (' . e($ur['country']) . "):\n\n" . e($content) . $wallet, backButton('admin:info_user_view|id='.$id));

            break;

        case 'close_panel':

            if (!empty($messageId)) deleteMessage($chatId, $messageId);

            break;

        default:

            sendMessage($chatId,'حالت ناشناخته'); clearAdminState($chatId);

    }

}



function renderAdminPermsEditor(int $chatId, int $messageId, int $adminTid): void {

    $row = db()->prepare("SELECT is_owner, permissions FROM admin_users WHERE admin_telegram_id=?");

    $row->execute([$adminTid]); $r=$row->fetch(); if(!$r){ editMessageText($chatId,$messageId,'ادمین پیدا نشد', backButton('admin:admins')); return; }

    if ((int)$r['is_owner']===1) { editMessageText($chatId,$messageId,'این اکانت Owner است.', backButton('admin:admins')); return; }

    $allPerms = ['support','army','missile','defense','statement','war','roles','assets','shop','settings','wheel','users','bans','alliances','admins','user_info'];

    $labels = [

        'support'=>'پشتیبانی', 'army'=>'لشکرکشی', 'missile'=>'حمله موشکی', 'defense'=>'دفاع',

        'statement'=>'بیانیه', 'war'=>'اعلام جنگ', 'roles'=>'رول‌ها', 'assets'=>'دارایی‌ها', 'shop'=>'فروشگاه',

        'settings'=>'تنظیمات', 'wheel'=>'گردونه شانس', 'users'=>'کاربران', 'bans'=>'بن‌ها', 'alliances'=>'اتحادها', 'admins'=>'ادمین‌ها', 'user_info'=>'اطلاعات کاربران'

    ];

    $cur = $r['permissions'] ? (json_decode($r['permissions'], true) ?: []) : [];

    $kb=[]; foreach($allPerms as $p){ $on = in_array($p,$cur,true); $label = $labels[$p] ?? $p; $kb[]=[ ['text'=>($on?'✅ ':'⬜️ ').$label, 'callback_data'=>'admin:adm_toggle|id='.$adminTid.'|perm='.$p] ]; }

    $kb[]=[ ['text'=>'حذف ادمین','callback_data'=>'admin:adm_delete|id='.$adminTid] ];

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

    renderAllianceView($chatId, $messageId, (int)$a['id'], $isLeader, false);

}



function isAllianceLeader(int $tgId, int $allianceId): bool {

    $stmt = db()->prepare("SELECT 1 FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=? AND u.telegram_id=?");

    $stmt->execute([$allianceId,$tgId]); return (bool)$stmt->fetch();

}



function isAllianceMember(int $tgId, int $allianceId): bool {

    $stmt = db()->prepare("SELECT 1 FROM alliance_members m JOIN users u ON u.id=m.user_id WHERE m.alliance_id=? AND u.telegram_id=?");

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

    $isMember = isAllianceMember($chatId, $allianceId);

    if ($isLeader) {

        $kb[] = [ ['text'=>'دعوت عضو','callback_data'=>'alli:invite|id='.$allianceId] ];

        $kb[] = [ ['text'=>'ویرایش شعار','callback_data'=>'alli:editslogan|id='.$allianceId] ];

        $kb[] = [ ['text'=>'ویرایش نام اتحاد','callback_data'=>'alli:editname|id='.$allianceId] ];

        $kb[] = [ ['text'=>'ویرایش نام اعضا','callback_data'=>'alli:editmembers|id='.$allianceId] ];

        $kb[] = [ ['text'=>'تنظیم بنر اتحاد','callback_data'=>'alli:setbanner|id='.$allianceId] ];

        $kb[] = [ ['text'=>'حذف/انحلال اتحاد','callback_data'=>'alli:delete|id='.$allianceId] ];

    } elseif ($isMember) {

        $kb[] = [ ['text'=>'ترک اتحاد','callback_data'=>'alli:leave|id='.$allianceId] ];

    }

    $kb[] = [ ['text'=>'لیست اتحادها','callback_data'=>'alli:list|page=1'] ];

    $kb[] = [ ['text'=>'بازگشت به منو', 'callback_data'=>'nav:home'] ];

    $kb = widenKeyboard(['inline_keyboard'=>$kb]);

    if (!empty($a['banner_file_id'])) {

        deleteMessage($chatId, $messageId);

        $resp = sendPhoto($chatId, $a['banner_file_id'], $text, $kb); if ($resp && ($resp['ok']??false)) setHeaderPhoto($chatId, (int)($resp['result']['message_id']??0));

    } else {

        editMessageText($chatId,$messageId,$text,$kb);

    }

}



function getItemSelectionKeyboard(int $userId): array {
    $items = db()->prepare("SELECT ui.item_id, si.name, ui.quantity FROM user_items ui JOIN shop_items si ON si.id=ui.item_id WHERE ui.user_id=? AND ui.quantity>0 ORDER BY si.name ASC");
    $items->execute([$userId]); $rows=$items->fetchAll();
    $kb=[];
    foreach($rows as $r){
        $kb[]=[ ['text'=>e($r['name']).' ('.$r['quantity'].')','callback_data'=>'select_item:'.$r['item_id']] ];
    }
    return ['inline_keyboard'=>$kb];
}

function handleTradeNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {
    $uid = (int)$userRow['id'];
    
    switch ($route) {
        case 'send':
            setUserState($chatId,'await_trade_receiver',[]);
            sendMessage($chatId,'آیدی عددی کاربر مورد نظر را ارسال کنید:');
            break;
            
        case 'received':
            $trades = db()->prepare("SELECT pt.id, pt.sender_id, pt.trade_type, pt.money_amount, pt.item_id, pt.item_quantity, pt.status, pt.created_at, u.username, u.telegram_id, si.name as item_name FROM player_trades pt JOIN users u ON u.id=pt.sender_id LEFT JOIN shop_items si ON si.id=pt.item_id WHERE pt.receiver_id=? ORDER BY pt.created_at DESC LIMIT 20");
            $trades->execute([$uid]); $ts=$trades->fetchAll();
            if (!$ts) { 
                editMessageText($chatId,$messageId,'درخواست تجارتی دریافت نکرده‌اید.', ['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'nav:trade']]]] ); 
                return; 
            }
            $kb=[]; $lines=['درخواست‌های دریافتی:'];
            foreach($ts as $t){ 
                $statusText = ['pending'=>'در انتظار','accepted'=>'پذیرفته شده','rejected'=>'رد شده','admin_approved'=>'تایید شده','admin_rejected'=>'رد شده توسط ادمین'][$t['status']] ?? $t['status'];
                $tradeText = '';
                if($t['trade_type']==='money' || $t['trade_type']==='both'){
                    $tradeText .= formatPrice($t['money_amount']).' پول';
                }
                if($t['trade_type']==='item' || $t['trade_type']==='both'){
                    if($tradeText) $tradeText .= ' + ';
                    $tradeText .= $t['item_quantity'].' '.e($t['item_name']);
                }
                $lines[]='- '.($t['username']?'@'.$t['username']:$t['telegram_id']).' | '.$tradeText.' | '.$statusText;
                if($t['status']==='pending'){
                    $kb[]=[ ['text'=>'تایید','callback_data'=>'trade:accept|id='.$t['id']], ['text'=>'رد','callback_data'=>'trade:reject|id='.$t['id']] ];
                }
            }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'nav:trade'] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;
            
        case 'sent':
            $trades = db()->prepare("SELECT pt.id, pt.receiver_id, pt.trade_type, pt.money_amount, pt.item_id, pt.item_quantity, pt.status, pt.created_at, u.username, u.telegram_id, si.name as item_name FROM player_trades pt JOIN users u ON u.id=pt.receiver_id LEFT JOIN shop_items si ON si.id=pt.item_id WHERE pt.sender_id=? ORDER BY pt.created_at DESC LIMIT 20");
            $trades->execute([$uid]); $ts=$trades->fetchAll();
            if (!$ts) { 
                editMessageText($chatId,$messageId,'درخواست تجارتی ارسال نکرده‌اید.', ['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'nav:trade']]]] ); 
                return; 
            }
            $kb=[]; $lines=['درخواست‌های ارسالی:'];
            foreach($ts as $t){ 
                $statusText = ['pending'=>'در انتظار','accepted'=>'پذیرفته شده','rejected'=>'رد شده','admin_approved'=>'تایید شده','admin_rejected'=>'رد شده توسط ادمین'][$t['status']] ?? $t['status'];
                $tradeText = '';
                if($t['trade_type']==='money' || $t['trade_type']==='both'){
                    $tradeText .= formatPrice($t['money_amount']).' پول';
                }
                if($t['trade_type']==='item' || $t['trade_type']==='both'){
                    if($tradeText) $tradeText .= ' + ';
                    $tradeText .= $t['item_quantity'].' '.e($t['item_name']);
                }
                $lines[]='- '.($t['username']?'@'.$t['username']:$t['telegram_id']).' | '.$tradeText.' | '.$statusText;
            }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'nav:trade'] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;
            
        case 'accept':
            $tid=(int)($params['id']??0);
            $trade = db()->prepare("SELECT * FROM player_trades WHERE id=? AND receiver_id=? AND status='pending'"); 
            $trade->execute([$tid,$uid]); $t=$trade->fetch();
            if(!$t){ answerCallback($_POST['callback_query']['id']??'','درخواست یافت نشد',true); return; }
            
            db()->prepare("UPDATE player_trades SET status='accepted' WHERE id=?")->execute([$tid]);
            $sender = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); 
            $sender->execute([$t['sender_id']]); $s=$sender->fetch();
            if($s){
                sendMessage($s['telegram_id'],'کاربر معامله را تایید کرد. منتظر تایید ادمین باشید.');
            }
            sendMessage($chatId,'شما معامله را تایید کردید. منتظر تایید ادمین باشید.');
            break;
            
        case 'reject':
            $tid=(int)($params['id']??0);
            $trade = db()->prepare("SELECT * FROM player_trades WHERE id=? AND receiver_id=? AND status='pending'"); 
            $trade->execute([$tid,$uid]); $t=$trade->fetch();
            if(!$t){ answerCallback($_POST['callback_query']['id']??'','درخواست یافت نشد',true); return; }
            
            db()->prepare("UPDATE player_trades SET status='rejected' WHERE id=?")->execute([$tid]);
            $sender = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); 
            $sender->execute([$t['sender_id']]); $s=$sender->fetch();
            if($s){
                sendMessage($s['telegram_id'],'کاربر معامله را رد کرد.');
            }
            sendMessage($chatId,'معامله رد شد.');
            break;
            
        default:
            answerCallback($_POST['callback_query']['id']??'','دستور ناشناخته',true);
    }
}

function handleAllianceNav(int $chatId, int $messageId, string $route, array $params, array $userRow): void {

    clearHeaderPhoto($chatId, $messageId);

    switch ($route) {

        case 'new':

            setUserState($chatId,'await_alliance_name',[]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'نام اتحاد را ارسال کنید');

            sendGuide($chatId,'برای ساخت اتحاد، یک نام ارسال کنید.');

            break;

        case 'list':

            $page=(int)($params['page']??1); $perPage=10; [$offset,$limit]=paginate($page,$perPage);

            $total = db()->query("SELECT COUNT(*) c FROM alliances")->fetch()['c']??0;

            $stmt = db()->prepare("SELECT a.id, a.name, u.username, u.telegram_id, u.country FROM alliances a JOIN users u ON u.id=a.leader_user_id ORDER BY a.created_at DESC LIMIT ?,?");

            $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->bindValue(2,$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();

            $kbRows=[]; foreach($rows as $r){ $label = e($r['name']).' | رهبر: '.e($r['country']).' - '.($r['username']?'@'.$r['username']:$r['telegram_id']); $kbRows[]=[ ['text'=>$label,'callback_data'=>'alli:view|id='.$r['id']] ]; }

            $hasMore = ($offset + count($rows)) < $total;

            $kb = $kbRows;

            $nav=[]; if ($page>1) $nav[]=['text'=>'قبلی','callback_data'=>'alli:list|page='.($page-1)]; if ($hasMore) $nav[]=['text'=>'بعدی','callback_data'=>'alli:list|page='.($page+1)]; if ($nav) $kb[]=$nav;

            $kb[]=[ ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            deleteMessage($chatId, $messageId);

            setSetting('header_msg_'.$chatId, '');

            sendMessage($chatId,'لیست اتحادها',['inline_keyboard'=>$kb]);

            break;

        case 'view':

            $id=(int)$params['id'];

            $stmt=db()->prepare("SELECT a.*, u.telegram_id AS leader_tid FROM alliances a JOIN users u ON u.id=a.leader_user_id WHERE a.id=?"); $stmt->execute([$id]); $a=$stmt->fetch(); if(!$a){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }

            $isLeader = isAllianceLeader($chatId, $id);

            renderAllianceView($chatId, $messageId, $id, $isLeader, false);

            break;

        case 'invite':

            $id=(int)$params['id']; setUserState($chatId,'await_invite_ident',['alliance_id'=>$id]);

            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');

            sendGuide($chatId,'آیدی عددی یا پیام فوروارد عضو را ارسال کنید تا دعوت شود.');

            break;

        case 'editslogan':

            $id=(int)$params['id']; setUserState($chatId,'await_slogan',['alliance_id'=>$id]); answerCallback($_POST['callback_query']['id'] ?? '', 'شعار جدید را ارسال کنید');

            sendGuide($chatId,'شعار جدید اتحاد را ارسال کنید.');

            break;

        case 'editname':

            $id=(int)$params['id']; setUserState($chatId,'await_alliance_rename',['alliance_id'=>$id]); answerCallback($_POST['callback_query']['id'] ?? '', 'نام جدید اتحاد را ارسال کنید');

            sendGuide($chatId,'نام جدید اتحاد را ارسال کنید.');

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

            sendGuide($chatId,'نام نمایشی جدید عضو را ارسال کنید.');

            break;

        case 'setbanner':

            $id=(int)$params['id']; if (!isAllianceLeader($chatId,$id)) { answerCallback($_POST['callback_query']['id'] ?? '', 'فقط رهبر', true); return; }

            setUserState($chatId,'await_alliance_banner',['alliance_id'=>$id]);

            sendGuide($chatId,'تصویر بنر اتحاد را به صورت عکس ارسال کنید.');

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

    applyDailyProfitsIfDue();
    applyUserCleanupIfDue();



    if ((int)$u['banned'] === 1) {

        sendMessage($chatId, 'شما از ربات بن هستید.');

        return;

    }



    // Maintenance block for non-admins

    if (!getAdminPermissions($chatId) && isMaintenanceEnabled()) {

        sendMessage($chatId, maintenanceMessage());

        return;

    }



    if (isset($message['text']) && trim($message['text']) === '/start') {

        clearUserState($chatId);

        handleStart($u);

        return;

    }



    if (isset($message['text']) && trim($message['text']) === '/info') {

        if (!getAdminPermissions($chatId) || (!in_array('all', getAdminPermissions($chatId), true) && !hasPerm($chatId,'user_info'))) {

            sendMessage($chatId,'دسترسی ندارید.'); return;

        }

        // open admin user-info list page 1

        handleAdminNav($chatId, $message['message_id'] ?? 0, 'info_users', ['page'=>1], $u);

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



    // If user is not registered, route any free text to support

    if ((int)$u['is_registered'] !== 1) {

        setUserState($chatId, 'await_support', []);

        sendMessage($chatId, 'فقط پشتیبانی در دسترس است. پیام خود را برای پشتیبانی ارسال کنید.', backButton('nav:home'));

        return;

    }



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

            if ($cost > 2147483647) $cost = 2147483647;

            $stmt = db()->prepare("UPDATE submissions SET status='cost_proposed', cost_amount=? WHERE id=?"); $stmt->execute([$cost,$id]);

            // Notify user with confirm buttons

            $r = db()->prepare("SELECT s.id, s.user_id, s.text, s.cost_amount, s.photo_file_id, u.telegram_id FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $r->execute([$id]); $row=$r->fetch();

            if ($row) {

                $kb = [ [ ['text'=>'دیدن رول','callback_data'=>'rolecost:view|id='.$id] ], [ ['text'=>'تایید','callback_data'=>'rolecost:accept|id='.$id], ['text'=>'رد','callback_data'=>'rolecost:reject|id='.$id] ] ];

                sendMessage((int)$row['telegram_id'], 'هزینه رول شما: ' . $cost . "\nآیا تایید می‌کنید؟", ['inline_keyboard'=>$kb]);

                sendMessage($chatId, 'هزینه درخواست شد.');

            }

            clearAdminState($chatId);

            break;

        case 'await_asset_text':

            $country = $data['country']; $content = $text ?: ($message['caption'] ?? '');

            $stmt = db()->prepare("INSERT INTO assets (country, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()"); $stmt->execute([$country, $content]);

            sendMessage($chatId, 'متن دارایی این کشور ثبت شد: ' . e($country));

            clearAdminState($chatId);

            break;

        case 'await_asset_user_text':

            $uid=(int)$data['id']; $page=(int)($data['page']??1);

            $content = $text ?: ($message['caption'] ?? '');

            db()->prepare("UPDATE users SET assets_text=? WHERE id=?")->execute([$content,$uid]);

            // Clean previous combined asset message

            $mm = getSetting('asset_msg_'.$chatId); if ($mm) { @deleteMessage($chatId, (int)$mm); setSetting('asset_msg_'.$chatId,''); }

            // delete the prompt message too

            if (!empty($message['message_id'])) { @deleteMessage($chatId, (int)$message['message_id']); }

            clearAdminState($chatId);

            // Re-render updated asset view

            handleAdminNav($chatId, 0, 'asset_user_view', ['id'=>$uid,'page'=>$page], ['telegram_id'=>$chatId]);

            break;

        case 'await_btn_rename':

            $key = $data['key']; $title = trim((string)$text);

            if ($title===''){ sendMessage($chatId,'نام نامعتبر'); return; }

            db()->prepare("UPDATE button_settings SET title=? WHERE `key`=?")->execute([$title,$key]);

            sendMessage($chatId,'نام دکمه تغییر کرد.'); clearAdminState($chatId);

            break;

        case 'await_disc_new':

            $lines = preg_split("/\r?\n/", trim((string)($text ?: ($message['caption'] ?? ''))));

            if (count($lines)<6){ sendMessage($chatId,'فرمت نادرست - 6 خط نیاز است'); return; }

            $code = trim($lines[0] ?? ''); if ($code==='') { sendMessage($chatId,'خط اول کد یا random'); return; }

            if (strtolower($code)==='random') { $code = strtoupper(bin2hex(random_bytes(3))); }

            $percent = (int)($lines[1] ?? 0); if ($percent<1||$percent>100){ sendMessage($chatId,'درصد نامعتبر'); return; }

            $maxUses = (int)($lines[2] ?? 0); $perUser = max(1,(int)($lines[3] ?? 1)); $expRaw = trim($lines[4] ?? ''); $expiresAt = $expRaw!==''? $expRaw : null;

            $shopType = trim($lines[5] ?? 'regular'); if (!in_array($shopType, ['regular','vip','both'])){ sendMessage($chatId,'نوع فروشگاه نامعتبر - regular, vip, both'); return; }

            db()->prepare("INSERT INTO discount_codes (code,percent,max_uses,per_user_limit,expires_at,shop_type,created_by) VALUES (?,?,?,?,?,?,?)")

              ->execute([$code,$percent,$maxUses,$perUser,$expiresAt,$shopType,$chatId]);

            $kb=[ [ ['text'=>'کپی کد','copy_text'=>['text'=>$code]] ] ];

            sendMessage($chatId,'کد ایجاد شد: '.$code, ['inline_keyboard'=>$kb]);

            clearAdminState($chatId);

            handleAdminNav($chatId,$message['message_id'] ?? 0,'disc_list',[],['telegram_id'=>$chatId]);

            break;

        case 'await_disc_edit':

            $id=(int)$data['id']; $raw=trim((string)($text ?: ($message['caption'] ?? '')));

            $parts = preg_split("/\r?\n/", $raw);

            $percent = strlen($parts[0]??'')? (int)$parts[0] : null;

            $maxUses = strlen($parts[1]??'')? (int)$parts[1] : null;

            $perUser = strlen($parts[2]??'')? (int)$parts[2] : null;

            $expiresAt = strlen($parts[3]??'')? ($parts[3]) : null;

            if ($percent!==null) db()->prepare("UPDATE discount_codes SET percent=? WHERE id=?")->execute([$percent,$id]);

            if ($maxUses!==null) db()->prepare("UPDATE discount_codes SET max_uses=? WHERE id=?")->execute([$maxUses,$id]);

            if ($perUser!==null) db()->prepare("UPDATE discount_codes SET per_user_limit=? WHERE id=?")->execute([$perUser,$id]);

            db()->prepare("UPDATE discount_codes SET expires_at=? WHERE id=?")->execute([$expiresAt,$id]);

            sendMessage($chatId,'به‌روزرسانی شد');

            clearAdminState($chatId);

            handleAdminNav($chatId,$message['message_id'] ?? 0,'disc_view',['id'=>$id],['telegram_id'=>$chatId]);

            break;

        case 'await_btn_days':

            $key = $data['key'] ?? '';

            $val = strtolower(trim((string)$text));

            if ($val === '') { sendMessage($chatId,'الگو نامعتبر'); return; }

            if ($val !== 'all') {

                if (!preg_match('/^(su|mo|tu|we|th|fr|sa)(,(su|mo|tu|we|th|fr|sa))*$/', $val)) { sendMessage($chatId,'فرمت روزها نامعتبر است. نمونه: mo,tu,we یا all'); return; }

            }

            db()->prepare("UPDATE button_settings SET days=? WHERE `key`=?")->execute([$val, $key]);

            sendMessage($chatId,'روزهای مجاز تنظیم شد.');

            clearAdminState($chatId);

            break;

        case 'await_btn_time':

            $key = $data['key'] ?? '';

            $val = trim((string)$text);

            if (!preg_match('/^(\\d{2}:\\d{2})-(\\d{2}:\\d{2})$/', $val, $m)) { sendMessage($chatId,'فرمت ساعت نامعتبر. نمونه: 09:00-22:00'); return; }

            $t1 = $m[1]; $t2 = $m[2];

            db()->prepare("UPDATE button_settings SET time_start=?, time_end=? WHERE `key`=?")->execute([$t1,$t2,$key]);

            sendMessage($chatId,'بازه ساعت تنظیم شد.');

            clearAdminState($chatId);

            break;

        case 'await_starter_money':

            $val = (string)trim((string)$text);

            if (!preg_match('/^-?\d+$/',$val)) { sendMessage($chatId,'فقط عدد معتبر وارد کنید.'); return; }

            setSetting('starter_default_money', (string)intval($val));

            sendMessage($chatId,'مقدار پول پیش‌فرض ذخیره شد.');

            clearAdminState($chatId);

            break;

        case 'await_starter_profit':

            $val = (string)trim((string)$text);

            if (!preg_match('/^-?\d+$/',$val)) { sendMessage($chatId,'فقط عدد معتبر وارد کنید.'); return; }

            setSetting('starter_default_daily_profit', (string)intval($val));

            sendMessage($chatId,'سود روزانه پیش‌فرض ذخیره شد.');

            clearAdminState($chatId);

            break;

        case 'await_cleanup_days':
            $val = (string)trim((string)$text);
            if (!preg_match('/^\d+$/',$val)) { sendMessage($chatId,'فقط عدد معتبر وارد کنید.'); return; }
            setSetting('user_cleanup_days', (string)intval($val));
            sendMessage($chatId,'مقدار روز برای خالی شدن خودکار ذخیره شد.');
            clearAdminState($chatId);
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

            db()->prepare("UPDATE users SET is_registered=1, country=?, money=COALESCE(money,0)+?, daily_profit=COALESCE(daily_profit,0)+? WHERE telegram_id=?")

                ->execute([$country,(int)(getSetting('starter_default_money','0')?:0),(int)(getSetting('starter_default_daily_profit','0')?:0),$tgid]);

            // refresh to ensure username is current

            $u = ensureUser(['id'=>$tgid]);

            sendMessage($chatId,'کاربر ثبت شد.');

            sendMessage($tgid,'ثبت شما تکمیل شد.');

            $header = '🚨 𝗪𝗼𝗿𝗹𝗱 𝗡𝗲𝘄𝘀 | اخبار جهانی 🚨';

            $uname = $u['username'] ? '@'.$u['username'] : '';

            $msg = $header."\n\n".e($country).' پر شد ✅' . "\n\n" . $uname;

            sendToChannel($msg);

            clearAdminState($chatId);

            break;

        case 'await_ban_ident':

            $tgid = extractTelegramIdFromMessage($message);

            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر.'); return; }

            // Protect Owner from ban

            if ($tgid === MAIN_ADMIN_ID) { sendMessage($chatId,'بن Owner مجاز نیست.'); clearAdminState($chatId); return; }

            // If target is an admin, only Owner can ban

            $adm = db()->prepare("SELECT is_owner FROM admin_users WHERE admin_telegram_id=?");

            $adm->execute([$tgid]);

            $admRow = $adm->fetch();

            if ($admRow) {

                if (!isOwner($chatId)) { sendMessage($chatId,'بن ادمین فقط توسط Owner مجاز است.'); clearAdminState($chatId); return; }

                if ((int)$admRow['is_owner'] === 1) { sendMessage($chatId,'بن Owner مجاز نیست.'); clearAdminState($chatId); return; }

            }

            db()->prepare("UPDATE users SET banned=1 WHERE telegram_id=?")->execute([$tgid]);

            sendMessage($chatId,'کاربر بن شد: '.$tgid);

            clearAdminState($chatId);

            break;

        case 'await_unban_ident':

            $tgid = extractTelegramIdFromMessage($message);

            if (!$tgid) { sendMessage($chatId,'آیدی نامعتبر.'); return; }

            // If target is an admin, only Owner can unban

            $adm = db()->prepare("SELECT is_owner FROM admin_users WHERE admin_telegram_id=?");

            $adm->execute([$tgid]);

            $admRow = $adm->fetch();

            if ($admRow && !isOwner($chatId)) { sendMessage($chatId,'حذف بن ادمین فقط توسط Owner مجاز است.'); clearAdminState($chatId); return; }

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

            // Confirm info

            $u = ensureUser(['id'=>$tgid]);

            $info = 'ادمین جدید ثبت شد:\n'

                  . 'یوزرنیم: ' . ($u['username']?'@'.$u['username']:'—') . "\n"

                  . 'ID: ' . $u['telegram_id'] . "\n"

                  . 'نام: ' . trim(($u['first_name']?:'').' '.($u['last_name']?:'')) . "\n"

                  . 'کشور: ' . ($u['country']?:'—') . "\n"

                  . 'ثبت‌شده: ' . ((int)$u['is_registered']===1?'بله':'خیر') . "\n"

                  . 'بن: ' . ((int)$u['banned']===1?'بله':'خیر') . "\n"

                  . 'زمان ایجاد: ' . iranDateTime($u['created_at']);

            sendMessage($chatId, $info);

            setAdminState($chatId,'await_admin_perms',['tgid'=>$tgid]);

            // render perms editor

            $fakeMsgId = $message['message_id'] ?? 0;

            renderAdminPermsEditor($chatId, $fakeMsgId, $tgid);

            break;

        case 'await_admin_perms':

            // handled via buttons (adm_toggle)

            break;

        case 'await_maint_msg':

            $msg = $text ?: ($message['caption'] ?? '');

            setSetting('maintenance_message', $msg);

            sendMessage($chatId,'پیام نگهداری ثبت شد.');

            clearAdminState($chatId);

            break;

        case 'await_support_reply':

            $supportId = (int)$data['support_id']; $page=(int)($data['page']??1);

            $replyText = $text ?: ($message['caption'] ?? '');

            $photo = null; if (!empty($message['photo'])) { $photos=$message['photo']; $largest=end($photos); $photo=$largest['file_id']??null; }

            $stmt = db()->prepare("SELECT sm.id, sm.text AS stext, sm.photo_file_id AS sphoto, u.telegram_id FROM support_messages sm JOIN users u ON u.id=sm.user_id WHERE sm.id=?"); $stmt->execute([$supportId]); $r=$stmt->fetch();

            if ($r) {

                // store reply only; do not send direct reply text

                db()->prepare("INSERT INTO support_replies (support_id, admin_id, text, photo_file_id) VALUES (?, ?, ?, ?)")->execute([$supportId, $chatId, $replyText ?: null, $photo]);

                $replyId = (int)db()->lastInsertId();

                // notify with view button only

                $kb=[ [ ['text'=>'دیدن پاسخ','callback_data'=>'sreply:view|sid='.$supportId.'|rid='.$replyId] ] ];

                sendMessage((int)$r['telegram_id'], 'ادمین به پیام شما پاسخ داد.', ['inline_keyboard'=>$kb]);

                sendMessage($chatId,'ارسال شد.');

            } else {

                sendMessage($chatId,'یافت نشد');

            }

            clearAdminState($chatId);

            break;

        case 'await_user_assets_text':

            $id=(int)$data['id']; $content = $text ?: ($message['caption'] ?? '');

            db()->prepare("UPDATE users SET assets_text=? WHERE id=?")->execute([$content, $id]);

            sendMessage($chatId,'متن دارایی ذخیره شد.');

            clearAdminState($chatId);

            break;

        case 'await_user_money':

            $id=(int)$data['id']; $val = (int)preg_replace('/\D+/', '', (string)$text);

            db()->prepare("UPDATE users SET money=? WHERE id=?")->execute([$val, $id]);

            sendMessage($chatId,'پول کاربر تنظیم شد: '.$val);

            clearAdminState($chatId);

            break;

        case 'await_user_profit':

            $id=(int)$data['id']; $val = (int)preg_replace('/\D+/', '', (string)$text);

            db()->prepare("UPDATE users SET daily_profit=? WHERE id=?")->execute([$val, $id]);

            sendMessage($chatId,'سود روزانه کاربر تنظیم شد: '.$val);

            clearAdminState($chatId);

            break;

        case 'await_user_mcoin':

            $id=(int)$data['id']; $val = (int)preg_replace('/\D+/', '', (string)$text);

            db()->prepare("UPDATE users SET m_coins=? WHERE id=?")->execute([$val, $id]);

            sendMessage($chatId,'M-Coin کاربر تنظیم شد: '.$val);

            clearAdminState($chatId);

            break;

        case 'await_user_delete_reason':

            $uid=(int)$data['id']; $page=(int)($data['page']??1);

            $reason = trim((string)($text ?: ($message['caption'] ?? '')));

            $row = db()->prepare("SELECT telegram_id, username, country FROM users WHERE id=?"); $row->execute([$uid]); $u=$row->fetch();

            // reset registration instead of hard delete

            db()->prepare("UPDATE users SET is_registered=0, country=NULL WHERE id=?")->execute([$uid]);

            sendMessage($chatId,'حذف شد.');

            // Channel notify

            $header = '🚨 𝗪𝗼𝗿𝗹𝗱 𝗡𝗲𝘄𝘀 | اخبار جهانی 🚨';

            $name = $u && $u['country'] ? $u['country'] : 'کشور';

            $uname = $u && $u['username'] ? ('@'.$u['username']) : '';

            $msg = $header."\n\n".e($name).' خالی شد ❌' . "\n\n" . $uname . "\n\n" . 'دلیل: ' . ($reason?:'—');

            sendToChannel($msg);

            clearAdminState($chatId);

            handleAdminNav($chatId,$message['message_id'] ?? 0,'user_list',['page'=>$page],['telegram_id'=>$chatId]);

            break;

        case 'await_country_flag':

            $country = $data['country'];

            $photo = null; if (!empty($message['photo'])) { $photos=$message['photo']; $largest=end($photos); $photo=$largest['file_id']??null; }

            if (!$photo) { sendMessage($chatId,'عکس ارسال کنید.'); return; }

            db()->prepare("INSERT INTO country_flags (country, photo_file_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE photo_file_id=VALUES(photo_file_id)")->execute([$country, $photo]);

            sendMessage($chatId,'پرچم برای '.e($country).' ثبت شد.');

            clearAdminState($chatId);

            break;

        case 'await_war_attacker':

            $sid=(int)$data['submission_id']; $page=(int)($data['page']??1);

            $attTid = extractTelegramIdFromMessage($message);

            if (!$attTid) { sendMessage($chatId,'آیدی نامعتبر. دوباره آیدی عددی حمله کننده را بفرستید.'); return; }

            setAdminState($chatId,'await_war_defender',['submission_id'=>$sid,'page'=>$page,'att_tid'=>$attTid]);

            sendMessage($chatId,'آیدی عددی دفاع کننده را ارسال کنید.');

            break;

        case 'await_war_defender':

            $sid=(int)$data['submission_id']; $page=(int)($data['page']??1); $attTid=(int)$data['att_tid'];

            $defTid = extractTelegramIdFromMessage($message);

            if (!$defTid) { sendMessage($chatId,'آیدی نامعتبر. دوباره آیدی عددی دفاع کننده را بفرستید.'); return; }

            // Show confirm with attacker/defender info

            $att = ensureUser(['id'=>$attTid]); $def = ensureUser(['id'=>$defTid]);

            $info = 'حمله کننده: '.($att['username']?'@'.$att['username']:$attTid).' | کشور: '.($att['country']?:'—')."\n".

                    'دفاع کننده: '.($def['username']?'@'.$def['username']:$defTid).' | کشور: '.($def['country']?:'—');

            $kb = [ [ ['text'=>'ارسال','callback_data'=>'admin:war_send_confirm|id='.$sid.'|att='.$attTid.'|def='.$defTid], ['text'=>'لغو','callback_data'=>'admin:sw_view|id='.$sid.'|type=war|page='.$page] ] ];

            sendMessage($chatId,$info,['inline_keyboard'=>$kb]);

            clearAdminState($chatId);

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

            // fetch alliance info

            $ainfo = db()->prepare("SELECT name FROM alliances WHERE id=?"); $ainfo->execute([$aid]); $ar=$ainfo->fetch(); $aname = $ar?$ar['name']:'اتحاد';

            $title = 'دعوت به اتحاد: '.e($aname)."\n".'کشور دعوت‌کننده: '.e($inviter['country']?:'—');

            $kb=[ [ ['text'=>'بله','callback_data'=>'alli_inv:accept|aid='.$aid], ['text'=>'خیر','callback_data'=>'alli_inv:reject|aid='.$aid] ] ];

            sendMessage((int)$invitee['telegram_id'], $title."\n\nشما به این اتحاد دعوت شدید. آیا می‌پذیرید؟", ['inline_keyboard'=>$kb]);

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

        case 'await_alliance_banner':

            $aid=(int)$data['alliance_id'];

            $photo = null; if (!empty($message['photo'])) { $photos=$message['photo']; $largest=end($photos); $photo=$largest['file_id']??null; }

            if (!$photo) { sendMessage($chatId,'عکس ارسال کنید.'); return; }

            db()->prepare("UPDATE alliances SET banner_file_id=? WHERE id=?")->execute([$photo,$aid]);

            sendMessage($chatId,'بنر اتحاد تنظیم شد.');

            clearUserState($chatId);

            break;

        case 'await_shop_cat_name':

            $name = trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }

            db()->prepare("INSERT INTO shop_categories (name, sort_order) VALUES (?, 0)")->execute([$name]);

            sendMessage($chatId,'ثبت شد. عدد ترتیب را ارسال کنید یا /skip بزنید.');

            setAdminState($chatId,'await_shop_cat_sort',['name'=>$name]);

            break;

        case 'await_shop_cat_sort':

            $sort = (int)preg_replace('/\D+/','',(string)$text);

            db()->prepare("UPDATE shop_categories SET sort_order=? WHERE name=?")->execute([$sort, $state['data']['name']]);

            sendMessage($chatId,'ترتیب ذخیره شد.'); clearAdminState($chatId);

            break;

        case 'await_shop_cat_edit':

            $cid=(int)$data['id'];

            $parts = preg_split('/\n+/', (string)$text);

            $name = trim($parts[0] ?? ''); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }

            $sort = isset($parts[1]) ? (int)preg_replace('/\D+/','',$parts[1]) : 0;

            db()->prepare("UPDATE shop_categories SET name=?, sort_order=? WHERE id=?")->execute([$name,$sort,$cid]);

            sendMessage($chatId,'ویرایش شد.'); clearAdminState($chatId);

            break;

        case 'await_shop_item_name':

            $cid=(int)$data['cid']; $name=trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }

            setAdminState($chatId,'await_shop_item_fields',['cid'=>$cid,'name'=>$name]);

            sendMessage($chatId,'به ترتیب در خطوط جدا قیمت واحد، اندازه بسته، محدودیت هر کاربر (۰=بی‌نهایت)، سود روزانه هر بسته را ارسال کنید.');

            break;

        case 'await_shop_item_fields':

            $cid=(int)$data['cid']; $name=$data['name'];

            $lines = preg_split('/\n+/', (string)$text);

            if (count($lines) < 4) { sendMessage($chatId,'فرمت نامعتبر. ۴ خط لازم است.'); return; }

            $price = (int)preg_replace('/\D+/','',$lines[0]);

            $pack = max(1,(int)preg_replace('/\D+/','',$lines[1]));

            $limit = (int)preg_replace('/\D+/','',$lines[2]);

            $profit = (int)preg_replace('/\D+/','',$lines[3]);

            db()->prepare("INSERT INTO shop_items (category_id,name,unit_price,pack_size,per_user_limit,daily_profit_per_pack) VALUES (?,?,?,?,?,?)")

              ->execute([$cid,$name,$price,$pack,$limit,$profit]);

            sendMessage($chatId,'آیتم اضافه شد.'); clearAdminState($chatId);

            break;

        case 'await_factory_name':

            $name = trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }

            setAdminState($chatId,'await_factory_prices',['name'=>$name]);

            sendMessage($chatId,'قیمت لول ۱ و سپس لول ۲ را در دو خط بفرستید.');

            break;

        case 'await_factory_prices':

            $name = (string)$data['name'];

            $parts = preg_split('/\n+/', (string)$text);

            if (count($parts) < 2) { sendMessage($chatId,'دو عدد در دو خط ارسال کنید.'); return; }

            $p1 = (int)preg_replace('/\D+/','',$parts[0]);

            $p2 = (int)preg_replace('/\D+/','',$parts[1]);

            db()->prepare("INSERT INTO factories (name, price_l1, price_l2) VALUES (?,?,?)")->execute([$name,$p1,$p2]);

            $fid = (int)db()->lastInsertId();

            sendMessage($chatId,'کارخانه ثبت شد. حالا می‌توانید محصول اضافه کنید.');

            clearAdminState($chatId);

            // show view

            $fakeMsgId = $message['message_id'] ?? 0;

            handleAdminNav($chatId, $fakeMsgId, 'shop_factory_view', ['id'=>$fid], ['telegram_id'=>$chatId]);

            break;

        case 'await_factory_prod_qty':

            $fid=(int)$data['fid']; $item=(int)$data['item'];

            $parts = preg_split('/\n+/', (string)$text);

            if (count($parts) < 2) { sendMessage($chatId,'دو عدد در دو خط ارسال کنید.'); return; }

            $q1 = (int)preg_replace('/\D+/','',$parts[0]); $q2 = (int)preg_replace('/\D+/','',$parts[1]);

            db()->prepare("INSERT INTO factory_products (factory_id,item_id,qty_l1,qty_l2) VALUES (?,?,?,?)")

              ->execute([$fid,$item,$q1,$q2]);

            sendMessage($chatId,'محصول اضافه شد.');

            clearAdminState($chatId);

            break;

        case 'await_user_item_set':

            $id=(int)$data['id']; $item=(int)$data['item']; $page=(int)($data['page']??1);

            $valRaw = trim((string)($text ?: ($message['caption'] ?? '')));

            if ($valRaw === '') { sendMessage($chatId,'یک عدد ارسال کنید.'); return; }

            $val = (int)preg_replace('/\D+/', '', $valRaw);

            // allow zero to clear

            db()->prepare("INSERT INTO user_items (user_id,item_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")->execute([$id,$item]);

            db()->prepare("UPDATE user_items SET quantity=? WHERE user_id=? AND item_id=?")->execute([$val,$id,$item]);

            sendMessage($chatId,'مقدار آیتم تنظیم شد: '.$val);

            clearAdminState($chatId);

            // refresh list

            handleAdminNav($chatId, $message['message_id'] ?? 0, 'user_items', ['id'=>$id,'page'=>$page], ['telegram_id'=>$chatId]);

            break;

        case 'await_vip_shop_cat_name':
            $type = $data['type'] ?? 'item';
            $name = trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            db()->prepare("INSERT INTO vip_shop_categories (name, type, sort_order) VALUES (?, ?, 0)")->execute([$name, $type]);
            sendMessage($chatId,'دسته‌بندی VIP Shop ثبت شد.');
            clearAdminState($chatId);
            break;

        case 'await_vip_shop_cat_edit':
            $cid=(int)$data['id'];
            $parts = preg_split('/\n+/', (string)$text);
            if (count($parts) < 2) { sendMessage($chatId,'نام و عدد ترتیب در دو خط ارسال کنید.'); return; }
            $name = trim($parts[0]); $order = (int)preg_replace('/\D+/','',$parts[1]);
            db()->prepare("UPDATE vip_shop_categories SET name=?, sort_order=? WHERE id=?")->execute([$name, $order, $cid]);
            sendMessage($chatId,'دسته ویرایش شد.');
            clearAdminState($chatId);
            break;

        case 'await_vip_shop_item_name':
            $cid=(int)$data['cid'];
            $name = trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            setAdminState($chatId,'await_vip_shop_item_price',['cid'=>$cid,'name'=>$name]);
            sendMessage($chatId,'قیمت M-Coin آیتم را ارسال کنید.');
            break;

        case 'await_vip_shop_item_price':
            $cid=(int)$data['cid']; $name=$data['name'];
            $price = (int)preg_replace('/\D+/','',(string)$text); if($price<=0){ sendMessage($chatId,'قیمت نامعتبر'); return; }
            setAdminState($chatId,'await_vip_shop_item_pack',['cid'=>$cid,'name'=>$name,'price'=>$price]);
            sendMessage($chatId,'تعداد بسته را ارسال کنید.');
            break;

        case 'await_vip_shop_item_pack':
            $cid=(int)$data['cid']; $name=$data['name']; $price=(int)$data['price'];
            $pack = (int)preg_replace('/\D+/','',(string)$text); if($pack<=0){ sendMessage($chatId,'تعداد نامعتبر'); return; }
            setAdminState($chatId,'await_vip_shop_item_limit',['cid'=>$cid,'name'=>$name,'price'=>$price,'pack'=>$pack]);
            sendMessage($chatId,'حد مجاز خرید برای هر کاربر را ارسال کنید (0 = نامحدود).');
            break;

        case 'await_vip_shop_item_limit':
            $cid=(int)$data['cid']; $name=$data['name']; $price=(int)$data['price']; $pack=(int)$data['pack'];
            $limit = (int)preg_replace('/\D+/','',(string)$text);
            setAdminState($chatId,'await_vip_shop_item_profit',['cid'=>$cid,'name'=>$name,'price'=>$price,'pack'=>$pack,'limit'=>$limit]);
            sendMessage($chatId,'سود روزانه برای هر بسته را ارسال کنید (0 = بدون سود).');
            break;

        case 'await_vip_shop_item_profit':
            $cid=(int)$data['cid']; $name=$data['name']; $price=(int)$data['price']; $pack=(int)$data['pack']; $limit=(int)$data['limit'];
            $profit = (int)preg_replace('/\D+/','',(string)$text);
            db()->prepare("INSERT INTO vip_shop_items (category_id,name,mcoin_price,pack_size,per_user_limit,daily_profit_per_pack,enabled) VALUES (?,?,?,?,?,?,1)")->execute([$cid,$name,$price,$pack,$limit,$profit]);
            sendMessage($chatId,'آیتم VIP ثبت شد.');
            clearAdminState($chatId);
            break;

        case 'await_custom_factory_name':
            $name = trim((string)$text); if($name===''){ sendMessage($chatId,'نام نامعتبر'); return; }
            setUserState($chatId,'await_custom_factory_product',['name'=>$name]);
            sendMessage($chatId,'نام محصول کارخانه را ارسال کنید:');
            break;

        case 'await_custom_factory_product':
            $name=$data['name']; $product = trim((string)$text); if($product===''){ sendMessage($chatId,'نام محصول نامعتبر'); return; }
            setUserState($chatId,'await_custom_factory_output',['name'=>$name,'product'=>$product]);
            sendMessage($chatId,'تعداد بازدهی روزانه کارخانه را ارسال کنید:');
            break;

        case 'await_custom_factory_output':
            $name=$data['name']; $product=$data['product']; $output = (int)preg_replace('/\D+/','',(string)$text); if($output<=0){ sendMessage($chatId,'تعداد نامعتبر'); return; }
            db()->prepare("INSERT INTO custom_factory_requests (user_id,factory_name,product_name,daily_output) VALUES (?,?,?,?)")->execute([$userId,$name,$product,$output]);
            sendMessage($chatId,'درخواست کارخانه ارسال شد. منتظر تایید ادمین باشید.');
            clearUserState($chatId);
            break;

        case 'await_trade_receiver':
            $receiverId = (int)preg_replace('/\D+/','',(string)$text); 
            if($receiverId<=0){ sendMessage($chatId,'آیدی نامعتبر'); return; }
            $receiver = db()->prepare("SELECT id, telegram_id, username FROM users WHERE telegram_id=? AND is_registered=1"); 
            $receiver->execute([$receiverId]); $r=$receiver->fetch();
            if(!$r){ sendMessage($chatId,'کاربر یافت نشد'); return; }
            if($r['id']===$userId){ sendMessage($chatId,'نمی‌توانید با خودتان تجارت کنید'); return; }
            setUserState($chatId,'await_trade_type',['receiver_id'=>$r['id'],'receiver_telegram_id'=>$r['telegram_id'],'receiver_username'=>$r['username']]);
            sendMessage($chatId,'نوع تجارت را انتخاب کنید:',['inline_keyboard'=>[
                [ ['text'=>'پول','callback_data'=>'trade_type:money'] ],
                [ ['text'=>'آیتم','callback_data'=>'trade_type:item'] ],
                [ ['text'=>'هر دو','callback_data'=>'trade_type:both'] ]
            ]]);
            break;

        case 'await_trade_money':
            $receiverId=$data['receiver_id']; $receiverTelegramId=$data['receiver_telegram_id']; $receiverUsername=$data['receiver_username']; $tradeType=$data['trade_type'];
            $amount = (int)preg_replace('/\D+/','',(string)$text); 
            if($amount<=0){ sendMessage($chatId,'مقدار نامعتبر'); return; }
            if($tradeType==='both'){
                setUserState($chatId,'await_trade_item',['receiver_id'=>$receiverId,'receiver_telegram_id'=>$receiverTelegramId,'receiver_username'=>$receiverUsername,'trade_type'=>$tradeType,'money_amount'=>$amount]);
                sendMessage($chatId,'آیتم مورد نظر را انتخاب کنید:',['inline_keyboard'=>getItemSelectionKeyboard($userId)]);
            }else{
                db()->prepare("INSERT INTO player_trades (sender_id,receiver_id,trade_type,money_amount) VALUES (?,?,?,?)")->execute([$userId,$receiverId,$tradeType,$amount]);
                sendMessage($receiverTelegramId,'درخواست تجارت با شما ارسال شد. آیا مایل هستید؟');
                sendMessage($chatId,'درخواست تجارت ارسال شد.');
                clearUserState($chatId);
            }
            break;

        case 'await_trade_item':
            $receiverId=$data['receiver_id']; $receiverTelegramId=$data['receiver_telegram_id']; $receiverUsername=$data['receiver_username']; $tradeType=$data['trade_type']; $moneyAmount=$data['money_amount']??0;
            $itemId = (int)preg_replace('/\D+/','',(string)$text); 
            if($itemId<=0){ sendMessage($chatId,'آیتم نامعتبر'); return; }
            setUserState($chatId,'await_trade_quantity',['receiver_id'=>$receiverId,'receiver_telegram_id'=>$receiverTelegramId,'receiver_username'=>$receiverUsername,'trade_type'=>$tradeType,'money_amount'=>$moneyAmount,'item_id'=>$itemId]);
            sendMessage($chatId,'تعداد آیتم را ارسال کنید:');
            break;

        case 'await_trade_quantity':
            $receiverId=$data['receiver_id']; $receiverTelegramId=$data['receiver_telegram_id']; $receiverUsername=$data['receiver_username']; $tradeType=$data['trade_type']; $moneyAmount=$data['money_amount']??0; $itemId=$data['item_id'];
            $quantity = (int)preg_replace('/\D+/','',(string)$text); 
            if($quantity<=0){ sendMessage($chatId,'تعداد نامعتبر'); return; }
            db()->prepare("INSERT INTO player_trades (sender_id,receiver_id,trade_type,money_amount,item_id,item_quantity) VALUES (?,?,?,?,?,?)")->execute([$userId,$receiverId,$tradeType,$moneyAmount,$itemId,$quantity]);
            sendMessage($receiverTelegramId,'درخواست تجارت با شما ارسال شد. آیا مایل هستید؟');
            sendMessage($chatId,'درخواست تجارت ارسال شد.');
            clearUserState($chatId);
            break;

        case 'await_factory_reject_reason':
            $rid=(int)$data['id'];
            $reason = trim((string)$text); if($reason===''){ sendMessage($chatId,'دلیل نامعتبر'); return; }
            db()->prepare("UPDATE custom_factory_requests SET status='rejected', admin_message=? WHERE id=?")->execute([$reason,$rid]);
            $req = db()->prepare("SELECT u.telegram_id FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id WHERE cfr.id=?"); 
            $req->execute([$rid]); $r=$req->fetch();
            if($r){
                sendMessage($r['telegram_id'],'درخواست کارخانه شما رد شد. دلیل: '.e($reason));
            }
            sendMessage($chatId,'درخواست رد شد.');
            clearAdminState($chatId);
            break;

        case 'await_factory_cost':
            $rid=(int)$data['id'];
            $parts = preg_split('/\n+/', (string)$text);
            if (count($parts) < 2) { sendMessage($chatId,'پول و M-Coin در دو خط ارسال کنید.'); return; }
            $money = (int)preg_replace('/\D+/','',$parts[0]);
            $mcoin = (int)preg_replace('/\D+/','',$parts[1]);
            if($money<=0 && $mcoin<=0){ sendMessage($chatId,'حداقل یکی از مقادیر باید بیشتر از صفر باشد'); return; }
            db()->prepare("UPDATE custom_factory_requests SET status='cost_set', admin_cost_money=?, admin_cost_mcoin=? WHERE id=?")->execute([$money,$mcoin,$rid]);
            $req = db()->prepare("SELECT cfr.*, u.telegram_id FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id WHERE cfr.id=?"); 
            $req->execute([$rid]); $r=$req->fetch();
            if($r){
                $costText = ($money>0 ? formatPrice($money).' پول' : '').($money>0 && $mcoin>0 ? ' + ' : '').($mcoin>0 ? $mcoin.' M-Coin' : '');
                sendMessage($r['telegram_id'],'هزینه کارخانه تعیین شد: '.$costText.'. آیا می‌پذیرید؟');
            }
            sendMessage($chatId,'هزینه تعیین شد.');
            clearAdminState($chatId);
            break;

        case 'vip_shop_cat_view':
            $cid=(int)($params['id']??0); $page=(int)($params['page']??1);
            $c = db()->prepare("SELECT id,name,type,sort_order FROM vip_shop_categories WHERE id=?"); $c->execute([$cid]); $cat=$c->fetch(); if(!$cat){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $items = db()->prepare("SELECT id,name,mcoin_price,pack_size,per_user_limit,daily_profit_per_pack,enabled FROM vip_shop_items WHERE category_id=? ORDER BY name ASC"); $items->execute([$cid]); $rows=$items->fetchAll();
            $lines = ['دسته: '.e($cat['name']).' ('.$cat['type'].')', ''];
            $kb=[]; foreach($rows as $r){ 
                $lbl = e($r['name']).' | '.formatPrice((int)$r['mcoin_price']).' M-Coin | بسته: '.$r['pack_size']; 
                if((int)$r['daily_profit_per_pack']>0){ $lbl.=' | سود: '.$r['daily_profit_per_pack']; } 
                if((int)$r['enabled']===0){ $lbl.=' (غیرفعال)'; }
                $lines[]=$lbl; 
                $kb[]=[ ['text'=>$lbl, 'callback_data'=>'admin:vip_shop_item_view|id='.$r['id'].'|cid='.$cid.'|page='.$page] ]; 
            }
            if(empty($rows)){ $lines[]='—'; }
            $kb[]=[ ['text'=>'افزودن آیتم','callback_data'=>'admin:vip_shop_item_add|cid='.$cid] ];
            $kb[]=[ ['text'=>'ویرایش دسته','callback_data'=>'admin:vip_shop_cat_edit|id='.$cid], ['text'=>'حذف دسته','callback_data'=>'admin:vip_shop_cat_del|id='.$cid.'|page='.$page] ];
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:vip_shop_cats|page='.$page] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;

        case 'vip_shop_cat_edit':
            $cid=(int)($params['id']??0); setAdminState($chatId,'await_vip_shop_cat_edit',['id'=>$cid]); sendMessage($chatId,'نام جدید و سپس عدد ترتیب را ارسال کنید.'); break;

        case 'vip_shop_cat_del':
            $cid=(int)($params['id']??0); $page=(int)($params['page']??1);
            db()->prepare("DELETE FROM vip_shop_categories WHERE id=?")->execute([$cid]);
            sendMessage($chatId,'دسته حذف شد.');
            handleAdminNav($chatId,$messageId,'vip_shop_cats',['page'=>$page],$userRow);
            break;

        case 'vip_shop_item_add':
            $cid=(int)($params['cid']??0); setAdminState($chatId,'await_vip_shop_item_name',['cid'=>$cid]); sendMessage($chatId,'نام آیتم VIP را ارسال کنید.'); break;

        case 'vip_shop_item_view':
            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);
            $it = db()->prepare("SELECT * FROM vip_shop_items WHERE id=?"); $it->execute([$iid]); $r=$it->fetch(); if(!$r){ answerCallback($_POST['callback_query']['id']??'','پیدا نشد',true); return; }
            $lines = ['آیتم: '.e($r['name']), 'قیمت M-Coin: '.formatPrice((int)$r['mcoin_price']), 'بسته: '.$r['pack_size'], 'حد مجاز: '.$r['per_user_limit'], 'سود روزانه/بسته: '.$r['daily_profit_per_pack'], 'وضعیت: '.(($r['enabled']??0)?'فعال':'غیرفعال')];
            $kb=[ [ ['text'=>($r['enabled']??0)?'خاموش کردن':'روشن کردن','callback_data'=>'admin:vip_shop_item_toggle|id='.$iid.'|cid='.$cid.'|page='.$page] , ['text'=>'حذف','callback_data'=>'admin:vip_shop_item_del|id='.$iid.'|cid='.$cid.'|page='.$page] ], [ ['text'=>'بازگشت','callback_data'=>'admin:vip_shop_cat_view|id='.$cid.'|page='.$page] ] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;

        case 'vip_shop_item_toggle':
            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);
            db()->prepare("UPDATE vip_shop_items SET enabled = 1 - enabled WHERE id=?")->execute([$iid]);
            sendMessage($chatId,'وضعیت تغییر کرد.');
            handleAdminNav($chatId,$messageId,'vip_shop_item_view',['id'=>$iid,'cid'=>$cid,'page'=>$page],$userRow);
            break;

        case 'vip_shop_item_del':
            $iid=(int)($params['id']??0); $cid=(int)($params['cid']??0); $page=(int)($params['page']??1);
            db()->prepare("DELETE FROM vip_shop_items WHERE id=?")->execute([$iid]);
            sendMessage($chatId,'آیتم حذف شد.');
            handleAdminNav($chatId,$messageId,'vip_shop_cat_view',['id'=>$cid,'page'=>$page],$userRow);
            break;

        case 'factory_requests':
            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);
            $tot = db()->query("SELECT COUNT(*) c FROM custom_factory_requests")->fetch()['c']??0;
            $st = db()->prepare("SELECT cfr.id, cfr.factory_name, cfr.product_name, cfr.daily_output, cfr.status, cfr.created_at, u.username, u.telegram_id FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id ORDER BY cfr.created_at DESC LIMIT ?,?"); 
            $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
            $kb=[]; foreach($rows as $r){ 
                $statusText = ['pending'=>'در انتظار','approved'=>'تایید شده','rejected'=>'رد شده','cost_set'=>'تعیین هزینه','accepted'=>'پذیرفته شده','declined'=>'رد شده توسط کاربر'][$r['status']] ?? $r['status'];
                $kb[]=[ ['text'=>e($r['factory_name']).' - '.$statusText,'callback_data'=>'admin:factory_request_view|id='.$r['id'].'|page='.$page] ]; 
            }
            foreach(paginationKeyboard('admin:factory_requests',$page, ($offset+count($rows))<$tot, 'admin:vip_shop')['inline_keyboard'] as $row){ $kb[]=$row; }
            editMessageText($chatId,$messageId,'درخواست‌های کارخانه شخصی',['inline_keyboard'=>$kb]);
            break;

        case 'factory_request_view':
            $rid=(int)($params['id']??0); $page=(int)($params['page']??1);
            $req = db()->prepare("SELECT cfr.*, u.username, u.telegram_id, u.country FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id WHERE cfr.id=?"); 
            $req->execute([$rid]); $r=$req->fetch(); 
            if(!$r){ answerCallback($_POST['callback_query']['id']??'','درخواست یافت نشد',true); return; }
            
            $statusText = ['pending'=>'در انتظار','approved'=>'تایید شده','rejected'=>'رد شده','cost_set'=>'تعیین هزینه','accepted'=>'پذیرفته شده','declined'=>'رد شده توسط کاربر'][$r['status']] ?? $r['status'];
            $lines = [
                'درخواست کارخانه شخصی',
                'کاربر: '.($r['username']?'@'.$r['username']:$r['telegram_id']).' ('.$r['country'].')',
                'نام کارخانه: '.e($r['factory_name']),
                'محصول: '.e($r['product_name']),
                'بازدهی روزانه: '.$r['daily_output'],
                'وضعیت: '.$statusText,
                'تاریخ: '.iranDateTime($r['created_at'])
            ];
            if($r['admin_cost_money']>0 || $r['admin_cost_mcoin']>0){
                $lines[] = 'هزینه: '.($r['admin_cost_money']>0 ? formatPrice($r['admin_cost_money']).' پول' : '').($r['admin_cost_money']>0 && $r['admin_cost_mcoin']>0 ? ' + ' : '').($r['admin_cost_mcoin']>0 ? $r['admin_cost_mcoin'].' M-Coin' : '');
            }
            if($r['admin_message']){
                $lines[] = 'پیام ادمین: '.e($r['admin_message']);
            }
            
            $kb=[];
            if($r['status']==='pending'){
                $kb[]=[ ['text'=>'تایید','callback_data'=>'admin:factory_request_approve|id='.$rid], ['text'=>'رد','callback_data'=>'admin:factory_request_reject|id='.$rid] ];
                $kb[]=[ ['text'=>'تعیین هزینه','callback_data'=>'admin:factory_request_cost|id='.$rid] ];
            }elseif($r['status']==='cost_set'){
                $kb[]=[ ['text'=>'تایید نهایی','callback_data'=>'admin:factory_request_final_approve|id='.$rid] ];
            }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:factory_requests|page='.$page] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;

        case 'factory_request_approve':
            $rid=(int)($params['id']??0);
            db()->prepare("UPDATE custom_factory_requests SET status='approved' WHERE id=?")->execute([$rid]);
            $req = db()->prepare("SELECT cfr.*, u.telegram_id FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id WHERE cfr.id=?"); 
            $req->execute([$rid]); $r=$req->fetch();
            if($r){
                db()->prepare("INSERT INTO user_custom_factories (user_id,request_id,factory_name,product_name,daily_output) VALUES (?,?,?,?,?)")->execute([$r['user_id'],$rid,$r['factory_name'],$r['product_name'],$r['daily_output']]);
                sendMessage($r['telegram_id'],'درخواست کارخانه شما تایید شد!');
            }
            sendMessage($chatId,'درخواست تایید شد.');
            handleAdminNav($chatId,$messageId,'factory_request_view',['id'=>$rid],$userRow);
            break;

        case 'factory_request_reject':
            $rid=(int)($params['id']??0);
            setAdminState($chatId,'await_factory_reject_reason',['id'=>$rid]);
            sendMessage($chatId,'دلیل رد درخواست را ارسال کنید:');
            break;

        case 'factory_request_cost':
            $rid=(int)($params['id']??0);
            setAdminState($chatId,'await_factory_cost',['id'=>$rid]);
            sendMessage($chatId,'هزینه کارخانه را ارسال کنید (پول و M-Coin در دو خط جدا):');
            break;

        case 'factory_request_final_approve':
            $rid=(int)($params['id']??0);
            $req = db()->prepare("SELECT cfr.*, u.telegram_id FROM custom_factory_requests cfr JOIN users u ON u.id=cfr.user_id WHERE cfr.id=?"); 
            $req->execute([$rid]); $r=$req->fetch();
            if($r){
                db()->prepare("INSERT INTO user_custom_factories (user_id,request_id,factory_name,product_name,daily_output) VALUES (?,?,?,?,?)")->execute([$r['user_id'],$rid,$r['factory_name'],$r['product_name'],$r['daily_output']]);
                db()->prepare("UPDATE custom_factory_requests SET status='accepted' WHERE id=?")->execute([$rid]);
                sendMessage($r['telegram_id'],'درخواست کارخانه شما تایید شد!');
            }
            sendMessage($chatId,'درخواست تایید شد.');
            handleAdminNav($chatId,$messageId,'factory_request_view',['id'=>$rid],$userRow);
            break;

        case 'player_trades':
            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);
            $tot = db()->query("SELECT COUNT(*) c FROM player_trades")->fetch()['c']??0;
            $st = db()->prepare("SELECT pt.id, pt.sender_id, pt.receiver_id, pt.trade_type, pt.money_amount, pt.item_id, pt.item_quantity, pt.status, pt.created_at, s.username as sender_username, s.telegram_id as sender_telegram_id, r.username as receiver_username, r.telegram_id as receiver_telegram_id, si.name as item_name FROM player_trades pt JOIN users s ON s.id=pt.sender_id JOIN users r ON r.id=pt.receiver_id LEFT JOIN shop_items si ON si.id=pt.item_id ORDER BY pt.created_at DESC LIMIT ?,?"); 
            $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
            $kb=[]; foreach($rows as $r){ 
                $statusText = ['pending'=>'در انتظار','accepted'=>'پذیرفته شده','rejected'=>'رد شده','admin_approved'=>'تایید شده','admin_rejected'=>'رد شده توسط ادمین'][$r['status']] ?? $r['status'];
                $tradeText = '';
                if($r['trade_type']==='money' || $r['trade_type']==='both'){
                    $tradeText .= formatPrice($r['money_amount']).' پول';
                }
                if($r['trade_type']==='item' || $r['trade_type']==='both'){
                    if($tradeText) $tradeText .= ' + ';
                    $tradeText .= $r['item_quantity'].' '.e($r['item_name']);
                }
                $kb[]=[ ['text'=>e($r['sender_username']).' -> '.e($r['receiver_username']).' | '.$tradeText.' | '.$statusText,'callback_data'=>'admin:trade_view|id='.$r['id'].'|page='.$page] ]; 
            }
            foreach(paginationKeyboard('admin:player_trades',$page, ($offset+count($rows))<$tot, 'admin:shop')['inline_keyboard'] as $row){ $kb[]=$row; }
            editMessageText($chatId,$messageId,'تجارت‌های بازیکن‌ها',['inline_keyboard'=>$kb]);
            break;

        case 'trade_view':
            $tid=(int)($params['id']??0); $page=(int)($params['page']??1);
            $trade = db()->prepare("SELECT pt.*, s.username as sender_username, s.telegram_id as sender_telegram_id, s.country as sender_country, r.username as receiver_username, r.telegram_id as receiver_telegram_id, r.country as receiver_country, si.name as item_name FROM player_trades pt JOIN users s ON s.id=pt.sender_id JOIN users r ON r.id=pt.receiver_id LEFT JOIN shop_items si ON si.id=pt.item_id WHERE pt.id=?"); 
            $trade->execute([$tid]); $t=$trade->fetch(); 
            if(!$t){ answerCallback($_POST['callback_query']['id']??'','تجارت یافت نشد',true); return; }
            
            $statusText = ['pending'=>'در انتظار','accepted'=>'پذیرفته شده','rejected'=>'رد شده','admin_approved'=>'تایید شده','admin_rejected'=>'رد شده توسط ادمین'][$t['status']] ?? $t['status'];
            $lines = [
                'جزئیات تجارت',
                'فرستنده: '.($t['sender_username']?'@'.$t['sender_username']:$t['sender_telegram_id']).' ('.$t['sender_country'].')',
                'گیرنده: '.($t['receiver_username']?'@'.$t['receiver_username']:$t['receiver_telegram_id']).' ('.$t['receiver_country'].')',
                'نوع: '.$t['trade_type'],
                'وضعیت: '.$statusText,
                'تاریخ: '.iranDateTime($t['created_at'])
            ];
            if($t['trade_type']==='money' || $t['trade_type']==='both'){
                $lines[] = 'مقدار پول: '.formatPrice($t['money_amount']);
            }
            if($t['trade_type']==='item' || $t['trade_type']==='both'){
                $lines[] = 'آیتم: '.e($t['item_name']).' | تعداد: '.$t['item_quantity'];
            }
            
            $kb=[];
            if($t['status']==='accepted'){
                $kb[]=[ ['text'=>'تایید معامله','callback_data'=>'admin:trade_approve|id='.$tid], ['text'=>'فسخ معامله','callback_data'=>'admin:trade_reject|id='.$tid] ];
            }
            $kb[]=[ ['text'=>'لاگ تجارت','callback_data'=>'admin:trade_logs|page=1'] ];
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'admin:player_trades|page='.$page] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            break;

        case 'trade_approve':
            $tid=(int)($params['id']??0);
            $trade = db()->prepare("SELECT * FROM player_trades WHERE id=? AND status='accepted'"); 
            $trade->execute([$tid]); $t=$trade->fetch();
            if(!$t){ answerCallback($_POST['callback_query']['id']??'','تجارت یافت نشد',true); return; }
            
            db()->beginTransaction();
            try {
                // Transfer money if applicable
                if($t['trade_type']==='money' || $t['trade_type']==='both'){
                    db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([$t['money_amount'], $t['sender_id']]);
                    db()->prepare("UPDATE users SET money = money + ? WHERE id=?")->execute([$t['money_amount'], $t['receiver_id']]);
                }
                
                // Transfer items if applicable
                if($t['trade_type']==='item' || $t['trade_type']==='both'){
                    // Remove from sender
                    db()->prepare("UPDATE user_items SET quantity = quantity - ? WHERE user_id=? AND item_id=?")->execute([$t['item_quantity'], $t['sender_id'], $t['item_id']]);
                    // Add to receiver
                    db()->prepare("INSERT INTO user_items (user_id,item_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?")->execute([$t['receiver_id'], $t['item_id'], $t['item_quantity'], $t['item_quantity']]);
                }
                
                db()->prepare("UPDATE player_trades SET status='admin_approved', admin_id=? WHERE id=?")->execute([$userRow['id'], $tid]);
                db()->prepare("INSERT INTO trade_logs (trade_id,admin_id,action) VALUES (?,?,?)")->execute([$tid, $userRow['id'], 'approved']);
                
                // Notify users
                $sender = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); $sender->execute([$t['sender_id']]); $s=$sender->fetch();
                $receiver = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); $receiver->execute([$t['receiver_id']]); $r=$receiver->fetch();
                if($s) sendMessage($s['telegram_id'],'معامله تایید و انجام شد.');
                if($r) sendMessage($r['telegram_id'],'معامله تایید و انجام شد.');
                
                db()->commit();
                sendMessage($chatId,'معامله تایید شد.');
            } catch (Exception $e) {
                db()->rollBack();
                sendMessage($chatId,'خطا در انجام معامله: '.$e->getMessage());
            }
            handleAdminNav($chatId,$messageId,'trade_view',['id'=>$tid,'page'=>$page],$userRow);
            break;

        case 'trade_reject':
            $tid=(int)($params['id']??0);
            db()->prepare("UPDATE player_trades SET status='admin_rejected', admin_id=? WHERE id=?")->execute([$userRow['id'], $tid]);
            db()->prepare("INSERT INTO trade_logs (trade_id,admin_id,action) VALUES (?,?,?)")->execute([$tid, $userRow['id'], 'rejected']);
            
            // Notify users
            $trade = db()->prepare("SELECT sender_id, receiver_id FROM player_trades WHERE id=?"); $trade->execute([$tid]); $t=$trade->fetch();
            if($t){
                $sender = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); $sender->execute([$t['sender_id']]); $s=$sender->fetch();
                $receiver = db()->prepare("SELECT telegram_id FROM users WHERE id=?"); $receiver->execute([$t['receiver_id']]); $r=$receiver->fetch();
                if($s) sendMessage($s['telegram_id'],'معامله فسخ شد.');
                if($r) sendMessage($r['telegram_id'],'معامله فسخ شد.');
            }
            
            sendMessage($chatId,'معامله فسخ شد.');
            handleAdminNav($chatId,$messageId,'player_trades',['page'=>$page],$userRow);
            break;

        case 'trade_logs':
            $page=(int)($params['page']??1); $per=10; [$offset,$limit]=paginate($page,$per);
            $tot = db()->query("SELECT COUNT(*) c FROM trade_logs")->fetch()['c']??0;
            $st = db()->prepare("SELECT tl.*, pt.sender_id, pt.receiver_id, s.username as sender_username, r.username as receiver_username, a.username as admin_username FROM trade_logs tl JOIN player_trades pt ON pt.id=tl.trade_id JOIN users s ON s.id=pt.sender_id JOIN users r ON r.id=pt.receiver_id JOIN users a ON a.id=tl.admin_id ORDER BY tl.created_at DESC LIMIT ?,?"); 
            $st->bindValue(1,$offset,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
            $kb=[]; foreach($rows as $r){ 
                $actionText = $r['action']==='approved' ? 'تایید شده' : 'رد شده';
                $kb[]=[ ['text'=>e($r['sender_username']).' -> '.e($r['receiver_username']).' | '.$actionText.' توسط '.e($r['admin_username']),'callback_data'=>'admin:trade_log_view|id='.$r['id'].'|page='.$page] ]; 
            }
            foreach(paginationKeyboard('admin:trade_logs',$page, ($offset+count($rows))<$tot, 'admin:player_trades')['inline_keyboard'] as $row){ $kb[]=$row; }
            editMessageText($chatId,$messageId,'لاگ تجارت‌ها',['inline_keyboard'=>$kb]);
            break;

        case 'trade_log_view':
            $lid=(int)($params['id']??0); $page=(int)($params['page']??1);
            $log = db()->prepare("SELECT tl.*, pt.*, s.username as sender_username, s.telegram_id as sender_telegram_id, r.username as receiver_username, r.telegram_id as receiver_telegram_id, a.username as admin_username, a.telegram_id as admin_telegram_id, si.name as item_name FROM trade_logs tl JOIN player_trades pt ON pt.id=tl.trade_id JOIN users s ON s.id=pt.sender_id JOIN users r ON r.id=pt.receiver_id JOIN users a ON a.id=tl.admin_id LEFT JOIN shop_items si ON si.id=pt.item_id WHERE tl.id=?"); 
            $log->execute([$lid]); $l=$log->fetch(); 
            if(!$l){ answerCallback($_POST['callback_query']['id']??'','لاگ یافت نشد',true); return; }
            
            $actionText = $l['action']==='approved' ? 'تایید شده' : 'رد شده';
            $lines = [
                'جزئیات لاگ تجارت',
                'فرستنده: '.($l['sender_username']?'@'.$l['sender_username']:$l['sender_telegram_id']),
                'گیرنده: '.($l['receiver_username']?'@'.$l['receiver_username']:$l['receiver_telegram_id']),
                'ادمین: '.($l['admin_username']?'@'.$l['admin_username']:$l['admin_telegram_id']),
                'عمل: '.$actionText,
                'تاریخ: '.iranDateTime($l['created_at'])
            ];
            if($l['trade_type']==='money' || $l['trade_type']==='both'){
                $lines[] = 'مقدار پول: '.formatPrice($l['money_amount']);
            }
            if($l['trade_type']==='item' || $l['trade_type']==='both'){
                $lines[] = 'آیتم: '.e($l['item_name']).' | تعداد: '.$l['item_quantity'];
            }
            
            $kb=[[['text'=>'بازگشت','callback_data'=>'admin:trade_logs|page='.$page]]];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
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



    // cooldown helpers

    $u = userByTelegramId($chatId);

    $userId = (int)$u['id'];

    $hasRecentSupport = function(int $uid): bool {

        $stmt = db()->prepare("SELECT COUNT(*) c FROM support_messages WHERE user_id=? AND created_at >= (NOW() - INTERVAL 30 SECOND)"); $stmt->execute([$uid]);

        return ((int)($stmt->fetch()['c']??0))>0;

    };

    $hasRecentSubmission = function(int $uid): bool {

        $stmt = db()->prepare("SELECT COUNT(*) c FROM submissions WHERE user_id=? AND created_at >= (NOW() - INTERVAL 30 SECOND)"); $stmt->execute([$uid]);

        return ((int)($stmt->fetch()['c']??0))>0;

    };



    switch ($key) {

        case 'await_disc_code':

            $code = trim((string)($text ?: ($caption ?? '')));

            if ($code===''){ sendMessage($chatId,'کد را وارد کنید.'); return; }

            $row = db()->prepare("SELECT * FROM discount_codes WHERE code=? AND disabled=0"); $row->execute([$code]); $dc=$row->fetch();

            if (!$dc) { sendMessage($chatId,'کد نامعتبر است.'); clearUserState($chatId); return; }

            // Check if discount code is valid for current shop type
            $shopType = 'regular'; // Default to regular shop
            if(isset($data['shop_type'])) {
                $shopType = $data['shop_type'];
            }
            
            if($dc['shop_type'] !== 'both' && $dc['shop_type'] !== $shopType) {
                $shopName = $shopType === 'vip' ? 'VIP Shop' : 'فروشگاه معمولی';
                sendMessage($chatId,'این کد برای '.$shopName.' معتبر نیست.'); clearUserState($chatId); return;
            }

            if (!empty($dc['expires_at']) && (new DateTime($dc['expires_at'])) < new DateTime('now')) { sendMessage($chatId,'کد منقضی شده است.'); clearUserState($chatId); return; }

            if ((int)$dc['max_uses']>0 && (int)$dc['used_count'] >= (int)$dc['max_uses']) { sendMessage($chatId,'سقف مصرف کد تکمیل است.'); clearUserState($chatId); return; }

            $cnt = db()->prepare("SELECT COUNT(*) c FROM discount_usages WHERE code_id=? AND user_id=?"); $cnt->execute([(int)$dc['id'], (int)$userId]); $uc=(int)($cnt->fetch()['c']??0);

            if ($uc >= (int)$dc['per_user_limit']) { sendMessage($chatId,'سهمیه شما برای این کد تمام شده است.'); clearUserState($chatId); return; }

            setSetting('cart_disc_'.(int)$userId, (string)((int)$dc['percent']));

            setSetting('cart_disc_code_'.(int)$userId, (string)((int)$dc['id']));

            clearUserState($chatId);

            // refresh cart inline if possible

            $rows = db()->prepare("SELECT uci.item_id, uci.quantity, si.name, si.unit_price FROM user_cart_items uci JOIN shop_items si ON si.id=uci.item_id WHERE uci.user_id=? ORDER BY si.name ASC");

            $rows->execute([$userId]); $items=$rows->fetchAll();

            if ($items) {

                $lines=['سبد خرید:']; $kb=[]; foreach($items as $it){ $lines[]='- '.e($it['name']).' | تعداد: '.$it['quantity'].' | قیمت: '.formatPrice((int)$it['unit_price']*$it['quantity']); $kb[]=[ ['text'=>'+','callback_data'=>'user_shop:inc|id='.$it['item_id']], ['text'=>'-','callback_data'=>'user_shop:dec|id='.$it['item_id']] ]; }

                $total = getCartTotalForUser($userId); $disc=(int)$dc['percent']; $discAmt=(int)floor($total*$disc/100); $pay=max(0,$total-$discAmt);

                $txt = implode("\n",$lines)."\n\nجمع کل (بدون تخفیف): ".formatPrice($total)."\nتخفیف (".$disc."%): -".formatPrice($discAmt)."\nمبلغ قابل پرداخت: ".formatPrice($pay);

                $kb[]=[ ['text'=>'استفاده از کد تخفیف','callback_data'=>'user_shop:disc_apply'] ];

                $kb[]=[ ['text'=>'خرید','callback_data'=>'user_shop:checkout'] ];

                $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

                // try edit last cart msg if we have its id

                $mid = getSetting('cart_msg_'.(int)$userId); if ($mid){ @editMessageText($chatId,(int)$mid,$txt,['inline_keyboard'=>$kb]); } else { sendMessage($chatId,$txt,['inline_keyboard'=>$kb]); }

            } else { sendMessage($chatId,'کد تخفیف اعمال شد: '.$dc['percent'].'%'); }

            break;

        case 'await_support':

            if (!$text && !$photo) { sendMessage($chatId,'فقط متن یا عکس بفرستید.'); return; }

            if ($hasRecentSupport($userId)) { sendMessage($chatId,'لطفاً کمی صبر کنید و سپس دوباره تلاش کنید.'); return; }

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

            if ($hasRecentSubmission($userId)) { sendMessage($chatId,'لطفاً کمی صبر کنید و سپس دوباره تلاش کنید.'); return; }

            $u = userByTelegramId($chatId);

            db()->prepare("INSERT INTO submissions (user_id, type, text, photo_file_id) VALUES (?, ?, ?, ?)")->execute([(int)$u['id'], $type, $text ?: $caption, $photo]);

            sendMessage($chatId,'ارسال شما ثبت شد.');

            $sectionTitle = getInlineButtonTitle($type);

            notifySectionAdmins($type, 'پیام جدید در بخش ' . $sectionTitle);

            clearUserState($chatId);

            break;

        case 'await_war_format':

            // Expect text with attacker/defender names; optionally photo

            $content = $text ?: $caption;

            if (!$content) { sendMessage($chatId,'ابتدا متن با فرمت موردنظر را ارسال کنید.'); return; }

            if ($hasRecentSubmission($userId)) { sendMessage($chatId,'لطفاً کمی صبر کنید و سپس دوباره تلاش کنید.'); return; }

            $att = null; $def = null;

            if (preg_match('/نام\s*کشور\s*حمله\s*کننده\s*:\s*(.+)/u', $content, $m1)) { $att = trim($m1[1]); }

            if (preg_match('/نام\s*کشور\s*دفاع\s*کننده\s*:\s*(.+)/u', $content, $m2)) { $def = trim($m2[1]); }

            if (!$att || !$def) { sendMessage($chatId,'فرمت نامعتبر. هر دو نام کشور لازم است.'); return; }

            $u = userByTelegramId($chatId);

            db()->prepare("INSERT INTO submissions (user_id, type, text, photo_file_id, attacker_country, defender_country) VALUES (?, 'war', ?, ?, ?, ?)")->execute([(int)$u['id'], $content, $photo, $att, $def]);

            sendMessage($chatId,'اعلام جنگ ثبت شد.');

            notifySectionAdmins('war', 'پیام جدید در بخش ' . getInlineButtonTitle('war'));

            clearUserState($chatId);

            break;

        case 'await_role_text':

            if (!$text) { sendMessage($chatId,'فقط متن مجاز است.'); return; }

            if ($hasRecentSubmission($userId)) { sendMessage($chatId,'لطفاً کمی صبر کنید و سپس دوباره تلاش کنید.'); return; }

            $u = userByTelegramId($chatId);

            db()->prepare("INSERT INTO submissions (user_id, type, text) VALUES (?, 'role', ?)")->execute([(int)$u['id'], $text]);

            sendMessage($chatId,'رول شما ثبت شد و در انتظار بررسی است.');

            notifySectionAdmins('roles', 'پیام جدید در بخش ' . getInlineButtonTitle('roles'));

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

            // fetch alliance info

            $ainfo = db()->prepare("SELECT name FROM alliances WHERE id=?"); $ainfo->execute([$aid]); $ar=$ainfo->fetch(); $aname = $ar?$ar['name']:'اتحاد';

            $title = 'دعوت به اتحاد: '.e($aname)."\n".'کشور دعوت‌کننده: '.e($inviter['country']?:'—');

            $kb=[ [ ['text'=>'بله','callback_data'=>'alli_inv:accept|aid='.$aid], ['text'=>'خیر','callback_data'=>'alli_inv:reject|aid='.$aid] ] ];

            sendMessage((int)$invitee['telegram_id'], $title."\n\nشما به این اتحاد دعوت شدید. آیا می‌پذیرید؟", ['inline_keyboard'=>$kb]);

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

        case 'await_alliance_banner':

            $aid=(int)$data['alliance_id'];

            $photo = null; if (!empty($message['photo'])) { $photos=$message['photo']; $largest=end($photos); $photo=$largest['file_id']??null; }

            if (!$photo) { sendMessage($chatId,'عکس ارسال کنید.'); return; }

            db()->prepare("UPDATE alliances SET banner_file_id=? WHERE id=?")->execute([$photo,$aid]);

            sendMessage($chatId,'بنر اتحاد تنظیم شد.');

            clearUserState($chatId);

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

    applyDailyProfitsIfDue();
    applyUserCleanupIfDue();



    // Maintenance block for non-admins

    if (!getAdminPermissions($chatId) && isMaintenanceEnabled()) {

        answerCallback($callback['id'], maintenanceMessage(), true);

        return;

    }



    list($action, $params) = cbParse($data);



    if (strpos($action, 'nav:') === 0) {

        $route = substr($action, 4);

        // Enforce schedule for user-facing sections

        $routeToKey = [

            'army'=>'army','missile'=>'missile','defense'=>'defense','roles'=>'roles',

            'statement'=>'statement','war'=>'war','assets'=>'assets','support'=>'support',

            'alliance'=>'alliance','shop'=>'shop'

        ];

        if (isset($routeToKey[$route]) && !isButtonEnabled($routeToKey[$route])) {

            answerCallback($callback['id'], 'این دکمه در حال حاضر در دسترس نیست', true);

            return;

        }

        handleNav($chatId, $messageId, $route, $params, $u);

        return;

    }

    if (strpos($action, 'user_shop:') === 0) {

        if (!isButtonEnabled('shop')) { answerCallback($callback['id'], 'این دکمه در حال حاضر در دسترس نیست', true); return; }

        $route = substr($action, 10);

        $urow = userByTelegramId($chatId); $uid = (int)$urow['id'];

        if ($route === 'factories') {

            $rows = db()->query("SELECT id,name,price_l1,price_l2 FROM factories ORDER BY id DESC")->fetchAll();

            if (!$rows) { editMessageText($chatId,$messageId,'کارخانه‌ای موجود نیست.', ['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }

            $kb=[]; $lines=['کارخانه‌های نظامی:'];

            foreach($rows as $r){

                $lines[] = '- '.e($r['name']).' | L1: '.formatPrice((int)$r['price_l1']).' | L2: '.formatPrice((int)$r['price_l2']);

                $kb[]=[ ['text'=>'خرید L1 - '.e($r['name']),'callback_data'=>'user_shop:factory_buy|id='.$r['id'].'|lvl=1'], ['text'=>'خرید L2','callback_data'=>'user_shop:factory_buy|id='.$r['id'].'|lvl=2'] ];

            }

            $kb[]=[ ['text'=>'کارخانه‌های من','callback_data'=>'user_shop:myfactories'] ];

            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines), ['inline_keyboard'=>$kb]);

            return;

        }

        if ($route === 'myfactories') {

            $rows = db()->prepare("SELECT uf.id ufid, f.id fid, f.name, uf.level FROM user_factories uf JOIN factories f ON f.id=uf.factory_id WHERE uf.user_id=? ORDER BY f.name ASC");

            $rows->execute([$uid]); $fs=$rows->fetchAll();

            if (!$fs) { editMessageText($chatId,$messageId,'شما کارخانه‌ای ندارید.', ['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }

            $kb=[]; $lines=['کارخانه‌های من:'];

            foreach($fs as $f){ $lines[]='- '.e($f['name']).' | لول: '.$f['level']; $kb[]=[ ['text'=>'دریافت تولید امروز - '.e($f['name']), 'callback_data'=>'user_shop:factory_claim|fid='.$f['fid']] ]; }

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'user_shop:factories'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines), ['inline_keyboard'=>$kb]);

            return;

        }

        if (strpos($route,'factory_buy')===0) {

            $fid=(int)($params['id']??0); $lvl=(int)($params['lvl']??1); if($lvl!==1 && $lvl!==2){ $lvl=1; }

            $f = db()->prepare("SELECT id,name,price_l1,price_l2 FROM factories WHERE id=?"); $f->execute([$fid]); $fr=$f->fetch(); if(!$fr){ answerCallback($callback['id'],'ناموجود', true); return; }

            $owned = db()->prepare("SELECT id, level FROM user_factories WHERE user_id=? AND factory_id=?"); $owned->execute([$uid,$fid]); $ow=$owned->fetch();

            $price = $lvl===1 ? (int)$fr['price_l1'] : (int)$fr['price_l2'];

            if ($ow) {

                if ((int)$ow['level'] >= $lvl) { answerCallback($callback['id'],'قبلاً این سطح را دارید', true); return; }

                // upgrade to level 2

                if ((int)$urow['money'] < $price) { answerCallback($callback['id'],'موجودی کافی نیست', true); return; }

                db()->beginTransaction();

                try {

                    db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([$price, $uid]);

                    db()->prepare("UPDATE user_factories SET level=2 WHERE id=?")->execute([(int)$ow['id']]);

                    db()->commit();

                } catch (Exception $e) { db()->rollBack(); answerCallback($callback['id'],'خطا', true); return; }

                answerCallback($callback['id'],'ارتقا خرید شد');

            } else {

                if ((int)$urow['money'] < $price) { answerCallback($callback['id'],'موجودی کافی نیست', true); return; }

                db()->beginTransaction();

                try {

                    db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([$price, $uid]);

                    db()->prepare("INSERT INTO user_factories (user_id,factory_id,level) VALUES (?,?,?)")->execute([$uid,$fid,$lvl]);

                    db()->commit();

                } catch (Exception $e) { db()->rollBack(); answerCallback($callback['id'],'خطا', true); return; }

                answerCallback($callback['id'],'خرید شد');

            }

            // refresh factory list

            $rows = db()->query("SELECT id,name,price_l1,price_l2 FROM factories ORDER BY id DESC")->fetchAll();

            $kb=[]; $lines=['کارخانه‌های نظامی:']; foreach($rows as $r){ $lines[]='- '.e($r['name']).' | L1: '.formatPrice((int)$r['price_l1']).' | L2: '.formatPrice((int)$r['price_l2']); $kb[]=[ ['text'=>'خرید L1 - '.e($r['name']),'callback_data'=>'user_shop:factory_buy|id='.$r['id'].'|lvl=1'], ['text'=>'خرید L2','callback_data'=>'user_shop:factory_buy|id='.$r['id'].'|lvl=2'] ]; }

            $kb[]=[ ['text'=>'کارخانه‌های من','callback_data'=>'user_shop:myfactories'] ];

            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines), ['inline_keyboard'=>$kb]);

            return;

        }

        if (strpos($route,'factory_claim_pick')===0) {

            $ufid=(int)($params['ufid']??0); $item=(int)($params['item']??0);

            // check not already granted today

            $today = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');

            $chk = db()->prepare("SELECT granted FROM user_factory_grants WHERE user_factory_id=? AND for_date=?"); $chk->execute([$ufid,$today]); $exists=$chk->fetch(); if($exists && (int)$exists['granted']===1){ answerCallback($callback['id'],'دریافت شده است', true); return; }

            // find level and qty

            $uf = db()->prepare("SELECT uf.level, uf.factory_id FROM user_factories uf WHERE uf.id=? AND uf.user_id=?"); $uf->execute([$ufid,$uid]); $ufo=$uf->fetch(); if(!$ufo){ answerCallback($callback['id'],'یافت نشد', true); return; }

            $lvl=(int)$ufo['level']; $fp = db()->prepare("SELECT qty_l1, qty_l2 FROM factory_products WHERE factory_id=? AND item_id=?"); $fp->execute([(int)$ufo['factory_id'],$item]); $pr=$fp->fetch(); if(!$pr){ answerCallback($callback['id'],'محصول یافت نشد', true); return; }

            $units = $lvl===2 ? (int)$pr['qty_l2'] : (int)$pr['qty_l1']; if($units<=0){ answerCallback($callback['id'],'تولیدی تعریف نشده', true); return; }

            addUnitsForUser($uid, $item, $units);

            db()->prepare("INSERT INTO user_factory_grants (user_factory_id,for_date,granted,chosen_item_id) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE granted=VALUES(granted), chosen_item_id=VALUES(chosen_item_id)")->execute([$ufid,$today,$item]);

            answerCallback($callback['id'],'اضافه شد');

            editMessageText($chatId,$messageId,'محصول امروز اضافه شد.',['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'user_shop:myfactories']]]] );

            return;

        }

        if (strpos($route,'factory_claim')===0) {

            $fid=(int)($params['fid']??0);

            $uf = db()->prepare("SELECT id, level FROM user_factories WHERE user_id=? AND factory_id=?"); $uf->execute([$uid,$fid]); $ufo=$uf->fetch(); if(!$ufo){ answerCallback($callback['id'],'ندارید', true); return; }

            $ufid=(int)$ufo['id']; $lvl=(int)$ufo['level'];

            $today = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');

            $chk = db()->prepare("SELECT granted FROM user_factory_grants WHERE user_factory_id=? AND for_date=?"); $chk->execute([$ufid,$today]); $ex=$chk->fetch(); if($ex && (int)$ex['granted']===1){ answerCallback($callback['id'],'قبلاً دریافت شده', true); return; }

            // list products

            $ps = db()->prepare("SELECT fp.item_id, si.name, fp.qty_l1, fp.qty_l2 FROM factory_products fp JOIN shop_items si ON si.id=fp.item_id WHERE fp.factory_id=? ORDER BY si.name ASC"); $ps->execute([$fid]); $rows=$ps->fetchAll(); if(!$rows){ answerCallback($callback['id'],'محصولی ثبت نشده', true); return; }

            if (count($rows)===1) {

                $units = $lvl===2 ? (int)$rows[0]['qty_l2'] : (int)$rows[0]['qty_l1']; if($units<=0){ answerCallback($callback['id'],'تولیدی تعریف نشده', true); return; }

                addUnitsForUser($uid, (int)$rows[0]['item_id'], $units);

                db()->prepare("INSERT INTO user_factory_grants (user_factory_id,for_date,granted,chosen_item_id) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE granted=VALUES(granted), chosen_item_id=VALUES(chosen_item_id)")->execute([$ufid,$today,(int)$rows[0]['item_id']]);

                answerCallback($callback['id'],'اضافه شد');

                editMessageText($chatId,$messageId,'محصول امروز اضافه شد.',['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'user_shop:myfactories']]]] );

                return;

            }

            // ask user to pick one product

            $kb=[]; $lines=['یک محصول انتخاب کنید:']; foreach($rows as $r){ $units = $lvl===2 ? (int)$r['qty_l2'] : (int)$r['qty_l1']; $lines[]='- '.e($r['name']).' | مقدار: '.$units; $kb[]=[ ['text'=>e($r['name']), 'callback_data'=>'user_shop:factory_claim_pick|ufid='.$ufid.'|item='.$r['item_id']] ]; }

            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'user_shop:myfactories'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines), ['inline_keyboard'=>$kb]);

            return;

        }

        if ($route === 'cart') {

            $rows = db()->prepare("SELECT uci.item_id, uci.quantity, si.name, si.unit_price FROM user_cart_items uci JOIN shop_items si ON si.id=uci.item_id WHERE uci.user_id=? ORDER BY si.name ASC");

            $rows->execute([$uid]); $items=$rows->fetchAll();

            if (!$items) { editMessageText($chatId,$messageId,'سبد خرید شما خالی است.', ['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }

            $lines=['سبد خرید:']; $kb=[]; foreach($items as $it){ $lines[]='- '.e($it['name']).' | تعداد: '.$it['quantity'].' | قیمت: '.formatPrice((int)$it['unit_price']*$it['quantity']); $kb[]=[ ['text'=>'+','callback_data'=>'user_shop:inc|id='.$it['item_id']], ['text'=>'-','callback_data'=>'user_shop:dec|id='.$it['item_id']] ]; }

            $total = getCartTotalForUser($uid);

            // Show applied discount if any

            $ds = getSetting('cart_disc_'.$uid); $discTxt=''; if($ds){ $disc = (int)$ds; $discAmt = (int)floor($total*$disc/100); $pay = max(0,$total-$discAmt); $discTxt = "\nجمع کل (بدون تخفیف): ".formatPrice($total)."\nتخفیف (".$disc."%): -".formatPrice($discAmt)."\nمبلغ قابل پرداخت: ".formatPrice($pay); }

            $kb[]=[ ['text'=>'استفاده از کد تخفیف','callback_data'=>'user_shop:disc_apply'] ];

            $kb[]=[ ['text'=>'خرید','callback_data'=>'user_shop:checkout'] ];

            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines).($ds?"\n\n":"\n\nجمع کل: ").($ds?"":formatPrice($total)).$discTxt, ['inline_keyboard'=>$kb]);

            return;

        }

        if (strpos($route,'cat')===0) {

            $cid=(int)($params['id']??0);

            $st = db()->prepare("SELECT id,name,unit_price,pack_size,per_user_limit,daily_profit_per_pack FROM shop_items WHERE category_id=? AND enabled=1 ORDER BY name ASC"); $st->execute([$cid]); $rows=$st->fetchAll();

            if (!$rows) { editMessageText($chatId,$messageId,'این دسته خالی است.', ['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }

            $kb=[]; $lines=['آیتم‌ها:']; foreach($rows as $r){ $line = e($r['name']).' | قیمت: '.formatPrice((int)$r['unit_price']).' | بسته: '.$r['pack_size']; if((int)$r['daily_profit_per_pack']>0){ $line.=' | سود روزانه/بسته: '.$r['daily_profit_per_pack']; } $lines[]=$line; $kb[]=[ ['text'=>'افزودن به سبد - '.$r['name'], 'callback_data'=>'user_shop:add|id='.$r['id']] ]; }

            $kb[]=[ ['text'=>'مشاهده سبد خرید','callback_data'=>'user_shop:cart'] ];

            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);

            return;

        }

        if (strpos($route,'add')===0) {

            $iid=(int)($params['id']??0);

            $it = db()->prepare("SELECT per_user_limit FROM shop_items WHERE id=? AND enabled=1"); $it->execute([$iid]); $r=$it->fetch(); if(!$r){ answerCallback($callback['id'],'ناموجود', true); return; }

            $limit=(int)$r['per_user_limit']; if($limit>0){

                $p = db()->prepare("SELECT packs_bought FROM user_item_purchases WHERE user_id=? AND item_id=?"); $p->execute([$uid,$iid]); $pb=(int)($p->fetch()['packs_bought']??0);

                $inCart = db()->prepare("SELECT quantity FROM user_cart_items WHERE user_id=? AND item_id=?"); $inCart->execute([$uid,$iid]); $q=(int)($inCart->fetch()['quantity']??0);

                if ($pb + $q + 1 > $limit) { answerCallback($callback['id'],'به حد مجاز خرید رسیده‌اید', true); return; }

            }

            // clear any previous discount cache (cart changed)

            setSetting('cart_disc_'.$uid, ''); setSetting('cart_disc_code_'.$uid, '');

            db()->prepare("INSERT INTO user_cart_items (user_id,item_id,quantity) VALUES (?,?,1) ON DUPLICATE KEY UPDATE quantity=quantity+1")->execute([$uid,$iid]);

            answerCallback($callback['id'],'به سبد اضافه شد');

            return;

        }

        if ($route==='disc_apply') {

            setUserState($chatId,'await_disc_code',['shop_type'=>'regular']);

            sendMessage($chatId,'کد تخفیف را وارد کنید.');

            return;

        }

        if ($route==='vip_disc_apply') {

            setUserState($chatId,'await_disc_code',['shop_type'=>'vip']);

            sendMessage($chatId,'کد تخفیف VIP را وارد کنید.');

            return;

        }

        if (strpos($route,'inc')===0 || strpos($route,'dec')===0) {

            $iid=(int)($params['id']??0);

            if (strpos($route,'inc')===0) {

                $it = db()->prepare("SELECT per_user_limit FROM shop_items WHERE id=? AND enabled=1"); $it->execute([$iid]); $r=$it->fetch(); if(!$r){ answerCallback($callback['id'],'ناموجود', true); return; }

                $limit=(int)$r['per_user_limit']; if($limit>0){ $p = db()->prepare("SELECT packs_bought FROM user_item_purchases WHERE user_id=? AND item_id=?"); $p->execute([$uid,$iid]); $pb=(int)($p->fetch()['packs_bought']??0); $inCart = db()->prepare("SELECT quantity FROM user_cart_items WHERE user_id=? AND item_id=?"); $inCart->execute([$uid,$iid]); $q=(int)($inCart->fetch()['quantity']??0); if ($pb + $q + 1 > $limit) { answerCallback($callback['id'],'به حد مجاز خرید رسیده‌اید', true); return; } }

                db()->prepare("UPDATE user_cart_items SET quantity = quantity + 1 WHERE user_id=? AND item_id=?")->execute([$uid,$iid]);

            } else {

                db()->prepare("UPDATE user_cart_items SET quantity = GREATEST(0, quantity - 1) WHERE user_id=? AND item_id=?")->execute([$uid,$iid]);

                db()->prepare("DELETE FROM user_cart_items WHERE user_id=? AND item_id=? AND quantity=0")->execute([$uid,$iid]);

            }

            // refresh cart view inline

            $rows = db()->prepare("SELECT uci.item_id, uci.quantity, si.name, si.unit_price FROM user_cart_items uci JOIN shop_items si ON si.id=uci.item_id WHERE uci.user_id=? ORDER BY si.name ASC");

            $rows->execute([$uid]); $items=$rows->fetchAll();

            if (!$items) { editMessageText($chatId,$messageId,'سبد خرید شما خالی است.', ['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }

            $lines=['سبد خرید:']; $kb=[]; foreach($items as $it){ $lines[]='- '.e($it['name']).' | تعداد: '.$it['quantity'].' | قیمت: '.formatPrice((int)$it['unit_price']*$it['quantity']); $kb[]=[ ['text'=>'+','callback_data'=>'user_shop:inc|id='.$it['item_id']], ['text'=>'-','callback_data'=>'user_shop:dec|id='.$it['item_id']] ]; }

            $total = getCartTotalForUser($uid);

            // recalc discount view if any

            $ds = getSetting('cart_disc_'.$uid); $discTxt=''; if($ds){ $disc=(int)$ds; $discAmt=(int)floor($total*$disc/100); $pay=max(0,$total-$discAmt); $discTxt = "\nجمع کل (بدون تخفیف): ".formatPrice($total)."\nتخفیف (".$disc."%): -".formatPrice($discAmt)."\nمبلغ قابل پرداخت: ".formatPrice($pay); }

            $kb[]=[ ['text'=>'استفاده از کد تخفیف','callback_data'=>'user_shop:disc_apply'] ];

            $kb[]=[ ['text'=>'خرید','callback_data'=>'user_shop:checkout'] ];

            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];

            editMessageText($chatId,$messageId,implode("\n",$lines).($ds?"\n\n":"\n\nجمع کل: ").($ds?"":formatPrice($total)).$discTxt, ['inline_keyboard'=>$kb]);

            return;

        }

        if ($route === 'checkout') {

            $items = db()->prepare("SELECT uci.item_id, uci.quantity, si.unit_price, si.pack_size, si.daily_profit_per_pack FROM user_cart_items uci JOIN shop_items si ON si.id=uci.item_id WHERE uci.user_id=?");

            $items->execute([$uid]); $rows=$items->fetchAll(); if(!$rows){ answerCallback($callback['id'],'سبد خالی است', true); return; }

        if ($route === 'vip_shop') {
            $vipEnabled = (int)(getSetting('vip_shop_enabled', '0') ?: 0);
            if (!$vipEnabled) { answerCallback($callback['id'], 'VIP Shop غیرفعال است', true); return; }
            
            $cats = db()->query("SELECT id, name, type FROM vip_shop_categories ORDER BY sort_order ASC, name ASC")->fetchAll();
            $kb=[]; 
            foreach($cats as $c){ 
                $kb[]=[ ['text'=>$c['name'].' ('.$c['type'].')','callback_data'=>'user_shop:vip_cat|id='.$c['id']] ]; 
            }
            $kb[]=[ ['text'=>'سبد خرید VIP','callback_data'=>'user_shop:vip_cart'] ];
            $kb[]=[ ['text'=>'استفاده از کد تخفیف VIP','callback_data'=>'user_shop:vip_disc_apply'] ];
            $kb[]=[ ['text'=>'کارخانه شخصی سازی شده','callback_data'=>'user_shop:custom_factory'] ];
            $kb[]=[ ['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];
            editMessageText($chatId,$messageId,'VIP Shop',['inline_keyboard'=>$kb]);
            return;
        }

        if (strpos($route,'vip_cat')===0) {
            $cid=(int)($params['id']??0);
            $st = db()->prepare("SELECT id,name,mcoin_price,pack_size,per_user_limit,daily_profit_per_pack FROM vip_shop_items WHERE category_id=? AND enabled=1 ORDER BY name ASC"); 
            $st->execute([$cid]); $rows=$st->fetchAll();
            if (!$rows) { editMessageText($chatId,$messageId,'این دسته خالی است.', ['inline_keyboard'=>[[['text'=>'بازگشت به VIP Shop','callback_data'=>'user_shop:vip_shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]] ); return; }
            $kb=[]; $lines=['آیتم‌های VIP:']; 
            foreach($rows as $r){ 
                $line = e($r['name']).' | قیمت: '.formatPrice((int)$r['mcoin_price']).' M Coin | بسته: '.$r['pack_size']; 
                if((int)$r['daily_profit_per_pack']>0){ $line.=' | سود روزانه/بسته: '.$r['daily_profit_per_pack']; } 
                $lines[]=$line; 
                $kb[]=[ ['text'=>'افزودن به سبد - '.$r['name'], 'callback_data'=>'user_shop:vip_add|id='.$r['id']] ]; 
            }
            $kb[]=[ ['text'=>'مشاهده سبد خرید VIP','callback_data'=>'user_shop:vip_cart'] ];
            $kb[]=[ ['text'=>'بازگشت به VIP Shop','callback_data'=>'user_shop:vip_shop'], ['text'=>'بازگشت به منو','callback_data'=>'nav:home'] ];
            editMessageText($chatId,$messageId,implode("
",$lines),['inline_keyboard'=>$kb]);
            return;
        }

            // If user disabled from daily profit, prevent buying items that add daily profit unless admin will add manually

            $uProfit = db()->prepare("SELECT daily_profit_enabled FROM users WHERE id=?"); $uProfit->execute([$uid]); $urpf=$uProfit->fetch(); $profitEnabled = (int)($urpf['daily_profit_enabled']??1)===1;

            if (!$profitEnabled) {

                foreach($rows as $r){ if ((int)$r['daily_profit_per_pack']>0) { answerCallback($callback['id'],'خرید آیتم‌های دارای سود روزانه برای شما غیرفعال است. فقط توسط ادمین قابل اضافه است.', true); return; } }

            }

            $total = getCartTotalForUser($uid);

            // apply discount if set and valid

            $ds = getSetting('cart_disc_'.$uid); $appliedDisc = 0; if($ds){ $appliedDisc=(int)$ds; $discAmt=(int)floor($total*$appliedDisc/100); $total=max(0,$total-$discAmt); }

            if ((int)$urow['money'] < $total) { answerCallback($callback['id'],'موجودی کافی نیست', true); return; }

            db()->beginTransaction();

            try {

                db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([$total, $uid]);

                foreach($rows as $r){ addInventoryForUser($uid, (int)$r['item_id'], (int)$r['quantity'], (int)$r['pack_size']); $dp=(int)$r['daily_profit_per_pack']; if($dp>0 && $profitEnabled) increaseUserDailyProfit($uid, $dp * (int)$r['quantity']); db()->prepare("INSERT INTO user_item_purchases (user_id,item_id,packs_bought) VALUES (?,?,0) ON DUPLICATE KEY UPDATE packs_bought=packs_bought")->execute([$uid,(int)$r['item_id']]); db()->prepare("UPDATE user_item_purchases SET packs_bought = packs_bought + ? WHERE user_id=? AND item_id=?")->execute([(int)$r['quantity'],$uid,(int)$r['item_id']]); }

                // record discount usage if any

                $dcId = getSetting('cart_disc_code_'.$uid); if ($dcId){ db()->prepare("INSERT INTO discount_usages (code_id,user_id) VALUES (?,?)")->execute([(int)$dcId,$uid]); db()->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE id=?")->execute([(int)$dcId]); setSetting('cart_disc_'.$uid,''); setSetting('cart_disc_code_'.$uid,''); }

                db()->prepare("DELETE FROM user_cart_items WHERE user_id=?")->execute([$uid]);

                db()->commit();

            } catch (Exception $e) { db()->rollBack(); if (DEBUG) { @sendMessage(MAIN_ADMIN_ID, 'Shop checkout error: ' . $e->getMessage()); } answerCallback($callback['id'],'خطا در خرید', true); return; }

            editMessageText($chatId,$messageId,'خرید انجام شد.',['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]]);

            answerCallback($callback['id'],'خرید انجام شد');

            return;

        }

        if ($route === 'custom_factory') {
            $kb = [
                [ ['text'=>'درخواست کارخانه جدید','callback_data'=>'user_shop:custom_factory_request'] ],
                [ ['text'=>'کارخانه‌های من','callback_data'=>'user_shop:my_custom_factories'] ],
                [ ['text'=>'محصولات کارخانه','callback_data'=>'user_shop:factory_products'] ],
                [ ['text'=>'بازگشت به VIP Shop','callback_data'=>'user_shop:vip_shop'] ]
            ];
            editMessageText($chatId,$messageId,'کارخانه شخصی سازی شده',['inline_keyboard'=>$kb]);
            return;
        }

        if ($route === 'custom_factory_request') {
            setUserState($chatId,'await_custom_factory_name',[]);
            sendMessage($chatId,'نام کارخانه خود را ارسال کنید:');
            return;
        }

        if ($route === 'my_custom_factories') {
            $factories = db()->prepare("SELECT ucf.id, ucf.factory_name, ucf.product_name, ucf.daily_output, ucf.created_at FROM user_custom_factories ucf WHERE ucf.user_id=? ORDER BY ucf.created_at DESC");
            $factories->execute([$uid]); $fs=$factories->fetchAll();
            if (!$fs) { 
                editMessageText($chatId,$messageId,'شما کارخانه شخصی ندارید.', ['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'user_shop:custom_factory']]]] ); 
                return; 
            }
            $kb=[]; $lines=['کارخانه‌های شخصی شما:'];
            foreach($fs as $f){ 
                $lines[]='- '.e($f['factory_name']).' | محصول: '.e($f['product_name']).' | بازدهی: '.$f['daily_output'];
                $kb[]=[ ['text'=>'دریافت تولید - '.e($f['factory_name']), 'callback_data'=>'user_shop:custom_factory_claim|fid='.$f['id']] ]; 
            }
            $kb[]=[ ['text'=>'بازگشت','callback_data'=>'user_shop:custom_factory'] ];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            return;
        }

        if ($route === 'factory_products') {
            $products = db()->prepare("SELECT ufp.product_name, ufp.quantity, ucf.factory_name FROM user_factory_products ufp JOIN user_custom_factories ucf ON ucf.id=ufp.factory_id WHERE ufp.user_id=? ORDER BY ufp.product_name ASC");
            $products->execute([$uid]); $ps=$products->fetchAll();
            if (!$ps) { 
                editMessageText($chatId,$messageId,'محصولی از کارخانه‌هایتان ندارید.', ['inline_keyboard'=>[[['text'=>'بازگشت','callback_data'=>'user_shop:custom_factory']]]] ); 
                return; 
            }
            $lines=['محصولات کارخانه‌های شما:'];
            foreach($ps as $p){ 
                $lines[]='- '.e($p['product_name']).' | تعداد: '.$p['quantity'].' | کارخانه: '.e($p['factory_name']);
            }
            $kb=[[['text'=>'بازگشت','callback_data'=>'user_shop:custom_factory']]];
            editMessageText($chatId,$messageId,implode("\n",$lines),['inline_keyboard'=>$kb]);
            return;
        }

        if (strpos($route,'custom_factory_claim')===0) {
            $fid=(int)($params['fid']??0);
            $today = date('Y-m-d');
            $uf = db()->prepare("SELECT ucf.id, ucf.factory_name, ucf.product_name, ucf.daily_output FROM user_custom_factories ucf WHERE ucf.id=? AND ucf.user_id=?"); 
            $uf->execute([$fid,$uid]); $ufo=$uf->fetch(); 
            if(!$ufo){ answerCallback($callback['id'],'کارخانه یافت نشد', true); return; }
            
            $chk = db()->prepare("SELECT last_claim_date FROM user_factory_products WHERE factory_id=? AND user_id=?"); 
            $chk->execute([$fid,$uid]); $ex=$chk->fetch(); 
            if($ex && $ex['last_claim_date'] === $today){ answerCallback($callback['id'],'قبلاً دریافت شده', true); return; }
            
            $output = (int)$ufo['daily_output'];
            db()->prepare("INSERT INTO user_factory_products (user_id,factory_id,product_name,quantity,last_claim_date) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?, last_claim_date=?")->execute([$uid,$fid,$ufo['product_name'],$output,$today,$output,$today]);
            
            answerCallback($callback['id'],'تولید امروز دریافت شد: '.$output.' '.e($ufo['product_name']));
            return;
        }

        if (strpos($action, 'trade:') === 0) {
            $route = substr($action, 6);
            handleTradeNav($chatId, $messageId, $route, $params, $u);
            return;
        }

        if (strpos($action, 'trade_type:') === 0) {
            $tradeType = substr($action, 11);
            $state = getUserState($chatId);
            if($state && $state['key']==='await_trade_type'){
                if($tradeType==='item'){
                    setUserState($chatId,'await_trade_item',['receiver_id'=>$state['data']['receiver_id'],'receiver_telegram_id'=>$state['data']['receiver_telegram_id'],'receiver_username'=>$state['data']['receiver_username'],'trade_type'=>$tradeType,'money_amount'=>0]);
                    sendMessage($chatId,'آیتم مورد نظر را انتخاب کنید:',getItemSelectionKeyboard($uid));
                }else{
                    setUserState($chatId,'await_trade_money',['receiver_id'=>$state['data']['receiver_id'],'receiver_telegram_id'=>$state['data']['receiver_telegram_id'],'receiver_username'=>$state['data']['receiver_username'],'trade_type'=>$tradeType]);
                    sendMessage($chatId,'مقدار پول را ارسال کنید:');
                }
            }
            return;
        }

        if (strpos($action, 'select_item:') === 0) {
            $itemId = (int)substr($action, 12);
            $state = getUserState($chatId);
            if($state && $state['key']==='await_trade_item'){
                setUserState($chatId,'await_trade_quantity',['receiver_id'=>$state['data']['receiver_id'],'receiver_telegram_id'=>$state['data']['receiver_telegram_id'],'receiver_username'=>$state['data']['receiver_username'],'trade_type'=>$state['data']['trade_type'],'money_amount'=>$state['data']['money_amount']??0,'item_id'=>$itemId]);
                sendMessage($chatId,'تعداد آیتم را ارسال کنید:');
            }
            return;
        }

        answerCallback($callback['id'],'دستور ناشناخته', true);

        return;

    }

    if (strpos($action, 'alli:') === 0) {

        if (!isButtonEnabled('alliance')) { answerCallback($callback['id'], 'این دکمه در حال حاضر در دسترس نیست', true); return; }

        $route = substr($action, 5);

        handleAllianceNav($chatId, $messageId, $route, $params, $u);

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

        $stmt = db()->prepare("SELECT s.*, u.telegram_id, u.id AS uid FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ answerCallback($callback['id'],'یافت نشد',true); return; }

        if ($route==='view') {

            $body = $r['text'] ? e($r['text']) : '—';

            if ($r['photo_file_id']) sendPhoto($chatId, $r['photo_file_id'], $body); else sendMessage($chatId,$body);

            answerCallback($callback['id'],''); return;

        }

        if ($route==='accept') {

            // if cost defined, check and deduct

            if (!empty($r['cost_amount'])) {

                $um = db()->prepare("SELECT money FROM users WHERE id=?"); $um->execute([(int)$r['uid']]); $ur=$um->fetch(); $money=(int)($ur['money']??0);

                if ($money < (int)$r['cost_amount']) { sendMessage((int)$r['telegram_id'], 'موجودی کافی نیست.'); if (!empty($callback['message']['message_id'])) deleteMessage($chatId,(int)$callback['message']['message_id']); answerCallback($callback['id'],'پول کافی نیست', true); return; }

                db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([(int)$r['cost_amount'], (int)$r['uid']]);

            }

            db()->prepare("UPDATE submissions SET status='user_confirmed' WHERE id=?")->execute([$id]);

            // Insert into approved_roles upon user confirm

            $usr = db()->prepare("SELECT username, country FROM users WHERE id=?"); $usr->execute([(int)$r['uid']]); $urx=$usr->fetch();

            db()->prepare("INSERT INTO approved_roles (submission_id, user_id, text, cost_amount, username, telegram_id, country) VALUES (?,?,?,?,?,?,?)")

              ->execute([$id, (int)$r['uid'], $r['text'], (int)($r['cost_amount']?:0), $urx['username']??null, $r['telegram_id'], $urx['country']??null]);

            // notify admins with roles perm with details and view button

            $uname = $urx['username'] ? '@'.$urx['username'] : '—';

            $body = "کاربر هزینه رول را تایید کرد:\nیوزرنیم: ".$uname."\nID: ".$r['telegram_id']."\nکشور: ".($urx['country']?:'—')."\nهزینه: ".(int)($r['cost_amount']?:0);

            $kb=[ [ ['text'=>'دیدن رول','callback_data'=>'admin:roles_approved|page=1'] ] ];

            $q = db()->query("SELECT admin_telegram_id, is_owner, permissions FROM admin_users"); foreach($q as $row){ $adminId=(int)$row['admin_telegram_id']; $perms=(int)$row['is_owner']===1?['all']:((($row['permissions']?json_decode($row['permissions'],true):[])?:[])); if(in_array('all',$perms,true)||in_array('roles',$perms,true)){ sendMessage($adminId,$body,['inline_keyboard'=>$kb]); } }

            if (!empty($callback['message']['message_id'])) deleteMessage($chatId,(int)$callback['message']['message_id']);

            answerCallback($callback['id'],'تایید شد');

        } else {

            db()->prepare("UPDATE submissions SET status='user_declined' WHERE id=?")->execute([$id]);

            // notify admins with details

            $usr = db()->prepare("SELECT username, country FROM users WHERE id=?"); $usr->execute([(int)$r['uid']]); $urx=$usr->fetch(); $uname = $urx['username'] ? '@'.$urx['username'] : '—';

            $body = "کاربر هزینه رول را رد کرد:\nیوزرنیم: ".$uname."\nID: ".$r['telegram_id']."\nکشور: ".($urx['country']?:'—');

            $q = db()->query("SELECT admin_telegram_id, is_owner, permissions FROM admin_users"); foreach($q as $row){ $adminId=(int)$row['admin_telegram_id']; $perms=(int)$row['is_owner']===1?['all']:((($row['permissions']?json_decode($row['permissions'],true):[])?:[])); if(in_array('all',$perms,true)||in_array('roles',$perms,true)){ sendMessage($adminId,$body); } }

            sendMessage((int)$r['telegram_id'],'رد ثبت شد.');

            // remove from list per requirement

            db()->prepare("DELETE FROM submissions WHERE id=?")->execute([$id]);

            if (!empty($callback['message']['message_id'])) deleteMessage($chatId,(int)$callback['message']['message_id']);

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

        // delete invite message after action

        if (!empty($callback['message']['message_id'])) { deleteMessage($chatId, (int)$callback['message']['message_id']); }

        return;

    }

    if (strpos($action, 'sreply:') === 0) {

        $route = substr($action, 7);

        if ($route === 'view') {

            $sid=(int)($params['sid']??0); $rid=(int)($params['rid']??0);

            $stmt = db()->prepare("SELECT sm.text stext, sm.photo_file_id sphoto, sr.text rtext, sr.photo_file_id rphoto FROM support_messages sm JOIN support_replies sr ON sr.id=? WHERE sm.id=?");

            $stmt->execute([$rid,$sid]); $r=$stmt->fetch(); if(!$r){ answerCallback($callback['id'],'یافت نشد',true); return; }

            $body = "پیام شما:\n".($r['stext']?e($r['stext']):'—')."\n\nپاسخ ادمین:\n".($r['rtext']?e($r['rtext']):'—');

            $kb=[ [ ['text'=>'بستن','callback_data'=>'sreply:close|sid='.$sid] ] ];

            if ($r['rphoto']) sendPhoto($chatId, $r['rphoto'], $body, ['inline_keyboard'=>$kb]); else editMessageText($chatId, $messageId, $body, ['inline_keyboard'=>$kb]);

            return;

        }

        if ($route === 'close') {

            deleteMessage($chatId, $messageId);

            return;

        }

    }



    // Fallback

    answerCallback($callback['id'], 'دستور ناشناخته');

}



// --------------------- ENTRYPOINT ---------------------



// Optional webhook secret check

if (WEBHOOK_SECRET !== '' && (!isset($_GET['token']) || $_GET['token'] !== WEBHOOK_SECRET)) {

    if (!isset($_GET['cron'])) { // allow cron without token if not set, else enforce token when set

        http_response_code(403);

        echo 'Forbidden';

        exit;

    }

}



// Cron endpoint for daily profits

if (isset($_GET['cron']) && $_GET['cron'] === 'profits') {

    applyDailyProfitsIfDue();
    applyUserCleanupIfDue();

    echo 'OK';

    exit;

}



// Rebuild schema endpoint (dangerous). Use ?init=1 or ?init=1&drop=1

if (isset($_GET['init']) && $_GET['init'] === '1') {

    rebuildDatabase(isset($_GET['drop']) && $_GET['drop'] === '1');

    echo 'OK';

    exit;

}



$input = file_get_contents('php://input');

$update = json_decode($input, true);



if (!$update) { echo 'OK'; exit; }



try {

    if (isset($update['message'])) {

        processUserMessage($update['message']);

    } elseif (isset($update['callback_query'])) {

        processCallback($update['callback_query']);

    }

} catch (Throwable $e) {

    if (DEBUG) {

        @sendMessage(MAIN_ADMIN_ID, 'خطای غیرمنتظره: ' . $e->getMessage());

    }

}



echo 'OK';

?>

        case 'await_user_mcoin':
            $id=(int)$data['id']; $val = (int)preg_replace('/\D+/', '', (string)$text);
            db()->prepare("UPDATE users SET m_coins=? WHERE id=?")->execute([$val, $id]);
            sendMessage($chatId,'M Coin کاربر تنظیم شد: '.$val);
            clearAdminState($chatId);
            break;
