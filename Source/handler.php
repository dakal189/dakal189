<?php
define('API_KEY', $Token);
$ex = explode('/', $Folder_url);
$bot_username = trim($ex[count($ex)-2]);
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
	$forward = $message->forward_from;
	$forward_id = $message->forward_from->id;
	$sticker_id = $message->sticker->file_id;
	$video_id = $message->video->file_id;
	$voice_id = $message->voice->file_id;
	$file_id = $message->document->file_id;
	$music_id = $message->audio->file_id;
	$photo0_id = $message->photo[0]->file_id;
	$photo1_id = $message->photo[1]->file_id;
	$photo2_id = $message->photo[2]->file_id;
}
elseif (isset($update->callback_query)) {
	$callback_query = $update->callback_query;
	$callback_data = $callback_query->data;
	$data_id = $callback_query->id;
	$chatid = $update->callback_query->message->chat->id;
	$chat_id = $update->callback_query->message->chat->id;
	$fromid = $update->callback_query->from->id;
	$from_id = $update->callback_query->from->id;
	$user_id = $fromid;
	$tccall = $callback_query->message->chat->type;
	$messageid = $callback_query->message->message_id;
}
elseif (isset($update->inline_query->id)) {
	$getChat = getChat($Dev, false);
	$DevName = str_replace(['[', ']', '*', '_', '`'], '', $getChat->result->first_name);
	$user_id = $update->inline_query->from->id;

	//if ($user_id == $Dev) {
		bot('answerInlineQuery', [
			'inline_query_id'=>$update->inline_query->id,
			'cache_time'=>2000,
			'is_personal'=>true,
			'results'=>json_encode(
				[
					[
						'type'=>'article',
						'thumb_url'=>'https://t.me/PublicLogChannel/128',
						'id'=> base64_encode(1),
						'title'=>'🎮 بازی دوز',
						'input_message_content'=>
						[
							'parse_mode'=>'markdown',
							'message_text'=>"🎮 *بازی دوز*
	
👇🏻 برای پیوستن به بازی *دوز* بر روی دکمه زیر بزنید.",
							'disable_web_page_preview'=>true
						],
						'reply_markup'=>
						[
							'inline_keyboard'=>
							[
								[['text'=>'🧩 بازی می کنم!', 'callback_data'=>"palyxo_{$user_id}"]]
							]
						]
					],
					[
						'type'=>'article',
						'thumb_url'=>'https://t.me/PublicLogChannel/127',
						'id'=> base64_encode(2),
						'title'=>'💠 معرفی پیامرسان',
						'input_message_content'=>
						[
							'parse_mode'=>'markdown',
							'message_text'=>"🤖 ربات پیامرسان [$DevName](tg://user?id=$Dev)
	
😁 با استفاده از این ربات حتی در صورت ریپورت بودن می توانید با [$DevName](tg://user?id=$Dev) در ارتباط باشید.
👇🏻 برای رفتن به ربات پیامرسان [$DevName](tg://user?id=$Dev) بر روی دکمه زیر ضربه بزنید.",
							'disable_web_page_preview'=>true
						],
						'reply_markup'=>
						[
							'inline_keyboard'=>
							[
								[['text'=>'🤖 ربات پیامرسان', 'url'=>"https://telegram.me/{$bot_username}"]]
							]
						]
					]
				]
			)
		]);
	/*}
	else {
		bot('answerInlineQuery', [
			'inline_query_id'=>$update->inline_query->id,
			'cache_time'=>2000,
			'is_personal'=>true,
			'results'=>json_encode(
				[
					[
						'type'=>'article',
						'thumb_url'=>'https://t.me/PublicLogChannel/127',
						'id'=> base64_encode(1),
						'title'=>'💠 معرفی پیامرسان',
						'input_message_content'=>
						[
							'parse_mode'=>'markdown',
							'message_text'=>"🤖 ربات پیامرسان [$DevName](tg://user?id=$Dev)
	
😁 با استفاده از این ربات حتی در صورت ریپورت بودن می توانید با [$DevName](tg://user?id=$Dev) در ارتباط باشید.
👇🏻 برای رفتن به ربات پیامرسان [$DevName](tg://user?id=$Dev) بر روی دکمه زیر ضربه بزنید.",
							'disable_web_page_preview'=>true
						],
						'reply_markup'=>
						[
							'inline_keyboard'=>
							[
								[['text'=>'🤖 ربات پیامرسان', 'url'=>"https://telegram.me/{$bot_username}"]]
							]
						]
					]
				]
			)
		]);
	}*/
	exit();
}
else {
	exit();
}
##DB
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
$pdo->exec('SET NAMES utf8mb4');
##----------------------
if (!is_dir('data')) {
	mkdir('data');
}
if (!is_file('data/list.json')) {
	file_put_contents('data/list.json', json_encode(
		[
			'ban' => [],
			'user'=>[],
			'admin'=>[]
		]
	));
}
if (!is_file('data/data.json')) {
	file_put_contents('data/data.json', json_encode([]));
}
if (!is_file('data/flood.json')) {
	file_put_contents('data/flood.json', json_encode(
		[
			'flood' => []
		]
	));
}
##----------------------
$data = [];
$list = json_decode(file_get_contents('data/list.json'), true);
$data = json_decode(file_get_contents('data/data.json'), true);
$data_2 = $data;
##----------------------
$getd = bot('getChatMember',[
'chat_id' => $main_channel,
'user_id' => $Dev], API_KEY_CR);

if (isset($getd['result']['status'])) $rankdev = $getd['result']['status'];
else $rankdev = 'member';
##----------------------
if ($data['button']['profile']['stats'] != '⛔️') {
	if (empty($data['button']['profile']['name'])) {
		$profile_key = '📬 پروفایل';
	} else {
		$profile_key = $data['button']['profile']['name'];
	}
}
else {
	$profile_key = null;
}
if ($data['button']['contact']['stats'] != '⛔️') {
	if (empty($data['button']['contact']['name'])) {
		$contact_key = '☎️ ارسال شماره';
	} else {
		$contact_key = $data['button']['contact']['name'];
	}
}
else {
	$contact_key = null;
}
if ($data['button']['location']['stats'] != '⛔️') {
	if (empty($data['button']['location']['name'])) {
		$location_key = '🗺 ارسال مکان';
	} else {
		$location_key = $data['button']['location']['name'];
	}
}
else {
	$location_key = null;
}

if (isset($data['count-button']) && is_numeric($data['count-button'])) {
	$button_count = (int) $data['count-button'];
}
else {
	$button_count = 2;
}
//--------[Buttons]--------//
if ($profile_key == null && $contact_key == null && $location_key == null && !isset($data['buttons'])) {
	$button_user = json_encode(['KeyboardRemove'=>[], 'remove_keyboard'=>true]);
}
elseif ($profile_key == null && $contact_key == null && $location_key == null && isset($data['buttons'][0])) {
	$button_user = [];

	$i = 0;
	$j = 1;
	foreach ($data['buttons'] as $key => $name) {
		if (!is_null($key) && !is_null($name)) {
			$button_user[$i][] = ['text'=>$name];
			if ($j >= $button_count) {
				$i++;
				$j = 1;
			}
			else {
				$j++;
			}
		}
	}
	$button_user = json_encode(['keyboard'=> $button_user , 'resize_keyboard'=>true]);
}
else {
	$button_user = [];

	$i = 0;
	$j = 1;
	foreach ($data['buttons'] as $key => $name) {
		if (!is_null($key) && !is_null($name)) {
			$button_user[$i][] = ['text'=>$name];
			if ($j >= $button_count) {
				$i++;
				$j = 1;
			}
			else {
				$j++;
			}
		}
	}

	if (!is_null($profile_key)) {
		$button_user[] = [ ['text'=>$profile_key] ];
	}
	if (!is_null($contact_key)) {
		$two_key[] = ['text'=>$contact_key, 'request_contact' => true];
	}
	if (!is_null($location_key)) {
		$two_key[] = ['text'=>$location_key, 'request_location' => true];
	}
	if (!is_null($two_key)) {
		$button_user[] = $two_key;
	}

	$button_user = json_encode(['keyboard'=> $button_user , 'resize_keyboard'=>true]);
}

$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `bot`='{$bot_username}';");
$prepared_vip->execute();
$fetch_vip = $prepared_vip->fetchAll();
if (count($fetch_vip) > 0) {
	$is_vip = true;
	if ($fetch_vip[0]['end'] <= time()) {
		$prepare = $pdo->prepare("DELETE FROM `vip_bots` WHERE `bot`='{$bot_username}';");
		$prepare->execute();
		bot('sendMessage', [
			'chat_id'=>$fetch_vip[0]['admin'],
			'text'=>"⚠️ زمان اشتراک ویژه ربات شما به پایان رسید."
		]);

		$start_time = jdate('Y/m/j H:i:s', $fetch_vip[0]['start']);
		$end_time = jdate('Y/m/j H:i:s', $fetch_vip[0]['end']);
		$time_elapsed = timeElapsed($fetch_vip[0]['end']-$fetch_vip[0]['start']);
                
                bot('sendMessage', [
                        'chat_id'=>$logchannel,
                        'parse_mode'=>'html',
                        'text'=>"⚠️ زمان اشتراک ویژه ربات @{$fetch_vip[0]['bot']} به پایان رسید.
                        
⏳ زمان شروع : <b>{$start_time}</b>
🧭 زمان سپری شده : {$time_elapsed}
⌛️ زمان پایان : <b>{$end_time}</b>

👤 <code>{$fetch_vip[0]['admin']}</code>"
		], API_KEY_CR);
		
		$is_vip = false;
	}
	elseif ($fetch_vip[0]['alert'] != 1 && $fetch_vip[0]['end']-time() <= 24*60*60) {
		$time_elapsed = timeElapsed($fetch_vip[0]['end']-time());
		$prepared = $pdo->prepare("UPDATE `vip_bots` SET `alert`=1 WHERE `bot`='{$bot_username}';");
		$prepared->execute();
		bot('sendMessage', [
			'chat_id'=>$fetch_vip[0]['admin'],
			'parse_mode'=>'html',
			'text'=>"⚠️ تنها {$time_elapsed} از زمان اشتراک ویژه ربات شما باقی مانده است."
		]);
	}
}
else {
	$is_vip = false;
}

if ($data['stats'] == 'off') {
$panel = json_encode(['keyboard'=>[
[['text'=>'💡 روشن کردن ربات']],
[['text'=>"📕 راهنما"]],
[['text'=>"⛔️ کاربران مسدود"],['text'=>"📊 آمار"]],
[['text'=>'✉️ پیام همگانی'],['text'=>'🚀 هدایت همگانی']],
[['text'=>'🎲 سرگرمی']],
[['text'=>'⌨️ دکمه ها'],['text'=>'✉️ پیغام ها']],
[['text'=>'💻 پاسخ خودکار'],['text'=>'⛔️ فیلتر کلمه']],
[['text'=>'☎️ شماره من'],['text'=>'👨🏻‍💻 ادمین ها']],
[['text'=>'📣 قفل کانال ها'],['text'=>'🔐 قفل ها']],
[['text'=>'📝 پیام خصوصی'],['text'=>'👤 اطلاعات کاربر']],
[['text'=>'📤 بارگذاری پشتیبان'],['text'=>'📥 دریافت پشتیبان']],
[['text'=>'🎖 اشتراک ویژه'],['text'=>'🗑 پاکسازی']],
[['text'=>'🔙 خروج از مدیریت']]
], 'resize_keyboard'=>true]);
} else {
$panel = json_encode(['keyboard'=>[
[['text'=>"📕 راهنما"]],
[['text'=>"⛔️ کاربران مسدود"],['text'=>"📊 آمار"]],
[['text'=>'✉️ پیام همگانی'],['text'=>'🚀 هدایت همگانی']],
[['text'=>'🎲 سرگرمی']],
[['text'=>'⌨️ دکمه ها'],['text'=>'✉️ پیغام ها']],
[['text'=>'💻 پاسخ خودکار'],['text'=>'⛔️ فیلتر کلمه']],
[['text'=>'☎️ شماره من'],['text'=>'👨🏻‍💻 ادمین ها']],
[['text'=>'📣 قفل کانال ها'],['text'=>'🔐 قفل ها']],
[['text'=>'📝 پیام خصوصی'],['text'=>'👤 اطلاعات کاربر']],
[['text'=>'📤 بارگذاری پشتیبان'],['text'=>'📥 دریافت پشتیبان']],
[['text'=>'🎖 اشتراک ویژه'],['text'=>'🗑 پاکسازی']],
[['text'=>'🔌 خاموش کردن ربات']],
[['text'=>'🔙 خروج از مدیریت']]
], 'resize_keyboard'=>true]);
}
//------------------------------------------------------Zir Menu
$peygham = json_encode(['keyboard'=>[
[['text'=>'✅ متن ارسال'],['text'=>'🗒 متن شروع']],
[['text'=>'📬 متن پروفایل']],
[['text'=>'📣 متن قفل کانال ها']],
[['text'=>'🔌 متن خاموش بودن ربات']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true]);
$quick = json_encode(['keyboard'=>[
[['text'=>'➖ حذف کلمه'],['text'=>'➕ افزودن کلمه']],
[['text'=>'📑 لیست پاسخ ها']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true]);
$button = json_encode(['keyboard'=>[
[['text'=>'➖ حذف دکمه'],['text'=>'➕ افزودن دکمه']],
[['text'=>'📃 نام دکمه ها'],['text'=>'⌨️ وضعیت دکمه ها']],
[['text'=>'💠 تعداد دکمه ها در هر ردیف']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true]);
$button_tools = json_encode(['keyboard'=>[
[['text'=>'📥 دانلودر'],['text'=>'📤 آپلودر']],
[['text'=>'〽️ ساختن و خواندن QrCode']],
[['text'=>'📿 ذکر روز هفته'],['text'=>'🕋 حدیث']],
[['text'=>'😂 متن های طنز']],
[['text'=>'🗣 دیالوگ ماندگار'], ['text'=>'❤️ متن عاشقانه']],
[['text'=>'🏳️‍🌈 مترجم'],['text'=>'🖊 زیبا سازی متن']],
[['text'=>'🙏🏻 فال حافظ']],
[['text'=>'🖼 استیکر به تصویر'],['text'=>'🏞 تصویر به استیکر']],
[['text' => '👦🏻👱🏻‍♀️ تشخیص چهرهٔ انسان']],
[['text'=>'🌐 تصویر از سایت'],['text'=>'🎨 تصویر تصادفی']],
[['text'=>'🐼 تصویر پاندا'],['text'=>'🦅 تصویر پرنده']],
[['text'=>'🐶 تصویر سگ'],['text'=>'🐱 تصویر گربه']],
[['text'=>'🐨 تصویر کوآلا'],['text'=>'🦊 تصویر روباه']],
[['text'=>'😜 گیف چشمک زدن'],['text'=>'🙃 گیف نوازش']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
$button_texts = json_encode(
[
'keyboard'=>[
[['text'=>'😹 خاطره'],['text'=>'😂 لطیفه']],
[['text'=>'🤓 ... چیست؟'],['text'=>'🤪 ... است دیگر!']],
[['text'=>'😌 الکی مثلا'],['text'=>'😜 دقت کردین؟']],
[['text'=>'😁 پ ن پ'],['text'=>'🙃 مورد داشتیم']],
[['text'=>'⚽️ ورزشی'],['text'=>'😝 جمله سازی']],
[['text'=>'🐼 حیوانات'],['text'=>'🤯 امتحانات']],
[['text'=>'🙃 فانتزیم اینه!'],['text'=>'😅 اعتراف میکنم']],
[['text'=>'🥺 یه وقت زشت نباشه!'],['text'=>'😄 فک و فامیله داریم؟']],
[['text'=>'🗣 به بعضیا باید گفت']],
[['text'=>'🔙 بازگشت به بخش سرگرمی']]
],
'resize_keyboard'=>true
]
);
$languages = json_encode(['keyboard'=>[
[['text'=>'🇺🇸 انگلیسی'],['text'=>'🇮🇷 فارسی']],
[['text'=>'🇷🇺 روسی'],['text'=>'🇸🇦 عربی']],
[['text'=>'🇹🇷 ترکی'],['text'=>'🇫🇷 فرانسوی']],
[['text'=>'🔙 بازگشت به بخش سرگرمی']]
], 'resize_keyboard'=>true);
$button_name = json_encode(['keyboard'=>[
[['text'=>'پروفایل']],
[['text'=>'ارسال شماره'],['text'=>'ارسال مکان']],
[['text'=>'↩️ بازگشت']]
], 'resize_keyboard'=>true);
$button_filter = json_encode(['keyboard'=>[
[['text'=>'➖ حذف فیلتر'],['text'=>'➕ افزودن فیلتر']],
[['text'=>'📑 لیست فیلتر']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
$button_admins = json_encode(['keyboard'=>[
[['text'=>'➖ حذف ادمین'],['text'=>'➕ افزودن ادمین']],
[['text'=>'👨🏻‍💻 لیست ادمین ها']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
$reset = json_encode(['keyboard'=>[
[['text'=>'✅ بله، کاملا مطمئن هستم']],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
$contact = json_encode(['keyboard'=>[
[['text'=>'📞 شماره من']],
[['text'=>'☎️ تنظیم شماره', 'request_contact'=>true]],
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
##----------------------Back
$back = json_encode(['keyboard'=>[
[['text'=>'🔙 بازگشت']]
], 'resize_keyboard'=>true);
$back_to_channels = json_encode(['keyboard'=>[
[['text'=>'🔙 برگشت']]
], 'resize_keyboard'=>true);
$backans = json_encode(['keyboard'=>[
[['text'=>'↩️ برگشت ']]
], 'resize_keyboard'=>true);
$backbtn = json_encode(['keyboard'=>[
[['text'=>'↩️ بازگشت']]
], 'resize_keyboard'=>true);
$backto = json_encode(['keyboard'=>[
[['text'=>'🔙 بازگشت به بخش سرگرمی']]
], 'resize_keyboard'=>true);
$remove = json_encode(['KeyboardRemove'=>[], 'remove_keyboard'=>true]);
##----------------------Inline
$profile_btn = $data['button']['profile']['stats'];
$contact_btn = $data['button']['contact']['stats'];
$location_btn = $data['button']['location']['stats'];
$btnstats = json_encode(['inline_keyboard'=>[
[['text'=>"پروفایل $profile_btn", 'callback_data'=>"profile"]],
[['text'=>"ارسال شماره $contact_btn", 'callback_data'=>"contact"]],
[['text'=>"ارسال مکان $location_btn", 'callback_data'=>"location"]],
]]);
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
			$zip->addFile($file, $file);
			if (!is_null($password)) {
				$zip->setEncryptionName($file, ZipArchive::EM_AES_256);
			}
		}
		$zip->close();
		return file_exists($destination);
	} else {
		return false;
	}
}
##----------------------
function Win($table)
{
	if ($table[0][0]['text'] == $table[0][1]['text'] && $table[0][1]['text'] == $table[0][2]['text'] && $table[0][0]['text'] != ' ') return $table[0][0]['text'];
	elseif ($table[1][0]['text'] == $table[1][1]['text'] && $table[1][1]['text'] == $table[1][2]['text'] && $table[1][0]['text'] != ' ') return $table[1][0]['text'];
	elseif ($table[2][0]['text'] == $table[2][1]['text'] && $table[2][1]['text'] == $table[2][2]['text'] && $table[2][0]['text'] != ' ') return $table[2][0]['text'];
	
	elseif ($table[0][0]['text'] == $table[1][0]['text'] && $table[0][0]['text'] == $table[2][0]['text'] && $table[0][0]['text'] != ' ') return $table[0][0]['text'];
	elseif ($table[0][1]['text'] == $table[1][1]['text'] && $table[0][1]['text'] == $table[2][1]['text'] && $table[0][1]['text'] != ' ') return $table[0][1]['text'];
	elseif ($table[0][2]['text'] == $table[1][2]['text'] && $table[0][2]['text'] == $table[2][2]['text'] && $table[0][2]['text'] != ' ') return $table[0][2]['text'];
	
	elseif ($table[0][0]['text'] == $table[1][1]['text'] && $table[0][0]['text'] == $table[2][2]['text'] && $table[0][0]['text'] != ' ') return $table[0][0]['text'];
	elseif ($table[0][2]['text'] == $table[1][1]['text'] && $table[0][2]['text'] == $table[2][0]['text'] && $table[0][2]['text'] != ' ') return $table[0][2]['text'];
	
	return false;
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
function timeElapsed_2($secs)
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
				$ret[] = $v;
				$ret[] = $k;
				$ret[] = 'و';
		}
	}
	return trim(join(' ', $ret), 'و ');
}
##----------------------Telegram Functions
function bot($method, $data = [], $bot_token = API_KEY, $be_array = true)
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
	return (!empty($result) ? json_decode($result, $be_array) : false);
}
function sendAction($chat_id, $action = 'typing')
{
	return bot('sendChataction',
	[
		'chat_id' => $chat_id,
		'action' => $action
	]);
}
function sendMessage($chat_id, $text, $mode = null, $reply = null, $keyboard = null)
{
	return bot('sendMessage',[
	'chat_id' => $chat_id,
	'text' => $text,
	'parse_mode' => $mode,
	'reply_to_message_id' => $reply,
	'reply_markup' => $keyboard,
	'disable_web_page_preview'=>true,
	]);
}
function editMessageText($chat_id, $message_id, $text)
{
	return bot('editMessageText',[
	'chat_id' => $chat_id,
	'message_id' => $message_id,
	'text' => $text
	]);
}
function editKeyboard($chat_id, $message_id, $keyboard)
{
	return bot('EditMessageReplyMarkup',[
	'chat_id' => $chat_id,
	'message_id' => $message_id,
	'reply_markup' => $keyboard
	]);
}
function answerCallbackQuery($callback_query_id, $text, $show_alert = false)
{
	return bot('answerCallbackQuery',[
	'callback_query_id' => $callback_query_id,
	'text' => $text,
	'show_alert' => $show_alert
	]);
}
function Forward($chatid, $from_id, $massege_id)
{
	return bot('ForwardMessage',[
	'chat_id' => $chatid,
	'from_chat_id' => $from_id,
	'message_id' => $massege_id
	]);
}
function deleteMessage($chat_id, $message_id)
{
	return bot('deleteMessage',[
	'chat_id' => $chat_id,
	'message_id' => $message_id
	]);
}
function sendPhoto($chatid, $photo, $caption = null)
{
	return bot('sendPhoto',[
	'chat_id' => $chatid,
	'photo' => $photo,
	'caption' => $caption,
	'parse_mode' => 'html'
	]);
}
function sendAudio($chatid, $audio, $caption = null, $sazande = null, $title = null)
{
	return bot('sendAudio',[
	'chat_id' => $chatid,
	'audio' => $audio,
	'caption' => $caption,
	'performer' => $sazande,
	'title' => $title,
	'parse_mode' => 'html'
	]);
}
function sendDocument($chatid, $document, $caption = null)
{
	return bot('sendDocument',[
	'chat_id' => $chatid,
	'document' => $document,
	'caption' => $caption,
	'parse_mode' => 'html'
	]);
}
function sendSticker($chatid, $sticker)
{
	return bot('sendSticker',[
	'chat_id' => $chatid,
	'sticker' => $sticker
	]);
}
function sendVideo($chatid, $video, $caption = null, $duration = null)
{
	return bot('sendVideo',[
	'chat_id' => $chatid,
	'video' => $video,
	'caption' => $caption,
	'duration' => $duration,
	'parse_mode' => 'html'
	]);
}
function sendVoice($chatid, $voice, $caption = null)
{
	return bot('sendVoice',[
	'chat_id' => $chatid,
	'voice' => $voice,
	'caption' => $caption,
	'parse_mode' => 'html'
	]);
}
function sendContact($chatid, $first_name, $phone_number, $msg_id = null)
{
	return bot('sendContact',[
	'chat_id' => $chatid,
	'first_name' => $first_name,
	'phone_number' => $phone_number,
	'reply_to_message_id' => $msg_id
	]);
}
function GetProfile($from_id)
{
	$get = file_get_contents('https://api.telegram.org/bot'.API_KEY.'/getUserProfilePhotos?user_id='.$from_id);
	$decode = json_decode($get, true);
	$result = $decode['result'];
	$profile = $result['photos'][0][0]['file_id'];
	return $profile;
}
function getChat($chatid, $be_array = true)
{
	$get = bot('getChat', ['chat_id'=> $chatid], API_KEY, $be_array);
	return $get;
}
function getMention($user_id)
{
	$info = bot('getChat', [
		'chat_id' => $user_id
	], API_KEY, false);
	$name = isset($info->result->last_name) ? $info->result->first_name . ' ' . $info->result->last_name : $info->result->first_name;
	$name = str_replace(['<', '>'], '', $name);
	$mention = isset($info->result->username) ? 'https://telegram.me/' . $info->result->username : "tg://user?id={$user_id}";
	return "<a href='$mention'>$name</a>";
}
function GetMe()
{
	$get =  bot('GetMe',[]);
	return $get;
}
function zekr() {
	$today = jdate('l');
	switch ($today) {
		case 'شنبه';
			return 'یا رَبِّ الْعالَمِین';
		case 'یکشنبه';
			return 'یا ذَالجَلالِ وَ اْلاِکْرام';
		case 'دوشنبه';
			return 'یا قاضیَ الحاجات';
		case 'سه شنبه';
			return 'یا أَرْحَمَ الرَّاحِمِین';
		case 'چهارشنبه';
			return 'یا حَیُّ یا قَیّومُ';
		case 'پنجشنبه';
			return 'لا إِلهَ إِلَّا اللَّهُ المَلِک الحقّ المُبین';
		case 'جمعه';
			return 'الّلهُمَّ صَلِّ عَلَی مُحَمَّدٍ وَآلِ مُحَمَّدٍ و عجل فرجهم';

	}
}
function replace(string $text)
{
	global $username, $first_name, $last_name;

	$full_name = empty($last_name) ? $first_name : $first_name . ' ' . $last_name;
	$username = !empty($username) ? "@{$username}" : 'بدون یوزرنیم';

	$text = str_replace('FULL-NAME', $full_name, $text);
	$text = str_replace('F-NAME', $first_name, $text);
	$text = str_replace('L-NAME', $last_name, $text);
	$text = str_replace('U-NAME', $username, $text);
	$text = str_replace('TIME', jdate('H:i:s'), $text);
	$text = str_replace('DATE', jdate('Y/n/j'), $text);
	$text = str_replace('TODAY', jdate('l'), $text);

	if (strpos($text, 'JOKE') !== false) {
		$parts = scandir('../../texts/joke/');
		$part = '../../texts/joke/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('JOKE', $replace, $text);
	}
	if (strpos($text, 'DEQAT-KARDIN') !== false) {
		$parts = scandir('../../texts/deqat-kardin/');
		$part = '../../texts/deqat-kardin/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('DEQAT-KARDIN', $replace, $text);
	}
	if (strpos($text, 'KHATERE') !== false) {
		$parts = scandir('../../texts/khatere/');
		$part = '../../texts/khatere/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('KHATERE', $replace, $text);
	}
	if (strpos($text, 'ETERAF-MIKONAM') !== false) {
		$parts = scandir('../../texts/eteraf/');
		$part = '../../texts/eteraf/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('ETERAF-MIKONAM', $replace, $text);
	}
	if (strpos($text, 'FANTASYM-INE') !== false) {
		$parts = scandir('../../texts/fantasy/');
		$part = '../../texts/fantasy/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('FANTASYM-INE', $replace, $text);
	}
	if (strpos($text, 'FAK-O-FAMILE-DARIM') !== false) {
		$parts = scandir('../../texts/famil/');
		$part = '../../texts/famil/' . $parts[mt_rand(2, count($parts)-1)];
		$texts = json_decode(file_get_contents($part), true);
		$replace = $texts[mt_rand(0, count($texts)-1)];
		$text = str_replace('FAK-O-FAMILE-DARIM', $replace, $text);
	}
	if (strpos($text, 'AST-DIGAR') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/ast-digar.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('AST-DIGAR', $love_texts, $text);
	}
	if (strpos($text, 'CHIST') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/chist.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('CHIST', $love_texts, $text);
	}
	if (strpos($text, 'ALAKI-MASALAN') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/alaki-masalan.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('ALAKI-MASALAN', $love_texts, $text);
	}
	if (strpos($text, 'MORED-DASHTIM') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/mored-dashtim.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('MORED-DASHTIM', $love_texts, $text);
	}
	if (strpos($text, 'PA-NA-PA') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/pa-na-pa.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('PA-NA-PA', $love_texts, $text);
	}
	if (strpos($text, 'JOMLE-SAZI') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/jomle.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('JOMLE-SAZI', $love_texts, $text);
	}
	if (strpos($text, 'VARZESHI') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/sport.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('VARZESHI', $love_texts, $text);
	}
	if (strpos($text, 'EMTEHANAT') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/emtehan.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('EMTEHANAT', $love_texts, $text);
	}
	if (strpos($text, 'HEYVANAT') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/animals.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('HEYVANAT', $love_texts, $text);
	}
	if (strpos($text, 'YE-VAQT-ZESHT-NABASHE') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/ye-vaqt-zesht-nabashe.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('YE-VAQT-ZESHT-NABASHE', $love_texts, $text);
	}
	if (strpos($text, 'BE-BAZIA-BAYAD-GOFT') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/be-bazia-bayad-goft.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('BE-BAZIA-BAYAD-GOFT', $love_texts, $text);
	}
	if (strpos($text, 'DIALOG') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/dialog.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('DIALOG', $love_texts, $text);
	}
	if (strpos($text, 'ZEKR') !== false) {
		$text = str_replace('ZEKR', zekr(), $text);
	}
	if (strpos($text, 'LOVE') !== false) {
		$love_texts = json_decode(file_get_contents('../../texts/love.json'), true);
		$love_texts = $love_texts[mt_rand(0, count($love_texts)-1)];
		$text = str_replace('LOVE', $love_texts, $text);
	}
	if (strpos($text, 'HADITH') !== false) {
		$hadithes = json_decode(file_get_contents('../../texts/hadith.json'), true);
		$hadith = $hadithes[mt_rand(0, count($hadithes)-1)];

		$text = str_replace('HADITH-TITLE', $hadith['title'], $text);
		$text = str_replace('HADITH-ARABIC', $hadith['ar'], $text);
		$text = str_replace('HADITH-FARSI', $hadith['fa'], $text);
		$text = str_replace('HADITH-WHO', $hadith['who'], $text);
		$text = str_replace('HADITH-SRC', $hadith['src'], $text);
	}
	if (strpos($text, 'DANESTANI') !== false) {
		$ayamidanid = json_decode(file_get_contents('https://api.keybit.ir/ayamidanid/'), true)['text'];
		$text = str_replace('DANESTANI', $ayamidanid, $text);
	}

	return $text;
}
##----------------------
function CheckLink($text) {
	global $data;
	if ($data['lock']['link'] == '✅') {
		if (stripos($text, "t.me") !== false || stripos($text, "http") !== false || stripos($text, "www.") !== false) {
			return true;
		}
	}
}
function CheckFilter($text) {
	global $data;
	foreach($data['filters'] as $value) {
		if (mb_strstr($text, "$value")) {
			return true;
		}
	}
}
##----------------------
function curl_file_get_contents($url)
{
return file_get_contents($url);
}
##----------------------
function get_file_size($url)
{
	return get_headers($url, 1)['Content-Length'];
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
function humanFileSize($size)
{ 
	$units = array( 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت', 'پنتابایت', 'EB', 'ZB', 'YB');
	$power = $size > 0 ? floor(log($size, 1024)) : 0;
	return  str_replace(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], number_format($size / pow(1024, $power), 2, '.', ', ') . ' ' . $units[$power]);
}