<?php
declare(strict_types=1);

require __DIR__ . '/confige.php';

initDatabase();

$input = file_get_contents('php://input');
$update = json_decode($input ?: '[]', true) ?: [];

$BOT_ID = (int)($_GET['bot_id'] ?? 0);
if ($BOT_ID <= 0) {
	echo 'Missing bot_id';
	exit;
}

$botRow = getBotById($BOT_ID);
if (!$botRow) {
	echo 'Bot not found';
	exit;
}

$BOT_TOKEN = $botRow['bot_token'];

function getUserLang(int $userId, ?string $fallback = 'en'): string {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT lang FROM users WHERE user_id = :u');
	$stmt->execute([':u' => $userId]);
	$row = $stmt->fetch();
	return $row ? ($row['lang'] ?? $fallback ?? 'en') : ($fallback ?? 'en');
}

function handleStart(array $update, array $botRow): void {
	global $BOT_TOKEN, $BOT_ID;
	$msg = $update['message'];
	$chatId = $msg['chat']['id'];
	$from = $msg['from'];
	$userId = (int)$from['id'];
	$username = $from['username'] ?? null;
	$lang = substr(($from['language_code'] ?? $botRow['lang_default'] ?? 'en'), 0, 2);
	upsertUser($userId, $username, $lang);
	bumpStat($BOT_ID, 'total_users', 1);
	$st = getSettings($BOT_ID);
	$welcome = ($st['welcome_text'] ?? '') ?: t($lang, 'user_start');
	$kb = kbInline([[['text' => 'Top 10', 'callback_data' => 'top10']]]);
	tgSendMessage($BOT_TOKEN, $chatId, $welcome, ['reply_markup' => $kb]);
}

function performSearch(string $query): array {
	// For MVP, expect Spotify track id or link
	$spotifyId = parseSpotifyTrackId($query);
	if (!$spotifyId) return ['ok' => false, 'error' => 'parse_failed'];
	$res = songstatsRequest('tracks/info', ['spotify_track_id' => $spotifyId]);
	if (!$res || !isset($res['result'])) return ['ok' => false, 'error' => 'no_result'];
	return ['ok' => true, 'data' => $res['result']];
}

function handleSearch(array $update, array $botRow): void {
	global $BOT_TOKEN, $BOT_ID;
	$msg = $update['message'];
	$chatId = $msg['chat']['id'];
	$from = $msg['from'];
	$userId = (int)$from['id'];
	$lang = getUserLang($userId, $botRow['lang_default'] ?? 'en');
	$text = trim((string)($msg['text'] ?? ''));
	// Enforce public/private
	if (!(int)$botRow['public_enabled'] && !isBotAdmin($BOT_ID, $userId, (int)$botRow['owner_id'])) {
		tgSendMessage($BOT_TOKEN, $chatId, t($lang, 'private_only'));
		return;
	}
	if ($text === '') return;

	$search = performSearch($text);
	if (!$search['ok']) {
		tgSendMessage($BOT_TOKEN, $chatId, t($lang, 'no_results'));
		return;
	}
	$data = $search['data'];
	// songstats result normalization
	$track = [
		'title' => $data['title'] ?? ($data['name'] ?? ''),
		'artist' => $data['artist'] ?? ($data['artists'][0]['name'] ?? ''),
		'album' => $data['album'] ?? '',
		'cover' => $data['artwork'] ?? ($data['image'] ?? ''),
		'spotify' => ['url' => $data['spotify_url'] ?? ($data['urls']['spotify'] ?? '')],
		'id' => $data['spotify_id'] ?? ($data['id'] ?? ''),
	];
	$caption = formatTrackMessage($track);
	$buttons = [
		[
			['text' => '🎧 Save', 'callback_data' => 'save:' . $track['id']],
			['text' => '⬇️ MP3', 'callback_data' => 'dl:' . $track['id']],
		],
		[
			['text' => 'Spotify', 'url' => $track['spotify']['url'] ?: 'https://open.spotify.com/'],
		],
	];
	$replyMarkup = kbInline($buttons);
	bumpRankingSearch($track['id']);
	bumpStat($BOT_ID, 'total_queries', 1);
	if (!empty($track['cover'])) {
		tgSendPhoto($BOT_TOKEN, $chatId, $track['cover'], ['caption' => $caption, 'parse_mode' => 'HTML', 'reply_markup' => $replyMarkup]);
	} else {
		tgSendMessage($BOT_TOKEN, $chatId, $caption, ['reply_markup' => $replyMarkup]);
	}

	// Ads (if bot not VIP)
	if (!isVipActive($botRow)) {
		$ad = getRandomAd();
		if ($ad) {
			$kb = $ad['inline_keyboard'] ?: null;
			if ($ad['type'] === 'text') {
				tgSendMessage($BOT_TOKEN, $chatId, ($ad['content'] ?? '') . "\n\n" . t($lang, 'ad_postfix'), $kb ? ['reply_markup' => $kb] : []);
			} elseif ($ad['type'] === 'photo' && !empty($ad['content'])) {
				tgSendPhoto($BOT_TOKEN, $chatId, $ad['content'], ['caption' => t($lang, 'ad_postfix'), 'reply_markup' => $kb]);
			} elseif ($ad['type'] === 'video' && !empty($ad['content'])) {
				tgSendVideo($BOT_TOKEN, $chatId, $ad['content'], ['caption' => t($lang, 'ad_postfix'), 'reply_markup' => $kb]);
			}
		}
	}
}

function handleCallback(array $update, array $botRow): void {
	global $BOT_TOKEN, $BOT_ID;
	$cb = $update['callback_query'];
	$from = $cb['from'];
	$userId = (int)$from['id'];
	$lang = getUserLang($userId, $botRow['lang_default'] ?? 'en');
	$data = (string)($cb['data'] ?? '');
	$chatId = $cb['message']['chat']['id'];

	if (str_starts_with($data, 'save:')) {
		$trackId = substr($data, 5);
		$pdo = db();
		$pdo->prepare('INSERT INTO playlists (user_id, bot_id, track_id) VALUES (:u, :b, :t)')->execute([':u' => $userId, ':b' => $BOT_ID, ':t' => $trackId]);
		bumpRankingSave($trackId);
		bumpStat($BOT_ID, 'total_playlists', 1);
		tgAnswerCallbackQuery($BOT_TOKEN, $cb['id'], t($lang, 'saved'), false);
		return;
	}
	if (str_starts_with($data, 'dl:')) {
		$trackId = substr($data, 3);
		// For MVP, just send spotify link as placeholder
		$url = 'https://open.spotify.com/track/' . rawurlencode($trackId);
		tgAnswerCallbackQuery($BOT_TOKEN, $cb['id'], 'Opening download...', false);
		tgSendMessage($BOT_TOKEN, $chatId, 'Download: ' . $url);
		return;
	}
	if ($data === 'top10') {
		$pdo = db();
		$rows = $pdo->query('SELECT track_id, (search_count + save_count) AS score FROM rankings ORDER BY score DESC, last_update DESC LIMIT 10')->fetchAll();
		if (!$rows) {
			tgAnswerCallbackQuery($BOT_TOKEN, $cb['id'], t($lang, 'no_results'), false);
			return;
		}
		$out = [t($lang, 'top10') . ':'];
		foreach ($rows as $i => $r) { $out[] = ($i + 1) . '. ' . $r['track_id'] . ' (' . $r['score'] . ')'; }
		tgEditMessageText($BOT_TOKEN, $chatId, $cb['message']['message_id'], implode("\n", $out));
		return;
	}
}

// Basic admin controls via commands in user bot
function handleAdminCommand(array $update, array $botRow): bool {
	global $BOT_TOKEN, $BOT_ID;
	$msg = $update['message'] ?? null;
	if (!$msg) return false;
	$text = trim((string)($msg['text'] ?? ''));
	if ($text === '') return false;
	$chatId = $msg['chat']['id'];
	$userId = (int)$msg['from']['id'];

	$isOwner = ((int)$botRow['owner_id'] === $userId);
	$isAdmin = isBotAdmin($BOT_ID, $userId, (int)$botRow['owner_id']);
	if (!$isAdmin) return false;

	if (str_starts_with($text, '/setlang ')) {
		$lang = substr($text, 9, 2);
		$pdo = db();
		$pdo->prepare('UPDATE bots SET lang_default = :l WHERE id = :b')->execute([':l' => $lang, ':b' => $BOT_ID]);
		tgSendMessage($BOT_TOKEN, $chatId, 'Default language set: ' . $lang);
		return true;
	}
	if ($text === '/public on' || $text === '/public off') {
		$flag = $text === '/public on' ? 1 : 0;
		$pdo = db();
		$pdo->prepare('UPDATE bots SET public_enabled = :p WHERE id = :b')->execute([':p' => $flag, ':b' => $BOT_ID]);
		tgSendMessage($BOT_TOKEN, $chatId, 'Public: ' . ($flag ? 'on' : 'off'));
		return true;
	}
	if (str_starts_with($text, '/admins ')) {
		$sub = trim(substr($text, 8));
		$admins = getBotAdmins($BOT_ID);
		if ($sub === 'list') {
			$lst = $admins ? implode(',', $admins) : '-';
			tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'admins_list', ['list' => $lst]));
			return true;
		}
		if (str_starts_with($sub, 'add ')) {
			$aid = (int)trim(substr($sub, 4));
			$admins[] = $aid;
			setBotAdmins($BOT_ID, $admins);
			tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'admin_added', ['id' => $aid]));
			return true;
		}
		if (str_starts_with($sub, 'del ')) {
			$aid = (int)trim(substr($sub, 4));
			$admins = array_values(array_filter($admins, fn($x) => (int)$x !== $aid));
			setBotAdmins($BOT_ID, $admins);
			tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'admin_removed', ['id' => $aid]));
			return true;
		}
		return true;
	}
	if (str_starts_with($text, '/setwelcome ')) {
		$val = trim(substr($text, 12));
		setWelcomeText($BOT_ID, $val);
		tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'welcome_set'));
		return true;
	}
	if (str_starts_with($text, '/setlogo ')) {
		$val = trim(substr($text, 9));
		setLogoUrl($BOT_ID, $val);
		tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'logo_set'));
		return true;
	}
	if (str_starts_with($text, '/lang ')) {
		$code = substr($text, 6, 2);
		$pdo = db();
		$pdo->prepare('UPDATE bots SET lang_default = :l WHERE id = :b')->execute([':l' => $code, ':b' => $BOT_ID]);
		tgSendMessage($BOT_TOKEN, $chatId, t($code, 'lang_set', ['code' => $code]));
		return true;
	}
	if ($text === '/playlist') {
		$pdo = db();
		$stmt = $pdo->prepare('SELECT track_id FROM playlists WHERE user_id = :u AND bot_id = :b ORDER BY id DESC LIMIT 50');
		$stmt->execute([':u' => $userId, ':b' => $BOT_ID]);
		$list = $stmt->fetchAll();
		if (!$list) { tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'playlist_empty')); return true; }
		$lines = [t($botRow['lang_default'], 'playlist_header')];
		foreach ($list as $i => $r) { $lines[] = ($i+1) . '. ' . $r['track_id']; }
		tgSendMessage($BOT_TOKEN, $chatId, implode("\n", $lines));
		return true;
	}
	if ($text === '/playlist_clear') {
		$pdo = db();
		$pdo->prepare('DELETE FROM playlists WHERE user_id = :u AND bot_id = :b')->execute([':u' => $userId, ':b' => $BOT_ID]);
		tgSendMessage($BOT_TOKEN, $chatId, t($botRow['lang_default'], 'playlist_cleared'));
		return true;
	}
	return false;
}

if (isset($update['message'])) {
	$msg = $update['message'];
	if (isset($msg['text'])) {
		$text = trim((string)$msg['text']);
		if ($text === '/start') {
			handleStart($update, $botRow);
		} elseif (handleAdminCommand($update, $botRow)) {
			// handled
		} elseif (!isset($msg['entities'][0]['type']) || $msg['entities'][0]['type'] !== 'bot_command') {
			handleSearch($update, $botRow);
		}
	}
} elseif (isset($update['callback_query'])) {
	handleCallback($update, $botRow);
}

echo 'OK';

