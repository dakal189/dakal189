<?php

// Single-file Telegram Referral Bot (PHP + MySQL)
// Webhook entrypoint: set to https://YOUR_DOMAIN/bot.php?secret=WEBHOOK_SECRET

// ====== CONFIG (defaults; override via env) ======
$CONFIG = [
	'app' => [
		'name' => getenv('APP_NAME') ?: 'ReferralBot',
		'env' => getenv('APP_ENV') ?: 'production',
		'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Tehran',
		'webhook_secret' => getenv('WEBHOOK_SECRET') ?: 'change-me-secret',
	],
	'db' => [
		'host' => getenv('DB_HOST') ?: 'localhost',
		'port' => (int)(getenv('DB_PORT') ?: 3306),
		'database' => getenv('DB_DATABASE') ?: 'dakallli_Test2',
		'username' => getenv('DB_USERNAME') ?: 'dakallli_Test2',
		'password' => getenv('DB_PASSWORD') ?: 'hosyarww123',
		'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
	],
	'telegram' => [
		'token' => getenv('TELEGRAM_BOT_TOKEN') ?: '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ',
		'admin_id' => (int)(getenv('TELEGRAM_ADMIN_ID') ?: 5641303137),
		'admin_group_id' => getenv('TELEGRAM_ADMIN_GROUP_ID') ?: '-1002987179440',
		'api_base' => 'https://api.telegram.org',
	],
	'settings' => [
		'points_per_referral' => (int)(getenv('POINTS_PER_REFERRAL') ?: 10),
	]
];

date_default_timezone_set($CONFIG['app']['timezone']);

// ====== PHP ERROR LOGGING ======
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

// ====== LOGGING ======
$LOG_FILE = __DIR__ . '/storage/logs/referral_single.log';
if (!is_dir(dirname($LOG_FILE))) {
	@mkdir(dirname($LOG_FILE), 0777, true);
}
function log_info(string $msg, array $ctx = []): void {
	global $LOG_FILE;
	file_put_contents($LOG_FILE, sprintf("%s [INFO] %s %s\n", date('c'), $msg, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''), FILE_APPEND);
}
function log_error(string $msg, array $ctx = []): void {
	global $LOG_FILE;
	file_put_contents($LOG_FILE, sprintf("%s [ERROR] %s %s\n", date('c'), $msg, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''), FILE_APPEND);
}

// ====== SECURITY (webhook secret) ======
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$secretQuery = $_GET['secret'] ?? '';
$expectedSecret = (string)($CONFIG['app']['webhook_secret'] ?? '');
if (!empty($expectedSecret) && ($secretHeader !== $expectedSecret && $secretQuery !== $expectedSecret)) {
	log_error('Forbidden webhook secret mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
	http_response_code(403);
	echo 'forbidden';
	exit;
}

// ====== DEBUG ENDPOINTS (GET) ======
if (isset($_GET['health'])) {
	echo 'ok';
	exit;
}

// ====== TELEGRAM CLIENT ======
function tg_call(string $method, array $params = []) {
	global $CONFIG;
	$url = rtrim($CONFIG['telegram']['api_base'], '/') . '/bot' . $CONFIG['telegram']['token'] . '/' . $method;
	$useCurl = function_exists('curl_init');
	if ($useCurl) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$res = curl_exec($ch);
		if ($res === false) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new RuntimeException('Telegram request failed: ' . $err);
		}
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	} else {
		$opts = [
			'http' => [
				'method' => 'POST',
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => http_build_query($params),
				'timeout' => 30,
			]
		];
		$context = stream_context_create($opts);
		$res = @file_get_contents($url, false, $context);
		$code = 0;
		if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
			$code = (int)$m[1];
		}
		if ($res === false) {
			throw new RuntimeException('Telegram request failed (no curl)');
		}
	}
	$data = json_decode($res, true) ?: [];
	if ($code !== 200 || !($data['ok'] ?? false)) {
		throw new RuntimeException('Telegram API error: ' . ($data['description'] ?? 'unknown'));
	}
	return $data['result'];
}
function tg_sendMessage(array $params) { return tg_call('sendMessage', $params); }
function tg_editMessageText(array $params) { return tg_call('editMessageText', $params); }
function tg_answerCallbackQuery(array $params) { return tg_call('answerCallbackQuery', $params); }
function tg_getChatMember(array $params) { return tg_call('getChatMember', $params); }

if (isset($_GET['debug']) && $_GET['debug'] === 'send') {
	try {
		tg_sendMessage(['chat_id' => (int)$CONFIG['telegram']['admin_id'], 'text' => 'Debug OK']);
		echo 'sent';
	} catch (Throwable $e) {
		log_error('debug send failed', ['e' => $e->getMessage()]);
		echo 'error';
	}
	exit;
}

// ====== INPUT ======
$raw = file_get_contents('php://input');
if (!$raw) {
	log_info('Empty body');
	echo 'ok';
	exit;
}
$update = json_decode($raw, true);
if (!$update) {
	log_error('Invalid JSON');
	echo 'ok';
	exit;
}

// ====== DB ======
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $CONFIG['db']['host'], $CONFIG['db']['port'], $CONFIG['db']['database'], $CONFIG['db']['charset']);
try {
	$pdo = new PDO($dsn, $CONFIG['db']['username'], $CONFIG['db']['password'], [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	log_info('DB connected');
} catch (Throwable $e) {
	log_error('DB connect failed', ['e' => $e->getMessage()]);
	echo 'ok';
	exit;
}

// ====== SCHEMA ======
try {
	$pdo->exec("CREATE TABLE IF NOT EXISTS users (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		telegram_id BIGINT NOT NULL UNIQUE,
		username VARCHAR(64) NULL,
		first_name VARCHAR(128) NULL,
		last_name VARCHAR(128) NULL,
		language_code VARCHAR(8) NULL,
		points INT NOT NULL DEFAULT 0,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS referrals (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		inviter_user_id BIGINT UNSIGNED NOT NULL,
		invitee_user_id BIGINT UNSIGNED NOT NULL,
		status ENUM('pending','qualified','revoked') NOT NULL DEFAULT 'pending',
		qualified_at TIMESTAMP NULL,
		revoked_at TIMESTAMP NULL,
		UNIQUE KEY uniq_ref_pair (inviter_user_id, invitee_user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS points_transactions (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		amount INT NOT NULL,
		type ENUM('referral_reward','spend','revoke','adjust') NOT NULL,
		reference_id BIGINT NULL,
		description VARCHAR(255) NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_user_created (user_id, created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS items (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(128) NOT NULL,
		required_points INT NOT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		priority INT NOT NULL DEFAULT 0,
		stock INT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		item_id BIGINT UNSIGNED NOT NULL,
		status ENUM('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
		note VARCHAR(255) NULL,
		admin_id BIGINT UNSIGNED NULL,
		admin_message_id BIGINT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
		user_id BIGINT UNSIGNED PRIMARY KEY,
		role ENUM('owner','admin') NOT NULL DEFAULT 'admin',
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS forced_channels (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		channel_id VARCHAR(64) NOT NULL,
		title VARCHAR(128) NULL,
		is_required TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
		`key` VARCHAR(64) PRIMARY KEY,
		`value` VARCHAR(255) NOT NULL,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
	log_error('Schema create failed', ['e' => $e->getMessage()]);
	echo 'ok';
	exit;
}

// ====== SEED OWNER ADMIN ======
try {
	if (!empty($CONFIG['telegram']['admin_id'])) {
		$tgAdminId = (int)$CONFIG['telegram']['admin_id'];
		$u = db_fetch_one($pdo, 'SELECT * FROM users WHERE telegram_id = ?', [$tgAdminId]);
		if (!$u) {
			$pdo->prepare('INSERT INTO users (telegram_id) VALUES (?)')->execute([$tgAdminId]);
			$uid = (int)$pdo->lastInsertId();
			$pdo->prepare('INSERT INTO admins (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role=VALUES(role)')->execute([$uid, 'owner']);
		} else {
			$pdo->prepare('INSERT INTO admins (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role=VALUES(role)')->execute([(int)$u['id'], 'owner']);
		}
	}
} catch (Throwable $e) {
	log_error('Admin seed failed', ['e' => $e->getMessage()]);
}

// ====== HELPERS ======
function db_fetch_one(PDO $pdo, string $sql, array $params = []): ?array {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$row = $stmt->fetch();
	return $row !== false ? $row : null;
}
function db_fetch_all(PDO $pdo, string $sql, array $params = []): array {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll();
}
function settings_get(PDO $pdo, string $key, $default = null) {
	$row = db_fetch_one($pdo, 'SELECT `value` FROM settings WHERE `key` = ?', [$key]);
	if ($row) return $row['value'];
	global $CONFIG;
	if (isset($CONFIG['settings'][$key])) return $CONFIG['settings'][$key];
	return $default;
}
function settings_set(PDO $pdo, string $key, string $value): void {
	$pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$key, $value]);
}
function ensure_user(PDO $pdo, array $tgUser): array {
	$tid = (int)$tgUser['id'];
	$row = db_fetch_one($pdo, 'SELECT * FROM users WHERE telegram_id = ?', [$tid]);
	if ($row) {
		$pdo->prepare('UPDATE users SET username=?, first_name=?, last_name=?, language_code=? WHERE id=?')
			->execute([$tgUser['username'] ?? null, $tgUser['first_name'] ?? null, $tgUser['last_name'] ?? null, $tgUser['language_code'] ?? null, (int)$row['id']]);
		return db_fetch_one($pdo, 'SELECT * FROM users WHERE id = ?', [(int)$row['id']]);
	}
	$pdo->prepare('INSERT INTO users (telegram_id, username, first_name, last_name, language_code) VALUES (?,?,?,?,?)')
		->execute([$tid, $tgUser['username'] ?? null, $tgUser['first_name'] ?? null, $tgUser['last_name'] ?? null, $tgUser['language_code'] ?? null]);
	$id = (int)$pdo->lastInsertId();
	return db_fetch_one($pdo, 'SELECT * FROM users WHERE id = ?', [$id]);
}
function user_by_tid(PDO $pdo, int $telegramId): ?array {
	return db_fetch_one($pdo, 'SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
}
function admin_is(PDO $pdo, int $telegramId): bool {
	$u = user_by_tid($pdo, $telegramId);
	if (!$u) return false;
	$adm = db_fetch_one($pdo, 'SELECT * FROM admins WHERE user_id = ?', [(int)$u['id']]);
	if ($adm) return true;
	global $CONFIG;
	if (!empty($CONFIG['telegram']['admin_id']) && (int)$CONFIG['telegram']['admin_id'] === $telegramId) {
		$pdo->prepare('INSERT INTO admins (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)')->execute([(int)$u['id'], 'owner']);
		return true;
	}
	return false;
}
function add_points(PDO $pdo, int $userId, int $amount, string $type, ?int $refId = null, ?string $desc = null): void {
	$pdo->prepare('INSERT INTO points_transactions (user_id, amount, type, reference_id, description) VALUES (?,?,?,?,?)')
		->execute([$userId, $amount, $type, $refId, $desc]);
	$pdo->prepare('UPDATE users SET points = points + ? WHERE id = ?')->execute([$amount, $userId]);
}
function set_points_absolute(PDO $pdo, int $userId, int $newPoints): void {
	$cur = db_fetch_one($pdo, 'SELECT points FROM users WHERE id = ?', [$userId]);
	$old = $cur ? (int)$cur['points'] : 0;
	$delta = $newPoints - $old;
	if ($delta !== 0) {
		add_points($pdo, $userId, $delta, 'adjust', null, 'Admin set points');
	}
}
function forced_channels(PDO $pdo): array {
	return db_fetch_all($pdo, 'SELECT * FROM forced_channels WHERE is_required = 1');
}
function check_all_joined(PDO $pdo, int $userTelegramId): array {
	$chs = forced_channels($pdo);
	$missing = [];
	foreach ($chs as $ch) {
		try {
			$res = tg_getChatMember(['chat_id' => $ch['channel_id'], 'user_id' => $userTelegramId]);
			$status = $res['status'] ?? 'left';
			if (!in_array($status, ['member','administrator','creator'], true)) {
				$missing[] = $ch;
			}
		} catch (Throwable $e) {
			$missing[] = $ch;
		}
	}
	return [count($missing) === 0, $missing];
}
function kb_join_check(array $channels): array {
	$rows = [];
	foreach ($channels as $ch) {
		$rows[] = [ ['text' => 'عضویت در ' . ($ch['title'] ?: $ch['channel_id']), 'url' => 'https://t.me/' . ltrim($ch['channel_id'], '@')] ];
	}
	$rows[] = [ ['text' => 'بررسی عضویت ✅', 'callback_data' => 'check_join'] ];
	return ['inline_keyboard' => $rows];
}
function kb_order_review(int $orderId): array {
	return [ 'inline_keyboard' => [ [
		['text' => 'تایید درخواست ✅', 'callback_data' => 'order_approve:' . $orderId],
		['text' => 'رد درخواست ❌', 'callback_data' => 'order_reject:' . $orderId],
	] ] ];
}
function qualify_referral_if_pending(PDO $pdo, int $inviteeUserId): ?array {
	$ref = db_fetch_one($pdo, "SELECT r.* FROM referrals r WHERE r.invitee_user_id = ? AND r.status = 'pending'", [$inviteeUserId]);
	if (!$ref) return null;
	$pdo->prepare("UPDATE referrals SET status = 'qualified', qualified_at = NOW() WHERE id = ?")->execute([(int)$ref['id']]);
	return $ref;
}

// ====== ROUTER ======
try {
	if (isset($update['message'])) {
		log_info('onMessage', ['from' => $update['message']['from']['id'] ?? null]);
		$chatId = $update['message']['chat']['id'];
		$from = $update['message']['from'] ?? $update['message']['chat'];
		$user = ensure_user($pdo, $from);
		$text = trim($update['message']['text'] ?? '');

		// Admin state machine key
		$adminStateKey = 'admin_state_' . (int)$from['id'];
		$state = settings_get($pdo, $adminStateKey, '');

		if (strpos($text, '/start') === 0) {
			$refTid = null;
			if (preg_match('/^\/start\s+ref_(\d+)/', $text, $m)) { $refTid = (int)$m[1]; }
			if ($refTid && $refTid !== (int)$from['id']) {
				$inviter = user_by_tid($pdo, $refTid);
				if ($inviter) {
					$exists = db_fetch_one($pdo, 'SELECT id FROM referrals WHERE inviter_user_id = ? AND invitee_user_id = ?', [(int)$inviter['id'], (int)$user['id']]);
					if (!$exists) {
						$pdo->prepare('INSERT INTO referrals (inviter_user_id, invitee_user_id) VALUES (?, ?)')->execute([(int)$inviter['id'], (int)$user['id']]);
					}
				}
			}

			list($ok, $missing) = check_all_joined($pdo, (int)$from['id']);
			if (!$ok) {
				tg_sendMessage([
					'chat_id' => $chatId,
					'text' => 'لطفاً ابتدا در کانال‌های اجباری عضو شوید و سپس روی بررسی عضویت بزنید.',
					'reply_markup' => json_encode(kb_join_check($missing), JSON_UNESCAPED_UNICODE)
				]);
				return;
			}

			$pointsPerRef = (int)settings_get($pdo, 'points_per_referral', 10);
			$ref = qualify_referral_if_pending($pdo, (int)$user['id']);
			if ($ref) {
				add_points($pdo, (int)$ref['inviter_user_id'], $pointsPerRef, 'referral_reward', (int)$ref['id'], 'Referral qualified');
			}

			tg_sendMessage(['chat_id' => $chatId, 'text' => 'خوش آمدید! از /shop برای مشاهده آیتم‌ها و از /panel برای مدیریت استفاده کنید.']);
			return;
		}

		if ($text === '/shop') {
			$items = db_fetch_all($pdo, 'SELECT * FROM items WHERE is_active = 1 ORDER BY priority DESC, id ASC');
			if (!$items) {
				tg_sendMessage(['chat_id' => $chatId, 'text' => 'فعلاً آیتمی موجود نیست.']);
				return;
			}
			$rows = [];
			$msg = "فروشگاه:\n";
			foreach ($items as $it) {
				$msg .= sprintf("- %s | %d امتیاز\n", $it['name'], $it['required_points']);
				$rows[] = [ ['text' => 'درخواست ' . $it['name'], 'callback_data' => 'order:' . $it['id']] ];
			}
			tg_sendMessage(['chat_id' => $chatId, 'text' => $msg, 'reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)]);
			return;
		}

		if ($text === '/panel') {
			if (!admin_is($pdo, (int)$from['id'])) {
				tg_sendMessage(['chat_id' => $chatId, 'text' => 'دسترسی ندارید.']);
				return;
			}
			$kb = [ 'inline_keyboard' => [
				[ ['text' => 'تنظیم امتیاز کاربر ✍️', 'callback_data' => 'panel_setpoints'] ],
				[ ['text' => 'افزودن آیتم 🧩', 'callback_data' => 'panel_additem'], ['text' => 'لیست آیتم‌ها 📋', 'callback_data' => 'panel_listitems'] ],
				[ ['text' => 'افزودن کانال اجباری ➕', 'callback_data' => 'panel_addchannel'], ['text' => 'لیست کانال‌ها 📋', 'callback_data' => 'panel_listchannels'] ],
				[ ['text' => 'تنظیم امتیاز هر دعوت ⚙️', 'callback_data' => 'panel_setrefpoint'], ['text' => 'لغو ⛔', 'callback_data' => 'panel_cancel'] ],
			] ];
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'پنل مدیریت:', 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
			return;
		}

		if ($text === '/cancel') {
			settings_set($pdo, $adminStateKey, '');
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'لغو شد.']);
			return;
		}

		// Admin awaiting inputs
		if ($state === 'awaiting_setpoints' && $text !== '') {
			if (!admin_is($pdo, (int)$from['id'])) { settings_set($pdo, $adminStateKey, ''); return; }
			$parts = preg_split('/\s+/', $text);
			if (count($parts) < 2) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'فرمت نادرست. مثال: 123456789 100 یا @username 250']); return; }
			$who = $parts[0]; $points = (int)$parts[1]; $target = null;
			if (strpos($who, '@') === 0) { $u = db_fetch_one($pdo, 'SELECT * FROM users WHERE username = ?', [substr($who, 1)]); if ($u) $target = $u; }
			elseif (ctype_digit($who)) { $u = user_by_tid($pdo, (int)$who); if ($u) $target = $u; }
			if (!$target) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'کاربر یافت نشد. کاربر باید یکبار استارت کند.']); return; }
			set_points_absolute($pdo, (int)$target['id'], $points);
			settings_set($pdo, $adminStateKey, '');
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'امتیاز کاربر تنظیم شد.']);
			return;
		}

		if ($state === 'awaiting_additem' && $text !== '') {
			if (!admin_is($pdo, (int)$from['id'])) { settings_set($pdo, $adminStateKey, ''); return; }
			if (!preg_match('/^(.+?)\s*[|,\-]?\s*(\d+)$/u', $text, $m)) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'فرمت: نام آیتم | امتیاز']); return; }
			$name = trim($m[1]); $req = (int)$m[2];
			$pdo->prepare('INSERT INTO items (name, required_points) VALUES (?, ?)')->execute([$name, $req]);
			settings_set($pdo, $adminStateKey, '');
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'آیتم اضافه شد.']);
			return;
		}

		if ($state === 'awaiting_addchannel' && $text !== '') {
			if (!admin_is($pdo, (int)$from['id'])) { settings_set($pdo, $adminStateKey, ''); return; }
			$parts = preg_split('/\s+/', $text, 2);
			$chId = trim($parts[0]); $title = isset($parts[1]) ? trim($parts[1]) : null;
			$pdo->prepare('INSERT INTO forced_channels (channel_id, title) VALUES (?, ?)')->execute([$chId, $title]);
			settings_set($pdo, $adminStateKey, '');
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'کانال اضافه شد.']);
			return;
		}

		if ($state === 'awaiting_setrefpoint' && $text !== '') {
			if (!admin_is($pdo, (int)$from['id'])) { settings_set($pdo, $adminStateKey, ''); return; }
			$val = (int)preg_replace('/[^\d]/', '', $text);
			if ($val <= 0) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'یک عدد معتبر وارد کنید.']); return; }
			settings_set($pdo, 'points_per_referral', (string)$val);
			settings_set($pdo, $adminStateKey, '');
			tg_sendMessage(['chat_id' => $chatId, 'text' => 'امتیاز هر دعوت تنظیم شد.']);
			return;
		}

		// Fallback help
		tg_sendMessage(['chat_id' => $chatId, 'text' => 'برای شروع /start، فروشگاه /shop، مدیریت /panel']);
		return;
	}

	if (isset($update['callback_query'])) {
		log_info('onCallback', ['from' => $update['callback_query']['from']['id'] ?? null, 'data' => $update['callback_query']['data'] ?? '']);
		$cb = $update['callback_query'];
		$data = $cb['data'] ?? '';
		$message = $cb['message'] ?? null;
		$chatId = $message['chat']['id'] ?? null;
		$from = $cb['from'];
		$user = ensure_user($pdo, $from);

		if ($data === 'check_join') {
			list($ok, $missing) = check_all_joined($pdo, (int)$from['id']);
			if ($ok) {
				$pointsPerRef = (int)settings_get($pdo, 'points_per_referral', 10);
				$ref = qualify_referral_if_pending($pdo, (int)$user['id']);
				if ($ref) {
					add_points($pdo, (int)$ref['inviter_user_id'], $pointsPerRef, 'referral_reward', (int)$ref['id'], 'Referral qualified');
				}
				tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'عضویت تایید شد.']);
				if ($chatId) { tg_editMessageText(['chat_id' => $chatId, 'message_id' => $message['message_id'], 'text' => 'عضویت تایید شد. از /shop استفاده کنید.']); }
			} else {
				tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'عضویت ناقص است.']);
			}
			return;
		}

		// Panel callbacks
		$adminStateKey = 'admin_state_' . (int)$from['id'];
		if ($data === 'panel_setpoints') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			settings_set($pdo, $adminStateKey, 'awaiting_setpoints');
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'فرمت: آیدی/یوزرنیم و امتیاز']);
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => "لطفاً ارسال کنید: 123456789 100 یا @username 250"]); }
			return;
		}
		if ($data === 'panel_additem') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			settings_set($pdo, $adminStateKey, 'awaiting_additem');
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'نام آیتم و امتیاز لازم را وارد کنید.']);
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'مثال: استارز 100 | 150']); }
			return;
		}
		if ($data === 'panel_listitems') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			$items = db_fetch_all($pdo, 'SELECT * FROM items ORDER BY is_active DESC, priority DESC, id ASC');
			$text = $items ? "آیتم‌ها:\n" : 'آیتمی نیست.';
			foreach ($items as $it) { $text .= sprintf("#%d %s | %d | %s\n", $it['id'], $it['name'], $it['required_points'], $it['is_active'] ? 'فعال' : 'غیرفعال'); }
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => $text]); }
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'نمایش داده شد.']);
			return;
		}
		if ($data === 'panel_addchannel') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			settings_set($pdo, $adminStateKey, 'awaiting_addchannel');
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'آیدی یا @یوزرنیم کانال را وارد کنید.']);
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'مثال: @mychannel عنوان دلخواه']); }
			return;
		}
		if ($data === 'panel_listchannels') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			$chs = db_fetch_all($pdo, 'SELECT * FROM forced_channels ORDER BY id DESC');
			$text = $chs ? "کانال‌ها:\n" : 'کانالی ثبت نشده.';
			foreach ($chs as $ch) { $text .= sprintf("#%d %s %s\n", $ch['id'], $ch['channel_id'], $ch['title'] ? '(' . $ch['title'] . ')' : ''); }
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => $text]); }
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'نمایش داده شد.']);
			return;
		}
		if ($data === 'panel_setrefpoint') {
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			settings_set($pdo, $adminStateKey, 'awaiting_setrefpoint');
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'عدد امتیاز هر دعوت را ارسال کنید.']);
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'مثال: 15']); }
			return;
		}
		if ($data === 'panel_cancel') {
			settings_set($pdo, $adminStateKey, '');
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'لغو شد.']);
			return;
		}

		// Orders
		if (strpos($data, 'order:') === 0) {
			$parts = explode(':', $data, 2);
			$itemId = (int)$parts[1];
			list($ok, $missing) = check_all_joined($pdo, (int)$from['id']);
			if (!$ok) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'ابتدا عضویت را تکمیل کنید.']); return; }
			$item = db_fetch_one($pdo, 'SELECT * FROM items WHERE id = ? AND is_active = 1', [$itemId]);
			if (!$item) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'آیتم موجود نیست.']); return; }
			$u = user_by_tid($pdo, (int)$from['id']);
			if (!$u) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'خطای کاربری.']); return; }
			if ((int)$u['points'] < (int)$item['required_points']) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'امتیاز کافی نیست.']); return; }
			$pdo->prepare('INSERT INTO orders (user_id, item_id, status) VALUES (?, ?, "pending")')->execute([(int)$u['id'], $itemId]);
			$orderId = (int)$pdo->lastInsertId();

			$adminGroupId = trim((string)($CONFIG['telegram']['admin_group_id'] ?? ''));
			if ($adminGroupId !== '') {
				$txt = "درخواست جدید:\nکاربر: " . ((string)($from['username'] ?? $from['id'])) . "\nآیتم: " . $item['name'] . "\nوضعیت: در حال بررسی";
				try {
					$msg = tg_sendMessage(['chat_id' => $adminGroupId, 'text' => $txt, 'reply_markup' => json_encode(kb_order_review($orderId), JSON_UNESCAPED_UNICODE)]);
					$pdo->prepare('UPDATE orders SET status = "under_review", admin_message_id = ? WHERE id = ?')->execute([(int)$msg['message_id'], $orderId]);
				} catch (Throwable $e) {
					log_error('send to admin group failed', ['e' => $e->getMessage()]);
				}
			}
			tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'درخواست ثبت شد.']);
			if ($chatId) { tg_sendMessage(['chat_id' => $chatId, 'text' => 'درخواست شما ارسال شد. منتظر تایید ادمین باشید.']); }
			return;
		}

		if (strpos($data, 'order_approve:') === 0 || strpos($data, 'order_reject:') === 0) {
			$orderId = (int)substr($data, strpos($data, ':') + 1);
			$isApprove = strpos($data, 'order_approve:') === 0;
			if (!admin_is($pdo, (int)$from['id'])) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']); return; }
			$order = db_fetch_one($pdo, 'SELECT * FROM orders WHERE id = ?', [$orderId]);
			if (!$order) { tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'سفارش یافت نشد.']); return; }
			$u = db_fetch_one($pdo, 'SELECT * FROM users WHERE id = ?', [(int)$order['user_id']]);
			$item = db_fetch_one($pdo, 'SELECT * FROM items WHERE id = ?', [(int)$order['item_id']]);
			if ($isApprove) {
				$need = (int)$item['required_points'];
				$cur = (int)$u['points'];
				if ($cur >= $need) { add_points($pdo, (int)$u['id'], -$need, 'spend', $orderId, 'Order approved'); }
				$pdo->prepare('UPDATE orders SET status = "approved", admin_id = ? WHERE id = ?')->execute([(int)(user_by_tid($pdo, (int)$from['id'])['id'] ?? 0), $orderId]);
				tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'تایید شد.']);
				if ($message && isset($message['message_id'])) { tg_editMessageText(['chat_id' => $message['chat']['id'], 'message_id' => $message['message_id'], 'text' => 'درخواست تایید شد ✅']); }
				try { tg_sendMessage(['chat_id' => (int)$u['telegram_id'], 'text' => 'درخواست شما تایید شد ✅']); } catch (Throwable $e) {}
			} else {
				$pdo->prepare('UPDATE orders SET status = "rejected", admin_id = ? WHERE id = ?')->execute([(int)(user_by_tid($pdo, (int)$from['id'])['id'] ?? 0), $orderId]);
				tg_answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'رد شد.']);
				if ($message && isset($message['message_id'])) { tg_editMessageText(['chat_id' => $message['chat']['id'], 'message_id' => $message['message_id'], 'text' => 'درخواست رد شد ❌']); }
				try { tg_sendMessage(['chat_id' => (int)$u['telegram_id'], 'text' => 'درخواست شما رد شد ❌']); } catch (Throwable $e) {}
			}
			return;
		}
	}
} catch (Throwable $e) {
	log_error('runtime', ['e' => $e->getMessage()]);
}

http_response_code(200);
echo 'ok';