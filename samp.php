<?php
/**
 * Samp Info Bot - Single File PHP Telegram Bot (MySQL)
 * Mixed UX (Reply Keyboard + Inline)
 *
 * Includes: forced join, multi-language (fa,en,ru), items (skins/vehicles/weapons/objects/mapping/weather/colors),
 * rules, likes/share/favorites, deep-links, sponsors, admin inline panel with multi-step wizards (add/list/delete),
 * favorites browser, color extraction from images.
 */

// Polyfills
if (!function_exists('str_starts_with')) {
	function str_starts_with(string $haystack, string $needle): bool {
		return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
	}
}

const SUPPORTED_LANGS = ['fa', 'en', 'ru'];

// Baked config (can override via env)
$BOT_TOKEN       = getenv('BOT_TOKEN') ?: '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ';
$BOT_USERNAME    = getenv('BOT_USERNAME') ?: '@Samp_Info_Bot';
$OWNER_ID        = getenv('OWNER_ID') ?: '5641303137';
$DB_HOST         = getenv('DB_HOST') ?: 'localhost';
$DB_PORT         = getenv('DB_PORT') ?: '3306';
$DB_NAME         = getenv('DB_NAME') ?: 'dakallli_Test2';
$DB_USER         = getenv('DB_USER') ?: 'dakallli_Test2';
$DB_PASS         = getenv('DB_PASS') ?: 'hosyarww123';
$BASE_URL        = getenv('BASE_URL') ?: 'https://dakalll.ir/samp.php';

if (empty($BOT_TOKEN)) { http_response_code(500); echo 'BOT_TOKEN not set'; exit; }

// Telegram HTTP
function botApi($method, $params = array()) {
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

function sendMessage($chatId, $text, $opts = array()) { return botApi('sendMessage', array_merge(array('chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true), $opts)); }
function editMessageText($chatId, $messageId, $text, $opts = array()) { return botApi('editMessageText', array_merge(array('chat_id'=>$chatId,'message_id'=>$messageId,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true), $opts)); }
function answerCallback($callbackId, $text = '', $showAlert = false) { return botApi('answerCallbackQuery',array('callback_query_id'=>$callbackId,'text'=>$text,'show_alert'=>$showAlert)); }
function sendPhoto($chatId, $photo, $opts = array()) { return botApi('sendPhoto', array_merge(array('chat_id'=>$chatId,'photo'=>$photo,'parse_mode'=>'HTML'), $opts)); }
function sendMediaGroup($chatId, $media, $opts = array()) { return botApi('sendMediaGroup', array_merge(array('chat_id'=>$chatId,'media'=>json_encode($media, JSON_UNESCAPED_UNICODE)), $opts)); }
function getMeUsername() { global $BOT_USERNAME; if (!empty($BOT_USERNAME)) return ltrim($BOT_USERNAME, '@'); $me = botApi('getMe'); return (!empty($me['ok']) && !empty($me['result']['username'])) ? ltrim($me['result']['username'],'@') : ''; }
function notifyOwner($text) { global $OWNER_ID; if ($OWNER_ID) { @sendMessage((int)$OWNER_ID, $text); } }

// DB
function db() {
	static $pdo = null; global $DB_HOST,$DB_PORT,$DB_NAME,$DB_USER,$DB_PASS;
	if ($pdo instanceof PDO) return $pdo;
	$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
	$pdo = new PDO($dsn, $DB_USER, $DB_PASS, [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8mb4' ]);
	ensureSchema($pdo);
	return $pdo;
}

function ensureSchema($pdo) {
	$pdo->exec('CREATE TABLE IF NOT EXISTS users (
		user_id BIGINT PRIMARY KEY,
		first_name VARCHAR(255) NULL,
		username VARCHAR(255) NULL,
		language VARCHAR(5) NOT NULL DEFAULT "fa",
		language_selected TINYINT(1) NOT NULL DEFAULT 0,
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

// i18n
function i18n(): array {
	static $map = null; if ($map !== null) return $map;
	$map = [
		'fa' => [
			'welcome' => "به Samp Info Bot خوش آمدید!",
			'choose_language' => "زبان خود را انتخاب کنید:",
			'pick_language' => "لطفاً زبان خود را انتخاب کنید:",
			'lng_changed' => "زبان ذخیره شد.",
			'lng_usage' => "برای تغییر زبان یکی از گزینه‌ها را انتخاب کنید.",
			'language' => "تغییر زبان",
			'panel' => "پنل مدیریت",
			'back' => "بازگشت",
			'home' => "خانه",
			'yes' => "بله",
			'no' => "خیر",
			'next' => "بعدی",
			'done' => "انجام شد",
			'cancel' => "انصراف",
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
			'send_skin_query' => "آیدی یا نام اسکین را بفرستید.",
			'send_vehicle_query' => "آیدی یا نام وسیله نقلیه را بفرستید.",
			'send_weapon_query' => "آیدی یا نام سلاح را بفرستید.",
			'send_object_query' => "آیدی یا نام آبجکت را بفرستید.",
			'send_mapping_query' => "آیدی یا نام مپینگ را بفرستید.",
			'send_weather_query' => "آیدی یا نام آب‌وهوا را بفرستید.",
			'colors_id_prompt' => "آیدی یا کد HEX رنگ را بفرستید یا عکس ارسال کنید.",
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
			'manage_sponsors' => "مدیریت اسپانسرها",
			'manage_rules' => "مدیریت قوانین",
			'manage_items' => "مدیریت آیتم‌ها",
			'manage_admins' => "مدیریت ادمین‌ها",
			'add_item_hint' => "برای افزودن، عکس بفرستید و در کپشن دستور مناسب را بنویسید.",
			'admin_only' => "دسترسی ادمین لازم است.",
			'send_photo_with_caption' => "لطفاً عکس را با کپشن دستور ارسال کنید.",
			'usage_addskin' => "نمونه: /addskin id=21 name_fa=اسم name_en=Name name_ru=Имя group=Group model=Model bio_fa=...",
			'saved' => "ذخیره شد.",
			'rules_list' => "فهرست قوانین:",
			'favorites' => "علاقه‌مندی‌ها",
			'favorites_empty' => "هیچ موردی در علاقه‌مندی‌ها پیدا نشد.",
			'choose_type' => "نوع را انتخاب کنید:",
			'prompt_skins_range' => "برای اسکین، یک آیدی بین 0 تا N وارد کنید یا نام را بفرستید.",
		],
		'en' => [
			'welcome' => "Welcome to Samp Info Bot!",
			'choose_language' => "Choose your language:",
			'pick_language' => "Please choose your language:",
			'lng_changed' => "Language saved.",
			'lng_usage' => "Pick a language to switch.",
			'language' => "Change Language",
			'panel' => "Admin Panel",
			'back' => "Back",
			'home' => "Home",
			'yes' => "Yes",
			'no' => "No",
			'next' => "Next",
			'done' => "Done",
			'cancel' => "Cancel",
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
			'send_skin_query' => "Send skin ID or name.",
			'send_vehicle_query' => "Send vehicle ID or name.",
			'send_weapon_query' => "Send weapon ID or name.",
			'send_object_query' => "Send object ID or name.",
			'send_mapping_query' => "Send mapping ID or name.",
			'send_weather_query' => "Send weather ID or name.",
			'colors_id_prompt' => "Send color ID or HEX code, or send a photo.",
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
			'manage_sponsors' => "Manage sponsors",
			'manage_rules' => "Manage rules",
			'manage_items' => "Manage items",
			'manage_admins' => "Manage admins",
			'add_item_hint' => "To add: send a photo with command in caption.",
			'admin_only' => "Admin access required.",
			'send_photo_with_caption' => "Please send a photo with a caption command.",
			'usage_addskin' => "Example: /addskin id=21 name_fa=... name_en=... name_ru=... group=... model=... bio_fa=...",
			'saved' => "Saved.",
			'rules_list' => "Rules list:",
			'favorites' => "Favorites",
			'favorites_empty' => "No favorites yet.",
			'choose_type' => "Choose a type:",
			'prompt_skins_range' => "For skins, send an ID between 0..N or a name.",
		],
		'ru' => [
			'welcome' => "Добро пожаловать в Samp Info Bot!",
			'choose_language' => "Выберите язык:",
			'pick_language' => "Пожалуйста, выберите язык:",
			'lng_changed' => "Язык сохранен.",
			'lng_usage' => "Выберите язык.",
			'language' => "Сменить язык",
			'panel' => "Панель",
			'back' => "Назад",
			'home' => "Домой",
			'yes' => "Да",
			'no' => "Нет",
			'next' => "Далее",
			'done' => "Готово",
			'cancel' => "Отмена",
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
			'send_skin_query' => "Отправьте ID или имя скина.",
			'send_vehicle_query' => "Отправьте ID или имя транспорта.",
			'send_weapon_query' => "Отправьте ID или имя оружия.",
			'send_object_query' => "Отправьте ID или имя объекта.",
			'send_mapping_query' => "Отправьте ID или имя маппинга.",
			'send_weather_query' => "Отправьте ID или имя погоды.",
			'colors_id_prompt' => "Отправьте ID цвета или HEX, или изображение.",
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
			'manage_sponsors' => "Спонсоры",
			'manage_rules' => "Правила",
			'manage_items' => "Управление предметами",
			'manage_admins' => "Админы",
			'add_item_hint' => "Чтобы добавить: отправьте фото с командой в подписи.",
			'admin_only' => "Требуются права администратора.",
			'send_photo_with_caption' => "Пожалуйста, отправьте фото с подписью-командой.",
			'usage_addskin' => "Пример: /addskin id=21 name_fa=... name_en=... name_ru=... group=... model=... bio_fa=...",
			'saved' => "Сохранено.",
			'rules_list' => "Список правил:",
			'favorites' => "Избранное",
			'favorites_empty' => "В избранном пока пусто.",
			'choose_type' => "Выберите тип:",
			'prompt_skins_range' => "Для скинов отправьте ID 0..N или имя.",
		],
	];
	return $map;
}

function t($key, $lang) { $map = i18n(); if (isset($map[$lang][$key])) return $map[$lang][$key]; if (isset($map['en'][$key])) return $map['en'][$key]; return $key; }

// Users & sessions
function upsertUser($userId, $firstName = '', $username = null) { $pdo = db(); $pdo->prepare('INSERT INTO users (user_id, first_name, username) VALUES (?,?,?) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), username=VALUES(username)')->execute(array($userId,$firstName,$username)); }
function getUser($userId) { $pdo=db(); $st=$pdo->prepare('SELECT * FROM users WHERE user_id=?'); $st->execute(array($userId)); $r=$st->fetch(); return $r ? $r : null; }
function getUserLang($userId) { $u=getUser($userId); return ($u && in_array($u['language'], SUPPORTED_LANGS, true)) ? $u['language'] : 'fa'; }
function setUserLang($userId, $lang, $selected=true) { if (!in_array($lang, SUPPORTED_LANGS,true)) return; $pdo=db(); $pdo->prepare('UPDATE users SET language=?, language_selected=? WHERE user_id=?')->execute(array($lang, $selected?1:0, $userId)); }
function isAdmin($userId) { $u=getUser($userId); return $u ? (bool)$u['is_admin'] : false; }
function ensureOwnerAdmin($userId) { global $OWNER_ID; if ((string)$userId===(string)$OWNER_ID) { db()->prepare('UPDATE users SET is_admin=1 WHERE user_id=?')->execute(array($userId)); } }
function setAdmin($userId, $flag) { db()->prepare('UPDATE users SET is_admin=? WHERE user_id=?')->execute(array($flag?1:0,$userId)); }
function setSession($userId, $state, $payload=array()) { db()->prepare('REPLACE INTO user_sessions (user_id,state,payload) VALUES (?,?,?)')->execute(array($userId,$state,json_encode($payload,JSON_UNESCAPED_UNICODE))); }
function getSession($userId) { $st=db()->prepare('SELECT state,payload FROM user_sessions WHERE user_id=?'); $st->execute(array($userId)); $r=$st->fetch(); return $r?array('state'=>$r['state'],'payload'=>$r['payload']?json_decode($r['payload'],true):array()):null; }
function clearSession($userId) { db()->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute(array($userId)); }

// Forced join
function listRequiredChannels() { return db()->query('SELECT chat_id, username, title FROM required_channels ORDER BY id ASC')->fetchAll() ?: array(); }
function isUserMemberAll($userId) { $chs=listRequiredChannels(); if (empty($chs)) return true; foreach($chs as $c){$res=botApi('getChatMember',array('chat_id'=>$c['chat_id'],'user_id'=>$userId)); if (empty($res['ok'])) return false; $st=isset($res['result']['status'])?$res['result']['status']:'left'; if ($st==='left' || $st==='kicked') return false;} return true; }
function membershipGuard($chatId, $userId, $lang) { $chs=listRequiredChannels(); if (empty($chs)) return true; if (isUserMemberAll($userId)) return true; $text=t('force_join',$lang)."\n\n"; $i=1; foreach($chs as $ch){$title=$ch['title']?:($ch['username']?'@'.$ch['username']:(string)$ch['chat_id']); $url=$ch['username']?('https://t.me/'.$ch['username']):''; $text.=$i++.'. '.($url?"<a href=\"$url\">$title</a>":$title)."\n";} $kb=array('inline_keyboard'=>array(array(array('text'=>t('check_join',$lang),'callback_data'=>'check_join')))); sendMessage($chatId,$text,array('reply_markup'=>json_encode($kb))); return false; }

// Items helper
function addItem($data, $images, $createdBy) {
	$pdo=db(); $pdo->beginTransaction(); try {
		$pdo->prepare('INSERT INTO items (type, ext_id, slug, attributes, created_by) VALUES (?,?,?,?,?)')->execute([
			$data['type'],$data['ext_id']??null,$data['slug']??null, isset($data['attributes'])?json_encode($data['attributes'],JSON_UNESCAPED_UNICODE):null, $createdBy
		]); $id=(int)$pdo->lastInsertId();
		if (!empty($data['translations'])) { $st=$pdo->prepare('INSERT INTO item_translations (item_id,lang,name,description,biography) VALUES (?,?,?,?,?)'); foreach($data['translations'] as $lg=>$tr){ if(!in_array($lg,SUPPORTED_LANGS,true))continue; $st->execute([$id,$lg,$tr['name']??'', $tr['description']??null, $tr['biography']??null]); }}
		if (!empty($images)) { $st=$pdo->prepare('INSERT INTO item_images (item_id,file_id,file_unique_id,url,position) VALUES (?,?,?,?,?)'); $pos=0; foreach($images as $img){ $st->execute([$id,$img['file_id']??null,$img['file_unique_id']??null,$img['url']??null,$pos++]); } }
		$pdo->commit(); return $id;
	} catch (Throwable $e){ $pdo->rollBack(); throw $e; }
}
function findItem($type, $query, $lang) { $pdo=db(); $query=trim($query); if($query==='') return null; if(ctype_digit($query)){ $st=$pdo->prepare('SELECT * FROM items WHERE type=? AND ext_id=? AND published=1 LIMIT 1'); $st->execute(array($type,(int)$query)); $it=$st->fetch(); if($it) return $it; } $st=$pdo->prepare('SELECT i.* FROM items i JOIN item_translations t ON t.item_id=i.id WHERE i.type=? AND i.published=1 AND t.lang=? AND t.name LIKE ? LIMIT 1'); $st->execute(array($type,$lang,'%'.$query.'%')); $it=$st->fetch(); if($it) return $it; $st=$pdo->prepare('SELECT i.* FROM items i JOIN item_translations t ON t.item_id=i.id WHERE i.type=? AND i.published=1 AND t.name LIKE ? LIMIT 1'); $st->execute(array($type,'%'.$query.'%')); $row=$st->fetch(); return $row?$row:null; }
function getItemTranslations($itemId) { $st=db()->prepare('SELECT lang,name,description,biography FROM item_translations WHERE item_id=?'); $st->execute(array($itemId)); $out=array(); foreach($st->fetchAll() as $r){$out[$r['lang']]=array('name'=>$r['name'],'description'=>$r['description'],'biography'=>$r['biography']);} return $out; }
function getItemImages($itemId) { $st=db()->prepare('SELECT file_id,file_unique_id,url,position FROM item_images WHERE item_id=? ORDER BY position ASC,id ASC'); $st->execute(array($itemId)); $rows=$st->fetchAll(); return $rows?$rows:array(); }
function getItemAttributes($itemId) { $st=db()->prepare('SELECT attributes FROM items WHERE id=?'); $st->execute(array($itemId)); $r=$st->fetch(); if(!$r||!$r['attributes']) return array(); $a=json_decode($r['attributes'],true); return is_array($a)?$a:array(); }
function getLikeCount($itemId) { $st=db()->prepare('SELECT COUNT(*) c FROM likes WHERE item_id=?'); $st->execute(array($itemId)); return (int)$st->fetchColumn(); }
function hasFavorite($userId, $itemId) { $st=db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND item_id=?'); $st->execute(array($userId,$itemId)); return (bool)$st->fetchColumn(); }
function toggleFavorite($userId, $itemId) { if (hasFavorite($userId,$itemId)) { db()->prepare('DELETE FROM favorites WHERE user_id=? AND item_id=?')->execute(array($userId,$itemId)); return false; } db()->prepare('INSERT IGNORE INTO favorites (item_id,user_id) VALUES (?,?)')->execute(array($itemId,$userId)); return true; }

function listRequiredSponsors(): array { return db()->query('SELECT chat_id,label FROM sponsors ORDER BY ordering ASC,id ASC')->fetchAll()?:[]; }
function sponsorsText(): string { $rows=listRequiredSponsors(); if(empty($rows)) return ''; $parts=[]; foreach($rows as $r){ $parts[] = $r['label'] ?: (string)$r['chat_id']; } return "\n\n".implode(' | ',$parts); }
function deepLinkToItem($itemId) { $u=getMeUsername(); return $u?('https://t.me/'.$u.'?start=item_'.$itemId):''; }

function buildItemCaption($item, $trans, $lang) {
	$t = $trans[$lang] ?? ($trans['en'] ?? reset($trans) ?: ['name' => '', 'description' => null, 'biography' => null]);
	$attrs = getItemAttributes((int)$item['id']);
	$lines = [];
	$lines[] = '<b>' . htmlspecialchars($t['name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
	if (!empty($item['ext_id'])) $lines[] = 'ID: ' . (int)$item['ext_id'];
	if ($item['type'] === 'skin') { if (!empty($attrs['group'])) $lines[] = 'Group: ' . htmlspecialchars($attrs['group']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'vehicle') { if (!empty($attrs['category'])) $lines[] = 'Category: ' . htmlspecialchars($attrs['category']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'weapon') { if (!empty($attrs['category'])) $lines[] = 'Category: ' . htmlspecialchars($attrs['category']); if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'object') { if (!empty($attrs['model'])) $lines[] = 'Model: ' . htmlspecialchars($attrs['model']); }
	elseif ($item['type'] === 'mapping') { if (!empty($attrs['coordinates'])) $lines[] = 'Coordinates: ' . htmlspecialchars($attrs['coordinates']); }
	elseif ($item['type'] === 'weather') { if (!empty($attrs['type'])) $lines[] = 'Type: ' . htmlspecialchars($attrs['type']); }
	elseif ($item['type'] === 'color') { if (!empty($attrs['hex'])) $lines[] = 'HEX: #' . strtoupper($attrs['hex']); }
	if (!empty($t['biography'])) $lines[] = '"' . htmlspecialchars($t['biography']) . '"';
	$st = sponsorsText(); if (!empty($st)) $lines[] = $st; return implode("\n", $lines);
}

function itemInlineKeyboard($itemId, $lang, $userId) { $likeCount=getLikeCount($itemId); $fav=hasFavorite($userId,$itemId); $favText=$fav?'⭐':t('favorite',$lang); $likeText=t('like',$lang).' '.($likeCount>0?(string)$likeCount:''); $shareUrl=deepLinkToItem($itemId); return array('inline_keyboard'=>array(array(array('text'=>$likeText,'callback_data'=>'like:'.$itemId),array('text'=>$favText,'callback_data'=>'fav:'.$itemId),array('text'=>t('share',$lang),'url'=>'https://t.me/share/url?url='.urlencode($shareUrl))))); }
function sendItemToChat($chatId, $itemId, $lang, $userId) { $st=db()->prepare('SELECT * FROM items WHERE id=? AND published=1'); $st->execute(array($itemId)); $item=$st->fetch(); if(!$item) return; $trans=getItemTranslations($itemId); $caption=buildItemCaption($item,$trans,$lang); $imgs=getItemImages($itemId); $kb=itemInlineKeyboard($itemId,$lang,$userId); if(count($imgs)>1){ $media=array(); foreach($imgs as $i=>$img){ $m=array('type'=>'photo','media'=>$img['file_id']?:$img['url']); if($i===0){$m['caption']=$caption;$m['parse_mode']='HTML';} $media[]=$m; } sendMediaGroup($chatId,$media); sendMessage($chatId,'—',array('reply_markup'=>json_encode($kb)));} elseif(count($imgs)===1){ $img=$imgs[0]; sendPhoto($chatId,$img['file_id']?:$img['url'],array('caption'=>$caption,'reply_markup'=>json_encode($kb)));} else { sendMessage($chatId,$caption,array('reply_markup'=>json_encode($kb)));} }

// Color extraction (GD)
function extractDominantColorsFromFile(string $filePath, int $count = 5): array { if(!extension_loaded('gd')) return []; $img=@imagecreatefromstring(file_get_contents($filePath)); if(!$img) return []; $w=imagesx($img); $h=imagesy($img); $sample=40; $tmp=imagecreatetruecolor($sample,$sample); imagecopyresampled($tmp,$img,0,0,0,0,$sample,$sample,$w,$h); $hist=[]; for($y=0;$y<$sample;$y++){for($x=0;$x<$sample;$x++){ $rgb=imagecolorat($tmp,$x,$y); $r=($rgb>>16)&0xFF; $g=($rgb>>8)&0xFF; $b=$rgb&0xFF; $rq=intdiv($r,16)*16; $gq=intdiv($g,16)*16; $bq=intdiv($b,16)*16; $key=sprintf('%02x%02x%02x',$rq,$gq,$bq); $hist[$key]=($hist[$key]??0)+1; }} arsort($hist); $top=array_slice(array_keys($hist),0,$count); imagedestroy($tmp); imagedestroy($img); return $top; }
function hexToRgb(string $hex): array { $hex=ltrim($hex,'#'); return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]; }
function buildPaletteImage(array $hexColors): string { if(!extension_loaded('gd')||empty($hexColors)) return ''; $w=600;$h=120;$img=imagecreatetruecolor($w,$h); imagefilledrectangle($img,0,0,$w,$h,imagecolorallocate($img,255,255,255)); $n=count($hexColors); $pad=10; $boxW=intval(($w-($n+1)*$pad)/$n); $x=$pad; $i=1; $black=imagecolorallocate($img,0,0,0); foreach($hexColors as $hex){[$r,$g,$b]=hexToRgb($hex); $col=imagecolorallocate($img,$r,$g,$b); imagefilledrectangle($img,$x,$pad,$x+$boxW,$h-$pad-30,$col); $label='#'.strtoupper($hex); imagestring($img,5,$x+6,$h-26,"$i. $label",$black); $x+=$boxW+$pad; $i++;} $tmp=tempnam(sys_get_temp_dir(),'pal_'); $file=$tmp.'.png'; imagepng($img,$file); imagedestroy($img); return $file; }

// Keyboards
function languageKeyboard() { return array('inline_keyboard'=>array(array(array('text'=>'فارسی','callback_data'=>'lang:fa'),array('text'=>'English','callback_data'=>'lang:en'),array('text'=>'Русский','callback_data'=>'lang:ru')))); }
function mainMenuKeyboard($lang) { return array('inline_keyboard'=>array(array(array('text'=>t('skins',$lang),'callback_data'=>'module:skins'),array('text'=>t('vehicles',$lang),'callback_data'=>'module:vehicles')),array(array('text'=>t('weapons',$lang),'callback_data'=>'module:weapons'),array('text'=>t('objects',$lang),'callback_data'=>'module:objects')),array(array('text'=>t('mapping',$lang),'callback_data'=>'module:mapping'),array('text'=>t('colors',$lang),'callback_data'=>'module:colors')),array(array('text'=>t('weather',$lang),'callback_data'=>'module:weather'),array('text'=>t('rules',$lang),'callback_data'=>'rules:list')),array(array('text'=>t('language',$lang),'callback_data'=>'lang:open')))); }
function replyMainKeyboard(string $lang, bool $isAdmin): array {
    $rows = [[t('skins',$lang),t('vehicles',$lang)],[t('weapons',$lang),t('objects',$lang)],[t('mapping',$lang),t('colors',$lang)],[t('weather',$lang),t('rules',$lang)],[t('favorites',$lang),t('language',$lang)]];
    if ($isAdmin) { $rows[] = [ t('panel',$lang) ]; }
    $keyboard = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $txt) { $line[] = ['text' => $txt]; }
        $keyboard[] = $line;
    }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
}
function favoritesTypeKeyboard(string $lang): array { return ['inline_keyboard'=>[[['text'=>t('skins',$lang),'callback_data'=>'favtype:skin'],['text'=>t('vehicles',$lang),'callback_data'=>'favtype:vehicle']],[['text'=>t('weapons',$lang),'callback_data'=>'favtype:weapon'],['text'=>t('objects',$lang),'callback_data'=>'favtype:object']],[['text'=>t('mapping',$lang),'callback_data'=>'favtype:mapping'],['text'=>t('weather',$lang),'callback_data'=>'favtype:weather']],[['text'=>t('back',$lang),'callback_data'=>'back:main']]]]; }
function favoritesListKeyboard(int $userId, string $type, string $lang): array { $st=db()->prepare('SELECT i.id, COALESCE(t.name, CONCAT(UPPER(SUBSTRING(i.type,1,1)), SUBSTRING(i.type,2)), i.slug, i.ext_id) AS name FROM favorites f JOIN items i ON i.id=f.item_id AND i.type=? LEFT JOIN item_translations t ON t.item_id=i.id AND t.lang=? WHERE f.user_id=? ORDER BY i.id DESC LIMIT 50'); $st->execute([$type,$lang,$userId]); $rows=$st->fetchAll()?:[]; $btns=[]; foreach($rows as $r){ $title=$r['name']?:($type.' #'.$r['id']); $btns[]=[['text'=>$title,'callback_data'=>'open_item:'.$r['id']]]; } if(empty($btns)) $btns[]=[['text'=>t('favorites_empty',$lang),'callback_data'=>'noop']]; $btns[]=[['text'=>t('back',$lang),'callback_data'=>'favtypes']]; return ['inline_keyboard'=>$btns]; }

// Panel keyboards
function panelRootKeyboard(string $lang): array { return ['inline_keyboard'=>[[['text'=>t('manage_items',$lang),'callback_data'=>'panel:items'],['text'=>t('rules',$lang),'callback_data'=>'panel:rules']],[['text'=>t('manage_channels',$lang),'callback_data'=>'panel:channels'],['text'=>t('manage_sponsors',$lang),'callback_data'=>'panel:sponsors']],[['text'=>t('manage_admins',$lang),'callback_data'=>'panel:admins']]]]; }
function itemsTypeKeyboard(string $lang): array { $types=[['skin','Skins'],['vehicle','Vehicles'],['weapon','Weapons'],['object','Objects'],['mapping','Mapping'],['weather','Weather'],['color','Colors']]; $row1=$row2=$row3=[]; $rows=[]; foreach($types as $i=>$p){ $text = t(strtolower($p[1]), $lang) ?: ucfirst($p[1]); $rows[]=[['text'=>$text,'callback_data'=>'items:type:'.$p[0]]]; } $rows[]=[['text'=>t('back',$lang),'callback_data'=>'panel:back']]; return ['inline_keyboard'=>$rows]; }
function itemsActionsKeyboard(string $type, string $lang): array { return ['inline_keyboard'=>[[['text'=>'+ Add','callback_data'=>'items:action:add:'.$type],['text'=>'List','callback_data'=>'items:action:list:'.$type]],[['text'=>'Edit','callback_data'=>'items:action:edit:'.$type],['text'=>'Delete','callback_data'=>'items:action:delete:'.$type]],[['text'=>t('back',$lang),'callback_data'=>'panel:items']]]]; }

// Handlers
function handleStart(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; upsertUser($userId,$from['first_name']??'',$from['username']??null); ensureOwnerAdmin($userId); $u=getUser($userId); $lang=getUserLang($userId);
	$txt=$msg['text']??''; $param=explode(' ',$txt,2)[1]??'';
	if (!$u || !(int)$u['language_selected']) { sendMessage($chatId, t('pick_language',$lang), ['reply_markup'=>json_encode(languageKeyboard())]); return; }
	if (!membershipGuard($chatId,$userId,$lang)) return;
	if (str_starts_with($param,'item_')) { $itemId=(int)substr($param,5); sendItemToChat($chatId,$itemId,$lang,$userId); return; }
	sendMessage($chatId, t('welcome',$lang)."\n\n".t('main_menu',$lang), ['reply_markup'=>json_encode(mainMenuKeyboard($lang))]);
	sendMessage($chatId, '—', ['reply_markup'=>json_encode(replyMainKeyboard($lang,isAdmin($userId)), JSON_UNESCAPED_UNICODE)]);
}

function handlePanel(array $msg): void { $chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; upsertUser($userId,$from['first_name']??'',$from['username']??null); ensureOwnerAdmin($userId); $lang=getUserLang($userId); if(!isAdmin($userId)){ sendMessage($chatId,t('admin_only',$lang)); return; } sendMessage($chatId, t('panel_title',$lang), ['reply_markup'=>json_encode(panelRootKeyboard($lang))]); }

function handleText(array $msg): void {
	$chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; upsertUser($userId,$from['first_name']??'',$from['username']??null); ensureOwnerAdmin($userId); $u=getUser($userId); $lang=getUserLang($userId);
	$text=trim($msg['text']??'');
	if ($text==='/start' || str_starts_with($text,'/start ')) { handleStart($msg); return; }
	if ($text==='/panel' || $text===t('panel',$lang)) { handlePanel($msg); return; }
	if ($text==='/lng') { sendMessage($chatId, t('lng_usage',$lang), ['reply_markup'=>json_encode(languageKeyboard())]); return; }
	if (!membershipGuard($chatId,$userId,$lang)) return;
	if ($text===t('language',$lang)) { sendMessage($chatId, t('choose_language',$lang), ['reply_markup'=>json_encode(languageKeyboard())]); return; }

	$map=[ t('skins',$lang)=>'skins', t('vehicles',$lang)=>'vehicles', t('weapons',$lang)=>'weapons', t('objects',$lang)=>'objects', t('mapping',$lang)=>'mapping', t('colors',$lang)=>'colors', t('weather',$lang)=>'weather' ];
	if (isset($map[$text])) { $mod=$map[$text]; setSession($userId,'awaiting_query',['module'=>$mod]); $prompts=['skins'=>'send_skin_query','vehicles'=>'send_vehicle_query','weapons'=>'send_weapon_query','objects'=>'send_object_query','mapping'=>'send_mapping_query','colors'=>'colors_id_prompt','weather'=>'send_weather_query']; sendMessage($chatId, t($prompts[$mod],$lang)); return; }
	if ($text===t('favorites',$lang)) { sendMessage($chatId, t('choose_type',$lang), ['reply_markup'=>json_encode(favoritesTypeKeyboard($lang))]); return; }

	$session=getSession($userId);
	if ($session && $session['state']==='awaiting_query') {
		$module=$session['payload']['module']??''; $map2=['skins'=>'skin','vehicles'=>'vehicle','weapons'=>'weapon','objects'=>'object','mapping'=>'mapping','weather'=>'weather','colors'=>'color'];
		if ($module==='colors') {
			$in=$text;
			if (preg_match('/^#?[0-9a-fA-F]{6}$/',$in)) { $hex=ltrim($in,'#'); $file=buildPaletteImage([$hex]); $caption='#'.strtoupper($hex); if($file){ sendPhoto($chatId,new CURLFile($file),['caption'=>$caption]); @unlink($file);} else { sendMessage($chatId,$caption); } clearSession($userId); return; }
		}
		if(isset($map2[$module])){ $item=findItem($map2[$module],$text,$lang); if($item) sendItemToChat($chatId,(int)$item['id'],$lang,$userId); else sendMessage($chatId,t('not_found',$lang)); clearSession($userId); return; }
	}
	// Edit name flow
	if ($session && $session['state']==='edititem_wait_name') { $p=$session['payload']; $newName=$text; $pdo=db(); $exists=$pdo->prepare('SELECT id FROM item_translations WHERE item_id=? AND lang=?'); $exists->execute([$p['id'],$p['edit_lang']]); if($exists->fetchColumn()){ $pdo->prepare('UPDATE item_translations SET name=? WHERE item_id=? AND lang=?')->execute([$newName,$p['id'],$p['edit_lang']]); } else { $pdo->prepare('INSERT INTO item_translations (item_id,lang,name) VALUES (?,?,?)')->execute([$p['id'],$p['edit_lang'],$newName]); } clearSession($userId); sendMessage($chatId, t('saved',$lang)); return; }
	// Edit attrs flow
	if ($session && $session['state']==='edititem_wait_attrs') { $p=$session['payload']; $pairs=[]; if(preg_match_all('/(\w+)=([^\s]+)/u',$text,$m,PREG_SET_ORDER)){ foreach($m as $g){ $pairs[$g[1]]=$g[2]; } } $attrs=getItemAttributes((int)$p['id']); foreach($pairs as $k=>$v){ $attrs[$k]=$v; } db()->prepare('UPDATE items SET attributes=? WHERE id=?')->execute([json_encode($attrs,JSON_UNESCAPED_UNICODE), (int)$p['id']]); clearSession($userId); sendMessage($chatId, t('saved',$lang)); return; }
	// Channel add quick flow
	if ($session && $session['state']==='channel_add') { $parts=preg_split('/\s+/', $text); $cid=(int)($parts[0]??0); $username=null; foreach($parts as $p2) if(str_starts_with($p2,'@')) $username=ltrim($p2,'@'); $title=null; if(count($parts)>1){ $rest=array_slice($parts,1); $rest2=[]; foreach($rest as $token){ if(!str_starts_with($token,'@')) $rest2[]=$token; } $title=implode(' ', $rest2); } if($cid){ db()->prepare('INSERT INTO required_channels (chat_id,username,title) VALUES (?,?,?)')->execute([$cid,$username,$title]); sendMessage($chatId, t('saved',$lang)); } clearSession($userId); return; }
	// Sponsor add quick flow
	if ($session && $session['state']==='sponsor_add') { $parts=preg_split('/\s+/', $text); $cid=(int)($parts[0]??0); $label=null; if(count($parts)>1) $label=implode(' ', array_slice($parts,1)); if($cid){ $max=(int)db()->query('SELECT COALESCE(MAX(ordering),0) FROM sponsors')->fetchColumn(); db()->prepare('INSERT INTO sponsors (chat_id,label,ordering) VALUES (?,?,?)')->execute([$cid,$label,$max+1]); sendMessage($chatId, t('saved',$lang)); } clearSession($userId); return; }
	sendMessage($chatId, t('main_menu',$lang), ['reply_markup'=>json_encode(mainMenuKeyboard($lang))]);
}

function handlePhoto(array $msg): void { $chatId=$msg['chat']['id']; $from=$msg['from']; $userId=$from['id']; upsertUser($userId,$from['first_name']??'',$from['username']??null); ensureOwnerAdmin($userId); $lang=getUserLang($userId);
	$photos=$msg['photo']??[]; if(empty($photos)){ sendMessage($chatId, t('send_photo_with_caption',$lang)); return; }
	$photo=end($photos); $fileId=$photo['file_id']; $fileUnique=$photo['file_unique_id']??null;
	$session=getSession($userId);
	if ($session && $session['state']==='edititem_wait_photo') { $p=$session['payload']; db()->prepare('INSERT INTO item_images (item_id,file_id,file_unique_id,position) VALUES (?,?,?,?)')->execute([(int)$p['id'],$fileId,$fileUnique,0]); clearSession($userId); sendMessage($chatId, t('saved',$lang)); return; }
	if ($session && ($session['state']??'')==='additem_wait_photos') {
		$payload=$session['payload']; $payload['images'][]=['file_id'=>$fileId,'file_unique_id'=>$fileUnique]; setSession($userId,'additem_wait_photos',$payload); sendMessage($chatId, t('done',$lang).'?', ['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('done',$lang),'callback_data'=>'additem:photos_done'],['text'=>t('next',$lang),'callback_data'=>'additem:photos_done']]]])]); return;
	}
	if ($session && ($session['state']??'')==='awaiting_query' && (($session['payload']['module']??'')==='colors')) {
		$file = botApi('getFile', ['file_id'=>$fileId]); if (empty($file['ok'])) { sendMessage($chatId, 'getFile failed'); return; }
		$path = $file['result']['file_path']; $url = 'https://api.telegram.org/file/bot' . getenv('BOT_TOKEN') . '/' . $path; $tmp = tempnam(sys_get_temp_dir(),'tg_'); file_put_contents($tmp, file_get_contents($url));
		$colors = extractDominantColorsFromFile($tmp, 6); $lines=[]; $i=1; foreach($colors as $hex){ $lines[] = ($i++).'. #'.strtoupper($hex); }
		$palette = buildPaletteImage($colors); if ($palette) { sendPhoto($chatId, new CURLFile($palette), ['caption'=>implode("\n", $lines)]); @unlink($palette);} else { sendMessage($chatId, implode("\n", $lines)); }
		@unlink($tmp); clearSession($userId); return;
	}
	sendMessage($chatId, t('send_photo_with_caption',$lang));
}

// Callback
function handleCallback(array $cb): void {
	$from=$cb['from']; $userId=$from['id']; upsertUser($userId,$from['first_name']??'',$from['username']??null); ensureOwnerAdmin($userId); $lang=getUserLang($userId);
	$data=$cb['data']??''; $message=$cb['message']??null; $chatId=$message['chat']['id']??null; $messageId=$message['message_id']??null;
	if ($data==='check_join') { if (isUserMemberAll($userId)) { answerCallback($cb['id'], t('you_are_in',$lang)); if($chatId&&$messageId) editMessageText($chatId,$messageId,t('main_menu',$lang),['reply_markup'=>json_encode(mainMenuKeyboard($lang))]); } else { answerCallback($cb['id'], t('force_join',$lang), true);} return; }
	if (str_starts_with($data,'lang:')) { $code=substr($data,5); if($code==='open'){ if($chatId&&$messageId) editMessageText($chatId,$messageId,t('choose_language',$lang),['reply_markup'=>json_encode(languageKeyboard())]); return; } if(in_array($code,SUPPORTED_LANGS,true)){ setUserLang($userId,$code,true); answerCallback($cb['id'], t('lng_changed',$code)); if($chatId&&$messageId) editMessageText($chatId,$messageId,t('main_menu',$code),['reply_markup'=>json_encode(mainMenuKeyboard($code))]); } return; }
	if (str_starts_with($data,'module:')) { $mod=substr($data,7); $map=['skins'=>'send_skin_query','vehicles'=>'send_vehicle_query','weapons'=>'send_weapon_query','objects'=>'send_object_query','mapping'=>'send_mapping_query','colors'=>'colors_id_prompt','weather'=>'send_weather_query']; if(isset($map[$mod])){ setSession($userId,'awaiting_query',['module'=>$mod]); answerCallback($cb['id']); if($chatId) sendMessage($chatId,t($map[$mod],$lang)); return; } answerCallback($cb['id']); return; }
	if (str_starts_with($data,'rules:')) { $action=substr($data,6); if($action==='list'){ $rules=listRules($lang); $rows=[]; foreach($rules as $r){ $rows[]=[[ 'text'=>$r['title'], 'callback_data'=>'rule:view:'.$r['id'] ]]; } $rows[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'back:main' ]]; $kb=['inline_keyboard'=>$rows]; if($chatId&&$messageId) editMessageText($chatId,$messageId,t('rules_list',$lang),['reply_markup'=>json_encode($kb)]); answerCallback($cb['id']); return; } }
	if (str_starts_with($data,'rule:view:')) { $rid=(int)substr($data,10); $r=getRule($rid,$lang); if($r&&$chatId&&$messageId){ $kb=['inline_keyboard'=>[[['text'=>t('back',$lang),'callback_data'=>'rules:list']]]]; editMessageText($chatId,$messageId,'<b>'.htmlspecialchars($r['title'])."</b>\n\n".htmlspecialchars($r['content']), ['reply_markup'=>json_encode($kb)]);} answerCallback($cb['id']); return; }
	if ($data==='back:main') { if($chatId&&$messageId) editMessageText($chatId,$messageId,t('main_menu',$lang),['reply_markup'=>json_encode(mainMenuKeyboard($lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'like:')) { $id=(int)substr($data,5); $ok=likeOnce($userId,$id); answerCallback($cb['id'], $ok?t('liked',$lang):''); if($chatId&&$messageId) botApi('editMessageReplyMarkup',['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(itemInlineKeyboard($id,$lang,$userId))]); return; }
	if (str_starts_with($data,'fav:')) { $id=(int)substr($data,4); $added=toggleFavorite($userId,$id); answerCallback($cb['id'], $added?t('favorited',$lang):t('unfavorited',$lang)); if($chatId&&$messageId) botApi('editMessageReplyMarkup',['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(itemInlineKeyboard($id,$lang,$userId))]); return; }
	if ($data==='favtypes') { if($chatId&&$messageId) editMessageText($chatId,$messageId,t('choose_type',$lang),['reply_markup'=>json_encode(favoritesTypeKeyboard($lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'favtype:')) { $type=substr($data,8); if($chatId&&$messageId) editMessageText($chatId,$messageId,t('favorites',$lang),['reply_markup'=>json_encode(favoritesListKeyboard($userId,$type,$lang))]); answerCallback($cb['id']); return; }

	// Admin panel
	if (str_starts_with($data,'panel:')) { if(!isAdmin($userId)){ answerCallback($cb['id'], t('admin_only',$lang), true); return;} $action=substr($data,6); if($action==='items'){ if($chatId&&$messageId) editMessageText($chatId,$messageId,t('choose_type',$lang),['reply_markup'=>json_encode(itemsTypeKeyboard($lang))]); answerCallback($cb['id']); return; } if($action==='channels'){ // list required channels
		$chs=listRequiredChannels(); $rows=[]; foreach($chs as $c){ $label=($c['title']?:'').' '.($c['username']?'@'.$c['username']:'').' ('.$c['chat_id'].')'; $rows[]=[[ 'text'=>'❌ '.$label, 'callback_data'=>'channel:del:'.$c['chat_id'] ]]; } $rows[]=[[ 'text'=>'+ Add', 'callback_data'=>'channel:add' ]]; $rows[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'panel:back' ]]; $kb=['inline_keyboard'=>$rows]; if($chatId&&$messageId) editMessageText($chatId,$messageId,t('manage_channels',$lang),['reply_markup'=>json_encode($kb)]); answerCallback($cb['id']); return; } if($action==='sponsors'){ $rows=[]; foreach(listRequiredSponsors() as $s){ $rows[]=[[ 'text'=>'❌ '.($s['label']?:$s['chat_id']), 'callback_data'=>'sponsor:del:'.$s['chat_id'] ]]; } $rows[]=[[ 'text'=>'+ Add', 'callback_data'=>'sponsor:add' ]]; $rows[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'panel:back' ]]; if($chatId&&$messageId) editMessageText($chatId,$messageId,t('manage_sponsors',$lang),['reply_markup'=>json_encode(['inline_keyboard'=>$rows])]); answerCallback($cb['id']); return; } if($action==='admins'){ if($chatId&&$messageId) editMessageText($chatId,$messageId,t('manage_admins',$lang)."\n/addadmin <id>",['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('back',$lang),'callback_data'=>'panel:back']]]])]); answerCallback($cb['id']); return; } if($action==='rules'){ if($chatId&&$messageId) editMessageText($chatId,$messageId,t('manage_rules',$lang)."\n/addrule ...",['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('back',$lang),'callback_data'=>'panel:back']]]])]); answerCallback($cb['id']); return; } if($action==='back'){ if($chatId&&$messageId) editMessageText($chatId,$messageId,t('panel_title',$lang),['reply_markup'=>json_encode(panelRootKeyboard($lang))]); answerCallback($cb['id']); return; } }

	if (str_starts_with($data,'items:type:')) { $type=substr($data,11); if($chatId&&$messageId) editMessageText($chatId,$messageId, strtoupper($type), ['reply_markup'=>json_encode(itemsActionsKeyboard($type,$lang))]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'items:action:add:')) { $type=substr($data,17); setSession($userId,'additem_wait_photos',['type'=>$type,'images'=>[]]); if($chatId) sendMessage($chatId,'Send photos for '.$type.' (you can send multiple), then press Done.', ['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('done',$lang),'callback_data'=>'additem:photos_done']]]])]); answerCallback($cb['id']); return; }
	if ($data==='additem:photos_done') { $s=getSession($userId); if(!$s||($s['state']!=='additem_wait_photos')){ answerCallback($cb['id']); return;} $p=$s['payload']; setSession($userId,'additem_wait_id',$p); if($cb['message']) sendMessage($cb['message']['chat']['id'],'Send numeric ID (ext_id).'); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'items:action:list:')) { $type=substr($data,17); // list latest 20
		$st=db()->prepare('SELECT i.id, i.ext_id, COALESCE(t.name, i.slug) name FROM items i LEFT JOIN item_translations t ON t.item_id=i.id AND t.lang=? WHERE i.type=? ORDER BY i.id DESC LIMIT 20'); $st->execute([$lang,$type]); $rows=$st->fetchAll()?:[]; $btns=[]; foreach($rows as $r){ $btns[]=[[ 'text'=>($r['ext_id']!==null?('#'.$r['ext_id'].' ':'')).($r['name']?:('ID '.$r['id'])), 'callback_data'=>'open_item:'.$r['id'] ]]; } $btns[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'items:type:'.$type ]]; if($chatId&&$messageId) editMessageText($chatId,$messageId,'List: '.$type,['reply_markup'=>json_encode(['inline_keyboard'=>$btns])]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'items:action:edit:')) { $type=substr($data,16); $st=db()->prepare('SELECT i.id, i.ext_id, COALESCE(t.name, i.slug) name FROM items i LEFT JOIN item_translations t ON t.item_id=i.id AND t.lang=? WHERE i.type=? ORDER BY i.id DESC LIMIT 20'); $st->execute([$lang,$type]); $rows=$st->fetchAll()?:[]; $btns=[]; foreach($rows as $r){ $btns[]=[[ 'text'=>($r['ext_id']!==null?('#'.$r['ext_id'].' ':'')).($r['name']?:('ID '.$r['id'])), 'callback_data'=>'item:edit:'.$r['id'].':'.$type ]]; } $btns[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'items:type:'.$type ]]; if($chatId&&$messageId) editMessageText($chatId,$messageId,'Select to edit: '.$type,['reply_markup'=>json_encode(['inline_keyboard'=>$btns])]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'item:edit:')) { $parts=explode(':',$data); $id=(int)$parts[2]; $type=$parts[3]??''; setSession($userId,'edititem_choose_field',['id'=>$id,'type'=>$type]); $kb=['inline_keyboard'=>[[['text'=>'Name (fa)','callback_data'=>'edititem:field:name:fa'],['text'=>'Name (en)','callback_data'=>'edititem:field:name:en'],['text'=>'Name (ru)','callback_data'=>'edititem:field:name:ru']],[['text'=>'Attributes','callback_data'=>'edititem:field:attrs'],['text'=>'Add photo','callback_data'=>'edititem:field:addphoto']],[['text'=>t('back',$lang),'callback_data'=>'items:action:edit:'.$type]]]]; if($chatId&&$messageId) editMessageText($chatId,$messageId,'Which field?',['reply_markup'=>json_encode($kb)]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'edititem:field:name:')) { $lg=substr($data,strlen('edititem:field:name:')); $s=getSession($userId); if(!$s){ answerCallback($cb['id']); return;} $p=$s['payload']; $p['edit_lang']=$lg; setSession($userId,'edititem_wait_name',$p); if($chatId) sendMessage($chatId,'Send new name ('.$lg.'):'); answerCallback($cb['id']); return; }
	if ($data==='edititem:field:attrs') { $s=getSession($userId); if(!$s){ answerCallback($cb['id']); return;} setSession($userId,'edititem_wait_attrs',$s['payload']); if($chatId) sendMessage($chatId,'Send attributes as key=value separated by spaces (e.g. group=Grove model=mdl_21)'); answerCallback($cb['id']); return; }
	if ($data==='edititem:field:addphoto') { $s=getSession($userId); if(!$s){ answerCallback($cb['id']); return;} setSession($userId,'edititem_wait_photo',$s['payload']); if($chatId) sendMessage($chatId,'Send a photo to add.'); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'items:action:delete:')) { $type=substr($data,19); // choose latest
		$st=db()->prepare('SELECT id, ext_id FROM items WHERE type=? ORDER BY id DESC LIMIT 20'); $st->execute([$type]); $rows=$st->fetchAll()?:[]; $btns=[]; foreach($rows as $r){ $btns[]=[[ 'text'=>'Delete #'.$r['ext_id'].' (ID '.$r['id'].')', 'callback_data'=>'item:del_confirm:'.$r['id'].':'.$type ]]; } $btns[]=[[ 'text'=>t('back',$lang), 'callback_data'=>'items:type:'.$type ]]; if($chatId&&$messageId) editMessageText($chatId,$messageId,'Delete which?',['reply_markup'=>json_encode(['inline_keyboard'=>$btns])]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'item:del_confirm:')) { $parts=explode(':',$data); $id=(int)$parts[2]; $type=$parts[3]??''; if($chatId&&$messageId) editMessageText($chatId,$messageId,'Confirm delete?', ['reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>t('yes',$lang),'callback_data'=>'item:del:'.$id.':'.$type],['text'=>t('no',$lang),'callback_data'=>'items:type:'.$type]]]])]); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'item:del:')) { $parts=explode(':',$data); $id=(int)$parts[2]; $type=$parts[3]??''; db()->prepare('DELETE FROM items WHERE id=?')->execute([$id]); if($chatId&&$messageId) editMessageText($chatId,$messageId,t('saved',$lang),['reply_markup'=>json_encode(itemsActionsKeyboard($type,$lang))]); notifyOwner("Item deleted #$id by $userId"); answerCallback($cb['id']); return; }

	// Channels & sponsors quick inline delete/add triggers
	if (str_starts_with($data,'channel:del:')) { $cid=(int)substr($data,12); db()->prepare('DELETE FROM required_channels WHERE chat_id=?')->execute([$cid]); answerCallback($cb['id'], t('saved',$lang)); return; }
	if ($data==='channel:add') { setSession($userId,'channel_add',[]); if($chatId) sendMessage($chatId,'Send channel chat_id then optionally @username and title.'); answerCallback($cb['id']); return; }
	if (str_starts_with($data,'sponsor:del:')) { $cid=(int)substr($data,12); db()->prepare('DELETE FROM sponsors WHERE chat_id=?')->execute([$cid]); answerCallback($cb['id'], t('saved',$lang)); return; }
	if ($data==='sponsor:add') { setSession($userId,'sponsor_add',[]); if($chatId) sendMessage($chatId,'Send sponsor chat_id and optional label.'); answerCallback($cb['id']); return; }

	answerCallback($cb['id']);
}

// Rules
function listRules(string $lang): array { $st=db()->prepare('SELECT r.id, t.title FROM rules r JOIN rule_translations t ON t.rule_id=r.id AND t.lang=? ORDER BY r.id ASC'); $st->execute([$lang]); return $st->fetchAll()?:[]; }
function getRule(int $ruleId, string $lang): ?array { $st=db()->prepare('SELECT r.id, t.title, t.content FROM rules r JOIN rule_translations t ON t.rule_id=r.id AND t.lang=? WHERE r.id=?'); $st->execute([$lang,$ruleId]); $r=$st->fetch(); return $r?:null; }

// Admin typed commands (fallbacks and add flows)
function handleCommandAddChannel(array $msg): void { $chatId=$msg['chat']['id']; $uid=$msg['from']['id']; $lang=getUserLang($uid); if(!isAdmin($uid)){ sendMessage($chatId,t('admin_only',$lang)); return;} $text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if(count($parts)<2){ sendMessage($chatId,'Usage: /addchannel <chat_id> [@username] [title...]'); return;} $cid=(int)$parts[1]; $username=null; $title=null; foreach($parts as $p) if(str_starts_with($p,'@')) $username=ltrim($p,'@'); if(empty($title)&&count($parts)>2){ $rest=array_slice($parts,2); $rest=array_values(array_filter($rest,fn($x)=>!str_starts_with($x,'@'))); $title=implode(' ',$rest);} db()->prepare('INSERT INTO required_channels (chat_id,username,title) VALUES (?,?,?)')->execute([$cid,$username,$title]); sendMessage($chatId,t('saved',$lang)); }
function handleCommandAddSponsor(array $msg): void { $chatId=$msg['chat']['id']; $uid=$msg['from']['id']; $lang=getUserLang($uid); if(!isAdmin($uid)){ sendMessage($chatId,t('admin_only',$lang)); return;} $text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if(count($parts)<2){ sendMessage($chatId,'Usage: /addsponsor <chat_id> [label...]'); return;} $cid=(int)$parts[1]; $label=count($parts)>2?trim(implode(' ',array_slice($parts,2))):null; $max=(int)db()->query('SELECT COALESCE(MAX(ordering),0) FROM sponsors')->fetchColumn(); db()->prepare('INSERT INTO sponsors (chat_id,label,ordering) VALUES (?,?,?)')->execute([$cid,$label,$max+1]); sendMessage($chatId,t('saved',$lang)); }
function handleCommandDelSponsor(array $msg): void { $chatId=$msg['chat']['id']; $uid=$msg['from']['id']; $lang=getUserLang($uid); if(!isAdmin($uid)){ sendMessage($chatId,t('admin_only',$lang)); return;} $text=trim($msg['text']??''); $parts=preg_split('/\s+/', $text); if(count($parts)<2){ sendMessage($chatId,'Usage: /delsponsor <chat_id>'); return;} $cid=(int)$parts[1]; db()->prepare('DELETE FROM sponsors WHERE chat_id=?')->execute([$cid]); sendMessage($chatId,t('saved',$lang)); }
function handleCommandAddRule(array $msg): void { $chatId=$msg['chat']['id']; $uid=$msg['from']['id']; $lang=getUserLang($uid); if(!isAdmin($uid)){ sendMessage($chatId,t('admin_only',$lang)); return;} $text=trim($msg['text']??''); if(!str_starts_with($text,'/addrule')){ sendMessage($chatId,'Usage: /addrule code=... title_fa=... content_fa=...'); return;} $pairs=[]; $pattern='/(\w+)=((\"[^\"]*\")|(\'[^\']*\')|([^\s]+))/u'; $str=trim(substr($text,strlen('/addrule'))); if(preg_match_all($pattern,$str,$m,PREG_SET_ORDER)) foreach($m as $g){ $k=$g[1]; $v=$g[2]; if(($v[0]=='"'&&substr($v,-1)=='"')||($v[0]=="'"&&substr($v,-1)=="'")) $v=substr($v,1,-1); $pairs[$k]=$v; } if(empty($pairs['code'])){ sendMessage($chatId,'code= is required'); return;} $code=$pairs['code']; $pdo=db(); $st=$pdo->prepare('SELECT id FROM rules WHERE code=? LIMIT 1'); $st->execute([$code]); $rid=(int)($st->fetchColumn()?:0); if(!$rid){ $pdo->prepare('INSERT INTO rules (code,created_by) VALUES (?,?)')->execute([$code,$uid]); $rid=(int)$pdo->lastInsertId(); } foreach(SUPPORTED_LANGS as $lg){ $title=$pairs['title_'.$lg]??null; $content=$pairs['content_'.$lg]??null; if($title===null && $content===null) continue; $ex=$pdo->prepare('SELECT id FROM rule_translations WHERE rule_id=? AND lang=?'); $ex->execute([$rid,$lg]); if($ex->fetchColumn()){ $pdo->prepare('UPDATE rule_translations SET title=COALESCE(?,title), content=COALESCE(?,content) WHERE rule_id=? AND lang=?')->execute([$title,$content,$rid,$lg]); } else { $pdo->prepare('INSERT INTO rule_translations (rule_id,lang,title,content) VALUES (?,?,?,?)')->execute([$rid,$lg,$title?:'', $content?:'']); } } sendMessage($chatId,t('saved',$lang)); }

// Router
$input=file_get_contents('php://input'); if(!$input){ echo 'OK'; exit; }
$update=json_decode($input,true); if(!is_array($update)){ echo 'NO_UPDATE'; exit; }
if(isset($update['message'])){ $msg=$update['message']; if(isset($msg['text'])){ $text=$msg['text']; if(str_starts_with($text,'/addchannel')){ handleCommandAddChannel($msg); exit; } if(str_starts_with($text,'/addsponsor')){ handleCommandAddSponsor($msg); exit; } if(str_starts_with($text,'/delsponsor')){ handleCommandDelSponsor($msg); exit; } if(str_starts_with($text,'/addrule')){ handleCommandAddRule($msg); exit; } handleText($msg); exit; } elseif(isset($msg['photo'])){ handlePhoto($msg); exit; } else { echo 'OK'; exit; } }
if(isset($update['callback_query'])){ handleCallback($update['callback_query']); echo 'OK'; exit; }
if(isset($update['inline_query'])){ echo 'OK'; exit; }
echo 'OK';

?>

