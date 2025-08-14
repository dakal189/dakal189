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
	public function migrate(string $schemaPath): void {
		$sql = file_get_contents($schemaPath);
		$this->pdo->exec($sql);
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
	public function isAdmin(int $chatId, int $userId): bool { try { $res=$this->call('getChatMember',['chat_id'=>$chatId,'user_id'=>$userId]); $s=$res['result']['status']??''; return in_array($s,['creator','administrator'],true);} catch(\Throwable $e){return false;} }
	public function authOkForWeb(int $chatId, int $userId, int $ts, string $hash): bool { $secret=$_ENV['WEB_APP_SECRET']??'devsecret'; $calc=hash_hmac('sha256', $chatId.':'.$userId.':'.$ts, $secret); if (!hash_equals($calc, $hash)) return false; return true; }
	public function checkForceJoin(int $userId): bool {
		$state=$this->getBotState(); $ch=(int)($state['force_channel_id']??0); if($ch===0) return true; try { $r=$this->call('getChatMember',['chat_id'=>$ch,'user_id'=>$userId]); $st=$r['result']['status']??'left'; return !in_array($st, ['left','kicked'], true); } catch(\Throwable $e){ return false; }
	}

	// Filters
	public static function containsLink(?string $text): bool { if(!$text) return false; return (bool)preg_match('/(https?:\/\/|t\.me\/)\S+/i',$text); }
	public static function containsBadWord(?string $text): bool { if(!$text) return false; $text=mb_strtolower($text); foreach(['fuck','shit','bitch','asshole','faggot','nigger','slut','whore','porn','xxx','rape','sex'] as $w){ if (str_contains($text,$w)) return true; } return false; }

	// Bot logic
	public function runBot(): void {
		$this->migrate(__DIR__.'/schema.sql');
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
	}
	private function handleCallback(array $cq): void {
		$data=$cq['data']??''; $chatId=$cq['message']['chat']['id']??null; $userId=$cq['from']['id']??null; if(!$chatId||!$userId) return;
		if (str_starts_with($data,'captcha:')){ $target=(int)substr($data,8); if($target===(int)$userId){ $this->restrict($chatId,$userId,['can_send_messages'=>true,'can_send_audios'=>true,'can_send_documents'=>true,'can_send_photos'=>true,'can_send_videos'=>true,'can_send_video_notes'=>true,'can_send_voice_notes'=>true,'can_send_polls'=>true,'can_send_other_messages'=>true]); $this->call('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'تایید شد. خوش آمدید!']); } }
	}
	private function handleMessage(array $m): void {
		$chat=$m['chat']??[]; $chatId=(int)($chat['id']??0); $type=$chat['type']??'private'; $title=$chat['title']??null; $from=$m['from']??[]; $userId=(int)($from['id']??0); if(!$chatId||!$userId) return;
		$this->upsertGroup($chatId,$type,$title);
		$ownerId=(int)($_ENV['BOT_OWNER_ID']??0);

		if ($type==='private' && isset($m['text']) && $m['text']==='/start') { $this->sendMessage($chatId,'سلام! من ربات مدیریت گروه گاردین هستم. من را به گروه/کانال خود اضافه کنید.'); return; }

		$state=$this->getBotState(); if ((int)$state['disabled']===1 && $userId!==$ownerId) return;

		$settings = $this->getSettings($chatId);

		// Admin betrayal detection: admin removing members
		if (!empty($m['left_chat_member'])) {
			if ($this->isAdmin($chatId,$userId)) {
				$cnt = $this->incrAdminRemoval($chatId,$userId);
				if ($cnt > 10) { $this->demoteAdmin($chatId,$userId); $this->sendMessage($chatId, "ادمین {$userId} بیش از حد حذف انجام داده و دسترسی او گرفته شد."); $this->logSanction($chatId,$userId,'demote','mass removals'); }
			}
			return;
		}

		// New members welcome with banner
		if (!empty($m['new_chat_members'])) {
			if ((int)($settings['captcha_required']??1)===1) {
				foreach($m['new_chat_members'] as $mem){ $this->restrict($chatId,(int)$mem['id'],['can_send_messages'=>false]); $this->sendMessage($chatId,$mem['first_name'].' خوش آمدی! برای تایید روی دکمه بزنید.', ['reply_markup'=>['inline_keyboard'=>[[['text'=>'من ربات نیستم 🤖❌','callback_data'=>'captcha:'.$mem['id']]]]]]); }
			}
			$banner=$settings['welcome_banner_url']??''; $wtext=$settings['welcome_text']??''; if($banner){ $this->sendPhoto($chatId,$banner,['caption'=>$wtext?:'خوش آمدید']); } elseif($wtext){ $this->sendMessage($chatId,$wtext); }
			return;
		}

		// Forwarded
		if (!empty($m['forward_date']) && (int)($settings['anti_forward']??1)===1) { $this->deleteMessage($chatId,(int)$m['message_id']); $this->sendMessage($chatId,'فوروارد پیام مجاز نیست.'); return; }

		// Commands
		if (!empty($m['text']) && str_starts_with($m['text'], '/')) { $this->handleCommand($m,$settings); return; }

		// Ignore admins for content filters
		if ($this->isAdmin($chatId,$userId)) return;

		$text=$m['text']??$m['caption']??'';
		if ((int)($settings['lockdown']??0)===1) { $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if ((int)($settings['anti_link']??1)===1 && self::containsLink($text)) { $this->warnAndMaybeMute($chatId,$userId,'ارسال لینک ممنوع است'); $this->deleteMessage($chatId,(int)$m['message_id']); return; }
		if ((int)($settings['anti_badwords']??1)===1 && self::containsBadWord($text)) { $this->warnAndMaybeMute($chatId,$userId,'کلمات نامناسب ممنوع است'); $this->deleteMessage($chatId,(int)$m['message_id']); return; }
	}
	private function handleCommand(array $m, array $settings): void {
		$chatId=(int)$m['chat']['id']; $fromId=(int)$m['from']['id']; $text=trim($m['text']); $cmd=explode(' ',$text); $command=explode('@',ltrim($cmd[0],'/'))[0]; $ownerId=(int)($_ENV['BOT_OWNER_ID']??0);
		$forceOk=$this->checkForceJoin($fromId); if(!$forceOk && $command!=='help'){ $this->sendMessage($chatId,'برای استفاده ابتدا در کانال تعیین‌شده عضو شوید.'); return; }
		switch($command){
			case 'help': $this->sendMessage($chatId,"دستورات:\n/settings\n/warn\n/mute\n/ban\n/unban\n/lockdown on|off\nshutdown on|off (owner)\n/broadcast <متن> (owner)\n/fwd (owner, reply)"); break;
			case 'settings': $ts=time(); $hash=hash_hmac('sha256',$chatId.':'.$fromId.':'.$ts, $_ENV['WEB_APP_SECRET']??'devsecret'); $url=rtrim($_ENV['WEB_ORIGIN']??'http://localhost:8080','/')."/?chat_id={$chatId}&user_id={$fromId}&timestamp={$ts}&hash={$hash}"; $this->sendMessage($chatId,"پنل مدیریت: {$url}"); break;
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
		$this->migrate(__DIR__.'/schema.sql');
		header('Cache-Control: no-store');
		$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)?:'/'; $method=$_SERVER['REQUEST_METHOD']??'GET';
		if ($path==='/' && $method==='GET'){ header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/public/index.html'); return; }
		if ($path==='/api/auth' && $method==='POST'){ $in=json_decode(file_get_contents('php://input'), true)??[]; $ok=$this->authOkForWeb((int)($in['chat_id']??0),(int)($in['user_id']??0),(int)($in['timestamp']??0), (string)($in['hash']??'')); header('Content-Type: application/json'); echo json_encode(['ok'=>$ok]); return; }
		// All below require auth params
		$chatId=(int)($_GET['chat_id'] ?? ($_POST['chat_id'] ?? 0)); $userId=(int)($_GET['user_id'] ?? ($_POST['user_id'] ?? 0)); $ts=(int)($_GET['timestamp'] ?? ($_POST['timestamp'] ?? 0)); $hash=(string)($_GET['hash'] ?? ($_POST['hash'] ?? ''));
		if (!$this->authOkForWeb($chatId,$userId,$ts,$hash)) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'unauthorized']); return; }
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