<?php
error_reporting(0);
set_time_limit(5);
date_default_timezone_set('Asia/Tehran');
##----------------------
require 'config.php';
##----------------------
if (!is_dir('Data')) {
	mkdir('Data');
}
if (!is_file('Data/ads.json')) {
	file_put_contents('Data/ads.json', json_encode([]));
}
if (!is_dir('Bots')) {
	mkdir('Bots');
}
##----------------------
function CreateZip($files = array(), $destination, $password = null, $overwrite = false)
{
	if (file_exists($destination)) {
		return false;
	}
	$valid_files = array();
	if (is_array($files)) {
		foreach($files as $file) {
			if (file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	if (count($valid_files)) {
		$zip = new ZipArchive();
		if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		if (!is_null($password)) {
			$zip->setPassword($password);
		}
		foreach($valid_files as $file) {
			$zip->addFile($file, basename($file));
			if (!is_null($password)) {
				$zip->setEncryptionName(basename($file), ZipArchive::EM_AES_256);
			}
		}
		$zip->close();
		return file_exists($destination);
	} else {
		return false;
	}
}
##----------------------
function makeInlineKeyboard($text)
{
	$keyboard = [];
	$explode = explode("\n", $text);
	$i = 0; $j = 0;
	foreach ($explode as $values) {
		$value = explode(', ', $values);
		foreach ($value as $inline) {
			preg_match('#(.+?)\|(.+)#i', $inline, $matches);
			if (filter_var($matches[2], FILTER_VALIDATE_URL)) {
				$keyboard[$i][$j]['text'] = $matches[1];
				$keyboard[$i][$j]['url'] = $matches[2];
			}
			$keyboard[$i][$j] = array_reverse($keyboard[$i][$j]);
			$j++;
		}
		$keyboard[$i] = array_reverse($keyboard[$i]);
		$i++;
		$j = 0;
	}
	if ($keyboard != null) return ['inline_keyboard' => $keyboard];
	return null;
}
##----------------------
function convert_size($size)
{
    $unit = [
	'بایت',
	'کیلوبایت',
	'مگابایت',
	'گیگابایت',
	'ترابایت',
	'پنتابایت'
    ];
    $i = 0;
    return @round($size/pow(1024, ($i=(int)floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}
##----------------------
function convert($string)
{
	$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	$arabic = ['٩', '٨', '٧', '٦', '٥', '٤', '٣', '٢', '١', '٠'];
	$num = range(0, 9);
	$string = str_replace($persian, $num, $string);
	return str_replace($arabic, $num, $string);
}
##----------------------
function bot($method, $data = [], $bot_token = API_KEY_CR)
{
	$ch = curl_init('https://api.telegram.org/bot' . $bot_token . '/' . $method);
	curl_setopt_array($ch,
	[
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => $data
	]);
	$result = curl_exec($ch);
	curl_close($ch);
	return (!empty($result) ? json_decode($result) : false);
}
##----------------------
function sendAction($chat_id, $action = 'typing')
{
	return bot('sendChatAction', [
		'chat_id' => $chat_id,
		'action' => $action
	]);
}
##----------------------
function sendMessage($chat_id, $text, $mode = null, $reply = null, $keyboard = null)
{
	return bot('sendMessage', [
		'chat_id' => $chat_id,
		'text' => $text,
		'parse_mode' => $mode,
		'reply_to_message_id' => $reply,
		'reply_markup' => $keyboard,
		'disable_web_page_preview' => true
	]);
}
##----------------------
function sendDocument($chatid, $document, $caption = null)
{
	return bot('sendDocument', [
		'chat_id' => $chatid,
		'document' => $document,
		'caption' => $caption,
		'parse_mode' => 'html'
	]);
}
##----------------------
function forwardMessage($chatid, $from_id, $massege_id)
{
	return bot('forwardMessage', [
		'chat_id' => $chatid,
		'from_chat_id' => $from_id,
		'message_id' => $massege_id
	]);
}
##----------------------
function getChat($chatid)
{
	return bot('getChat', [
		'chat_id' => $chatid
	]);
}
##----------------------
function myFloor($num) {
	if ($num == floor($num)) {
		return floor($num)-1;
	}
	else {
		return floor($num);
	}
}
##----------------------
$update = json_decode(file_get_contents('php://input'));
if (isset($update->message)) {
	$message = $update->message; 
	$chat_id = $message->chat->id;
	$text = $message->text;
	$message_id = $message->message_id;
	$from_id = $message->from->id;
	$user_id = $from_id;
	$tc = $message->chat->type;
	$first_name = $message->from->first_name;
	$last_name = $message->from->last_name;
	$username = $message->from->username;
	$caption = $message->caption;
	$reply = $message->reply_to_message->forward_from->id;
	$reply_id = $message->reply_to_message->from->id;
}
elseif (isset($update->callback_query)) {
	$Data = $update->callback_query->data;
	$data_id = $update->callback_query->id;
	$chatid = $update->callback_query->message->chat->id;
	$chat_id = $update->callback_query->message->chat->id;
	$fromid = $update->callback_query->from->id;
	$from_id = $fromid;
	$first_name = $update->callback_query->from->first_name;
	$user_id = $fromid;
	$tccall = $update->callback_query->chat->type;
	$messageid = $update->callback_query->message->message_id;
	$message_id = $update->callback_query->message->message_id;
}
else {
	exit();
}
##----------------------
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
$pdo->exec('SET NAMES utf8mb4');


$pdo->exec("CREATE TABLE IF NOT EXISTS `members` (
        `id` INT(255) NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT(255) NOT NULL,
        `time` INT(255) NOT NULL,
        PRIMARY KEY (`id`)
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS `bots` (
        `id` int(255) NOT NULL AUTO_INCREMENT,
        `admin` BIGINT(255) NOT NULL,
        `username` varchar(1024),
	`token` varchar(1024),
	`time` int(255) NOT NULL,
        PRIMARY KEY (`id`)
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS `sendlist` (
        `id` int(255) NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT(255) NOT NULL,
        `message_id` int(255) DEFAULT NULL,
        `offset` int(255) NOT NULL,
        `time` int(255) NOT NULL,
        `type` varchar(255) NOT NULL,
        `data` json NOT NULL,
        `caption` varchar(1024) DEFAULT NULL,
        PRIMARY KEY (`id`)
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS `vip_bots` (
        `id` int(255) NOT NULL AUTO_INCREMENT,
        `admin` BIGINT(255) NOT NULL,
        `bot` varchar(1024),
	`start` int(255) NOT NULL,
	`end` int(255) NOT NULL,
        `alert` int(1) NOT NULL,
        PRIMARY KEY (`id`)
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS `bots_sendlist` (
        `id` int(255) NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT(255) NOT NULL,
	`token` varchar(255) NOT NULL,
	`bot_username` varchar(255) NOT NULL,
        `message_id` int(255) DEFAULT NULL,
        `offset` int(255) NOT NULL,
        `time` int(255) NOT NULL,
        `type` varchar(255) NOT NULL,
        `data` json NOT NULL,
        `caption` varchar(1024) DEFAULT NULL,
        PRIMARY KEY (`id`)
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS `xo_games` (
        `id` int(255) NOT NULL AUTO_INCREMENT,
        `message_id` varchar(255) NOT NULL,
        `start` int(255) NOT NULL,
	`time` int(255) NOT NULL,
	`bot` varchar(1024) NOT NULL,
        PRIMARY KEY (`id`)
);");

$db = $pdo->prepare("SELECT * FROM `members` WHERE `user_id`={$user_id}");
$db->execute();
if (!$db->fetch()) {
        $pdo->exec("INSERT INTO `members` (`user_id`, `time`) VALUES ({$user_id}, UNIX_TIMESTAMP());");
}
##----------------------JSON
@$list = json_decode(file_get_contents("Data/list.json"), true);
@$data = json_decode(file_get_contents("Data/$from_id/data.json"), true);
@$step = $data['step'];
##----------------------
if (!is_null($from_id) and !is_dir("Data/$from_id/")) {
	mkdir("Data/$from_id");
	$data = [
		'step' => 'none'
	];
	file_put_contents("Data/$from_id/data.json", json_encode($data));
	if ($list['user'] == null) {
		$list['user'] = [];
	}
	$list['user'][] = $from_id;
	file_put_contents('Data/list.json', json_encode($list));
}
##----------------------
if (!isset($list['bot_count'])) {
	$list['bot_count'] = 5;
	file_put_contents('Data/list.json', json_encode($list));
}
##----------------------
$get_in_channel_1 = json_decode(file_get_contents('https://api.telegram.org/bot' . API_KEY_CR . '/getChatMember?chat_id=' . $lock_channel_1 . '&user_id=' . $user_id));
$in_channel_1 = isset($get_in_channel_1->result->status) ? in_array($get_in_channel_1->result->status, ['creator', 'administrator', 'member']) : true;
$get_in_channel_2 = json_decode(file_get_contents('https://api.telegram.org/bot' . API_KEY_LOCK_BOT . '/getChatMember?chat_id=' . $lock_channel_2 . '&user_id=' . $user_id));
$in_channel_2 = isset($get_in_channel_2->result->status) ? in_array($get_in_channel_2->result->status, ['creator', 'administrator', 'member']) : true;
##----------------------Buttons
if ($from_id != $admin) {
	$menu = json_encode(
		[
			'keyboard' => [
				[['text'=>'🤖 ربات های من'],['text'=>'🔰 ساخت ربات']],
				[['text'=>'🌈 ثبت تبلیغ']],
				[['text'=>'📕 قوانین'],['text'=>'📖 راهنما']]
			],
			'resize_keyboard' => true
		]);
}
else {
	$menu = json_encode(
		[
			'keyboard' => [
				[['text'=>'🤖 ربات های من'],['text'=>'🔰 ساخت ربات']],
				[['text'=>'🌈 ثبت تبلیغ']],
				[['text'=>'📕 قوانین'],['text'=>'📖 راهنما']],
				[['text'=>'🔑 مدیریت']]
			],
			'resize_keyboard' => true
		]
	);
}

$ads_menu = json_encode(
	[
		'keyboard' => [
			[['text'=>'✏️ ثبت تبلیغ']],
			[['text'=>'🗒 لیست تبلیغات']],
			[['text'=>'🔙 بازگشت به مدیریت']]
		],
		'resize_keyboard' => true
	]
);
##----------------------Dev
$panel = json_encode(
	[
		'keyboard' => [
			[['text'=>'🔖 پیام همگانی'],['text'=>'🚀 هدایت همگانی']],
			[['text'=>'🤖 آمار ربات ها'],['text'=>'📊 آمار کاربران']],
			[['text'=>'⛔️ لیست کاربران مسدود']],
			[['text'=>'🔓 آزاد کردن'],['text'=>'🔒 مسدود کردن']],
			[['text'=>'🎖 لیست رباتهای ویژه']],
			[['text'=>'➖ اشتراک ویژه'],['text'=>'➕ اشتراک ویژه']],
			[['text'=>'✖️ حذف ربات'],['text'=>'🤖 تعداد مجاز']],
			[['text'=>'💠 تبلیغات']],
			[['text'=>'🔙 بازگشت']]
		],
		'resize_keyboard' => true
	]
);
##----------------------Other
$back = json_encode(
	[
		'keyboard' => [
			[['text'=>'🔙 بازگشت']]
		],
		'resize_keyboard' => true
	]
);
$backpanel = json_encode(
	[
		'keyboard' => [
			[['text'=>'🔙 بازگشت به مدیریت']]
		],
		'resize_keyboard' => true
	]
);
$backpanelads = json_encode(
	[
		'keyboard' => [
			[['text'=>'🔙 بازگشت به تبلیغات']]
		],
		'resize_keyboard' => true
	]
);
$remove = json_encode(
	[
		'KeyboardRemove' => [],
		'remove_keyboard' => true
	]
);
##----------------------
if (in_array($user_id, $list['ban'])) {
	exit();
}
##----------------------
if ($from_id != $admin) {
	if (time()-filectime('Data/flood.json') >= 50*60) {
		unlink('Data/flood.json');
	}

	@$flood = json_decode(file_get_contents('Data/flood.json'), true);
	$now = date('Y-m-d-h-i-a', $update->message->date);
	$flood['flood']["$now-$from_id"] += 1;
	file_put_contents('Data/flood.json', json_encode($flood));

	if ($flood['flood']["$now-$from_id"] >= 25 && $tc == 'private') {
		sendAction($chat_id);
		if ($list['ban'] == null) {
			$list['ban'] = [];
		}
		unlink('Data/flood.json');
		array_push($list['ban'], $from_id);
		file_put_contents("Data/list.json", json_encode($list));
		sendMessage($from_id, "⛔️ شما به دلیل ارسال پیام های مکرر و بیهوده مسدود گردیدید.\n\n🔰 برای آزاد شدن به $support پیام دهید.", 'markdown', null, $remove);
		sendMessage($admin, "👤 کاربر [$from_id](tg://user?id=$from_id) به دلیل ارسال پیام های مکرر و بیهوده از ربات مسدود گردید.\n/unban\_{$from_id}", 'markdown');
		exit();
	}
}
##----------------------
if (strtolower($text) == '/start') {
	sendAction($chat_id);
	sendMessage($chat_id, "😁✋🏻 سلام\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید.", null, $message_id, $menu);
	$data['step'] = "none";
	file_put_contents("Data/$from_id/data.json",json_encode($data));
}
elseif ($from_id != $admin && (!$in_channel_1 || !$in_channel_2)) {
	sendAction($chat_id);
        $lock_channel_1_emoji = $in_channel_1 ? '✅' : '❌';
        $lock_channel_2_emoji = $in_channel_2 ? '✅' : '❌';
	bot('sendMessage', [
		'chat_id'=>$chat_id,
		'reply_to_message_id'=>$message_id,
		'text'=>"🔰 لطفا برای حمایت از ما و گرفتن اجازه استفاده از ربات در کانال های زیر عضو شوید.

📣{$lock_channel_1_emoji} {$lock_channel_1}
📣{$lock_channel_2_emoji} {$lock_channel_2}",
		'reply_markup'=>json_encode(
			[
				'keyboard'=>[
					[['text' => '/start']]
				],
				'resize_keyboard'=>true
			]
		)
	]);
        exit();
}
elseif ($text == "🔙 بازگشت") {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("Data/$from_id/data.json",json_encode($data));
	sendMessage($chat_id, "🔰 به منوی اصلی خوش آمدید.\n\n👇🏻 یکی از گزینه های زیر را انتخاب کنید.", null, $message_id, $menu);
}
elseif ($text == "📕 قوانین") {
	sendAction($chat_id);
	sendMessage($chat_id, "📕 *قوانین* :

🔞 هرگونه *مسائل خلاف شرع و مستهجن* ممنوع است.
🚷 نقض *قوانین جمهوری اسلامی ایران* ممنوع است.
🚯 ارسال پیام های مکرر و بیهوده (*SPAM*)  ممنوع است.

⛔️ تخطی از موارد ذکر شده *مسدود شدن دائمی* شما را در پی خواهد داشت.", 'markdown', $message_id);
}
elseif ($text == "📖 راهنما" || strtolower($text) == '/help') {
	sendAction($chat_id);
	sendMessage($chat_id, "📖 آموزش ایجاد ربات پیامرسان :

1⃣ ابتدا به ربات @BotFather رفته و دستور /start را می فرستید.
2⃣ حالا برای ساخت یک ربات جدید دستور /newbot را می فرستید.
ربات پیام زیر را برای شما می فرستد :
Alright, a new bot. How are we going to call it? Please choose a name for your bot.
3⃣ یک نام برای ربات خود انتخاب کنید و بفرستید.
ربات در پاسخ پیام زیر را میفرستد :
Good. Now let's choose a username for your bot. It must end in bot. Like this, for example: TetrisBot or tetris_bot.
ربات در این پیام می گوید :« اکنون می بایست برای ربات خود یک نام کاربری انتخاب کنید. نام کاربری ای که انتخاب می کنید باید به کلمهٔ bot ختم شود. به عنوان مثال TetrisBot یا tetris_bot»
4⃣ اگر نام کاربری ای که فرستادید به bot ختم نشده باشد ربات به صورت زیر پاسخ می دهد و می گوید :« نام کاربری حتما باید به کلمه bot ختم شود »
Sorry, the username must end in 'bot'. E.g. 'Tetris_bot' or 'Tetrisbot'
اگر نام کاربری که فرستادید قبلا توسط فرد دیگری گرفته شده باشد ربات پاسخ زیر را برای شما می فرستد و می گوید :« این نام کاربری قبلا توسط فرد دیگری گرفته شده است، لطفا یک نام کاربری بدون مالک ارسال کنید»
Sorry, this username is already taken. Please try something different.
5⃣ در صورتی که تمام موارد ذکر شده در بالا را رعایت کرده باشید ربات @BotFather پیامی حاوی توکن رباتتان برای شما ارسال خواهد کرد. آنرا برای این ربات فروارد کنید تا ربات پیامرسانتان ساخته شود.", '', $message_id);
}
elseif ($text == "🔰 ساخت ربات") {
	sendAction($chat_id);
	$count_bot = count($data['bots']);
	if ( ($count_bot<$list['bot_count']) or $from_id == $admin) {
		$data['step'] = "create";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "🤖 توکن رباتت رو که از @BotFather گرفتی برام هدایت (فروارد) کن

📕 اگه راهنمایی لازم داری دستور /help رو ارسال کن", null, $message_id, $back);
	}
	else {
		if ($list['bot_count'] < 1) {
			sendMessage($chat_id, "🎃 امکان ساخت ربات توسط مدیریت غیر فعال شده است.\n\n🤠 لطفا زمانی دیگر دوباره امتحان کنید.", 'markdown', $message_id, $menu);
		}
		else {
			sendMessage($chat_id, "🎃 هر کاربر تنها می تواند *$list[bot_count]* ربات بسازد.\n\n🤖 شما اکنون *$count_bot* ربات دارید و امکان ساخت ربات های بیشتر از شما سلب شده است.\n\n🌈 برای ساخت رباتی جدید باید ربات های قدیمی خود را حذف کنید.", 'markdown', $message_id, $menu);
		}
	}
}
elseif ($step == "create") {
	sendAction($chat_id);
	$count_bot = count($data['bots']);
	if ( ($count_bot<$list['bot_count']) or $from_id == $admin) {
		if (!preg_match('|(?<token>[0-9]+\:[a-zA-Z0-9\-\_]+)|ius', $text, $matches)) {
			sendMessage($chat_id, "⛔️ توکن ارسالی نامعتبر است.", null, $message_id, $back);
			exit();
		}
		$token = $matches['token'];
		$result = json_decode(file_get_contents('https://api.telegram.org/bot' . $token . '/getMe'), true);
		$ok = $result['ok'];
		if ($ok) {
			$un = strtolower($result['result']['username']);
			if (!file_exists("Bots/$un/config.php")) {

				$pdo->exec("CREATE TABLE IF NOT EXISTS `{$un}_members` (
					`id` INT(255) NOT NULL AUTO_INCREMENT,
					`user_id` BIGINT(255) NOT NULL,
					`time` INT(255) NOT NULL,
					PRIMARY KEY (`id`)
				);");

				$prepared = $pdo->prepare("SELECT * FROM `bots` WHERE `username`='{$un}';");
				$prepared->execute();
				$fetch = $prepared->fetchAll();
				if (count($fetch) <= 0) {
					$pdo->exec("INSERT INTO `bots` (`admin`, `username`, `token`, `time`) VALUES ({$user_id}, '{$un}', '{$token}', UNIX_TIMESTAMP());");
				}

				$config = file_get_contents("Source/config.php");
				$config = str_replace("**ADMIN**", $from_id, $config);
				$config = str_replace("**TOKEN**", $token, $config);
				$config = str_replace("**URL**", "$host_folder/Bots/$un/", $config);
				mkdir("Bots/$un");
				mkdir("Bots/$un/data");
				copy('Source/index.php', "Bots/$un/bot.php");
				file_put_contents("Bots/$un/config.php", $config);
				$delete_updates = json_decode(file_get_contents("https://api.telegram.org/bot$token/getUpdates"),true);
				$count_updates = count($delete_updates['result']) - 1;
				$last_update_id = $delete_updates['result'][$count_updates]['update_id'] + 1;
				file_get_contents("https://api.telegram.org/bot$token/getUpdates?offset=$last_update_id");
				$txt = urlencode("✅ ربات شما با موفقیت ساخته شد.\n💠 برای مشاهده امکانات ربات دستور /start را ارسال نمایید.\n\n📣 کانال : " . $main_channel);
				$keyboard = json_encode(['KeyboardRemove'=>[], 'remove_keyboard'=>true]);
				file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$from_id."&text=".$txt."&reply_markup=$keyboard&disable_web_page_preview=true");
				$WebHook = file_get_contents("https://api.telegram.org/bot$token/setWebhook?url=$host_folder/Bots/$un/bot.php&max_connections=1&allowed_updates=[\"message\",\"callback_query\",\"inline_query\"]");
				$data['step'] = "none";
				$data['bots'][] = "@$un";
				file_put_contents("Data/$from_id/data.json",json_encode($data));
				$keyboard = json_encode
				(
					[
						'inline_keyboard' => [
							[['text' => '🤖 @' . $un, 'url' => 'https://telegram.me/' . $un . '?start']]
						]
					]
				);
				sendMessage($chat_id, "✅ ربات شما با موفقیت به سرور ما متصل گردید.\n\n🤖 <a href='https://telegram.me/$un?start'>@$un</a>", 'html', $message_id, $keyboard);
				sendMessage($chat_id, "👇🏻 یکی از گزینه های زیر را انتخاب کنید.", null, $message_id, $menu);
				$first_name = str_replace(["<", ">"], null, $first_name);
				sendMessage($logchannel, "id: <code>$from_id</code>\n👤 کاربر <a href='tg://user?id=$from_id'>$first_name</a>\nربات خود را با یوزرنیم « @$un » ایجاد کرد.\n🤖 توکن ربات :\n<code>$token</code>", 'html', null);
			} else {
				$data['step'] = "none";
				file_put_contents("Data/$from_id/data.json",json_encode($data));
				sendMessage($chat_id, "⛔️ این ربات از قبل به سرور ما متصل بود.", null, $message_id, $menu);
			}
		} else {
			sendMessage($chat_id, "⛔️ توکن ارسالی نامعتبر است.", null, $message_id, $back);
		}
	}
	else {
		if ($list['bot_count'] < 1) {
			sendMessage($chat_id, "🎃 امکان ساخت ربات توسط مدیریت غیر فعال شده است.\n\n🤠 لطفا زمانی دیگر دوباره امتحان کنید.", 'markdown', $message_id, $menu);
		}
		else {
			sendMessage($chat_id, "🎃 هر کاربر تنها می تواند *$list[bot_count]* ربات بسازد.\n\n🤖 شما اکنون *$count_bot* ربات دارید و امکان ساخت ربات های بیشتر از شما سلب شده است.\n\n🌈 برای ساخت رباتی جدید باید ربات های قدیمی خود را حذف کنید.", 'markdown', $message_id, $menu);
		}
	}
}
elseif ($text == '🤖 ربات های من' || $text == '🔙 بازگشت به ربات ها') {
	sendAction($chat_id);
	if (!empty($data['bots'])) {
		$data['step'] = 'show_bot';
		file_put_contents("Data/{$from_id}/data.json", json_encode($data));

		$keyboard = [];
		foreach ($data['bots'] as $user_bot) {
			$keyboard[][] = ['text' => "👉🏻🤖 {$user_bot}"];
		}

		$keyboard[][] = ['text' => '🔙 بازگشت'];
		$keyboard = json_encode([
			'keyboard'=> $keyboard,
			'resize_keyboard' => true
		]);
		sendMessage($chat_id, "🔰 ربات مورد نظرتان را از لیست زیر انتخاب کنید.", null, $message_id, $keyboard);
	} else {
		sendMessage($chat_id, "❌ شما هیچ رباتی نساخته اید.", null, $message_id);
	}
}
elseif ($data['step'] == 'show_bot' && preg_match('#\@(?<bot>[a-zA-Z0-9\_]+bot)#usi', $text, $matches) || ($text == '🔙 بازگشت به ربات' && preg_match('#token\_(?<bot>.+)#', $data['step'], $matches))) {
	sendAction($chat_id);
	$bot = strtolower($matches['bot']);

	if (in_array("@{$bot}", $data['bots'])) {
		$data['step'] = "manage_{$bot}";
		file_put_contents("Data/{$from_id}/data.json", json_encode($data));
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"🤖 ربات @{$bot} انتخاب شد.
🔰 چه کاری می خواهید انجام دهید؟",
			'reply_markup'=>json_encode(
				[
					'keyboard'=>[
						[['text' => '💾 پشتیبان گیری'], ['text' => '🔰 اطلاعات']],
						[['text' => '🗑 حذف ربات'], ['text' => '♻️ تغییر توکن']],
						[['text' => '🔙 بازگشت به ربات ها']],
					],
					'resize_keyboard'=>true
				]
			)
		]);
	}
	else {
		sendMessage($chat_id, "❌ شما هیچ رباتی با نام کاربری @{$bot} ندارید.", null, $message_id);
	}
}
elseif (isset($update->message) && preg_match('#manage\_(?<bot>.+)#', $data['step'], $matches) ) {
	$bot = $matches['bot'];

	if (in_array("@{$bot}", $data['bots'])) {
		if ($text == '🔰 اطلاعات') {
			sendAction($chat_id);
			$bot_config = file_get_contents("Bots/{$bot}/config.php");
			preg_match('/\$Token\s=\s"(.*?)";/', $bot_config, $match);
			$bot_token = $match[1];
			$folder_url = "{$host_folder}/Bots/{$bot}/";

			$get_bot = json_decode(file_get_contents("https://api.telegram.org/bot{$bot_token}/getMe"), true);
			if ($get_bot['ok'] == true) {
				$can_join_groups = $get_bot['result']['can_join_groups'] == true ? '✅' : '❌';
				$can_read_all_group_messages = $get_bot['result']['can_read_all_group_messages'] == true ? '✅' : '❌';
				$supports_inline_queries = $get_bot['result']['supports_inline_queries'] == true ? '✅' : '❌';

				$webhook_info = json_decode(file_get_contents("https://api.telegram.org/bot{$bot_token}/getWebhookInfo"), true);

				if (isset($webhook_info['result']['pending_update_count'])) {
					$pending_update_count = "\n♻️ پیام های در صف انتظار : {$webhook_info['result']['pending_update_count']}";
				}
				else {
					$pending_update_count = '';
				}
				if (isset($webhook_info['result']['url']) && $webhook_info['result']['url'] != "{$folder_url}bot.php") {
					file_get_contents("https://api.telegram.org/bot{$bot_token}/setWebhook?url={$folder_url}bot.php&max_connections=1&allowed_updates=[\"message\",\"callback_query\",\"inline_query\"]");

					$answer_text = "✅ مشکل وبهوک ربات حل گردید.

📎 توکن ربات : {$bot_token}
🆔 شناسه عددی ربات : {$get_bot['result']['id']}
🤖 نام ربات : {$get_bot['result']['first_name']}
👤 نام کاربری ربات : @{$get_bot['result']['username']}
👥 امکان عضویت در گروه : {$can_join_groups}
🧐 امکان خواندن همه پیام های گروه : {$can_read_all_group_messages}
📥 پشتیبانی از حالت درون خطی : {$supports_inline_queries}{$pending_update_count}";
				}
				else {
					$answer_text = "📎 توکن ربات : {$bot_token}
🆔 شناسه عددی ربات : {$get_bot['result']['id']}
🤖 نام ربات : {$get_bot['result']['first_name']}
👤 نام کاربری ربات : @{$get_bot['result']['username']}
👥 امکان عضویت در گروه : {$can_join_groups}
🧐 امکان خواندن همه پیام های گروه : {$can_read_all_group_messages}
📥 پشتیبانی از حالت درون خطی : {$supports_inline_queries}{$pending_update_count}";
				}

				sendMessage($chat_id, $answer_text, null, $message_id);
			}
			else {
				sendMessage($chat_id, "❌ توکن ثبت شده برای ربات @{$bot} از کار افتاده است.
✅ لطفا توکن جدید رباتتان را از @BotFather دریافت کنید و با استفاده از دکمه «♻️ تغییر توکن» آنرا ثبت کنید.", null, $message_id);
			}
		}
		elseif ($text == '💾 پشتیبان گیری') {
			sendAction($chat_id, 'upload_document');
			$prepared = $pdo->prepare("SELECT * FROM `{$bot}_members`;");
			$prepared->execute();
			$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
			file_put_contents("Bots/{$bot}/data/members.json", json_encode($fetch));
			$file_to_zip = array(
				"Bots/{$bot}/data/list.json",
				"Bots/{$bot}/data/data.json",
				"Bots/{$bot}/data/members.json"
			);
			$file_name = date('Y-m-d') . '_' . $bot . '_backup.zip';
			CreateZip($file_to_zip, $file_name, "{$bot}_147852369");
			$zipfile = new CURLFile($file_name);
			$time = date('Y/m/d - H:i:s');
			sendDocument($chat_id, $zipfile, "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>");
			unlink($file_name);
			unlink("Bots/{$bot}/data/members.json");
		}
		elseif ($text == '♻️ تغییر توکن') {
			sendAction($chat_id);

			$bot_config = file_get_contents("Bots/{$bot}/config.php");
			preg_match('/\$Token\s=\s"(.*?)";/', $bot_config, $match);
			$bot_token = $match[1];

			$get_bot = json_decode(file_get_contents("https://api.telegram.org/bot{$bot_token}/getMe"), true);

			if ($get_bot['ok'] == true) {
				sendMessage($chat_id, "❌ توکن ربات @{$bot} سالم است و نیاز به تغییر ندارد.", null, $message_id);
			}
			else {
				$data['step'] = "token_{$bot}";
				file_put_contents("Data/{$from_id}/data.json", json_encode($data));

				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>"🔰 لطفا توکن جدید ربات @{$bot} را ارسال کنید.",
					'reply_markup'=>json_encode(
						[
							'keyboard'=>[
								[['text' => '🔙 بازگشت به ربات']],
							],
							'resize_keyboard'=>true
						]
					)
				]);
			}
		}
		elseif ($text == '🗑 حذف ربات') {
			$keyboard = json_encode([
				'inline_keyboard' => [
					[['text' => "❌ بله", 'callback_data' => "delete_{$bot}"]],
					[['text' => "✅ خیر", 'callback_data' => "nodelete_{$bot}"]]
				]
			]);
			sendMessage($chat_id, "❓ آیا می خواهید ربات @{$bot} را حذف کنید؟", null, $message_id, $keyboard);
		}
		else {
			sendMessage($chat_id, "❌ دستور ناشناخته است.", null, $message_id);
		}
	}
	else {
		sendAction($chat_id);

		if (!empty($data['bots'])) {
			$data['step'] = 'show_bot';
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
	
			$keyboard = [];
			foreach ($data['bots'] as $user_bot) {
				$keyboard[][] = ['text' => "👉🏻🤖 {$user_bot}"];
			}
	
			$keyboard[][] = ['text' => '🔙 بازگشت'];
			$keyboard = json_encode([
				'keyboard'=> $keyboard,
				'resize_keyboard' => true
			]);
			sendMessage($chat_id, "❌ ربات @{$bot} حذف شده است.", null, $message_id, $keyboard);
		} else {
			$data['step'] = '';
			file_put_contents("Data/$from_id/data.json", json_encode($data));
			sendMessage($chat_id, "❌ ربات @{$bot} حذف شده است.", null, $message_id, $menu);
		}
	}
}
elseif (isset($update->message) && preg_match('#token\_(?<bot>.+)#', $data['step'], $matches) ) {
	sendAction($chat_id);
	$bot = $matches['bot'];

	if (in_array("@{$bot}", $data['bots'])) {
		if (preg_match('|(?<token>[0-9]+\:[a-zA-Z0-9\-\_]+)|ius', $text, $matches)) {
			$bot_token = $matches['token'];

			$get_bot = json_decode(file_get_contents("https://api.telegram.org/bot{$bot_token}/getMe"), true);
			if ($get_bot['ok'] == true) {
				if (strtolower($get_bot['result']['username']) == $bot) {
					$data['step'] = "manage_{$bot}";
					file_put_contents("Data/{$from_id}/data.json", json_encode($data));

					$folder_url = "{$host_folder}/Bots/{$bot}/";
					$bot_config = file_get_contents("Bots/{$bot}/config.php");
					$bot_config = file_get_contents('Source/config.php');
					$bot_config = str_replace('**ADMIN**', $from_id, $bot_config);
					$bot_config = str_replace('**TOKEN**', $bot_token, $bot_config);
					$bot_config = str_replace('**URL**', $folder_url, $bot_config);
					file_put_contents('Bots/' . $bot . '/config.php', $bot_config);

					file_get_contents("https://api.telegram.org/bot{$bot_token}/setWebhook?url={$folder_url}bot.php&max_connections=1&allowed_updates=[\"message\",\"callback_query\",\"inline_query\"]");

					bot('sendMessage', [
						'chat_id'=>$chat_id,
						'reply_to_message_id'=>$message_id,
						'text'=>"✅ توکن ربات @{$bot} تغییر کرد.",
						'reply_markup'=>json_encode(
							[
								'keyboard'=>[
									[['text' => '💾 پشتیبان گیری'], ['text' => '🔰 اطلاعات']],
									[['text' => '🗑 حذف ربات'], ['text' => '♻️ تغییر توکن']],
									[['text' => '🔙 بازگشت به ربات ها']],
								],
								'resize_keyboard'=>true
							]
						)
					]);
				}
				else {
					sendMessage($chat_id, "❌ توکن باید مربوط به ربات @{$bot} باشد.
🚫 این توکن مربوط به ربات @{$get_bot['result']['username']} است.", null, $message_id);
				}
			}
			else {
				sendMessage($chat_id, "⛔️ توکن ارسالی نامعتبر است.", null, $message_id);
			}
		}
		else {
			sendMessage($chat_id, "⛔️ توکن ارسالی نامعتبر است.", null, $message_id);
		}
	}
	else {
		if (!empty($data['bots'])) {
			$data['step'] = 'show_bot';
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
	
			$keyboard = [];

			foreach ($data['bots'] as $user_bot) {
				$keyboard[][] = ['text' => "👉🏻🤖 {$user_bot}"];
			}
	
			$keyboard[][] = ['text' => '🔙 بازگشت'];
			$keyboard = json_encode([
				'keyboard'=> $keyboard,
				'resize_keyboard' => true
			]);
			sendMessage($chat_id, "❌ ربات @{$bot} حذف شده است.", null, $message_id, $keyboard);
		} else {
			$data['step'] = '';
			file_put_contents("Data/$from_id/data.json", json_encode($data));
			sendMessage($chat_id, "❌ ربات @{$bot} حذف شده است.", null, $message_id, $menu);
		}
	}
}
elseif (preg_match('#^nodelete\_(?<bot>.+)$#', $update->callback_query->data, $matches)) {
	bot('editMessagetext', [
		'chat_id'=>$chatid,
		'message_id'=>$messageid,
		'parse_mode'=>'html',
		'disable_web_page_preview'=>true,
		'text'=>"✅ شما از حذف کردن ربات @{$matches['bot']} منصرف شدید."
	]);
}
elseif (preg_match('#^delete\_(?<bot>.+)$#', $update->callback_query->data, $matches)) {
	$rand = mt_rand(0, 4);
	$i = 0;
	$inline_keyboard = [];
	while ($i < 5) {
		if ($i == $rand) {
			$inline_keyboard[] = [['text' => "❌ بله", 'callback_data' => "yesdelete_{$matches['bot']}"]];
		}
		else {
			$inline_keyboard[] = [['text' => "✅ خیر", 'callback_data' => "nodelete_{$matches['bot']}"]];
		} 
		$i++;
	}
	$inline_keyboard = json_encode([
		'inline_keyboard' => $inline_keyboard
	]);

	bot('editMessagetext', [
		'chat_id'=>$chatid,
		'message_id'=>$messageid,
		'parse_mode'=>'html',
		'disable_web_page_preview'=>true,
		'reply_markup' => $inline_keyboard,
		'text'=>"❓ آیا واقعا می خواهید ربات @{$matches['bot']} را حذف کنید؟"
	]);
	bot('AnswerCallbackQuery',
	[
		'callback_query_id'=>$update->callback_query->id,
		'text'=>''
	]);
	
}
elseif (preg_match('#^yesdelete\_(?<bot>.+)$#', $update->callback_query->data, $matches)) {
	$botid = $matches['bot'];

	if (in_array('@' . $botid, $data['bots'])) {
		sendAction($chat_id, 'upload_document');
		$prepared = $pdo->prepare("SELECT * FROM `{$botid}_members`;");
		$prepared->execute();
		$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
		file_put_contents("Bots/{$botid}/data/members.json", json_encode($fetch));
		$file_to_zip = array(
			"Bots/{$botid}/data/list.json",
			"Bots/{$botid}/data/data.json",
			"Bots/{$botid}/data/members.json"
		);
		$file_name = date('Y-m-d') . '_' . $botid . '_backup.zip';
		CreateZip($file_to_zip, $file_name, "{$botid}_147852369");
		$time = date('Y/m/d - H:i:s');

		if ((preg_match('#token\_(?<bot>.+)#', $data['step'], $matches) || preg_match('#manage\_(?<bot>.+)#', $data['step'], $matches) || $data['step'] == 'show_bot') && !empty( array_diff($data['bots'], ['@' . $botid]) )) {
			$keyboard = [];
			foreach (array_diff($data['bots'], ['@' . $botid]) as $user_bot) {
				$keyboard[][] = ['text' => "👉🏻🤖 {$user_bot}"];
			}
	
			$keyboard[][] = ['text' => '🔙 بازگشت'];
			$keyboard = json_encode([
				'keyboard'=> $keyboard,
				'resize_keyboard' => true
			]);

			bot('sendDocument', [
				'chat_id' => $chat_id,
				'parse_mode' => 'html',
				'document' => $zipfile = new CURLFile($file_name),
				'caption' => "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>\n\n👆🏻 این فایل شامل تمامی اطلاعات ربات @{$botid} است تا اگر دوباره خواستید رباتتان را به سرویس ما وصل کنید اطلاعات برگردانده شود.",
				'reply_markup' => $keyboard
			]);
			$data['step'] = 'show_bot';
		}
		elseif (preg_match('#token\_(?<bot>.+)#', $data['step'], $matches) || preg_match('#manage\_(?<bot>.+)#', $data['step'], $matches) || $data['step'] == 'show_bot') {
			bot('sendDocument', [
				'chat_id' => $chat_id,
				'parse_mode' => 'html',
				'document' => $zipfile = new CURLFile($file_name),
				'caption' => "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>\n\n👆🏻 این فایل شامل تمامی اطلاعات ربات @{$botid} است تا اگر دوباره خواستید رباتتان را به سرویس ما وصل کنید اطلاعات برگردانده شود.",
				'reply_markup' => $menu
			]);
			$data['step'] = 'none';
		}

		unlink($file_name);
		unlink("Bots/{$botid}/data/members.json");

		
		$pdo->exec("DROP TABLE IF EXISTS `{$botid}_members`;");
		$prepare = $pdo->prepare("DELETE FROM `bots` WHERE `username`='{$botid}';");
		$prepare->execute();

		$prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `bot_username`='{$botid}';");
		$prepare->execute();

		$config = file_get_contents("Bots/".$botid."/config.php");
		preg_match_all('/\$Token\s=\s"(.*?)";/', $config, $match);
		file_get_contents("https://api.telegram.org/bot".$match[1][0]."/deleteWebHook");
		deleteFolder("Bots/$botid");
		$search = array_search("@".$botid, $data['bots']);
		unset($data['bots'][$search]);
		$data['bots'] = array_values($data['bots']);
		file_put_contents("Data/$from_id/data.json",json_encode($data));

		bot('editMessagetext', [
			'chat_id'=>$chatid,
			'message_id'=>$messageid,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"✅ ربات « @$botid » با موفقیت حذف گردید."
		]);
		bot('AnswerCallbackQuery',
		[
			'callback_query_id'=>$update->callback_query->id,
			'text'=>''
		]);

		$first_name = str_replace(["<", ">"], null, $first_name);
		sendMessage($logchannel, "id: <code>$from_id</code>\n👤 کاربر <a href='tg://user?id=$from_id'>$first_name</a>\nربات خود را با یوزرنیم « @$botid » از سرور حذف کرد.", 'html', null);
	}
	else {
		bot('editMessagetext', [
			'chat_id'=>$chatid,
			'message_id'=>$messageid,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"❌ عملیات حذف ربات با مشکل مواجه شد."
		]);
	}
}
elseif ($text == "🌈 ثبت تبلیغ") {
	sendAction($chat_id);
	$inline_keyboard = json_encode(
		[
			'inline_keyboard' => [
				[['text'=>"🌈 $support", 'url'=>'https://telegram.me/' . str_replace('@', '', $support)]]
			]
		]
	);
	sendMessage($chat_id, "👇🏻 برای ثبت تبلیغات خود برای نمایش در ربات های ساخته شده توسط این سرویس به ربات زیر مراجعه نمایید.", 'markdown', $message_id, $inline_keyboard);
}
##----------------------
if ($from_id == $admin && $chat_id > 0) {
	if ($text == "🔑 مدیریت" || $text == "🔙 بازگشت به مدیریت") {
		sendAction($chat_id);
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "👇🏻یکی از دکمه های زیر را انتخاب کنید :", null, $message_id, $panel);
	}
	elseif ($text == '🤖 تعداد مجاز') {
		sendAction($chat_id);
		$data['step'] = "count_bots";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
		sendMessage($chat_id, "🤖 هر کاربر می تواند چند ربات بسازد؟\n👀 تعداد : $list[bot_count]\n🎃 لطفا یک عدد ارسال کنید.", 'markdown', $message_id, $backpanel);
	}
	elseif ($step == 'count_bots') {
		$number = convert($text);
		if (!is_numeric($number)) {
			sendMessage($chat_id, "🎃 لطفا یک عدد ارسال کنید.", 'markdown', $message_id, $backpanel);
		}
		else {
			$data['step'] = "none";
			file_put_contents("Data/$from_id/data.json",json_encode($data));
			$list['bot_count'] = $number;
			file_put_contents('Data/list.json', json_encode($list));
			sendMessage($chat_id, "👈🏻 محدودیت ساخت ربات بر روی $number عدد تنظیم گردید.", null, $message_id, $panel);
		}
	}
	elseif ($text == '💠 تبلیغات' || $text == '🔙 بازگشت به تبلیغات') {
		sendAction($chat_id);
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "🧮 به بخش تبلیغات ربات خوش آمدید.\n✏️ لطفا یکی از دکمه های زیر را انتخاب کنید.", 'markdown', null, $ads_menu);
	}
	elseif ($text == '✏️ ثبت تبلیغ') {
		sendAction($chat_id);
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		if (count($ads) > 5) {
			sendMessage($chat_id, "🚨 امکان ثیت بیش از 5 تبلیغ وجود ندارد.\n🔰 لطفا ابتدا از بخش « 🗑 حذف تبلیغ » اقدام به حذف برخی تبلیغات قدیمی نمایید.", 'markdown', null, $ads_menu);
		exit();
		}
		$data['step'] = "setads";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
		sendMessage($chat_id, "🔰 لطفا تبلیغ مورد نظر خود را بفرستید.", null, $message_id, $backpanelads);
	}
	elseif ($step == 'setads') {
		sendAction($chat_id);
		$ad_code = time();
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		if (isset($message->video)) {
			$type = 'video';
			$file_id = bot('sendVideo', [
				'chat_id' => $public_logchannel,
				'video' => $message->video->file_id
			])->result->message_id;
		}
		elseif (isset($message->photo)) {
			$type = 'photo';
			$file_id = bot('sendPhoto', [
				'chat_id' => $public_logchannel,
				'photo' => $message->photo[count($message->photo)-1]->file_id
			])->result->message_id;
		}
		elseif (isset($message->document)) {
			$type = 'document';
			$file_id = bot('sendDocument', [
				'chat_id' => $public_logchannel,
				'document' => $message->document->file_id
			])->result->message_id;
		}
		elseif (isset($message->text)) {
			$type = 'text';
		}
		else {
			sendMessage($chat_id, "🚨 تنها متن، تصویر، ویدیو و فایل قابل قبول هستند.", null, $message_id);
			exit();
		}
		$ads[$ad_code] = [];
		$ads[$ad_code]['type'] = $type;
		$ads[$ad_code]['text'] = (is_null($text) ? $caption : $text);
		$ads[$ad_code]['keyboard'] = null;
		$ads[$ad_code]['file_id'] = $file_id;
		$ads[$ad_code]['on'] = false;
		$ads[$ad_code]['count'] = 0;
		file_put_contents('Data/ads.json', json_encode($ads));
		$data['step'] = "setkeyboard-$ad_code";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
		sendMessage($chat_id, "✅ تبلیغ شما ثبت شد.\n🌐 حالا می توانید برای آن دکمه شیشه ای تعیین کنید.\n🍭 برای تنظیم دکمهٔ شیشه ای به صورت زیر عمل کنید :\n`text1|url1,text2|url2,text3,url3\ntext4|url4,text5|url5`\n\n❗️ نکته : تعداد ستون ها توسط تلگرام به عدد ۶ محدود شده است.", 'markdown', $message_id, json_encode(['keyboard'=>[[['text'=>"🔴 بدون دکمه شیشه ای"]]], 'resize_keyboard'=>true]));
	}
	elseif ($step != str_replace('setkeyboard-', '', $step)) {
		sendAction($chat_id);
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		$ad_code = str_replace('setkeyboard-', '', $step);
		$inline_keyboard = null;
		if ($text != '🔴 بدون دکمه شیشه ای') {
			$inline_keyboard = makeInlineKeyboard($text);
			$ads[$ad_code]['keyboard'] = $inline_keyboard;
			file_put_contents('Data/ads.json', json_encode($ads));
		}
		$type = $ads[$ad_code]['type'];
		$method = str_replace(['video', 'photo', 'document', 'text'], ['sendVideo', 'sendPhoto', 'sendDocument', 'sendMessage'], $type);
		$dataa = [
			'chat_id' => $chat_id,
			'parse_mode' => 'html'
		];
		if ($type == 'text') {
			$dataa['text'] = $ads[$ad_code]['text'];
			$dataa['disable_web_page_preview'] = true;
		} else {
			$dataa[$type] = 'https://telegram.me/' . str_replace('@', '', $public_logchannel) . '/' . $ads[$ad_code]['file_id'];
			$dataa['caption'] = $ads[$ad_code]['text'];
		}
		if ($inline_keyboard != null) {
			$dataa['reply_markup'] = json_encode($inline_keyboard);
		}
		bot($method, $dataa);
		sendMessage($chat_id, "👆🏻 تبلیغ مورد نظر به شرح بالا است.\n💠 آیا از ثبت نهایی آن مطمئن هستید؟", null, null, json_encode(['keyboard'=>[[['text'=>"✅ بله"],['text'=>"❌ خیر"]]], 'resize_keyboard'=>true]));
		$data['step'] = "accept-$ad_code";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
	}
	elseif ($step != str_replace('accept-', '', $step)) {
		sendAction($chat_id);
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		$ad_code = str_replace('accept-', '', $step);
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
		if ($text == '✅ بله') {
			$ads[$ad_code]['on'] = true;
			file_put_contents('Data/ads.json', json_encode($ads));
			sendMessage($chat_id, "✅ تبلیغ مورد نظر شما با موفقیت ثبت شد.", null, $message_id, $ads_menu);
		} else {
			unset($ads[$ad_code]);
			file_put_contents('Data/ads.json', json_encode($ads));
			sendMessage($chat_id, "❌ تبلیغ مورد نظر شما ثبت نشد.", null, $message_id, $ads_menu);
		}
	}
	elseif ($text == '🗒 لیست تبلیغات') {
		sendAction($chat_id);
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		$count = count($ads);
		if ($count < 1) {
			sendMessage($chat_id, '❗️ هیچ تبلیغی ثبت نشده است.', null, $message_id, $ads_menu);
			exit();
		}
		$text = "📊 تعداد : $count\n\n";
		foreach ($ads as $key => $ad) {
			$text .= "🔦 نوع : " . str_replace(['video', 'photo', 'document', 'text'], ['🎥 ویدیو', '🌠 تصویر', '📎 فایل', '📃 متن'], $ad['type']);
			$text .= "\n🧭 تعداد بازدید : " . $ad['count'];
			$text .= "\n🔰 نمایش : " . ($ad['on'] == true ? '✅ بله' : '❌ خیر');
			$text .= "\n📌 دکمه شیشه ای : " . ($ad['keyboard'] == null ? '❌ ندارد' : '✅ دارد');
			$text .= "\n🗑 حذف : /delete_$key\n\n";
		}
		sendMessage($chat_id, $text, null, $message_id, $ads_menu);
	}
	elseif (preg_match("|\/delete\_([0-9]+)|i", $text, $matches)) {
		sendAction($chat_id);
		$ads = json_decode(file_get_contents('Data/ads.json'), true);
		$ad_code = $matches[1];
		if (!isset($ads[$ad_code])) {
			sendMessage($chat_id, '❗️ تبلیغ مورد نظر شما وجود ندارد.', null, $message_id, $ads_menu);
			exit();
		}
		$type = $ads[$ad_code]['type'];
		$method = str_replace(['video', 'photo', 'document', 'text'], ['sendVideo', 'sendPhoto', 'sendDocument', 'sendMessage'], $type);
		$dataa = [
			'chat_id' => $chat_id,
			'parse_mode' => 'html'
		];
		if ($type == 'text') {
			$dataa['text'] = $ads[$ad_code]['text'];
			$dataa['disable_web_page_preview'] = true;
		} else {
			$dataa[$type] = 'https://telegram.me/' . str_replace('@', '', $public_logchannel) . '/' . $ads[$ad_code]['file_id'];
			$dataa['caption'] = $ads[$ad_code]['text'];
		}
		if ($ads[$ad_code]['keyboard'] != null) {
			$dataa['reply_markup'] = json_encode($ads[$ad_code]['keyboard']);
		}
		bot($method, $dataa);
		sendMessage($chat_id, "👆🏻 تبلیغ مورد نظر به شرح بالا است.\n💠 آیا از حذف آن مطمئن هستید؟", null, null, json_encode(['keyboard'=>[[['text'=>"✅ بله"],['text'=>"❌ خیر"]]], 'resize_keyboard'=>true]));
		$data['step'] = "delete-$ad_code";
		file_put_contents("Data/$from_id/data.json", json_encode($data));

	}
	elseif ($step != str_replace('delete-', '', $step)) {
		sendAction($chat_id);
		if ($text == '✅ بله') {
			$ads = json_decode(file_get_contents('Data/ads.json'), true);
			$ad_code = str_replace('delete-', '', $step);
			unset($ads[$ad_code]);
			file_put_contents('Data/ads.json', json_encode($ads));
			sendMessage($chat_id, '✅ تبلیغ مورد نظر شما با موفقیت حذف شد.', null, $message_id, $ads_menu);
		} else {
			sendMessage($chat_id, '✅ تبلیغ مورد نظر شما باقی ماند و حذف نشد.', null, $message_id, $ads_menu);
		}
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json", json_encode($data));
	}
	elseif (preg_match('#\/(?:start uid\-?|info )(?<info>@?[a-zA-Z][a-zA-Z0-9\_]{4,32}|[0-9]{3,25})#i', $text, $matches)) {
		if (is_numeric($matches['info'])) {
			if (is_dir("Data/{$matches['info']}")) {
				$get_chat = bot('getChat',
				[
					'chat_id'=>$matches['info']
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$user['user_id']}";
				$user_name_mention = "<a href='$mention'>$name</a>";
				$user_data = json_decode(file_get_contents("Data/{$matches['info']}/data.json"), true);
				$user_count_bots = count($user_data['bots']);
				if ($user_count_bots > 0) {
					$user_bots = "\n";
					foreach ($user_data['bots'] as $user_bot) {
						$user_bot = str_replace('@', '', $user_bot);
						$prepared_bot = $pdo->prepare("SELECT * FROM `{$user_bot}_members`;");
						$prepared_bot->execute();
						$fetch_bot = $prepared_bot->fetchAll();
						$bot_count = number_format(count($fetch_bot));
						$user_bots .= "@{$user_bot} {$bot_count} members\n";
					}
				}
				sendMessage($chat_id, "👤 {$user_name_mention}\n🤖 {$user_count_bots}{$user_bots}", 'html', $message_id);
			}
			else {
				sendMessage($chat_id, "❌ کاربر مورد نظر شما وجود ندارد.", 'html', $message_id);
			}
		}
		else {
			$bot_username = trim(strtolower(str_replace('@', '', $matches['info'])));
			if (is_dir("Bots/{$bot_username}")) {
				$config = file_get_contents("Bots/{$bot_username}/config.php");
				preg_match('/\$Dev\s=\s"(.*?)";/', $config, $match);
				$Dev = $match[1];
				preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
				$token = $match[1];
				$prepared_bot = $pdo->prepare("SELECT * FROM `{$bot_username}_members`;");
				$prepared_bot->execute();
				$fetch_bot = $prepared_bot->fetchAll();
				$bot_count = number_format(count($fetch_bot));

				$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
				$prepared_vip->execute();
				$fetch_vip = $prepared_vip->fetchAll();
				if (count($fetch_vip) > 0) {
					$vip_emoji = '🎖';
				}
				else {
					$vip_emoji = '';
				}
				$get_chat = bot('getChat',
				[
					'chat_id'=>$Dev
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$Dev}";
				$user_name_mention = "<a href='$mention'>$name</a>";


				sendMessage($chat_id, "{$vip_emoji}🤖 @{$bot_username}
📊 <b>{$bot_count}</b> کاربر
👤 {$user_name_mention}
🆔 <code>{$Dev}</code>
🔰 <code>{$token}</code>
💾 دریافت فایل پشتیبان : /backup_{$bot_username}", 'html', $message_id);
			}
			else {
				sendMessage($chat_id, "❌ ربات مورد نظر شما وجود ندارد.", 'html', $message_id);
			}
		}
	}
	elseif (preg_match('@/setvip (?<price>[1-9][0-9]+)@i', $text, $matches)) {
		sendAction($chat_id);
		file_put_contents('Data/vip-price.txt', $matches['price']);
		sendMessage($chat_id, "🚀 هزینه اشتراک ماهیانه بر روی {$matches['price']} تومان تنظیم گردید.");
	}
	elseif ($text == '📊 آمار کاربران') {
		sendAction($chat_id);
		$res = $pdo->query("SELECT * FROM `members` ORDER BY `id` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);
		$division_10 = ($count)/10;

		$count_format = number_format($count);
	
		$answer_text_array = [];
	
		$i = 1;
		foreach ($fetch as $user) {
			$get_chat = bot('getChat',
			[
				'chat_id'=>$user['user_id']
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$user['user_id']}";
			$user_name_mention = "<a href='$mention'>$name</a>";
			$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$user['user_id']}'>👤 {$i}</a>";

			$user_data = json_decode(file_get_contents("Data/{$user['user_id']}/data.json"), true);
			$user_count_bots = count($user_data['bots']);
			$user_bots = '';
			if ($user_count_bots > 0) {
				foreach ($user_data['bots'] as $user_bot) {
					$user_bot = str_replace('@', '', $user_bot);
					$prepared_bot = $pdo->prepare("SELECT * FROM `{$user_bot}_members`;");
					$prepared_bot->execute();
					$fetch_bot = $prepared_bot->fetchAll();
					$bot_count = number_format(count($fetch_bot));
					$user_bots .= "@{$user_bot} {$bot_count} members\n";
				}
			}
			
			$answer_text_array[] = "{$user_info_link} - {$user_name_mention}\n🆔 <code>{$user['user_id']}</code>\n🤖 <b>{$user_count_bots}</b>\n{$user_bots}🕰 " . jdate('Y/m/j H:i:s', $user['time']);
			if ($i >= 10) break;
			$i++;
		}
	
		if ($division_10 <= 1) {
			$reply_markup = null;
		}
		else {
			if ($division_10 <= 2) {
				$reply_markup = json_encode(
					[
						'inline_keyboard' => [
							[
								['text'=>'«1»', 'callback_data'=>'goto_0_1'],
								['text'=>'2', 'callback_data'=>'goto_10_2']
							]
						]
					]
				);
			}
			else {
				$inline_keyboard = [];

				$inline_keyboard[0][0]['text'] = '«1»';
				$inline_keyboard[0][0]['callback_data'] = 'goto_0_1';

				for ($i = 1; ($i < myFloor($division_10) && $i < 4); $i++) {
					$inline_keyboard[0][$i]['text'] = ($i+1);
					$inline_keyboard[0][$i]['callback_data'] = 'goto_' . ($i*10) . '_' . ($i+1);
				}

				$inline_keyboard[0][$i]['text'] = (myFloor($division_10)+1);
				$inline_keyboard[0][$i]['callback_data'] = 'goto_' . (myFloor($division_10)*10) . '_' . (myFloor($division_10)+1);

				$reply_markup = json_encode([ 'inline_keyboard' => $inline_keyboard ]);
			}
		}

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'reply_markup'=>$reply_markup,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n📊 تعداد کاربران : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
		]);
	}
	elseif (preg_match('@goto\_(?<offset>[0-9]+)\_(?<page>[0-9]+)@', $update->callback_query->data, $matches)) {
		$offset = $matches['offset'];
		$page = $matches['page'];

		$res = $pdo->query("SELECT * FROM `members` ORDER BY `id` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);

		$count_format = number_format($count);

		$division_10 = ($count)/10;
		$floor = floor($division_10);
		$floor_10 = ($floor*10);
	
		##text
		$answer_text_array = [];
	
		$x = 1;
		$j = $offset + 1;
		for ($i = $offset; $i < $count; $i++) {
			$get_chat = bot('getChat',
			[
				'chat_id'=>$fetch[$i]['user_id']
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[$i]['user_id']}";
			$user_name_mention = "<a href='$mention'>$name</a>";
			$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$fetch[$i]['user_id']}'>👤 {$j}</a>";

			$user_data = json_decode(file_get_contents("Data/{$fetch[$i]['user_id']}/data.json"), true);
			$user_count_bots = count($user_data['bots']);
			$user_bots = '';
			if ($user_count_bots > 0) {
				foreach ($user_data['bots'] as $user_bot) {
					$user_bot = str_replace('@', '', $user_bot);
					$prepared_bot = $pdo->prepare("SELECT * FROM `{$user_bot}_members`;");
					$prepared_bot->execute();
					$fetch_bot = $prepared_bot->fetchAll();
					$bot_count = number_format(count($fetch_bot));
					$user_bots .= "@{$user_bot} {$bot_count} members\n";
				}
			}

			$answer_text_array[] = "{$user_info_link} - {$user_name_mention}\n🆔 <code>{$fetch[$i]['user_id']}</code>\n🤖 <b>{$user_count_bots}</b>\n{$user_bots}🕰 " . jdate('Y/m/j H:i:s', $fetch[$i]['time']);
			if ($x >= 10) break;
			$x++;
			$j++;
		}
	
		##keyboard
		$inline_keyboard = [];
	
		if ($division_10 <= 2) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "goto_10_2";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2]
			];
		}
		elseif ($division_10 <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "goto_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "goto_20_3";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3]
			];
		}
		elseif ($division_10 <= 4) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "goto_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "goto_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "goto_30_4";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4]
			];
		}
		elseif ($division_10 <= 5) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "goto_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "goto_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "goto_30_4";
	
			$text_5 = $page == 5 ? '«5»' : 5;
			$data_5 = "goto_40_5";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "goto_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "goto_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "goto_30_4";
	
			$text_5 = ($floor+1);
			$data_5 = "goto_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page >= ($floor-1)) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = $page == ($floor-2) ? '«' . $page . '»' : ($floor-2);
			$data_2 = 'goto_' . (($floor-3)*10) . '_' . ($floor-2);
	
			$text_3 = $page == ($floor-1) ? '«' . $page . '»' : ($floor-1);
			$data_3 = 'goto_' . (($floor-2)*10) . '_' . ($floor-1);
	
			$text_4 = $page == ($floor) ? '«' . $page . '»' : ($floor);
			$data_4 = 'goto_' . (($floor-1)*10) . '_' . ($floor);
	
			$text_5 = $page == ($floor+1) ? '«' . $page . '»' : ($floor+1);
			$data_5 = "goto_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		else {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "goto_0_1";
	
			$text_2 = ($page-1);
			$data_2 = 'goto_' . ($offset-10) . '_' . ($page-1);
	
			$text_3 = '«' . $page . '»';
			$data_3 = 'goto_' . $offset . '_' . $page;
	
			$text_4 = ($page+1);
			$data_4 = 'goto_' . ($offset+10) . '_' . ($page+1);
	
			$text_5 = ($floor+1);
			$data_5 = "goto_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
	
		$reply_markup = json_encode(
			[
				'inline_keyboard' => $inline_keyboard
			]
		);

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('AnswerCallbackQuery',
		[
			'callback_query_id'=>$update->callback_query->id,
			'text'=>''
		]);

		bot('editMessagetext', [
			'chat_id'=>$chatid,
			'message_id'=>$messageid,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n📊 تعداد کاربران : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
			'reply_markup'=>$reply_markup
		]);
	}
	elseif ($text == '🤖 آمار ربات ها') {
		sendAction($chat_id);
		$res = $pdo->query("SELECT * FROM `bots` ORDER BY `time` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);
		$division_10 = ($count)/10;

		$count_format = number_format($count);
	
		$answer_text_array = [];
	
		$i = 1;
		foreach ($fetch as $user) {
			$prepared_bot = $pdo->prepare("SELECT * FROM `{$user['username']}_members`;");
			$prepared_bot->execute();
			$fetch_bot = $prepared_bot->fetchAll();
			$bot_count = number_format(count($fetch_bot));

			$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$user['username']}';");
			$prepared_vip->execute();
			$fetch_vip = $prepared_vip->fetchAll();
			if (count($fetch_vip) > 0) {
				$vip_emoji = '🎖';
			}
			else {
				$vip_emoji = '';
			}
			$get_chat = bot('getChat',
			[
				'chat_id'=>$user['admin']
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$user['admin']}";
			$user_name_mention = "<a href='$mention'>$name</a>";
			$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$user['admin']}'>👤 </a>";
			$bot_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid-{$user['username']}'>{$i} - {$vip_emoji}🤖</a>";

			$bot_time = '🕰 ' . jdate('Y/m/j H:i:s', $user['time']);
			$answer_text_array[] = "{$bot_info_link} @{$user['username']}
📊 <b>{$bot_count}</b> کاربر
{$bot_time}
{$user_info_link}{$user_name_mention}
🆔 <code>{$user['admin']}</code>";
			if ($i >= 10) break;
			$i++;
		}
	
		if ($division_10 <= 1) {
			$reply_markup = null;
		}
		else {
			if ($division_10 <= 2) {
				$reply_markup = json_encode(
					[
						'inline_keyboard' => [
							[
								['text'=>'«1»', 'callback_data'=>'bots_0_1'],
								['text'=>'2', 'callback_data'=>'bots_10_2']
							]
						]
					]
				);
			}
			else {
				$inline_keyboard = [];

				$inline_keyboard[0][0]['text'] = '«1»';
				$inline_keyboard[0][0]['callback_data'] = 'bots_0_1';

				for ($i = 1; ($i < myFloor($division_10) && $i < 4); $i++) {
					$inline_keyboard[0][$i]['text'] = ($i+1);
					$inline_keyboard[0][$i]['callback_data'] = 'bots_' . ($i*10) . '_' . ($i+1);
				}

				$inline_keyboard[0][$i]['text'] = (myFloor($division_10)+1);
				$inline_keyboard[0][$i]['callback_data'] = 'bots_' . (myFloor($division_10)*10) . '_' . (myFloor($division_10)+1);

				$reply_markup = json_encode([ 'inline_keyboard' => $inline_keyboard ]);
			}
		}

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'reply_markup'=>$reply_markup,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n🤖 تعداد ربات ها : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
		]);
	}
	elseif (preg_match('@bots\_(?<offset>[0-9]+)\_(?<page>[0-9]+)@', $update->callback_query->data, $matches)) {
		$offset = $matches['offset'];
		$page = $matches['page'];

		$res = $pdo->query("SELECT * FROM `bots` ORDER BY `id` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);

		$count_format = number_format($count);

		$division_10 = ($count)/10;
		$floor = floor($division_10);
		$floor_10 = ($floor*10);
	
		##text
		$answer_text_array = [];
	
		$x = 1;
		$j = $offset + 1;
		for ($i = $offset; $i < $count; $i++) {

			$prepared_bot = $pdo->prepare("SELECT * FROM `{$fetch[$i]['username']}_members`;");
			$prepared_bot->execute();
			$fetch_bot = $prepared_bot->fetchAll();
			$bot_count = number_format(count($fetch_bot));
			$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$fetch[$i]['username']}';");
			$prepared_vip->execute();
			$fetch_vip = $prepared_vip->fetchAll();
			if (count($fetch_vip) > 0) {
				$vip_emoji = '🎖';
			}
			else {
				$vip_emoji = '';
			}
			$get_chat = bot('getChat',
			[
				'chat_id'=>$fetch[$i]['admin']
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[$i]['admin']}";
			$user_name_mention = "<a href='$mention'>$name</a>";
			$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$fetch[$i]['admin']}'>👤 </a>";
			$bot_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid-{$fetch[$i]['username']}'>{$i} - {$vip_emoji}🤖</a>";

			$bot_time = '🕰 ' . jdate('Y/m/j H:i:s', $fetch[$i]['time']);
			$answer_text_array[] = "{$bot_info_link} @{$fetch[$i]['username']}
📊 <b>{$bot_count}</b> کاربر
{$bot_time}
{$user_info_link}{$user_name_mention}
🆔 <code>{$fetch[$i]['admin']}</code>";
			if ($x >= 10) break;
			$x++;
			$j++;
		}
	
		##keyboard
		$inline_keyboard = [];
	
		if ($division_10 <= 2) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "bots_10_2";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2]
			];
		}
		elseif ($division_10 <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "bots_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "bots_20_3";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3]
			];
		}
		elseif ($division_10 <= 4) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "bots_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "bots_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "bots_30_4";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4]
			];
		}
		elseif ($division_10 <= 5) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "bots_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "bots_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "bots_30_4";
	
			$text_5 = $page == 5 ? '«5»' : 5;
			$data_5 = "bots_40_5";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "bots_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "bots_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "bots_30_4";
	
			$text_5 = ($floor+1);
			$data_5 = "bots_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page >= ($floor-1)) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = $page == ($floor-2) ? '«' . $page . '»' : ($floor-2);
			$data_2 = 'bots_' . (($floor-3)*10) . '_' . ($floor-2);
	
			$text_3 = $page == ($floor-1) ? '«' . $page . '»' : ($floor-1);
			$data_3 = 'bots_' . (($floor-2)*10) . '_' . ($floor-1);
	
			$text_4 = $page == ($floor) ? '«' . $page . '»' : ($floor);
			$data_4 = 'bots_' . (($floor-1)*10) . '_' . ($floor);
	
			$text_5 = $page == ($floor+1) ? '«' . $page . '»' : ($floor+1);
			$data_5 = "bots_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		else {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "bots_0_1";
	
			$text_2 = ($page-1);
			$data_2 = 'bots_' . ($offset-10) . '_' . ($page-1);
	
			$text_3 = '«' . $page . '»';
			$data_3 = 'bots_' . $offset . '_' . $page;
	
			$text_4 = ($page+1);
			$data_4 = 'bots_' . ($offset+10) . '_' . ($page+1);
	
			$text_5 = ($floor+1);
			$data_5 = "bots_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
	
		$reply_markup = json_encode(
			[
				'inline_keyboard' => $inline_keyboard
			]
		);

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('AnswerCallbackQuery',
		[
			'callback_query_id'=>$update->callback_query->id,
			'text'=>''
		]);

		bot('editMessagetext', [
			'chat_id'=>$chatid,
			'message_id'=>$messageid,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n🤖 تعداد ربات ها : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
			'reply_markup'=>$reply_markup
		]);
	}
	elseif ($text == '🎖 لیست رباتهای ویژه') {
		sendAction($chat_id);
		$res = $pdo->query("SELECT * FROM `vip_bots` ORDER BY `start` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);
		$division_10 = ($count)/10;
		$count_format = number_format($count);
		if ($count < 1) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ هیچ ربات ویژه ای وجود ندارد.'
			]);
		}
		else {
			$answer_text_array = [];
	
			$i = 1;
			foreach ($fetch as $user) {
				$prepared_bot = $pdo->prepare("SELECT * FROM `{$user['bot']}_members`;");
				$prepared_bot->execute();
				$fetch_bot = $prepared_bot->fetchAll();
				$bot_count = number_format(count($fetch_bot));

				$get_chat = bot('getChat',
				[
					'chat_id'=>$user['admin']
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$user['admin']}";
				$user_name_mention = "<a href='$mention'>$name</a>";
				$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$user['admin']}'>👤 </a>";

				$start_time = jdate('Y/m/j H:i:s', $user['start']);
				$end_time = jdate('Y/m/j H:i:s', $user['end']);
				$time_elapsed = timeElapsed($user['end']-time());

				$bot_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid-{$user['bot']}'> 🤖 </a>";
				
				$answer_text_array[] = "<b>{$i}</b> -{$bot_info_link}@{$user['bot']}
⏳ <b>{$start_time}</b>
🧭 {$time_elapsed}
⌛️ <b>{$end_time}</b>
📊 <b>{$bot_count}</b> کاربر
{$user_info_link}{$user_name_mention}
🆔 <code>{$user['admin']}</code>";
				if ($i >= 10) break;
				$i++;
			}
		
			if ($division_10 <= 1) {
				$reply_markup = null;
			}
			else {
				if ($division_10 <= 2) {
					$reply_markup = json_encode(
						[
							'inline_keyboard' => [
								[
									['text'=>'«1»', 'callback_data'=>'vip_0_1'],
									['text'=>'2', 'callback_data'=>'vip_10_2']
								]
							]
						]
					);
				}
				else {
					$inline_keyboard = [];

					$inline_keyboard[0][0]['text'] = '«1»';
					$inline_keyboard[0][0]['callback_data'] = 'vip_0_1';

					for ($i = 1; ($i < myFloor($division_10) && $i < 4); $i++) {
						$inline_keyboard[0][$i]['text'] = ($i+1);
						$inline_keyboard[0][$i]['callback_data'] = 'vip_' . ($i*10) . '_' . ($i+1);
					}

					$inline_keyboard[0][$i]['text'] = (myFloor($division_10)+1);
					$inline_keyboard[0][$i]['callback_data'] = 'vip_' . (myFloor($division_10)*10) . '_' . (myFloor($division_10)+1);

					$reply_markup = json_encode([ 'inline_keyboard' => $inline_keyboard ]);
				}
			}

			$load_server = sys_getloadavg()[0];
			$ram = convert_size(memory_get_peak_usage(true));

			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'reply_markup'=>$reply_markup,
				'parse_mode'=>'html',
				'disable_web_page_preview'=>true,
				'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n🎖 تعداد ربات های ویژه : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
			]);
		}
	}
	elseif (preg_match('@^vip\_(?<offset>[0-9]+)\_(?<page>[0-9]+)$@', $update->callback_query->data, $matches)) {
		$offset = $matches['offset'];
		$page = $matches['page'];

		$res = $pdo->query("SELECT * FROM `vip_bots` ORDER BY `start` DESC;");
		$fetch = $res->fetchAll();
		$count = count($fetch);

		$count_format = number_format($count);

		$division_10 = ($count)/10;
		$floor = floor($division_10);
		$floor_10 = ($floor*10);
	
		##text
		$answer_text_array = [];
	
		$x = 1;
		$j = $offset + 1;
		for ($i = $offset; $i < $count; $i++) {

			$prepared_bot = $pdo->prepare("SELECT * FROM `{$fetch[$i]['bot']}_members`;");
			$prepared_bot->execute();
			$fetch_bot = $prepared_bot->fetchAll();
			$bot_count = number_format(count($fetch_bot));

			$get_chat = bot('getChat',
			[
				'chat_id'=>$fetch[$i]['admin']
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[$i]['admin']}";
			$user_name_mention = "<a href='$mention'>$name</a>";
			$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$fetch[$i]['admin']}'>👤 </a>";
			$bot_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid-{$fetch[$i]['bot']}'> 🤖 </a>";

			$start_time = jdate('Y/m/j H:i:s', $fetch[$i]['start']);
			$end_time = jdate('Y/m/j H:i:s', $fetch[$i]['end']);
			$time_elapsed = timeElapsed($fetch[$i]['end']-time());
			$answer_text_array[] = "<b>{$i}</b> -{$bot_info_link}@{$fetch[$i]['bot']}
⏳ <b>{$start_time}</b>
🧭 {$time_elapsed}
⌛️ <b>{$end_time}</b>
📊 <b>{$bot_count}</b> کاربر
{$user_info_link}{$user_name_mention}
🆔 <code>{$fetch[$i]['admin']}</code>";
			if ($x >= 10) break;
			$x++;
			$j++;
		}
	
		##keyboard
		$inline_keyboard = [];
	
		if ($division_10 <= 2) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "vip_10_2";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2]
			];
		}
		elseif ($division_10 <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "vip_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "vip_20_3";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3]
			];
		}
		elseif ($division_10 <= 4) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "vip_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "vip_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "vip_30_4";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4]
			];
		}
		elseif ($division_10 <= 5) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "vip_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "vip_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "vip_30_4";
	
			$text_5 = $page == 5 ? '«5»' : 5;
			$data_5 = "vip_40_5";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "vip_10_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "vip_20_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "vip_30_4";
	
			$text_5 = ($floor+1);
			$data_5 = "vip_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page >= ($floor-1)) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = $page == ($floor-2) ? '«' . $page . '»' : ($floor-2);
			$data_2 = 'vip_' . (($floor-3)*10) . '_' . ($floor-2);
	
			$text_3 = $page == ($floor-1) ? '«' . $page . '»' : ($floor-1);
			$data_3 = 'vip_' . (($floor-2)*10) . '_' . ($floor-1);
	
			$text_4 = $page == ($floor) ? '«' . $page . '»' : ($floor);
			$data_4 = 'vip_' . (($floor-1)*10) . '_' . ($floor);
	
			$text_5 = $page == ($floor+1) ? '«' . $page . '»' : ($floor+1);
			$data_5 = "vip_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		else {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "vip_0_1";
	
			$text_2 = ($page-1);
			$data_2 = 'vip_' . ($offset-10) . '_' . ($page-1);
	
			$text_3 = '«' . $page . '»';
			$data_3 = 'vip_' . $offset . '_' . $page;
	
			$text_4 = ($page+1);
			$data_4 = 'vip_' . ($offset+10) . '_' . ($page+1);
	
			$text_5 = ($floor+1);
			$data_5 = "vip_{$floor_10}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
	
		$reply_markup = json_encode(
			[
				'inline_keyboard' => $inline_keyboard
			]
		);

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('AnswerCallbackQuery',
		[
			'callback_query_id'=>$update->callback_query->id,
			'text'=>''
		]);

		bot('editMessagetext', [
			'chat_id'=>$chatid,
			'message_id'=>$messageid,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n🎖 تعداد ربات های ویژه : <b>$count_format</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
			'reply_markup'=>$reply_markup
		]);
	}
	elseif ($text == '⛔️ لیست کاربران مسدود') {
		sendAction($chat_id);
		$blacklist_array = array_reverse($list['ban']);
		$count = count($blacklist_array);
		$count_format = number_format($count);
	
		if ($count < 1) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ لیست کاربران مسدود خالی است.'
			]);
		}
		else {
			$division_20 = $count/20;
	
			$answer_text_array = [];
			$i = 1;
			foreach ($blacklist_array as $blacklist_user) {
				$get_chat = bot('getChat',
				[
					'chat_id'=>$blacklist_user
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$blacklist_user}";
				$answer_text_array[] = "<b>{$i}</b> - 🆔 <code>{$blacklist_user}</code>
👤 <a href='{$mention}'>{$name}</a>
/unban_{$blacklist_user}";
				if ($i >= 20) break;
				$i++;
			}
	
			if ($division_20 <= 1) {
				$reply_markup = null;
			}
			else {
				if ($division_20 <= 2) {
					$reply_markup = json_encode(
						[
							'inline_keyboard' => [
								[
									['text'=>'«1»', 'callback_data'=>'blacklist_0_1'],
									['text'=>'2', 'callback_data'=>'blacklist_10_2']
								]
							]
						]
					);
				}
				else {
					$inline_keyboard = [];
	
					$inline_keyboard[0][0]['text'] = '«1»';
					$inline_keyboard[0][0]['callback_data'] = 'blacklist_0_1';
	
					for ($i = 1; ($i < myFloor($division_20) && $i < 4); $i++) {
						$inline_keyboard[0][$i]['text'] = ($i+1);
						$inline_keyboard[0][$i]['callback_data'] = 'blacklist_' . ($i*10) . '_' . ($i+1);
					}
	
					$inline_keyboard[0][$i]['text'] = (myFloor($division_20)+1);
					$inline_keyboard[0][$i]['callback_data'] = 'blacklist_' . (myFloor($division_20)*10) . '_' . (myFloor($division_20)+1);
	
					$reply_markup = json_encode([ 'inline_keyboard' => $inline_keyboard ]);
				}
			}

			$load_server = sys_getloadavg()[0];
			$ram = convert_size(memory_get_peak_usage(true));

			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'reply_markup'=>$reply_markup,
				'parse_mode'=>'html',
				'disable_web_page_preview'=>true,
				'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n⛔️ تعداد کاربران مسدود : <b>{$count_format}</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
			]);
		}
	}
	elseif (preg_match('@blacklist\_(?<offset>[0-9]+)\_(?<page>[0-9]+)@', $update->callback_query->data, $matches)) {
		$offset = $matches['offset'];
		$page = $matches['page'];
	
		$blacklist_array = array_reverse($list['ban']);
		$count = count($blacklist_array);
		$count_format = number_format($count);
		$division_20 = $count/20;
		$floor = floor($division_20);
		$floor_20 = $floor*20;
	
		##text
		$answer_text_array = [];
		$x = 1;
		$j = $offset + 1;
		for ($i = $offset; $i < $count; $i++) {
			$get_chat = bot('getChat',
			[
				'chat_id'=>$blacklist_array[$i]
			]);
			$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
			$name = str_replace(['<', '>'], '', $name);
			$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$blacklist_array[$i]}";
			$answer_text_array[] = "<b>{$j}</b> - 🆔 <code>{$blacklist_array[$i]}</code>
👤 <a href='{$mention}'>{$name}</a>
/unban_{$blacklist_array[$i]}";
			if ($x >= 20) break;
			$x++;
			$j++;
		}
	
		##keyboard
		$inline_keyboard = [];
	
		if ($division_20 <= 2) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "blacklist_20_2";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2]
			];
		}
		elseif ($division_20 <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "blacklist_20_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "blacklist_40_3";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3]
			];
		}
		elseif ($division_20 <= 4) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "blacklist_20_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "blacklist_40_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "blacklist_60_4";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4]
			];
		}
		elseif ($division_20 <= 5) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "blacklist_20_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "blacklist_40_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "blacklist_60_4";
	
			$text_5 = $page == 5 ? '«5»' : 5;
			$data_5 = "blacklist_80_5";
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page <= 3) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == 2 ? '«2»' : 2;
			$data_2 = "blacklist_20_2";
	
			$text_3 = $page == 3 ? '«3»' : 3;
			$data_3 = "blacklist_40_3";
	
			$text_4 = $page == 4 ? '«4»' : 4;
			$data_4 = "blacklist_60_4";
	
			$text_5 = ($floor+1);
			$data_5 = "blacklist_{$floor_20}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		elseif ($page >= ($floor-1)) {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = $page == ($floor-2) ? '«' . $page . '»' : ($floor-2);
			$data_2 = 'blacklist_' . (($floor-3)*20) . '_' . ($floor-2);
	
			$text_3 = $page == ($floor-1) ? '«' . $page . '»' : ($floor-1);
			$data_3 = 'blacklist_' . (($floor-2)*20) . '_' . ($floor-1);
	
			$text_4 = $page == ($floor) ? '«' . $page . '»' : ($floor);
			$data_4 = 'blacklist_' . (($floor-1)*20) . '_' . ($floor);
	
			$text_5 = $page == ($floor+1) ? '«' . $page . '»' : ($floor+1);
			$data_5 = "blacklist_{$floor_20}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
		else {
			$text_1 = $page == 1 ? '«1»' : 1;
			$data_1 = "blacklist_0_1";
	
			$text_2 = ($page-1);
			$data_2 = 'blacklist_' . ($offset-20) . '_' . ($page-1);
	
			$text_3 = '«' . $page . '»';
			$data_3 = 'blacklist_' . $offset . '_' . $page;
	
			$text_4 = ($page+1);
			$data_4 = 'blacklist_' . ($offset+20) . '_' . ($page+1);
	
			$text_5 = ($floor+1);
			$data_5 = "blacklist_{$floor_20}_" . ($floor+1);
	
			$inline_keyboard[] = [
				['text' => $text_1, 'callback_data' => $data_1],
				['text' => $text_2, 'callback_data' => $data_2],
				['text' => $text_3, 'callback_data' => $data_3],
				['text' => $text_4, 'callback_data' => $data_4],
				['text' => $text_5, 'callback_data' => $data_5]
			];
		}
	
		$reply_markup = json_encode(
			[
				'inline_keyboard' => $inline_keyboard
			]
		);
	
		bot('AnswerCallbackQuery',
		[
			'callback_query_id'=>$update->callback_query->id,
			'text'=>''
		]);

		$load_server = sys_getloadavg()[0];
		$ram = convert_size(memory_get_peak_usage(true));

		bot('editMessagetext', [
			'chat_id'=>$chat_id,
			'message_id'=>$message_id,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⏱ بار روی هاست : <b>{$load_server}</b>\n🗃 رم مصرفی : <b>{$ram}</b>\n\n⛔️ تعداد کاربران مسدود : <b>{$count_format}</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
			'reply_markup'=>$reply_markup
		]);
	}
	##-------------------
	elseif ($text == '➕ اشتراک ویژه' || $text == '🔙 بازگشت به + اشتراک ویژه') {
		sendAction($chat_id);
		$data['step'] = 'set_vip';
		file_put_contents("Data/{$from_id}/data.json", json_encode($data));
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>'🔰 لطفا یوزرنیم ربات مورد نظرتان را ارسال کنید.',
			'reply_markup'=>$backpanel
		]);
	}
	elseif ($step == 'set_vip') {
		sendAction($chat_id);
		$bot_username = trim(strtolower(str_replace('@', '', $text)));
		if (is_dir("Bots/{$bot_username}")) {
			$data['step'] = "set_vip_{$bot_username}";
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
			$prepared = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
			$prepared->execute();
			$fetch = $prepared->fetchAll();
			if (count($fetch) > 0) {
				$get_chat = bot('getChat',
				[
					'chat_id'=>$fetch[0]['admin']
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[0]['admin']}";
				$user_name_mention = "<a href='$mention'>$name</a>";
				$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$fetch[0]['admin']}'>👤 </a>";
				$start_time = jdate('Y/m/j H:i:s', $fetch[0]['start']);
				$end_time = jdate('Y/m/j H:i:s', $fetch[0]['end']);
				$time_elapsed = timeElapsed($fetch[0]['end']-time());

				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'parse_mode'=>'html',
					'disable_web_page_preview'=>true,
					'text'=>"✅ اشتراک ویژه برای ربات @{$bot_username} فعال است.

⏳ <b>{$start_time}</b>
🧭 {$time_elapsed}
⌛️ <b>{$end_time}</b>
📊 <b>{$bot_count}</b> کاربر
{$user_info_link}{$user_name_mention}
🆔 <code>{$fetch[0]['admin']}</code>

🔰 می خواهید چند روز به آن اضافه کنید؟",
					'reply_markup'=>json_encode([
						'keyboard'=>[
							[['text'=>'🔙 بازگشت به + اشتراک ویژه']],
							[['text'=>'🔙 بازگشت به مدیریت']],
						],
						'resize_keyboard'=>true
					])
				]);
			}
			else {
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>"🎖 اشتراک ویژه برای ربات @{$bot_username} فعال نیست.

🔰 می خواهید اشتراک چند روزه برای آن فعال کنید؟",
					'reply_markup'=>json_encode([
						'keyboard'=>[
							[['text'=>'🔙 بازگشت به + اشتراک ویژه']],
							[['text'=>'🔙 بازگشت به مدیریت']],
						],
						'resize_keyboard'=>true
					])
				]);
			}
		}
		else {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ این ربات وجود ندارد.'
			]);
		}
	}
	elseif (preg_match('@^set\_vip\_(?<bot>.+)$@i', $step, $matches)) {
		$text = convert($text);
		$bot_username = $matches['bot'];
		$prepared = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (!is_numeric($text) || ((int) $text) < 1) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ لطفا یک مقدار معتبر وارد کنید.'
			]);
		}
		elseif (count($fetch) > 0) {
			$data['step'] = 'none';
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
			$config = file_get_contents("Bots/{$bot_username}/config.php");
			preg_match('/\$Dev\s=\s"(.*?)";/', $config, $match);
			$Dev = $match[1];
			preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
			$token = $match[1];

			$days = (int) $text;
			$second = $days*24*60*60;
			$new_end_time = $fetch[0]['end']+$second;
			$prepared = $pdo->prepare("UPDATE `vip_bots` SET `end`={$new_end_time}, alert=0 WHERE `bot`='{$bot_username}';");
			$prepared->execute();
			bot('sendMessage', [
				'chat_id'=>$Dev,
				'text'=>"✅ {$days} روز به زمان اشتراک ویژه ربات شما اضافه گردید."
			], $token);
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"✅ {$days} روز به زمان اشتراک ویژه ربات @{$bot_username} اضافه گردید.",
				'reply_markup'=>$panel
			]);
		}
		else {
			$data['step'] = 'none';
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
			$config = file_get_contents("Bots/{$bot_username}/config.php");
			preg_match('/\$Dev\s=\s"(.*?)";/', $config, $match);
			$Dev = $match[1];
			preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
			$token = $match[1];

			$days = (int) $text;
			$second = $days*24*60*60;
			$end_time = time()+$second;
			$prepare = $pdo->prepare("INSERT INTO `vip_bots` (`admin`, `bot`, `start`, `end`, `alert`) VALUES ('{$Dev}', '{$bot_username}', UNIX_TIMESTAMP(), '{$end_time}', 0);");
			$prepare->execute();

			bot('sendMessage', [
				'chat_id'=>$Dev,
				'text'=>"✅ اشتراک ویژه {$days} روزه برای ربات شما فعال گردید."
			], $token);
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"✅ اشتراک ویژه {$days} روزه برای ربات @{$bot_username} فعال گردید.",
				'reply_markup'=>$panel
			]);
		}
	}
	elseif ($text == '➖ اشتراک ویژه' || $text == '🔙 بازگشت به - اشتراک ویژه') {
		sendAction($chat_id);
		$data['step'] = 'del_vip';
		file_put_contents("Data/{$from_id}/data.json", json_encode($data));
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>'🔰 لطفا یوزرنیم ربات مورد نظرتان را ارسال کنید.',
			'reply_markup'=>$backpanel
		]);
	}
	elseif ($step == 'del_vip') {
		sendAction($chat_id);
		$bot_username = trim(str_replace('@', '', strtolower($text)));
		if (is_dir("Bots/{$bot_username}")) {
			$prepared = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
			$prepared->execute();
			$fetch = $prepared->fetchAll();
			if (count($fetch) > 0) {
				$data['step'] = "del_vip_{$bot_username}";
				file_put_contents("Data/{$from_id}/data.json", json_encode($data));
				$get_chat = bot('getChat',
				[
					'chat_id'=>$fetch[0]['admin']
				]);
				$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
				$name = str_replace(['<', '>'], '', $name);
				$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[0]['admin']}";
				$user_name_mention = "<a href='$mention'>$name</a>";
				$user_info_link = "<a href='https://telegram.me/" . str_replace('@', '', $main_bot) . "?start=uid{$fetch[0]['admin']}'>👤 </a>";
				$start_time = jdate('Y/m/j H:i:s', $fetch[0]['start']);
				$end_time = jdate('Y/m/j H:i:s', $fetch[0]['end']);
				$time_elapsed = timeElapsed($fetch[0]['end']-time());

				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'parse_mode'=>'html',
					'disable_web_page_preview'=>true,
					'text'=>"✅ اشتراک ویژه برای ربات @{$bot_username} فعال است.

⏳ <b>{$start_time}</b>
🧭 {$time_elapsed}
⌛️ <b>{$end_time}</b>
📊 <b>{$bot_count}</b> کاربر
{$user_info_link}{$user_name_mention}
🆔 <code>{$fetch[0]['admin']}</code>

🔰 می خواهید چند روز از آن کم کنید؟",
					'reply_markup'=>json_encode([
						'keyboard'=>[
							[['text'=>'🔙 بازگشت به - اشتراک ویژه']],
							[['text'=>'🔙 بازگشت به مدیریت']],
						],
						'resize_keyboard'=>true
					])
				]);
			}
			else {
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>"❌ اشتراک ویژه برای ربات @{$bot_username} فعال نیست."
				]);
			}
		}
		else {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ این ربات وجود ندارد.'
			]);
		}
	}
	elseif (preg_match('@^del\_vip\_(?<bot>.+)$@i', $step, $matches)) {
		$text = convert($text);
		$bot_username = $matches['bot'];
		$prepared = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (!is_numeric($text) || ((int) $text) < 1) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'❌ لطفا یک مقدار معتبر وارد کنید.'
			]);
		}
		elseif (count($fetch) > 0) {
			$data['step'] = 'none';
			file_put_contents("Data/{$from_id}/data.json", json_encode($data));
			$days = (int) $text;
			$second = $days*24*60*60;
			$new_end_time = $fetch[0]['end']-$second;
			if ($new_end_time <= time()) {
				$config = file_get_contents("Bots/{$bot_username}/config.php");
				preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
				$token = $match[1];
				$prepare = $pdo->prepare("DELETE FROM `vip_bots` WHERE `bot`='{$bot_username}';");
				$prepare->execute();
				bot('sendMessage', [
					'chat_id'=>$fetch[0]['admin'],
					'text'=>"⚠️ اشتراک ویژه ربات شما حذف گردید."
				], $token);
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>"⚠️ اشتراک ویژه ربات @{$bot_username} حذف گردید.",
					'reply_markup'=>$panel
				]);
			}
			else {
				$data['step'] = 'none';
				file_put_contents("Data/{$from_id}/data.json", json_encode($data));
				$config = file_get_contents("Bots/{$bot_username}/config.php");
				preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
				$token = $match[1];
				$prepared = $pdo->prepare("UPDATE `vip_bots` SET `end`={$new_end_time}, alert=0 WHERE `bot`='{$bot_username}';");
				$prepared->execute();
				bot('sendMessage', [
					'chat_id'=>$fetch[0]['admin'],
					'text'=>"⚠️ {$days} روز از زمان اشتراک ویژه ربات شما کسر گردید."
				], $token);
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>"⚠️ {$days} روز از زمان اشتراک ویژه ربات @{$bot_username} کسر گردید.",
					'reply_markup'=>$panel
				]);
			}
		}
		else {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ اشتراک ویژه برای ربات @{$bot_username} فعال نیست."
			]);
		}
	}
	##-------------------
	elseif ($text == '🔖 پیام همگانی') {
		sendAction($chat_id);
		$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`!='f2a';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ هنوز پیام قبلی شما در صف ارسال همگانی قرار دارد و برای کاربران ربات ارسال نشده است.
	
👇🏻 برای ثبت پیام همگانی جدید، ابتدا پیام همگانی قبلی را با استفاده از دستور زیر لغو کنید و یا اینکه منتظر بمانید تا پیام ارسال شدن آنرا دریافت نمایید.
	
/determents2a_{$fetch[0]['time']}"
			]);
		}
		else {
			$user_data = json_decode(file_get_contents("Data/$from_id/data.json"), true);
			$user_data['step'] = 's2a';
			file_put_contents("Data/{$from_id}/data.json", json_encode($user_data));
	
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'parse_mode'=>'markdown',
				'text'=>'📩 پیام مورد نظرتان را برای ارسال همگانی بفرستید.
🔴 شما می توانید از متغیر های زیر استفاده کنید.

▪️`FULL-NAME` 👉🏻 نام کامل کاربر
▫️`F-NAME` 👉🏻 نام کاربر
▪️`L-NAME` 👉🏻 نام خانوادگی کاربر
▫️`U-NAME` 👉🏻 نام کاربری کاربر 
▪️`TIME` 👉🏻 زمان به وقت ایران
▫️`DATE` 👉🏻 تاریخ
▪️`TODAY` 👉🏻 روز هفته',
				'reply_markup'=>$backpanel
			]);
		}
	}
	elseif ($step == 's2a') {
		sendAction($chat_id);
		$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`!='f2a';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ هنوز پیام قبلی شما در صف ارسال همگانی قرار دارد و برای کاربران ربات ارسال نشده است.
	
👇🏻 برای ثبت پیام همگانی جدید، ابتدا پیام همگانی قبلی را با استفاده از دستور زیر لغو کنید و یا اینکه منتظر بمانید تا پیام ارسال شدن آنرا دریافت نمایید.
	
/determents2a_{$fetch[0]['time']}"
			]);
		}
		else {
			if (isset($update->message->media_group_id)) {
				$is_file = is_file('Data/album-' . $update->message->media_group_id . '.json');
				$media_group = json_decode(@file_get_contents('Data/album-' . $update->message->media_group_id . '.json'), true);
		
				$media_type = isset($update->message->video) ? 'video' : 'photo';
				$media_file_id = isset($update->message->video) ? $update->message->video->file_id : $update->message->photo[count($update->message->photo)-1]->file_id;
				$media_group[] = [
					'type' => $media_type,
					'media' => $media_file_id,
					'caption' => isset($update->message->caption) ? $update->message->caption : ''
				];
		
				file_put_contents('Data/album-' . $update->message->media_group_id . '.json', json_encode($media_group));
		
				$data = [
					'media_group_id'=>$update->message->media_group_id
				];
		
				$type = 'media_group';
				if ($is_file) exit();
		
			}
			elseif (isset($update->message->photo)) {
				$data = [
					'file_id'=>$update->message->photo[count($update->message->photo)-1]->file_id
				];
				$type = 'photo';
			}
			elseif (isset($update->message->video)) {
				$data = [
					'file_id'=>$update->message->video->file_id
				];
				$type = 'video';
			}
			elseif (isset($update->message->animation)) {
				$data = [
					'file_id'=>$update->message->animation->file_id
				];
				$type = 'animation';
			}
			elseif (isset($update->message->audio)) {
				$data = [
					'file_id'=>$update->message->audio->file_id
				];
				$type = 'audio';
			}
			elseif (isset($update->message->document)) {
				$data = [
					'file_id'=>$update->message->document->file_id
				];
				$type = 'document';
			}
			elseif (isset($update->message->video_note)) {
				$data = [
					'file_id'=>$update->message->video_note->file_id
				];
				$type = 'video_note';
			}
			elseif (isset($update->message->voice)) {
				$data = [
					'file_id'=>$update->message->voice->file_id
				];
				$type = 'voice';
			}
			elseif (isset($update->message->sticker)) {
				$data = [
					'file_id' => $update->message->sticker->file_id
				];
				$type = 'sticker';
			}
			elseif (isset($update->message->contact)) {
				$data = [
					'phone_number' => $update->message->contact->phone_number,
					'phone_first' => $update->message->contact->first_name,
					'phone_last' => $update->message->contact->last_name
				];
				$type = 'contact';
			}
			elseif (isset($update->message->location)) {
				$data = [
					'longitude' => $update->message->location->longitude,
					'latitude' => $update->message->location->latitude
				];
				$type = 'location';
			}
			elseif (isset($update->message->text)) {
				$data = [
					'text' => utf8_encode($update->message->text)
				];
				$type = 'text';
			}
			else {
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>'❌ این پیام پشتیبانی نمی شود.
🔰 لطفا یک چیز دیگر ارسال نمایید.'
				]);
				exit();
			}
			$user_data = json_decode(file_get_contents("Data/$from_id/data.json"), true);
			$user_data['step'] = '';
			file_put_contents("Data/{$from_id}/data.json", json_encode($user_data));

			$caption = ( isset($update->caption) ? $update->caption : (isset($update->message->caption) ? $update->message->caption : '') );
			$data['caption'] = utf8_encode($caption);
			$data = json_encode($data);
			$time = time();
		
			$sql = "INSERT INTO `sendlist` (`user_id`, `offset`, `time`, `type`, `data`, `caption`) VALUES (:user_id, :offset, :time, :type, :data, :caption);";
			$prepare = $pdo->prepare($sql);
			$prepare->execute(['user_id'=>$user_id, 'offset'=>0, 'time'=>$time, 'type'=>$type, 'data'=>$data, 'caption'=>$caption]);
		
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"✅ پیام مورد نظر شما در صف ارسال همگانی قرار گرفت.
				
👇🏻 برای لغو ارسالی همگانی این پیام دستور زیر را بفرستید.
/determents2a_{$time}",
				'reply_markup'=>$panel
			]);
		}
	}
	elseif (isset($update->message->media_group_id) && is_file('Data/album-' . $update->message->media_group_id . '.json')) {
		$media_group = json_decode(@file_get_contents('Data/album-' . $update->message->media_group_id . '.json'), true);
	
		$media_type = isset($update->message->video) ? 'video' : 'photo';
		$media_file_id = isset($update->message->video) ? $update->message->video->file_id : $update->message->photo[count($update->message->photo)-1]->file_id;
		$media_group[] = [
			'type' => $media_type,
			'media' => $media_file_id,
			'caption' => isset($update->message->caption) ? $update->message->caption : ''
		];
	
		file_put_contents('Data/album-' . $update->message->media_group_id . '.json', json_encode($media_group));
	}
	elseif ($text == '🚀 هدایت همگانی') {
		sendAction($chat_id);
		$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`='f2a';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ هنوز پیام قبلی شما در صف هدایت همگانی قرار دارد و برای کاربران ربات هدایت نشده است.
	
👇🏻 برای ثبت هدایت همگانی جدید، ابتدا هدایت همگانی قبلی را با استفاده از دستور زیر لغو کنید و یا اینکه منتظر بمانید تا پیام هدایت شدن آنرا دریافت نمایید.

/determentf2a_{$fetch[0]['time']}"
			]);
		}
		else {
			$user_data = json_decode(file_get_contents("Data/$from_id/data.json"), true);
			$user_data['step'] = 'f2a';
			file_put_contents("Data/{$from_id}/data.json", json_encode($user_data));
	
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>'🚀 پیام مورد نظرتان را برای هدایت همگانی بفرستید.',
				'reply_markup'=>$backpanel
			]);
		}
	}
	elseif ($step == 'f2a') {
		sendAction($chat_id);
		$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`='f2a';");
		$prepared->execute();
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ هنوز پیام قبلی شما در صف هدایت همگانی قرار دارد و برای کاربران ربات هدایت نشده است.
	
👇🏻 برای ثبت هدایت همگانی جدید، ابتدا هدایت همگانی قبلی را با استفاده از دستور زیر لغو کنید و یا اینکه منتظر بمانید تا پیام هدایت شدن آنرا دریافت نمایید.

/determentf2a_{$fetch[0]['time']}"
			]);
		}
		else {
			$user_data = json_decode(file_get_contents("Data/$from_id/data.json"), true);
			$user_data['step'] = '';
			file_put_contents("Data/{$from_id}/data.json", json_encode($user_data));
	
			$sql = "INSERT INTO `sendlist` (`user_id`, `offset`, `time`, `type`, `data`, `caption`) VALUES (:user_id, :offset, :time, :type, :data, :caption);";
			$prepare = $pdo->prepare($sql);
	
			$data = [
				'message_id' => $message_id,
				'from_chat_id' => $chat_id
			];
			$time = time();
			$prepare->execute(['user_id'=>$user_id, 'offset'=>0, 'time'=>$time, 'type'=>'f2a', 'data'=>json_encode($data), 'caption'=>'']);
			
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"✅ پیام مورد نظر شما در صف هدایت همگانی قرار گرفت.
	
👇🏻 برای لغو هدایت همگانی این پیام دستور زیر را بفرستید.
/determentf2a_{$time}",
				'reply_markup'=>$panel
			]);
		}
	}
	elseif (preg_match('@\/determent(?<type>f2a|s2a|gift)\_(?<time>[0-9]+)@i', $text, $matches)) {
		sendAction($chat_id);
		$type = $matches['type'];
		$time = $matches['time'];
		if ($type == 's2a') {
			$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`!='f2a' AND `time`=:time;");
			$prepared->execute(['time' => $time]);
			$fetch = $prepared->fetchAll();
			if (count($fetch) > 0) {
				$prepare = $pdo->prepare("DELETE FROM `sendlist` WHERE `user_id`={$user_id} AND `time`=:time;");
				$prepare->execute(['time' => $time]);
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>'✅ پیام مورد نظر شما از صف ارسال همگانی خارج شد.'
				]);
			}
			else {
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>'❌ هیچ پیامی با این شناسه وجود ندارد.'
				]);
			}
		}
		elseif ($type == 'f2a') {
			$prepared = $pdo->prepare("SELECT * FROM `sendlist` WHERE `type`='f2a' AND `time`=:time;");
			$prepared->execute(['time' => $time]);
			$fetch = $prepared->fetchAll();
			if (count($fetch) > 0) {
				$prepare = $pdo->prepare("DELETE FROM `sendlist` WHERE `user_id`={$user_id} AND `time`=:time;");
				$prepare->execute(['time' => $time]);
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>'✅ پیام مورد نظر شما از صف هدایت همگانی خارج شد.'
				]);
			}
			else {
				bot('sendMessage', [
					'chat_id'=>$chat_id,
					'reply_to_message_id'=>$message_id,
					'text'=>'❌ هیچ پیامی با این شناسه وجود ندارد.'
				]);
			}
		}
	}
	##-------------------
	elseif (preg_match('|/backup\s?\_?@?(?<bot>[a-zA-Z0-9\_]+bot)|ius', $text, $matches)) {
		$botid = strtolower($matches['bot']);
		if (is_dir("Bots/$botid/")) {
			sendAction($chat_id, 'upload_document');
			$prepared = $pdo->prepare("SELECT * FROM `{$botid}_members`;");
			$prepared->execute();
			$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
			file_put_contents("Bots/{$botid}/data/members.json", json_encode($fetch));
			$file_to_zip = array(
				"Bots/{$botid}/data/list.json",
				"Bots/{$botid}/data/data.json",
				"Bots/{$botid}/data/members.json"
			);
			$file_name = date('Y-m-d') . '_' . $botid . '_backup.zip';
			CreateZip($file_to_zip, $file_name, "{$botid}_147852369");
			$time = date('Y/m/d - H:i:s');
			bot('sendDocument', [
				'chat_id' => $chat_id,
				'parse_mode' => 'html',
				'document' => $zipfile = new CURLFile($file_name),
				'caption' => "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>\n\n👆🏻 این فایل شامل تمامی اطلاعات ربات @{$botid} است.",
				'reply_markup' => $keyboard
			]);
			unlink($file_name);
			unlink("Bots/{$botid}/data/members.json");
		}
		else {
			sendAction($chat_id);
			sendMessage($chat_id, "❌ هیچ رباتی با یوزرنیم @$botid وجود ندارد.", 'markdown', $message_id, $backpanel);
		}
	}
	elseif ($text == "✖️ حذف ربات") {
		sendAction($chat_id);
		$data['step'] = "deletebot";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "🤖 یوزرنیم ربات مورد نظر خود را ارسال نمایید.", 'markdown', $message_id, $backpanel);
	}
	elseif ($step == "deletebot" and isset($text)) {
		sendAction($chat_id);
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		$id = strtolower(trim(str_replace("@", null, $text)));
		$botid = $id;
		if (is_dir("Bots/$id/")) {
			sendAction($chat_id, 'upload_document');
			$prepared = $pdo->prepare("SELECT * FROM `{$botid}_members`;");
			$prepared->execute();
			$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
			file_put_contents("Bots/{$botid}/data/members.json", json_encode($fetch));
			$file_to_zip = array(
				"Bots/{$botid}/data/list.json",
				"Bots/{$botid}/data/data.json",
				"Bots/{$botid}/data/members.json"
			);
			$file_name = date('Y-m-d') . '_' . $botid . '_backup.zip';
			CreateZip($file_to_zip, $file_name, "{$botid}_147852369");
			$time = date('Y/m/d - H:i:s');
			bot('sendDocument', [
				'chat_id' => $chat_id,
				'parse_mode' => 'html',
				'document' => $zipfile = new CURLFile($file_name),
				'caption' => "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>\n\n👆🏻 این فایل شامل تمامی اطلاعات ربات @{$botid} است.",
				'reply_markup' => $keyboard
			]);
			unlink($file_name);
			unlink("Bots/{$botid}/data/members.json");

			$config = file_get_contents('Bots/' . $id . '/config.php');
			preg_match_all('/\$Dev\s=\s"(.*?)";/', $config, $match);
			preg_match_all('/\$Token\s=\s"(.*?)";/', $config, $matchh);
			file_get_contents("https://api.telegram.org/bot".$matchh[1][0]."/deleteWebHook");
			$sdminn = $match[1][0];
			$data = json_decode(file_get_contents('Data/' . $sdminn . '/data.json'), true);
			$search = array_search('@' . $id, $data['bots']);
			unset($data['bots'][$search]);
			$data['bots'] = array_values($data['bots']);
			file_put_contents('Data/' . $sdminn . '/data.json', json_encode($data));
			sendMessage($sdminn, "🤖 ربات شما « @{$id} » توسط مدیریت حذف گردید.", null, $message_id, $panel);
			deleteFolder('Bots/' . $id . '/');
			sendMessage($chat_id, "🤖 ربات « @{$id} » با موفقیت حذف گردید.", null, $message_id, $panel);

			$pdo->exec("DROP TABLE IF EXISTS `{$id}_members`;");
			$prepare = $pdo->prepare("DELETE FROM `bots` WHERE `username`='{$id}';");
			$prepare->execute();

			$prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `bot_username`='{$id}';");
			$prepare->execute();
		} else {
			sendMessage($chat_id, "❌ هیچ رباتی با یوزرنیم « @{$id} » یافت نشد.", null, $message_id, $panel);
		}
	}
	elseif ($text == "🔒 مسدود کردن") {
		sendAction($chat_id);
		$data['step'] = "banuser";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "👤 شناسه تلگرامی کاربر مورد نظر خود را ارسال نمایید.", 'markdown', $message_id, $backpanel);
	}
	elseif ($step == "banuser" and is_numeric($text)) {
		sendAction($chat_id);
		$data['step'] = '';
		file_put_contents("Data/$from_id/data.json", json_encode($data));
		if ($text == $from_id) {
			sendMessage($chat_id, "⛔️ شما نمی توانید خودتان را مسدود کنید.", 'markdown', null, $panel);
		}
		elseif (!in_array($text, $list['ban'])) {
			$user_bots = json_decode(file_get_contents('Data/' . $text . '/data.json'), true)['bots'];
			if (count($user_bots) > 0) {
				foreach ($user_bots as $bot) {
					sendAction($chat_id, 'upload_document');
					$bot = str_replace('@', '', $bot);
					$botid = $bot;
					$prepared = $pdo->prepare("SELECT * FROM `{$botid}_members`;");
					$prepared->execute();
					$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
					file_put_contents("Bots/{$botid}/data/members.json", json_encode($fetch));
					$file_to_zip = array(
						"Bots/{$botid}/data/list.json",
						"Bots/{$botid}/data/data.json",
						"Bots/{$botid}/data/members.json"
					);
					$file_name = date('Y-m-d') . '_' . $botid . '_backup.zip';
					CreateZip($file_to_zip, $file_name, "{$botid}_147852369");
					$time = date('Y/m/d - H:i:s');
					bot('sendDocument', [
						'chat_id' => $chat_id,
						'parse_mode' => 'html',
						'document' => $zipfile = new CURLFile($file_name),
						'caption' => "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>\n\n👆🏻 این فایل شامل تمامی اطلاعات ربات @{$botid} است.",
						'reply_markup' => $keyboard
					]);
					unlink($file_name);
					unlink("Bots/{$bot}/data/members.json");

					sendMessage($text, "🤖 ربات « @{$bot} » توسط مدیریت حذف گردید.");
					deleteFolder('Bots/' . $bot . '/');
					$config = file_get_contents('Bots/' . $bot . '/config.php');
					preg_match('/\$Token\s=\s"(.*?)";/', $config, $matches);
					file_get_contents('https://api.telegram.org/bot' . $matches[1] . '/deleteWebhook');

					$pdo->exec("DROP TABLE IF EXISTS `{$bot}_members`;");
					$prepare = $pdo->prepare("DELETE FROM `bots` WHERE `username`='{$bot}';");
					$prepare->execute();

					$prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `bot_username`='{$bot}';");
					$prepare->execute();
				}
			}
			deleteFolder('Data/' . $text . '/');

			$list['ban'][] = $text;
			file_put_contents('Data/list.json', json_encode($list));
			sendMessage($text, "❌ شما مسدود شدید و دیگر ربات به پیام های شما جواب نخواهد داد.", null, null, $remove);
			sendMessage($chat_id, "⛔️ کاربر « [$text](tg://user?id=$text) » با موفقیت مسدود شد.", 'markdown', null, $panel);
		}
		else {
			sendMessage($chat_id, "⛔️ کاربر « [$text](tg://user?id=$text) » از قبل مسدود است.", 'markdown', null, $panel);
		}
	}
	elseif ($text == "🔓 آزاد کردن") {
		sendAction($chat_id);
		$data['step'] = "unbanuser";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		sendMessage($chat_id, "👤 شناسه تلگرامی کاربر مورد نظر خود را ارسال نمایید.", 'markdown', $message_id, $backpanel);
	}
	elseif ($step == "unbanuser" and is_numeric($text)) {
		sendAction($chat_id);
		$data['step'] = "none";
		file_put_contents("Data/$from_id/data.json",json_encode($data));
		if (in_array($text, $list['ban'])) {
			$search = array_search($text, $list['ban']);
			unset($list['ban'][$search]);
			$list['ban'] = array_values($list['ban']);
			file_put_contents("Data/list.json",json_encode($list, true));
			sendMessage($chat_id, "✅ کاربر « [$text](tg://user?id=$text) » با موفقیت آزاد شد.", 'markdown', null, $panel);
			sendMessage($text, "✅ شما آزاد شدید.\n\n💠 دستور /start را ارسال نمایید.", 'markdown', null);
		}
		else
		sendMessage($text, '❌ این کاربر در لیست سیاه نیست.', 'markdown', null);
	}
	elseif (preg_match("|\/unban([\_\s])([0-9]+)|i", $text, $match)) {
		sendAction($chat_id);
		if (in_array($match[2], $list['ban'])) {
			$search = array_search($match[2], $list['ban']);
			unset($list['ban'][$search]);
			$list['ban'] = array_values($list['ban']);
			file_put_contents("Data/list.json",json_encode($list, true));
			sendMessage($chat_id, "✅ کاربر « [$match[2]](tg://user?id=$match[2]) » با موفقیت آزاد شد.", 'markdown', null, $panel);
			$menu = json_encode(['keyboard'=>[
				[['text'=>'🤖 ربات های من'],['text'=>'🔰 ساخت ربات']],
				[['text'=>'🌈 ثبت تبلیغ']],
				[['text'=>'📕 قوانین'],['text'=>'📖 راهنما']]
			], 'resize_keyboard'=>true]);
			sendMessage($match[2], "✅ شما آزاد شدید.\n\n💠 دستور /start را ارسال نمایید.", 'markdown', null, $menu);
		}
		else
		sendMessage($chat_id, '❌ این کاربر در لیست سیاه نیست.', 'markdown', null);
	}
}
@unlink('error_log');