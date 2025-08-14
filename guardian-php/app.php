<?php
// app.php - consolidated core for Guardian PHP Bot + Web

require_once __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use PDO;

class App {
	public PDO $pdo;
	public Client $http;
	public string $token;
	public string $apiBase;
	private ?int $botId = null;
	public function __construct() {
		if (file_exists(__DIR__.'/.env')) Dotenv::createImmutable(__DIR__)->load();
		$this->pdo = $this->createPdo();
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->http = new Client(['timeout'=>20]);
		$this->token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
		$this->apiBase = "https://api.telegram.org/bot{$this->token}";
	}
	private function createPdo(): PDO {
		$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $_ENV['MYSQL_HOST']??'127.0.0.1', (int)($_ENV['MYSQL_PORT']??3306), $_ENV['MYSQL_DB']??'guardian');
		return new PDO($dsn, $_ENV['MYSQL_USER']??'root', $_ENV['MYSQL_PASS']??'', [PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
	}
	public function migrate(): void {
		$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS groups (
	chat_id BIGINT PRIMARY KEY,
	type VARCHAR(32) NOT NULL,
	title VARCHAR(255) NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
	user_id BIGINT,
	chat_id BIGINT,
	first_name VARCHAR(255) NULL,
	last_name VARCHAR(255) NULL,
	username VARCHAR(255) NULL,
	is_admin TINYINT DEFAULT 0,
	warn_count INT DEFAULT 0,
	PRIMARY KEY (user_id, chat_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
	chat_id BIGINT PRIMARY KEY,
	anti_link TINYINT DEFAULT 1,
	anti_forward TINYINT DEFAULT 1,
	anti_badwords TINYINT DEFAULT 1,
	captcha_required TINYINT DEFAULT 1,
	max_warns INT DEFAULT 3,
	lockdown TINYINT DEFAULT 0,
	welcome_banner_url VARCHAR(512) NULL,
	welcome_text VARCHAR(512) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sanctions (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	chat_id BIGINT,
	user_id BIGINT,
	action VARCHAR(32),
	reason VARCHAR(512) NULL,
	expires_at INT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	INDEX (chat_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_actions (
	chat_id BIGINT,
	admin_id BIGINT,
	day CHAR(8),
	deletions INT DEFAULT 0,
	PRIMARY KEY (chat_id, admin_id, day)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bot_state (
	id TINYINT PRIMARY KEY DEFAULT 1,
	disabled TINYINT DEFAULT 0,
	force_channel_id BIGINT DEFAULT 0
) ENGINE=InnoDB;
SQL;
		$this->pdo->exec($sql);
		// Ensure new lock columns exist
		$locks = [
			'lock_hashtag' => 'TINYINT DEFAULT 0',
			'lock_username' => 'TINYINT DEFAULT 0',
			'lock_link' => 'TINYINT DEFAULT 0',
			'lock_text' => 'TINYINT DEFAULT 0',
			'lock_persian' => 'TINYINT DEFAULT 0',
			'lock_english' => 'TINYINT DEFAULT 0',
			'lock_games' => 'TINYINT DEFAULT 0',
			'lock_profanity' => 'TINYINT DEFAULT 0',
			'lock_service' => 'TINYINT DEFAULT 0',
			'lock_inline_button' => 'TINYINT DEFAULT 0',
			'lock_bots' => 'TINYINT DEFAULT 0',
			'lock_edit' => 'TINYINT DEFAULT 0',
			'lock_emoji' => 'TINYINT DEFAULT 0',
			'lock_forward' => 'TINYINT DEFAULT 0',
			'lock_gif' => 'TINYINT DEFAULT 0',
			'lock_sticker' => 'TINYINT DEFAULT 0',
			'lock_photo' => 'TINYINT DEFAULT 0',
			'lock_file' => 'TINYINT DEFAULT 0',
			'lock_contact' => 'TINYINT DEFAULT 0',
			'lock_location' => 'TINYINT DEFAULT 0',
			'lock_spoiler' => 'TINYINT DEFAULT 0',
			'lock_command' => 'TINYINT DEFAULT 0',
			'lock_pin' => 'TINYINT DEFAULT 0',
			'lock_video' => 'TINYINT DEFAULT 0',
			'lock_video_note' => 'TINYINT DEFAULT 0',
			'lock_audio' => 'TINYINT DEFAULT 0',
			'lock_voice' => 'TINYINT DEFAULT 0',
		];
		foreach ($locks as $col => $def) {
			$this->addColumnIfMissing('settings', $col, $def);
		}
	}

	private function addColumnIfMissing(string $table, string $column, string $definition): void {
		$st = $this->pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
		$st->execute([$table, $column]);
		$row = $st->fetch();
		if (((int)($row['c'] ?? 0)) === 0) {
			$this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
		}
	}

	private function hasEntityType(array $m, array $types): bool {
		$entities = array_merge($m['entities'] ?? [], $m['caption_entities'] ?? []);
		foreach ($entities as $e) {
			if (in_array(($e['type'] ?? ''), $types, true)) return true;
		}
		return false;
	}

	private function containsEmoji(?string $text): bool {
		if (!$text) return false;
		return (bool)preg_match('/[\x{1F300}-\x{1F5FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}]/u', $text);
	}

	// DB helpers
	public function getSettings(int $chatId): array {
		$st=$this->pdo->prepare('SELECT * FROM settings WHERE chat_id=?'); $st->execute([$chatId]); $row=$st->fetch();
		if ($row) return $row;
		$this->pdo->prepare('INSERT INTO settings (chat_id) VALUES (?)')->execute([$chatId]);
		$st=$this->pdo->prepare('SELECT * FROM settings WHERE chat_id=?'); $st->execute([$chatId]); return $st->fetch();
	}
	public function setSetting(int $chatId, string $key, $value): void {
		$key=preg_replace('/[^a-z_]/','',$key); $this->pdo->prepare("UPDATE settings SET {$key}=:v WHERE chat_id=:c")->execute([':v'=>$value,':c'=>$chatId]);
	}
	public function ensureUser(int $chatId, array $user): void {
		$this->pdo->prepare('INSERT IGNORE INTO users (user_id,chat_id,first_name,last_name,username) VALUES (?,?,?,?,?)')->execute([$user['id'],$chatId,$user['first_name']??'',$user['last_name']??'',$user['username']??'']);
	}
	public function addWarn(int $chatId, int $userId): void { $this->pdo->prepare('INSERT INTO users (user_id,chat_id,warn_count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE warn_count=warn_count+1')->execute([$userId,$chatId]); }
	public function getWarn(int $chatId, int $userId): int { $st=$this->pdo->prepare('SELECT warn_count FROM users WHERE chat_id=? AND user_id=?'); $st->execute([$chatId,$userId]); $r=$st->fetch(); return (int)($r['warn_count']??0); }
	public function logSanction(int $chatId, int $userId, string $action, string $reason='', ?int $expiresAt=null): void { $this->pdo->prepare('INSERT INTO sanctions (chat_id,user_id,action,reason,expires_at) VALUES (?,?,?,?,?)')->execute([$chatId,$userId,$action,$reason,$expiresAt]); }
	public function upsertGroup(int $chatId, string $type, ?string $title=null): void {
		$this->pdo->prepare('INSERT INTO groups (chat_id,type,title) VALUES (?,?,?) ON DUPLICATE KEY UPDATE type=VALUES(type), title=IFNULL(VALUES(title), title)')->execute([$chatId,$type,$title]);
	}
	public function listChats(): array { return $this->pdo->query('SELECT chat_id,type,title FROM groups ORDER BY chat_id DESC')->fetchAll(); }
	public function incrAdminRemoval(int $chatId, int $adminId): int {
		$day = date('Ymd');
		$this->pdo->prepare('INSERT INTO admin_actions (chat_id,admin_id,day,deletions) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE deletions = deletions + 1')->execute([$chatId,$adminId,$day]);
		$st=$this->pdo->prepare('SELECT deletions FROM admin_actions WHERE chat_id=? AND admin_id=? AND day=?'); $st->execute([$chatId,$adminId,$day]); $r=$st->fetch(); return (int)($r['deletions']??0);
	}
	public function getBotState(): array {
		$st=$this->pdo->query('SELECT * FROM bot_state LIMIT 1'); $row=$st->fetch(); if ($row) return $row; $this->pdo->exec('INSERT INTO bot_state (disabled) VALUES (0)'); return $this->pdo->query('SELECT * FROM bot_state LIMIT 1')->fetch();
	}
	public function setBotState(array $updates): void {
		$fields=[]; $params=[]; foreach($updates as $k=>$v){ $fields[]="$k = :$k"; $params[":$k"]=$v; }
		$this->pdo->prepare('UPDATE bot_state SET '.implode(',', $fields).' WHERE id=1')->execute($params);
	}

	// Telegram helpers
	public function call(string $method, array $params=[]): array { $res=$this->http->post($this->apiBase.'/'.$method, ['json'=>$params]); return json_decode((string)$res->getBody(), true) ?? []; }
	public function sendMessage(int $chatId, string $text, array $extra=[]): void { $this->call('sendMessage', array_merge(['chat_id'=>$chatId,'text'=>$text], $extra)); }
	public function sendPhoto(int $chatId, string $photoUrl, array $extra=[]): void { $this->call('sendPhoto', array_merge(['chat_id'=>$chatId,'photo'=>$photoUrl], $extra)); }
	public function deleteMessage(int $chatId, int $messageId): void { $this->call('deleteMessage', ['chat_id'=>$chatId,'message_id'=>$messageId]); }
	public function restrict(int $chatId, int $userId, array $permissions, ?int $until=null): void { $p=['chat_id'=>$chatId,'user_id'=>$userId,'permissions'=>$permissions]; if($until) $p['until_date']=$until; $this->call('restrictChatMember',$p); }
	public function demoteAdmin(int $chatId, int $userId): void {
		$this->call('promoteChatMember', [
			'chat_id'=>$chatId, 'user_id'=>$userId,
			'can_manage_chat'=>false,'can_change_info'=>false,'can_post_messages'=>false,'can_edit_messages'=>false,'can_delete_messages'=>false,'can_invite_users'=>false,'can_restrict_members'=>false,'can_pin_messages'=>false,'can_promote_members'=>false,'can_manage_video_chats'=>false
		]);
	}
	private function ensureBotId(): void { if ($this->botId===null){ $me=$this->call('getMe'); $this->botId = (int)($me['result']['id'] ?? 0); } }
	private function isBotAdmin(int $chatId): bool { $this->ensureBotId(); try { $r=$this->call('getChatMember',['chat_id'=>$chatId,'user_id'=>$this->botId]); $s=$r['result']['status']??''; return in_array($s,['administrator','creator'],true);} catch(\Throwable $e){ return false; } }
	public function isAdmin(int $chatId, int $userId): bool { try { $res=$this->call('getChatMember',['chat_id'=>$chatId,'user_id'=>$userId]); $s=$res['result']['status']??''; return in_array($s,['creator','administrator'],true);} catch(\Throwable $e){return false;} }
	public function authOkForWeb(int $chatId, int $userId, int $ts, string $hash): bool { $secret=$_ENV['WEB_APP_SECRET']??'devsecret'; $calc=hash_hmac('sha256', $chatId.':'.$userId.':'.$ts, $secret); if (!hash_equals($calc, $hash)) return false; return true; }
	public function authUserOk(int $userId, int $ts, string $hash): bool { $secret=$_ENV['WEB_APP_SECRET']??'devsecret'; $calc=hash_hmac('sha256', 'user:'.$userId.':'.$ts, $secret); return hash_equals($calc,$hash); }
	public function checkForceJoin(int $userId): bool {
		$state=$this->getBotState(); $ch=(int)($state['force_channel_id']??0); if($ch===0) return true; try { $r=$this->call('getChatMember',['chat_id'=>$ch,'user_id'=>$userId]); $st=$r['result']['status']??'left'; return !in_array($st, ['left','kicked'], true); } catch(\Throwable $e){ return false; }
	}
	public function listManageableChatsForUser(int $userId): array {
		$this->ensureBotId();
		$res=[]; foreach($this->listChats() as $c){ $chatId=(int)$c['chat_id']; if(!$this->isBotAdmin($chatId)) continue; if(!$this->isAdmin($chatId,$userId)) continue; $res[]=$c; }
		return $res;
	}

	// Filters
	public static function containsLink(?string $text): bool { if(!$text) return false; return (bool)preg_match('/(https?:\/\/|t\.me\/)\S+/i',$text); }
	public static function containsBadWord(?string $text): bool { if(!$text) return false; $text=mb_strtolower($text); foreach(['fuck','shit','bitch','asshole','faggot','nigger','slut','whore','porn','xxx','rape','sex'] as $w){ if (str_contains($text,$w)) return true; } return false; }

	// Bot logic
	public function runBot(): void {
		$this->migrate();
		$this->call('setMyCommands', ['commands'=>[
			['command'=>'help','description'=>'راهنما'],
			['command'=>'settings','description'=>'لینک پنل'],
			['command'=>'warn','description'=>'اخطار'],
			['command'=>'mute','description'=>'میوت کاربر'],
			['command'=>'ban','description'=>'بن کاربر'],
			['command'=>'unban','description'=>'آنبن کاربر'],
			['command'=>'lockdown','description'=>'لاکدان'],
			['command'=>'shutdown','description'=>'خاموش/روشن (مالک)'],
			['command'=>'broadcast','description'=>'ارسال تبلیغات (مالک)']
		]]);
		$offset=0; while(true){
			$updates=$this->call('getUpdates',['timeout'=>30,'offset'=>$offset]);
			foreach(($updates['result']??[]) as $u){ $offset=max($offset,(int)$u['update_id']+1); $this->handleUpdate($u); }
		}
	}
	private function handleUpdate(array $u): void {
		if (!empty($u['message'])) $this->handleMessage($u['message']);
		elseif (!empty($u['callback_query'])) $this->handleCallback($u['callback_query']);
		elseif (!empty($u['edited_message'])) {
			$m = $u['edited_message'];
			$chatId = (int)($m['chat']['id'] ?? 0); $userId = (int)($m['from']['id'] ?? 0);
			if ($chatId && $userId) {
				$settings = $this->getSettings($chatId);
				if ((int)($settings['lock_edit'] ?? 0) === 1 && !$this->isAdmin($chatId, $userId)) {
					$this->deleteMessage($chatId, (int)$m['message_id']);
				}
			}
		}
	}
	private function handleCallback(array $cq): void {
		$data=$cq['data']??''; $chatId=$cq['message']['chat']['id']??null; $userId=$cq['from']['id']??null; if(!$chatId||!$userId) return;
		if (str_starts_with($data,'captcha:')){ $target=(int)substr($data,8); if($target===(int)$userId){ $this->restrict($chatId,$userId,['can_send_messages'=>true,'can_send_audios'=>true,'can_send_documents'=>true,'can_send_photos'=>true,'can_send_videos'=>true,'can_send_video_notes'=>true,'can_send_voice_notes'=>true,'can_send_polls'=>true,'can_send_other_messages'=>true]); $this->call('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'تایید شد. خوش آمدید!']); } }
	}
	private function handleMessage(array $m): void {
		$chat=$m['chat']??[]; $chatId=(int)($chat['id']??0); $type=$chat['type']??'private'; $title=$chat['title']??null; $from=$m['from']??[]; $userId=(int)($from['id']??0); if(!$chatId||!$userId) return;
		$this->upsertGroup($chatId,$type,$title);
		$ownerId=(int)($_ENV['BOT_OWNER_ID']??0);

		if ($type==='private' && isset($m['text']) && $m['text']==='/start') { $this->sendMessage($chatId,'سلام! من ربات مدیریت گروه Dakal Guardian هستم. من را به گروه/کانال خود اضافه کنید.'); return; }

		$state=$this->getBotState(); if ((int)$state['disabled']===1 && $userId!==$ownerId) return;

		$settings = $this->getSettings($chatId);

		// Admin betrayal detection: admin removing members
		if (!empty($m['left_chat_member'])) {
			if ($this->isAdmin($chatId,$userId)) {
				$cnt = $this->incrAdminRemoval($chatId,$userId);
				if ($cnt > 10) { $this->demoteAdmin($chatId,$userId); $this->sendMessage($chatId, "ادمین {$userId} بیش از حد حذف انجام داده و دسترسی او گرفته شد."); $this->logSanction($chatId,$userId,'demote','mass removals'); }
			}
			if ((int)($settings['lock_service'] ?? 0) === 1) { $this->deleteMessage($chatId, (int)$m['message_id']); }
			return;
		}

		// New members
		if (!empty($m['new_chat_members'])) {
			// Bots lock: ban bots and warn adder
			if ((int)($settings['lock_bots'] ?? 0) === 1) {
				foreach ($m['new_chat_members'] as $mem) {
					if (!empty($mem['is_bot'])) {
						$this->call('banChatMember', ['chat_id'=>$chatId,'user_id'=>(int)$mem['id']]);
						$this->logSanction($chatId, (int)$mem['id'], 'ban', 'bot blocked');
					}
				}
			}
			if ((int)($settings['captcha_required']??1)===1) {
				foreach($m['new_chat_members'] as $mem){ $this->restrict($chatId,(int)$mem['id'],['can_send_messages'=>false]); $this->sendMessage($chatId,$mem['first_name'].' خوش آمدی! برای تایید روی دکمه بزنید.', ['reply_markup'=>['inline_keyboard'=>[[['text'=>'من ربات نیستم 🤖❌','callback_data'=>'captcha:'.$mem['id']]]]]]); }
			}
			$banner=$settings['welcome_banner_url']??''; $wtext=$settings['welcome_text']??''; if($banner){ $this->sendPhoto($chatId,$banner,['caption'=>$wtext?:'خوش آمدید']); } elseif($wtext){ $this->sendMessage($chatId,$wtext); }
			if ((int)($settings['lock_service'] ?? 0) === 1) { $this->deleteMessage($chatId, (int)$m['message_id']); }
			return;
		}

		// Pinned message lock
		if (!empty($m['pinned_message']) && (int)($settings['lock_pin'] ?? 0) === 1) {
			$this->call('unpinChatMessage', ['chat_id'=>$chatId]);
			$this->deleteMessage($chatId, (int)$m['message_id']);
			return;
		}

		// Forwarded
		if (!empty($m['forward_date']) && (((int)($settings['lock_forward'] ?? 0) === 1) || ((int)($settings['anti_forward'] ?? 0) === 1))) { $this->deleteMessage($chatId,(int)$m['message_id']); $this->sendMessage($chatId,'فوروارد پیام مجاز نیست.'); return; }

		// Commands
		if (!empty($m['text']) && $this->hasEntityType($m, ['bot_command'])) {
			if ((int)($settings['lock_command'] ?? 0) === 1 && !$this->isAdmin($chatId,$userId)) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
			$this->handleCommand($m,$settings); return;
		}

		// Ignore admins for content locks
		if ($this->isAdmin($chatId,$userId)) return;

		// Lockdown
		if ((int)($settings['lockdown']??0)===1) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }

		// Content locks
		$text=$m['text']??$m['caption']??'';
		if (((int)($settings['lock_text']??0)===1) && isset($m['text'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if ((((int)($settings['lock_link']??0)===1) || ((int)($settings['anti_link']??0)===1)) && (self::containsLink($text) || $this->hasEntityType($m,['url','text_link']))) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_hashtag']??0)===1) && ($this->hasEntityType($m,['hashtag']) || preg_match('/(^|\s)#[\w\u0600-\u06FF]+/u',$text))) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_username']??0)===1) && ($this->hasEntityType($m,['mention']) || preg_match('/(^|\s)@\w{4,}/',$text))) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_persian']??0)===1) && preg_match('/[\x{0600}-\x{06FF}]/u',$text)) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_english']??0)===1) && preg_match('/[A-Za-z]/',$text)) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_games']??0)===1) && !empty($m['game'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if ((((int)($settings['lock_profanity']??0)===1) || ((int)($settings['anti_badwords']??0)===1)) && self::containsBadWord($text)) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_inline_button']??0)===1) && !empty($m['reply_markup']['inline_keyboard'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_emoji']??0)===1) && $this->containsEmoji($text)) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_gif']??0)===1) && !empty($m['animation'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_sticker']??0)===1) && !empty($m['sticker'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_photo']??0)===1) && !empty($m['photo'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_file']??0)===1) && !empty($m['document'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_contact']??0)===1) && !empty($m['contact'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_location']??0)===1) && !empty($m['location'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_spoiler']??0)===1) && $this->hasEntityType($m,['spoiler'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_video']??0)===1) && !empty($m['video'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_video_note']??0)===1) && !empty($m['video_note'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_audio']??0)===1) && !empty($m['audio'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if (((int)($settings['lock_voice']??0)===1) && !empty($m['voice'])) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
	}
	private function handleCommand(array $m, array $settings): void {
		$chatId=(int)$m['chat']['id']; $fromId=(int)$m['from']['id']; $text=trim($m['text']); $cmd=explode(' ',$text); $command=explode('@',ltrim($cmd[0],'/'))[0]; $ownerId=(int)($_ENV['BOT_OWNER_ID']??0);
		$forceOk=$this->checkForceJoin($fromId); if(!$forceOk && $command!=='help'){ $this->sendMessage($chatId,'برای استفاده ابتدا در کانال تعیین‌شده عضو شوید.'); return; }
		switch($command){
			case 'help': $this->sendMessage($chatId,"دستورات:\n/settings (این چت)\n/settings_all (همه چت‌ها)\n/warn\n/mute\n/ban\n/unban\n/lockdown on|off\nshutdown on|off (owner)\n/broadcast <متن> (owner)\n/fwd (owner, reply)"); break;
			case 'settings': $ts=time(); $hash=hash_hmac('sha256',$chatId.':'.$fromId.':'.$ts, $_ENV['WEB_APP_SECRET']??'devsecret'); $url=rtrim($_ENV['WEB_ORIGIN']??'http://localhost:8080','/')."/?chat_id={$chatId}&user_id={$fromId}&timestamp={$ts}&hash={$hash}"; $this->sendMessage($chatId,"پنل مدیریت: {$url}"); break;
			case 'settings_all': $ts=time(); $uhash=hash_hmac('sha256','user:'.$fromId.':'.$ts, $_ENV['WEB_APP_SECRET']??'devsecret'); $url=rtrim($_ENV['WEB_ORIGIN']??'http://localhost:8080','/')."/?user_id={$fromId}&timestamp={$ts}&hash={$uhash}"; $this->sendMessage($chatId,"پنل همه چت‌ها: {$url}"); break;
			case 'warn': if(!$this->isAdmin($chatId,$fromId)) return; $target=$this->extractTargetUserId($m); if($target){ $this->addWarn($chatId,$target); $this->logSanction($chatId,$target,'warn','manual'); $this->sendMessage($chatId,'اخطار ثبت شد'); } break;
			case 'mute': if(!$this->isAdmin($chatId,$fromId)) return; $target=$this->extractTargetUserId($m); $duration=$this->parseDuration($cmd[2]??'10m'); if($target){ $this->restrict($chatId,$target,['can_send_messages'=>false], time()+$duration); $this->logSanction($chatId,$target,'mute','by admin', time()+$duration); $this->sendMessage($chatId,'کاربر میوت شد'); } break;
			case 'ban': if(!$this->isAdmin($chatId,$fromId)) return; $target=$this->extractTargetUserId($m); if($target){ $this->call('banChatMember',['chat_id'=>$chatId,'user_id'=>$target]); $this->logSanction($chatId,$target,'ban','by admin'); $this->sendMessage($chatId,'کاربر بن شد'); } break;
			case 'unban': if(!$this->isAdmin($chatId,$fromId)) return; $target=$this->extractTargetUserId($m); if($target){ $this->call('unbanChatMember',['chat_id'=>$chatId,'user_id'=>$target]); $this->logSanction($chatId,$target,'unban','by admin'); $this->sendMessage($chatId,'کاربر آنبن شد'); } break;
			case 'lockdown': if(!$this->isAdmin($chatId,$fromId)) return; $on=(($cmd[1]??'')==='on')?1:0; $this->setSetting($chatId,'lockdown',$on); $this->sendMessage($chatId,$on?'لاکدان فعال شد':'لاکدان غیرفعال شد'); break;
			case 'shutdown': if($fromId!==$ownerId) return; $on=(($cmd[1]??'')==='on')?1:0; $this->setBotState(['disabled'=>$on]); $this->sendMessage($chatId,$on?'ربات خاموش شد':'ربات روشن شد'); break;
			case 'broadcast': if($fromId!==$ownerId) return; $msg=trim(implode(' ', array_slice($cmd,1))); foreach($this->listChats() as $c){ $this->sendMessage((int)$c['chat_id'],$msg); usleep(150000); } $this->sendMessage($chatId,'ارسال شد'); break;
			case 'fwd': if($fromId!==$ownerId) return; if(!empty($m['reply_to_message'])){ $fromChat=$chatId; $messageId=(int)$m['reply_to_message']['message_id']; foreach($this->listChats() as $c){ $this->call('forwardMessage',['chat_id'=>(int)$c['chat_id'],'from_chat_id'=>$fromChat,'message_id'=>$messageId]); usleep(150000);} $this->sendMessage($chatId,'فوروارد شد'); } break;
		}
	}
	private function warnAndMaybeMute(int $chatId,int $userId,string $reason): void { $this->addWarn($chatId,$userId); $warns=$this->getWarn($chatId,$userId); $settings=$this->getSettings($chatId); $this->logSanction($chatId,$userId,'warn',$reason); $this->sendMessage($chatId, "کاربر {$userId}: {$reason} (اخطار {$warns}/{$settings['max_warns']})"); if($warns >= (int)$settings['max_warns']){ $this->restrict($chatId,$userId,['can_send_messages'=>false], time()+3600); $this->logSanction($chatId,$userId,'mute','auto mute after warns', time()+3600); $this->sendMessage($chatId,'کاربر به مدت ۱ ساعت میوت شد'); } }
	private function extractTargetUserId(array $m): ?int { if(!empty($m['reply_to_message']['from']['id'])) return (int)$m['reply_to_message']['from']['id']; $parts=explode(' ', $m['text']??''); $arg=$parts[1]??''; if($arg!=='' && ctype_digit($arg)) return (int)$arg; return null; }
	private function parseDuration(string $s): int { if(!preg_match('/^(\d+)([smhd])$/i',$s,$m)) return 600; $v=(int)$m[1]; return match(strtolower($m[2])){ 's'=>$v,'m'=>$v*60,'h'=>$v*3600,'d'=>$v*86400, default=>600 }; }

	// Web routing
	public function handleWeb(): void {
		$this->migrate();
		header('Cache-Control: no-store');
		$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)?:'/'; $method=$_SERVER['REQUEST_METHOD']??'GET';
		if ($path==='/' && $method==='GET'){ header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/public/index.html'); return; }
		if ($path==='/api/auth' && $method==='POST'){
			$in=json_decode(file_get_contents('php://input'), true)??[];
			$chatId=(int)($in['chat_id']??0); $userId=(int)($in['user_id']??0); $ts=(int)($in['timestamp']??0); $hash=(string)($in['hash']??'');
			$ok = $chatId ? $this->authOkForWeb($chatId,$userId,$ts,$hash) : $this->authUserOk($userId,$ts,$hash);
			header('Content-Type: application/json'); echo json_encode(['ok'=>$ok]); return;
		}
		if ($path==='/api/my_chats' && $method==='GET'){
			$userId=(int)($_GET['user_id']??0); $ts=(int)($_GET['timestamp']??0); $hash=(string)($_GET['hash']??'');
			if (!$this->authUserOk($userId,$ts,$hash)) { http_response_code(401); echo json_encode(['ok'=>false]); return; }
			if (!$this->checkForceJoin($userId)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'force_join_required']); return; }
			header('Content-Type: application/json'); echo json_encode(['ok'=>true,'chats'=>$this->listManageableChatsForUser($userId)]); return;
		}

		// All below require auth (either chat-bound or user-bound)
		$chatId=(int)($_GET['chat_id'] ?? ($_POST['chat_id'] ?? 0)); $userId=(int)($_GET['user_id'] ?? ($_POST['user_id'] ?? 0)); $ts=(int)($_GET['timestamp'] ?? ($_POST['timestamp'] ?? 0)); $hash=(string)($_GET['hash'] ?? ($_POST['hash'] ?? ''));
		$okChat = $chatId && $this->authOkForWeb($chatId,$userId,$ts,$hash);
		$okUser = !$chatId && $this->authUserOk($userId,$ts,$hash);
		if (!($okChat || $okUser)) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'unauthorized']); return; }
		// if user auth used and chat endpoints requested, verify user is admin and bot is admin
		if ($okUser && $chatId){ if (!($this->isAdmin($chatId,$userId) && $this->isBotAdmin($chatId))) { http_response_code(403); echo json_encode(['ok'=>false]); return; } }
		if (!$this->checkForceJoin($userId)) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'force_join_required']); return; }

		if ($path==='/api/settings' && $method==='GET'){ header('Content-Type: application/json'); echo json_encode(['ok'=>true,'settings'=>$this->getSettings($chatId)]); return; }
		if ($path==='/api/settings' && $method==='POST'){ $updates=json_decode(file_get_contents('php://input'), true)??[]; unset($updates['chat_id']); foreach($updates as $k=>$v){ $this->setSetting($chatId,$k,$v); } header('Content-Type: application/json'); echo json_encode(['ok'=>true,'settings'=>$this->getSettings($chatId)]); return; }
		if ($path==='/api/sanctions' && $method==='GET'){ header('Content-Type: application/json'); $st=$this->pdo->prepare('SELECT * FROM sanctions WHERE chat_id=? ORDER BY id DESC LIMIT 200'); $st->execute([$chatId]); echo json_encode(['ok'=>true,'sanctions'=>$st->fetchAll()]); return; }
		if ($path==='/api/admins' && $method==='GET'){ header('Content-Type: application/json'); $res=$this->call('getChatAdministrators',['chat_id'=>$chatId]); echo json_encode(['ok'=>true,'admins'=>$res['result']??[]]); return; }
		if ($path==='/api/admins/set' && $method==='POST'){ $in=json_decode(file_get_contents('php://input'), true)??[]; $target=(int)($in['user_id']??0); $rights=$in['rights']??[]; $this->call('promoteChatMember', array_merge(['chat_id'=>$chatId,'user_id'=>$target], $rights)); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); return; }
		if ($path==='/api/owner/state' && $method==='GET'){ if($userId!==(int)($_ENV['BOT_OWNER_ID']??0)){ http_response_code(403); echo json_encode(['ok'=>false]); return; } header('Content-Type: application/json'); echo json_encode(['ok'=>true,'state'=>$this->getBotState()]); return; }
		if ($path==='/api/owner/state' && $method==='POST'){ if($userId!==(int)($_ENV['BOT_OWNER_ID']??0)){ http_response_code(403); echo json_encode(['ok'=>false]); return; } $in=json_decode(file_get_contents('php://input'), true)??[]; $this->setBotState($in); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'state'=>$this->getBotState()]); return; }
		if ($path==='/api/owner/broadcast' && $method==='POST'){ if($userId!==(int)($_ENV['BOT_OWNER_ID']??0)){ http_response_code(403); echo json_encode(['ok'=>false]); return; } $in=json_decode(file_get_contents('php://input'), true)??[]; $msg=trim((string)($in['text']??'')); foreach($this->listChats() as $c){ $this->sendMessage((int)$c['chat_id'],$msg); usleep(150000);} header('Content-Type: application/json'); echo json_encode(['ok'=>true]); return; }

		http_response_code(404); echo 'Not found';
	}
}

// Entrypoint: CLI bot or Web
if (php_sapi_name()==='cli') {
	$app=new App();
	$app->runBot();
	exit;
}
// If included from public/index.php
if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME']??'')) {
	$app=new App();
	$app->handleWeb();
}