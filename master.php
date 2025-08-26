<?php
declare(strict_types=1);

/*
	Master Bot (admin/builder)

	Webhook URL to set for master bot:
	  BASE_WEBHOOK_URL/master.php

	Key features:
	- Register new user bots: /newbot <token>
	- List/manage bots: /bots, /togglebot <id>, /setpublic <id> <on|off>, /delbot <id>
	- VIP config and per-bot VIP: /vip_price <local> <stars>, /vip_open, /vip_close, /setvip <id> <Basic|Premium|Pro> <days>
	- Ads management: /ads, /ad_text <content> [| <inline_keyboard_json>], /ad_photo <url> [| <inline_keyboard_json>], /ad_video <url> [| <inline_keyboard_json>], /ad_mixed <content> [| <inline_keyboard_json>], /ad_del <id>
	- Users: /users, /ban <user_id>, /unban <user_id>, /banned
	- Utilities: /setwebhook, /help, /bump_version <version>

	All user bots share the same bot engine at: BASE_WEBHOOK_URL/bot.php?token=<BOT_TOKEN>
	This ensures simultaneous updates across all created bots when the source changes.
*/

require_once __DIR__ . '/config.php';

$pdo = getPdo();
initDatabase($pdo);

$update = readUpdate();
if (!$update) {
	echo 'OK';
	exit;
}

$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

if ($message) {
	// Handle Telegram Stars successful payment
	if (isset($message['successful_payment'])) {
		handleSuccessfulPayment($pdo, $message);
		echo 'OK';
		exit;
	}
	$chatId = (int)($message['chat']['id'] ?? 0);
	$fromId = (int)($message['from']['id'] ?? 0);
	$username = $message['from']['username'] ?? null;
	$text = (string)($message['text'] ?? '');
	ensureUser($pdo, $fromId, $username, null);
	if (isBanned($pdo, $fromId)) {
		// Silently ignore
		echo 'OK';
		exit;
	}
	[$cmd, $args] = getCommandAndArgs($text);
	switch ($cmd) {
		case '/start':
			$kb = replyMarkupInline([
				[['text' => '➕ New Bot', 'callback_data' => 'newbot']],
				[['text' => '🤖 My Bots', 'callback_data' => 'mybots']],
				[['text' => '⭐ VIP', 'callback_data' => 'vip']],
				[['text' => '📢 Ads', 'callback_data' => 'ads']],
			]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, t('welcome_master', getUserLang($pdo, $fromId, 'fa')), $kb);
			break;

		case '/help':
			$help = "Commands:\n" .
				"/newbot <token> - Register a user bot\n" .
				"/bots - List bots\n" .
				"/togglebot <id> - Toggle active\n" .
				"/setpublic <id> <on|off>\n" .
				"/setvip <id> <Basic|Premium|Pro> <days>\n" .
				"/delbot <id>\n" .
				"/vip_price <local> <stars>\n" .
				"/vip_open | /vip_close\n" .
				"/ads | /ad_text | /ad_photo | /ad_video | /ad_mixed | /ad_del <id>\n" .
				"/users | /ban <uid> | /unban <uid> | /banned\n" .
				"/setwebhook | /bump_version <v>\n" .
				"/buyvip <id> <Basic|Premium|Pro> <days> - Pay with Stars\n";
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, $help);
			break;

		case '/setwebhook':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$url = rtrim(BASE_WEBHOOK_URL, '/') . '/master.php';
			$res = tgSetWebhook(MASTER_BOT_TOKEN, $url);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Master webhook: ' . ($res['ok'] ? 'OK' : 'FAIL'));
			break;

		case '/newbot':
			$newToken = trim($args);
			if ($newToken === '') {
				tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /newbot <token>');
				break;
			}
			$existing = getBotRowByToken($pdo, $newToken);
			if ($existing) {
				tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'This bot is already registered. ID: ' . (int)$existing['id']);
				break;
			}
			$gm = tgApiRequest($newToken, 'getMe');
			if (!($gm['ok'] ?? false)) {
				tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Token invalid or getMe failed.');
				break;
			}
			$botUser = $gm['result'] ?? [];
			$pdo->prepare('INSERT INTO bots (owner_id, bot_token, is_vip, vip_level, vip_expire, lang_default, public_enabled, is_active, source_version) VALUES (?,?,?,?,?,?,?,?,?)')
				->execute([$fromId, $newToken, 0, null, null, 'fa', 1, 1, SOURCE_VERSION]);
			$botId = (int)$pdo->lastInsertId();
			$pdo->prepare('INSERT IGNORE INTO bot_admins (bot_id, admin_user_id) VALUES (?,?)')->execute([$botId, $fromId]);
			ensureStatsRow($pdo, $botId);
			$url = rtrim(BASE_WEBHOOK_URL, '/') . '/bot.php?token=' . urlencode($newToken);
			$wh = tgSetWebhook($newToken, $url);
			$msg = 'Bot registered. ID: ' . $botId . "\n" .
				'Username: @' . ($botUser['username'] ?? 'unknown') . "\n" .
				'Webhook: ' . ($wh['ok'] ? 'OK' : 'FAIL');
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, $msg);
			break;

		case '/bots':
			if (isMasterAdmin($fromId)) {
				$stmt = $pdo->query('SELECT id, owner_id, is_active, is_vip, vip_level, public_enabled, source_version FROM bots ORDER BY id DESC LIMIT 50');
			} else {
				$stmt = $pdo->prepare('SELECT id, owner_id, is_active, is_vip, vip_level, public_enabled, source_version FROM bots WHERE owner_id = ? ORDER BY id DESC LIMIT 50');
				$stmt->execute([$fromId]);
			}
			$rows = $stmt->fetchAll();
			if (!$rows) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'No bots.'); break; }
			$out = [];
			foreach ($rows as $r) {
				$out[] = '#' . $r['id'] . ' ' . ($r['is_active'] ? '✅' : '⛔') . ' ' . ($r['public_enabled'] ? 'Public' : 'Private') . ' ' .
					(($r['is_vip'] ? ('VIP-' . ($r['vip_level'] ?? '')) : 'Free')) . ' v' . ($r['source_version'] ?? '');
			}
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, implode("\n", $out));
			break;

		case '/togglebot':
			[$idStr] = explode(' ', $args . ' ');
			$botId = (int)$idStr;
			if ($botId <= 0) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /togglebot <id>'); break; }
			if (!canManageBot($pdo, $fromId, $botId)) { deny($chatId); break; }
			$pdo->prepare('UPDATE bots SET is_active = 1 - is_active WHERE id = ?')->execute([$botId]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Toggled bot #' . $botId);
			break;

		case '/setpublic':
			list($idStr, $state) = array_pad(explode(' ', $args, 2), 2, null);
			$botId = (int)$idStr;
			if ($botId <= 0 || !in_array($state, ['on','off'], true)) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /setpublic <id> <on|off>'); break; }
			if (!canManageBot($pdo, $fromId, $botId)) { deny($chatId); break; }
			$pdo->prepare('UPDATE bots SET public_enabled = ? WHERE id = ?')->execute([$state === 'on' ? 1 : 0, $botId]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Updated visibility for #' . $botId);
			break;

		case '/setvip':
			list($idStr, $level, $daysStr) = array_pad(explode(' ', $args, 3), 3, null);
			$botId = (int)$idStr; $days = (int)($daysStr ?? '30');
			if ($botId <= 0 || !in_array($level, ['Basic','Premium','Pro'], true)) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /setvip <id> <Basic|Premium|Pro> <days>'); break; }
			if (!canManageBot($pdo, $fromId, $botId)) { deny($chatId); break; }
			$expire = date('Y-m-d H:i:s', time() + max(1, $days) * 86400);
			$pdo->prepare('UPDATE bots SET is_vip = 1, vip_level = ?, vip_expire = ? WHERE id = ?')->execute([$level, $expire, $botId]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'VIP set for #' . $botId . ' (' . $level . ', ' . $days . ' days)');
			break;

		case '/delbot':
			$botId = (int)$args;
			if ($botId <= 0) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /delbot <id>'); break; }
			if (!canManageBot($pdo, $fromId, $botId)) { deny($chatId); break; }
			$pdo->prepare('DELETE FROM bots WHERE id = ?')->execute([$botId]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Deleted bot #' . $botId);
			break;

		case '/vip_price':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			list($plocal, $pstars) = array_pad(explode(' ', $args, 2), 2, '0');
			$pdo->prepare('UPDATE vip_config SET price_local = ?, price_stars = ?, created_at = NOW() ORDER BY id ASC LIMIT 1')->execute([(float)$plocal, (int)$pstars]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'VIP base price set.');
			break;

		case '/vip_open':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$pdo->exec('UPDATE vip_config SET is_open = 1 ORDER BY id ASC LIMIT 1');
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'VIP is open.');
			break;

		case '/buyvip':
			list($idStr, $level, $daysStr) = array_pad(explode(' ', $args, 3), 3, null);
			$botId = (int)$idStr; $days = (int)($daysStr ?? '30');
			if ($botId <= 0 || !in_array($level, ['Basic','Premium','Pro'], true)) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /buyvip <id> <Basic|Premium|Pro> <days>'); break; }
			if (!isVipOpen($pdo)) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, t('vip_closed', getUserLang($pdo, $fromId, 'fa'))); break; }
			if (!canManageBot($pdo, $fromId, $botId)) { deny($chatId); break; }
			$prices = computeVipPrices($pdo, $level);
			$stars = max(1, (int)ceil(($prices['price_stars'] ?: 1) * max(1, $days) / 30));
			$payload = 'vip:' . $botId . ':' . $level . ':' . $days;
			$invoice = [
				'title' => 'VIP ' . $level,
				'description' => 'Activate VIP for bot #' . $botId . ' (' . $days . ' days).',
				'payload' => $payload,
				'currency' => 'XTR',
				'prices' => [['label' => 'VIP ' . $level, 'amount' => $stars]],
			];
			$res = tgSendInvoice(MASTER_BOT_TOKEN, $chatId, $invoice);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, $res['ok'] ? 'Invoice sent.' : 'Failed to send invoice.');
			break;

		case '/vip_close':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$pdo->exec('UPDATE vip_config SET is_open = 0 ORDER BY id ASC LIMIT 1');
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'VIP is closed.');
			break;

		case '/ads':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$stmt = $pdo->query('SELECT id, type, LEFT(content, 60) AS c, created_at FROM ads ORDER BY id DESC LIMIT 20');
			$rows = $stmt->fetchAll();
			if (!$rows) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'No ads.'); break; }
			$lines = array_map(fn($r) => '#' . $r['id'] . ' [' . $r['type'] . '] ' . $r['c'] . '...', $rows);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, implode("\n", $lines));
			break;

		case '/ad_text':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			[$content, $kb] = explodeInlineArg($args);
			insertAd($pdo, 'text', $content, $kb);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Ad saved.');
			break;

		case '/ad_photo':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			[$content, $kb] = explodeInlineArg($args);
			insertAd($pdo, 'photo', $content, $kb);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Ad saved.');
			break;

		case '/ad_video':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			[$content, $kb] = explodeInlineArg($args);
			insertAd($pdo, 'video', $content, $kb);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Ad saved.');
			break;

		case '/ad_mixed':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			[$content, $kb] = explodeInlineArg($args);
			insertAd($pdo, 'mixed', $content, $kb);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Ad saved.');
			break;

		case '/ad_del':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$id = (int)$args;
			$pdo->prepare('DELETE FROM ads WHERE id = ?')->execute([$id]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Ad deleted.');
			break;

		case '/users':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$row = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch();
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Total users: ' . (int)$row['c']);
			break;

		case '/ban':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$uid = (int)$args; if ($uid <= 0) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /ban <user_id>'); break; }
			$pdo->prepare('INSERT INTO banned_users (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)')->execute([$uid]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Banned ' . $uid);
			break;

		case '/unban':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$uid = (int)$args; if ($uid <= 0) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /unban <user_id>'); break; }
			$pdo->prepare('DELETE FROM banned_users WHERE user_id = ?')->execute([$uid]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Unbanned ' . $uid);
			break;

		case '/banned':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$stmt = $pdo->query('SELECT user_id FROM banned_users ORDER BY id DESC LIMIT 50');
			$rows = $stmt->fetchAll();
			if (!$rows) { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'No banned users.'); break; }
			$ids = array_map(fn($r) => (string)$r['user_id'], $rows);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, implode(', ', $ids));
			break;

		case '/bump_version':
			guardMasterAdminOrReply($pdo, $fromId, $chatId);
			$v = trim($args);
			if ($v === '') { tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Usage: /bump_version <v>'); break; }
			$pdo->prepare('UPDATE bots SET source_version = ?')->execute([$v]);
			tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Set source_version=' . $v . ' for all bots');
			break;

		default:
			// Fallback
			// Optionally implement inline flows through callback
	}
} elseif ($callback) {
	$fromId = (int)($callback['from']['id'] ?? 0);
	$chatId = (int)($callback['message']['chat']['id'] ?? 0);
	$cbid = (string)($callback['id'] ?? '');
	$data = (string)($callback['data'] ?? '');
	if ($data === 'newbot') {
		tgAnswerCallback(MASTER_BOT_TOKEN, $cbid, 'Send /newbot <token>');
	} elseif ($data === 'mybots') {
		$_GET = [];
		$message = ['chat' => ['id' => $chatId], 'from' => ['id' => $fromId], 'text' => '/bots'];
		$update['message'] = $message; // quick reuse
		// Do nothing else, Telegram will deliver no new update; we just hint.
		tgAnswerCallback(MASTER_BOT_TOKEN, $cbid, 'Use /bots');
	} else {
		tgAnswerCallback(MASTER_BOT_TOKEN, $cbid, '');
	}
}

echo 'OK';

// ===== Helpers (local to master) =====

function canManageBot(PDO $pdo, int $userId, int $botId): bool {
	if (isMasterAdmin($userId)) return true;
	$stmt = $pdo->prepare('SELECT owner_id FROM bots WHERE id = ?');
	$stmt->execute([$botId]);
	$ownerId = (int)($stmt->fetch()['owner_id'] ?? 0);
	return $ownerId === $userId;
}

function guardMasterAdminOrReply(PDO $pdo, int $userId, int $chatId): void {
	if (!isMasterAdmin($userId)) {
		tgSendMessage(MASTER_BOT_TOKEN, $chatId, t('not_authorized', getUserLang($pdo, $userId, 'fa')));
		exit;
	}
}

function deny(int $chatId): void {
	tgSendMessage(MASTER_BOT_TOKEN, $chatId, 'Not authorized.');
}

function isBanned(PDO $pdo, int $userId): bool {
	$stmt = $pdo->prepare('SELECT 1 FROM banned_users WHERE user_id = ?');
	$stmt->execute([$userId]);
	return (bool)$stmt->fetch();
}

function explodeInlineArg(string $arg): array {
	$parts = explode('|', $arg, 2);
	$content = trim($parts[0] ?? '');
	$kbJson = trim($parts[1] ?? '');
	$kb = '';
	if ($kbJson !== '') {
		// Expect a JSON array of button rows
		$json = json_decode($kbJson, true);
		if (is_array($json)) {
			$kb = json_encode($json, JSON_UNESCAPED_UNICODE);
		}
	}
	return [$content, $kb];
}

function insertAd(PDO $pdo, string $type, string $content, string $kbJson = ''): void {
	$pdo->prepare('INSERT INTO ads (type, content, inline_keyboard) VALUES (?,?,?)')->execute([$type, $content, $kbJson !== '' ? $kbJson : null]);
}

function handleSuccessfulPayment(PDO $pdo, array $message): void {
	$fromId = (int)($message['from']['id'] ?? 0);
	$sp = $message['successful_payment'] ?? [];
	$currency = $sp['currency'] ?? '';
	if (strtoupper($currency) !== 'XTR') { return; }
	$payload = (string)($sp['invoice_payload'] ?? '');
	// payload format: vip:<botId>:<level>:<days>
	if (strpos($payload, 'vip:') !== 0) { return; }
	$parts = explode(':', $payload);
	$botId = (int)($parts[1] ?? 0);
	$level = (string)($parts[2] ?? 'Basic');
	$days = (int)($parts[3] ?? 30);
	if ($botId <= 0) return;
	// Ensure payer can manage the bot or is master admin
	if (!canManageBot($pdo, $fromId, $botId) && !isMasterAdmin($fromId)) { return; }
	$expire = date('Y-m-d H:i:s', time() + max(1, $days) * 86400);
	$pdo->prepare('UPDATE bots SET is_vip = 1, vip_level = ?, vip_expire = ? WHERE id = ?')->execute([$level, $expire, $botId]);
	$chatId = (int)($message['chat']['id'] ?? 0);
	tgSendMessage(MASTER_BOT_TOKEN, $chatId, t('vip_bought', getUserLang($pdo, $fromId, 'fa')));
}

?>

