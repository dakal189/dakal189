<?php
// Core configuration, database init, Telegram helpers, Songstats helpers, i18n

declare(strict_types=1);

// Error reporting
ini_set('display_errors', '1');
error_reporting(E_ALL);

// --- Environment / Config ---
function env(string $key, ?string $default = null): ?string {
	$value = getenv($key);
	return $value === false ? $default : $value;
}

// Database
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'telegram_music_bots'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Master Bot
define('MASTER_BOT_TOKEN', env('MASTER_BOT_TOKEN', '')); // required for master.php
define('BASE_WEBHOOK_URL', rtrim(env('BASE_WEBHOOK_URL', 'https://example.com'), '/'));
define('ADMIN_USER_IDS', env('ADMIN_USER_IDS', '')); // comma-separated list of Telegram user ids

// Payments (Telegram Stars)
define('STARS_ENABLED', filter_var(env('STARS_ENABLED', '1'), FILTER_VALIDATE_BOOL));
define('PAYMENT_PROVIDER_TOKEN', env('PAYMENT_PROVIDER_TOKEN', '')); // optional for FIAT providers; Stars may not require

// RapidAPI - Songstats
define('RAPIDAPI_HOST', env('RAPIDAPI_HOST', 'songstats.p.rapidapi.com'));
define('RAPIDAPI_KEY', env('RAPIDAPI_KEY', ''));

// Optional downloads via yt-dlp + ffmpeg (set ENABLE_YTDLP_DOWNLOADS=1 to allow)
define('ENABLE_YTDLP_DOWNLOADS', filter_var(env('ENABLE_YTDLP_DOWNLOADS', '0'), FILTER_VALIDATE_BOOL));
define('YTDLP_BINARY', env('YTDLP_BINARY', '/usr/bin/yt-dlp'));
define('FFMPEG_BINARY', env('FFMPEG_BINARY', '/usr/bin/ffmpeg'));

// --- PDO (singleton) ---
function db(): PDO {
	static $pdo = null;
	if ($pdo instanceof PDO) return $pdo;
	$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
	$pdo = new PDO($dsn, DB_USER, DB_PASS, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	return $pdo;
}

// --- Database bootstrap ---
function initDatabase(): void {
	$pdo = db();
	$pdo->exec('CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT NOT NULL,
		username VARCHAR(64) NULL,
		lang VARCHAR(10) DEFAULT "en",
		is_admin TINYINT(1) DEFAULT 0,
		is_banned TINYINT(1) DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uq_users_user_id (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS bots (
		id INT AUTO_INCREMENT PRIMARY KEY,
		owner_id BIGINT NOT NULL,
		bot_token TEXT NOT NULL,
		is_vip TINYINT(1) DEFAULT 0,
		vip_level VARCHAR(20) DEFAULT NULL,
		vip_expire DATETIME DEFAULT NULL,
		lang_default VARCHAR(10) DEFAULT "en",
		public_enabled TINYINT(1) DEFAULT 1,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		KEY idx_bots_owner (owner_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS playlists (
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT NOT NULL,
		bot_id INT NOT NULL,
		track_id VARCHAR(255) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		KEY idx_playlists_user (user_id),
		KEY idx_playlists_bot (bot_id),
		KEY idx_playlists_track (track_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS rankings (
		id INT AUTO_INCREMENT PRIMARY KEY,
		track_id VARCHAR(255) NOT NULL,
		search_count INT DEFAULT 0,
		save_count INT DEFAULT 0,
		last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		UNIQUE KEY uq_rankings_track (track_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS ads (
		id INT AUTO_INCREMENT PRIMARY KEY,
		type ENUM("text","photo","video","mixed") NOT NULL DEFAULT "text",
		content TEXT NOT NULL,
		inline_keyboard TEXT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS settings (
		id INT AUTO_INCREMENT PRIMARY KEY,
		bot_id INT NOT NULL,
		welcome_text TEXT NULL,
		logo_url TEXT NULL,
		custom_keyboard TEXT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uq_settings_bot (bot_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS stats (
		id INT AUTO_INCREMENT PRIMARY KEY,
		bot_id INT NOT NULL,
		total_users INT DEFAULT 0,
		total_queries INT DEFAULT 0,
		total_playlists INT DEFAULT 0,
		last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		UNIQUE KEY uq_stats_bot (bot_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS vip_config (
		id INT AUTO_INCREMENT PRIMARY KEY,
		price_local DECIMAL(10,2) DEFAULT 0,
		price_stars INT DEFAULT 0,
		is_open TINYINT(1) DEFAULT 1,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

	// Ensure at least one vip_config row exists
	$stmt = $pdo->query('SELECT COUNT(*) AS c FROM vip_config');
	if ((int)$stmt->fetch()['c'] === 0) {
		$pdo->prepare('INSERT INTO vip_config (price_local, price_stars, is_open) VALUES (0, 100, 1)')->execute();
	}
}

// --- Helpers ---
function isAdminUser(int $userId): bool {
	if (!ADMIN_USER_IDS) return false;
	$list = array_filter(array_map('trim', explode(',', ADMIN_USER_IDS)), fn($x) => $x !== '');
	return in_array((string)$userId, $list, true);
}

function nowUtc(): string { return gmdate('Y-m-d H:i:s'); }

function upsertUser(int $userId, ?string $username, ?string $lang): void {
	$pdo = db();
	$stmt = $pdo->prepare('INSERT INTO users (user_id, username, lang) VALUES (:uid, :un, :lg)
		ON DUPLICATE KEY UPDATE username = VALUES(username), lang = COALESCE(VALUES(lang), lang)');
	$stmt->execute([':uid' => $userId, ':un' => $username, ':lg' => $lang]);
}

function setUserBanned(int $userId, bool $banned): void {
	$pdo = db();
	$stmt = $pdo->prepare('UPDATE users SET is_banned = :b WHERE user_id = :u');
	$stmt->execute([':b' => $banned ? 1 : 0, ':u' => $userId]);
}

function isUserBanned(int $userId): bool {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT is_banned FROM users WHERE user_id = :u');
\t$stmt->execute([':u' => $userId]);
	$row = $stmt->fetch();
	return $row ? ((int)$row['is_banned'] === 1) : false;
}

function getBotById(int $botId): ?array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT * FROM bots WHERE id = :id');
	$stmt->execute([':id' => $botId]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function registerBot(int $ownerId, string $botToken, string $langDefault = 'en'): int {
	$pdo = db();
	$stmt = $pdo->prepare('INSERT INTO bots (owner_id, bot_token, lang_default, public_enabled) VALUES (:o, :t, :l, 1)');
	$stmt->execute([':o' => $ownerId, ':t' => $botToken, ':l' => $langDefault]);
	return (int)$pdo->lastInsertId();
}

function setBotVip(int $botId, ?string $vipLevel, ?string $expireAt): void {
	$pdo = db();
	$stmt = $pdo->prepare('UPDATE bots SET is_vip = :v, vip_level = :lvl, vip_expire = :exp WHERE id = :id');
	$stmt->execute([
		':v' => $vipLevel ? 1 : 0,
		':lvl' => $vipLevel,
		':exp' => $expireAt,
		':id' => $botId,
	]);
}

function isVipActive(?array $botRow): bool {
	if (!$botRow) return false;
	if ((int)$botRow['is_vip'] !== 1) return false;
	if (empty($botRow['vip_expire'])) return false;
	return strtotime($botRow['vip_expire']) > time();
}

function vipLevelRank(?string $level): int {
	return match (strtolower((string)$level)) {
		'basic' => 1,
		'premium' => 2,
		'pro' => 3,
		default => 0,
	};
}

function hasVipLevel(?array $botRow, string $requiredLevel): bool {
	if (!isVipActive($botRow)) return false;
	return vipLevelRank($botRow['vip_level'] ?? '') >= vipLevelRank($requiredLevel);
}

// --- Telegram API ---
function tgRequest(string $botToken, string $method, array $params = []): array {
	$url = "https://api.telegram.org/bot{$botToken}/{$method}";
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $params,
	]);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl_error'];
	$decoded = json_decode($res, true);
	return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'invalid_json'];
}

function tgSendMessage(string $token, int $chatId, string $text, array $opts = []): array {
	$params = array_merge(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true], $opts);
	return tgRequest($token, 'sendMessage', $params);
}

function tgSendPhoto(string $token, int $chatId, string $photoUrl, array $opts = []): array {
	$params = array_merge(['chat_id' => $chatId, 'photo' => $photoUrl], $opts);
	return tgRequest($token, 'sendPhoto', $params);
}

function tgSendAudio(string $token, int $chatId, string $audioUrl, array $opts = []): array {
	$params = array_merge(['chat_id' => $chatId, 'audio' => $audioUrl], $opts);
	return tgRequest($token, 'sendAudio', $params);
}

function tgSendAudioFile(string $token, int $chatId, string $filePath, array $opts = []): array {
	if (!is_file($filePath)) return ['ok' => false, 'error' => 'file_missing'];
	$params = array_merge(['chat_id' => $chatId, 'audio' => new CURLFile($filePath)], $opts);
	return tgRequest($token, 'sendAudio', $params);
}

function tgSendVideo(string $token, int $chatId, string $videoUrl, array $opts = []): array {
	$params = array_merge(['chat_id' => $chatId, 'video' => $videoUrl], $opts);
	return tgRequest($token, 'sendVideo', $params);
}

function tgAnswerCallbackQuery(string $token, string $callbackId, string $text = '', bool $showAlert = false): array {
	$params = ['callback_query_id' => $callbackId, 'text' => $text, 'show_alert' => $showAlert ? 1 : 0];
	return tgRequest($token, 'answerCallbackQuery', $params);
}

function tgEditMessageText(string $token, int $chatId, int $messageId, string $text, array $opts = []): array {
	$params = array_merge(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'HTML'], $opts);
	return tgRequest($token, 'editMessageText', $params);
}

function tgSetWebhook(string $token, string $url): array {
	return tgRequest($token, 'setWebhook', ['url' => $url]);
}

function tgCreateInvoiceLink(string $token, array $invoice): array {
	return tgRequest($token, 'createInvoiceLink', $invoice);
}

function tgSendInvoice(string $token, array $invoice): array {
	return tgRequest($token, 'sendInvoice', $invoice);
}

function tgSetMyName(string $token, string $name, ?string $languageCode = null): array {
	$params = ['name' => $name];
	if ($languageCode) $params['language_code'] = $languageCode;
	return tgRequest($token, 'setMyName', $params);
}

function tgSetMyDescription(string $token, string $description, ?string $languageCode = null): array {
	$params = ['description' => $description];
	if ($languageCode) $params['language_code'] = $languageCode;
	return tgRequest($token, 'setMyDescription', $params);
}

// --- Songstats (RapidAPI) ---
function songstatsRequest(string $path, array $query): ?array {
	if (!RAPIDAPI_KEY) return null;
	$qs = http_build_query($query);
	$url = 'https://' . RAPIDAPI_HOST . '/' . ltrim($path, '/') . ($qs ? ('?' . $qs) : '');
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTPHEADER => [
			'x-rapidapi-host: ' . RAPIDAPI_HOST,
			'x-rapidapi-key: ' . RAPIDAPI_KEY,
		],
	]);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($res === false) return null;
	$decoded = json_decode((string)$res, true);
	return is_array($decoded) ? $decoded : null;
}

function parseSpotifyTrackId(string $input): ?string {
	$input = trim($input);
	if (preg_match('~open\.spotify\.com/track/([a-zA-Z0-9]+)~', $input, $m)) return $m[1];
	if (preg_match('~spotify:track:([a-zA-Z0-9]+)~', $input, $m)) return $m[1];
	if (preg_match('~^[a-zA-Z0-9]{8,}$~', $input)) return $input;
	return null;
}

function formatTrackMessage(array $t): string {
	$title = $t['title'] ?? ($t['name'] ?? 'Unknown');
	$artist = $t['artist'] ?? ($t['artists'][0]['name'] ?? '');
	$album = $t['album'] ?? '';
	$spotifyUrl = $t['spotify']['url'] ?? ($t['spotify_url'] ?? '');
	$lines = ["🎵 <b>{$title}</b>", $artist ? ("👤 " . htmlspecialchars($artist)) : null, $album ? ("💿 " . htmlspecialchars($album)) : null, $spotifyUrl ? ("🔗 <a href=\"{$spotifyUrl}\">Spotify</a>") : null];
	$lines = array_values(array_filter($lines));
	return implode("\n", $lines);
}

// --- i18n (15 languages) ---
$I18N = [
	'en' => [
		'master_start' => "Welcome to Music Bot Builder! Use commands to manage and create bots.",
		'user_start' => "Send a Spotify track link/ID or text to search.",
		'menu_master' => "Commands:\n/createbot <TOKEN>\n/listbots\n/vipprice <stars>\n/vipopen <on|off>\n/buyvip <bot_id> <level> <days>\n/ads_add text <text>\n/ads_list\n/users list|ban <id>|unban <id>",
		'created_bot' => "Bot registered and webhook set.",
		'vip_bought' => "VIP activated.",
		'ad_postfix' => "Sponsored",
		'no_results' => "No results. Please send a Spotify track link or ID.",
		'saved' => "Saved to playlist.",
		'top10' => "Top 10",
		'playlist_empty' => "Your playlist is empty.",
		'private_only' => "This bot is private. Access denied.",
		'playlist_header' => "Your playlist:",
		'playlist_cleared' => "Playlist cleared.",
		'lang_set' => "Language set to {code}.",
		'admin_added' => "Admin added: {id}",
		'admin_removed' => "Admin removed: {id}",
		'admins_list' => "Admins: {list}",
		'logo_set' => "Logo set.",
		'welcome_set' => "Welcome text set.",
		'not_authorized' => "You are not authorized.",
		'vip_required' => "VIP required ({level}+).",
		'stats_line' => "Users: {users} | Queries: {queries} | Saves: {saves} | VIP: {vip} {exp}",
	],
	'fa' => [
		'master_start' => "به ربات‌ساز موزیک خوش آمدید! با دستورات مدیریت و ربات بسازید.",
		'user_start' => "لینک/آیدی ترک اسپاتیفای یا متن را ارسال کنید.",
		'menu_master' => "دستورات:\n/createbot <TOKEN>\n/listbots\n/vipprice <stars>\n/vipopen <on|off>\n/buyvip <bot_id> <level> <days>\n/ads_add text <text>\n/ads_list\n/users list|ban <id>|unban <id>",
		'created_bot' => "ربات ثبت شد و وبهوک تنظیم گردید.",
		'vip_bought' => "VIP فعال شد.",
		'ad_postfix' => "حمایت شده",
		'no_results' => "نتیجه‌ای یافت نشد. لطفاً لینک یا آیدی آهنگ اسپاتیفای بفرستید.",
		'saved' => "به پلی‌لیست ذخیره شد.",
		'top10' => "۱۰ برتر",
		'playlist_empty' => "پلی‌لیست شما خالی است.",
		'private_only' => "این ربات خصوصی است. دسترسی ندارید.",
		'playlist_header' => "پلی‌لیست شما:",
		'playlist_cleared' => "پلی‌لیست پاک شد.",
		'lang_set' => "زبان به {code} تنظیم شد.",
		'admin_added' => "ادمین اضافه شد: {id}",
		'admin_removed' => "ادمین حذف شد: {id}",
		'admins_list' => "ادمین‌ها: {list}",
		'logo_set' => "لوگو تنظیم شد.",
		'welcome_set' => "متن خوش‌آمد تنظیم شد.",
		'not_authorized' => "اجازه ندارید.",
		'vip_required' => "نیازمند VIP ({level}+).",
		'stats_line' => "کاربران: {users} | جستجو: {queries} | ذخیره‌ها: {saves} | VIP: {vip} {exp}",
	],
	'ar' => ['master_start' => 'مرحباً! استخدم الأوامر لإنشاء وإدارة البوتات.', 'user_start' => 'أرسل رابط/معرّف أغنية سبوتيفاي أو نص للبحث.', 'menu_master' => 'استخدم الأوامر المذكورة.', 'created_bot' => 'تم تسجيل البوت وتعيين الويبهوك.', 'vip_bought' => 'تم تفعيل VIP.', 'ad_postfix' => 'إعلان', 'no_results' => 'لا توجد نتائج.', 'saved' => 'تم الحفظ في القائمة.', 'top10' => 'أفضل 10', 'playlist_empty' => 'القائمة فارغة.'],
	'ru' => ['master_start' => 'Добро пожаловать! Используйте команды для управления и создания ботов.', 'user_start' => 'Отправьте ссылку/ID трека Spotify или текст для поиска.', 'menu_master' => 'Используйте команды.', 'created_bot' => 'Бот зарегистрирован и вебхук установлен.', 'vip_bought' => 'VIP активирован.', 'ad_postfix' => 'Реклама', 'no_results' => 'Нет результатов.', 'saved' => 'Сохранено в плейлист.', 'top10' => 'Топ-10', 'playlist_empty' => 'Плейлист пуст.'],
	'tr' => ['master_start' => 'Hoş geldiniz! Botları yönetmek/oluşturmak için komutları kullanın.', 'user_start' => 'Spotify bağlantısı/ID veya metin gönderin.', 'menu_master' => 'Komutları kullanın.', 'created_bot' => 'Bot kaydedildi ve webhook ayarlandı.', 'vip_bought' => 'VIP etkinleştirildi.', 'ad_postfix' => 'Sponsorlu', 'no_results' => 'Sonuç yok.', 'saved' => 'Oynatma listesine kaydedildi.', 'top10' => 'En İyi 10', 'playlist_empty' => 'Oynatma listesi boş.'],
	'hi' => ['master_start' => 'स्वागत है! बॉट बनाने/प्रबंधन के लिए कमांड का उपयोग करें.', 'user_start' => 'Spotify लिंक/ID या टेक्स्ट भेजें.', 'menu_master' => 'कमांड का उपयोग करें.', 'created_bot' => 'बॉट पंजीकृत, वेबहूक सेट.', 'vip_bought' => 'VIP सक्रिय.', 'ad_postfix' => 'प्रायोजित', 'no_results' => 'कोई परिणाम नहीं.', 'saved' => 'प्लेलिस्ट में सहेजा गया.', 'top10' => 'टॉप 10', 'playlist_empty' => 'प्लेलिस्ट खाली है.'],
	'ur' => ['master_start' => 'خوش آمدید! بوٹس بنانے/انتظام کے لیے کمانڈز استعمال کریں.', 'user_start' => 'Spotify لنک/آئی ڈی یا متن بھیجیں.', 'menu_master' => 'کمانڈز استعمال کریں.', 'created_bot' => 'بوٹ رجسٹر اور ویبھوک سیٹ.', 'vip_bought' => 'VIP فعال.', 'ad_postfix' => 'اشتہار', 'no_results' => 'کوئی نتیجہ نہیں.', 'saved' => 'پلے لسٹ میں محفوظ.', 'top10' => 'ٹاپ 10', 'playlist_empty' => 'پلے لسٹ خالی ہے.'],
	'id' => ['master_start' => 'Selamat datang! Gunakan perintah untuk membuat/mengelola bot.', 'user_start' => 'Kirim tautan/ID Spotify atau teks.', 'menu_master' => 'Gunakan perintah.', 'created_bot' => 'Bot terdaftar & webhook diatur.', 'vip_bought' => 'VIP aktif.', 'ad_postfix' => 'Iklan', 'no_results' => 'Tidak ada hasil.', 'saved' => 'Tersimpan.', 'top10' => 'Top 10', 'playlist_empty' => 'Playlist kosong.'],
	'ms' => ['master_start' => 'Selamat datang! Guna arahan untuk cipta/urus bot.', 'user_start' => 'Hantar pautan/ID Spotify atau teks.', 'menu_master' => 'Guna arahan.', 'created_bot' => 'Bot didaftar & webhook diset.', 'vip_bought' => 'VIP aktif.', 'ad_postfix' => 'Tajaan', 'no_results' => 'Tiada hasil.', 'saved' => 'Disimpan.', 'top10' => 'Top 10', 'playlist_empty' => 'Senarai kosong.'],
	'zh' => ['master_start' => '欢迎！使用命令创建和管理机器人。', 'user_start' => '发送 Spotify 链接/ID 或文本搜索。', 'menu_master' => '使用命令。', 'created_bot' => '机器人已注册并设置 webhook。', 'vip_bought' => 'VIP 已激活。', 'ad_postfix' => '广告', 'no_results' => '没有结果。', 'saved' => '已保存到播放列表。', 'top10' => '前十', 'playlist_empty' => '播放列表为空。'],
	'de' => ['master_start' => 'Willkommen! Nutze Befehle zum Verwalten/Erstellen.', 'user_start' => 'Sende Spotify-Link/ID oder Text.', 'menu_master' => 'Nutze Befehle.', 'created_bot' => 'Bot registriert & Webhook gesetzt.', 'vip_bought' => 'VIP aktiviert.', 'ad_postfix' => 'Anzeige', 'no_results' => 'Keine Ergebnisse.', 'saved' => 'Gespeichert.', 'top10' => 'Top 10', 'playlist_empty' => 'Playlist leer.'],
	'fr' => ['master_start' => 'Bienvenue ! Utilisez des commandes pour gérer/créer.', 'user_start' => 'Envoyez un lien/ID Spotify ou du texte.', 'menu_master' => 'Utilisez les commandes.', 'created_bot' => 'Bot enregistré & webhook défini.', 'vip_bought' => 'VIP activé.', 'ad_postfix' => 'Sponsorisé', 'no_results' => 'Aucun résultat.', 'saved' => 'Enregistré.', 'top10' => 'Top 10', 'playlist_empty' => 'Playlist vide.'],
	'es' => ['master_start' => '¡Bienvenido! Usa comandos para gestionar/crear.', 'user_start' => 'Envía enlace/ID de Spotify o texto.', 'menu_master' => 'Usa comandos.', 'created_bot' => 'Bot registrado y webhook fijado.', 'vip_bought' => 'VIP activado.', 'ad_postfix' => 'Patrocinado', 'no_results' => 'Sin resultados.', 'saved' => 'Guardado.', 'top10' => 'Top 10', 'playlist_empty' => 'Lista vacía.'],
	'it' => ['master_start' => 'Benvenuto! Usa comandi per gestire/creare.', 'user_start' => 'Invia link/ID Spotify o testo.', 'menu_master' => 'Usa i comandi.', 'created_bot' => 'Bot registrato & webhook impostato.', 'vip_bought' => 'VIP attivo.', 'ad_postfix' => 'Sponsorizzato', 'no_results' => 'Nessun risultato.', 'saved' => 'Salvato.', 'top10' => 'Top 10', 'playlist_empty' => 'Playlist vuota.'],
	'pt' => ['master_start' => 'Bem-vindo! Use comandos para gerenciar/criar.', 'user_start' => 'Envie link/ID do Spotify ou texto.', 'menu_master' => 'Use comandos.', 'created_bot' => 'Bot registrado & webhook definido.', 'vip_bought' => 'VIP ativado.', 'ad_postfix' => 'Patrocinado', 'no_results' => 'Sem resultados.', 'saved' => 'Salvo.', 'top10' => 'Top 10', 'playlist_empty' => 'Playlist vazia.'],
];

function t(string $lang, string $key, array $repl = []): string {
	global $I18N;
	$pack = $I18N[$lang] ?? $I18N['en'];
	$text = $pack[$key] ?? ($I18N['en'][$key] ?? $key);
	if ($repl) {
		foreach ($repl as $k => $v) {
			$text = str_replace('{' . $k . '}', (string)$v, $text);
		}
	}
	return $text;
}

// --- Ads ---
function getRandomAd(): ?array {
	$pdo = db();
	$row = $pdo->query('SELECT * FROM ads ORDER BY RAND() LIMIT 1')->fetch();
	return $row ?: null;
}

// --- Stats ---
function ensureStatsRow(int $botId): void {
	$pdo = db();
	$pdo->prepare('INSERT IGNORE INTO stats (bot_id) VALUES (:b)')->execute([':b' => $botId]);
}

function bumpStat(int $botId, string $field, int $delta = 1): void {
	ensureStatsRow($botId);
	$pdo = db();
	$fieldSafe = in_array($field, ['total_users', 'total_queries', 'total_playlists'], true) ? $field : 'total_queries';
	$pdo->exec('UPDATE stats SET ' . $fieldSafe . ' = ' . $fieldSafe . ' + ' . (int)$delta . ' WHERE bot_id = ' . (int)$botId);
}

function bumpRankingSearch(string $trackId): void {
	$pdo = db();
	$pdo->prepare('INSERT INTO rankings (track_id, search_count, save_count) VALUES (:t, 1, 0)
		ON DUPLICATE KEY UPDATE search_count = search_count + 1, last_update = CURRENT_TIMESTAMP')->execute([':t' => $trackId]);
}

function bumpRankingSave(string $trackId): void {
	$pdo = db();
	$pdo->prepare('INSERT INTO rankings (track_id, search_count, save_count) VALUES (:t, 0, 1)
		ON DUPLICATE KEY UPDATE save_count = save_count + 1, last_update = CURRENT_TIMESTAMP')->execute([':t' => $trackId]);
}

// --- VIP Pricing helpers ---
function getVipConfig(): array {
	$pdo = db();
	$row = $pdo->query('SELECT * FROM vip_config ORDER BY id DESC LIMIT 1')->fetch();
	return $row ?: ['price_local' => 0, 'price_stars' => 100, 'is_open' => 1];
}

function computeVipPriceStars(string $level, int $days, array $cfg): int {
	$base = (int)($cfg['price_stars'] ?? 100);
	$mult = match (strtolower($level)) {
		'basic' => 1.0,
		'premium' => 2.0,
		'pro' => 3.0,
		default => 1.0,
	};
	$periodMult = max(1, (int)ceil($days / 30));
	return (int)round($base * $mult * $periodMult);
}

// --- Misc ---
function kbInline(array $buttons): string {
	return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE);
}

function safeText(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function logError(string $msg, array $ctx = []): void {
	$line = '[' . nowUtc() . "] ERROR: " . $msg . ($ctx ? (' ' . json_encode($ctx)) : '') . "\n";
	file_put_contents(__DIR__ . '/error.log', $line, FILE_APPEND);
}

// Per-bot settings helpers (admins, welcome, logo)
function getSettings(int $botId): array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT * FROM settings WHERE bot_id = :b');
	$stmt->execute([':b' => $botId]);
	$row = $stmt->fetch() ?: [];
	if (!$row) return [];
	$ck = $row['custom_keyboard'] ?? '';
	$row['custom_keyboard_json'] = $ck ? (json_decode($ck, true) ?: []) : [];
	return $row;
}

function ensureSettings(int $botId): void {
	$pdo = db();
	$pdo->prepare('INSERT IGNORE INTO settings (bot_id) VALUES (:b)')->execute([':b' => $botId]);
}

function getBotAdmins(int $botId): array {
	ensureSettings($botId);
	$st = getSettings($botId);
	$data = $st['custom_keyboard_json'] ?? [];
	$admins = $data['admins'] ?? [];
	$admins = array_values(array_unique(array_map('intval', $admins)));
	return $admins;
}

function setBotAdmins(int $botId, array $admins): void {
	$admins = array_values(array_unique(array_map('intval', $admins)));
	ensureSettings($botId);
	$pdo = db();
	$st = getSettings($botId);
	$data = $st['custom_keyboard_json'] ?? [];
	$data['admins'] = $admins;
	$pdo->prepare('UPDATE settings SET custom_keyboard = :j WHERE bot_id = :b')->execute([':j' => json_encode($data, JSON_UNESCAPED_UNICODE), ':b' => $botId]);
}

function isBotAdmin(int $botId, int $userId, int $ownerId): bool {
	if ($userId === $ownerId) return true;
	$admins = getBotAdmins($botId);
	return in_array($userId, $admins, true);
}

function setWelcomeText(int $botId, string $text): void {
	ensureSettings($botId);
	$pdo = db();
	$pdo->prepare('UPDATE settings SET welcome_text = :t WHERE bot_id = :b')->execute([':t' => $text, ':b' => $botId]);
}

function setLogoUrl(int $botId, string $url): void {
	ensureSettings($botId);
	$pdo = db();
	$pdo->prepare('UPDATE settings SET logo_url = :u WHERE bot_id = :b')->execute([':u' => $url, ':b' => $botId]);
}

function supportedLangCodes(): array {
	global $I18N;
	return array_keys($I18N);
}

