<?php
/**
 * Samp Info Bot — Single-file PHP Telegram bot (MVP)
 * Features:
 * - Start + language selection (fa/en/ru)
 * - Forced join channels before usage
 * - Main menu (skins, rules, favorites)
 * - Skins: search by game_id; show details with photo + Like/Share/Fav
 * - Rules: list and view; admin can manage
 * - Admin panel: add skin (wizard), manage rules, manage sponsors, manage forced-join channels
 * - Auto-migration for MySQL
 *
 * Notes:
 * - Configure environment variables: BOT_TOKEN, DB_DSN, DB_USER, DB_PASS, DEFAULT_LANG, BOT_USERNAME
 * - Optional: ADMIN_TG_ID (comma-separated list) for initial admins
 * - Webhook URL example: https://yourdomain/index.php?token=YOUR_SECRET
 */

declare(strict_types=1);

// -------------------------
// Configuration
// -------------------------

const BOT_TOKEN = getenv('BOT_TOKEN') ?: '';
const DB_DSN = getenv('DB_DSN') ?: '';
const DB_USER = getenv('DB_USER') ?: '';
const DB_PASS = getenv('DB_PASS') ?: '';
const DEFAULT_LANG = getenv('DEFAULT_LANG') ?: 'fa';
const BOT_USERNAME = getenv('BOT_USERNAME') ?: ''; // e.g., SampInfoBot (without @)

if (BOT_TOKEN === '' || DB_DSN === '' || DB_USER === '') {
    http_response_code(500);
    echo 'Missing required environment variables.';
    exit;
}

// Optional webhook token guard
if (isset($_GET['token'])) {
    // You can validate a secret token here if you want. For now it's a passthrough.
}

// -------------------------
// Utilities
// -------------------------

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
    return $pdo;
}

function tg(string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        return ['ok' => false, 'description' => curl_error($ch)];
    }
    $data = json_decode($res, true);
    if (!is_array($data)) {
        return ['ok' => false, 'description' => 'Invalid JSON from Telegram'];
    }
    return $data;
}

function now(): string {
    return date('Y-m-d H:i:s');
}

function j(bool $ok, string $msg, array $extra = []): array {
    return array_merge(['ok' => $ok, 'msg' => $msg], $extra);
}

function isPrivateChat(array $update): bool {
    if (isset($update['message']['chat']['type'])) {
        return $update['message']['chat']['type'] === 'private';
    }
    if (isset($update['callback_query']['message']['chat']['type'])) {
        return $update['callback_query']['message']['chat']['type'] === 'private';
    }
    return true;
}

// -------------------------
// i18n dictionaries
// -------------------------

$I18N = [
    'fa' => [
        'choose_language' => "لطفاً زبان خود را انتخاب کنید:",
        'lang_fa' => '🇮🇷 فارسی',
        'lang_en' => '🇬🇧 English',
        'lang_ru' => '🇷🇺 Русский',
        'welcome' => 'به Samp Info Bot خوش آمدید!',
        'must_join' => 'برای استفاده از ربات، لطفاً ابتدا در کانال‌های زیر عضو شوید:',
        'check_join' => '🔄 بررسی عضویت',
        'main_menu' => "لطفاً یک گزینه را انتخاب کنید:",
        'menu_skins' => '🧍 اسکین‌ها',
        'menu_rules' => '📜 قوانین RP',
        'menu_favorites' => '⭐ علاقه‌مندی‌ها',
        'menu_settings' => '⚙️ تنظیمات',
        'menu_random' => '🎲 پیشنهاد تصادفی',
        'skins_prompt' => 'لطفاً ID اسکین را ارسال کنید (عدد):',
        'not_found' => 'موردی یافت نشد.',
        'like' => '❤️ پسندیدم',
        'share' => '🔁 اشتراک‌گذاری',
        'fav_add' => '⭐ افزودن به علاقه‌مندی',
        'fav_remove' => '❌ حذف از علاقه‌مندی',
        'added_to_fav' => 'به علاقه‌مندی‌ها اضافه شد.',
        'removed_from_fav' => 'از علاقه‌مندی‌ها حذف شد.',
        'already_liked' => 'قبلاً لایک کرده‌اید.',
        'liked_ok' => 'پسندیده شد!',
        'panel_denied' => 'شما دسترسی به پنل ندارید.',
        'panel_title' => 'پنل مدیریت',
        'panel_manage_skins' => '🧍 مدیریت اسکین‌ها',
        'panel_manage_rules' => '📜 مدیریت قوانین',
        'panel_manage_sponsors' => '⭐ اسپانسر',
        'panel_manage_channels' => '🔗 عضویت اجباری',
        'panel_back' => '🔙 بازگشت',
        'skins_add' => '➕ افزودن اسکین',
        'rules_add' => '➕ افزودن قانون',
        'sponsor_add' => '➕ افزودن اسپانسر',
        'channel_add' => '➕ افزودن کانال عضویت',
        'send_photo' => 'لطفاً عکس را ارسال کنید (Skip برای رد):',
        'send_game_id' => 'ID داخل بازی را وارد کنید (عدد):',
        'send_name_fa' => 'نام فارسی را ارسال کنید:',
        'send_name_en' => 'نام انگلیسی را ارسال کنید:',
        'send_name_ru' => 'نام روسی را ارسال کنید:',
        'send_group' => 'گروه (اختیاری - Skip برای رد):',
        'send_model' => 'مدل (اختیاری - Skip برای رد):',
        'send_story_fa' => 'داستان فارسی (اختیاری - Skip برای رد):',
        'send_story_en' => 'داستان انگلیسی (اختیاری - Skip برای رد):',
        'send_story_ru' => 'داستان روسی (اختیاری - Skip برای رد):',
        'saved' => 'ذخیره شد.',
        'enter_rule_title_fa' => 'عنوان قانون (FA):',
        'enter_rule_title_en' => 'Rule title (EN):',
        'enter_rule_title_ru' => 'Название правила (RU):',
        'enter_rule_body_fa' => 'متن قانون (FA):',
        'enter_rule_body_en' => 'Rule body (EN):',
        'enter_rule_body_ru' => 'Текст правила (RU):',
        'rules_list' => 'فهرست قوانین:',
        'back' => '🔙 بازگشت',
        'join' => 'عضویت',
    ],
    'en' => [
        'choose_language' => 'Please choose your language:',
        'lang_fa' => '🇮🇷 Persian',
        'lang_en' => '🇬🇧 English',
        'lang_ru' => '🇷🇺 Russian',
        'welcome' => 'Welcome to Samp Info Bot!',
        'must_join' => 'Please join the following channels first:',
        'check_join' => '🔄 Check membership',
        'main_menu' => 'Please choose an option:',
        'menu_skins' => '🧍 Skins',
        'menu_rules' => '📜 RP Rules',
        'menu_favorites' => '⭐ Favorites',
        'menu_settings' => '⚙️ Settings',
        'menu_random' => '🎲 Random',
        'skins_prompt' => 'Please send the Skin ID (number):',
        'not_found' => 'Not found.',
        'like' => '❤️ Like',
        'share' => '🔁 Share',
        'fav_add' => '⭐ Add to favorites',
        'fav_remove' => '❌ Remove from favorites',
        'added_to_fav' => 'Added to favorites.',
        'removed_from_fav' => 'Removed from favorites.',
        'already_liked' => 'You already liked this.',
        'liked_ok' => 'Liked!',
        'panel_denied' => 'You are not allowed to access the panel.',
        'panel_title' => 'Admin Panel',
        'panel_manage_skins' => '🧍 Manage Skins',
        'panel_manage_rules' => '📜 Manage Rules',
        'panel_manage_sponsors' => '⭐ Sponsors',
        'panel_manage_channels' => '🔗 Force-Join Channels',
        'panel_back' => '🔙 Back',
        'skins_add' => '➕ Add Skin',
        'rules_add' => '➕ Add Rule',
        'sponsor_add' => '➕ Add Sponsor',
        'channel_add' => '➕ Add Force-Join',
        'send_photo' => 'Please send a photo (or type Skip):',
        'send_game_id' => 'Enter in-game ID (number):',
        'send_name_fa' => 'Send Persian name:',
        'send_name_en' => 'Send English name:',
        'send_name_ru' => 'Send Russian name:',
        'send_group' => 'Group (optional - type Skip):',
        'send_model' => 'Model (optional - type Skip):',
        'send_story_fa' => 'Story in FA (optional - type Skip):',
        'send_story_en' => 'Story in EN (optional - type Skip):',
        'send_story_ru' => 'Story in RU (optional - type Skip):',
        'saved' => 'Saved.',
        'enter_rule_title_fa' => 'Rule title (FA):',
        'enter_rule_title_en' => 'Rule title (EN):',
        'enter_rule_title_ru' => 'Rule title (RU):',
        'enter_rule_body_fa' => 'Rule body (FA):',
        'enter_rule_body_en' => 'Rule body (EN):',
        'enter_rule_body_ru' => 'Rule body (RU):',
        'rules_list' => 'Rules list:',
        'back' => '🔙 Back',
        'join' => 'Join',
    ],
    'ru' => [
        'choose_language' => 'Пожалуйста, выберите язык:',
        'lang_fa' => '🇮🇷 Персидский',
        'lang_en' => '🇬🇧 Английский',
        'lang_ru' => '🇷🇺 Русский',
        'welcome' => 'Добро пожаловать в Samp Info Bot!',
        'must_join' => 'Сначала присоединитесь к следующим каналам:',
        'check_join' => '🔄 Проверить подписку',
        'main_menu' => 'Пожалуйста, выберите опцию:',
        'menu_skins' => '🧍 Скины',
        'menu_rules' => '📜 Правила RP',
        'menu_favorites' => '⭐ Избранное',
        'menu_settings' => '⚙️ Настройки',
        'menu_random' => '🎲 Случайный',
        'skins_prompt' => 'Отправьте ID скина (число):',
        'not_found' => 'Не найдено.',
        'like' => '❤️ Нравится',
        'share' => '🔁 Поделиться',
        'fav_add' => '⭐ В избранное',
        'fav_remove' => '❌ Удалить из избранного',
        'added_to_fav' => 'Добавлено в избранное.',
        'removed_from_fav' => 'Удалено из избранного.',
        'already_liked' => 'Вы уже поставили лайк.',
        'liked_ok' => 'Лайк!',
        'panel_denied' => 'У вас нет доступа к панели.',
        'panel_title' => 'Панель админа',
        'panel_manage_skins' => '🧍 Управление скинами',
        'panel_manage_rules' => '📜 Управление правилами',
        'panel_manage_sponsors' => '⭐ Спонсоры',
        'panel_manage_channels' => '🔗 Обязательная подписка',
        'panel_back' => '🔙 Назад',
        'skins_add' => '➕ Добавить скин',
        'rules_add' => '➕ Добавить правило',
        'sponsor_add' => '➕ Добавить спонсора',
        'channel_add' => '➕ Добавить канал',
        'send_photo' => 'Отправьте фото (или Skip):',
        'send_game_id' => 'Введите ID в игре (число):',
        'send_name_fa' => 'Отправьте персидское имя:',
        'send_name_en' => 'Отправьте английское имя:',
        'send_name_ru' => 'Отправьте русское имя:',
        'send_group' => 'Группа (необязательно - Skip):',
        'send_model' => 'Модель (необязательно - Skip):',
        'send_story_fa' => 'История на персидском (необязательно - Skip):',
        'send_story_en' => 'История на английском (необязательно - Skip):',
        'send_story_ru' => 'История на русском (необязательно - Skip):',
        'saved' => 'Сохранено.',
        'enter_rule_title_fa' => 'Заголовок правила (FA):',
        'enter_rule_title_en' => 'Заголовок правила (EN):',
        'enter_rule_title_ru' => 'Заголовок правила (RU):',
        'enter_rule_body_fa' => 'Текст правила (FA):',
        'enter_rule_body_en' => 'Текст правила (EN):',
        'enter_rule_body_ru' => 'Текст правила (RU):',
        'rules_list' => 'Список правил:',
        'back' => '🔙 Назад',
        'join' => 'Подписаться',
    ],
];

function tr(string $lang, string $key): string {
    global $I18N;
    $l = $I18N[$lang] ?? $I18N[DEFAULT_LANG] ?? $I18N['fa'];
    return $l[$key] ?? $key;
}

// -------------------------
// Auto-migration
// -------------------------

function migrate(): void {
    $pdo = db();
    // users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tg_id BIGINT UNSIGNED NOT NULL UNIQUE,
        language VARCHAR(2) NOT NULL DEFAULT 'fa',
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        permissions JSON NULL,
        state VARCHAR(64) NULL,
        state_meta JSON NULL,
        favorites_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // items (only skin used in MVP)
    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type ENUM('skin','vehicle','color','weather','object','weapon','mapping') NOT NULL,
        game_id INT NULL,
        name_fa VARCHAR(255), name_en VARCHAR(255), name_ru VARCHAR(255),
        group_name VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        description_fa TEXT, description_en TEXT, description_ru TEXT,
        weather_type VARCHAR(64) NULL,
        coordinates VARCHAR(128) NULL,
        tags JSON NULL,
        images JSON NULL,
        extra JSON NULL,
        likes_count INT UNSIGNED NOT NULL DEFAULT 0,
        views_count INT UNSIGNED NOT NULL DEFAULT 0,
        search_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_type_gameid (type, game_id),
        FULLTEXT KEY ftx_name_en (name_en),
        FULLTEXT KEY ftx_name_fa (name_fa),
        FULLTEXT KEY ftx_name_ru (name_ru)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // favorites
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        item_id BIGINT UNSIGNED NOT NULL,
        UNIQUE KEY uniq_user_item (user_id, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // likes
    $pdo->exec("CREATE TABLE IF NOT EXISTS likes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        item_id BIGINT UNSIGNED NOT NULL,
        UNIQUE KEY uniq_user_like (user_id, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // rules
    $pdo->exec("CREATE TABLE IF NOT EXISTS rules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(64) UNIQUE,
        title_fa VARCHAR(255), title_en VARCHAR(255), title_ru VARCHAR(255),
        body_fa TEXT, body_en TEXT, body_ru TEXT,
        sort_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // force-join channels
    $pdo->exec("CREATE TABLE IF NOT EXISTS force_channels (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chat_id BIGINT NOT NULL,
        username VARCHAR(255) NULL,
        title VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // sponsors
    $pdo->exec("CREATE TABLE IF NOT EXISTS sponsors (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chat_id BIGINT NOT NULL,
        username VARCHAR(255) NULL,
        title VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // admin logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_tg_id BIGINT NOT NULL,
        action VARCHAR(32) NOT NULL,
        item_type VARCHAR(32) NOT NULL,
        item_id BIGINT UNSIGNED NULL,
        payload JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // seed initial admins if provided
    $adminsCsv = getenv('ADMIN_TG_ID') ?: '';
    if ($adminsCsv !== '') {
        $ids = array_filter(array_map('trim', explode(',', $adminsCsv)));
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (tg_id, is_admin, language, created_at, updated_at) VALUES (?, 1, ?, NOW(), NOW())");
        foreach ($ids as $tg) {
            if (ctype_digit($tg)) {
                $stmt->execute([$tg, DEFAULT_LANG]);
            }
        }
    }
}

// -------------------------
// User helpers
// -------------------------

function getOrCreateUser(int $tgId, ?string $username = null, ?string $language = null): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE tg_id = ?");
    $stmt->execute([$tgId]);
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }
    $lang = $language ?: DEFAULT_LANG;
    $pdo->prepare("INSERT INTO users (tg_id, language, created_at, updated_at) VALUES (?, ?, NOW(), NOW())")
        ->execute([$tgId, $lang]);
    $stmt->execute([$tgId]);
    return $stmt->fetch();
}

function setUserLang(int $tgId, string $lang): void {
    $pdo = db();
    $pdo->prepare("UPDATE users SET language = ?, updated_at = NOW() WHERE tg_id = ?")
        ->execute([$lang, $tgId]);
}

function setUserState(int $tgId, ?string $state, $meta = null): void {
    $pdo = db();
    $metaJson = $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
    $pdo->prepare("UPDATE users SET state = ?, state_meta = ?, updated_at = NOW() WHERE tg_id = ?")
        ->execute([$state, $metaJson, $tgId]);
}

function getUserByTgId(int $tgId): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE tg_id = ?");
    $stmt->execute([$tgId]);
    $u = $stmt->fetch();
    return $u ?: null;
}

// -------------------------
// Force-join and sponsors
// -------------------------

function getForceChannels(): array {
    $stmt = db()->query("SELECT * FROM force_channels WHERE active = 1 ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getSponsors(): array {
    $stmt = db()->query("SELECT * FROM sponsors WHERE active = 1 ORDER BY id ASC");
    return $stmt->fetchAll();
}

function userJoinedAllRequired(int $userId): bool {
    $channels = getForceChannels();
    foreach ($channels as $ch) {
        $chatId = (int)$ch['chat_id'];
        $res = tg('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
        if (!($res['ok'] ?? false)) {
            return false;
        }
        $status = $res['result']['status'] ?? '';
        if (!in_array($status, ['creator', 'administrator', 'member', 'restricted'], true)) {
            return false;
        }
    }
    return true;
}

function sendForceJoinMessage(int $chatId, string $lang): void {
    $channels = getForceChannels();
    $inline = [];
    foreach ($channels as $ch) {
        $username = $ch['username'];
        if ($username) {
            $inline[] = [
                ['text' => tr($lang, 'join') . ' @' . $username, 'url' => 'https://t.me/' . $username],
            ];
        }
    }
    $inline[] = [
        ['text' => tr($lang, 'check_join'), 'callback_data' => 'check_join'],
    ];
    tg('sendMessage', [
        'chat_id' => $chatId,
        'text' => tr($lang, 'must_join'),
        'reply_markup' => ['inline_keyboard' => $inline],
    ]);
}

// -------------------------
// Keyboards
// -------------------------

function langKeyboard(): array {
    global $I18N;
    $l = $I18N[DEFAULT_LANG];
    return [
        'inline_keyboard' => [
            [
                ['text' => $l['lang_fa'], 'callback_data' => 'lang:fa'],
                ['text' => $l['lang_en'], 'callback_data' => 'lang:en'],
                ['text' => $l['lang_ru'], 'callback_data' => 'lang:ru'],
            ],
        ],
    ];
}

function mainMenu(string $lang): array {
    return [
        'keyboard' => [
            [ ['text' => tr($lang, 'menu_skins')], ['text' => tr($lang, 'menu_rules')] ],
            [ ['text' => tr($lang, 'menu_favorites')], ['text' => tr($lang, 'menu_settings')] ],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
    ];
}

function panelMenu(string $lang): array {
    return [
        'keyboard' => [
            [ ['text' => tr($lang, 'panel_manage_skins')], ['text' => tr($lang, 'panel_manage_rules')] ],
            [ ['text' => tr($lang, 'panel_manage_sponsors')], ['text' => tr($lang, 'panel_manage_channels')] ],
            [ ['text' => tr($lang, 'panel_back')] ],
        ],
        'resize_keyboard' => true,
    ];
}

// -------------------------
// Items: Skins
// -------------------------

function findSkinByGameId(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM items WHERE type = 'skin' AND game_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buildItemCaption(array $item, string $lang): string {
    $lines = [];
    $name = $item['name_' . $lang] ?: ($item['name_en'] ?: ($item['name_fa'] ?: $item['name_ru']));
    if ($item['type'] === 'skin') {
        $lines[] = '🧍 ' . ($name ?: 'Skin');
        if ($item['game_id'] !== null) $lines[] = 'ID: ' . $item['game_id'];
        if (!empty($item['group_name'])) $lines[] = 'Group: ' . $item['group_name'];
        if (!empty($item['model'])) $lines[] = 'Model: ' . $item['model'];
        $story = $item['description_' . $lang] ?: '';
        if ($story !== '') {
            $lines[] = '"' . $story . '"';
        }
    }
    // sponsors footer
    $sps = getSponsors();
    if (!empty($sps)) {
        $names = [];
        foreach ($sps as $s) {
            if (!empty($s['username'])) $names[] = '@' . $s['username'];
        }
        if (!empty($names)) {
            $lines[] = '';
            $lines[] = implode('  ', $names);
        }
    }
    return implode("\n", $lines);
}

function itemInlineKeyboard(array $item, string $lang): array {
    $likeCb = 'like:' . $item['type'] . ':' . $item['id'];
    $favCb = 'fav:' . $item['type'] . ':' . $item['id'];
    $shareUrl = BOT_USERNAME ? ('https://t.me/' . BOT_USERNAME . '?start=item:' . $item['type'] . ':' . $item['id']) : null;
    $row = [];
    $row[] = ['text' => tr($lang, 'like') . ' (' . (int)$item['likes_count'] . ')', 'callback_data' => $likeCb];
    if ($shareUrl) {
        $row[] = ['text' => tr($lang, 'share'), 'url' => $shareUrl];
    }
    $row2 = [];
    $row2[] = ['text' => tr($lang, 'fav_add'), 'callback_data' => $favCb];
    return ['inline_keyboard' => [ $row, $row2 ]];
}

function showSkinByGameId(int $chatId, string $lang, int $gameId): void {
    $item = findSkinByGameId($gameId);
    if (!$item) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => tr($lang, 'not_found'),
        ]);
        return;
    }
    $caption = buildItemCaption($item, $lang);
    $kb = itemInlineKeyboard($item, $lang);
    $images = json_decode($item['images'] ?: '[]', true) ?: [];
    $photo = $images[0] ?? null;
    if ($photo) {
        tg('sendPhoto', [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $kb,
        ]);
    } else {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => $caption,
            'reply_markup' => $kb,
        ]);
    }
}

// -------------------------
// Rules
// -------------------------

function listRules(string $lang): array {
    $stmt = db()->query("SELECT id, title_fa, title_en, title_ru FROM rules ORDER BY sort_order ASC, id ASC");
    $rows = $stmt->fetchAll();
    $inline = [];
    foreach ($rows as $r) {
        $title = $r['title_' . $lang] ?: ($r['title_en'] ?: ($r['title_fa'] ?: $r['title_ru']));
        $inline[] = [ ['text' => $title ?: ('Rule #' . $r['id']), 'callback_data' => 'rule:' . $r['id']] ];
    }
    if (empty($inline)) {
        $inline[] = [ ['text' => '—', 'callback_data' => 'noop'] ];
    }
    return ['inline_keyboard' => $inline];
}

function getRuleById(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM rules WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function buildRuleText(array $rule, string $lang): string {
    $title = $rule['title_' . $lang] ?: ($rule['title_en'] ?: ($rule['title_fa'] ?: $rule['title_ru']));
    $body = $rule['body_' . $lang] ?: ($rule['body_en'] ?: ($rule['body_fa'] ?: $rule['body_ru']));
    return "<b>" . htmlspecialchars($title) . "</b>\n\n" . htmlspecialchars($body);
}

// -------------------------
// Admin helpers
// -------------------------

function isAdmin(int $tgId): bool {
    $stmt = db()->prepare("SELECT is_admin FROM users WHERE tg_id = ?");
    $stmt->execute([$tgId]);
    $row = $stmt->fetch();
    return $row && (int)$row['is_admin'] === 1;
}

function adminLog(int $tgId, string $action, string $type, ?int $itemId, $payload = null): void {
    $pdo = db();
    $pdo->prepare("INSERT INTO admin_logs (actor_tg_id, action, item_type, item_id, payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
        ->execute([$tgId, $action, $type, $itemId, $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]);
}

// -------------------------
// Input handlers
// -------------------------

function handleStart(array $message, array $user): void {
    $chatId = $message['chat']['id'];
    $lang = $user['language'] ?: DEFAULT_LANG;
    $text = $message['text'] ?? '';
    $args = '';
    if (strpos($text, '/start') === 0) {
        $parts = explode(' ', $text, 2);
        if (count($parts) === 2) {
            $args = trim($parts[1]);
        }
    }

    if (!userJoinedAllRequired((int)$user['tg_id'])) {
        sendForceJoinMessage($chatId, $lang);
        return;
    }

    // First-time language selection if language empty – here we always show welcome + menu
    tg('sendMessage', [
        'chat_id' => $chatId,
        'text' => tr($lang, 'welcome') . "\n" . tr($lang, 'main_menu'),
        'reply_markup' => mainMenu($lang),
    ]);

    // Deep-link handling
    if ($args !== '') {
        if (strpos($args, 'item:') === 0) {
            $p = explode(':', $args);
            // item:<type>:<id>
            if (count($p) === 3) {
                $type = $p[1];
                $id = (int)$p[2];
                if ($type === 'skin') {
                    // Here we interpret <id> as internal item id, but we share deep links with internal primary id
                    $stmt = db()->prepare("SELECT * FROM items WHERE id = ? AND type = 'skin'");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch();
                    if ($item) {
                        $caption = buildItemCaption($item, $lang);
                        $kb = itemInlineKeyboard($item, $lang);
                        $images = json_decode($item['images'] ?: '[]', true) ?: [];
                        $photo = $images[0] ?? null;
                        if ($photo) {
                            tg('sendPhoto', [
                                'chat_id' => $chatId,
                                'photo' => $photo,
                                'caption' => $caption,
                                'parse_mode' => 'HTML',
                                'reply_markup' => $kb,
                            ]);
                        } else {
                            tg('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => $caption,
                                'reply_markup' => $kb,
                            ]);
                        }
                    }
                }
            }
        }
    }
}

function handleMessage(array $message): void {
    $from = $message['from'];
    $tgId = (int)$from['id'];
    $user = getOrCreateUser($tgId, $from['username'] ?? null, $from['language_code'] ?? DEFAULT_LANG);
    $lang = $user['language'] ?: DEFAULT_LANG;
    $chatId = (int)$message['chat']['id'];

    if (!isPrivateChat(['message' => $message])) {
        return; // ignore non-private for now
    }

    // Panel command
    if (isset($message['text']) && strpos($message['text'], '/panel') === 0) {
        if (!isAdmin($tgId)) {
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'panel_denied') ]);
            return;
        }
        setUserState($tgId, 'panel', ['section' => null]);
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => tr($lang, 'panel_title'),
            'reply_markup' => panelMenu($lang),
        ]);
        return;
    }

    // Start command
    if (isset($message['text']) && strpos($message['text'], '/start') === 0) {
        handleStart($message, $user);
        return;
    }

    // Check forced join before menu actions
    if (!userJoinedAllRequired($tgId)) {
        sendForceJoinMessage($chatId, $lang);
        return;
    }

    // State machine for admin add/edit wizards
    if ($user['state']) {
        handleStateMessage($message, $user);
        return;
    }

    // Main menu routing
    $text = trim($message['text'] ?? '');
    if ($text === tr($lang, 'menu_skins')) {
        setUserState($tgId, 'skins_wait_id');
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'skins_prompt') ]);
        return;
    }
    if ($text === tr($lang, 'menu_rules')) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text' => tr($lang, 'rules_list'),
            'reply_markup' => listRules($lang),
        ]);
        return;
    }
    if ($text === tr($lang, 'menu_favorites')) {
        showFavorites($chatId, $tgId, $lang);
        return;
    }

    // Handle skins id input
    if ($user['state'] === 'skins_wait_id') {
        $id = (int)preg_replace('/\D+/', '', $text);
        if ($id > 0) {
            setUserState($tgId, null, null);
            showSkinByGameId($chatId, $lang, $id);
        }
        return;
    }

    // Panel menu choices
    if (isAdmin($tgId)) {
        if ($text === tr($lang, 'panel_manage_skins')) {
            setUserState($tgId, 'panel_skins');
            tg('sendMessage', [
                'chat_id' => $chatId,
                'text' => tr($lang, 'panel_manage_skins'),
                'reply_markup' => [
                    'keyboard' => [ [ ['text' => tr($lang, 'skins_add')] ], [ ['text' => tr($lang, 'panel_back')] ] ],
                    'resize_keyboard' => true,
                ],
            ]);
            return;
        }
        if ($text === tr($lang, 'skins_add')) {
            setUserState($tgId, 'add_skin_photo', [ 'images' => [] ]);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_photo') ]);
            return;
        }
        if ($text === tr($lang, 'panel_manage_rules')) {
            setUserState($tgId, 'panel_rules');
            tg('sendMessage', [
                'chat_id' => $chatId,
                'text' => tr($lang, 'panel_manage_rules'),
                'reply_markup' => [ 'keyboard' => [ [ ['text' => tr($lang, 'rules_add')] ], [ ['text' => tr($lang, 'panel_back')] ] ], 'resize_keyboard' => true ],
            ]);
            return;
        }
        if ($text === tr($lang, 'rules_add')) {
            setUserState($tgId, 'add_rule_title_fa');
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_title_fa') ]);
            return;
        }
        if ($text === tr($lang, 'panel_manage_sponsors')) {
            setUserState($tgId, 'panel_sponsors');
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'panel_manage_sponsors'), 'reply_markup' => [ 'keyboard' => [ [ ['text' => tr($lang, 'sponsor_add')] ], [ ['text' => tr($lang, 'panel_back')] ] ], 'resize_keyboard' => true ] ]);
            return;
        }
        if ($text === tr($lang, 'sponsor_add')) {
            setUserState($tgId, 'sponsor_add_wait');
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => 'Send sponsor username (without @) or channel ID:' ]);
            return;
        }
        if ($text === tr($lang, 'panel_manage_channels')) {
            setUserState($tgId, 'panel_channels');
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'panel_manage_channels'), 'reply_markup' => [ 'keyboard' => [ [ ['text' => tr($lang, 'channel_add')] ], [ ['text' => tr($lang, 'panel_back')] ] ], 'resize_keyboard' => true ] ]);
            return;
        }
        if ($text === tr($lang, 'channel_add')) {
            setUserState($tgId, 'channel_add_wait');
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => 'Send channel username (without @) or channel ID:' ]);
            return;
        }
        if ($text === tr($lang, 'panel_back')) {
            setUserState($tgId, 'panel', ['section' => null]);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'panel_title'), 'reply_markup' => panelMenu($lang) ]);
            return;
        }
    }
}

function handleStateMessage(array $message, array $user): void {
    $tgId = (int)$user['tg_id'];
    $chatId = (int)$message['chat']['id'];
    $lang = $user['language'] ?: DEFAULT_LANG;
    $state = $user['state'];
    $meta = $user['state_meta'] ? json_decode($user['state_meta'], true) : [];

    // Add skin wizard
    if ($state === 'add_skin_photo') {
        $images = $meta['images'] ?? [];
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            usort($photos, function ($a, $b) { return ($a['file_size'] ?? 0) <=> ($b['file_size'] ?? 0); });
            $fileId = end($photos)['file_id'];
            $images[] = $fileId;
            $meta['images'] = $images;
            setUserState($tgId, 'add_skin_photo', $meta);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_photo') . "\n(added: " . count($images) . ")" ]);
            return;
        }
        if (isset($message['text']) && strcasecmp(trim($message['text']), 'skip') === 0) {
            setUserState($tgId, 'add_skin_game_id', $meta);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_game_id') ]);
            return;
        }
        // Ask to send photo or type Skip
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_photo') ]);
        return;
    }
    if ($state === 'add_skin_game_id') {
        $id = (int)preg_replace('/\D+/', '', $message['text'] ?? '');
        $meta['game_id'] = $id;
        setUserState($tgId, 'add_skin_name_fa', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_name_fa') ]);
        return;
    }
    if ($state === 'add_skin_name_fa') {
        $meta['name_fa'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_skin_name_en', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_name_en') ]);
        return;
    }
    if ($state === 'add_skin_name_en') {
        $meta['name_en'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_skin_name_ru', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_name_ru') ]);
        return;
    }
    if ($state === 'add_skin_name_ru') {
        $meta['name_ru'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_skin_group', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_group') ]);
        return;
    }
    if ($state === 'add_skin_group') {
        $txt = trim($message['text'] ?? '');
        if (strcasecmp($txt, 'skip') === 0) $txt = '';
        $meta['group_name'] = $txt;
        setUserState($tgId, 'add_skin_model', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_model') ]);
        return;
    }
    if ($state === 'add_skin_model') {
        $txt = trim($message['text'] ?? '');
        if (strcasecmp($txt, 'skip') === 0) $txt = '';
        $meta['model'] = $txt;
        setUserState($tgId, 'add_skin_story_fa', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_story_fa') ]);
        return;
    }
    if ($state === 'add_skin_story_fa') {
        $txt = trim($message['text'] ?? '');
        if (strcasecmp($txt, 'skip') === 0) $txt = '';
        $meta['description_fa'] = $txt;
        setUserState($tgId, 'add_skin_story_en', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_story_en') ]);
        return;
    }
    if ($state === 'add_skin_story_en') {
        $txt = trim($message['text'] ?? '');
        if (strcasecmp($txt, 'skip') === 0) $txt = '';
        $meta['description_en'] = $txt;
        setUserState($tgId, 'add_skin_story_ru', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'send_story_ru') ]);
        return;
    }
    if ($state === 'add_skin_story_ru') {
        $txt = trim($message['text'] ?? '');
        if (strcasecmp($txt, 'skip') === 0) $txt = '';
        $meta['description_ru'] = $txt;
        // Save
        $pdo = db();
        $pdo->prepare("INSERT INTO items (type, game_id, name_fa, name_en, name_ru, group_name, model, description_fa, description_en, description_ru, images, created_by, created_at, updated_at) VALUES ('skin', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")
            ->execute([
                (int)($meta['game_id'] ?? null),
                $meta['name_fa'] ?? null,
                $meta['name_en'] ?? null,
                $meta['name_ru'] ?? null,
                $meta['group_name'] ?? null,
                $meta['model'] ?? null,
                $meta['description_fa'] ?? null,
                $meta['description_en'] ?? null,
                $meta['description_ru'] ?? null,
                json_encode($meta['images'] ?? [], JSON_UNESCAPED_UNICODE),
                $tgId,
            ]);
        setUserState($tgId, null, null);
        adminLog($tgId, 'add', 'skin', (int)db()->lastInsertId(), $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'saved'), 'reply_markup' => panelMenu($lang) ]);
        return;
    }

    // Add rule wizard
    if ($state === 'add_rule_title_fa') {
        $meta['title_fa'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_rule_title_en', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_title_en') ]);
        return;
    }
    if ($state === 'add_rule_title_en') {
        $meta['title_en'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_rule_title_ru', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_title_ru') ]);
        return;
    }
    if ($state === 'add_rule_title_ru') {
        $meta['title_ru'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_rule_body_fa', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_body_fa') ]);
        return;
    }
    if ($state === 'add_rule_body_fa') {
        $meta['body_fa'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_rule_body_en', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_body_en') ]);
        return;
    }
    if ($state === 'add_rule_body_en') {
        $meta['body_en'] = trim($message['text'] ?? '');
        setUserState($tgId, 'add_rule_body_ru', $meta);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'enter_rule_body_ru') ]);
        return;
    }
    if ($state === 'add_rule_body_ru') {
        $meta['body_ru'] = trim($message['text'] ?? '');
        db()->prepare("INSERT INTO rules (title_fa, title_en, title_ru, body_fa, body_en, body_ru, sort_order) VALUES (?, ?, ?, ?, ?, ?, 0)")
            ->execute([$meta['title_fa'] ?? null, $meta['title_en'] ?? null, $meta['title_ru'] ?? null, $meta['body_fa'] ?? null, $meta['body_en'] ?? null, $meta['body_ru'] ?? null]);
        setUserState($tgId, null, null);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'saved'), 'reply_markup' => panelMenu($lang) ]);
        return;
    }

    // Sponsor/channel add
    if ($state === 'sponsor_add_wait') {
        $txt = trim($message['text'] ?? '');
        $username = ltrim($txt, '@');
        $chatIdOrNull = null;
        if (ctype_digit($txt)) {
            $chatIdOrNull = (int)$txt;
        }
        db()->prepare("INSERT INTO sponsors (chat_id, username, title, active) VALUES (?, ?, ?, 1)")
            ->execute([$chatIdOrNull ?? 0, $username ?: null, null]);
        setUserState($tgId, null, null);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'saved'), 'reply_markup' => panelMenu($lang) ]);
        return;
    }
    if ($state === 'channel_add_wait') {
        $txt = trim($message['text'] ?? '');
        $username = ltrim($txt, '@');
        $chatIdOrNull = null;
        if (ctype_digit($txt)) {
            $chatIdOrNull = (int)$txt;
        }
        db()->prepare("INSERT INTO force_channels (chat_id, username, title, active) VALUES (?, ?, ?, 1)")
            ->execute([$chatIdOrNull ?? 0, $username ?: null, null]);
        setUserState($tgId, null, null);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'saved'), 'reply_markup' => panelMenu($lang) ]);
        return;
    }
}

function showFavorites(int $chatId, int $tgId, string $lang): void {
    // MVP: show only skin favorites
    $pdo = db();
    $stmt = $pdo->prepare("SELECT i.* FROM favorites f JOIN users u ON u.id = f.user_id JOIN items i ON i.id = f.item_id WHERE u.tg_id = ? AND i.type = 'skin' ORDER BY i.id DESC LIMIT 50");
    $stmt->execute([$tgId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'not_found') ]);
        return;
    }
    foreach ($rows as $item) {
        $caption = buildItemCaption($item, $lang);
        $kb = itemInlineKeyboard($item, $lang);
        $images = json_decode($item['images'] ?: '[]', true) ?: [];
        $photo = $images[0] ?? null;
        if ($photo) {
            tg('sendPhoto', [ 'chat_id' => $chatId, 'photo' => $photo, 'caption' => $caption, 'parse_mode' => 'HTML', 'reply_markup' => $kb ]);
        } else {
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => $caption, 'reply_markup' => $kb ]);
        }
    }
}

// -------------------------
// Callback handler
// -------------------------

function handleCallback(array $cb): void {
    $from = $cb['from'];
    $tgId = (int)$from['id'];
    $user = getOrCreateUser($tgId, $from['username'] ?? null, $from['language_code'] ?? DEFAULT_LANG);
    $lang = $user['language'] ?: DEFAULT_LANG;
    $data = $cb['data'] ?? '';
    $message = $cb['message'] ?? null;
    $chatId = $message['chat']['id'] ?? null;
    $messageId = $message['message_id'] ?? null;

    if ($data === 'check_join') {
        $ok = userJoinedAllRequired($tgId);
        if ($ok) {
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => 'OK', 'show_alert' => false ]);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'main_menu'), 'reply_markup' => mainMenu($lang) ]);
        } else {
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => tr($lang, 'must_join'), 'show_alert' => true ]);
        }
        return;
    }
    if (strpos($data, 'lang:') === 0) {
        $parts = explode(':', $data);
        $newLang = $parts[1] ?? DEFAULT_LANG;
        setUserLang($tgId, $newLang);
        tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => 'OK', 'show_alert' => false ]);
        tg('editMessageReplyMarkup', [ 'chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => ['inline_keyboard' => []] ]);
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($newLang, 'welcome'), 'reply_markup' => mainMenu($newLang) ]);
        return;
    }
    if (strpos($data, 'like:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1] ?? '';
        $itemId = (int)($parts[2] ?? 0);
        // Ensure not already liked
        $pdo = db();
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE tg_id = ?");
        $uStmt->execute([$tgId]);
        $uRow = $uStmt->fetch();
        if (!$uRow) return;
        $uid = (int)$uRow['id'];
        try {
            $pdo->prepare("INSERT INTO likes (user_id, item_id) VALUES (?, ?)")->execute([$uid, $itemId]);
            $pdo->prepare("UPDATE items SET likes_count = likes_count + 1 WHERE id = ?")->execute([$itemId]);
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => tr($lang, 'liked_ok'), 'show_alert' => false ]);
            // update inline keyboard with new like count
            tg('editMessageReplyMarkup', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => itemInlineKeyboard($item, $lang),
            ]);
        } catch (Throwable $e) {
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => tr($lang, 'already_liked'), 'show_alert' => false ]);
        }
        return;
    }
    if (strpos($data, 'fav:') === 0) {
        $parts = explode(':', $data);
        $type = $parts[1] ?? '';
        $itemId = (int)($parts[2] ?? 0);
        $pdo = db();
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE tg_id = ?");
        $uStmt->execute([$tgId]);
        $uRow = $uStmt->fetch();
        if (!$uRow) return;
        $uid = (int)$uRow['id'];
        // Toggle favorite
        $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND item_id = ?");
        $check->execute([$uid, $itemId]);
        $exists = $check->fetch();
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if ($exists) {
            $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$exists['id']]);
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => tr($lang, 'removed_from_fav'), 'show_alert' => false ]);
        } else {
            $pdo->prepare("INSERT INTO favorites (user_id, item_id) VALUES (?, ?)")->execute([$uid, $itemId]);
            tg('answerCallbackQuery', [ 'callback_query_id' => $cb['id'], 'text' => tr($lang, 'added_to_fav'), 'show_alert' => false ]);
        }
        // No need to update markup text in MVP (button text static)
        return;
    }
    if (strpos($data, 'rule:') === 0) {
        $ruleId = (int)substr($data, strlen('rule:'));
        $rule = getRuleById($ruleId);
        if ($rule) {
            $txt = buildRuleText($rule, $lang);
            tg('sendMessage', [ 'chat_id' => $chatId, 'text' => $txt, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => [ [ ['text' => tr($lang, 'back'), 'callback_data' => 'rules_back'] ] ]] ]);
        }
        return;
    }
    if ($data === 'rules_back') {
        tg('sendMessage', [ 'chat_id' => $chatId, 'text' => tr($lang, 'rules_list'), 'reply_markup' => listRules($lang) ]);
        return;
    }
}

// -------------------------
// Entry point
// -------------------------

migrate();

$raw = file_get_contents('php://input');
if ($raw === '' || $raw === false) {
    echo 'OK';
    exit;
}

$update = json_decode($raw, true);
if (!is_array($update)) {
    echo 'OK';
    exit;
}

if (isset($update['message'])) {
    handleMessage($update['message']);
} elseif (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
}

echo 'OK';
?>

