<?php
/**
 * Samp Info Bot - Single File PHP Telegram Bot (MySQL)
 * Mixed UX: Reply Keyboard + Inline Buttons
 * Built with the provided credentials and defaults; can be overridden via env vars.
 */

// ---- Polyfills & Config ----
if (!function_exists('str_starts_with')) {
	function str_starts_with(string $haystack, string $needle): bool {
		return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
	}
}

const SUPPORTED_LANGS = ['fa', 'en', 'ru'];

$BOT_TOKEN       = getenv('BOT_TOKEN') ?: '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ';
$BOT_USERNAME    = getenv('BOT_USERNAME') ?: '@Samp_Info_Bot';
$OWNER_ID        = getenv('OWNER_ID') ?: '5641303137';
$DB_HOST         = getenv('DB_HOST') ?: 'localhost';
$DB_PORT         = getenv('DB_PORT') ?: '3306';
$DB_NAME         = getenv('DB_NAME') ?: 'dakallli_Test2';
$DB_USER         = getenv('DB_USER') ?: 'dakallli_Test2';
$DB_PASS         = getenv('DB_PASS') ?: 'hosyarww123';
$OPENAI_API_KEY  = getenv('OPENAI_API_KEY') ?: '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';
$BASE_URL        = getenv('BASE_URL') ?: 'https://dakalll.ir/samp.php';

if (empty($BOT_TOKEN)) { http_response_code(500); echo 'BOT_TOKEN not set'; exit; }

// ---- Telegram HTTP ----
function botApi(string $method, array $params = []): array {
	global $BOT_TOKEN;
	$url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 20,
	]);
	$res = curl_exec($ch);
	if ($res === false) { error_log('curl error: ' . curl_error($ch)); curl_close($ch); return ['ok' => false, 'description' => 'curl error']; }
	curl_close($ch);
	$decoded = json_decode($res, true);
	return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'invalid json'];
}

function sendMessage(int $chatId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'text' => $text,
		'parse_mode' => 'HTML',
		'disable_web_page_preview' => true,
	], $opts);
	return botApi('sendMessage', $params);
}

function editMessageText(int $chatId, int $messageId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'message_id' => $messageId,
		'text' => $text,
		'parse_mode' => 'HTML',
		'disable_web_page_preview' => true,
	], $opts);
	return botApi('editMessageText', $params);
}

function answerCallback(string $callbackId, string $text = '', bool $showAlert = false): array {
	return botApi('answerCallbackQuery', [
		'callback_query_id' => $callbackId,
		'text' => $text,
		'show_alert' => $showAlert,
	]);
}

function sendPhoto(int $chatId, $photo, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'photo' => $photo,
		'parse_mode' => 'HTML',
	], $opts);
	return botApi('sendPhoto', $params);
}

function sendMediaGroup(int $chatId, array $media, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'media' => json_encode($media, JSON_UNESCAPED_UNICODE),
	], $opts);
	return botApi('sendMediaGroup', $params);
}

function getMeUsername(): string {
	global $BOT_USERNAME;
	if (!empty($BOT_USERNAME)) return ltrim($BOT_USERNAME, '@');
	$me = botApi('getMe');
	return (!empty($me['ok']) && !empty($me['result']['username'])) ? ltrim($me['result']['username'], '@') : '';
}

// ---- DB ----
function db(): PDO {
	static $pdo = null;
	global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
	if ($pdo instanceof PDO) return $pdo;
	$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
	$pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
	]);
	ensureSchema($pdo);
	return $pdo;
}

function ensureSchema(PDO $pdo): void {
	$pdo->exec('CREATE TABLE IF NOT EXISTS users (
		user_id BIGINT PRIMARY KEY,
		first_name VARCHAR(255) NULL,
		username VARCHAR(255) NULL,
		language VARCHAR(5) NOT NULL DEFAULT "fa",
		is_admin TINYINT(1) NOT NULL DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS required_channels (
		id INT AUTO_INCREMENT PRIMARY KEY,
		chat_id BIGINT NOT NULL,
		username VARCHAR(255) NULL,
		title VARCHAR(255) NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS items (
		id INT AUTO_INCREMENT PRIMARY KEY,
		type VARCHAR(32) NOT NULL,
		ext_id INT NULL,
		slug VARCHAR(255) NULL,
		attributes JSON NULL,
		created_by BIGINT NULL,
		published TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY type_ext (type, ext_id),
		UNIQUE KEY type_slug (type, slug)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS item_translations (
		id INT AUTO_INCREMENT PRIMARY KEY,
		item_id INT NOT NULL,
		lang VARCHAR(5) NOT NULL,
		name VARCHAR(255) NOT NULL,
		description TEXT NULL,
		biography TEXT NULL,
		UNIQUE KEY item_lang (item_id, lang),
		CONSTRAINT fk_item_translations_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS item_images (
		id INT AUTO_INCREMENT PRIMARY KEY,
		item_id INT NOT NULL,
		file_id VARCHAR(255) NULL,
		file_unique_id VARCHAR(128) NULL,
		url TEXT NULL,
		position INT NOT NULL DEFAULT 0,
		CONSTRAINT fk_item_images_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS likes (
		item_id INT NOT NULL,
		user_id BIGINT NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (item_id, user_id),
		CONSTRAINT fk_likes_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS favorites (
		item_id INT NOT NULL,
		user_id BIGINT NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (item_id, user_id),
		CONSTRAINT fk_favorites_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS rules (
		id INT AUTO_INCREMENT PRIMARY KEY,
		code VARCHAR(64) NOT NULL UNIQUE,
		created_by BIGINT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS rule_translations (
		id INT AUTO_INCREMENT PRIMARY KEY,
		rule_id INT NOT NULL,
		lang VARCHAR(5) NOT NULL,
		title VARCHAR(255) NOT NULL,
		content TEXT NOT NULL,
		UNIQUE KEY rule_lang (rule_id, lang),
		CONSTRAINT fk_rule_translations_rule FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS sponsors (
		id INT AUTO_INCREMENT PRIMARY KEY,
		chat_id BIGINT NOT NULL,
		label VARCHAR(255) NULL,
		ordering INT NOT NULL DEFAULT 0
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

	$pdo->exec('CREATE TABLE IF NOT EXISTS user_sessions (
		user_id BIGINT PRIMARY KEY,
		state VARCHAR(64) NOT NULL,
		payload JSON NULL,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ---- i18n ----
function i18n(): array {
	static $map = null;
	if ($map !== null) return $map;
	$map = [
		'fa' => [
			'welcome' => "به Samp Info Bot خوش آمدید!",
			'choose_language' => "زبان خود را انتخاب کنید:",
			'main_menu' => "یکی از گزینه‌ها را انتخاب کنید:",
			'skins' => "اسکین‌ها",
			'vehicles' => "وسایل نقلیه",
			'weapons' => "سلاح‌ها",
			'objects' => "آبجکت‌ها",
			'mapping' => "مپینگ",
			'colors' => "رنگ‌ها",
			'color_from_image' => "کد رنگ از عکس",
			'weather' => "آب‌وهوا",
			'rules' => "قوانین RP",
			'panel' => "پنل مدیریت",
			'language' => "تغییر زبان",
			'back' => "بازگشت",
			'send_skin_query' => "آیدی یا نام اسکین را بفرستید.",
			'send_vehicle_query' => "آیدی یا نام وسیله نقلیه را بفرستید.",
			'send_weapon_query' => "آیدی یا نام سلاح را بفرستید.",
			'send_object_query' => "آیدی یا نام آبجکت را بفرستید.",
			'send_mapping_query' => "آیدی یا نام مپینگ را بفرستید.",
			'send_weather_query' => "آیدی یا نام آب‌وهوا را بفرستید.",
			'not_found' => "موردی یافت نشد.",
			'like' => "❤️ لایک",
			'liked' => "لایک شد!",
			'favorite' => "⭐ علاقه‌مندی",
			'favorited' => "به علاقه‌مندی‌ها اضافه شد.",
			'unfavorited' => "از علاقه‌مندی‌ها حذف شد.",
			'share' => "↗️ اشتراک‌گذاری",
			'force_join' => "برای استفاده از ربات، لطفاً در کانال‌های زیر عضو شوید:",
			'check_join' => "بررسی عضویت",
			'you_are_in' => "عضویت تایید شد!",
			'panel_title' => "پنل مدیریت",
			'manage_channels' => "مدیریت عضویت اجباری",
			'add_item_hint' => "برای افزودن، عکس بفرستید و در کپشن دستور مناسب را بنویسید.",
			'admin_only' => "دسترسی ادمین لازم است.",
			'send_photo_with_caption' => "لطفاً عکس را با کپشن دستور ارسال کنید.",
			'usage_addskin' => "نمونه: /addskin id=21 name_fa=اسم name_en=Name name_ru=Имя group=Group model=Model bio_fa=...",
			'saved' => "ذخیره شد.",
			'rules_list' => "فهرست قوانین:",
			'favorites' => "علاقه‌مندی‌ها",
			'favorites_empty' => "هیچ موردی در علاقه‌مندی‌ها پیدا نشد.",
			'choose_type' => "نوع را انتخاب کنید:",
		],
		'en' => [
			'welcome' => "Welcome to Samp Info Bot!",
			'choose_language' => "Choose your language:",
			'main_menu' => "Choose an option:",
			'skins' => "Skins",
			'vehicles' => "Vehicles",
			'weapons' => "Weapons",
			'objects' => "Objects",
			'mapping' => "Mapping",
			'colors' => "Colors",
			'color_from_image' => "Colors from Image",
			'weather' => "Weather",
			'rules' => "RP Rules",
			'panel' => "Admin Panel",
			'language' => "Change Language",
			'back' => "Back",
			'send_skin_query' => "Send skin ID or name.",
			'send_vehicle_query' => "Send vehicle ID or name.",
			'send_weapon_query' => "Send weapon ID or name.",
			'send_object_query' => "Send object ID or name.",
			'send_mapping_query' => "Send mapping ID or name.",
			'send_weather_query' => "Send weather ID or name.",
			'not_found' => "Not found.",
			'like' => "❤️ Like",
			'liked' => "Liked!",
			'favorite' => "⭐ Favorite",
			'favorited' => "Added to favorites.",
			'unfavorited' => "Removed from favorites.",
			'share' => "↗️ Share",
			'force_join' => "Please join the channels below to use the bot:",
			'check_join' => "Re-check",
			'you_are_in' => "Membership verified!",
			'panel_title' => "Admin Panel",
			'manage_channels' => "Manage forced join",
			'add_item_hint' => "To add: send a photo with command in caption.",
			'admin_only' => "Admin access required.",
			'send_photo_with_caption' => "Please send a photo with a caption command.",
			'usage_addskin' => "Example: /addskin id=21 name_fa=... name_en=... name_ru=... group=... model=... bio_fa=...",
			'saved' => "Saved.",
			'rules_list' => "Rules list:",
			'favorites' => "Favorites",
			'favorites_empty' => "No favorites yet.",
			'choose_type' => "Choose a type:",
		],
		'ru' => [
			'welcome' => "Добро пожаловать в Samp Info Bot!",
			'choose_language' => "Выберите язык:",
			'main_menu' => "Выберите опцию:",
			'skins' => "Скины",
			'vehicles' => "Транспорт",
			'weapons' => "Оружие",
			'objects' => "Объекты",
			'mapping' => "Маппинг",
			'colors' => "Цвета",
			'color_from_image' => "Цвета из изображения",
			'weather' => "Погода",
			'rules' => "RP Правила",
			'panel' => "Панель",
			'language' => "Сменить язык",
			'back' => "Назад",
			'send_skin_query' => "Отправьте ID или имя скина.",
			'send_vehicle_query' => "Отправьте ID или имя транспорта.",
			'send_weapon_query' => "Отправьте ID или имя оружия.",
			'send_object_query' => "Отправьте ID или имя объекта.",
			'send_mapping_query' => "Отправьте ID или имя маппинга.",
			'send_weather_query' => "Отправьте ID или имя погоды.",
			'not_found' => "Не найдено.",
			'like' => "❤️ Лайк",
			'liked' => "Лайкнуто!",
			'favorite' => "⭐ Избранное",
			'favorited' => "Добавлено в избранное.",
			'unfavorited' => "Удалено из избранного.",
			'share' => "↗️ Поделиться",
			'force_join' => "Для использования бота присоединитесь к каналам:",
			'check_join' => "Проверить",
			'you_are_in' => "Участие подтверждено!",
			'panel_title' => "Панель",
			'manage_channels' => "Управление подпиской",
			'add_item_hint' => "Чтобы добавить: отправьте фото с командой в подписи.",
			'admin_only' => "Требуются права администратора.",
			'send_photo_with_caption' => "Пожалуйста, отправьте фото с подписью-командой.",
			'usage_addskin' => "Пример: /addskin id=21 name_fa=... name_en=... name_ru=... group=... model=... bio_fa=...",
			'saved' => "Сохранено.",
			'rules_list' => "Список правил:",
			'favorites' => "Избранное",
			'favorites_empty' => "В избранном пока пусто.",
			'choose_type' => "Выберите тип:",
		],
	];
	return $map;
}

function t(string $key, string $lang): string {
	$map = i18n();
	if (isset($map[$lang][$key])) return $map[$lang][$key];
	if (isset($map['en'][$key])) return $map['en'][$key];
	return $key;
}

// ---- Users/Sessions/Admin ----
function upsertUser(int $userId, string $firstName = '', string $username = null): void {
	$pdo = db();
	$pdo->prepare('INSERT INTO users (user_id, first_name, username) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), username = VALUES(username)')->execute([$userId, $firstName, $username]);
}

function getUserLang(int $userId): string {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT language FROM users WHERE user_id = ?');
	$stmt->execute([$userId]);
	$row = $stmt->fetch();
	return ($row && in_array($row['language'], SUPPORTED_LANGS, true)) ? $row['language'] : 'fa';
}

function setUserLang(int $userId, string $lang): void {
	if (!in_array($lang, SUPPORTED_LANGS, true)) return;
	$pdo = db();
	$pdo->prepare('UPDATE users SET language = ? WHERE user_id = ?')->execute([$lang, $userId]);
}

function isAdmin(int $userId): bool {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
	$stmt->execute([$userId]);
	$row = $stmt->fetch();
	return $row ? (bool)$row['is_admin'] : false;
}

function ensureOwnerAdmin(int $userId): void {
	global $OWNER_ID;
	if ((string)$userId !== (string)$OWNER_ID) return;
	$pdo = db();
	$pdo->prepare('UPDATE users SET is_admin = 1 WHERE user_id = ?')->execute([$userId]);
}

function setAdmin(int $userId, bool $flag): void {
	$pdo = db();
	$pdo->prepare('UPDATE users SET is_admin = ? WHERE user_id = ?')->execute([$flag ? 1 : 0, $userId]);
}

function setSession(int $userId, string $state, array $payload = []): void {
	$pdo = db();
	$pdo->prepare('REPLACE INTO user_sessions (user_id, state, payload) VALUES (?, ?, ?)')->execute([$userId, $state, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

function getSession(int $userId): ?array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT state, payload FROM user_sessions WHERE user_id = ?');
	$stmt->execute([$userId]);
	$row = $stmt->fetch();
	return $row ? [ 'state' => $row['state'], 'payload' => $row['payload'] ? json_decode($row['payload'], true) : [] ] : null;
}

function clearSession(int $userId): void {
	$pdo = db();
	$pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$userId]);
}

// ---- Forced Join ----
function listRequiredChannels(): array {
	$pdo = db();
	return $pdo->query('SELECT chat_id, username, title FROM required_channels ORDER BY id ASC')->fetchAll() ?: [];
}

function isUserMemberAll(int $userId): bool {
	$channels = listRequiredChannels();
	if (empty($channels)) return true;
	foreach ($channels as $ch) {
		$res = botApi('getChatMember', [ 'chat_id' => $ch['chat_id'], 'user_id' => $userId ]);
		if (empty($res['ok'])) return false;
		$status = $res['result']['status'] ?? 'left';
		if (in_array($status, ['left','kicked'], true)) return false;
	}
	return true;
}

function membershipGuard(int $chatId, int $userId, string $lang): bool {
	$channels = listRequiredChannels();
	if (empty($channels)) return true;
	if (isUserMemberAll($userId)) return true;
	$text = t('force_join', $lang) . "\n\n";
	$i = 1;
	foreach ($channels as $ch) {
		$title = $ch['title'] ?: ($ch['username'] ? '@' . $ch['username'] : (string)$ch['chat_id']);
		$url = $ch['username'] ? ('https://t.me/' . $ch['username']) : '';
		$text .= $i++ . '. ' . ($url ? "<a href=\"{$url}\">{$title}</a>" : $title) . "\n";
	}
	$kb = [ 'inline_keyboard' => [[ ['text' => t('check_join', $lang), 'callback_data' => 'check_join'] ]] ];
	sendMessage($chatId, $text, ['reply_markup' => json_encode($kb)]);
	return false;
}

// ---- Items/Search ----
function addItem(array $data, array $images, int $createdBy): int {
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$pdo->prepare('INSERT INTO items (type, ext_id, slug, attributes, created_by) VALUES (?, ?, ?, ?, ?)')
			->execute([
				$data['type'], $data['ext_id'] ?? null, $data['slug'] ?? null,
				isset($data['attributes']) ? json_encode($data['attributes'], JSON_UNESCAPED_UNICODE) : null,
				$createdBy
			]);
		$itemId = (int)$pdo->lastInsertId();
		if (!empty($data['translations'])) {
			$stmt2 = $pdo->prepare('INSERT INTO item_translations (item_id, lang, name, description, biography) VALUES (?, ?, ?, ?, ?)');
			foreach ($data['translations'] as $lang => $tr) {
				if (!in_array($lang, SUPPORTED_LANGS, true)) continue;
				$stmt2->execute([$itemId, $lang, $tr['name'] ?? '', $tr['description'] ?? null, $tr['biography'] ?? null]);
			}
		}
		if (!empty($images)) {
			$stmt3 = $pdo->prepare('INSERT INTO item_images (item_id, file_id, file_unique_id, url, position) VALUES (?, ?, ?, ?, ?)');
			$pos = 0;
			foreach ($images as $img) { $stmt3->execute([$itemId, $img['file_id'] ?? null, $img['file_unique_id'] ?? null, $img['url'] ?? null, $pos++]); }
		}
		$pdo->commit();
		return $itemId;
	} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

function findItem(string $type, string $query, string $lang): ?array {
	$pdo = db();
	$query = trim($query);
	if ($query === '') return null;
	if (ctype_digit($query)) {
		$stmt = $pdo->prepare('SELECT * FROM items WHERE type = ? AND ext_id = ? AND published = 1 LIMIT 1');
		$stmt->execute([$type, (int)$query]);
		$item = $stmt->fetch();
		if ($item) return $item;
	}
	$stmt = $pdo->prepare('SELECT i.* FROM items i JOIN item_translations t ON t.item_id = i.id WHERE i.type = ? AND i.published = 1 AND t.lang = ? AND t.name LIKE ? LIMIT 1');
	$stmt->execute([$type, $lang, '%' . $query . '%']);
	$item = $stmt->fetch();
	if ($item) return $item;
	$stmt = $pdo->prepare('SELECT i.* FROM items i JOIN item_translations t ON t.item_id = i.id WHERE i.type = ? AND i.published = 1 AND t.name LIKE ? LIMIT 1');
	$stmt->execute([$type, '%' . $query . '%']);
	return $stmt->fetch() ?: null;
}

function getItemTranslations(int $itemId): array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT lang, name, description, biography FROM item_translations WHERE item_id = ?');
	$stmt->execute([$itemId]);
	$out = [];
	foreach ($stmt->fetchAll() as $r) $out[$r['lang']] = ['name' => $r['name'], 'description' => $r['description'], 'biography' => $r['biography']];
	return $out;
}

function getItemImages(int $itemId): array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT file_id, file_unique_id, url, position FROM item_images WHERE item_id = ? ORDER BY position ASC, id ASC');
	$stmt->execute([$itemId]);
	return $stmt->fetchAll() ?: [];
}

function getItemAttributes(int $itemId): array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT attributes FROM items WHERE id = ?');
	$stmt->execute([$itemId]);
	$row = $stmt->fetch();
	return ($row && $row['attributes']) ? (json_decode($row['attributes'], true) ?: []) : [];
}

function getLikeCount(int $itemId): int {
	$pdo = db(); $stmt = $pdo->prepare('SELECT COUNT(*) c FROM likes WHERE item_id = ?'); $stmt->execute([$itemId]); return (int)$stmt->fetchColumn();
}

function hasFavorite(int $userId, int $itemId): bool {
	$pdo = db(); $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND item_id = ?'); $stmt->execute([$userId, $itemId]); return (bool)$stmt->fetchColumn();
}

function toggleFavorite(int $userId, int $itemId): bool {
	$pdo = db(); if (hasFavorite($userId, $itemId)) { $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND item_id = ?')->execute([$userId, $itemId]); return false; } $pdo->prepare('INSERT IGNORE INTO favorites (item_id, user_id) VALUES (?, ?)')->execute([$itemId, $userId]); return true;
}

function likeOnce(int $userId, int $itemId): bool { $pdo = db(); try { $pdo->prepare('INSERT INTO likes (item_id, user_id) VALUES (?, ?)')->execute([$itemId, $userId]); return true; } catch (Throwable $e) { return false; } }

function listRequiredSponsors(): array { $pdo = db(); return $pdo->query('SELECT chat_id, label FROM sponsors ORDER BY ordering ASC, id ASC')->fetchAll() ?: [];
}

function sponsorsText(): string { $rows = listRequiredSponsors(); if (empty($rows)) return ''; $parts = []; foreach ($rows as $r) $parts[] = $r['label'] ?: (string)$r['chat_id']; return "\n\n" . implode(' | ', $parts); }

function deepLinkToItem(int $itemId): string { $u = getMeUsername(); return $u ? ('https://t.me/' . $u . '?start=item_' . $itemId) : ''; }

function buildItemCaption(array $item, array $trans, string $lang): string {
	$t = $trans[$lang] ?? ($trans['en'] ?? reset($trans) ?: ['name' => '', 'description' => null, 'biography' => null]);
	$attrs = getItemAttributes((int)$item['id']);
	$lines = [];
	$lines[] = '<b>' . htmlspecialchars($t['name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
	if (!empty($item['ext_id'])) $lines[] = 'ID: ' . (int)$item['ext_id'];
	if ($item['type'] === 'skin') { if (!empty($attrs['group'])) $lines[] = 'Group: ' . htmlspecialchars($attrs['group']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'vehicle') { if (!empty($attrs['category'])) $lines[] = 'Category: ' . htmlspecialchars($attrs['category']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'weapon') { if (!empty($attrs['category'])) $lines[] = 'Category: ' . htmlspecialchars($attrs['category']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'object') { if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'mapping') { if (!empty($attrs['coordinates'])) $lines[] = 'Coordinates: ' . htmlspecialchars($attrs['coordinates']); if (!empty($attrs['coords'])) $lines[] = 'Coordinates: ' . htmlspecialchars($attrs['coords']); }
	elseif ($item['type'] === 'weather') { if (!empty($attrs['type'])) $lines[] = 'Type: ' . htmlspecialchars($attrs['type']); }
	if (!empty($t['biography'])) $lines[] = '"' . htmlspecialchars($t['biography']) . '"';
	$st = sponsorsText(); if (!empty($st)) $lines[] = $st; return implode("\n", $lines);
}

function itemInlineKeyboard(int $itemId, string $lang, int $userId): array {
	$likeCount = getLikeCount($itemId);
	$fav = hasFavorite($userId, $itemId);
	$favText = $fav ? '⭐' : t('favorite', $lang);
	$likeText = t('like', $lang) . ' ' . ($likeCount > 0 ? (string)$likeCount : '');
	$shareUrl = deepLinkToItem($itemId);
	return [ 'inline_keyboard' => [[
		['text' => $likeText, 'callback_data' => 'like:' . $itemId],
		['text' => $favText, 'callback_data' => 'fav:' . $itemId],
		['text' => t('share', $lang), 'url' => 'https://t.me/share/url?url=' . urlencode($shareUrl)],
	]] ];
}

function sendItemToChat(int $chatId, int $itemId, string $lang, int $userId): void {
	$pdo = db(); $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? AND published = 1'); $stmt->execute([$itemId]); $item = $stmt->fetch(); if (!$item) return;
	$trans = getItemTranslations($itemId); $caption = buildItemCaption($item, $trans, $lang); $images = getItemImages($itemId); $kb = itemInlineKeyboard($itemId, $lang, $userId);
	if (count($images) > 1) { $media = []; foreach ($images as $i => $img) { $m = ['type' => 'photo', 'media' => $img['file_id'] ?: $img['url']]; if ($i === 0) { $m['caption'] = $caption; $m['parse_mode'] = 'HTML'; } $media[] = $m; } sendMediaGroup($chatId, $media); sendMessage($chatId, '—', ['reply_markup' => json_encode($kb)]); }
	elseif (count($images) === 1) { $img = $images[0]; sendPhoto($chatId, $img['file_id'] ?: $img['url'], ['caption' => $caption, 'reply_markup' => json_encode($kb)]); }
	else { sendMessage($chatId, $caption, ['reply_markup' => json_encode($kb)]); }
}

// ---- Color extraction (GD) ----
function extractDominantColorsFromFile(string $filePath, int $count = 5): array {
	if (!extension_loaded('gd')) return [];
	$img = @imagecreatefromstring(file_get_contents($filePath)); if (!$img) return [];
	$w = imagesx($img); $h = imagesy($img); $sample = 40; $tmp = imagecreatetruecolor($sample, $sample);
	imagecopyresampled($tmp, $img, 0, 0, 0, 0, $sample, $sample, $w, $h);
	$hist = [];
	for ($y = 0; $y < $sample; $y++) for ($x = 0; $x < $sample; $x++) {
		$rgb = imagecolorat($tmp, $x, $y); $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
		$rq = intdiv($r, 16) * 16; $gq = intdiv($g, 16) * 16; $bq = intdiv($b, 16) * 16;
		$key = sprintf('%02x%02x%02x', $rq, $gq, $bq); $hist[$key] = ($hist[$key] ?? 0) + 1;
	}
	arsort($hist); $top = array_slice(array_keys($hist), 0, $count);
	imagedestroy($tmp); imagedestroy($img); return $top;
}

function hexToRgb(string $hex): array { $hex = ltrim($hex, '#'); return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]; }

function buildPaletteImage(array $hexColors): string {
	if (!extension_loaded('gd') || empty($hexColors)) return '';
	$w = 600; $h = 120; $img = imagecreatetruecolor($w, $h); imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img,255,255,255));
	$n = count($hexColors); $pad = 10; $boxW = intval(($w - ($n + 1)*$pad)/$n); $x = $pad; $i = 1; $black = imagecolorallocate($img,0,0,0);
	foreach ($hexColors as $hex) { [$r,$g,$b] = hexToRgb($hex); $col = imagecolorallocate($img,$r,$g,$b); imagefilledrectangle($img, $x, $pad, $x + $boxW, $h - $pad - 30, $col); $label = '#' . strtoupper($hex); imagestring($img, 5, $x + 6, $h - 26, "$i. $label", $black); $x += $boxW + $pad; $i++; }
	$tmp = tempnam(sys_get_temp_dir(), 'pal_'); $file = $tmp . '.png'; imagepng($img, $file); imagedestroy($img); return $file;
}

// ---- Keyboards ----
function languageKeyboard(): array { return [ 'inline_keyboard' => [[ ['text' => 'فارسی','callback_data'=>'lang:fa'], ['text'=>'English','callback_data'=>'lang:en'], ['text'=>'Русский','callback_data'=>'lang:ru'] ]] ]; }

function mainMenuKeyboard(string $lang): array {
	return [ 'inline_keyboard' => [
		[ ['text'=>t('skins',$lang),'callback_data'=>'module:skins'], ['text'=>t('vehicles',$lang),'callback_data'=>'module:vehicles'] ],
		[ ['text'=>t('weapons',$lang),'callback_data'=>'module:weapons'], ['text'=>t('objects',$lang),'callback_data'=>'module:objects'] ],
		[ ['text'=>t('mapping',$lang),'callback_data'=>'module:mapping'], ['text'=>t('colors',$lang),'callback_data'=>'module:colors'] ],
		[ ['text'=>t('weather',$lang),'callback_data'=>'module:weather'], ['text'=>t('rules',$lang),'callback_data'=>'rules:list'] ],
		[ ['text'=>t('language',$lang),'callback_data'=>'lang:open'] ],
	] ];
}

function replyMainKeyboard(string $lang, bool $isAdmin): array {
	$kb = [ [t('skins',$lang),t('vehicles',$lang)], [t('weapons',$lang),t('objects',$lang)], [t('mapping',$lang),t('colors',$lang)], [t('weather',$lang),t('rules',$lang)], [t('favorites',$lang),t('language',$lang)] ];
	if ($isAdmin) $kb[] = [ t('panel',$lang) ];
	return [ 'keyboard' => array_map(fn($row)=>array_map(fn($t)=>['text'=>$t],$row), $kb), 'resize_keyboard'=>true, 'one_time_keyboard'=>false ];
}

function favoritesTypeKeyboard(string $lang): array { return [ 'inline_keyboard' => [ [ ['text'=>t('skins',$lang),'callback_data'=>'favtype:skin'], ['text'=>t('vehicles',$lang),'callback_data'=>'favtype:vehicle'] ], [ ['text'=>t('weapons',$lang),'callback_data'=>'favtype:weapon'], ['text'=>t('objects',$lang),'callback_data'=>'favtype:object'] ], [ ['text'=>t('mapping',$lang),'callback_data'=>'favtype:mapping'], ['text'=>t('weather',$lang),'callback_data'=>'favtype:weather'] ], [ ['text'=>t('back',$lang),'callback_data'=>'back:main'] ] ] ]; }

function favoritesListKeyboard(int $userId, string $type, string $lang): array {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT i.id, COALESCE(t.name, CONCAT(UPPER(SUBSTRING(i.type,1,1)), SUBSTRING(i.type,2)), i.slug, i.ext_id) AS name FROM favorites f JOIN items i ON i.id = f.item_id AND i.type = ? LEFT JOIN item_translations t ON t.item_id = i.id AND t.lang = ? WHERE f.user_id = ? ORDER BY i.id DESC LIMIT 50');
	$stmt->execute([$type, $lang, $userId]);
	$rows = $stmt->fetchAll() ?: [];
	$buttons = [];
	foreach ($rows as $r) { $title = $r['name'] ?: ($type . ' #' . $r['id']); $buttons[] = [ ['text'=>$title,'callback_data'=>'open_item:'.$r['id']] ]; }
	if (empty($buttons)) $buttons[] = [ ['text'=>t('favorites_empty',$lang),'callback_data'=>'noop'] ];
	$buttons[] = [ ['text'=>t('back',$lang),'callback_data'=>'favtypes'] ];
	return ['inline_keyboard'=>$buttons];
}

// ---- Handlers ----
function handleStart(array $msg): void {
	$chatId = $msg['chat']['id']; $from = $msg['from']; $userId = $from['id']; upsertUser($userId, $from['first_name'] ?? '', $from['username'] ?? null); ensureOwnerAdmin($userId); $lang = getUserLang($userId);
	$txt = $msg['text'] ?? ''; $param = explode(' ', $txt, 2)[1] ?? '';
	if (!membershipGuard($chatId, $userId, $lang)) return;
	if (str_starts_with($param, 'item_')) { $itemId = (int)substr($param,5); sendItemToChat($chatId, $itemId, $lang, $userId); return; }
	$kbInline = mainMenuKeyboard($lang); $kbReply = replyMainKeyboard($lang, isAdmin($userId));
	sendMessage($chatId, t('welcome',$lang) . "\n\n" . t('main_menu',$lang), ['reply_markup'=>json_encode($kbInline)]);
	sendMessage($chatId, '—', ['reply_markup'=>json_encode($kbReply, JSON_UNESCAPED_UNICODE)]);
}

function handlePanel(array $msg): void {
	$chatId = $msg['chat']['id']; $from = $msg['from']; $userId = $from['id']; upsertUser($userId, $from['first_name'] ?? '', $from['username'] ?? null); ensureOwnerAdmin($userId); $lang = getUserLang($userId);
	if (!isAdmin($userId)) { sendMessage($chatId, t('admin_only',$lang)); return; }
	$panelText = t('panel_title',$lang) . "\n\n" . t('add_item_hint',$lang) . "\n" . t('usage_addskin',$lang)
		. "\n\n" . "Channels: /addchannel <chat_id> [@username] [title]"
		. "\n" . "Sponsors: /addsponsor <chat_id> [label...] | /delsponsor <id>"
		. "\n" . "Rules: /addrule code=rp_1 title_fa=... content_fa=... [title_en=... content_en=... title_ru=... content_ru=...]";
	sendMessage($chatId, $panelText, ['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('manage_channels',$lang),'callback_data'=>'panel:channels']]]])]);
}

function handleText(array $msg): void {
	$chatId = $msg['chat']['id']; $from = $msg['from']; $userId = $from['id']; upsertUser($userId, $from['first_name'] ?? '', $from['username'] ?? null); ensureOwnerAdmin($userId); $lang = getUserLang($userId);
	if (!membershipGuard($chatId, $userId, $lang)) return;
	$text = trim($msg['text'] ?? '');
	if (str_starts_with($text, '/start')) { handleStart($msg); return; }
	if ($text === '/panel' || $text === t('panel',$lang)) { handlePanel($msg); return; }
	if (str_starts_with($text, '/setlang ')) { $code = explode(' ', $text)[1] ?? 'fa'; if (in_array($code, SUPPORTED_LANGS,true)) setUserLang($userId,$code); sendMessage($chatId, t('saved',$code)); return; }
	if (str_starts_with($text, '/addadmin ')) { if (!isAdmin($userId)) { sendMessage($chatId,t('admin_only',$lang)); return; } $uid=(int)trim(substr($text,strlen('/addadmin '))); setAdmin($uid,true); sendMessage($chatId,t('saved',$lang)); return; }
	if (str_starts_with($text, '/addchannel')) { handleCommandAddChannel($msg); return; }
	if (str_starts_with($text, '/addsponsor')) { handleCommandAddSponsor($msg); return; }
	if (str_starts_with($text, '/delsponsor')) { handleCommandDelSponsor($msg); return; }
	if (str_starts_with($text, '/addrule')) { handleCommandAddRule($msg); return; }

	$mapToModule = [ t('skins',$lang)=>'skins', t('vehicles',$lang)=>'vehicles', t('weapons',$lang)=>'weapons', t('objects',$lang)=>'objects', t('mapping',$lang)=>'mapping', t('colors',$lang)=>'colors', t('weather',$lang)=>'weather' ];
	if (isset($mapToModule[$text])) { $mod = $mapToModule[$text]; setSession($userId,'awaiting_query',['module'=>$mod]); $prompts=['skins'=>'send_skin_query','vehicles'=>'send_vehicle_query','weapons'=>'send_weapon_query','objects'=>'send_object_query','mapping'=>'send_mapping_query','colors'=>'color_from_image','weather'=>'send_weather_query']; sendMessage($chatId, t($prompts[$mod] ?? 'main_menu', $lang)); return; }
	if ($text === t('favorites',$lang)) { sendMessage($chatId, t('choose_type',$lang), ['reply_markup'=>json_encode(favoritesTypeKeyboard($lang))]); return; }
	if ($text === t('language',$lang)) { sendMessage($chatId, t('choose_language',$lang), ['reply_markup'=>json_encode(languageKeyboard())]); return; }

	$session = getSession($userId);
	if ($session && $session['state'] === 'awaiting_query') {
		$module = $session['payload']['module'] ?? '';
		$map = ['skins'=>'skin','vehicles'=>'vehicle','weapons'=>'weapon','objects'=>'object','mapping'=>'mapping','weather'=>'weather'];
		if (isset($map[$module])) { $item = findItem($map[$module], $text, $lang); if ($item) sendItemToChat($chatId,(int)$item['id'],$lang,$userId); else sendMessage($chatId,t('not_found',$lang)); clearSession($userId); return; }
	}
	sendMessage($chatId, t('main_menu',$lang), ['reply_markup'=>json_encode(mainMenuKeyboard($lang))]);
}

function handlePhoto(array $msg): void {
	$chatId = $msg['chat']['id']; $from = $msg['from']; $userId = $from['id']; upsertUser($userId, $from['first_name'] ?? '', $from['username'] ?? null); ensureOwnerAdmin($userId); $lang = getUserLang($userId);
	$photos = $msg['photo'] ?? []; if (empty($photos)) { sendMessage($chatId, t('send_photo_with_caption',$lang)); return; }
	$photo = end($photos); $fileId = $photo['file_id']; $fileUnique = $photo['file_unique_id'] ?? null; $caption = trim($msg['caption'] ?? '');
	if (str_starts_with($caption,'/addskin') || str_starts_with($caption,'/addvehicle') || str_starts_with($caption,'/addweapon') || str_starts_with($caption,'/addobject') || str_starts_with($caption,'/addmapping') || str_starts_with($caption,'/addweather')) {
		if (!isAdmin($userId)) { sendMessage($chatId, t('admin_only',$lang)); return; }
		$type = str_starts_with($caption,'/addskin') ? 'skin' : (str_starts_with($caption,'/addvehicle') ? 'vehicle' : (str_starts_with($caption,'/addweapon') ? 'weapon' : (str_starts_with($caption,'/addobject') ? 'object' : (str_starts_with($caption,'/addmapping') ? 'mapping' : 'weather'))));
		$pairs = parseKeyValuePairs($caption);
		$extId = isset($pairs['id']) ? (int)$pairs['id'] : null; $slug = $pairs['slug'] ?? null;
		$attributes = []; foreach (['group','model','category','coordinates','coords','type'] as $k) if (isset($pairs[$k])) $attributes[$k] = $pairs[$k];
		$translations = []; foreach (SUPPORTED_LANGS as $lg) { $translations[$lg] = ['name'=>$pairs['name_'.$lg] ?? ($pairs['name'] ?? ''), 'description'=>$pairs['desc_'.$lg] ?? null, 'biography'=>$pairs['bio_'.$lg] ?? null]; }
		$data = ['type'=>$type,'ext_id'=>$extId,'slug'=>$slug,'attributes'=>$attributes,'translations'=>$translations]; $images = [['file_id'=>$fileId,'file_unique_id'=>$fileUnique]];
		try { $itemId = addItem($data,$images,$userId); sendMessage($chatId, t('saved',$lang) . " (#{$itemId})"); } catch (Throwable $e) { sendMessage($chatId, 'Error: '.$e->getMessage()); }
		return;
	}
	$session = getSession($userId);
	if ($session && ($session['payload']['module'] ?? '') === 'colors') {
		$file = botApi('getFile', ['file_id'=>$fileId]); if (empty($file['ok'])) { sendMessage($chatId, 'getFile failed'); return; }
		$path = $file['result']['file_path']; $url = 'https://api.telegram.org/file/bot' . getenv('BOT_TOKEN') . '/' . $path; $tmp = tempnam(sys_get_temp_dir(),'tg_'); file_put_contents($tmp, file_get_contents($url));
		$colors = extractDominantColorsFromFile($tmp, 6); $lines = []; $i=1; foreach ($colors as $hex) { $lines[] = ($i++) . '. #' . strtoupper($hex); }
		$palette = buildPaletteImage($colors); if ($palette) { sendPhoto($chatId, new CURLFile($palette), ['caption'=>implode("\n", $lines)]); @unlink($palette); } else { sendMessage($chatId, implode("\n", $lines)); }
		@unlink($tmp); clearSession($userId); return;
	}
	sendMessage($chatId, t('send_photo_with_caption',$lang));
}

function parseKeyValuePairs(string $caption): array {
	$out = []; $str = trim(preg_replace('/^\/(addskin|addvehicle|addweapon|addobject|addmapping|addweather)\s*/','',$caption));
	$pattern = '/(\w+)=((\"[^\"]*\")|(\'[^\']*\')|([^\s]+))/u'; if (preg_match_all($pattern, $str, $m, PREG_SET_ORDER)) {
		foreach ($m as $g) { $key = $g[1]; $val = $g[2]; if (($val[0] === '"' && substr($val,-1)==='"') || ($val[0]==="'" && substr($val,-1)==="'")) $val = substr($val,1,-1); $out[$key] = $val; }
	} return $out;
}

function handleCallback(array $cb): void {
	$from = $cb['from']; $userId = $from['id']; upsertUser($userId, $from['first_name'] ?? '', $from['username'] ?? null); ensureOwnerAdmin($userId); $lang = getUserLang($userId);
	$data = $cb['data'] ?? ''; $message = $cb['message'] ?? null; $chatId = $message['chat']['id'] ?? null; $messageId = $message['message_id'] ?? null;
	if ($data === 'check_join') { if (isUserMemberAll($userId)) { answerCallback($cb['id'], t('you_are_in',$lang)); if ($chatId && $messageId) editMessageText($chatId,$messageId,t('main_menu',$lang), ['reply_markup'=>json_encode(mainMenuKeyboard($lang))]); } else { answerCallback($cb['id'], t('force_join',$lang), true); } return; }
	if (str_starts_with($data,'lang:')) { $code = substr($data,5); if ($code==='open') { if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('choose_language',$lang), ['reply_markup'=>json_encode(languageKeyboard())]); return; } if (in_array($code,SUPPORTED_LANGS,true)) setUserLang($userId,$code); answerCallback($cb['id'], t('saved',$code)); if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('main_menu',$code), ['reply_markup'=>json_encode(mainMenuKeyboard($code))]); return; }
	if (str_starts_with($data,'module:')) { $mod = substr($data,7); $map=['skins'=>'send_skin_query','vehicles'=>'send_vehicle_query','weapons'=>'send_weapon_query','objects'=>'send_object_query','mapping'=>'send_mapping_query','colors'=>'color_from_image','weather'=>'send_weather_query']; if (isset($map[$mod])) { setSession($userId,'awaiting_query',['module'=>$mod]); answerCallback($cb['id']); if ($chatId) sendMessage($chatId, t($map[$mod],$lang)); return; } answerCallback($cb['id']); return; }
	if (str_starts_with($data,'rules:')) { $action = substr($data,6); if ($action==='list') { $rules = listRules($lang); $rows=[]; foreach ($rules as $r) { $rows[] = [ ['text'=>$r['title'], 'callback_data'=>'rule:view:'.$r['id']] ]; } $rows[] = [ ['text'=>t('back',$lang), 'callback_data'=>'back:main'] ]; $kb = ['inline_keyboard'=>$rows]; if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('rules_list',$lang), ['reply_markup'=>json_encode($kb)]); answerCallback($cb['id']); return; } }
	if (str_starts_with($data,'rule:view:')) { $rid=(int)substr($data,10); $r=getRule($rid,$lang); if ($r && $chatId && $messageId) { $kb=['inline_keyboard'=>[[['text'=>t('back',$lang),'callback_data'=>'rules:list']]]]; editMessageText($chatId,$messageId,'<b>'.htmlspecialchars($r['title'])."</b>\n\n".htmlspecialchars($r['content']), ['reply_markup'=>json_encode($kb)]); } answerCallback($cb['id']); return; }
	if ($data==='back:main') { if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('main_menu',$lang), ['reply_markup'=>json_encode(mainMenuKeyboard($lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'like:')) { $id=(int)substr($data,5); $ok=likeOnce($userId,$id); answerCallback($cb['id'], $ok ? t('liked',$lang) : ''); if ($chatId&&$messageId) botApi('editMessageReplyMarkup', ['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(itemInlineKeyboard($id,$lang,$userId))]); return; }
	if (str_starts_with($data,'fav:')) { $id=(int)substr($data,4); $added=toggleFavorite($userId,$id); answerCallback($cb['id'], $added ? t('favorited',$lang) : t('unfavorited',$lang)); if ($chatId&&$messageId) botApi('editMessageReplyMarkup', ['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(itemInlineKeyboard($id,$lang,$userId))]); return; }
	if ($data==='favtypes') { if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('choose_type',$lang), ['reply_markup'=>json_encode(favoritesTypeKeyboard($lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'favtype:')) { $type=substr($data,8); if ($chatId&&$messageId) editMessageText($chatId,$messageId,t('favorites',$lang), ['reply_markup'=>json_encode(favoritesListKeyboard($userId,$type,$lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'open_item:')) { $id=(int)substr($data,10); if ($chatId) sendItemToChat($chatId,$id,$lang,$userId); answerCallback($cb['id']); return; }
	answerCallback($cb['id']);
}

// ---- Rules ----
function listRules(string $lang): array { $pdo = db(); $stmt = $pdo->prepare('SELECT r.id, t.title FROM rules r JOIN rule_translations t ON t.rule_id = r.id AND t.lang = ? ORDER BY r.id ASC'); $stmt->execute([$lang]); return $stmt->fetchAll() ?: []; }
function getRule(int $ruleId, string $lang): ?array { $pdo = db(); $stmt = $pdo->prepare('SELECT r.id, t.title, t.content FROM rules r JOIN rule_translations t ON t.rule_id = r.id AND t.lang = ? WHERE r.id = ?'); $stmt->execute([$lang,$ruleId]); $row=$stmt->fetch(); return $row ?: null; }

// ---- Admin Commands ----
function handleCommandAddChannel(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; $lang=getUserLang($userId); if (!isAdmin($userId)) { sendMessage($chatId,t('admin_only',$lang)); return; }
	$text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if (count($parts)<2) { sendMessage($chatId,'Usage: /addchannel <chat_id> [@username] [title...]'); return; }
	$cid=(int)$parts[1]; $username=null; $title=null; foreach($parts as $p) if (str_starts_with($p,'@')) $username=ltrim($p,'@'); if (empty($title) && count($parts)>2) { $rest=array_slice($parts,2); $rest=array_values(array_filter($rest, fn($x)=>!str_starts_with($x,'@'))); $title=implode(' ',$rest); }
	$pdo=db(); $pdo->prepare('INSERT INTO required_channels (chat_id, username, title) VALUES (?, ?, ?)')->execute([$cid,$username,$title]); sendMessage($chatId,t('saved',$lang));
}

function handleCommandAddSponsor(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; $lang=getUserLang($userId); if (!isAdmin($userId)) { sendMessage($chatId,t('admin_only',$lang)); return; }
	$text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if (count($parts)<2) { sendMessage($chatId,'Usage: /addsponsor <chat_id> [label...]'); return; }
	$cid=(int)$parts[1]; $label = count($parts)>2 ? trim(implode(' ', array_slice($parts,2))) : null; $pdo=db(); $max=(int)$pdo->query('SELECT COALESCE(MAX(ordering),0) FROM sponsors')->fetchColumn(); $pdo->prepare('INSERT INTO sponsors (chat_id, label, ordering) VALUES (?, ?, ?)')->execute([$cid, $label, $max+1]); sendMessage($chatId,t('saved',$lang));
}

function handleCommandDelSponsor(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; $lang=getUserLang($userId); if (!isAdmin($userId)) { sendMessage($chatId,t('admin_only',$lang)); return; }
	$text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if (count($parts)<2) { sendMessage($chatId,'Usage: /delsponsor <id>'); return; }
	$id=(int)$parts[1]; db()->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]); sendMessage($chatId,t('saved',$lang));
}

function handleCommandAddRule(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; $lang=getUserLang($userId); if (!isAdmin($userId)) { sendMessage($chatId,t('admin_only',$lang)); return; }
	$text=trim($msg['text']??''); if (!str_starts_with($text,'/addrule')) { sendMessage($chatId,'Usage: /addrule code=... title_fa=... content_fa=...'); return; }
	$pairs=[]; $pattern='/(\w+)=((\"[^\"]*\")|(\'[^\']*\')|([^\s]+))/u'; $str=trim(substr($text,strlen('/addrule')));
	if (preg_match_all($pattern,$str,$m,PREG_SET_ORDER)) foreach ($m as $g) { $k=$g[1]; $v=$g[2]; if (($v[0]=='"'&&substr($v,-1)=='"')||($v[0]=="'"&&substr($v,-1)=="'")) $v=substr($v,1,-1); $pairs[$k]=$v; }
	if (empty($pairs['code'])) { sendMessage($chatId,'code= is required'); return; } $code=$pairs['code']; $pdo=db(); $stmt=$pdo->prepare('SELECT id FROM rules WHERE code=? LIMIT 1'); $stmt->execute([$code]); $rid=(int)($stmt->fetchColumn()?:0); if(!$rid){ $pdo->prepare('INSERT INTO rules (code, created_by) VALUES (?, ?)')->execute([$code,$userId]); $rid=(int)$pdo->lastInsertId(); }
	foreach (SUPPORTED_LANGS as $lg) { $title=$pairs['title_'.$lg]??null; $content=$pairs['content_'.$lg]??null; if ($title===null && $content===null) continue; $ex=$pdo->prepare('SELECT id FROM rule_translations WHERE rule_id=? AND lang=?'); $ex->execute([$rid,$lg]); if ($ex->fetchColumn()) { $pdo->prepare('UPDATE rule_translations SET title=COALESCE(?,title), content=COALESCE(?,content) WHERE rule_id=? AND lang=?')->execute([$title,$content,$rid,$lg]); } else { $pdo->prepare('INSERT INTO rule_translations (rule_id,lang,title,content) VALUES (?,?,?,?)')->execute([$rid,$lg,$title?:'', $content?:'']); } }
	sendMessage($chatId,t('saved',$lang));
}

// ---- Router ----
$input = file_get_contents('php://input'); if (!$input) { echo 'OK'; exit; }
$update = json_decode($input, true); if (!is_array($update)) { echo 'NO_UPDATE'; exit; }
if (isset($update['message'])) { $msg = $update['message']; if (isset($msg['text'])) { handleText($msg); exit; } elseif (isset($msg['photo'])) { handlePhoto($msg); exit; } else { echo 'OK'; exit; } }
if (isset($update['callback_query'])) { handleCallback($update['callback_query']); echo 'OK'; exit; }
if (isset($update['inline_query'])) { echo 'OK'; exit; }
echo 'OK';

?>

