<?php
declare(strict_types=1);

require __DIR__ . '/confige.php';

initDatabase();

$input = file_get_contents('php://input');
$update = json_decode($input ?: '[]', true) ?: [];

// Master bot token
$BOT_TOKEN = MASTER_BOT_TOKEN;
if (!$BOT_TOKEN) {
	echo 'Missing MASTER_BOT_TOKEN';
	exit;
}

function getLangForUser(?array $from): string {
	$lang = $from['language_code'] ?? 'en';
	return substr($lang, 0, 2);
}

function ensureWebhook(): void {
	$url = BASE_WEBHOOK_URL . '/master.php';
	$res = tgSetWebhook(MASTER_BOT_TOKEN, $url);
	if (!($res['ok'] ?? false)) {
		logError('setWebhook failed', ['res' => $res]);
	}
}

// Commands
function handleCommand(array $update): void {
	global $BOT_TOKEN;
	$message = $update['message'] ?? $update['edited_message'] ?? null;
	if (!$message) return;
	$chatId = $message['chat']['id'] ?? null;
	$from = $message['from'] ?? [];
	if (!$chatId || !$from) return;
	$userId = (int)($from['id'] ?? 0);
	$username = $from['username'] ?? null;
	$lang = getLangForUser($from);
	upsertUser($userId, $username, $lang);
	if (isUserBanned($userId)) return;

	$text = trim((string)($message['text'] ?? ''));
	if ($text === '') return;

	if ($text === '/start') {
		tgSendMessage($BOT_TOKEN, $chatId, t($lang, 'master_start'));
		tgSendMessage($BOT_TOKEN, $chatId, t($lang, 'menu_master'));
		return;
	}

	// Admin-only commands
	$admin = isAdminUser($userId);
	if (!$admin) return;

	if (str_starts_with($text, '/createbot ')) {
		$parts = explode(' ', $text, 2);
		$token = trim($parts[1] ?? '');
		if (!$token) {
			tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /createbot <TOKEN>');
			return;
		}
		$botId = registerBot($userId, $token, 'en');
		$hookUrl = BASE_WEBHOOK_URL . '/music.php?bot_id=' . $botId;
		$res = tgSetWebhook($token, $hookUrl);
		if (!($res['ok'] ?? false)) {
			logError('child setWebhook failed', ['res' => $res]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Webhook failed.');
			return;
		}
		tgSendMessage($BOT_TOKEN, $chatId, t($lang, 'created_bot') . " ID: {$botId}");
		return;
	}

	if ($text === '/listbots') {
		$pdo = db();
		$rows = $pdo->query('SELECT id, owner_id, is_vip, vip_level, vip_expire, lang_default, public_enabled, LEFT(bot_token, 15) AS token_prefix FROM bots ORDER BY id DESC LIMIT 100')->fetchAll();
		if (!$rows) {
			tgSendMessage($BOT_TOKEN, $chatId, 'No bots.');
			return;
		}
		$out = [];
		foreach ($rows as $r) {
			$out[] = "#{$r['id']} owner={$r['owner_id']} VIP={$r['is_vip']}({$r['vip_level']}) exp={$r['vip_expire']} pub={$r['public_enabled']} tk={$r['token_prefix']}...";
		}
		tgSendMessage($BOT_TOKEN, $chatId, implode("\n", $out));
		return;
	}

	if (str_starts_with($text, '/bot ')) {
		// /bot <id> enable|disable|vip <level> <days>|delete
		$parts = preg_split('/\s+/', $text);
		if (count($parts) < 3) {
			tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /bot <id> enable|disable|delete|vip <level> <days>');
			return;
		}
		$botId = (int)$parts[1];
		$cmd = strtolower($parts[2]);
		$pdo = db();
		$bot = getBotById($botId);
		if (!$bot) { tgSendMessage($BOT_TOKEN, $chatId, 'Bot not found'); return; }
		if ($cmd === 'enable' || $cmd === 'disable') {
			$flag = $cmd === 'enable' ? 1 : 0;
			$pdo->prepare('UPDATE bots SET public_enabled = :p WHERE id = :b')->execute([':p' => $flag, ':b' => $botId]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Public set to ' . $flag);
			return;
		}
		if ($cmd === 'delete') {
			$pdo->prepare('DELETE FROM bots WHERE id = :b')->execute([':b' => $botId]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Bot deleted');
			return;
		}
		if ($cmd === 'vip' && (count($parts) >= 5)) {
			$level = strtolower($parts[3]);
			$days = max(1, (int)$parts[4]);
			$expire = date('Y-m-d H:i:s', time() + $days * 86400);
			setBotVip($botId, $level, $expire);
			tgSendMessage($BOT_TOKEN, $chatId, 'VIP set: ' . $level . ' until ' . $expire);
			return;
		}
		return;
	}

	if (str_starts_with($text, '/vipprice ')) {
		$parts = explode(' ', $text, 2);
		$stars = (int)trim($parts[1] ?? '0');
		$pdo = db();
		$pdo->prepare('INSERT INTO vip_config (price_local, price_stars, is_open) VALUES (0, :s, 1)')->execute([':s' => $stars]);
		tgSendMessage($BOT_TOKEN, $chatId, 'VIP price set: ' . $stars . ' Stars');
		return;
	}

	if (str_starts_with($text, '/vipopen ')) {
		$parts = explode(' ', $text, 2);
		$on = strtolower(trim($parts[1] ?? ''));
		$flag = $on === 'on' ? 1 : 0;
		$pdo = db();
		$cfg = getVipConfig();
		$pdo->prepare('INSERT INTO vip_config (price_local, price_stars, is_open) VALUES (:pl, :ps, :io)')
			->execute([':pl' => $cfg['price_local'] ?? 0, ':ps' => $cfg['price_stars'] ?? 100, ':io' => $flag]);
		tgSendMessage($BOT_TOKEN, $chatId, 'VIP purchasing ' . ($flag ? 'opened' : 'closed'));
		return;
	}

	if (str_starts_with($text, '/buyvip ')) {
		// /buyvip <bot_id> <level> <days>
		$parts = preg_split('/\s+/', $text);
		if (count($parts) < 4) {
			tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /buyvip <bot_id> <basic|premium|pro> <days>');
			return;
		}
		$botId = (int)$parts[1];
		$level = strtolower($parts[2]);
		$days = max(1, (int)$parts[3]);
		$cfg = getVipConfig();
		if (!(int)($cfg['is_open'] ?? 1)) {
			tgSendMessage($BOT_TOKEN, $chatId, 'VIP purchase is closed.');
			return;
		}
		$priceStars = computeVipPriceStars($level, $days, $cfg);

		// Stars invoice: currency XTR, prices in integer of stars*1000 (subunits)
		$prices = json_encode([[
			'label' => strtoupper($level) . ' ' . $days . 'd',
			'amount' => $priceStars * 1000
		]]);
		$payload = json_encode(['bot_id' => $botId, 'level' => $level, 'days' => $days]);
		$invoice = [
			'chat_id' => $chatId,
			'title' => 'VIP ' . strtoupper($level),
			'description' => 'Activate VIP for bot #' . $botId . ' for ' . $days . ' days',
			'payload' => $payload,
			'currency' => 'XTR',
			'prices' => $prices,
		];
		if (PAYMENT_PROVIDER_TOKEN) $invoice['provider_token'] = PAYMENT_PROVIDER_TOKEN;
		$res = tgSendInvoice($BOT_TOKEN, $invoice);
		if (!($res['ok'] ?? false)) {
			logError('sendInvoice failed', ['res' => $res]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Invoice failed.');
			return;
		}
		return;
	}

	if ($text === '/rehook') {
		$pdo = db();
		$rows = $pdo->query('SELECT id, bot_token FROM bots ORDER BY id ASC')->fetchAll();
		$okCount = 0; $failCount = 0;
		foreach ($rows as $r) {
			$hookUrl = BASE_WEBHOOK_URL . '/music.php?bot_id=' . $r['id'];
			$res = tgSetWebhook($r['bot_token'], $hookUrl);
			if ($res['ok'] ?? false) $okCount++; else $failCount++;
		}
		tgSendMessage($BOT_TOKEN, $chatId, "Rehook completed. OK={$okCount} FAIL={$failCount}");
		return;
	}

	if (str_starts_with($text, '/vipall ')) {
		$sub = trim(substr($text, 8));
		$pdo = db();
		if ($sub === 'off') {
			$pdo->exec('UPDATE bots SET is_vip = 0, vip_level = NULL, vip_expire = NULL');
			tgSendMessage($BOT_TOKEN, $chatId, 'VIP cleared for all bots');
			return;
		}
		$parts = preg_split('/\s+/', $sub);
		if (count($parts) >= 2) {
			$level = strtolower($parts[0]);
			$days = max(1, (int)$parts[1]);
			$expire = date('Y-m-d H:i:s', time() + $days * 86400);
			$stmt = $pdo->prepare('UPDATE bots SET is_vip = 1, vip_level = :lvl, vip_expire = :exp');
			$stmt->execute([':lvl' => $level, ':exp' => $expire]);
			tgSendMessage($BOT_TOKEN, $chatId, 'VIP set for all: ' . $level . ' until ' . $expire);
			return;
		}
		tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /vipall off | /vipall <level> <days>');
		return;
	}

	if (str_starts_with($text, '/ads_add ')) {
		$sub = trim(substr($text, 9));
		$pdo = db();
		if (str_starts_with($sub, 'text ')) {
			$content = trim(substr($sub, 5));
			$pdo->prepare('INSERT INTO ads (type, content) VALUES ("text", :c)')->execute([':c' => $content]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Ad saved.');
			return;
		}
		if (str_starts_with($sub, 'photo ')) {
			$content = trim(substr($sub, 6));
			$pdo->prepare('INSERT INTO ads (type, content) VALUES ("photo", :c)')->execute([':c' => $content]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Photo ad saved.');
			return;
		}
		if (str_starts_with($sub, 'video ')) {
			$content = trim(substr($sub, 6));
			$pdo->prepare('INSERT INTO ads (type, content) VALUES ("video", :c)')->execute([':c' => $content]);
			tgSendMessage($BOT_TOKEN, $chatId, 'Video ad saved.');
			return;
		}
		tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /ads_add text|photo|video <content or URL>');
		return;
	}

	if ($text === '/ads_list') {
		$pdo = db();
		$rows = $pdo->query('SELECT id, type, LEFT(content, 60) AS preview, created_at FROM ads ORDER BY id DESC LIMIT 50')->fetchAll();
		if (!$rows) {
			tgSendMessage($BOT_TOKEN, $chatId, 'No ads.');
			return;
		}
		$out = [];
		foreach ($rows as $r) {
			$out[] = "#{$r['id']} [{$r['type']}] {$r['preview']}...";
		}
		tgSendMessage($BOT_TOKEN, $chatId, implode("\n", $out));
		return;
	}

	if (str_starts_with($text, '/ads_kb ')) {
		$sub = trim(substr($text, 8));
		$space = strpos($sub, ' ');
		if ($space === false) { tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /ads_kb <id> <json>'); return; }
		$id = (int)substr($sub, 0, $space);
		$json = trim(substr($sub, $space + 1));
		$pdo = db();
		$pdo->prepare('UPDATE ads SET inline_keyboard = :j WHERE id = :i')->execute([':j' => $json, ':i' => $id]);
		tgSendMessage($BOT_TOKEN, $chatId, 'Inline keyboard set for Ad #' . $id);
		return;
	}

	if (str_starts_with($text, '/deletead ')) {
		$id = (int)trim(substr($text, 10));
		$pdo = db();
		$pdo->prepare('DELETE FROM ads WHERE id = :i')->execute([':i' => $id]);
		tgSendMessage($BOT_TOKEN, $chatId, 'Ad deleted: #' . $id);
		return;
	}

	if (str_starts_with($text, '/setbotname ')) {
		$sub = trim(substr($text, 12));
		$space = strpos($sub, ' ');
		if ($space === false) { tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /setbotname <bot_id> <name>'); return; }
		$botId = (int)substr($sub, 0, $space);
		$name = trim(substr($sub, $space + 1));
		$bot = getBotById($botId);
		if (!$bot) { tgSendMessage($BOT_TOKEN, $chatId, 'Bot not found'); return; }
		if (!hasVipLevel($bot, 'premium')) { tgSendMessage($BOT_TOKEN, $chatId, 'VIP required (Premium+)'); return; }
		$res = tgSetMyName($bot['bot_token'], $name);
		tgSendMessage($BOT_TOKEN, $chatId, ($res['ok'] ?? false) ? 'Name updated.' : 'Failed to set name');
		return;
	}

	if (str_starts_with($text, '/setbotdesc ')) {
		$sub = trim(substr($text, 12));
		$space = strpos($sub, ' ');
		if ($space === false) { tgSendMessage($BOT_TOKEN, $chatId, 'Usage: /setbotdesc <bot_id> <description>'); return; }
		$botId = (int)substr($sub, 0, $space);
		$desc = trim(substr($sub, $space + 1));
		$bot = getBotById($botId);
		if (!$bot) { tgSendMessage($BOT_TOKEN, $chatId, 'Bot not found'); return; }
		if (!hasVipLevel($bot, 'premium')) { tgSendMessage($BOT_TOKEN, $chatId, 'VIP required (Premium+)'); return; }
		$res = tgSetMyDescription($bot['bot_token'], $desc);
		tgSendMessage($BOT_TOKEN, $chatId, ($res['ok'] ?? false) ? 'Description updated.' : 'Failed to set description');
		return;
	}

	if (str_starts_with($text, '/users ')) {
		$sub = trim(substr($text, 7));
		$pdo = db();
		if ($sub === 'list') {
			$rows = $pdo->query('SELECT user_id, username, lang, is_banned, created_at FROM users ORDER BY id DESC LIMIT 100')->fetchAll();
			$out = [];
			foreach ($rows as $r) {
				$out[] = ($r['is_banned'] ? '🚫' : '✅') . ' ' . $r['user_id'] . ' @' . ($r['username'] ?? '-') . ' [' . $r['lang'] . ']';
			}
			tgSendMessage($BOT_TOKEN, $chatId, implode("\n", $out) ?: 'No users.');
			return;
		}
		if (str_starts_with($sub, 'ban ')) {
			$uid = (int)trim(substr($sub, 4));
			setUserBanned($uid, true);
			tgSendMessage($BOT_TOKEN, $chatId, 'User banned: ' . $uid);
			return;
		}
		if (str_starts_with($sub, 'unban ')) {
			$uid = (int)trim(substr($sub, 6));
			setUserBanned($uid, false);
			tgSendMessage($BOT_TOKEN, $chatId, 'User unbanned: ' . $uid);
			return;
		}
		return;
	}
}

function handlePreCheckout(array $update): void {
	global $BOT_TOKEN;
	$pc = $update['pre_checkout_query'] ?? null;
	if (!$pc) return;
	$ok = true; // validate as needed
	$answer = tgRequest($BOT_TOKEN, 'answerPreCheckoutQuery', ['pre_checkout_query_id' => $pc['id'], 'ok' => $ok ? true : false]);
	if (!($answer['ok'] ?? false)) logError('answerPreCheckoutQuery failed', ['res' => $answer]);
}

function handleSuccessfulPayment(array $update): void {
	$message = $update['message'] ?? null;
	if (!$message) return;
	$sp = $message['successful_payment'] ?? null;
	if (!$sp) return;
	$payload = json_decode($sp['invoice_payload'] ?? '{}', true) ?: [];
	$botId = (int)($payload['bot_id'] ?? 0);
	$level = (string)($payload['level'] ?? 'basic');
	$days = max(1, (int)($payload['days'] ?? 30));
	$expire = date('Y-m-d H:i:s', time() + $days * 86400);
	setBotVip($botId, $level, $expire);
}

if (isset($update['pre_checkout_query'])) {
	handlePreCheckout($update);
} elseif (isset($update['message']['successful_payment'])) {
	handleSuccessfulPayment($update);
} elseif (isset($update['message'])) {
	handleCommand($update);
}

// Allow ping to ensure webhook set
if (php_sapi_name() === 'cli' && ($argv[1] ?? null) === 'setwebhook') {
	ensureWebhook();
}

echo 'OK';

