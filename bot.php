<?php
declare(strict_types=1);

/*
Samp Info Bot – Single-file PHP Telegram Bot

Features:
- Forced channel join
- Multilingual (fa, en, ru) with per-user preference
- Main modules: Skins, Vehicles, Colors, Weather, Objects, Weapons, Mappings
- Rules (RP) in 3 languages
- Favorites with categories
- Like and Share inline buttons
- Random suggestions
- AI color detection from user photo (OpenAI)
- Admin panel (/panel) for managing all content, sponsors, admins, settings

Notes:
- Designed as a single PHP file for deployment as a Telegram webhook endpoint
- Creates required MySQL tables automatically if missing
- Uses only cURL and GD (if available). If GD is unavailable, AI color detection sends text results only.
*/

// ---------------------------
// Configuration
// ---------------------------

const BOT_TOKEN = '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ';
const ADMIN_PRIMARY_CHAT_ID = 5641303137; // numeric

const DB_HOST = 'localhost';
const DB_NAME = 'dakallli_Test2';
const DB_USER = 'dakallli_Test2';
const DB_PASS = 'hosyarww123';

const OPENAI_API_KEY = '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';
const OPENAI_MODEL = 'gpt-4o-mini';

const DEFAULT_LANGUAGE = 'fa';
const TIMEZONE = 'Asia/Tehran';

date_default_timezone_set(TIMEZONE);

// Allow environment variable overrides
function env(string $key, $default = null) {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

// ---------------------------
// Bootstrap: Database
// ---------------------------

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . env('DB_HOST', DB_HOST) . ';dbname=' . env('DB_NAME', DB_NAME) . ';charset=utf8mb4';
    $user = env('DB_USER', DB_USER);
    $pass = env('DB_PASS', DB_PASS);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    initializeSchema($pdo);
    return $pdo;
}

function initializeSchema(PDO $pdo): void {
    $queries = [];
    $queries[] = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id BIGINT NOT NULL UNIQUE,
        first_name VARCHAR(255) NULL,
        username VARCHAR(64) NULL,
        language VARCHAR(5) NOT NULL DEFAULT '" . DEFAULT_LANGUAGE . "',
        is_blocked TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS admins (
        chat_id BIGINT NOT NULL PRIMARY KEY,
        permissions JSON NULL,
        daily_limit INT NOT NULL DEFAULT 100,
        added_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_activity DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS forced_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id BIGINT NULL,
        username VARCHAR(64) NULL,
        title VARCHAR(255) NULL,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(191) NOT NULL PRIMARY KEY,
        v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Content tables
    $queries[] = "CREATE TABLE IF NOT EXISTS skins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        skin_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        group_name VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        story TEXT NULL,
        image_url TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        image_url TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS colors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        color_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        hex_code VARCHAR(16) NOT NULL,
        image_url TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS weather (
        id INT AUTO_INCREMENT PRIMARY KEY,
        weather_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(255) NULL,
        images_json JSON NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS objects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        object_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        images_json JSON NULL,
        related_ids TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS weapons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        weapon_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        image_url TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS mappings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mapping_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        coordinates VARCHAR(255) NULL,
        tags VARCHAR(255) NULL,
        image_url TEXT NULL,
        likes_count INT NOT NULL DEFAULT 0,
        search_count INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_chat_id BIGINT NOT NULL,
        item_type VARCHAR(32) NOT NULL,
        item_table_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_chat_id, item_type, item_table_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_chat_id BIGINT NOT NULL,
        item_type VARCHAR(32) NOT NULL,
        item_table_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fav (user_chat_id, item_type, item_table_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(191) NULL,
        title_fa VARCHAR(255) NULL,
        title_en VARCHAR(255) NULL,
        title_ru VARCHAR(255) NULL,
        text_fa TEXT NULL,
        text_en TEXT NULL,
        text_ru TEXT NULL,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS user_state (
        chat_id BIGINT NOT NULL PRIMARY KEY,
        state VARCHAR(64) NULL,
        meta JSON NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }

    // Ensure primary admin exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (chat_id, permissions, daily_limit, added_by) VALUES (?, '{""all"": true}', 1000, ?) ");
    $stmt->execute([ADMIN_PRIMARY_CHAT_ID, ADMIN_PRIMARY_CHAT_ID]);
}

// ---------------------------
// Localization
// ---------------------------

function t(string $key, string $lang, array $vars = []): string {
    static $i18n = null;
    if ($i18n === null) {
        $i18n = buildTranslations();
    }
    $langMap = $i18n[$lang] ?? $i18n[DEFAULT_LANGUAGE];
    $text = $langMap[$key] ?? ($i18n[DEFAULT_LANGUAGE][$key] ?? $key);
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string)$v, $text);
    }
    return $text;
}

function buildTranslations(): array {
    return [
        'fa' => [
            'start_choose_language' => 'لطفاً زبان خود را انتخاب کنید:',
            'lang_fa' => '🇮🇷 فارسی',
            'lang_en' => '🇬🇧 English',
            'lang_ru' => '🇷🇺 Русский',
            'welcome' => 'خوش آمدید! از منوی زیر انتخاب کنید.',
            'forced_join' => "برای استفاده از ربات ابتدا در کانال‌های زیر عضو شوید:",
            'check_membership' => '🔄 بررسی عضویت',
            'main_skins' => '🧍 اسکین‌ها',
            'main_vehicles' => '🚗 وسایل نقلیه',
            'main_colors' => '🎨 رنگ‌ها',
            'main_weather' => '⛅ آب و هوا',
            'main_objects' => '📦 شئ‌ها (Object)',
            'main_weapons' => '🔫 سلاح‌ها',
            'main_mappings' => '🗺️ مپ‌ها',
            'main_ai_colors' => '🖼️ تشخیص رنگ از عکس',
            'main_rules' => '📜 قوانین RP',
            'main_favorites' => '⭐ علاقه‌مندی‌های من',
            'main_random' => '🎲 پیشنهاد تصادفی',
            'main_settings' => '⚙️ تنظیمات',
            'settings_language' => '🌐 تغییر زبان',
            'search_by_id' => '🔎 جستجو بر اساس ID',
            'search_by_name' => '🔎 جستجو بر اساس نام',
            'send_id' => 'لطفاً ID را ارسال کنید.',
            'send_name' => 'لطفاً نام را ارسال کنید.',
            'not_found' => 'چیزی یافت نشد.',
            'like' => '❤️ {count}',
            'share' => '🔁 اشتراک‌گذاری',
            'fav_add' => '⭐ افزودن به علاقه‌مندی‌ها',
            'fav_remove' => '❌ حذف از علاقه‌مندی‌ها',
            'liked_once' => 'فقط یک بار می‌توانید لایک کنید.',
            'fav_added' => 'به علاقه‌مندی‌ها اضافه شد.',
            'fav_removed' => 'از علاقه‌مندی‌ها حذف شد.',
            'skins_prompt' => 'اسکین: یک گزینه را انتخاب کنید.',
            'vehicles_prompt' => 'وسیله نقلیه: یک گزینه را انتخاب کنید.',
            'colors_prompt' => 'رنگ: لطفاً ID رنگ را ارسال کنید.',
            'weather_prompt' => 'آب و هوا: یک گزینه را انتخاب کنید.',
            'objects_prompt' => 'شیء: لطفاً ID را ارسال کنید.',
            'weapons_prompt' => 'سلاح: یک گزینه را انتخاب کنید.',
            'mappings_prompt' => 'مپ: لطفاً ID را ارسال کنید.',
            'rules_list' => 'لیست قوانین:',
            'back' => '🔙 بازگشت',
            'favorites_menu' => 'بخش علاقه‌مندی‌ها را انتخاب کنید:',
            'fav_cat_skins' => '🧍 اسکین‌ها',
            'fav_cat_vehicles' => '🚗 وسایل نقلیه',
            'fav_cat_weapons' => '🔫 سلاح‌ها',
            'fav_cat_mappings' => '🗺️ مپ‌ها',
            'no_favorites' => 'چیزی در این بخش ندارید.',
            'random_prompt' => 'یک مورد تصادفی انتخاب شد:',
            'send_photo_for_ai' => 'لطفاً یک عکس ارسال کنید تا رنگ‌ها استخراج شوند.',
            'ai_colors_result_title' => 'نتایج تشخیص رنگ:',
            'admin_panel' => 'پنل مدیریت',
            'admin_modules' => 'یک بخش را انتخاب کنید:',
            'admin_skins' => '🧍 مدیریت اسکین‌ها',
            'admin_vehicles' => '🚗 مدیریت وسایل نقلیه',
            'admin_colors' => '🎨 مدیریت رنگ‌ها',
            'admin_weather' => '⛅ مدیریت آب و هوا',
            'admin_objects' => '📦 مدیریت Object',
            'admin_weapons' => '🔫 مدیریت سلاح‌ها',
            'admin_mappings' => '🗺️ مدیریت مپ‌ها',
            'admin_rules' => '📜 مدیریت قوانین RP',
            'admin_sponsors' => '⭐ اسپانسر',
            'admin_admins' => '👥 ادمین‌ها',
            'admin_settings' => '⚙️ تنظیمات سیستمی',
            'add' => '➕ افزودن',
            'edit' => '✏️ ویرایش',
            'delete' => '❌ حذف',
            'stats' => '📊 آمار',
            'enter_value' => 'لطفاً مقدار را ارسال کنید.',
            'saved' => 'با موفقیت ذخیره شد.',
            'deleted' => 'با موفقیت حذف شد.',
            'enter_id_to_delete' => 'لطفاً ID آیتم را برای حذف ارسال کنید.',
            'sponsors_footer' => 'اسپانسرها:',
            'membership_verified' => 'عضویت شما تایید شد.',
            'panel_denied' => 'دسترسی به پنل مدیریت ندارید.',
            'lang_changed' => 'زبان شما تغییر کرد.',
        ],
        'en' => [
            'start_choose_language' => 'Please choose your language:',
            'lang_fa' => '🇮🇷 فارسی',
            'lang_en' => '🇬🇧 English',
            'lang_ru' => '🇷🇺 Русский',
            'welcome' => 'Welcome! Choose from the menu below.',
            'forced_join' => 'Please join the following channels first:',
            'check_membership' => '🔄 Check membership',
            'main_skins' => '🧍 Skins',
            'main_vehicles' => '🚗 Vehicles',
            'main_colors' => '🎨 Colors',
            'main_weather' => '⛅ Weather',
            'main_objects' => '📦 Objects',
            'main_weapons' => '🔫 Weapons',
            'main_mappings' => '🗺️ Mappings',
            'main_ai_colors' => '🖼️ Detect colors from photo',
            'main_rules' => '📜 RP Rules',
            'main_favorites' => '⭐ My favorites',
            'main_random' => '🎲 Random suggestion',
            'main_settings' => '⚙️ Settings',
            'settings_language' => '🌐 Change language',
            'search_by_id' => '🔎 Search by ID',
            'search_by_name' => '🔎 Search by name',
            'send_id' => 'Please send the ID.',
            'send_name' => 'Please send the name.',
            'not_found' => 'Nothing found.',
            'like' => '❤️ {count}',
            'share' => '🔁 Share',
            'fav_add' => '⭐ Add to favorites',
            'fav_remove' => '❌ Remove from favorites',
            'liked_once' => 'You can like only once.',
            'fav_added' => 'Added to favorites.',
            'fav_removed' => 'Removed from favorites.',
            'skins_prompt' => 'Skins: choose one option.',
            'vehicles_prompt' => 'Vehicles: choose one option.',
            'colors_prompt' => 'Colors: please send color ID.',
            'weather_prompt' => 'Weather: choose one option.',
            'objects_prompt' => 'Object: please send ID.',
            'weapons_prompt' => 'Weapons: choose one option.',
            'mappings_prompt' => 'Mapping: please send ID.',
            'rules_list' => 'Rules list:',
            'back' => '🔙 Back',
            'favorites_menu' => 'Choose favorites category:',
            'fav_cat_skins' => '🧍 Skins',
            'fav_cat_vehicles' => '🚗 Vehicles',
            'fav_cat_weapons' => '🔫 Weapons',
            'fav_cat_mappings' => '🗺️ Mappings',
            'no_favorites' => 'You have nothing here.',
            'random_prompt' => 'Random suggestion:',
            'send_photo_for_ai' => 'Please send a photo to extract colors.',
            'ai_colors_result_title' => 'Detected colors:',
            'admin_panel' => 'Admin Panel',
            'admin_modules' => 'Choose a section:',
            'admin_skins' => '🧍 Manage Skins',
            'admin_vehicles' => '🚗 Manage Vehicles',
            'admin_colors' => '🎨 Manage Colors',
            'admin_weather' => '⛅ Manage Weather',
            'admin_objects' => '📦 Manage Objects',
            'admin_weapons' => '🔫 Manage Weapons',
            'admin_mappings' => '🗺️ Manage Mappings',
            'admin_rules' => '📜 Manage RP Rules',
            'admin_sponsors' => '⭐ Sponsors',
            'admin_admins' => '👥 Admins',
            'admin_settings' => '⚙️ System Settings',
            'add' => '➕ Add',
            'edit' => '✏️ Edit',
            'delete' => '❌ Delete',
            'stats' => '📊 Stats',
            'enter_value' => 'Please send the value.',
            'saved' => 'Saved successfully.',
            'deleted' => 'Deleted successfully.',
            'enter_id_to_delete' => 'Please send item ID to delete.',
            'sponsors_footer' => 'Sponsors:',
            'membership_verified' => 'Your membership is verified.',
            'panel_denied' => 'You are not allowed to access admin panel.',
            'lang_changed' => 'Language updated.',
        ],
        'ru' => [
            'start_choose_language' => 'Пожалуйста, выберите язык:',
            'lang_fa' => '🇮🇷 فارسی',
            'lang_en' => '🇬🇧 English',
            'lang_ru' => '🇷🇺 Русский',
            'welcome' => 'Добро пожаловать! Выберите пункт меню.',
            'forced_join' => 'Сначала вступите в следующие каналы:',
            'check_membership' => '🔄 Проверить подписку',
            'main_skins' => '🧍 Скины',
            'main_vehicles' => '🚗 Транспорт',
            'main_colors' => '🎨 Цвета',
            'main_weather' => '⛅ Погода',
            'main_objects' => '📦 Объекты',
            'main_weapons' => '🔫 Оружие',
            'main_mappings' => '🗺️ Карты',
            'main_ai_colors' => '🖼️ Цвета из фото',
            'main_rules' => '📜 RP Правила',
            'main_favorites' => '⭐ Избранное',
            'main_random' => '🎲 Случайное',
            'main_settings' => '⚙️ Настройки',
            'settings_language' => '🌐 Сменить язык',
            'search_by_id' => '🔎 По ID',
            'search_by_name' => '🔎 По имени',
            'send_id' => 'Отправьте ID.',
            'send_name' => 'Отправьте имя.',
            'not_found' => 'Ничего не найдено.',
            'like' => '❤️ {count}',
            'share' => '🔁 Поделиться',
            'fav_add' => '⭐ В избранное',
            'fav_remove' => '❌ Удалить из избранного',
            'liked_once' => 'Можно лайкнуть только один раз.',
            'fav_added' => 'Добавлено в избранное.',
            'fav_removed' => 'Удалено из избранного.',
            'skins_prompt' => 'Скины: выберите опцию.',
            'vehicles_prompt' => 'Транспорт: выберите опцию.',
            'colors_prompt' => 'Цвета: отправьте ID цвета.',
            'weather_prompt' => 'Погода: выберите опцию.',
            'objects_prompt' => 'Объект: отправьте ID.',
            'weapons_prompt' => 'Оружие: выберите опцию.',
            'mappings_prompt' => 'Карта: отправьте ID.',
            'rules_list' => 'Список правил:',
            'back' => '🔙 Назад',
            'favorites_menu' => 'Выберите раздел избранного:',
            'fav_cat_skins' => '🧍 Скины',
            'fav_cat_vehicles' => '🚗 Транспорт',
            'fav_cat_weapons' => '🔫 Оружие',
            'fav_cat_mappings' => '🗺️ Карты',
            'no_favorites' => 'Тут пока пусто.',
            'random_prompt' => 'Случайное предложение:',
            'send_photo_for_ai' => 'Отправьте фото для извлечения цветов.',
            'ai_colors_result_title' => 'Обнаруженные цвета:',
            'admin_panel' => 'Панель администратора',
            'admin_modules' => 'Выберите раздел:',
            'admin_skins' => '🧍 Скины (упр.)',
            'admin_vehicles' => '🚗 Транспорт (упр.)',
            'admin_colors' => '🎨 Цвета (упр.)',
            'admin_weather' => '⛅ Погода (упр.)',
            'admin_objects' => '📦 Объекты (упр.)',
            'admin_weapons' => '🔫 Оружие (упр.)',
            'admin_mappings' => '🗺️ Карты (упр.)',
            'admin_rules' => '📜 RP Правила (упр.)',
            'admin_sponsors' => '⭐ Спонсоры',
            'admin_admins' => '👥 Админы',
            'admin_settings' => '⚙️ Настройки системы',
            'add' => '➕ Добавить',
            'edit' => '✏️ Редактировать',
            'delete' => '❌ Удалить',
            'stats' => '📊 Статистика',
            'enter_value' => 'Отправьте значение.',
            'saved' => 'Успешно сохранено.',
            'deleted' => 'Успешно удалено.',
            'enter_id_to_delete' => 'Отправьте ID для удаления.',
            'sponsors_footer' => 'Спонсоры:',
            'membership_verified' => 'Подписка подтверждена.',
            'panel_denied' => 'Нет доступа к панели.',
            'lang_changed' => 'Язык изменен.',
        ],
    ];
}

// ---------------------------
// Telegram API Helpers
// ---------------------------

function tg(string $method, array $params = []): array {
    $url = 'https://api.telegram.org/bot' . env('BOT_TOKEN', BOT_TOKEN) . '/' . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $res = curl_exec($ch);
    if ($res === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => $error];
    }
    curl_close($ch);
    $data = json_decode($res, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'invalid_json', 'raw' => $res];
    }
    return $data;
}

function tgSendMessage(int $chatId, string $text, array $options = []): void {
    $params = array_merge(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true], $options);
    tg('sendMessage', $params);
}

function tgSendPhoto(int $chatId, string $photo, string $caption = '', array $options = []): void {
    $params = array_merge(['chat_id' => $chatId, 'photo' => $photo, 'caption' => $caption, 'parse_mode' => 'HTML'], $options);
    tg('sendPhoto', $params);
}

function tgSendMediaGroup(int $chatId, array $media, array $options = []): void {
    $params = array_merge(['chat_id' => $chatId, 'media' => json_encode($media, JSON_UNESCAPED_UNICODE)], $options);
    tg('sendMediaGroup', $params);
}

function tgEditReplyMarkup(int $chatId, int $messageId, array $replyMarkup): void {
    tg('editMessageReplyMarkup', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'reply_markup' => json_encode($replyMarkup, JSON_UNESCAPED_UNICODE)
    ]);
}

function tgAnswerCallback(string $callbackId, string $text = '', bool $alert = false): void {
    tg('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $alert ? 'true' : 'false'
    ]);
}

function tgGetMe(): ?array {
    static $me = null;
    if ($me !== null) return $me;
    $res = tg('getMe');
    if (($res['ok'] ?? false) && isset($res['result'])) {
        $me = $res['result'];
        return $me;
    }
    return null;
}

function getBotUsername(): string {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT v FROM settings WHERE k = 'bot_username'");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row && !empty($row['v'])) {
        return $row['v'];
    }
    $me = tgGetMe();
    $username = $me['username'] ?? '';
    if ($username !== '') {
        $stmt = $pdo->prepare("REPLACE INTO settings (k, v) VALUES ('bot_username', ?)");
        $stmt->execute([$username]);
    }
    return $username;
}

// ---------------------------
// User and State Management
// ---------------------------

function getOrCreateUser(array $from): array {
    $pdo = db();
    $chatId = (int)$from['id'];
    $first = $from['first_name'] ?? null;
    $username = $from['username'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, first_name, username, language) VALUES (?, ?, ?, ?)");
        $stmt->execute([$chatId, $first, $username, DEFAULT_LANGUAGE]);
        $user = [
            'id' => (int)$pdo->lastInsertId(),
            'chat_id' => $chatId,
            'first_name' => $first,
            'username' => $username,
            'language' => DEFAULT_LANGUAGE,
            'is_blocked' => 0,
        ];
    } else {
        if ($user['first_name'] !== $first || $user['username'] !== $username) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, username = ? WHERE chat_id = ?");
            $stmt->execute([$first, $username, $chatId]);
        }
    }
    return $user;
}

function getUserLanguage(int $chatId): string {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT language FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $lang = $stmt->fetchColumn();
    return $lang ?: DEFAULT_LANGUAGE;
}

function setUserLanguage(int $chatId, string $lang): void {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE chat_id = ?");
    $stmt->execute([$lang, $chatId]);
}

function getState(int $chatId): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT state, meta FROM user_state WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $row = $stmt->fetch();
    if (!$row) return ['state' => null, 'meta' => []];
    $meta = [];
    if (!empty($row['meta'])) {
        $decoded = json_decode($row['meta'], true);
        if (is_array($decoded)) $meta = $decoded;
    }
    return ['state' => $row['state'], 'meta' => $meta];
}

function setState(int $chatId, ?string $state, array $meta = []): void {
    $pdo = db();
    $stmt = $pdo->prepare("REPLACE INTO user_state (chat_id, state, meta) VALUES (?, ?, ?)");
    $stmt->execute([$chatId, $state, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
}

// ---------------------------
// Forced Join
// ---------------------------

function getForcedChannels(): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, channel_id, username, title FROM forced_channels ORDER BY id ASC");
    return $stmt->fetchAll();
}

function isUserMemberAll(int $userChatId): bool {
    $channels = getForcedChannels();
    if (count($channels) === 0) return true;
    foreach ($channels as $ch) {
        $chatIdOrUsername = $ch['channel_id'] ?: ($ch['username'] ? '@' . ltrim($ch['username'], '@') : null);
        if (!$chatIdOrUsername) continue;
        $res = tg('getChatMember', ['chat_id' => $chatIdOrUsername, 'user_id' => $userChatId]);
        if (!($res['ok'] ?? false)) return false;
        $status = $res['result']['status'] ?? '';
        if (!in_array($status, ['member', 'creator', 'administrator'])) return false;
    }
    return true;
}

function requireMembershipOrPrompt(int $chatId, string $lang): bool {
    if (isUserMemberAll($chatId)) return true;
    $channels = getForcedChannels();
    $buttons = [];
    foreach ($channels as $ch) {
        $btnText = $ch['title'] ? $ch['title'] : ($ch['username'] ? '@' . $ch['username'] : (string)$ch['channel_id']);
        $url = $ch['username'] ? ('https://t.me/' . ltrim($ch['username'], '@')) : 'https://t.me/';
        $buttons[] = [['text' => $btnText, 'url' => $url]];
    }
    $buttons[] = [['text' => t('check_membership', $lang), 'callback_data' => 'check_membership']];
    tgSendMessage($chatId, t('forced_join', $lang), [
        'reply_markup' => json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE)
    ]);
    return false;
}

// ---------------------------
// Keyboards
// ---------------------------

function mainMenuKeyboard(string $lang): array {
    $rows = [
        [t('main_skins', $lang), t('main_vehicles', $lang)],
        [t('main_colors', $lang), t('main_weather', $lang)],
        [t('main_objects', $lang), t('main_weapons', $lang)],
        [t('main_mappings', $lang)],
        [t('main_ai_colors', $lang)],
        [t('main_rules', $lang)],
        [t('main_favorites', $lang), t('main_random', $lang)],
        [t('main_settings', $lang), t('settings_language', $lang)],
    ];
    return ['keyboard' => array_map(fn($r) => array_map(fn($c) => ['text' => $c], $r), $rows), 'resize_keyboard' => true];
}

function favoritesMenuKeyboard(string $lang): array {
    $rows = [
        [t('fav_cat_skins', $lang), t('fav_cat_vehicles', $lang)],
        [t('fav_cat_weapons', $lang), t('fav_cat_mappings', $lang)],
        [t('back', $lang)]
    ];
    return ['keyboard' => array_map(fn($r) => array_map(fn($c) => ['text' => $c], $r), $rows), 'resize_keyboard' => true, 'one_time_keyboard' => false];
}

function adminPanelKeyboard(string $lang): array {
    $rows = [
        [t('admin_skins', $lang), t('admin_vehicles', $lang)],
        [t('admin_colors', $lang), t('admin_weather', $lang)],
        [t('admin_objects', $lang), t('admin_weapons', $lang)],
        [t('admin_mappings', $lang), t('admin_rules', $lang)],
        [t('admin_sponsors', $lang), t('admin_admins', $lang)],
        [t('admin_settings', $lang), t('back', $lang)],
    ];
    return ['keyboard' => array_map(fn($r) => array_map(fn($c) => ['text' => $c], $r), $rows), 'resize_keyboard' => true];
}

function adminCrudKeyboard(string $lang): array {
    $rows = [[t('add', $lang), t('edit', $lang), t('delete', $lang)], [t('stats', $lang)], [t('back', $lang)]];
    return ['keyboard' => array_map(fn($r) => array_map(fn($c) => ['text' => $c], $r), $rows), 'resize_keyboard' => true];
}

// ---------------------------
// Utilities
// ---------------------------

function buildLikeShareFavKeyboard(string $lang, string $type, int $tableId, int $likes, bool $isFav, string $sharePayload): array {
    $botUsername = getBotUsername();
    $likeBtn = ['text' => t('like', $lang, ['count' => $likes]), 'callback_data' => 'like:' . $type . ':' . $tableId];
    $shareUrl = $botUsername ? ('https://t.me/' . $botUsername . '?start=' . $sharePayload) : 'https://t.me/';
    $shareBtn = ['text' => t('share', $lang), 'url' => $shareUrl];
    $favBtn = $isFav ? ['text' => t('fav_remove', $lang), 'callback_data' => 'fav:remove:' . $type . ':' . $tableId] : ['text' => t('fav_add', $lang), 'callback_data' => 'fav:add:' . $type . ':' . $tableId];
    return ['inline_keyboard' => [[$likeBtn, $shareBtn], [$favBtn]]];
}

function sponsorsFooter(): string {
    $pdo = db();
    $stmt = $pdo->query("SELECT username FROM forced_channels ORDER BY id ASC");
    $rows = $stmt->fetchAll();
    if (!$rows) return '';
    $usernames = [];
    foreach ($rows as $r) {
        if (!empty($r['username'])) $usernames[] = '@' . ltrim($r['username'], '@');
    }
    if (count($usernames) === 0) return '';
    return "\n\n" . t('sponsors_footer', getDefaultLanguage()) . ' ' . implode(' | ', $usernames);
}

function getDefaultLanguage(): string {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT v FROM settings WHERE k = 'default_language'");
    $stmt->execute();
    $v = $stmt->fetchColumn();
    return $v ?: DEFAULT_LANGUAGE;
}

// ---------------------------
// Content Retrieval Helpers
// ---------------------------

function findSkinByIdOrName($val): ?array {
    $pdo = db();
    if (is_numeric($val)) {
        $stmt = $pdo->prepare("SELECT * FROM skins WHERE skin_id = ?");
        $stmt->execute([(int)$val]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM skins WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $val . '%']);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function findVehicleByIdOrName($val): ?array {
    $pdo = db();
    if (is_numeric($val)) {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([(int)$val]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $val . '%']);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function findColorById($id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM colors WHERE color_id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function findWeatherByIdOrName($val): ?array {
    $pdo = db();
    if (is_numeric($val)) {
        $stmt = $pdo->prepare("SELECT * FROM weather WHERE weather_id = ?");
        $stmt->execute([(int)$val]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM weather WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $val . '%']);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function findObjectById($id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM objects WHERE object_id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function findWeaponByIdOrName($val): ?array {
    $pdo = db();
    if (is_numeric($val)) {
        $stmt = $pdo->prepare("SELECT * FROM weapons WHERE weapon_id = ?");
        $stmt->execute([(int)$val]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM weapons WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $val . '%']);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function findMappingById($id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM mappings WHERE mapping_id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function incrementSearchCount(string $table, int $tableId): void {
    $pdo = db();
    $pdo->prepare("UPDATE {$table} SET search_count = search_count + 1 WHERE id = ?")->execute([$tableId]);
}

// ---------------------------
// Presenters
// ---------------------------

function presentSkin(array $row, string $lang, int $chatId): void {
    $caption = "<b>Skin</b>\n";
    $caption .= "ID: <code>" . $row['skin_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['group_name'])) $caption .= "Group: " . htmlspecialchars($row['group_name']) . "\n";
    if (!empty($row['model'])) $caption .= "Model: <code>" . htmlspecialchars($row['model']) . "</code>\n";
    if (!empty($row['story'])) $caption .= "\n“" . htmlspecialchars($row['story']) . "”";
    $isFav = isFavorited($chatId, 'skin', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'skin', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_skin_' . $row['skin_id']);
    $footer = sponsorsFooter();
    $caption .= $footer;
    if (!empty($row['image_url'])) {
        tgSendPhoto($chatId, $row['image_url'], $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentVehicle(array $row, string $lang, int $chatId): void {
    $caption = "<b>Vehicle</b>\n";
    $caption .= "ID: <code>" . $row['vehicle_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['category'])) $caption .= "Category: " . htmlspecialchars($row['category']) . "\n";
    if (!empty($row['model'])) $caption .= "Model: <code>" . htmlspecialchars($row['model']) . "</code>";
    $isFav = isFavorited($chatId, 'vehicle', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'vehicle', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_vehicle_' . $row['vehicle_id']);
    $caption .= sponsorsFooter();
    if (!empty($row['image_url'])) {
        tgSendPhoto($chatId, $row['image_url'], $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentColor(array $row, string $lang, int $chatId): void {
    $caption = "<b>Color</b>\n";
    $caption .= "ID: <code>" . $row['color_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    $caption .= "Code: <code>" . htmlspecialchars($row['hex_code']) . "</code>";
    $isFav = isFavorited($chatId, 'color', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'color', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_color_' . $row['color_id']);
    $caption .= sponsorsFooter();
    if (!empty($row['image_url'])) {
        tgSendPhoto($chatId, $row['image_url'], $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentWeather(array $row, string $lang, int $chatId): void {
    $caption = "<b>Weather</b>\n";
    $caption .= "ID: <code>" . $row['weather_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['type'])) $caption .= "Type: " . htmlspecialchars($row['type']);
    $isFav = isFavorited($chatId, 'weather', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'weather', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_weather_' . $row['weather_id']);
    $caption .= sponsorsFooter();
    $images = [];
    if (!empty($row['images_json'])) {
        $decoded = json_decode($row['images_json'], true);
        if (is_array($decoded)) $images = $decoded;
    }
    if (count($images) > 0) {
        $media = [];
        foreach ($images as $img) {
            $media[] = ['type' => 'photo', 'media' => $img];
        }
        tgSendMediaGroup($chatId, $media);
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentObject(array $row, string $lang, int $chatId): void {
    $caption = "<b>Object</b>\n";
    $caption .= "ID: <code>" . $row['object_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['related_ids'])) $caption .= "\nSeen in: " . htmlspecialchars($row['related_ids']);
    $isFav = isFavorited($chatId, 'object', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'object', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_object_' . $row['object_id']);
    $caption .= sponsorsFooter();
    $images = [];
    if (!empty($row['images_json'])) {
        $decoded = json_decode($row['images_json'], true);
        if (is_array($decoded)) $images = $decoded;
    }
    if (count($images) > 0) {
        $media = [];
        foreach ($images as $img) {
            $media[] = ['type' => 'photo', 'media' => $img];
        }
        tgSendMediaGroup($chatId, $media);
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentWeapon(array $row, string $lang, int $chatId): void {
    $caption = "<b>Weapon</b>\n";
    $caption .= "ID: <code>" . $row['weapon_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['description'])) $caption .= htmlspecialchars($row['description']);
    $isFav = isFavorited($chatId, 'weapon', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'weapon', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_weapon_' . $row['weapon_id']);
    $caption .= sponsorsFooter();
    if (!empty($row['image_url'])) {
        tgSendPhoto($chatId, $row['image_url'], $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

function presentMapping(array $row, string $lang, int $chatId): void {
    $caption = "<b>Mapping</b>\n";
    $caption .= "ID: <code>" . $row['mapping_id'] . "</code>\n";
    $caption .= "Name: " . htmlspecialchars($row['name']) . "\n";
    if (!empty($row['coordinates'])) $caption .= "Coordinates: <code>" . htmlspecialchars($row['coordinates']) . "</code>\n";
    if (!empty($row['tags'])) $caption .= "Tags: " . htmlspecialchars($row['tags']);
    $isFav = isFavorited($chatId, 'mapping', (int)$row['id']);
    $keyboard = buildLikeShareFavKeyboard($lang, 'mapping', (int)$row['id'], (int)$row['likes_count'], $isFav, 'item_mapping_' . $row['mapping_id']);
    $caption .= sponsorsFooter();
    if (!empty($row['image_url'])) {
        tgSendPhoto($chatId, $row['image_url'], $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    } else {
        tgSendMessage($chatId, $caption, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]);
    }
}

// ---------------------------
// Likes and Favorites
// ---------------------------

function isFavorited(int $userChatId, string $type, int $tableId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_chat_id = ? AND item_type = ? AND item_table_id = ?");
    $stmt->execute([$userChatId, $type, $tableId]);
    return (bool)$stmt->fetchColumn();
}

function toggleFavorite(int $userChatId, string $type, int $tableId): bool {
    $pdo = db();
    if (isFavorited($userChatId, $type, $tableId)) {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_chat_id = ? AND item_type = ? AND item_table_id = ?");
        $stmt->execute([$userChatId, $type, $tableId]);
        return false;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (user_chat_id, item_type, item_table_id) VALUES (?, ?, ?)");
    $stmt->execute([$userChatId, $type, $tableId]);
    return true;
}

function likeItem(int $userChatId, string $type, int $tableId): array {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("INSERT INTO likes (user_chat_id, item_type, item_table_id) VALUES (?, ?, ?)");
        $stmt->execute([$userChatId, $type, $tableId]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_chat_id = ? AND item_type = ? AND item_table_id = ?");
        $stmt->execute([$userChatId, $type, $tableId]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'reason' => 'already'];
        }
        return ['ok' => false, 'reason' => 'error'];
    }
    $table = match ($type) {
        'skin' => 'skins', 'vehicle' => 'vehicles', 'color' => 'colors', 'weather' => 'weather', 'object' => 'objects', 'weapon' => 'weapons', 'mapping' => 'mappings', default => null
    };
    if ($table) {
        $pdo->prepare("UPDATE {$table} SET likes_count = likes_count + 1 WHERE id = ?")->execute([$tableId]);
        $likes = (int)$pdo->query("SELECT likes_count FROM {$table} WHERE id = " . (int)$tableId)->fetchColumn();
        return ['ok' => true, 'likes' => $likes];
    }
    return ['ok' => false, 'reason' => 'unknown_type'];
}

// ---------------------------
// AI Color Detection
// ---------------------------

function telegramFileToBase64(string $fileId): ?string {
    $res = tg('getFile', ['file_id' => $fileId]);
    if (!(bool)($res['ok'] ?? false)) return null;
    $path = $res['result']['file_path'] ?? null;
    if (!$path) return null;
    $url = 'https://api.telegram.org/file/bot' . env('BOT_TOKEN', BOT_TOKEN) . '/' . $path;
    $data = file_get_contents($url);
    if ($data === false) return null;
    $mime = 'image/jpeg';
    if (str_ends_with(strtolower($path), '.png')) $mime = 'image/png';
    $b64 = base64_encode($data);
    return 'data:' . $mime . ';base64,' . $b64;
}

function openaiExtractColorsFromImage(string $dataUrl): array {
    $payload = [
        'model' => env('OPENAI_MODEL', OPENAI_MODEL),
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'input_text', 'text' => 'Extract up to 8 dominant colors from the image. Return ONLY strict JSON array with items: {"hex":"#RRGGBB","name":"Color name"}. No extra text.'],
                ['type' => 'input_image', 'image_url' => $dataUrl]
            ]
        ]],
        'temperature' => 0.2,
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . env('OPENAI_API_KEY', OPENAI_API_KEY)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res = curl_exec($ch);
    if ($res === false) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    $data = json_decode($res, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    $json = trim($content);
    $colors = [];
    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) $colors = $decoded;
    }
    $normalized = [];
    foreach ($colors as $c) {
        $hex = strtoupper(trim((string)($c['hex'] ?? '')));
        if ($hex === '' || $hex[0] !== '#') continue;
        if (strlen($hex) === 4) {
            $r = $hex[1]; $g = $hex[2]; $b = $hex[3];
            $hex = '#' . $r . $r . $g . $g . $b . $b;
        }
        $name = trim((string)($c['name'] ?? 'Color'));
        $normalized[] = ['hex' => $hex, 'name' => $name];
    }
    return array_slice($normalized, 0, 8);
}

function generateColorPaletteImage(array $colors): ?string {
    if (!function_exists('imagecreatetruecolor')) return null;
    $count = max(1, count($colors));
    $swatchWidth = 160;
    $height = 180;
    $width = $swatchWidth * $count;
    $im = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefill($im, 0, 0, $white);
    for ($i = 0; $i < $count; $i++) {
        $hex = $colors[$i]['hex'];
        [$r, $g, $b] = hexToRgb($hex);
        $color = imagecolorallocate($im, $r, $g, $b);
        $x1 = $i * $swatchWidth;
        imagefilledrectangle($im, $x1, 0, $x1 + $swatchWidth - 1, 120, $color);
        $black = imagecolorallocate($im, 0, 0, 0);
        $numText = (string)($i + 1);
        imagestring($im, 5, $x1 + 6, 6, $numText, $black);
        $text = '#' . ($i + 1) . ' ' . $hex;
        imagestring($im, 3, $x1 + 6, 130, $text, $black);
        $name = $colors[$i]['name'];
        imagestring($im, 3, $x1 + 6, 150, $name, $black);
    }
    $tmp = sys_get_temp_dir() . '/palette_' . uniqid() . '.png';
    imagepng($im, $tmp);
    imagedestroy($im);
    return $tmp;
}

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0], 2));
        $g = hexdec(str_repeat($hex[1], 2));
        $b = hexdec(str_repeat($hex[2], 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return [$r, $g, $b];
}

// ---------------------------
// Admin Utilities (basic)
// ---------------------------

function isAdmin(int $chatId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT chat_id FROM admins WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    return (bool)$stmt->fetchColumn();
}

function adminUpdateActivity(int $chatId): void {
    $pdo = db();
    $pdo->prepare("UPDATE admins SET last_activity = NOW() WHERE chat_id = ?")->execute([$chatId]);
}

// ---------------------------
// Routing
// ---------------------------

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(200);
    exit('ok');
}

if (isset($update['message'])) {
    handleMessage($update['message']);
} elseif (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
}

http_response_code(200);
echo 'OK';
exit;

// ---------------------------
// Handlers
// ---------------------------

function handleMessage(array $message): void {
    $from = $message['from'] ?? [];
    if (!isset($from['id'])) return;
    $user = getOrCreateUser($from);
    $chatId = (int)$user['chat_id'];
    $lang = getUserLanguage($chatId);

    if (!empty($message['text'])) {
        $text = trim($message['text']);

        // Commands
        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6));
            if ($payload !== '') {
                handleStartPayload($chatId, $lang, $payload);
                return;
            }
            if (!requireMembershipOrPrompt($chatId, $lang)) return;
            sendLanguageIfUnsetOrShowMenu($chatId, $lang);
            return;
        }
        if ($text === '/panel') {
            if (!isAdmin($chatId)) {
                tgSendMessage($chatId, t('panel_denied', $lang));
                return;
            }
            adminUpdateActivity($chatId);
            tgSendMessage($chatId, t('admin_panel', $lang) . "\n" . t('admin_modules', $lang), [
                'reply_markup' => json_encode(adminPanelKeyboard($lang), JSON_UNESCAPED_UNICODE)
            ]);
            setState($chatId, 'admin_home');
            return;
        }
        if ($text === '/language') {
            sendLanguageChooser($chatId);
            return;
        }

        // If membership is required
        if (!requireMembershipOrPrompt($chatId, $lang)) return;

        // Language change buttons
        if (in_array($text, [t('lang_fa', $lang), t('lang_en', $lang), t('lang_ru', $lang)], true)) {
            $newLang = ($text === t('lang_fa', $lang)) ? 'fa' : (($text === t('lang_en', $lang)) ? 'en' : 'ru');
            setUserLanguage($chatId, $newLang);
            tgSendMessage($chatId, t('lang_changed', $newLang), [
                'reply_markup' => json_encode(mainMenuKeyboard($newLang), JSON_UNESCAPED_UNICODE)
            ]);
            return;
        }

        // Main menu navigation
        switch ($text) {
            case t('main_skins', $lang):
                setState($chatId, 'skins_menu');
                tgSendMessage($chatId, t('skins_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [
                        [['text' => t('search_by_id', $lang)], ['text' => t('search_by_name', $lang)]],
                        [['text' => t('back', $lang)]]
                    ], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_vehicles', $lang):
                setState($chatId, 'vehicles_menu');
                tgSendMessage($chatId, t('vehicles_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [
                        [['text' => t('search_by_id', $lang)], ['text' => t('search_by_name', $lang)]],
                        [['text' => t('back', $lang)]]
                    ], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_colors', $lang):
                setState($chatId, 'colors_wait_id');
                tgSendMessage($chatId, t('colors_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [[['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_weather', $lang):
                setState($chatId, 'weather_menu');
                tgSendMessage($chatId, t('weather_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [
                        [['text' => t('search_by_id', $lang)], ['text' => t('search_by_name', $lang)]],
                        [['text' => t('back', $lang)]]
                    ], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_objects', $lang):
                setState($chatId, 'objects_wait_id');
                tgSendMessage($chatId, t('objects_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [[['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_weapons', $lang):
                setState($chatId, 'weapons_menu');
                tgSendMessage($chatId, t('weapons_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [
                        [['text' => t('search_by_id', $lang)], ['text' => t('search_by_name', $lang)]],
                        [['text' => t('back', $lang)]]
                    ], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_mappings', $lang):
                setState($chatId, 'mappings_wait_id');
                tgSendMessage($chatId, t('mappings_prompt', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [[['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_ai_colors', $lang):
                setState($chatId, 'ai_wait_photo');
                tgSendMessage($chatId, t('send_photo_for_ai', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [[['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_rules', $lang):
                setState($chatId, 'rules_list');
                sendRulesList($chatId, $lang);
                return;
            case t('main_favorites', $lang):
                setState($chatId, 'favorites_menu');
                tgSendMessage($chatId, t('favorites_menu', $lang), [
                    'reply_markup' => json_encode(favoritesMenuKeyboard($lang), JSON_UNESCAPED_UNICODE)
                ]);
                return;
            case t('main_random', $lang):
                sendRandomSuggestion($chatId, $lang);
                return;
            case t('main_settings', $lang):
                tgSendMessage($chatId, t('main_settings', $lang), [
                    'reply_markup' => json_encode(['keyboard' => [[['text' => t('settings_language', $lang)]], [['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
                ]);
                setState($chatId, 'settings');
                return;
            case t('settings_language', $lang):
                sendLanguageChooser($chatId);
                return;
            case t('back', $lang):
                setState($chatId, null);
                tgSendMessage($chatId, t('welcome', $lang), [
                    'reply_markup' => json_encode(mainMenuKeyboard($lang), JSON_UNESCAPED_UNICODE)
                ]);
                return;
        }

        // Admin panel navigation entries
        if (isAdmin($chatId)) {
            $handled = handleAdminText($chatId, $lang, $text);
            if ($handled) return;
        }

        // Handle by state for search inputs
        $state = getState($chatId);
        switch ($state['state']) {
            case 'skins_menu':
                if ($text === t('search_by_id', $lang)) {
                    setState($chatId, 'skins_wait_id');
                    tgSendMessage($chatId, t('send_id', $lang));
                } elseif ($text === t('search_by_name', $lang)) {
                    setState($chatId, 'skins_wait_name');
                    tgSendMessage($chatId, t('send_name', $lang));
                }
                return;
            case 'skins_wait_id':
                $row = findSkinByIdOrName($text);
                if ($row) {
                    incrementSearchCount('skins', (int)$row['id']);
                    presentSkin($row, $lang, $chatId);
                } else {
                    tgSendMessage($chatId, t('not_found', $lang));
                }
                return;
            case 'skins_wait_name':
                $row = findSkinByIdOrName($text);
                if ($row) {
                    incrementSearchCount('skins', (int)$row['id']);
                    presentSkin($row, $lang, $chatId);
                } else {
                    tgSendMessage($chatId, t('not_found', $lang));
                }
                return;
            case 'vehicles_menu':
                if ($text === t('search_by_id', $lang)) {
                    setState($chatId, 'vehicles_wait_id');
                    tgSendMessage($chatId, t('send_id', $lang));
                } elseif ($text === t('search_by_name', $lang)) {
                    setState($chatId, 'vehicles_wait_name');
                    tgSendMessage($chatId, t('send_name', $lang));
                }
                return;
            case 'vehicles_wait_id':
                $row = findVehicleByIdOrName($text);
                if ($row) {
                    incrementSearchCount('vehicles', (int)$row['id']);
                    presentVehicle($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'vehicles_wait_name':
                $row = findVehicleByIdOrName($text);
                if ($row) {
                    incrementSearchCount('vehicles', (int)$row['id']);
                    presentVehicle($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'colors_wait_id':
                $row = findColorById($text);
                if ($row) {
                    incrementSearchCount('colors', (int)$row['id']);
                    presentColor($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'weather_menu':
                if ($text === t('search_by_id', $lang)) {
                    setState($chatId, 'weather_wait_id');
                    tgSendMessage($chatId, t('send_id', $lang));
                } elseif ($text === t('search_by_name', $lang)) {
                    setState($chatId, 'weather_wait_name');
                    tgSendMessage($chatId, t('send_name', $lang));
                }
                return;
            case 'weather_wait_id':
                $row = findWeatherByIdOrName($text);
                if ($row) {
                    incrementSearchCount('weather', (int)$row['id']);
                    presentWeather($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'weather_wait_name':
                $row = findWeatherByIdOrName($text);
                if ($row) {
                    incrementSearchCount('weather', (int)$row['id']);
                    presentWeather($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'objects_wait_id':
                $row = findObjectById($text);
                if ($row) {
                    incrementSearchCount('objects', (int)$row['id']);
                    presentObject($row, $lang, $chatId);
                } else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'weapons_menu':
                if ($text === t('search_by_id', $lang)) {
                    setState($chatId, 'weapons_wait_id');
                    tgSendMessage($chatId, t('send_id', $lang));
                } elseif ($text === t('search_by_name', $lang)) {
                    setState($chatId, 'weapons_wait_name');
                    tgSendMessage($chatId, t('send_name', $lang));
                }
                return;
            case 'weapons_wait_id':
                $row = findWeaponByIdOrName($text);
                if ($row) { incrementSearchCount('weapons', (int)$row['id']); presentWeapon($row, $lang, $chatId);} else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'weapons_wait_name':
                $row = findWeaponByIdOrName($text);
                if ($row) { incrementSearchCount('weapons', (int)$row['id']); presentWeapon($row, $lang, $chatId);} else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'mappings_wait_id':
                $row = findMappingById($text);
                if ($row) { incrementSearchCount('mappings', (int)$row['id']); presentMapping($row, $lang, $chatId);} else tgSendMessage($chatId, t('not_found', $lang));
                return;
            case 'favorites_menu':
                handleFavoritesMenu($chatId, $lang, $text);
                return;
            case 'ai_wait_photo':
                tgSendMessage($chatId, t('send_photo_for_ai', $lang));
                return;
        }

        // Default fallback
        tgSendMessage($chatId, t('welcome', $lang), [
            'reply_markup' => json_encode(mainMenuKeyboard($lang), JSON_UNESCAPED_UNICODE)
        ]);
        return;
    }

    // Photo handling for AI colors
    if (!empty($message['photo'])) {
        $state = getState($chatId);
        if ($state['state'] === 'ai_wait_photo') {
            $photos = $message['photo'];
            usort($photos, fn($a, $b) => ($b['file_size'] ?? 0) <=> ($a['file_size'] ?? 0));
            $fileId = $photos[0]['file_id'];
            $dataUrl = telegramFileToBase64($fileId);
            if ($dataUrl) {
                $colors = openaiExtractColorsFromImage($dataUrl);
                if (count($colors) === 0) {
                    tgSendMessage($chatId, t('not_found', $lang));
                    return;
                }
                $lines = [];
                foreach ($colors as $idx => $c) {
                    $lines[] = ($idx + 1) . '. ' . $c['hex'] . ' – ' . $c['name'];
                }
                $text = t('ai_colors_result_title', $lang) . "\n" . implode("\n", $lines);
                $tmp = generateColorPaletteImage($colors);
                if ($tmp) {
                    $curlFile = new CURLFile($tmp, 'image/png', 'palette.png');
                    tg('sendPhoto', ['chat_id' => $chatId, 'photo' => $curlFile, 'caption' => $text]);
                    @unlink($tmp);
                } else {
                    tgSendMessage($chatId, $text);
                }
                setState($chatId, null);
                return;
            }
            tgSendMessage($chatId, t('not_found', $lang));
            return;
        }
    }
}

function handleCallback(array $cb): void {
    $from = $cb['from'] ?? [];
    $chatId = (int)($from['id'] ?? 0);
    $lang = getUserLanguage($chatId);
    $data = $cb['data'] ?? '';
    if ($data === 'check_membership') {
        if (isUserMemberAll($chatId)) {
            tgAnswerCallback($cb['id'], t('membership_verified', $lang));
            tgSendMessage($chatId, t('welcome', $lang), [
                'reply_markup' => json_encode(mainMenuKeyboard($lang), JSON_UNESCAPED_UNICODE)
            ]);
        } else {
            tgAnswerCallback($cb['id'], t('forced_join', $lang));
        }
        return;
    }
    if (str_starts_with($data, 'like:')) {
        [$prefix, $type, $tableIdStr] = explode(':', $data);
        $res = likeItem($chatId, $type, (int)$tableIdStr);
        if (!$res['ok']) {
            tgAnswerCallback($cb['id'], t('liked_once', $lang), false);
        } else {
            $likes = (int)$res['likes'];
            $isFav = isFavorited($chatId, $type, (int)$tableIdStr);
            $keyboard = buildLikeShareFavKeyboard($lang, $type, (int)$tableIdStr, $likes, $isFav, '');
            tgEditReplyMarkup($cb['message']['chat']['id'], $cb['message']['message_id'], $keyboard);
            tgAnswerCallback($cb['id'], '');
        }
        return;
    }
    if (str_starts_with($data, 'fav:')) {
        [$prefix, $action, $type, $tableIdStr] = explode(':', $data);
        $added = toggleFavorite($chatId, $type, (int)$tableIdStr);
        $pdo = db();
        $table = match ($type) { 'skin' => 'skins', 'vehicle' => 'vehicles', 'color' => 'colors', 'weather' => 'weather', 'object' => 'objects', 'weapon' => 'weapons', 'mapping' => 'mappings' };
        $likes = (int)$pdo->query("SELECT likes_count FROM {$table} WHERE id = " . (int)$tableIdStr)->fetchColumn();
        $keyboard = buildLikeShareFavKeyboard($lang, $type, (int)$tableIdStr, $likes, $added, '');
        tgEditReplyMarkup($cb['message']['chat']['id'], $cb['message']['message_id'], $keyboard);
        tgAnswerCallback($cb['id'], $added ? t('fav_added', $lang) : t('fav_removed', $lang));
        return;
    }
    if (str_starts_with($data, 'rule_view:')) {
        $id = (int)substr($data, strlen('rule_view:'));
        sendRuleView($chatId, $lang, $id, $cb['message']['message_id']);
        tgAnswerCallback($cb['id']);
        return;
    }
    if ($data === 'rules_back') {
        sendRulesList($chatId, $lang, $cb['message']['message_id']);
        tgAnswerCallback($cb['id']);
        return;
    }
}

// ---------------------------
// Start payload deep-links
// ---------------------------

function handleStartPayload(int $chatId, string $lang, string $payload): void {
    if (!requireMembershipOrPrompt($chatId, $lang)) return;
    if (str_starts_with($payload, 'item_skin_')) {
        $sid = (int)substr($payload, strlen('item_skin_'));
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM skins WHERE skin_id = ?");
        $stmt->execute([$sid]);
        $row = $stmt->fetch();
        if ($row) presentSkin($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_vehicle_')) {
        $vid = (int)substr($payload, strlen('item_vehicle_'));
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([$vid]);
        $row = $stmt->fetch();
        if ($row) presentVehicle($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_color_')) {
        $cid = (int)substr($payload, strlen('item_color_'));
        $row = findColorById($cid);
        if ($row) presentColor($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_weather_')) {
        $wid = (int)substr($payload, strlen('item_weather_'));
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM weather WHERE weather_id = ?");
        $stmt->execute([$wid]);
        $row = $stmt->fetch();
        if ($row) presentWeather($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_object_')) {
        $oid = (int)substr($payload, strlen('item_object_'));
        $row = findObjectById($oid);
        if ($row) presentObject($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_weapon_')) {
        $wid = (int)substr($payload, strlen('item_weapon_'));
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM weapons WHERE weapon_id = ?");
        $stmt->execute([$wid]);
        $row = $stmt->fetch();
        if ($row) presentWeapon($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
    if (str_starts_with($payload, 'item_mapping_')) {
        $mid = (int)substr($payload, strlen('item_mapping_'));
        $row = findMappingById($mid);
        if ($row) presentMapping($row, $lang, $chatId); else tgSendMessage($chatId, t('not_found', $lang));
        return;
    }
}

// ---------------------------
// Rules
// ---------------------------

function sendRulesList(int $chatId, string $lang, ?int $editMessageId = null): void {
    $pdo = db();
    $rows = $pdo->query("SELECT id, title_fa, title_en, title_ru FROM rules ORDER BY id ASC")->fetchAll();
    $buttons = [];
    foreach ($rows as $r) {
        $title = match ($lang) { 'fa' => $r['title_fa'], 'en' => $r['title_en'], 'ru' => $r['title_ru'], default => $r['title_en'] };
        if (!$title) $title = 'Rule #' . $r['id'];
        $buttons[] = [['text' => $title, 'callback_data' => 'rule_view:' . $r['id']]];
    }
    $text = t('rules_list', $lang);
    $replyMarkup = json_encode(['inline_keyboard' => array_merge($buttons ?: [], [[['text' => t('back', $lang), 'callback_data' => 'close']]])], JSON_UNESCAPED_UNICODE);
    if ($editMessageId) {
        tg('editMessageText', ['chat_id' => $chatId, 'message_id' => $editMessageId, 'text' => $text, 'reply_markup' => $replyMarkup]);
    } else {
        tgSendMessage($chatId, $text, ['reply_markup' => $replyMarkup]);
    }
}

function sendRuleView(int $chatId, string $lang, int $id, ?int $editMessageId = null): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM rules WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) return;
    $title = match ($lang) { 'fa' => $r['title_fa'], 'en' => $r['title_en'], 'ru' => $r['title_ru'] };
    $text = match ($lang) { 'fa' => $r['text_fa'], 'en' => $r['text_en'], 'ru' => $r['text_ru'] };
    $title = $title ?: ('Rule #' . $id);
    $text = $text ?: '';
    $full = '<b>' . htmlspecialchars($title) . "</b>\n\n" . $text;
    $replyMarkup = json_encode(['inline_keyboard' => [[['text' => t('back', $lang), 'callback_data' => 'rules_back']]]], JSON_UNESCAPED_UNICODE);
    if ($editMessageId) {
        tg('editMessageText', ['chat_id' => $chatId, 'message_id' => $editMessageId, 'text' => $full, 'parse_mode' => 'HTML', 'reply_markup' => $replyMarkup]);
    } else {
        tgSendMessage($chatId, $full, ['reply_markup' => $replyMarkup]);
    }
}

// ---------------------------
// Favorites
// ---------------------------

function handleFavoritesMenu(int $chatId, string $lang, string $text): void {
    if ($text === t('fav_cat_skins', $lang)) {
        listFavorites($chatId, $lang, 'skin'); return;
    }
    if ($text === t('fav_cat_vehicles', $lang)) {
        listFavorites($chatId, $lang, 'vehicle'); return;
    }
    if ($text === t('fav_cat_weapons', $lang)) {
        listFavorites($chatId, $lang, 'weapon'); return;
    }
    if ($text === t('fav_cat_mappings', $lang)) {
        listFavorites($chatId, $lang, 'mapping'); return;
    }
}

function listFavorites(int $chatId, string $lang, string $type): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT f.item_table_id, t.* FROM favorites f JOIN " . typeToTable($type) . " t ON t.id = f.item_table_id WHERE f.user_chat_id = ? AND f.item_type = ? ORDER BY f.id DESC LIMIT 50");
    $stmt->execute([$chatId, $type]);
    $rows = $stmt->fetchAll();
    if (!$rows) { tgSendMessage($chatId, t('no_favorites', $lang)); return; }
    foreach ($rows as $row) {
        switch ($type) {
            case 'skin': presentSkin($row, $lang, $chatId); break;
            case 'vehicle': presentVehicle($row, $lang, $chatId); break;
            case 'weapon': presentWeapon($row, $lang, $chatId); break;
            case 'mapping': presentMapping($row, $lang, $chatId); break;
        }
    }
}

function typeToTable(string $type): string {
    return match ($type) {
        'skin' => 'skins', 'vehicle' => 'vehicles', 'color' => 'colors', 'weather' => 'weather', 'object' => 'objects', 'weapon' => 'weapons', 'mapping' => 'mappings', default => ''
    };
}

// ---------------------------
// Random Suggestion
// ---------------------------

function sendRandomSuggestion(int $chatId, string $lang): void {
    $pdo = db();
    $options = [
        ['table' => 'skins', 'type' => 'skin', 'present' => 'presentSkin'],
        ['table' => 'vehicles', 'type' => 'vehicle', 'present' => 'presentVehicle'],
        ['table' => 'mappings', 'type' => 'mapping', 'present' => 'presentMapping'],
    ];
    shuffle($options);
    foreach ($options as $opt) {
        $row = $pdo->query("SELECT * FROM {$opt['table']} ORDER BY RAND() LIMIT 1")->fetch();
        if ($row) {
            tgSendMessage($chatId, t('random_prompt', $lang));
            $fn = $opt['present'];
            $fn($row, $lang, $chatId);
            return;
        }
    }
    tgSendMessage($chatId, t('not_found', $lang));
}

// ---------------------------
// Admin Handlers (basic add/delete for modules, sponsors, admins)
// ---------------------------

function handleAdminText(int $chatId, string $lang, string $text): bool {
    $state = getState($chatId);
    if ($text === t('admin_skins', $lang)) { setState($chatId, 'admin_skins'); tgSendMessage($chatId, t('admin_skins', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_vehicles', $lang)) { setState($chatId, 'admin_vehicles'); tgSendMessage($chatId, t('admin_vehicles', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_colors', $lang)) { setState($chatId, 'admin_colors'); tgSendMessage($chatId, t('admin_colors', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_weather', $lang)) { setState($chatId, 'admin_weather'); tgSendMessage($chatId, t('admin_weather', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_objects', $lang)) { setState($chatId, 'admin_objects'); tgSendMessage($chatId, t('admin_objects', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_weapons', $lang)) { setState($chatId, 'admin_weapons'); tgSendMessage($chatId, t('admin_weapons', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_mappings', $lang)) { setState($chatId, 'admin_mappings'); tgSendMessage($chatId, t('admin_mappings', $lang), ['reply_markup' => json_encode(adminCrudKeyboard($lang), JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_rules', $lang)) { setState($chatId, 'admin_rules'); tgSendMessage($chatId, t('admin_rules', $lang), ['reply_markup' => json_encode(['keyboard' => [[['text' => t('add', $lang)], ['text' => t('delete', $lang)]], [['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_sponsors', $lang)) { setState($chatId, 'admin_sponsors'); tgSendMessage($chatId, t('admin_sponsors', $lang), ['reply_markup' => json_encode(['keyboard' => [[['text' => t('add', $lang)], ['text' => t('delete', $lang)]], [['text' => t('back', $lang)]]], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)]); return true; }
    if ($text === t('admin_admins', $lang)) { setState($chatId, 'admin_admins'); tgSendMessage($chatId, t('admin_admins', $lang) . "\nSend: add <chat_id> | del <chat_id>"); return true; }
    if ($text === t('admin_settings', $lang)) { setState($chatId, 'admin_settings'); tgSendMessage($chatId, t('admin_settings', $lang) . "\nSend: default_lang fa|en|ru"); return true; }

    // CRUD generic basic flows: only Add (single-step with comma-separated values) and Delete by ID for brevity
    $section = $state['state'] ?? '';
    if (in_array($text, [t('add', $lang), t('delete', $lang)], true)) {
        if ($text === t('add', $lang)) {
            switch ($section) {
                case 'admin_skins': tgSendMessage($chatId, "Send: skin_id,name,group,model,story,image_url"); setState($chatId, 'admin_skins_add'); return true;
                case 'admin_vehicles': tgSendMessage($chatId, "Send: vehicle_id,name,category,model,image_url"); setState($chatId, 'admin_vehicles_add'); return true;
                case 'admin_colors': tgSendMessage($chatId, "Send: color_id,name,hex_code,image_url(optional)"); setState($chatId, 'admin_colors_add'); return true;
                case 'admin_weather': tgSendMessage($chatId, "Send: weather_id,name,type,images(separate by space)"); setState($chatId, 'admin_weather_add'); return true;
                case 'admin_objects': tgSendMessage($chatId, "Send: object_id,name,images(separate by space),related_ids(optional)"); setState($chatId, 'admin_objects_add'); return true;
                case 'admin_weapons': tgSendMessage($chatId, "Send: weapon_id,name,description,image_url(optional)"); setState($chatId, 'admin_weapons_add'); return true;
                case 'admin_mappings': tgSendMessage($chatId, "Send: mapping_id,name,coordinates,tags,image_url(optional)"); setState($chatId, 'admin_mappings_add'); return true;
                case 'admin_rules': tgSendMessage($chatId, "Send: title_fa|title_en|title_ru\nThen next line text_fa\nNext line text_en\nNext line text_ru"); setState($chatId, 'admin_rules_add'); return true;
                case 'admin_sponsors': tgSendMessage($chatId, "Send: @channel_username (or multiple separated by space)"); setState($chatId, 'admin_sponsors_add'); return true;
            }
        } else {
            switch ($section) {
                case 'admin_skins': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_skins_del'); return true;
                case 'admin_vehicles': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_vehicles_del'); return true;
                case 'admin_colors': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_colors_del'); return true;
                case 'admin_weather': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_weather_del'); return true;
                case 'admin_objects': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_objects_del'); return true;
                case 'admin_weapons': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_weapons_del'); return true;
                case 'admin_mappings': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_mappings_del'); return true;
                case 'admin_rules': tgSendMessage($chatId, t('enter_id_to_delete', $lang)); setState($chatId, 'admin_rules_del'); return true;
                case 'admin_sponsors': tgSendMessage($chatId, t('enter_value', $lang)); setState($chatId, 'admin_sponsors_del'); return true;
            }
        }
    }

    // Handle Add/Del content inputs
    switch ($state['state']) {
        case 'admin_skins_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 6) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            [$sid, $name, $group, $model, $story, $img] = $parts;
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO skins (skin_id, name, group_name, model, story, image_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$sid, $name, $group, $model, $story, $img, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_skins'); return true;
        case 'admin_skins_del':
            $pdo = db(); $pdo->prepare("DELETE FROM skins WHERE skin_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_skins'); return true;

        case 'admin_vehicles_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 5) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            [$vid, $name, $cat, $model, $img] = $parts;
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO vehicles (vehicle_id, name, category, model, image_url, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$vid, $name, $cat, $model, $img, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_vehicles'); return true;
        case 'admin_vehicles_del':
            $pdo = db(); $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_vehicles'); return true;

        case 'admin_colors_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 3) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            $img = $parts[3] ?? null;
            [$cid, $name, $hex] = $parts;
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO colors (color_id, name, hex_code, image_url, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([(int)$cid, $name, strtoupper($hex), $img, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_colors'); return true;
        case 'admin_colors_del':
            $pdo = db(); $pdo->prepare("DELETE FROM colors WHERE color_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_colors'); return true;

        case 'admin_weather_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 4) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            [$wid, $name, $type, $imgsStr] = $parts;
            $imgs = array_values(array_filter(array_map('trim', explode(' ', $imgsStr))));
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO weather (weather_id, name, type, images_json, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([(int)$wid, $name, $type, json_encode($imgs), $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_weather'); return true;
        case 'admin_weather_del':
            $pdo = db(); $pdo->prepare("DELETE FROM weather WHERE weather_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_weather'); return true;

        case 'admin_objects_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 3) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            $oid = (int)$parts[0];
            $name = $parts[1];
            $imgs = array_values(array_filter(array_map('trim', explode(' ', $parts[2]))));
            $related = $parts[3] ?? '';
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO objects (object_id, name, images_json, related_ids, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$oid, $name, json_encode($imgs), $related, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_objects'); return true;
        case 'admin_objects_del':
            $pdo = db(); $pdo->prepare("DELETE FROM objects WHERE object_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_objects'); return true;

        case 'admin_weapons_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 3) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            $img = $parts[3] ?? null;
            [$wid, $name, $desc] = $parts;
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO weapons (weapon_id, name, description, image_url, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([(int)$wid, $name, $desc, $img, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_weapons'); return true;
        case 'admin_weapons_del':
            $pdo = db(); $pdo->prepare("DELETE FROM weapons WHERE weapon_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_weapons'); return true;

        case 'admin_mappings_add':
            $parts = array_map('trim', explode(',', $text));
            if (count($parts) < 4) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            $img = $parts[4] ?? null;
            [$mid, $name, $coords, $tags] = $parts;
            $pdo = db(); $stmt = $pdo->prepare("INSERT INTO mappings (mapping_id, name, coordinates, tags, image_url, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$mid, $name, $coords, $tags, $img, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_mappings'); return true;
        case 'admin_mappings_del':
            $pdo = db(); $pdo->prepare("DELETE FROM mappings WHERE mapping_id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_mappings'); return true;

        case 'admin_rules_add':
            $lines = explode("\n", $text);
            if (count($lines) < 4) { tgSendMessage($chatId, 'Invalid format.'); return true; }
            $titles = array_map('trim', explode('|', trim($lines[0])));
            if (count($titles) < 3) { tgSendMessage($chatId, 'Invalid titles.'); return true; }
            $text_fa = trim($lines[1]);
            $text_en = trim($lines[2]);
            $text_ru = trim($lines[3]);
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO rules (title_fa, title_en, title_ru, text_fa, text_en, text_ru, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titles[0], $titles[1], $titles[2], $text_fa, $text_en, $text_ru, $chatId]);
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_rules'); return true;
        case 'admin_rules_del':
            $pdo = db(); $pdo->prepare("DELETE FROM rules WHERE id = ?")->execute([(int)$text]); tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_rules'); return true;

        case 'admin_sponsors_add':
            $parts = preg_split('/\s+/', trim($text));
            $pdo = db();
            foreach ($parts as $p) {
                if ($p === '') continue;
                $username = ltrim($p, '@');
                $stmt = $pdo->prepare("INSERT INTO forced_channels (username, title, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$username, '@' . $username, $chatId]);
            }
            tgSendMessage($chatId, t('saved', $lang)); setState($chatId, 'admin_sponsors'); return true;
        case 'admin_sponsors_del':
            $parts = preg_split('/\s+/', trim($text));
            $pdo = db();
            foreach ($parts as $p) {
                $username = ltrim($p, '@');
                $stmt = $pdo->prepare("DELETE FROM forced_channels WHERE username = ?");
                $stmt->execute([$username]);
            }
            tgSendMessage($chatId, t('deleted', $lang)); setState($chatId, 'admin_sponsors'); return true;

        case 'admin_admins':
            if (preg_match('/^add\s+(\d{5,})$/', $text, $m)) {
                $pdo = db(); $pdo->prepare("INSERT IGNORE INTO admins (chat_id, permissions, daily_limit, added_by) VALUES (?, '{""all"":true}', 100, ?)")->execute([(int)$m[1], $chatId]); tgSendMessage($chatId, t('saved', $lang)); return true;
            }
            if (preg_match('/^del\s+(\d{5,})$/', $text, $m)) {
                $pdo = db(); $pdo->prepare("DELETE FROM admins WHERE chat_id = ?")->execute([(int)$m[1]]); tgSendMessage($chatId, t('deleted', $lang)); return true;
            }
            return true;
        case 'admin_settings':
            if (preg_match('/^default_lang\s+(fa|en|ru)$/', strtolower($text), $m)) {
                $pdo = db(); $pdo->prepare("REPLACE INTO settings (k, v) VALUES ('default_language', ?)")->execute([$m[1]]);
                tgSendMessage($chatId, t('saved', $lang)); return true;
            }
            return true;
    }

    return false;
}

// ---------------------------
// Language selection and welcome
// ---------------------------

function sendLanguageIfUnsetOrShowMenu(int $chatId, string $lang): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT language FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $cur = $stmt->fetchColumn();
    if (!$cur) {
        sendLanguageChooser($chatId);
        return;
    }
    tgSendMessage($chatId, t('welcome', $cur), [
        'reply_markup' => json_encode(mainMenuKeyboard($cur), JSON_UNESCAPED_UNICODE)
    ]);
}

function sendLanguageChooser(int $chatId): void {
    $lang = getUserLanguage($chatId);
    $rows = [
        [t('lang_fa', $lang), t('lang_en', $lang), t('lang_ru', $lang)],
    ];
    tgSendMessage($chatId, t('start_choose_language', $lang), [
        'reply_markup' => json_encode(['keyboard' => array_map(fn($r) => array_map(fn($c) => ['text' => $c], $r), $rows), 'resize_keyboard' => true, 'one_time_keyboard' => true], JSON_UNESCAPED_UNICODE)
    ]);
}

// ---------------------------
// End of file
// ---------------------------

?>

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
const BOT_TOKEN = '8114188003:AAFZU5QDdW2OE93hPxIOwIqGQL2G3FRiMqc';
const API_URL   = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';

// Main (owner) admin numeric ID
const MAIN_ADMIN_ID = 5641303137; // Replace with your Telegram numeric ID

// Channel ID for posting statements/war announcements and wheel winners (e.g., -1001234567890)
const CHANNEL_ID = -1002183534048; // Replace with your channel ID

// Database credentials
const DB_HOST = 'localhost';
const DB_NAME = 'dakallli_ModernWar';
const DB_USER = 'dakallli_ModernWar';
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
        db()->exec("UPDATE users SET money = money + daily_profit WHERE daily_profit > 0");
        setSetting('last_profit_apply_date', $today);
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
            if ($ur) { $wallet = "\n\nپول: ".$ur['money']." | سود روزانه: ".$ur['daily_profit']; }
            editMessageText($chatId, $messageId, 'دارایی های شما (' . e($country) . "):\n\n" . e($content) . $wallet, backButton('nav:home'));
            break;
        case 'shop':
            if (!$isRegistered) { answerCallback($_POST['callback_query']['id'] ?? '', 'برای استفاده باید ثبت شوید.', true); return; }
            // list categories + cart button
            $cats = db()->query("SELECT id, name FROM shop_categories ORDER BY sort_order ASC, name ASC")->fetchAll();
            $kb=[]; foreach($cats as $c){ $kb[]=[ ['text'=>$c['name'], 'callback_data'=>'user_shop:cat|id='.$c['id']] ]; }
            $kb[]=[ ['text'=>'سبد خرید','callback_data'=>'user_shop:cart'] ];
            $kb[]=[ ['text'=>'کارخانه‌های نظامی','callback_data'=>'user_shop:factories'] ];
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
    editMessageText($chatId, $messageId, 'پنل مدیریت', ['inline_keyboard' => $rows]);
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
            $kb = array_merge($kbRows, paginationKeyboard('admin:assets', $page, ($offset+count($rows))<$total, 'nav:admin')['inline_keyboard']);
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
        case 'asset_user_view':
            $uid=(int)($params['id']??0); $page=(int)($params['page']??1);
            $u = db()->prepare("SELECT username, telegram_id, country, assets_text, money, daily_profit FROM users WHERE id=?"); $u->execute([$uid]); $ur=$u->fetch(); if(!$ur){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }
            $hdr = 'کاربر: '.($ur['username']?'@'.$ur['username']:$ur['telegram_id'])."\nکشور: ".($ur['country']?:'—')."\nپول: ".$ur['money']." | سود روزانه: ".$ur['daily_profit'];
            $text = $ur['assets_text'] ?: '—';
            $kb=[ [ ['text'=>'تغییر دارایی متنی','callback_data'=>'admin:asset_user_edit|id='.$uid.'|page='.$page], ['text'=>'کپی دارایی','copy_text'=>['text'=>$text]] ], [ ['text'=>'بازگشت','callback_data'=>'admin:assets|page='.$page] ] ];
            if (!empty($messageId)) { @deleteMessage($chatId,$messageId); }
            $body = $hdr."\n\n".e($text);
            $resp = sendMessage($chatId, $body, ['inline_keyboard'=>$kb]);
            if ($resp && ($resp['ok']??false)) { setSetting('asset_msg_'.$chatId, (string)($resp['result']['message_id']??0)); }
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
            $kb=[ [ ['text'=>'ثبت کاربر','callback_data'=>'admin:user_register'] , ['text'=>'لیست کاربران','callback_data'=>'admin:user_list|page=1'] ], [ ['text'=>'بازگشت','callback_data'=>'nav:admin'] ] ];
            editMessageText($chatId,$messageId,'مدیریت کاربران',['inline_keyboard'=>$kb]);
            break;
        case 'user_register':
            setAdminState($chatId,'await_user_ident',[]);
            answerCallback($_POST['callback_query']['id'] ?? '', 'آیدی عددی یا پیام فوروارد شده کاربر را ارسال کنید');
            sendMessage($chatId,'آیدی عددی یا پیام فوروارد کاربر را ارسال کنید تا ثبت شود. سپس نام کشور را بفرستید.');
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
            $stmt=db()->prepare("SELECT username, telegram_id, country, assets_text, money, daily_profit FROM users WHERE id=?"); $stmt->execute([$id]); $u=$stmt->fetch(); if(!$u){ answerCallback($_POST['callback_query']['id']??'','کاربر یافت نشد',true); return; }
            $text = 'دارایی کاربر: '.($u['username']?'@'.$u['username']:$u['telegram_id'])."\nکشور: ".e($u['country'])."\n\n".($u['assets_text']?e($u['assets_text']):'—')."\n\nپول: ".$u['money']." | سود روزانه: ".$u['daily_profit'];
            $kb=[
                [ ['text'=>'تغییر متن دارایی','callback_data'=>'admin:user_assets_text|id='.$id.'|page='.$page] ],
                [ ['text'=>'+100','callback_data'=>'admin:user_money_delta|id='.$id.'|d=100'], ['text'=>'+1000','callback_data'=>'admin:user_money_delta|id='.$id.'|d=1000'], ['text'=>'-100','callback_data'=>'admin:user_money_delta|id='.$id.'|d=-100'], ['text'=>'-1000','callback_data'=>'admin:user_money_delta|id='.$id.'|d=-1000'] ],
                [ ['text'=>'+10 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=10'], ['text'=>'+100 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=100'], ['text'=>'-10 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=-10'], ['text'=>'-100 سود','callback_data'=>'admin:user_profit_delta|id='.$id.'|d=-100'] ],
                [ ['text'=>'تنظیم مستقیم پول','callback_data'=>'admin:user_money_set|id='.$id.'|page='.$page], ['text'=>'تنظیم مستقیم سود','callback_data'=>'admin:user_profit_set|id='.$id.'|page='.$page] ],
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
            $st = db()->prepare("SELECT id, code, percent, max_uses, used_count, per_user_limit, expires_at, disabled FROM discount_codes ORDER BY id DESC LIMIT ?,?"); $st->bindValue(1,$off,PDO::PARAM_INT); $st->bindValue(2,$per,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
            $kb=[]; foreach($rows as $r){ $label = ((int)$r['disabled']?'[خاموش] ':'').$r['code'].' | '.$r['percent'].'% | '.$r['used_count'].'/'.$r['max_uses'].' | هرکاربر: '.$r['per_user_limit'].' | تا: '.($r['expires_at']?:'∞'); $kb[]=[ ['text'=>$label, 'callback_data'=>'admin:disc_view|id='.$r['id'].'|page='.$page] ]; }
            $kb[]=[ ['text'=>'افزودن کد جدید','callback_data'=>'admin:disc_add'] ];
            foreach(paginationKeyboard('admin:disc_list',$page, ($off+count($rows))<$tot, 'admin:shop')['inline_keyboard'] as $row){ $kb[]=$row; }
            editMessageText($chatId,$messageId,'کدهای تخفیف',['inline_keyboard'=>$kb]);
            break;
        case 'disc_add':
            setAdminState($chatId,'await_disc_new',[]);
            sendMessage($chatId,"فرمت را در 5 خط بفرستید:\n1) کد یا random\n2) درصد تخفیف (1 تا 100)\n3) سقف کل استفاده (0 = نامحدود)\n4) سقف هر کاربر (>=1)\n5) تاریخ انقضا به صورت YYYY-MM-DD HH:MM یا خالی بگذارید");
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
            if (!empty($_POST['callback_query']['message']['message_id'])) deleteMessage($chatId, (int)$_POST['callback_query']['message']['message_id']);
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
            $code = trim($lines[0] ?? ''); if ($code==='') { sendMessage($chatId,'خط اول کد یا random'); return; }
            if (strtolower($code)==='random') { $code = strtoupper(bin2hex(random_bytes(3))); }
            $percent = (int)($lines[1] ?? 0); if ($percent<1||$percent>100){ sendMessage($chatId,'درصد نامعتبر'); return; }
            $maxUses = (int)($lines[2] ?? 0); $perUser = max(1,(int)($lines[3] ?? 1)); $expRaw = trim($lines[4] ?? ''); $expiresAt = $expRaw!==''? $expRaw : null;
            db()->prepare("INSERT INTO discount_codes (code,percent,max_uses,per_user_limit,expires_at,created_by) VALUES (?,?,?,?,?,?)")
              ->execute([$code,$percent,$maxUses,$perUser,$expiresAt,$chatId]);
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
            setUserState($chatId,'await_disc_code',[]);
            sendMessage($chatId,'کد تخفیف را وارد کنید.');
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
            $total = getCartTotalForUser($uid);
            // apply discount if set and valid
            $ds = getSetting('cart_disc_'.$uid); $appliedDisc = 0; if($ds){ $appliedDisc=(int)$ds; $discAmt=(int)floor($total*$appliedDisc/100); $total=max(0,$total-$discAmt); }
            if ((int)$urow['money'] < $total) { answerCallback($callback['id'],'موجودی کافی نیست', true); return; }
            db()->beginTransaction();
            try {
                db()->prepare("UPDATE users SET money = money - ? WHERE id=?")->execute([$total, $uid]);
                foreach($rows as $r){ addInventoryForUser($uid, (int)$r['item_id'], (int)$r['quantity'], (int)$r['pack_size']); $dp=(int)$r['daily_profit_per_pack']; if($dp>0) increaseUserDailyProfit($uid, $dp * (int)$r['quantity']); db()->prepare("INSERT INTO user_item_purchases (user_id,item_id,packs_bought) VALUES (?,?,0) ON DUPLICATE KEY UPDATE packs_bought=packs_bought")->execute([$uid,(int)$r['item_id']]); db()->prepare("UPDATE user_item_purchases SET packs_bought = packs_bought + ? WHERE user_id=? AND item_id=?")->execute([(int)$r['quantity'],$uid,(int)$r['item_id']]); }
                // record discount usage if any
                $dcId = getSetting('cart_disc_code_'.$uid); if ($dcId){ db()->prepare("INSERT INTO discount_usages (code_id,user_id) VALUES (?,?)")->execute([(int)$dcId,$uid]); db()->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE id=?")->execute([(int)$dcId]); setSetting('cart_disc_'.$uid,''); setSetting('cart_disc_code_'.$uid,''); }
                db()->prepare("DELETE FROM user_cart_items WHERE user_id=?")->execute([$uid]);
                db()->commit();
            } catch (Exception $e) { db()->rollBack(); if (DEBUG) { @sendMessage(MAIN_ADMIN_ID, 'Shop checkout error: ' . $e->getMessage()); } answerCallback($callback['id'],'خطا در خرید', true); return; }
            editMessageText($chatId,$messageId,'خرید انجام شد.',['inline_keyboard'=>[[['text'=>'بازگشت به فروشگاه','callback_data'=>'nav:shop']], [['text'=>'بازگشت به منو','callback_data'=>'nav:home']]]]);
            answerCallback($callback['id'],'خرید انجام شد');
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
