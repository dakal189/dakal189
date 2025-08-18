<?php
declare(strict_types=1);

/*
 Single-file Samp Info Bot (PHP + MySQL)
 Features: i18n (fa/en/ru), force-join, main menu (skins only demo), deep-link, like/share/favorite, sponsors tail, admin /panel entry
 Extend similarly for vehicles/colors/weather/objects/weapons/maps.

 ENV (put in server env or inline constants):
  BOT_TOKEN, BOT_USERNAME, WEBHOOK_SECRET,
  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
  DEFAULT_LANG=fa, FORCE_JOIN_REQUIRED=true
*/

// ---------- CONFIG ----------
const DEFAULT_LANG = 'fa';
const FORCE_JOIN_REQUIRED = true; // can be overridden by env

function env(string $k, ?string $def=null): ?string { $v = getenv($k); return $v===false? $def : $v; }
function cfg_bool(string $k, bool $def): bool { $v = env($k); return $v===null? $def : filter_var($v, FILTER_VALIDATE_BOOLEAN); }

$BOT_TOKEN = env('BOT_TOKEN','');
$BOT_USERNAME = env('BOT_USERNAME','');
$WEBHOOK_SECRET = env('WEBHOOK_SECRET','');
$DB_HOST = env('DB_HOST','127.0.0.1');
$DB_PORT = (int)env('DB_PORT','3306');
$DB_NAME = env('DB_NAME','samp_bot');
$DB_USER = env('DB_USER','root');
$DB_PASS = env('DB_PASS','');
$DEF_LANG = env('DEFAULT_LANG', DEFAULT_LANG);
$FORCE_JOIN = cfg_bool('FORCE_JOIN_REQUIRED', FORCE_JOIN_REQUIRED);

if ($BOT_TOKEN === '') { http_response_code(500); echo 'BOT_TOKEN missing'; exit; }

// ---------- DB ----------
function db(): PDO {
    static $pdo=null; global $DB_HOST,$DB_PORT,$DB_NAME,$DB_USER,$DB_PASS;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $DB_HOST,$DB_PORT,$DB_NAME);
    $pdo = new PDO($dsn,$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id BIGINT PRIMARY KEY, lang VARCHAR(2) NOT NULL DEFAULT 'fa', is_admin TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(64) PRIMARY KEY, `value` TEXT NOT NULL);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS force_channels (id BIGINT AUTO_INCREMENT PRIMARY KEY, chat_id BIGINT NOT NULL, username VARCHAR(64), active TINYINT(1) NOT NULL DEFAULT 1);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sponsors (id BIGINT AUTO_INCREMENT PRIMARY KEY, chat_id BIGINT NOT NULL, username VARCHAR(64), active TINYINT(1) NOT NULL DEFAULT 1);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (user_id BIGINT PRIMARY KEY, is_super TINYINT(1) NOT NULL DEFAULT 0);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS skins (id INT PRIMARY KEY, name VARCHAR(128), `group` VARCHAR(128), model VARCHAR(128), story TEXT NULL, photo_file_id VARCHAR(256) NULL, search_count INT NOT NULL DEFAULT 0, like_count INT NOT NULL DEFAULT 0);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS likes (user_id BIGINT NOT NULL, entity ENUM('skin','vehicle','color','weather','object','weapon','map') NOT NULL, entity_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, entity, entity_id));");
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (user_id BIGINT NOT NULL, entity ENUM('skin','vehicle','color','weather','object','weapon','map') NOT NULL, entity_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, entity, entity_id));");
}

// ---------- I18N ----------
function t_catalog(string $lang): array {
    return match($lang){
        'en' => [
            'choose_language' => 'Please choose your language:',
            'language_set' => 'Language set to: {lang}',
            'force_join' => 'To use the bot, please join channels below and tap "Check membership".',
            'check_membership' => '🔄 Check membership',
            'main_menu' => 'Main menu',
            'menu.skins' => '🧍 Skins',
            'prompt.choose_search' => 'Choose one:',
            'search.by_id' => '🔎 Search by ID',
            'search.by_name' => '🔎 Search by name',
            'prompt.search_by_id' => 'Send the ID:',
            'prompt.search_by_name' => 'Send the name:',
            'not_found' => 'No result found.',
            'button.like' => '❤️ Like ({count})',
            'button.share' => '🔁 Share',
            'button.fav_add' => '⭐ Add to favorites',
            'button.fav_remove' => '❌ Remove from favorites',
            'panel.title' => 'Admin panel',
            'panel.skins' => '🧍 Manage skins',
            'panel.back' => '🔙 Back',
            'share.caption' => '— Sponsors: {sponsors}',
        ],
        'ru' => [
            'choose_language' => 'Пожалуйста, выберите язык:',
            'language_set' => 'Язык установлен: {lang}',
            'force_join' => 'Чтобы использовать бота, вступите в каналы и нажмите «Проверить подписку».',
            'check_membership' => '🔄 Проверить подписку',
            'main_menu' => 'Главное меню',
            'menu.skins' => '🧍 Скины',
            'prompt.choose_search' => 'Выберите:',
            'search.by_id' => '🔎 Поиск по ID',
            'search.by_name' => '🔎 Поиск по названию',
            'prompt.search_by_id' => 'Отправьте ID:',
            'prompt.search_by_name' => 'Отправьте название:',
            'not_found' => 'Ничего не найдено.',
            'button.like' => '❤️ Лайк ({count})',
            'button.share' => '🔁 Поделиться',
            'button.fav_add' => '⭐ В избранное',
            'button.fav_remove' => '❌ Удалить из избранного',
            'panel.title' => 'Панель админа',
            'panel.skins' => '🧍 Управление скинами',
            'panel.back' => '🔙 Назад',
            'share.caption' => '— Спонсоры: {sponsors}',
        ],
        default => [
            'choose_language' => 'لطفا زبان را انتخاب کنید:',
            'language_set' => 'زبان تنظیم شد: {lang}',
            'force_join' => 'برای استفاده از ربات، لطفاً در کانال‌های زیر عضو شوید و سپس روی «بررسی عضویت» بزنید.',
            'check_membership' => '🔄 بررسی عضویت',
            'main_menu' => 'منوی اصلی',
            'menu.skins' => '🧍 اسکین‌ها',
            'prompt.choose_search' => 'یکی را انتخاب کنید:',
            'search.by_id' => '🔎 جستجو بر اساس ID',
            'search.by_name' => '🔎 جستجو بر اساس نام',
            'prompt.search_by_id' => 'شناسه را ارسال کنید:',
            'prompt.search_by_name' => 'نام را ارسال کنید:',
            'not_found' => 'موردی یافت نشد.',
            'button.like' => '❤️ لایک ({count})',
            'button.share' => '🔁 اشتراک‌گذاری',
            'button.fav_add' => '⭐ افزودن به علاقه‌مندی',
            'button.fav_remove' => '❌ حذف از علاقه‌مندی',
            'panel.title' => 'پنل مدیریت',
            'panel.skins' => '🧍 مدیریت اسکین‌ها',
            'panel.back' => '🔙 بازگشت',
            'share.caption' => '— اسپانسر: {sponsors}',
        ],
    };
}

function t(string $key, array $params=[]): string {
    $lang = user_lang();
    $catalog = t_catalog($lang);
    $val = $catalog[$key] ?? $key;
    foreach ($params as $k=>$v) { $val = str_replace('{'.$k.'}', (string)$v, $val); }
    return $val;
}

// ---------- HTTP guard ----------
$secret = $_GET['secret'] ?? '';
if ($WEBHOOK_SECRET && $secret !== $WEBHOOK_SECRET) { http_response_code(403); echo 'forbidden'; exit; }

$raw = file_get_contents('php://input') ?: '';
if ($raw === '') { echo 'ok'; exit; }
$update = json_decode($raw, true);

// ---------- Telegram client ----------
function tg(string $method, array $params=[]): array {
    global $BOT_TOKEN; $url = 'https://api.telegram.org/bot'.$BOT_TOKEN.'/'.$method;
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($params, JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); if ($res===false) throw new RuntimeException('curl error');
    $data = json_decode($res,true); if (!($data['ok']??false)) throw new RuntimeException('tg error: '.($data['description']??'unknown'));
    return $data['result'];
}

// ---------- User/lang/state ----------
function user_ensure(int $uid): array {
    $pdo = db(); global $DEF_LANG;
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$uid]); $row=$st->fetch();
    if ($row) return $row;
    $pdo->prepare('INSERT INTO users (id, lang, is_admin) VALUES (?, ?, 0)')->execute([$uid, $DEF_LANG]);
    return ['id'=>$uid,'lang'=>$DEF_LANG,'is_admin'=>0];
}
function user_lang(): string {
    static $lang=null; if ($lang) return $lang;
    // fallback: default
    return $lang ?? 'fa';
}
function user_set_lang(int $uid, string $lang): void { db()->prepare('UPDATE users SET lang=? WHERE id=?')->execute([$lang,$uid]); }
function user_get_lang(int $uid): string { $st=db()->prepare('SELECT lang FROM users WHERE id=?'); $st->execute([$uid]); return $st->fetchColumn() ?: 'fa'; }
function set_state(int $uid, ?string $state): void { $k='state:'.$uid; $pdo=db(); if ($state===null){ $pdo->prepare('DELETE FROM settings WHERE `key`=?')->execute([$k]); return; } $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$k,$state]); }
function get_state(int $uid): ?string { $st=db()->prepare('SELECT `value` FROM settings WHERE `key`=?'); $st->execute(['state:'.$uid]); $r=$st->fetch(); return $r['value']??null; }

// ---------- Admin/force/sponsors ----------
function is_admin(int $uid): bool { $st=db()->prepare('SELECT 1 FROM admins WHERE user_id=?'); $st->execute([$uid]); return (bool)$st->fetchColumn(); }
function force_channels(): array { $st=db()->query('SELECT chat_id, username FROM force_channels WHERE active=1'); return $st->fetchAll() ?: []; }
function sponsors_tail(): string { $st=db()->query('SELECT username FROM sponsors WHERE active=1'); $rows=$st->fetchAll(); $names=[]; foreach ($rows as $r){ if(!empty($r['username'])) $names[]='@'.$r['username']; } return implode(' ', $names); }

function need_force_join(int $uid): bool {
    global $FORCE_JOIN; if (!$FORCE_JOIN) return false; $chs=force_channels(); if (!$chs) return false;
    foreach ($chs as $ch){ try { $res = tg('getChatMember', ['chat_id'=>$ch['chat_id'],'user_id'=>$uid]); $s=$res['status']??''; if (!in_array($s,['member','administrator','creator'],true)) return true; } catch (Throwable $e){ return true; } }
    return false;
}

function kb_main(string $lang): array {
    $cat = t_catalog($lang);
    return ['keyboard'=>[
        [ ['text'=>$cat['menu.skins']] ],
    ], 'resize_keyboard'=>true];
}
function kb_choose_search(string $lang): array { $c=t_catalog($lang); return ['keyboard'=>[ [ ['text'=>$c['search.by_id']], ['text'=>$c['search.by_name']] ] ], 'resize_keyboard'=>true]; }
function ik_force_join(string $lang, array $chs): array { $c=t_catalog($lang); $rows=[]; foreach($chs as $ch){ if(!empty($ch['username'])){ $rows[]=[ ['text'=>'@'.$ch['username'],'url'=>'https://t.me/'.$ch['username']] ]; } } $rows[]=[ ['text'=>$c['check_membership'],'callback_data'=>cb('check_membership','sys',0)] ]; return ['inline_keyboard'=>$rows]; }
function ik_actions(string $lang, string $entity, int $id, int $likeCount, bool $fav): array { $c=t_catalog($lang); $favText=$fav?$c['button.fav_remove']:$c['button.fav_add']; return ['inline_keyboard'=>[[ ['text'=>str_replace('{count}',(string)$likeCount,$c['button.like']),'callback_data'=>cb('like',$entity,$id)], ['text'=>$c['button.share'],'callback_data'=>cb('share',$entity,$id)], ['text'=>$favText,'callback_data'=>cb('fav',$entity,$id)] ]]]; }
function cb(string $action,string $entity,int $id): string { $nonce=substr(bin2hex(random_bytes(2)),0,4); return '1|'.$action.'|'.$entity.'|'.$id.'|'.$nonce; }

// ---------- Domain: skins ----------
function skin_find_by_id(int $id): ?array { $st=db()->prepare('SELECT * FROM skins WHERE id=?'); $st->execute([$id]); $r=$st->fetch(); return $r?:null; }
function skin_find_by_name(string $name): ?array { $st=db()->prepare('SELECT * FROM skins WHERE name LIKE ? LIMIT 1'); $st->execute(['%'.$name.'%']); $r=$st->fetch(); return $r?:null; }
function skin_inc_search(int $id): void { db()->prepare('UPDATE skins SET search_count=search_count+1 WHERE id=?')->execute([$id]); }
function skin_inc_like(int $id): void { db()->prepare('UPDATE skins SET like_count=like_count+1 WHERE id=?')->execute([$id]); }

// ---------- Likes/Favorites ----------
function like_add(int $uid, string $entity, int $eid): bool { $st=db()->prepare('INSERT IGNORE INTO likes (user_id, entity, entity_id) VALUES (?,?,?)'); $st->execute([$uid,$entity,$eid]); return $st->rowCount()>0; }
function fav_toggle(int $uid, string $entity, int $eid): void { if (fav_exists($uid,$entity,$eid)){ db()->prepare('DELETE FROM favorites WHERE user_id=? AND entity=? AND entity_id=?')->execute([$uid,$entity,$eid]); return; } db()->prepare('INSERT INTO favorites (user_id, entity, entity_id) VALUES (?,?,?)')->execute([$uid,$entity,$eid]); }
function fav_exists(int $uid, string $entity, int $eid): bool { $st=db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND entity=? AND entity_id=?'); $st->execute([$uid,$entity,$eid]); return (bool)$st->fetchColumn(); }

// ---------- Handlers ----------
try {
    if (isset($update['callback_query'])) { handle_cb($update['callback_query']); echo 'ok'; exit; }
    if (isset($update['message'])) { handle_msg($update['message']); echo 'ok'; exit; }
} catch (Throwable $e) { error_log('ERR: '.$e->getMessage()); echo 'ok'; }

function handle_msg(array $m): void {
    global $DEF_LANG, $BOT_USERNAME;
    $chatId = (int)$m['chat']['id'];
    $uid = (int)($m['from']['id'] ?? $chatId);
    $text = trim((string)($m['text'] ?? ''));

    $u = user_ensure($uid);
    $lang = user_get_lang($uid);

    // commands
    if (isset($m['entities'])) {
        foreach ($m['entities'] as $ent) {
            if (($ent['type'] ?? '') === 'bot_command') {
                $cmd = substr($text, $ent['offset'], $ent['length']);
                $payload = null;
                if (str_starts_with($text, '/start') && str_contains($text, ' ')) {
                    $payload = trim(substr($text, strpos($text, ' ')));
                }
                if ($cmd === '/start') {
                    if (need_force_join($uid)) {
                        $chs = force_channels();
                        tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('force_join'),'reply_markup'=>json_encode(ik_force_join($lang,$chs), JSON_UNESCAPED_UNICODE)]);
                        return;
                    }
                    if ($payload) { dispatch_payload($chatId,$uid,$payload,$lang); return; }
                    tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('main_menu'),'reply_markup'=>json_encode(kb_main($lang), JSON_UNESCAPED_UNICODE)]);
                    return;
                }
                if ($cmd === '/panel') { if (!is_admin($uid)) return; tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('panel.title')]); return; }
                if ($cmd === '/lang') {
                    tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('choose_language'),'reply_markup'=>json_encode(['keyboard'=>[[['text'=>'فارسی'],['text'=>'English'],['text'=>'Русский']]],'resize_keyboard'=>true,'one_time_keyboard'=>true], JSON_UNESCAPED_UNICODE)]);
                    return;
                }
            }
        }
    }

    // language selection by plain text
    if (in_array($text, ['فارسی','English','Русский'], true)) {
        $set = $text==='English' ? 'en' : ($text==='Русский' ? 'ru' : 'fa');
        user_set_lang($uid, $set);
        $lang = $set;
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>str_replace('{lang}',$lang,t('language_set')),'reply_markup'=>json_encode(kb_main($lang), JSON_UNESCAPED_UNICODE)]);
        return;
    }

    // menu: skins
    if ($text === t('menu.skins')) {
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('prompt.choose_search'),'reply_markup'=>json_encode(kb_choose_search($lang), JSON_UNESCAPED_UNICODE)]);
        set_state($uid, 'skins_menu');
        return;
    }

    $state = get_state($uid);
    if ($state === 'skins_menu') {
        if ($text === t('search.by_id')) { set_state($uid,'skins_wait_id'); tg('sendMessage',['chat_id'=>$chatId,'text'=>t('prompt.search_by_id')]); return; }
        if ($text === t('search.by_name')) { set_state($uid,'skins_wait_name'); tg('sendMessage',['chat_id'=>$chatId,'text'=>t('prompt.search_by_name')]); return; }
    }
    if ($state === 'skins_wait_id') {
        $id = (int)$text; $skin = skin_find_by_id($id); if (!$skin){ tg('sendMessage',['chat_id'=>$chatId,'text'=>t('not_found')]); return; }
        send_skin($chatId, $uid, $skin, $lang); set_state($uid,null); return;
    }
    if ($state === 'skins_wait_name') {
        $skin = skin_find_by_name($text); if (!$skin){ tg('sendMessage',['chat_id'=>$chatId,'text'=>t('not_found')]); return; }
        send_skin($chatId, $uid, $skin, $lang); set_state($uid,null); return;
    }

    // fallback main menu
    tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('main_menu'),'reply_markup'=>json_encode(kb_main($lang), JSON_UNESCAPED_UNICODE)]);
}

function handle_cb(array $cb): void {
    global $BOT_USERNAME;
    $data = $cb['data'] ?? ''; $uid = (int)($cb['from']['id'] ?? 0); $chatId=(int)($cb['message']['chat']['id'] ?? 0); $messageId=(int)($cb['message']['message_id'] ?? 0);
    $parts = explode('|',$data); if (count($parts) < 5) return; [$ver,$action,$entity,$id] = $parts; $id=(int)$id;
    $lang = user_get_lang($uid);

    if ($action==='check_membership') {
        if (need_force_join($uid)) return;
        tg('editMessageText', ['chat_id'=>$chatId,'message_id'=>$messageId,'text'=>t('main_menu'),'reply_markup'=>json_encode(kb_main($lang), JSON_UNESCAPED_UNICODE)]);
        return;
    }

    if ($entity==='skin') {
        if ($action==='like') {
            if (like_add($uid,'skin',$id)) skin_inc_like($id);
            $skin = skin_find_by_id($id); if ($skin){ $isFav=fav_exists($uid,'skin',$id); tg('editMessageReplyMarkup',['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(ik_actions($lang,'skin',$id,(int)$skin['like_count'],$isFav), JSON_UNESCAPED_UNICODE)]);} return;
        }
        if ($action==='fav') {
            fav_toggle($uid,'skin',$id); $skin=skin_find_by_id($id); if ($skin){ $isFav=fav_exists($uid,'skin',$id); tg('editMessageReplyMarkup',['chat_id'=>$chatId,'message_id'=>$messageId,'reply_markup'=>json_encode(ik_actions($lang,'skin',$id,(int)$skin['like_count'],$isFav), JSON_UNESCAPED_UNICODE)]);} return;
        }
        if ($action==='share') {
            $deep = 'https://t.me/'.$BOT_USERNAME.'?start=item_skin_'.$id;
            tg('answerCallbackQuery', ['callback_query_id'=>$cb['id'], 'text'=>$deep, 'show_alert'=>false]);
            return;
        }
    }
}

function send_skin(int $chatId, int $uid, array $skin, string $lang): void {
    $caption = $skin['name']."\nID: ".$skin['id']."\nGroup: ".$skin['group']."\nModel: ".$skin['model'];
    if (!empty($skin['story'])) { $caption .= "\n\n\"".$skin['story']."\""; }
    $tail = sponsors_tail(); if ($tail) { $caption .= "\n\n".str_replace('{sponsors}', $tail, t('share.caption')); }
    $isFav = fav_exists($uid,'skin',(int)$skin['id']);
    $markup = ik_actions($lang,'skin',(int)$skin['id'],(int)$skin['like_count'],$isFav);
    if (!empty($skin['photo_file_id'])) {
        tg('sendPhoto', ['chat_id'=>$chatId,'photo'=>$skin['photo_file_id'],'caption'=>$caption,'reply_markup'=>json_encode($markup, JSON_UNESCAPED_UNICODE)]);
    } else {
        tg('sendMessage', ['chat_id'=>$chatId,'text'=>$caption,'reply_markup'=>json_encode($markup, JSON_UNESCAPED_UNICODE)]);
    }
    skin_inc_search((int)$skin['id']);
}

function dispatch_payload(int $chatId,int $uid,string $payload,string $lang): void {
    if (str_starts_with($payload,'item_skin_')) { $id=(int)substr($payload, strlen('item_skin_')); $skin=skin_find_by_id($id); if ($skin){ send_skin($chatId,$uid,$skin,$lang); return; } }
    tg('sendMessage', ['chat_id'=>$chatId,'text'=>t('not_found')]);
}

