<?php
declare(strict_types=1);

// Global configuration and helpers shared by master.php and bot.php

// ========= Basic Config =========
// Fill these values before deploying
const DB_HOST = '127.0.0.1';
const DB_NAME = 'telegram_music_builder';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// Base URL that Telegram will call for webhooks (no trailing slash)
// Example: https://your-domain.com
const BASE_WEBHOOK_URL = 'https://your-domain.com';

// Master bot token (Webhook will be: BASE_WEBHOOK_URL/master.php)
const MASTER_BOT_TOKEN = 'REPLACE_WITH_MASTER_BOT_TOKEN';

// Optional: Master admins (can manage everything in master bot)
const MASTER_ADMIN_IDS = [
	/* 123456789 */
];

// RapidAPI (Songstats)
const RAPIDAPI_KEY = 'REPLACE_WITH_RAPIDAPI_KEY';
const RAPIDAPI_HOST = 'songstats.p.rapidapi.com';

// Optional external downloader service for MP3 links (must support query + quality)
// Use placeholders {q} and {quality} for interpolation. If empty, audio downloads are disabled.
// Example: https://downloader.your-domain.com/mp3?query={q}&quality={quality}
const DOWNLOADER_BASE_URL = '';

// General
const DEBUG = true;
const SOURCE_VERSION = '1.0.0';

if (DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

// ========= Database =========

/** @return PDO */
function getPdo(): PDO {
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}
	$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
	return $pdo;
}

/** Ensure all tables exist. Safe to call on every request. */
function initDatabase(PDO $pdo): void {
	// Users interacting with master or user bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS users (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			username VARCHAR(64) NULL,
			lang VARCHAR(10) NULL,
			is_admin TINYINT DEFAULT 0,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_user_id (user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Registered user-created bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS bots (
			id INT AUTO_INCREMENT PRIMARY KEY,
			owner_id BIGINT NOT NULL,
			bot_token TEXT NOT NULL,
			is_vip TINYINT DEFAULT 0,
			vip_level VARCHAR(20) NULL,
			vip_expire DATETIME NULL,
			lang_default VARCHAR(10) DEFAULT 'fa',
			public_enabled TINYINT DEFAULT 1,
			is_active TINYINT DEFAULT 1,
			source_version VARCHAR(20) DEFAULT '1.0.0',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Best-effort migrate for older schemas (MySQL 8+)
	try { $pdo->exec("ALTER TABLE bots ADD COLUMN IF NOT EXISTS is_active TINYINT DEFAULT 1"); } catch (Throwable $e) { /* ignore */ }
	try { $pdo->exec("ALTER TABLE bots ADD COLUMN IF NOT EXISTS source_version VARCHAR(20) DEFAULT '1.0.0'"); } catch (Throwable $e) { /* ignore */ }

	// Separate table to store per-bot admin users
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS bot_admins (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			admin_user_id BIGINT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_bot_admin (bot_id, admin_user_id),
			INDEX idx_bot_admin_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Playlists saved by users per bot
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS playlists (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			bot_id INT NOT NULL,
			track_id VARCHAR(255) NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_playlist_user_bot (user_id, bot_id),
			INDEX idx_playlist_track (track_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Track rankings across all bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS rankings (
			id INT AUTO_INCREMENT PRIMARY KEY,
			track_id VARCHAR(255) NOT NULL,
			search_count INT DEFAULT 0,
			save_count INT DEFAULT 0,
			last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_track (track_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Banned users in master scope
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS banned_users (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			reason TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_banned_user (user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Advertisements to show after each music send
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS ads (
			id INT AUTO_INCREMENT PRIMARY KEY,
			type ENUM('text','photo','video','mixed') NOT NULL,
			content TEXT NOT NULL,
			inline_keyboard TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Bot presentation and customization
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS settings (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			welcome_text TEXT NULL,
			logo_url TEXT NULL,
			custom_keyboard TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_settings_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Aggregate stats per bot
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS stats (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			total_users INT DEFAULT 0,
			total_queries INT DEFAULT 0,
			total_playlists INT DEFAULT 0,
			last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_stats_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// VIP configuration (single row base price). Levels will use multipliers.
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS vip_config (
			id INT AUTO_INCREMENT PRIMARY KEY,
			price_local DECIMAL(10,2) DEFAULT 0.00,
			price_stars INT DEFAULT 0,
			is_open TINYINT DEFAULT 1,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Ensure one vip_config row exists
	$stmt = $pdo->query('SELECT COUNT(*) AS c FROM vip_config');
	$row = $stmt->fetch();
	if ((int)$row['c'] === 0) {
		$pdo->prepare('INSERT INTO vip_config (price_local, price_stars, is_open) VALUES (?,?,?)')
			->execute([0.00, 0, 1]);
	}
}

// ========= Telegram Bot API Helpers =========

function tgApiRequest(string $botToken, string $method, array $params = []): array {
	$url = 'https://api.telegram.org/bot' . urlencode($botToken) . '/' . $method;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
	]);
	$raw = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	curl_close($ch);
	if ($errno) {
		return ['ok' => false, 'error' => $error, 'errno' => $errno, 'result' => null];
	}
	$decoded = json_decode((string)$raw, true);
	return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'Bad JSON', 'result' => null];
}

function tgSendMessage(string $token, int|string $chatId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'text' => $text,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendMessage', $params);
}

function tgSendPhoto(string $token, int|string $chatId, string $photo, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'photo' => $photo,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendPhoto', $params);
}

function tgSendAudio(string $token, int|string $chatId, string $audioUrl, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'audio' => $audioUrl,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendAudio', $params);
}

function tgAnswerCallback(string $token, string $callbackId, string $text = '', bool $alert = false): array {
	$params = [
		'callback_query_id' => $callbackId,
	];
	if ($text !== '') {
		$params['text'] = $text;
		$params['show_alert'] = $alert ? 'true' : 'false';
	}
	return tgApiRequest($token, 'answerCallbackQuery', $params);
}

function tgEditMessageText(string $token, int|string $chatId, int $messageId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'message_id' => $messageId,
		'text' => $text,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'editMessageText', $params);
}

function tgSetWebhook(string $token, string $url): array {
	return tgApiRequest($token, 'setWebhook', ['url' => $url]);
}

function tgSendInvoice(string $token, int|string $chatId, array $invoice): array {
	// Expected keys: title, description, payload, currency (use 'XTR' for Stars), prices (array of ['label','amount'])
	$params = $invoice;
	$params['chat_id'] = $chatId;
	if (isset($params['prices']) && is_array($params['prices'])) {
		$params['prices'] = json_encode($params['prices']);
	}
	return tgApiRequest($token, 'sendInvoice', $params);
}

// ========= Songstats API Client =========

class SongstatsClient {
	private string $apiKey;
	public function __construct(string $apiKey) {
		$this->apiKey = $apiKey;
	}

	/** Basic search by query. Returns a simplified list. */
	public function searchTracks(string $query): array {
		$endpoint = 'https://' . RAPIDAPI_HOST . '/tracks/search';
		$params = ['q' => $query];
		$response = $this->curlGet($endpoint, $params);
		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'] ?? 'Request failed', 'results' => []];
		}
		$data = $response['data'] ?? [];
		$items = $data['tracks'] ?? $data['data']['tracks'] ?? [];
		$results = [];
		foreach ($items as $it) {
			$results[] = [
				'id' => $it['id'] ?? ($it['songstats_track_id'] ?? ''),
				'name' => $it['name'] ?? ($it['title'] ?? ''),
				'artist' => $it['artist_name'] ?? ($it['artists'][0]['name'] ?? ''),
				'cover' => $it['image_url'] ?? ($it['cover'] ?? ''),
				'spotify_url' => $it['spotify']['external_urls']['spotify'] ?? ($it['spotify_url'] ?? ''),
				'spotify_track_id' => $it['spotify']['id'] ?? ($it['spotify_track_id'] ?? ''),
				'isrc' => $it['isrc'] ?? '',
			];
		}
		return ['ok' => true, 'results' => $results];
	}

	/** Fetch track info by known ids. */
	public function getTrackInfo(?string $spotifyTrackId = null, ?string $songstatsTrackId = null, ?string $isrc = null): array {
		$endpoint = 'https://' . RAPIDAPI_HOST . '/tracks/info';
		$params = [];
		if ($spotifyTrackId) $params['spotify_track_id'] = $spotifyTrackId;
		if ($songstatsTrackId) $params['songstats_track_id'] = $songstatsTrackId;
		if ($isrc) $params['isrc'] = $isrc;
		$response = $this->curlGet($endpoint, $params);
		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'] ?? 'Request failed'];
		}
		return ['ok' => true, 'data' => $response['data'] ?? []];
	}

	private function curlGet(string $url, array $params): array {
		$finalUrl = $url;
		if (!empty($params)) {
			$finalUrl .= '?' . http_build_query($params);
		}
		$ch = curl_init($finalUrl);
		$headers = [
			'x-rapidapi-host: ' . RAPIDAPI_HOST,
			'x-rapidapi-key: ' . $this->apiKey,
		];
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 20,
		]);
		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($errno) {
			return ['ok' => false, 'error' => $error, 'code' => $code];
		}
		$data = json_decode((string)$raw, true);
		return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $data];
	}
}

// ========= i18n =========

/** Minimal phrasebook. Extend as needed. */
function getPhrases(): array {
	return [
		'fa' => [
			'welcome_master' => "به ربات ساز موزیک خوش آمدید!\n/newbot <token> را بفرستید تا ربات خود را بسازید.",
			'welcome_userbot' => "سلام! نام آهنگ یا هنرمند را ارسال کنید تا جستجو کنم.",
			'choose_language' => 'زبان خود را انتخاب کنید:',
			'playlist_added' => 'به پلی‌لیست شما اضافه شد!',
			'no_results' => 'نتیجه‌ای یافت نشد.',
			'not_authorized' => 'دسترسی لازم را ندارید.',
			'vip_closed' => 'خرید VIP در حال حاضر بسته است.',
			'vip_bought' => 'VIP با موفقیت فعال شد.',
			'ads_soon' => 'تبلیغی برای نمایش موجود نیست.',
			'feature_vip_only' => 'این بخش مخصوص VIP است.',
			'public_on' => 'ربات عمومی شد.',
			'public_off' => 'ربات خصوصی شد.',
			'admins_list' => 'ادمین‌ها:',
			'stats' => 'آمار: کاربران {u} | جستجو {q} | پلی‌لیست {p}',
		],
		'en' => [
			'welcome_master' => "Welcome to the Music Bot Builder!\nSend /newbot <token> to register your bot.",
			'welcome_userbot' => 'Send a song or artist name to search.',
			'choose_language' => 'Choose your language:',
			'playlist_added' => 'Added to your playlist!',
			'no_results' => 'No results found.',
			'not_authorized' => 'You are not authorized.',
			'vip_closed' => 'VIP purchase is currently closed.',
			'vip_bought' => 'VIP activated successfully.',
			'ads_soon' => 'No ad to show right now.',
			'feature_vip_only' => 'This feature requires VIP.',
			'public_on' => 'Bot is now public.',
			'public_off' => 'Bot is now private.',
			'admins_list' => 'Admins:',
			'stats' => 'Stats: users {u} | searches {q} | playlists {p}',
		],
		'ar' => ['welcome_master' => 'أهلًا بك! أرسل /newbot <token>.', 'welcome_userbot' => 'أرسل اسم أغنية أو فنان.','choose_language'=>'اختر لغتك:','playlist_added'=>'تمت الإضافة إلى قائمتك!','no_results'=>'لا توجد نتائج.','not_authorized'=>'غير مصرح لك.','vip_closed'=>'الشراء متوقف.','vip_bought'=>'تم تفعيل VIP.','ads_soon'=>'لا يوجد إعلان.','feature_vip_only'=>'هذه الميزة لـ VIP.','public_on'=>'عام الآن.','public_off'=>'خاص الآن.','admins_list'=>'المدراء:','stats'=>'الإحصاءات: مستخدمون {u} | بحث {q} | قوائم {p}'],
		'tr' => ['welcome_master' => 'Hoş geldiniz! /newbot <token> gönderin.','welcome_userbot'=>'Şarkı veya sanatçı adı gönderin.','choose_language'=>'Dil seçin:','playlist_added'=>'Çalma listenize eklendi!','no_results'=>'Sonuç yok.','not_authorized'=>'Yetkiniz yok.','vip_closed'=>'VIP kapalı.','vip_bought'=>'VIP etkin.','ads_soon'=>'Reklam yok.','feature_vip_only'=>'VIP gerekir.','public_on'=>'Artık herkese açık.','public_off'=>'Artık özel.','admins_list'=>'Yöneticiler:','stats'=>'İstatistikler: kullanıcı {u} | arama {q} | liste {p}'],
		'ru' => ['welcome_master' => 'Добро пожаловать! Отправьте /newbot <token>.','welcome_userbot'=>'Отправьте название песни или артиста.','choose_language'=>'Выберите язык:','playlist_added'=>'Добавлено в плейлист!','no_results'=>'Ничего не найдено.','not_authorized'=>'Нет доступа.','vip_closed'=>'VIP закрыт.','vip_bought'=>'VIP активирован.','ads_soon'=>'Нет рекламы.','feature_vip_only'=>'Требуется VIP.','public_on'=>'Теперь публичный.','public_off'=>'Теперь приватный.','admins_list'=>'Админы:','stats'=>'Статистика: пользователи {u} | поиски {q} | плейлисты {p}'],
		'es' => ['welcome_master' => '¡Bienvenido! Envía /newbot <token>.','welcome_userbot'=>'Envía nombre de canción o artista.','choose_language'=>'Elige tu idioma:','playlist_added'=>'Añadido a tu playlist.','no_results'=>'Sin resultados.','not_authorized'=>'No autorizado.','vip_closed'=>'VIP cerrado.','vip_bought'=>'VIP activado.','ads_soon'=>'Sin anuncios.','feature_vip_only'=>'Requiere VIP.','public_on'=>'Ahora público.','public_off'=>'Ahora privado.','admins_list'=>'Admins:','stats'=>'Estadísticas: usuarios {u} | búsquedas {q} | listas {p}'],
		'de' => ['welcome_master' => 'Willkommen! Sende /newbot <token>.','welcome_userbot'=>'Sende Song- oder Künstlername.','choose_language'=>'Sprache wählen:','playlist_added'=>'Zur Playlist hinzugefügt!','no_results'=>'Keine Ergebnisse.','not_authorized'=>'Nicht autorisiert.','vip_closed'=>'VIP geschlossen.','vip_bought'=>'VIP aktiviert.','ads_soon'=>'Keine Werbung.','feature_vip_only'=>'VIP erforderlich.','public_on'=>'Öffentlich.','public_off'=>'Privat.','admins_list'=>'Admins:','stats'=>'Statistiken: Nutzer {u} | Suchen {q} | Playlists {p}'],
		'fr' => ['welcome_master' => 'Bienvenue ! Envoie /newbot <token>.','welcome_userbot'=>'Envoie un titre ou artiste.','choose_language'=>'Choisis ta langue:','playlist_added'=>'Ajouté à ta playlist !','no_results'=>'Aucun résultat.','not_authorized'=>'Non autorisé.','vip_closed'=>'VIP fermé.','vip_bought'=>'VIP activé.','ads_soon'=>'Pas de pub.','feature_vip_only'=>'VIP requis.','public_on'=>'Public.','public_off'=>'Privé.','admins_list'=>'Admins:','stats'=>'Stats: utilisateurs {u} | recherches {q} | playlists {p}'],
		'it' => ['welcome_master' => 'Benvenuto! Invia /newbot <token>.','welcome_userbot'=>'Invia nome brano o artista.','choose_language'=>'Scegli la lingua:','playlist_added'=>'Aggiunto alla playlist!','no_results'=>'Nessun risultato.','not_authorized'=>'Non autorizzato.','vip_closed'=>'VIP chiuso.','vip_bought'=>'VIP attivato.','ads_soon'=>'Nessuna pubblicità.','feature_vip_only'=>'Richiede VIP.','public_on'=>'Pubblico.','public_off'=>'Privato.','admins_list'=>'Admin:','stats'=>'Statistiche: utenti {u} | ricerche {q} | playlist {p}'],
		'pt' => ['welcome_master' => 'Bem-vindo! Envie /newbot <token>.','welcome_userbot':'Envie nome da música ou artista.','choose_language':'Escolha o idioma:','playlist_added':'Adicionado à playlist!','no_results':'Sem resultados.','not_authorized':'Não autorizado.','vip_closed':'VIP fechado.','vip_bought':'VIP ativado.','ads_soon':'Sem anúncios.','feature_vip_only':'Requer VIP.','public_on':'Público.','public_off':'Privado.','admins_list':'Admins:','stats':'Estatísticas: usuários {u} | buscas {q} | playlists {p}'],
		'hi' => ['welcome_master' => 'स्वागत है! /newbot <token> भेजें.','welcome_userbot':'गाना या कलाकार नाम भेजें.','choose_language':'भाषा चुनें:','playlist_added':'प्लेलिस्ट में जोड़ा गया!','no_results':'कोई परिणाम नहीं.','not_authorized':'अनुमति नहीं.','vip_closed':'VIP बंद है.','vip_bought':'VIP सक्रिय.','ads_soon':'कोई विज्ञापन नहीं.','feature_vip_only':'VIP आवश्यक.','public_on':'अब सार्वजनिक.','public_off':'अब निजी.','admins_list':'एडमिन:','stats':'आँकड़े: उपयोगकर्ता {u} | खोज {q} | प्लेलिस्ट {p}'],
		'ur' => ['welcome_master' => 'خوش آمدید! /newbot <token> بھیجیں.','welcome_userbot':'گانے یا فنکار کا نام بھیجیں.','choose_language':'زبان منتخب کریں:','playlist_added':'پلے لسٹ میں شامل!','no_results':'کوئی نتیجہ نہیں.','not_authorized':'اجازت نہیں.','vip_closed':'VIP بند ہے.','vip_bought':'VIP فعال.','ads_soon':'کوئی اشتہار نہیں.','feature_vip_only':'VIP درکار.','public_on':'اب عوامی.','public_off':'اب نجی.','admins_list':'ایڈمنز:','stats':'اعداد: صارفین {u} | تلاش {q} | فہرست {p}'],
		'id' => ['welcome_master' => 'Selamat datang! Kirim /newbot <token>.','welcome_userbot':'Kirim nama lagu/artist.','choose_language':'Pilih bahasa:','playlist_added':'Ditambahkan ke playlist!','no_results':'Tidak ada hasil.','not_authorized':'Tidak diizinkan.','vip_closed':'VIP ditutup.','vip_bought':'VIP aktif.','ads_soon':'Tidak ada iklan.','feature_vip_only':'Memerlukan VIP.','public_on':'Publik.','public_off':'Privat.','admins_list':'Admin:','stats':'Statistik: pengguna {u} | cari {q} | playlist {p}'],
		'zh' => ['welcome_master' => '欢迎！发送 /newbot <token>.','welcome_userbot':'发送歌曲或歌手名称.','choose_language':'选择语言:','playlist_added':'已加入播放列表!','no_results':'未找到结果.','not_authorized':'未授权.','vip_closed':'VIP 关闭.','vip_bought':'VIP 已激活.','ads_soon':'暂无广告.','feature_vip_only':'需 VIP.','public_on':'公开.','public_off':'私密.','admins_list':'管理员:','stats':'统计: 用户 {u} | 搜索 {q} | 列表 {p}'],
		'ja' => ['welcome_master' => 'ようこそ！/newbot <token> を送信。','welcome_userbot':'曲名またはアーティスト名を送ってください。','choose_language':'言語を選択:','playlist_added':'プレイリストに追加しました！','no_results':'結果が見つかりません。','not_authorized':'権限がありません。','vip_closed':'VIPは現在停止中。','vip_bought':'VIPが有効化されました。','ads_soon':'広告はありません。','feature_vip_only':'VIPが必要です。','public_on':'公開になりました。','public_off':'非公開になりました。','admins_list':'管理者:','stats':'統計: ユーザー {u} | 検索 {q} | プレイリスト {p}'],
	];
}

/** Translate a key for a given language with optional placeholders */
function t(string $key, string $lang, array $vars = []): string {
	$phr = getPhrases();
	$dict = $phr[$lang] ?? $phr['en'];
	$text = $dict[$key] ?? ($phr['en'][$key] ?? $key);
	foreach ($vars as $k => $v) {
		$text = str_replace('{' . $k . '}', (string)$v, $text);
	}
	return $text;
}

// ========= Utilities =========

function ensureUser(PDO $pdo, int $userId, ?string $username, ?string $lang = null): void {
	$stmt = $pdo->prepare('INSERT INTO users (user_id, username, lang) VALUES (?,?,?) ON DUPLICATE KEY UPDATE username = VALUES(username)');
	$stmt->execute([$userId, $username, $lang]);
}

function ensureStatsRow(PDO $pdo, int $botId): void {
	$stmt = $pdo->prepare('INSERT IGNORE INTO stats (bot_id) VALUES (?)');
	$stmt->execute([$botId]);
}

function incrementStat(PDO $pdo, int $botId, string $column, int $amount = 1): void {
	ensureStatsRow($pdo, $botId);
	$allowed = ['total_users','total_queries','total_playlists'];
	if (!in_array($column, $allowed, true)) return;
	$pdo->prepare("UPDATE stats SET $column = $column + ?, last_update = NOW() WHERE bot_id = ?")
		->execute([$amount, $botId]);
}

function recordRanking(PDO $pdo, string $trackId, int $searchInc = 0, int $saveInc = 0): void {
	$pdo->prepare('INSERT INTO rankings (track_id, search_count, save_count) VALUES (?,?,?) ON DUPLICATE KEY UPDATE search_count = search_count + VALUES(search_count), save_count = save_count + VALUES(save_count), last_update = NOW()')
		->execute([$trackId, $searchInc, $saveInc]);
}

function fetchRandomAd(PDO $pdo): ?array {
	$stmt = $pdo->query('SELECT * FROM ads ORDER BY RAND() LIMIT 1');
	$ad = $stmt->fetch();
	return $ad ?: null;
}

function sendAd(string $token, int|string $chatId, ?array $ad): void {
	if (!$ad) return;
	$kb = [];
	if (!empty($ad['inline_keyboard'])) {
		$json = json_decode((string)$ad['inline_keyboard'], true);
		if (is_array($json)) { $kb = $json; }
	}
	$replyMarkup = $kb ? ['reply_markup' => json_encode(['inline_keyboard' => $kb])] : [];
	switch ($ad['type']) {
		case 'photo':
			tgSendPhoto($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'video':
			// Fallback to sendMessage with link
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'mixed':
			// Expect content to be text with a URL; keep simple here
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'text':
		default:
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
	}
}

function getUserLang(PDO $pdo, int $userId, ?string $fallback = 'fa'): string {
	$stmt = $pdo->prepare('SELECT lang FROM users WHERE user_id = ?');
	$stmt->execute([$userId]);
	$row = $stmt->fetch();
	$lang = $row['lang'] ?? null;
	return $lang ?: ($fallback ?: 'en');
}

function setUserLang(PDO $pdo, int $userId, string $lang): void {
	$pdo->prepare('UPDATE users SET lang = ? WHERE user_id = ?')->execute([$lang, $userId]);
}

function isMasterAdmin(int $userId): bool {
	return in_array($userId, MASTER_ADMIN_IDS, true);
}

function isBotVip(array $botRow): array {
	$level = $botRow['vip_level'] ?? null;
	$isVip = (int)($botRow['is_vip'] ?? 0) === 1;
	$expire = $botRow['vip_expire'] ?? null;
	if ($expire) {
		try {
			$expTime = strtotime((string)$expire);
			if ($expTime !== false && $expTime < time()) {
				$isVip = false; $level = null;
			}
		} catch (Throwable $e) { /* ignore */ }
	}
	return ['is_vip' => $isVip, 'level' => $level];
}

function getVipMultipliers(): array {
	return [
		'Basic' => 1.0,
		'Premium' => 1.5,
		'Pro' => 2.0,
	];
}

function computeVipPrices(PDO $pdo, string $level): array {
	$mult = getVipMultipliers()[$level] ?? 1.0;
	$row = $pdo->query('SELECT price_local, price_stars FROM vip_config ORDER BY id ASC LIMIT 1')->fetch();
	$baseLocal = isset($row['price_local']) ? (float)$row['price_local'] : 0.0;
	$baseStars = isset($row['price_stars']) ? (int)$row['price_stars'] : 0;
	return [
		'price_local' => (float)round($baseLocal * $mult, 2),
		'price_stars' => (int)ceil($baseStars * $mult),
	];
}

function isVipOpen(PDO $pdo): bool {
	$row = $pdo->query('SELECT is_open FROM vip_config ORDER BY id ASC LIMIT 1')->fetch();
	return (int)($row['is_open'] ?? 1) === 1;
}

function buildInlineKeyboard(array $buttonsRows): string {
	return json_encode(['inline_keyboard' => $buttonsRows], JSON_UNESCAPED_UNICODE);
}

function sendLanguagePicker(string $token, int|string $chatId, string $lang = 'fa'): void {
	$langs = ['fa','en','ar','tr','ru','es','de','fr','it','pt','hi','ur','id','zh','ja'];
	$rows = [];
	$chunk = [];
	foreach ($langs as $code) {
		$chunk[] = ['text' => strtoupper($code), 'callback_data' => 'lang:' . $code];
		if (count($chunk) === 5) { $rows[] = $chunk; $chunk = []; }
	}
	if ($chunk) $rows[] = $chunk;
	$kb = buildInlineKeyboard($rows);
	tgSendMessage($token, $chatId, t('choose_language', $lang), ['reply_markup' => $kb]);
}

function resolveDownloaderUrl(string $query, string $quality): ?string {
	if (DOWNLOADER_BASE_URL === '') return null;
	$u = str_replace(['{q}','{quality}'], [urlencode($query), urlencode($quality)], DOWNLOADER_BASE_URL);
	return $u;
}

function getBotRowByToken(PDO $pdo, string $token): ?array {
	$stmt = $pdo->prepare('SELECT * FROM bots WHERE bot_token = ?');
	$stmt->execute([$token]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function isUserBotAdmin(PDO $pdo, int $botId, int $userId): bool {
	// owner is admin
	$stmt = $pdo->prepare('SELECT owner_id FROM bots WHERE id = ?');
	$stmt->execute([$botId]);
	$ownerId = (int)($stmt->fetch()['owner_id'] ?? 0);
	if ($ownerId === $userId) return true;
	// extra admins
	$stmt = $pdo->prepare('SELECT 1 FROM bot_admins WHERE bot_id = ? AND admin_user_id = ?');
	$stmt->execute([$botId, $userId]);
	return (bool)$stmt->fetch();
}

function upsertSetting(PDO $pdo, int $botId, array $fields): void {
	ensureStatsRow($pdo, $botId);
	$stmt = $pdo->prepare('SELECT id FROM settings WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	if ($row) {
		// Update only provided fields
		$setParts = [];
		$params = [];
		foreach ($fields as $k => $v) { $setParts[] = "$k = ?"; $params[] = $v; }
		$params[] = $botId;
		$pdo->prepare('UPDATE settings SET ' . implode(', ', $setParts) . ' WHERE bot_id = ?')->execute($params);
	} else {
		$columns = array_merge(['bot_id'], array_keys($fields));
		$placeholders = array_fill(0, count($columns), '?');
		$params = array_merge([$botId], array_values($fields));
		$pdo->prepare('INSERT INTO settings (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')')->execute($params);
	}
}

function getSettings(PDO $pdo, int $botId): array {
	$stmt = $pdo->prepare('SELECT * FROM settings WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	return $row ?: [];
}

function getStats(PDO $pdo, int $botId): array {
	$stmt = $pdo->prepare('SELECT * FROM stats WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	return $row ?: ['total_users'=>0,'total_queries'=>0,'total_playlists'=>0];
}

function readUpdate(): array {
	$raw = file_get_contents('php://input');
	$data = json_decode((string)$raw, true);
	return is_array($data) ? $data : [];
}

function replyMarkupInline(array $rows): array {
	return ['reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function cleanText(string $text): string {
	return trim(preg_replace('/\s+/', ' ', $text));
}

function getCommandAndArgs(?string $text): array {
	$text = $text ?? '';
	if ($text === '') return ['', ''];
	$parts = explode(' ', trim($text), 2);
	$cmd = strtolower($parts[0]);
	$args = $parts[1] ?? '';
	return [$cmd, $args];
}

function ensureBotAndUser(PDO $pdo, array $botRow, int $userId, ?string $username): void {
	ensureUser($pdo, $userId, $username, null);
	ensureStatsRow($pdo, (int)$botRow['id']);
	// increment users once per unique user-bot pair on first interaction
	$stmt = $pdo->prepare('SELECT 1 FROM playlists WHERE user_id = ? AND bot_id = ? LIMIT 1');
	$stmt->execute([$userId, (int)$botRow['id']]);
	// Use a small heuristic: if user has no playlist row yet, count as potential new user
	if (!$stmt->fetch()) {
		incrementStat($pdo, (int)$botRow['id'], 'total_users', 1);
	}
}

?>

<?php
declare(strict_types=1);

// Global configuration and helpers shared by master.php and bot.php

// ========= Basic Config =========
// Fill these values before deploying
const DB_HOST = '127.0.0.1';
const DB_NAME = 'telegram_music_builder';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// Base URL that Telegram will call for webhooks (no trailing slash)
// Example: https://your-domain.com
const BASE_WEBHOOK_URL = 'https://your-domain.com';

// Master bot token (Webhook will be: BASE_WEBHOOK_URL/master.php)
const MASTER_BOT_TOKEN = 'REPLACE_WITH_MASTER_BOT_TOKEN';

// Optional: Master admins (can manage everything in master bot)
const MASTER_ADMIN_IDS = [
	/* 123456789 */
];

// RapidAPI (Songstats)
const RAPIDAPI_KEY = 'REPLACE_WITH_RAPIDAPI_KEY';
const RAPIDAPI_HOST = 'songstats.p.rapidapi.com';

// Optional external downloader service for MP3 links (must support query + quality)
// Use placeholders {q} and {quality} for interpolation. If empty, audio downloads are disabled.
// Example: https://downloader.your-domain.com/mp3?query={q}&quality={quality}
const DOWNLOADER_BASE_URL = '';

// General
const DEBUG = true;
const SOURCE_VERSION = '1.0.0';

if (DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

// ========= Database =========

/** @return PDO */
function getPdo(): PDO {
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}
	$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
	return $pdo;
}

/** Ensure all tables exist. Safe to call on every request. */
function initDatabase(PDO $pdo): void {
	// Users interacting with master or user bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS users (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			username VARCHAR(64) NULL,
			lang VARCHAR(10) NULL,
			is_admin TINYINT DEFAULT 0,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_user_id (user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Registered user-created bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS bots (
			id INT AUTO_INCREMENT PRIMARY KEY,
			owner_id BIGINT NOT NULL,
			bot_token TEXT NOT NULL,
			is_vip TINYINT DEFAULT 0,
			vip_level VARCHAR(20) NULL,
			vip_expire DATETIME NULL,
			lang_default VARCHAR(10) DEFAULT 'fa',
			public_enabled TINYINT DEFAULT 1,
			is_active TINYINT DEFAULT 1,
			source_version VARCHAR(20) DEFAULT '1.0.0',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Best-effort migrate for older schemas (MySQL 8+)
	try { $pdo->exec("ALTER TABLE bots ADD COLUMN IF NOT EXISTS is_active TINYINT DEFAULT 1"); } catch (Throwable $e) { /* ignore */ }
	try { $pdo->exec("ALTER TABLE bots ADD COLUMN IF NOT EXISTS source_version VARCHAR(20) DEFAULT '1.0.0'"); } catch (Throwable $e) { /* ignore */ }

	// Separate table to store per-bot admin users
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS bot_admins (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			admin_user_id BIGINT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_bot_admin (bot_id, admin_user_id),
			INDEX idx_bot_admin_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Playlists saved by users per bot
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS playlists (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			bot_id INT NOT NULL,
			track_id VARCHAR(255) NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_playlist_user_bot (user_id, bot_id),
			INDEX idx_playlist_track (track_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Track rankings across all bots
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS rankings (
			id INT AUTO_INCREMENT PRIMARY KEY,
			track_id VARCHAR(255) NOT NULL,
			search_count INT DEFAULT 0,
			save_count INT DEFAULT 0,
			last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_track (track_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Banned users in master scope
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS banned_users (
			id INT AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT NOT NULL,
			reason TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_banned_user (user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Advertisements to show after each music send
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS ads (
			id INT AUTO_INCREMENT PRIMARY KEY,
			type ENUM('text','photo','video','mixed') NOT NULL,
			content TEXT NOT NULL,
			inline_keyboard TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Bot presentation and customization
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS settings (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			welcome_text TEXT NULL,
			logo_url TEXT NULL,
			custom_keyboard TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_settings_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Aggregate stats per bot
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS stats (
			id INT AUTO_INCREMENT PRIMARY KEY,
			bot_id INT NOT NULL,
			total_users INT DEFAULT 0,
			total_queries INT DEFAULT 0,
			total_playlists INT DEFAULT 0,
			last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_stats_bot (bot_id),
			FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// VIP configuration (single row base price). Levels will use multipliers.
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS vip_config (
			id INT AUTO_INCREMENT PRIMARY KEY,
			price_local DECIMAL(10,2) DEFAULT 0.00,
			price_stars INT DEFAULT 0,
			is_open TINYINT DEFAULT 1,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
	);

	// Ensure one vip_config row exists
	$stmt = $pdo->query('SELECT COUNT(*) AS c FROM vip_config');
	$row = $stmt->fetch();
	if ((int)$row['c'] === 0) {
		$pdo->prepare('INSERT INTO vip_config (price_local, price_stars, is_open) VALUES (?,?,?)')
			->execute([0.00, 0, 1]);
	}
}

// ========= Telegram Bot API Helpers =========

function tgApiRequest(string $botToken, string $method, array $params = []): array {
	$url = 'https://api.telegram.org/bot' . urlencode($botToken) . '/' . $method;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
	]);
	$raw = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	curl_close($ch);
	if ($errno) {
		return ['ok' => false, 'error' => $error, 'errno' => $errno, 'result' => null];
	}
	$decoded = json_decode((string)$raw, true);
	return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'Bad JSON', 'result' => null];
}

function tgSendMessage(string $token, int|string $chatId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'text' => $text,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendMessage', $params);
}

function tgSendPhoto(string $token, int|string $chatId, string $photo, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'photo' => $photo,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendPhoto', $params);
}

function tgSendAudio(string $token, int|string $chatId, string $audioUrl, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'audio' => $audioUrl,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'sendAudio', $params);
}

function tgAnswerCallback(string $token, string $callbackId, string $text = '', bool $alert = false): array {
	$params = [
		'callback_query_id' => $callbackId,
	];
	if ($text !== '') {
		$params['text'] = $text;
		$params['show_alert'] = $alert ? 'true' : 'false';
	}
	return tgApiRequest($token, 'answerCallbackQuery', $params);
}

function tgEditMessageText(string $token, int|string $chatId, int $messageId, string $text, array $opts = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'message_id' => $messageId,
		'text' => $text,
		'parse_mode' => 'HTML',
	], $opts);
	return tgApiRequest($token, 'editMessageText', $params);
}

function tgSetWebhook(string $token, string $url): array {
	return tgApiRequest($token, 'setWebhook', ['url' => $url]);
}

// ========= Songstats API Client =========

class SongstatsClient {
	private string $apiKey;
	public function __construct(string $apiKey) {
		$this->apiKey = $apiKey;
	}

	/** Basic search by query. Returns a simplified list. */
	public function searchTracks(string $query): array {
		$endpoint = 'https://' . RAPIDAPI_HOST . '/tracks/search';
		$params = ['q' => $query];
		$response = $this->curlGet($endpoint, $params);
		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'] ?? 'Request failed', 'results' => []];
		}
		$data = $response['data'] ?? [];
		$items = $data['tracks'] ?? $data['data']['tracks'] ?? [];
		$results = [];
		foreach ($items as $it) {
			$results[] = [
				'id' => $it['id'] ?? ($it['songstats_track_id'] ?? ''),
				'name' => $it['name'] ?? ($it['title'] ?? ''),
				'artist' => $it['artist_name'] ?? ($it['artists'][0]['name'] ?? ''),
				'cover' => $it['image_url'] ?? ($it['cover'] ?? ''),
				'spotify_url' => $it['spotify']['external_urls']['spotify'] ?? ($it['spotify_url'] ?? ''),
				'spotify_track_id' => $it['spotify']['id'] ?? ($it['spotify_track_id'] ?? ''),
				'isrc' => $it['isrc'] ?? '',
			];
		}
		return ['ok' => true, 'results' => $results];
	}

	/** Fetch track info by known ids. */
	public function getTrackInfo(?string $spotifyTrackId = null, ?string $songstatsTrackId = null, ?string $isrc = null): array {
		$endpoint = 'https://' . RAPIDAPI_HOST . '/tracks/info';
		$params = [];
		if ($spotifyTrackId) $params['spotify_track_id'] = $spotifyTrackId;
		if ($songstatsTrackId) $params['songstats_track_id'] = $songstatsTrackId;
		if ($isrc) $params['isrc'] = $isrc;
		$response = $this->curlGet($endpoint, $params);
		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'] ?? 'Request failed'];
		}
		return ['ok' => true, 'data' => $response['data'] ?? []];
	}

	private function curlGet(string $url, array $params): array {
		$finalUrl = $url;
		if (!empty($params)) {
			$finalUrl .= '?' . http_build_query($params);
		}
		$ch = curl_init($finalUrl);
		$headers = [
			'x-rapidapi-host: ' . RAPIDAPI_HOST,
			'x-rapidapi-key: ' . $this->apiKey,
		];
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 20,
		]);
		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($errno) {
			return ['ok' => false, 'error' => $error, 'code' => $code];
		}
		$data = json_decode((string)$raw, true);
		return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $data];
	}
}

// ========= i18n =========

/** Minimal phrasebook. Extend as needed. */
function getPhrases(): array {
	return [
		'fa' => [
			'welcome_master' => "به ربات ساز موزیک خوش آمدید!\n/newbot <token> را بفرستید تا ربات خود را بسازید.",
			'welcome_userbot' => "سلام! نام آهنگ یا هنرمند را ارسال کنید تا جستجو کنم.",
			'choose_language' => 'زبان خود را انتخاب کنید:',
			'playlist_added' => 'به پلی‌لیست شما اضافه شد!',
			'no_results' => 'نتیجه‌ای یافت نشد.',
			'not_authorized' => 'دسترسی لازم را ندارید.',
			'vip_closed' => 'خرید VIP در حال حاضر بسته است.',
			'vip_bought' => 'VIP با موفقیت فعال شد.',
			'ads_soon' => 'تبلیغی برای نمایش موجود نیست.',
			'feature_vip_only' => 'این بخش مخصوص VIP است.',
			'public_on' => 'ربات عمومی شد.',
			'public_off' => 'ربات خصوصی شد.',
			'admins_list' => 'ادمین‌ها:',
			'stats' => 'آمار: کاربران {u} | جستجو {q} | پلی‌لیست {p}',
		],
		'en' => [
			'welcome_master' => "Welcome to the Music Bot Builder!\nSend /newbot <token> to register your bot.",
			'welcome_userbot' => 'Send a song or artist name to search.',
			'choose_language' => 'Choose your language:',
			'playlist_added' => 'Added to your playlist!',
			'no_results' => 'No results found.',
			'not_authorized' => 'You are not authorized.',
			'vip_closed' => 'VIP purchase is currently closed.',
			'vip_bought' => 'VIP activated successfully.',
			'ads_soon' => 'No ad to show right now.',
			'feature_vip_only' => 'This feature requires VIP.',
			'public_on' => 'Bot is now public.',
			'public_off' => 'Bot is now private.',
			'admins_list' => 'Admins:',
			'stats' => 'Stats: users {u} | searches {q} | playlists {p}',
		],
		'ar' => ['welcome_master' => 'أهلًا بك! أرسل /newbot <token>.', 'welcome_userbot' => 'أرسل اسم أغنية أو فنان.','choose_language'=>'اختر لغتك:','playlist_added'=>'تمت الإضافة إلى قائمتك!','no_results'=>'لا توجد نتائج.','not_authorized'=>'غير مصرح لك.','vip_closed'=>'الشراء متوقف.','vip_bought'=>'تم تفعيل VIP.','ads_soon'=>'لا يوجد إعلان.','feature_vip_only'=>'هذه الميزة لـ VIP.','public_on'=>'عام الآن.','public_off'=>'خاص الآن.','admins_list'=>'المدراء:','stats'=>'الإحصاءات: مستخدمون {u} | بحث {q} | قوائم {p}'],
		'tr' => ['welcome_master' => 'Hoş geldiniz! /newbot <token> gönderin.','welcome_userbot'=>'Şarkı veya sanatçı adı gönderin.','choose_language'=>'Dil seçin:','playlist_added'=>'Çalma listenize eklendi!','no_results'=>'Sonuç yok.','not_authorized'=>'Yetkiniz yok.','vip_closed'=>'VIP kapalı.','vip_bought'=>'VIP etkin.','ads_soon'=>'Reklam yok.','feature_vip_only'=>'VIP gerekir.','public_on'=>'Artık herkese açık.','public_off'=>'Artık özel.','admins_list'=>'Yöneticiler:','stats'=>'İstatistikler: kullanıcı {u} | arama {q} | liste {p}'],
		'ru' => ['welcome_master' => 'Добро пожаловать! Отправьте /newbot <token>.','welcome_userbot'=>'Отправьте название песни или артиста.','choose_language'=>'Выберите язык:','playlist_added'=>'Добавлено в плейлист!','no_results'=>'Ничего не найдено.','not_authorized'=>'Нет доступа.','vip_closed'=>'VIP закрыт.','vip_bought'=>'VIP активирован.','ads_soon'=>'Нет рекламы.','feature_vip_only'=>'Требуется VIP.','public_on'=>'Теперь публичный.','public_off'=>'Теперь приватный.','admins_list'=>'Админы:','stats':'Статистика: пользователи {u} | поиски {q} | плейлисты {p}'],
		'es' => ['welcome_master' => '¡Bienvenido! Envía /newbot <token>.','welcome_userbot'=>'Envía nombre de canción o artista.','choose_language'=>'Elige tu idioma:','playlist_added':'Añadido a tu playlist.','no_results':'Sin resultados.','not_authorized':'No autorizado.','vip_closed':'VIP cerrado.','vip_bought':'VIP activado.','ads_soon':'Sin anuncios.','feature_vip_only':'Requiere VIP.','public_on':'Ahora público.','public_off':'Ahora privado.','admins_list':'Admins:','stats':'Estadísticas: usuarios {u} | búsquedas {q} | listas {p}'],
		'de' => ['welcome_master' => 'Willkommen! Sende /newbot <token>.','welcome_userbot'=>'Sende Song- oder Künstlername.','choose_language'=>'Sprache wählen:','playlist_added':'Zur Playlist hinzugefügt!','no_results':'Keine Ergebnisse.','not_authorized':'Nicht autorisiert.','vip_closed':'VIP geschlossen.','vip_bought':'VIP aktiviert.','ads_soon':'Keine Werbung.','feature_vip_only':'VIP erforderlich.','public_on':'Öffentlich.','public_off':'Privat.','admins_list':'Admins:','stats':'Statistiken: Nutzer {u} | Suchen {q} | Playlists {p}'],
		'fr' => ['welcome_master' => 'Bienvenue ! Envoie /newbot <token>.','welcome_userbot':'Envoie un titre ou artiste.','choose_language':'Choisis ta langue:','playlist_added':'Ajouté à ta playlist !','no_results':'Aucun résultat.','not_authorized':'Non autorisé.','vip_closed':'VIP fermé.','vip_bought':'VIP activé.','ads_soon':'Pas de pub.','feature_vip_only':'VIP requis.','public_on':'Public.','public_off':'Privé.','admins_list':'Admins:','stats':'Stats: utilisateurs {u} | recherches {q} | playlists {p}'],
		'it' => ['welcome_master' => 'Benvenuto! Invia /newbot <token>.','welcome_userbot':'Invia nome brano o artista.','choose_language':'Scegli la lingua:','playlist_added':'Aggiunto alla playlist!','no_results':'Nessun risultato.','not_authorized':'Non autorizzato.','vip_closed':'VIP chiuso.','vip_bought':'VIP attivato.','ads_soon':'Nessuna pubblicità.','feature_vip_only':'Richiede VIP.','public_on':'Pubblico.','public_off':'Privato.','admins_list':'Admin:','stats':'Statistiche: utenti {u} | ricerche {q} | playlist {p}'],
		'pt' => ['welcome_master' => 'Bem-vindo! Envie /newbot <token>.','welcome_userbot':'Envie nome da música ou artista.','choose_language':'Escolha o idioma:','playlist_added':'Adicionado à playlist!','no_results':'Sem resultados.','not_authorized':'Não autorizado.','vip_closed':'VIP fechado.','vip_bought':'VIP ativado.','ads_soon':'Sem anúncios.','feature_vip_only':'Requer VIP.','public_on':'Público.','public_off':'Privado.','admins_list':'Admins:','stats':'Estatísticas: usuários {u} | buscas {q} | playlists {p}'],
		'hi' => ['welcome_master' => 'स्वागत है! /newbot <token> भेजें.','welcome_userbot':'गाना या कलाकार नाम भेजें.','choose_language':'भाषा चुनें:','playlist_added':'प्लेलिस्ट में जोड़ा गया!','no_results':'कोई परिणाम नहीं.','not_authorized':'अनुमति नहीं.','vip_closed':'VIP बंद है.','vip_bought':'VIP सक्रिय.','ads_soon':'कोई विज्ञापन नहीं.','feature_vip_only':'VIP आवश्यक.','public_on':'अब सार्वजनिक.','public_off':'अब निजी.','admins_list':'एडमिन:','stats':'आँकड़े: उपयोगकर्ता {u} | खोज {q} | प्लेलिस्ट {p}'],
		'ur' => ['welcome_master' => 'خوش آمدید! /newbot <token> بھیجیں.','welcome_userbot':'گانے یا فنکار کا نام بھیجیں.','choose_language':'زبان منتخب کریں:','playlist_added':'پلے لسٹ میں شامل!','no_results':'کوئی نتیجہ نہیں.','not_authorized':'اجازت نہیں.','vip_closed':'VIP بند ہے.','vip_bought':'VIP فعال.','ads_soon':'کوئی اشتہار نہیں.','feature_vip_only':'VIP درکار.','public_on':'اب عوامی.','public_off':'اب نجی.','admins_list':'ایڈمنز:','stats':'اعداد: صارفین {u} | تلاش {q} | فہرست {p}'],
		'id' => ['welcome_master' => 'Selamat datang! Kirim /newbot <token>.','welcome_userbot':'Kirim nama lagu/artist.','choose_language':'Pilih bahasa:','playlist_added':'Ditambahkan ke playlist!','no_results':'Tidak ada hasil.','not_authorized':'Tidak diizinkan.','vip_closed':'VIP ditutup.','vip_bought':'VIP aktif.','ads_soon':'Tidak ada iklan.','feature_vip_only':'Memerlukan VIP.','public_on':'Publik.','public_off':'Privat.','admins_list':'Admin:','stats':'Statistik: pengguna {u} | cari {q} | playlist {p}'],
		'zh' => ['welcome_master' => '欢迎！发送 /newbot <token>.','welcome_userbot':'发送歌曲或歌手名称.','choose_language':'选择语言:','playlist_added':'已加入播放列表!','no_results':'未找到结果.','not_authorized':'未授权.','vip_closed':'VIP 关闭.','vip_bought':'VIP 已激活.','ads_soon':'暂无广告.','feature_vip_only':'需 VIP.','public_on':'公开.','public_off':'私密.','admins_list':'管理员:','stats':'统计: 用户 {u} | 搜索 {q} | 列表 {p}'],
		'ja' => ['welcome_master' => 'ようこそ！/newbot <token> を送信。','welcome_userbot':'曲名またはアーティスト名を送ってください。','choose_language':'言語を選択:','playlist_added':'プレイリストに追加しました！','no_results':'結果が見つかりません。','not_authorized':'権限がありません。','vip_closed':'VIPは現在停止中。','vip_bought':'VIPが有効化されました。','ads_soon':'広告はありません。','feature_vip_only':'VIPが必要です。','public_on':'公開になりました。','public_off':'非公開になりました。','admins_list':'管理者:','stats':'統計: ユーザー {u} | 検索 {q} | プレイリスト {p}'],
	];
}

/** Translate a key for a given language with optional placeholders */
function t(string $key, string $lang, array $vars = []): string {
	$phr = getPhrases();
	$dict = $phr[$lang] ?? $phr['en'];
	$text = $dict[$key] ?? ($phr['en'][$key] ?? $key);
	foreach ($vars as $k => $v) {
		$text = str_replace('{' . $k . '}', (string)$v, $text);
	}
	return $text;
}

// ========= Utilities =========

function ensureUser(PDO $pdo, int $userId, ?string $username, ?string $lang = null): void {
	$stmt = $pdo->prepare('INSERT INTO users (user_id, username, lang) VALUES (?,?,?) ON DUPLICATE KEY UPDATE username = VALUES(username)');
	$stmt->execute([$userId, $username, $lang]);
}

function ensureStatsRow(PDO $pdo, int $botId): void {
	$stmt = $pdo->prepare('INSERT IGNORE INTO stats (bot_id) VALUES (?)');
	$stmt->execute([$botId]);
}

function incrementStat(PDO $pdo, int $botId, string $column, int $amount = 1): void {
	ensureStatsRow($pdo, $botId);
	$allowed = ['total_users','total_queries','total_playlists'];
	if (!in_array($column, $allowed, true)) return;
	$pdo->prepare("UPDATE stats SET $column = $column + ?, last_update = NOW() WHERE bot_id = ?")
		->execute([$amount, $botId]);
}

function recordRanking(PDO $pdo, string $trackId, int $searchInc = 0, int $saveInc = 0): void {
	$pdo->prepare('INSERT INTO rankings (track_id, search_count, save_count) VALUES (?,?,?) ON DUPLICATE KEY UPDATE search_count = search_count + VALUES(search_count), save_count = save_count + VALUES(save_count), last_update = NOW()')
		->execute([$trackId, $searchInc, $saveInc]);
}

function fetchRandomAd(PDO $pdo): ?array {
	$stmt = $pdo->query('SELECT * FROM ads ORDER BY RAND() LIMIT 1');
	$ad = $stmt->fetch();
	return $ad ?: null;
}

function sendAd(string $token, int|string $chatId, ?array $ad): void {
	if (!$ad) return;
	$kb = [];
	if (!empty($ad['inline_keyboard'])) {
		$json = json_decode((string)$ad['inline_keyboard'], true);
		if (is_array($json)) { $kb = $json; }
	}
	$replyMarkup = $kb ? ['reply_markup' => json_encode(['inline_keyboard' => $kb])] : [];
	switch ($ad['type']) {
		case 'photo':
			tgSendPhoto($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'video':
			// Fallback to sendMessage with link
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'mixed':
			// Expect content to be text with a URL; keep simple here
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
			break;
		case 'text':
		default:
			tgSendMessage($token, $chatId, (string)$ad['content'], $replyMarkup);
	}
}

function getUserLang(PDO $pdo, int $userId, ?string $fallback = 'fa'): string {
	$stmt = $pdo->prepare('SELECT lang FROM users WHERE user_id = ?');
	$stmt->execute([$userId]);
	$row = $stmt->fetch();
	$lang = $row['lang'] ?? null;
	return $lang ?: ($fallback ?: 'en');
}

function setUserLang(PDO $pdo, int $userId, string $lang): void {
	$pdo->prepare('UPDATE users SET lang = ? WHERE user_id = ?')->execute([$lang, $userId]);
}

function isMasterAdmin(int $userId): bool {
	return in_array($userId, MASTER_ADMIN_IDS, true);
}

function isBotVip(array $botRow): array {
	$level = $botRow['vip_level'] ?? null;
	$isVip = (int)($botRow['is_vip'] ?? 0) === 1;
	$expire = $botRow['vip_expire'] ?? null;
	if ($expire) {
		try {
			$expTime = strtotime((string)$expire);
			if ($expTime !== false && $expTime < time()) {
				$isVip = false; $level = null;
			}
		} catch (Throwable $e) { /* ignore */ }
	}
	return ['is_vip' => $isVip, 'level' => $level];
}

function getVipMultipliers(): array {
	return [
		'Basic' => 1.0,
		'Premium' => 1.5,
		'Pro' => 2.0,
	];
}

function computeVipPrices(PDO $pdo, string $level): array {
	$mult = getVipMultipliers()[$level] ?? 1.0;
	$row = $pdo->query('SELECT price_local, price_stars FROM vip_config ORDER BY id ASC LIMIT 1')->fetch();
	$baseLocal = isset($row['price_local']) ? (float)$row['price_local'] : 0.0;
	$baseStars = isset($row['price_stars']) ? (int)$row['price_stars'] : 0;
	return [
		'price_local' => (float)round($baseLocal * $mult, 2),
		'price_stars' => (int)ceil($baseStars * $mult),
	];
}

function isVipOpen(PDO $pdo): bool {
	$row = $pdo->query('SELECT is_open FROM vip_config ORDER BY id ASC LIMIT 1')->fetch();
	return (int)($row['is_open'] ?? 1) === 1;
}

function buildInlineKeyboard(array $buttonsRows): string {
	return json_encode(['inline_keyboard' => $buttonsRows], JSON_UNESCAPED_UNICODE);
}

function sendLanguagePicker(string $token, int|string $chatId): void {
	$langs = ['fa','en','ar','tr','ru','es','de','fr','it','pt','hi','ur','id','zh','ja'];
	$rows = [];
	$chunk = [];
	foreach ($langs as $code) {
		$chunk[] = ['text' => strtoupper($code), 'callback_data' => 'lang:' . $code];
		if (count($chunk) === 5) { $rows[] = $chunk; $chunk = []; }
	}
	if ($chunk) $rows[] = $chunk;
	$kb = buildInlineKeyboard($rows);
	tgSendMessage($token, $chatId, t('choose_language', 'fa'), ['reply_markup' => $kb]);
}

function resolveDownloaderUrl(string $query, string $quality): ?string {
	if (DOWNLOADER_BASE_URL === '') return null;
	$u = str_replace(['{q}','{quality}'], [urlencode($query), urlencode($quality)], DOWNLOADER_BASE_URL);
	return $u;
}

function getBotRowByToken(PDO $pdo, string $token): ?array {
	$stmt = $pdo->prepare('SELECT * FROM bots WHERE bot_token = ?');
	$stmt->execute([$token]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function isUserBotAdmin(PDO $pdo, int $botId, int $userId): bool {
	// owner is admin
	$stmt = $pdo->prepare('SELECT owner_id FROM bots WHERE id = ?');
	$stmt->execute([$botId]);
	$ownerId = (int)($stmt->fetch()['owner_id'] ?? 0);
	if ($ownerId === $userId) return true;
	// extra admins
	$stmt = $pdo->prepare('SELECT 1 FROM bot_admins WHERE bot_id = ? AND admin_user_id = ?');
	$stmt->execute([$botId, $userId]);
	return (bool)$stmt->fetch();
}

function upsertSetting(PDO $pdo, int $botId, array $fields): void {
	ensureStatsRow($pdo, $botId);
	$stmt = $pdo->prepare('SELECT id FROM settings WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	if ($row) {
		// Update only provided fields
		$setParts = [];
		$params = [];
		foreach ($fields as $k => $v) { $setParts[] = "$k = ?"; $params[] = $v; }
		$params[] = $botId;
		$pdo->prepare('UPDATE settings SET ' . implode(', ', $setParts) . ' WHERE bot_id = ?')->execute($params);
	} else {
		$columns = array_merge(['bot_id'], array_keys($fields));
		$placeholders = array_fill(0, count($columns), '?');
		$params = array_merge([$botId], array_values($fields));
		$pdo->prepare('INSERT INTO settings (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')')->execute($params);
	}
}

function getSettings(PDO $pdo, int $botId): array {
	$stmt = $pdo->prepare('SELECT * FROM settings WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	return $row ?: [];
}

function getStats(PDO $pdo, int $botId): array {
	$stmt = $pdo->prepare('SELECT * FROM stats WHERE bot_id = ?');
	$stmt->execute([$botId]);
	$row = $stmt->fetch();
	return $row ?: ['total_users'=>0,'total_queries'=>0,'total_playlists'=>0];
}

function readUpdate(): array {
	$raw = file_get_contents('php://input');
	$data = json_decode((string)$raw, true);
	return is_array($data) ? $data : [];
}

function replyMarkupInline(array $rows): array {
	return ['reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function cleanText(string $text): string {
	return trim(preg_replace('/\s+/', ' ', $text));
}

function getCommandAndArgs(?string $text): array {
	$text = $text ?? '';
	if ($text === '') return ['', ''];
	$parts = explode(' ', trim($text), 2);
	$cmd = strtolower($parts[0]);
	$args = $parts[1] ?? '';
	return [$cmd, $args];
}

function ensureBotAndUser(PDO $pdo, array $botRow, int $userId, ?string $username): void {
	ensureUser($pdo, $userId, $username, null);
	ensureStatsRow($pdo, (int)$botRow['id']);
	// increment users once per unique user-bot pair on first interaction
	$stmt = $pdo->prepare('SELECT 1 FROM playlists WHERE user_id = ? AND bot_id = ? LIMIT 1');
	$stmt->execute([$userId, (int)$botRow['id']]);
	// Use a small heuristic: if user has no playlist row yet, count as potential new user
	if (!$stmt->fetch()) {
		incrementStat($pdo, (int)$botRow['id'], 'total_users', 1);
	}
}

?>

<?php
define('API_KEY_CR', '7494346557:AAEuRsG32P3fkQe5ooilDRJG-maKly_Qe1k');  //توکن ربات اصلی
define('API_KEY_LOCK_BOT', API_KEY_CR);
##----------------------
$host_folder = 'https://dakalll.ir/Bot/'; //ادرس فولدر سورس
$admin = 5641303137; // ایدی عددی ادمین
$support = '@Poshtiban_Dakalbot'; // پشتیبانی
$main_channel = '@DakalPvSaz'; // یوزرنیم کانال
$main_bot = '@Creatorbotdakalbot'; // یوزرنیم ربات
$logchannel = '-1002363662773'; // ایدی عددی کانال برای ارسال لاگ
$public_logchannel = '@Dakaladd'; // ایدی کانال عمومی که ربات اصلی در ان ادمین است برای بخش تبلیغات

$lock_channel_1 = '@DakalPvSaz'; // کانال اول قفل
$lock_channel_2 = '@DakalPvSaz'; // کانال دوم قفل

if (is_file('../../Data/vip-price.txt')) {
	$vip_price = file_get_contents('../../Data/vip-price.txt');
}
else {
	$vip_price = 'none';
}
$vip_price = is_numeric($vip_price) ? $vip_price : 20000; // قیمت پیشفرض وی ای پی 3000 تومن
##----------------------اطلاعات دیتابیس
$DB_HOST = 'localhost';
$DB_NAME = 'dakalsro_Sag'; // نام دیتابیس
$DB_USERNAME = 'dakalsro_Sag';  //یوزرینم برای اتصال به دیتابیس
$DB_PASSWORD = 'hosyarww123'; // پسورد برای اتصال به دیتابیس
##----------------------##
function deleteFolder($path)
{
	if (!is_dir($path)) return false;
	if ($handle = opendir($path)) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				if (is_file($path . '/' . $file)) { 
					@unlink($path . '/' . $file);
				} 
				if (is_dir($path . '/' . $file)) { 
					deletefolder($path . '/' . $file); 
					@rmdir($path . '/' . $file); 
				}
			}
		}
	}
	return rmdir($path);
}
##----------------------
function timeElapsed($secs)
{
	$bit = [
		'سال' => $secs / 31556926 % 12,
		'هفته' => $secs / 604800 % 52,
		'روز' => $secs / 86400 % 7,
		'ساعت' => $secs / 3600 % 24,
		'دقیقه' => $secs / 60 % 60,
		'ثانیه' => $secs % 60
	];
	
	foreach ($bit as $k => $v) {
		if ($v >= 1) {
				$ret[] = "<b>{$v}</b>";
				$ret[] = $k;
				$ret[] = 'و';
		}
	}
	return trim(join(' ', $ret), 'و ');
}
##----------------------
function jdate($format, $timestamp='', $none='', $time_zone='Asia/Tehran', $tr_num='fa')
{
	$T_sec=0;/* <= رفع خطاي زمان سرور ، با اعداد '+' و '-' بر حسب ثانيه */

 if ($time_zone!='local')date_default_timezone_set(($time_zone==='')?'Asia/Tehran':$time_zone);
 $ts=$T_sec+(($timestamp==='')?time():tr_num($timestamp));
 $date=explode('_',date('H_i_j_n_O_P_s_w_Y', $ts));
 list($j_y, $j_m, $j_d)=gregorian_to_jalali($date[8], $date[3], $date[2]);
 $doy=($j_m<7)?(($j_m-1)*31)+$j_d-1:(($j_m-7)*30)+$j_d+185;
 $kab=(((($j_y%33)%4)-1)==((int)(($j_y%33)*0.05)))?1:0;
 $sl=strlen($format);
 $out='';
 for($i=0; $i<$sl; $i++) {
  $sub=substr($format, $i,1);
  if ($sub=='\\') {
	$out.=substr($format,++$i,1);
	continue;
  }
  switch($sub) {
	case'B':case'e':case'g':
	case'G':case'h':case'I':
	case'T':case'u':case'Z':
	$out.=date($sub, $ts);
	break;

	case'a':
	$out.=($date[0]<12)?'ق.ظ':'ب.ظ';
	break;

	case'A':
	$out.=($date[0]<12)?'قبل از ظهر':'بعد از ظهر';
	break;

	case'b':
	$out.=(int)($j_m/3.1)+1;
	break;

	case'c':
	$out.=$j_y.'/'.$j_m.'/'.$j_d.' ،'.$date[0].':'.$date[1].':'.$date[6].' '.$date[5];
	break;

	case'C':
	$out.=(int)(($j_y+99)/100);
	break;

	case'd':
	$out.=($j_d<10)?'0'.$j_d:$j_d;
	break;

	case'D':
	$out.=jdate_words(array('kh' => $date[7]), ' ');
	break;

	case'f':
	$out.=jdate_words(array('ff' => $j_m), ' ');
	break;

	case'F':
	$out.=jdate_words(array('mm' => $j_m), ' ');
	break;

	case'H':
	$out.=$date[0];
	break;

	case'i':
	$out.=$date[1];
	break;

	case'j':
	$out.=$j_d;
	break;

	case'J':
	$out.=jdate_words(array('rr' => $j_d), ' ');
	break;

	case'k';
	$out.=tr_num(100-(int)($doy/($kab+365)*1000)/10, $tr_num);
	break;

	case'K':
	$out.=tr_num((int)($doy/($kab+365)*1000)/10, $tr_num);
	break;

	case'l':
	$out.=jdate_words(array('rh' => $date[7]), ' ');
	break;

	case'L':
	$out.=$kab;
	break;

	case'm':
	$out.=($j_m>9)?$j_m:'0'.$j_m;
	break;

	case'M':
	$out.=jdate_words(array('km' => $j_m), ' ');
	break;

	case'n':
	$out.=$j_m;
	break;

	case'N':
	$out.=$date[7]+1;
	break;

	case'o':
	$jdw=($date[7]==6)?0:$date[7]+1;
	$dny=364+$kab-$doy;
	$out.=($jdw>($doy+3) && $doy<3)?$j_y-1:(((3-$dny)>$jdw && $dny<3)?$j_y+1:$j_y);
	break;

	case'O':
	$out.=$date[4];
	break;

	case'p':
	$out.=jdate_words(array('mb' => $j_m), ' ');
	break;

	case'P':
	$out.=$date[5];
	break;

	case'q':
	$out.=jdate_words(array('sh' => $j_y), ' ');
	break;

	case'Q':
	$out.=$kab+364-$doy;
	break;

	case'r':
	$key=jdate_words(array('rh' => $date[7], 'mm' => $j_m));
	$out.=$date[0].':'.$date[1].':'.$date[6].' '.$date[4].' '.$key['rh'].'، '.$j_d.' '.$key['mm'].' '.$j_y;
	break;

	case's':
	$out.=$date[6];
	break;

	case'S':
	$out.='ام';
	break;

	case't':
	$out.=($j_m!=12)?(31-(int)($j_m/6.5)):($kab+29);
	break;

	case'U':
	$out.=$ts;
	break;

	case'v':
	 $out.=jdate_words(array('ss'=>($j_y%100)), ' ');
	break;

	case'V':
	$out.=jdate_words(array('ss' => $j_y), ' ');
	break;

	case'w':
	$out.=($date[7]==6)?0:$date[7]+1;
	break;

	case'W':
	$avs=(($date[7]==6)?0:$date[7]+1)-($doy%7);
	if ($avs<0)$avs+=7;
	$num=(int)(($doy+$avs)/7);
	if ($avs<4) {
	 $num++;
	}elseif ($num<1) {
	 $num=($avs==4 || $avs==((((($j_y%33)%4)-2)==((int)(($j_y%33)*0.05)))?5:4))?53:52;
	}
	$aks=$avs+$kab;
	if ($aks==7)$aks=0;
	$out.=(($kab+363-$doy)<$aks && $aks<3)?'01':(($num<10)?'0'.$num:$num);
	break;

	case'y':
	$out.=substr($j_y,2,2);
	break;

	case'Y':
	$out.=$j_y;
	break;

	case'z':
	$out.=$doy;
	break;

	default:$out.=$sub;
  }
 }
 return($tr_num!='en')?tr_num($out, 'fa', '.'):$out;
}

/*	F	*/
function jstrftime($format, $timestamp='', $none='', $time_zone='Asia/Tehran', $tr_num='fa') {

 $T_sec=0;/* <= رفع خطاي زمان سرور ، با اعداد '+' و '-' بر حسب ثانيه */

 if ($time_zone!='local')date_default_timezone_set(($time_zone==='')?'Asia/Tehran':$time_zone);
 $ts=$T_sec+(($timestamp==='')?time():tr_num($timestamp));
 $date=explode('_',date('h_H_i_j_n_s_w_Y', $ts));
 list($j_y, $j_m, $j_d)=gregorian_to_jalali($date[7], $date[4], $date[3]);
 $doy=($j_m<7)?(($j_m-1)*31)+$j_d-1:(($j_m-7)*30)+$j_d+185;
 $kab=(((($j_y%33)%4)-1)==((int)(($j_y%33)*0.05)))?1:0;
 $sl=strlen($format);
 $out='';
 for($i=0; $i<$sl; $i++) {
  $sub=substr($format, $i,1);
  if ($sub=='%') {
	$sub=substr($format,++$i,1);
  } else {
	$out.=$sub;
	continue;
  }
  switch($sub) {

	/* Day */
	case'a':
	$out.=jdate_words(array('kh' => $date[6]), ' ');
	break;

	case'A':
	$out.=jdate_words(array('rh' => $date[6]), ' ');
	break;

	case'd':
	$out.=($j_d<10)?'0'.$j_d:$j_d;
	break;

	case'e':
	$out.=($j_d<10)?' '.$j_d:$j_d;
	break;

	case'j':
	$out.=str_pad($doy+1,3,0,STR_PAD_LEFT);
	break;

	case'u':
	$out.=$date[6]+1;
	break;

	case'w':
	$out.=($date[6]==6)?0:$date[6]+1;
	break;

	/* Week */
	case'U':
	$avs=(($date[6]<5)?$date[6]+2:$date[6]-5)-($doy%7);
	if ($avs<0)$avs+=7;
	$num=(int)(($doy+$avs)/7)+1;
	if ($avs>3 || $avs==1)$num--;
	$out.=($num<10)?'0'.$num:$num;
	break;

	case'V':
	$avs=(($date[6]==6)?0:$date[6]+1)-($doy%7);
	if ($avs<0)$avs+=7;
	$num=(int)(($doy+$avs)/7);
	if ($avs<4) {
	 $num++;
	}elseif ($num<1) {
	 $num=($avs==4 || $avs==((((($j_y%33)%4)-2)==((int)(($j_y%33)*0.05)))?5:4))?53:52;
	}
	$aks=$avs+$kab;
	if ($aks==7)$aks=0;
	$out.=(($kab+363-$doy)<$aks && $aks<3)?'01':(($num<10)?'0'.$num:$num);
	break;

	case'W':
	$avs=(($date[6]==6)?0:$date[6]+1)-($doy%7);
	if ($avs<0)$avs+=7;
	$num=(int)(($doy+$avs)/7)+1;
	if ($avs>3)$num--;
	$out.=($num<10)?'0'.$num:$num;
	break;

	/* Month */
	case'b':
	case'h':
	$out.=jdate_words(array('km' => $j_m), ' ');
	break;

	case'B':
	$out.=jdate_words(array('mm' => $j_m), ' ');
	break;

	case'm':
	$out.=($j_m>9)?$j_m:'0'.$j_m;
	break;

	/* Year */
	case'C':
	$tmp=(int)($j_y/100);
	$out.=($tmp>9)?$tmp:'0'.$tmp;
	break;

	case'g':
	$jdw=($date[6]==6)?0:$date[6]+1;
	$dny=364+$kab-$doy;
	$out.=substr(($jdw>($doy+3) && $doy<3)?$j_y-1:(((3-$dny)>$jdw && $dny<3)?$j_y+1:$j_y),2,2);
	break;

	case'G':
	$jdw=($date[6]==6)?0:$date[6]+1;
	$dny=364+$kab-$doy;
	$out.=($jdw>($doy+3) && $doy<3)?$j_y-1:(((3-$dny)>$jdw && $dny<3)?$j_y+1:$j_y);
	break;

	case'y':
	$out.=substr($j_y,2,2);
	break;

	case'Y':
	$out.=$j_y;
	break;

	/* Time */
	case'H':
	$out.=$date[1];
	break;

	case'I':
	$out.=$date[0];
	break;

	case'l':
	$out.=($date[0]>9)?$date[0]:' '.(int)$date[0];
	break;

	case'M':
	$out.=$date[2];
	break;

	case'p':
	$out.=($date[1]<12)?'قبل از ظهر':'بعد از ظهر';
	break;

	case'P':
	$out.=($date[1]<12)?'ق.ظ':'ب.ظ';
	break;

	case'r':
	$out.=$date[0].':'.$date[2].':'.$date[5].' '.(($date[1]<12)?'قبل از ظهر':'بعد از ظهر');
	break;

	case'R':
	$out.=$date[1].':'.$date[2];
	break;

	case'S':
	$out.=$date[5];
	break;

	case'T':
	$out.=$date[1].':'.$date[2].':'.$date[5];
	break;

	case'X':
	$out.=$date[0].':'.$date[2].':'.$date[5];
	break;

	case'z':
	$out.=date('O', $ts);
	break;

	case'Z':
	$out.=date('T', $ts);
	break;

	/* Time && Date Stamps */
	case'c':
	$key=jdate_words(array('rh' => $date[6], 'mm' => $j_m));
	$out.=$date[1].':'.$date[2].':'.$date[5].' '.date('P', $ts).' '.$key['rh'].'، '.$j_d.' '.$key['mm'].' '.$j_y;
	break;

	case'D':
	$out.=substr($j_y,2,2).'/'.(($j_m>9)?$j_m:'0'.$j_m).'/'.(($j_d<10)?'0'.$j_d:$j_d);
	break;

	case'F':
	$out.=$j_y.'-'.(($j_m>9)?$j_m:'0'.$j_m).'-'.(($j_d<10)?'0'.$j_d:$j_d);
	break;

	case's':
	$out.=$ts;
	break;

	case'x':
	$out.=substr($j_y,2,2).'/'.(($j_m>9)?$j_m:'0'.$j_m).'/'.(($j_d<10)?'0'.$j_d:$j_d);
	break;

	/* Miscellaneous */
	case'n':
	$out.="\n";
	break;

	case't':
	$out.="\t";
	break;

	case'%':
	$out.='%';
	break;

	default:$out.=$sub;
  }
 }
 return($tr_num!='en')?tr_num($out, 'fa', '.'):$out;
}

/*	F	*/
function jmktime($h='', $m='', $s='', $jm='', $jd='', $jy='', $none='', $timezone='Asia/Tehran') {
 if ($timezone!='local')date_default_timezone_set($timezone);
 if ($h==='') {
  return time();
 } else {
	list($h, $m, $s, $jm, $jd, $jy)=explode('_',tr_num($h.'_'.$m.'_'.$s.'_'.$jm.'_'.$jd.'_'.$jy));
  if ($m==='') {
   return mktime($h);
  } else {
   if ($s==='') {
	return mktime($h, $m);
   } else {
	if ($jm==='') {
	 return mktime($h, $m, $s);
	} else {
	 $jdate=explode('_',jdate('Y_j', '', '', $timezone, 'en'));
	 if ($jd==='') {
	  list($gy, $gm, $gd)=jalali_to_gregorian($jdate[0], $jm, $jdate[1]);
	  return mktime($h, $m, $s, $gm);
	 } else {
	  if ($jy==='') {
	   list($gy, $gm, $gd)=jalali_to_gregorian($jdate[0], $jm, $jd);
	   return mktime($h, $m, $s, $gm, $gd);
	  } else {
	   list($gy, $gm, $gd)=jalali_to_gregorian($jy, $jm, $jd);
	   return mktime($h, $m, $s, $gm, $gd, $gy);
	  }
	 }
	}
   }
  }
 }
}

/*	F	*/
function jgetdate($timestamp='', $none='', $timezone='Asia/Tehran', $tn='en') {
 $ts=($timestamp==='')?time():tr_num($timestamp);
 $jdate=explode('_',jdate('F_G_i_j_l_n_s_w_Y_z', $ts, '', $timezone, $tn));
 return array(
	'seconds'=>tr_num((int)tr_num($jdate[6]), $tn),
	'minutes'=>tr_num((int)tr_num($jdate[2]), $tn),
	'hours' => $jdate[1],
	'mday' => $jdate[3],
	'wday' => $jdate[7],
	'mon' => $jdate[5],
	'year' => $jdate[8],
	'yday' => $jdate[9],
	'weekday' => $jdate[4],
	'month' => $jdate[0],
	0=>tr_num($ts, $tn)
 );
}

/*	F	*/
function jcheckdate($jm, $jd, $jy) {
 list($jm, $jd, $jy)=explode('_',tr_num($jm.'_'.$jd.'_'.$jy));
 $l_d=($jm==12)?((((($jy%33)%4)-1)==((int)(($jy%33)*0.05)))?30:29):31-(int)($jm/6.5);
 return($jm>12 || $jd>$l_d || $jm<1 || $jd<1 || $jy<1)?false:true;
}

/*	F	*/
function tr_num($str, $mod='en', $mf='٫') {
 $num_a=array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.');
 $key_a=array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $mf);
 return($mod=='fa')?str_replace($num_a, $key_a, $str):str_replace($key_a, $num_a, $str);
}

/*	F	*/
function jdate_words($array, $mod='') {
 foreach($array as $type=>$num) {
  $num=(int)tr_num($num);
  switch($type) {

	case'ss':
	$sl=strlen($num);
	$xy3=substr($num,2-$sl,1);
	$h3=$h34=$h4='';
	if ($xy3==1) {
	 $p34='';
	 $k34=array('ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده');
	 $h34=$k34[substr($num,2-$sl,2)-10];
	} else {
	 $xy4=substr($num,3-$sl,1);
	 $p34=($xy3==0 || $xy4==0)?'':' و ';
	 $k3=array('', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود');
	 $h3=$k3[$xy3];
	 $k4=array('', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه');
	 $h4=$k4[$xy4];
	}
	$array[$type]=(($num>99)?str_replace(array('12', '13', '14', '19', '20')
 ,array('هزار و دویست', 'هزار و سیصد', 'هزار و چهارصد', 'هزار و نهصد', 'دوهزار')
 ,substr($num,0,2)).((substr($num,2,2)=='00')?'':' و '):'').$h3.$p34.$h34.$h4;
	break;

	case'mm':
	$key=array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
	$array[$type]=$key[$num-1];
	break;

	case'rr':
	$key=array('یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه', 'ده', 'یازده', 'دوازده', 'سیزده'
 , 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده', 'بیست', 'بیست و یک', 'بیست و دو', 'بیست و سه'
 , 'بیست و چهار', 'بیست و پنج', 'بیست و شش', 'بیست و هفت', 'بیست و هشت', 'بیست و نه', 'سی', 'سی و یک');
	$array[$type]=$key[$num-1];
	break;

	case'rh':
	$key=array('یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه');
	$array[$type]=$key[$num];
	break;

	case'sh':
	$key=array('مار', 'اسب', 'گوسفند', 'میمون', 'مرغ', 'سگ', 'خوک', 'موش', 'گاو', 'پلنگ', 'خرگوش', 'نهنگ');
	$array[$type]=$key[$num%12];
	break;

	case'mb':
	$key=array('حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت');
	$array[$type]=$key[$num-1];
	break;

	case'ff':
	$key=array('بهار', 'تابستان', 'پاییز', 'زمستان');
	$array[$type]=$key[(int)($num/3.1)];
	break;

	case'km':
	$key=array('فر', 'ار', 'خر', 'تی‍', 'مر', 'شه‍', 'مه‍', 'آب‍', 'آذ', 'دی', 'به‍', 'اس‍');
	$array[$type]=$key[$num-1];
	break;

	case'kh':
	$key=array('ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش');
	$array[$type]=$key[$num];
	break;

	default:$array[$type]=$num;
  }
 }
 return($mod==='')?$array:implode($mod, $array);
}
function gregorian_to_jalali($gy, $gm, $gd, $mod='') {
	list($gy, $gm, $gd)=explode('_',tr_num($gy.'_'.$gm.'_'.$gd));/* <= Extra :اين سطر ، جزء تابع اصلي نيست */
 $g_d_m=array(0,31,59,90,120,151,181,212,243,273,304,334);
 if ($gy > 1600) {
  $jy=979;
  $gy-=1600;
 } else {
  $jy=0;
  $gy-=621;
 }
 $gy2=($gm > 2)?($gy+1):$gy;
 $days=(365*$gy) +((int)(($gy2+3)/4)) -((int)(($gy2+99)/100)) +((int)(($gy2+399)/400)) -80 +$gd +$g_d_m[$gm-1];
 $jy+=33*((int)($days/12053));
 $days%=12053;
 $jy+=4*((int)($days/1461));
 $days%=1461;
 $jy+=(int)(($days-1)/365);
 if ($days > 365)$days=($days-1)%365;
 if ($days < 186) {
  $jm=1+(int)($days/31);
  $jd=1+($days%31);
 } else {
  $jm=7+(int)(($days-186)/30);
  $jd=1+(($days-186)%30);
 }
 return($mod==='')?array($jy, $jm, $jd):$jy .$mod .$jm .$mod .$jd;
}

/*	F	*/
function jalali_to_gregorian($jy, $jm, $jd, $mod='') {
	list($jy, $jm, $jd)=explode('_',tr_num($jy.'_'.$jm.'_'.$jd));/* <= Extra :اين سطر ، جزء تابع اصلي نيست */
 if ($jy > 979) {
  $gy=1600;
  $jy-=979;
 } else {
  $gy=621;
 }
 $days=(365*$jy) +(((int)($jy/33))*8) +((int)((($jy%33)+3)/4)) +78 +$jd +(($jm<7)?($jm-1)*31:(($jm-7)*30)+186);
 $gy+=400*((int)($days/146097));
 $days%=146097;
 if ($days > 36524) {
  $gy+=100*((int)(--$days/36524));
  $days%=36524;
  if ($days >= 365)$days++;
 }
 $gy+=4*((int)(($days)/1461));
 $days%=1461;
 $gy+=(int)(($days-1)/365);
 if ($days > 365)$days=($days-1)%365;
 $gd=$days+1;
 foreach(array(0,31,((($gy%4==0) && ($gy%100!=0)) || ($gy%400==0))?29:28 ,31,30,31,30,31,31,30,31,30,31) as $gm=>$v) {
  if ($gd <= $v)break;
  $gd-=$v;
 }
 return($mod==='')?array($gy, $gm, $gd):$gy .$mod .$gm .$mod .$gd;
}