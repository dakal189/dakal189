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
    $permissionsJson = json_encode(['all' => true]);
    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (chat_id, permissions, daily_limit, added_by) VALUES (?, ?, 1000, ?)");
    $stmt->execute([ADMIN_PRIMARY_CHAT_ID, $permissionsJson, ADMIN_PRIMARY_CHAT_ID]);
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

// PHP 8 compatibility helpers for PHP 7
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        if ($needle === '') { return true; }
        return strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') { return true; }
        $len = strlen($needle);
        if ($len > strlen($haystack)) { return false; }
        return substr($haystack, -$len) === $needle;
    }
}

function columnExists(string $table, string $column): bool {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) { return false; }
}

function usersIdColumn(): string {
    static $col = null;
    if ($col !== null) return $col;
    if (columnExists('users', 'chat_id')) { $col = 'chat_id'; }
    elseif (columnExists('users', 'telegram_id')) { $col = 'telegram_id'; }
    else { $col = 'chat_id'; }
    return $col;
}

function getOrCreateUser(array $from): array {
    $pdo = db();
    $chatId = (int)$from['id'];
    $first = $from['first_name'] ?? null;
    $username = $from['username'] ?? null;
    $idCol = usersIdColumn();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE {$idCol} = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();
    if (!$user) {
        $languageColExists = columnExists('users', 'language');
        if ($languageColExists) {
            $stmt = $pdo->prepare("INSERT INTO users ({$idCol}, first_name, username, language) VALUES (?, ?, ?, ?)");
            $stmt->execute([$chatId, $first, $username, DEFAULT_LANGUAGE]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users ({$idCol}, first_name, username) VALUES (?, ?, ?)");
            $stmt->execute([$chatId, $first, $username]);
        }
        $user = [
            'id' => (int)$pdo->lastInsertId(),
            'chat_id' => $chatId,
            'first_name' => $first,
            'username' => $username,
            'language' => DEFAULT_LANGUAGE,
            'is_blocked' => 0,
        ];
    } else {
        if (($user['first_name'] ?? null) !== $first || ($user['username'] ?? null) !== $username) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, username = ? WHERE {$idCol} = ?");
            $stmt->execute([$first, $username, $chatId]);
        }
    }
    return $user;
}

function getUserLanguage(int $chatId): string {
    $pdo = db();
    if (columnExists('users', 'language')) {
        $idCol = usersIdColumn();
        $stmt = $pdo->prepare("SELECT language FROM users WHERE {$idCol} = ?");
        $stmt->execute([$chatId]);
        $lang = $stmt->fetchColumn();
        if ($lang) return $lang;
    } else {
        $k = 'user_lang_' . $chatId;
        $stmt = $pdo->prepare("SELECT v FROM settings WHERE k = ?");
        $stmt->execute([$k]);
        $lang = $stmt->fetchColumn();
        if ($lang) return $lang;
    }
    return DEFAULT_LANGUAGE;
}

function setUserLanguage(int $chatId, string $lang): void {
    $pdo = db();
    if (columnExists('users', 'language')) {
        $idCol = usersIdColumn();
        $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE {$idCol} = ?");
        $stmt->execute([$lang, $chatId]);
    } else {
        $k = 'user_lang_' . $chatId;
        $stmt = $pdo->prepare("REPLACE INTO settings (k, v) VALUES (?, ?)");
        $stmt->execute([$k, $lang]);
    }
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
    $keyboard = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $c) { $line[] = ['text' => $c]; }
        $keyboard[] = $line;
    }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true];
}

function favoritesMenuKeyboard(string $lang): array {
    $rows = [
        [t('fav_cat_skins', $lang), t('fav_cat_vehicles', $lang)],
        [t('fav_cat_weapons', $lang), t('fav_cat_mappings', $lang)],
        [t('back', $lang)]
    ];
    $keyboard = [];
    foreach ($rows as $row) { $line = []; foreach ($row as $c) { $line[] = ['text' => $c]; } $keyboard[] = $line; }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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
    $keyboard = [];
    foreach ($rows as $row) { $line = []; foreach ($row as $c) { $line[] = ['text' => $c]; } $keyboard[] = $line; }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true];
}

function adminCrudKeyboard(string $lang): array {
    $rows = [[t('add', $lang), t('edit', $lang), t('delete', $lang)], [t('stats', $lang)], [t('back', $lang)]];
    $keyboard = [];
    foreach ($rows as $row) { $line = []; foreach ($row as $c) { $line[] = ['text' => $c]; } $keyboard[] = $line; }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true];
}

// ---------------------------
// Utilities
// ---------------------------

function buildLikeShareFavKeyboard(string $lang, string $type, int $tableId, int $likes, bool $isFav, string $sharePayload): array {
    $likeBtn = ['text' => t('like', $lang, ['count' => $likes]), 'callback_data' => 'like:' . $type . ':' . $tableId];
    $shareBtn = ['text' => t('share', $lang), 'callback_data' => 'share_restart'];
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
    if (!empty($row['story'])) $caption .= "\n" . htmlspecialchars($row['story']);
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
    $table = typeToTable($type);
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
            case t('main_rules', $lang): setState($chatId, 'rules_menu'); sendRulesReplyMenu($chatId, $lang); return;
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
            case 'rules_menu':
                if ($text === t('back', $lang)) {
                    setState($chatId, null);
                    tgSendMessage($chatId, t('welcome', $lang), [ 'reply_markup' => json_encode(mainMenuKeyboard($lang), JSON_UNESCAPED_UNICODE) ]);
                    return;
                }
                $r = findRuleByLocalizedTitle($lang, $text);
                if ($r) { sendRuleAsText($chatId, $lang, $r); }
                return;

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
            case 'favorites_list':
                $meta = $state['meta'] ?? [];
                if ($text === t('back', $lang)) { setState($chatId, 'favorites_menu'); tgSendMessage($chatId, t('favorites_menu', $lang), [ 'reply_markup' => json_encode(favoritesMenuKeyboard($lang), JSON_UNESCAPED_UNICODE) ]); return; }
                $labelToId = $meta['label_to_id'] ?? []; $type = $meta['type'] ?? '';
                if ($type && isset($labelToId[$text])) { if (!presentFavoriteByTypeAndTableId($type, (int)$labelToId[$text], $lang, $chatId)) tgSendMessage($chatId, t('not_found', $lang)); }
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
        $table = typeToTable($type);
        $likes = (int)$pdo->query("SELECT likes_count FROM {$table} WHERE id = " . (int)$tableIdStr)->fetchColumn();
        $keyboard = buildLikeShareFavKeyboard($lang, $type, (int)$tableIdStr, $likes, $added, '');
        tgEditReplyMarkup($cb['message']['chat']['id'], $cb['message']['message_id'], $keyboard);
        tgAnswerCallback($cb['id'], $added ? t('fav_added', $lang) : t('fav_removed', $lang));
        return;
    }
    if ($data === 'share_restart') {
        tgAnswerCallback($cb['id']);
        setState($chatId, null);
        sendLanguageIfUnsetOrShowMenu($chatId, $lang);
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
        $title = ($lang === 'fa') ? $r['title_fa'] : (($lang === 'ru') ? $r['title_ru'] : ($r['title_en'] ?? $r['title_fa'] ?? $r['title_ru']));
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
    $title = ($lang === 'fa') ? $r['title_fa'] : (($lang === 'ru') ? $r['title_ru'] : $r['title_en']);
    $text  = ($lang === 'fa') ? $r['text_fa']  : (($lang === 'ru') ? $r['text_ru']  : $r['text_en']);
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
function sendRulesReplyMenu(int $chatId, string $lang): void {
    $pdo = db();
    $rows = $pdo->query("SELECT id, title_fa, title_en, title_ru FROM rules ORDER BY id ASC")->fetchAll();
    $keyboard = [];
    if ($rows) {
        foreach ($rows as $r) {
            $title = ($lang === 'fa') ? $r['title_fa'] : (($lang === 'ru') ? $r['title_ru'] : ($r['title_en'] ?? $r['title_fa'] ?? $r['title_ru']));
            if (!$title) $title = 'Rule #' . $r['id'];
            $keyboard[] = [[ 'text' => $title ]];
        }
    }
    $keyboard[] = [[ 'text' => t('back', $lang) ]];
    tgSendMessage($chatId, t('rules_list', $lang), [ 'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE) ]);
}

function findRuleByLocalizedTitle(string $lang, string $title): ?array {
    $pdo = db();
    if ($lang === 'fa') { $stmt = $pdo->prepare("SELECT * FROM rules WHERE title_fa = ? LIMIT 1"); }
    elseif ($lang === 'ru') { $stmt = $pdo->prepare("SELECT * FROM rules WHERE title_ru = ? LIMIT 1"); }
    else { $stmt = $pdo->prepare("SELECT * FROM rules WHERE title_en = ? LIMIT 1"); }
    $stmt->execute([$title]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function sendRuleAsText(int $chatId, string $lang, array $r): void {
    $title = ($lang === 'fa') ? $r['title_fa'] : (($lang === 'ru') ? $r['title_ru'] : $r['title_en']);
    $text  = ($lang === 'fa') ? $r['text_fa']  : (($lang === 'ru') ? $r['text_ru']  : $r['text_en']);
    $title = $title ?: ('Rule #' . (int)$r['id']);
    $text  = $text ?: '';
    tgSendMessage($chatId, '<b>' . htmlspecialchars($title) . '</b>

' . $text);
}


// ---------------------------
// Favorites
// ---------------------------

function handleFavoritesMenu(int $chatId, string $lang, string $text): void {
    if ($text === t('fav_cat_skins', $lang)) {
        showFavoritesKeyboard($chatId, $lang, 'skin'); return;
    }
    if ($text === t('fav_cat_vehicles', $lang)) {
        showFavoritesKeyboard($chatId, $lang, 'vehicle'); return;
    }
    if ($text === t('fav_cat_weapons', $lang)) {
        showFavoritesKeyboard($chatId, $lang, 'weapon'); return;
    }
    if ($text === t('fav_cat_mappings', $lang)) {
        showFavoritesKeyboard($chatId, $lang, 'mapping'); return;
    }
}

function showFavoritesKeyboard(int $chatId, string $lang, string $type): void {
    $pdo = db();
    $table = typeToTable($type);
    $stmt = $pdo->prepare("SELECT f.item_table_id, t.* FROM favorites f JOIN " . $table . " t ON t.id = f.item_table_id WHERE f.user_chat_id = ? AND f.item_type = ? ORDER BY f.id DESC LIMIT 50");
    $stmt->execute([$chatId, $type]);
    $rows = $stmt->fetchAll();
    if (!$rows) { tgSendMessage($chatId, t('no_favorites', $lang)); return; }
    $keyboard = [];
    $labelToId = [];
    foreach ($rows as $r) {
        switch ($type) {
            case 'skin': $label = 'ID ' . $r['skin_id'] . ' - ' . $r['name']; break;
            case 'vehicle': $label = 'ID ' . $r['vehicle_id'] . ' - ' . $r['name']; break;
            case 'weapon': $label = 'ID ' . $r['weapon_id'] . ' - ' . $r['name']; break;
            case 'mapping': $label = 'ID ' . $r['mapping_id'] . ' - ' . $r['name']; break;
            default: $label = (string)$r['id'];
        }
        $keyboard[] = [[ 'text' => $label ]];
        $labelToId[$label] = (int)$r['id'];
    }
    $keyboard[] = [[ 'text' => t('back', $lang) ]];
    setState($chatId, 'favorites_list', ['type' => $type, 'label_to_id' => $labelToId]);
    tgSendMessage($chatId, t('favorites_menu', $lang), [ 'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE) ]);
}

function presentFavoriteByTypeAndTableId(string $type, int $tableId, string $lang, int $chatId): bool {
    $pdo = db();
    $table = typeToTable($type);
    $stmt = $pdo->prepare("SELECT * FROM " . $table . " WHERE id = ?");
    $stmt->execute([$tableId]);
    $row = $stmt->fetch();
    if (!$row) return false;
    switch ($type) {
        case 'skin': presentSkin($row, $lang, $chatId); return true;
        case 'vehicle': presentVehicle($row, $lang, $chatId); return true;
        case 'weapon': presentWeapon($row, $lang, $chatId); return true;
        case 'mapping': presentMapping($row, $lang, $chatId); return true;
        default: return false;
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
    switch ($type) {
        case 'skin': return 'skins';
        case 'vehicle': return 'vehicles';
        case 'color': return 'colors';
        case 'weather': return 'weather';
        case 'object': return 'objects';
        case 'weapon': return 'weapons';
        case 'mapping': return 'mappings';
        default: return '';
    }
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
                $pdo = db();
                $permissionsJson = json_encode(['all' => true]);
                $stmt = $pdo->prepare("INSERT IGNORE INTO admins (chat_id, permissions, daily_limit, added_by) VALUES (?, ?, 100, ?)");
                $stmt->execute([(int)$m[1], $permissionsJson, $chatId]);
                tgSendMessage($chatId, t('saved', $lang));
                return true;
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
    $idCol = usersIdColumn();
    $stmt = $pdo->prepare("SELECT language FROM users WHERE {$idCol} = ?");
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
    $keyboard = [];
    foreach ($rows as $row) { $line = []; foreach ($row as $c) { $line[] = ['text' => $c]; } $keyboard[] = $line; }
    tgSendMessage($chatId, t('start_choose_language', $lang), [
        'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => true], JSON_UNESCAPED_UNICODE)
    ]);
}

// ---------------------------
// End of file
// ---------------------------

?>