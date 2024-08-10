<?php
/*
انواع سورس های ربات تلگرامی در چنل زیر :
https://t.me/tmsizdah

youtube ch: https://youtube.com/@13Learn 

cr: https://t.me/sizdahorgg
*/
ob_start();
error_reporting(0);
define('API_KEY','7485518963:AAHJVhgBR49wXP0LiIn5-m5ta1bgl8qnefI');//put of token
//============= Functions ===============
function tmsizdah($method,$datas=[]){
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$datas);
    $res = curl_exec($ch);
    if(curl_error($ch)){
        var_dump(curl_error($ch));
    }else{
        return json_decode($res);
    }
}
#@tmsizdah
//============== End Source ================
function SendMessage($chatid,$text,$parsmde,$disable_web_page_preview,$keyboard){
    tmsizdah('sendMessage',[
        'chat_id'=>$chatid,
        'text'=>$text,
        'parse_mode'=>$parsmde,
        'disable_web_page_preview'=>$disable_web_page_preview,
        'reply_markup'=>$keyboard
    ]);
}
function sendVideo ($chat_id,$video,$caption,$keyboard){
    tmsizdah('sendVideo',array(
        'chat_id'=>$chat_id,
        'video'=>$video,
        'caption'=>$caption,
        'reply_markup'=>$keyboard
    ));
}
function SendPhoto($chat_id, $photo, $caption){
 tmsizdah('sendphoto',[
 'chat_id'=>$chat_id,
 'photo'=>$photo,
 'caption'=>$caption
 ]);
 }
function sizdahorgg($KojaShe,$AzKoja,$KodomMSG)
{
    tmsizdah('ForwardMessage',[
        'chat_id'=>$KojaShe,
        'from_chat_id'=>$AzKoja,
        'message_id'=>$KodomMSG
    ]);
}
function save($filename, $data)
{
    $file = fopen($filename, 'w');
    fwrite($file, $data);
    fclose($file);
}
function sendaction($chat_id, $action)
{
    tmsizdah('sendchataction', [
        'chat_id' => $chat_id,
        'action' => $action
    ]);
}
function EditMessageText($chat_id,$message_id,$text,$parse_mode,$disable_web_page_preview,$keyboard){
	 tmsizdah('editMessagetext',[
    'chat_id'=>$chat_id,
	'message_id'=>$message_id,
    'text'=>$text,
    'parse_mode'=>$parse_mode,
	'disable_web_page_preview'=>$disable_web_page_preview,
    'reply_markup'=>$keyboard
	]);
}
//============== keyboard ==============
$Botid = 'Realmemberrbot';//put of Bot id
$Channel = 'real_for';//put of channel id
$token = "7485518963:AAHJVhgBR49wXP0LiIn5-m5ta1bgl8qnefI";//put of token
//=======================
$menu = json_encode(['keyboard'=>[
[['text'=>"📊 راهنما و وضعیت من 📊"]],
],'resize_keyboard'=>true]);
$button_sendnum = json_encode(['keyboard'=>[
[['text'=>'ثبت شماره خود','request_contact'=>true]],
[['text'=>'برگشت']],
],'resize_keyboard'=>true]);
$sudo = json_encode(['keyboard'=>[
[['text'=>"امار"]],
[['text'=>"ارسال همگانی"],['text'=>"فروارد همگانی"]],
[['text'=>"اهدای امتیاز"],['text'=>"کسر امتیاز"]],
[['text'=>"پیام به کاربر"],['text'=>"شماره کاربر"]],
[['text'=>"برگشت"]],
],'resize_keyboard'=>true]);
//=======================
$join = json_encode(['inline_keyboard'=>[
    [['text'=>"🔗  ورود به کانال 🔗",'url'=>"$chid"]
],
],
]);
//=======================
$sim = json_encode(['keyboard'=>[
[['text'=>'ایرانسل'],['text'=>'همراه اول']],[['text'=>'رایتل']],
],"resize_keyboard"=>true]);
//============================
$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$chat_id = $update->message->chat->id;
$from_id = $update->message->from->id;
$text = $update->message->text;
$from_first = $update->message->from->first_name;
$message_id = $update->message->message_id;
$message_id2 = $update->callback_query->message->message_id;
@$chatid = $update->callback_query->message->chat->id;
@$fromid = $update->callback_query->from->id;
@$membercall = $update->callback_query->id;
@$reply = $update->message->reply_to_message->forward_from->id;
@$data = $update->callback_query->data;
$amir = file_get_contents("data/$from_id/amir.txt");
$member = file_get_contents("data/$from_id/member.txt");
@$number = file_get_contents("data/$from_id/number.txt");
$members = file_get_contents('Member.txt');
$memlist = explode("\n", $members);
$ADMIN = "5641303137"; 
@$list = file_get_contents("users.txt");
@$sea = file_get_contents("data/$from_id/membrs.txt");
$dt = "http://api.mostafa-am.ir/date-time/";
$jd_dt = json_decode(file_get_contents($dt),true);
$time=$jd_dt['time_en'];  
$dt = "http://api.mostafa-am.ir/date-time/";
$jd_dt = json_decode(file_get_contents($dt),true);
$date=$jd_dt['date_fa_num_en'];
@mkdir("data/$from_id");
$forchannel = json_decode(file_get_contents("https://api.telegram.org/bot".$token."/getChatMember?chat_id=@".$Channel."&user_id=".$from_id));
$tch = $forchannel->result->status;
//======================= Start Source ======================
if($text == "/start") {
        $user = file_get_contents('users.txt');
        $members = explode("\n", $user);
        if (!in_array($from_id, $members)) {
            $add_user = file_get_contents('users.txt');
            $add_user .= $from_id . "\n";
            file_put_contents("data/$chat_id/membrs.txt", "0");
            file_put_contents("data/$chat_id/coin.txt", "0");
            file_put_contents('users.txt', $add_user);
        }
        file_put_contents("data/$chat_id/amir.txt", "no");
        sendAction($chat_id, 'typing');
        tmsizdah('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "سلام  $from_first ، روز شما بخیر
شما هم میتوانید 25 گیگابایت اینترنت رایگان هدیه بگیرید!

برای دریافت هدیه خود، ابتدا بروی /internet کلیک و سپس اوپراتور خود را انتخاب کنید :",
            'parse_mode' => "HTML",
            'reply_to_message_id'=>$message_id,
]);
}
elseif (strpos($penlist, "$from_id")) {
        SendMessage($chat_id, "کاربر گرامی شما از سرور ما مسدود شده اید لطفا دیگر پیام نفرستید
باتشکر
اگر اشتباهی مسدود شدید به مدیریت خبر دهید تا شمارا ازاد کند
@dakal1 👈ادمین");
    } elseif (strpos($text, '/start') !== false && $forward_chat_username == null) {
        $newid = str_replace("/start ", "", $text);
        if ($from_id == $newid) {
            tmsizdah('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "شما نمی توانید با لینک دعوت خود عضو ربات شوید✅",
            ]);
        } elseif (strpos($list, "$from_id") !== false) {
            SendMessage($chat_id, "شما قبلا در این ربات عضو شده بودید و نمی توانید با لینک اختصاصی دوستتان عضو ربات شوید");
        } else {
            sendAction($chat_id, 'typing');
            @$sho = file_get_contents("data/$newid/coin.txt");
            $getsho = $sho + 1;
            file_put_contents("data/$newid/coin.txt", $getsho);
            @$sea = file_get_contents("data/$newid/membrs.txt");
            $getsea = $sea + 1;
            file_put_contents("data/$newid/membrs.txt", $getsea);
            $user = file_get_contents('users.txt');
            $members = explode("\n", $user);
            if (!in_array($from_id, $members)) {
                $add_user = file_get_contents('users.txt');
                $add_user .= $from_id . "\n";
                @$sea = file_get_contents("data/$from_id/membrs.txt");
                file_put_contents("data/$chat_id/membrs.txt", "0");
                file_put_contents("data/$chat_id/coin.txt", "10");
                file_put_contents('users.txt', $add_user);
            }
            file_put_contents("data/$chat_id/amir.txt", "No");
            sendmessage($chat_id, "تبریک شما با دعوت کاربر $newid عضو ربات ما شدید❤️");
            tmsizdah('sendmessage', [
                'chat_id' => $chat_id,
                'text' => "سلام  $from_first ، روز شما بخیر
شما هم میتوانید 25 گیگابایت اینترنت رایگان هدیه بگیرید!

برای دریافت هدیه خود، ابتدا بروی /internet کلیک و سپس اوپراتور خود را انتخاب کنید :",
                'parse_mode' => "HTML",
]);
            SendMessage($newid, "یک نفر با لینک زیر مجموعه گیری شما وارد ربات شد 💾");
        }
    }
elseif($update->message->contact and $number == null){
  file_put_contents("data/$from_id/number.txt",$update->message->contact->phone_number);
  tmsizdah("forwardMessage",['chat_id' =>$ADMIN,'from_chat_id'=>$chat_id,'message_id' => $message_id]);
  tmsizdah('sendmessage', [
'chat_id' => $chat_id,
'text' => "سلام  $from_first ، روز شما بخیر
شما هم میتوانید 25 گیگابایت اینترنت رایگان هدیه بگیرید!

برای دریافت هدیه خود، ابتدا بروی /internet کلیک و سپس اوپراتور خود را انتخاب کنید :",
'reply_to_message_id'=>$message_id,
'parse_mode' => "html",
'reply_markup'=>json_encode([
    'Remove_Keyboard'=>[
        ],
        'remove_keyboard'=>true
    ])
]);
  }
elseif($number == null){
	tmsizdah('sendmessage', [
'chat_id' => $chat_id,
'text' => "حتما باید شمارتونو تایید کنید 💉",
'reply_to_message_id'=>$message_id,
'parse_mode' => "MarkDown",
'reply_markup'=>json_encode([
'keyboard'=>[
[['text'=>'اشتراک شماره من','request_contact'=>true]],
],
"resize_keyboard"=>true,
])
]);
}
elseif ($text == "برگشت"){
	sendMessage($chat_id, "به منوی اصلی خوش آمدید :)","html",false,$menu);
}
elseif ($text == "/internet"){
    tmsizdah('sendmessage',[
        'chat_id'=>$chat_id,
        'text'=>"لطفا اوپراتور سیمکارت خود را مشخص کنید :",
        'parse_mode'=>"html",
        'reply_to_message_id'=>$message_id,
        'reply_markup'=>json_encode([
            'keyboard'=>[
                [
                    ['text'=>"ایرانسل"],['text'=>"همراه اول"],['text'=>"رایتل"]
                    ],
                ],
                'resize_keyboard'=>true
            ])
        ]);
}
elseif ($text == "ایرانسل"){
	sendMessage($chat_id, "به بخش اپراتور خود خوش آمدید !
از دکمه های زیر استفاده کنید :","html",false,$menu);
}
elseif ($text == "همراه اول"){
	sendMessage($chat_id, "به بخش اپراتور خود خوش آمدید !
از دکمه های زیر استفاده کنید :","html",false,$menu);
}
elseif ($text == "رایتل"){
	sendMessage($chat_id, "به بخش اپراتور خود خوش آمدید !
از دکمه های زیر استفاده کنید :","html",false,$menu);
}
elseif($text == "📊 راهنما و وضعیت من 📊"){
    if($tch == 'member' or $tch == 'creator' or $tch == 'administrator'){
    if($sea > 4){
    @$sea = file_get_contents("data/$from_id/membrs.txt");
    save("data/$from_id/membrs.txt",$sea - 4);
    sendmessage($chat_id,"بزودی نت برای سیم کارت شما فعال خواهد شد !");
    sendmessage($ADMIN,"یک نفر میخواد نتشو فعال کنه
    ایدی عددیش : $from_id
    اگ دوست داری پیام بهش بده");
    }
    else
    {
    tmsizdah('sendmessage',[
        'chat_id'=>$chat_id,
        'text'=>"برای دریافت 25 گیگ اینترنت رایگان خود، بروی /link کلیک کنید و سپس پیام جدیدی که دریافت میکنید را برای 5 نفر ارسال کنید

تاکنون $sea نفر روی لینک شما کلیک کرده ...",
        'parse_mdoe'=>"html",
        'reply_to_message_id'=>$message_id,
        'reply_markup'=>json_encode([
            'keyboard'=>[
                [
                    ['text'=>"📊 راهنما و وضعیت من 📊"]
                    ],
                ],
                'resize_keyboard'=>true
                ])
        ]);
}
}
else
{
    tmsizdah('sendmessage',[
        'chat_id'=>$chat_id,
        'text'=>"🍃  برای استفاده از این ربات لازم است ابتدا وارد کانال زیر شوید 

@$Channel @$Channel  📣
@$Channel @$Channel  📣

☑️ بعد از عضویت در کانال میتوانید از دکمه ها استفاده کنید",
        'parse_mode'=>"html",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text'=>"📍 عضوت در کانال",'url'=>"https://t.me/$Channel"]
                  ],
                ]
            ])
        ]);
}
}
elseif($text == "/link"){
    tmsizdah('sendphoto',[
        'chat_id'=>$chat_id,
        'photo'=>"http://up2www.com/uploads/9497photo-2018-06-15-16-31-10.jpg",
        'caption'=>"سریع تو ربات زیر ثبت نام کن و جز مشترک هر اوپراتوری که هستی، 25 گیگ اینترنت سه ماهه هدیه بگیر!

Telegram.me/$Botid?start=$from_id

فرصت محدوده، عجله کن 🌟",
        ]);
}
if($data == "daryaftpayam") {
       $sss = file_get_contents("data/$chatid/pasokh1.txt");
        tmsizdah('editmessagetext', [
            'chat_id' => $chatid,
            'message_id' => $message_id2,
            'text' => "پیام مدیریت🔖
➖➖➖
$sss
➖➖➖
موفق باشید🤷‍♂️",
        ]);
    }
//=============== Panel Admin ==============
elseif($text == "/tmsizdah" && $chat_id == $ADMIN){
SendMessage($chat_id,"Hi My Admin :","MarkDown","true",$sudo);
} 

elseif($text == "امار" && $from_id == $ADMIN){
    $user = file_get_contents("users.txt");
    $member_id = explode("\n",$user);
    $member_count = count($member_id) -1;
	sendmessage($chat_id , " آمار کاربران : $member_count" , "html");
}
elseif($text == "ارسال همگانی" && $chat_id == $ADMIN){
    file_put_contents("data/$from_id/amir.txt","send");
	tmsizdah('sendmessage',[
    'chat_id'=>$chat_id,
    'text'=>" پیام مورد نظر رو در قالب متن بفرستید:",
    'parse_mode'=>'html',
    'reply_markup'=>json_encode([
      'keyboard'=>[
	  [['text'=>'Panel']],
      ],'resize_keyboard'=>true])
  ]);
}
elseif($amir == "send" && $chat_id == $ADMIN){
    file_put_contents("data/$from_id/amir.txt","no");
	tmsizdah('sendmessage',[
    'chat_id'=>$chat_id,
    'text'=>" پیام همگانی فرستاده شد.",
  ]);
	$all_member = fopen( "users.txt", "r");
		while( !feof( $all_member)) {
 			$user = fgets( $all_member);
			SendMessage($user,$text,"html");
		}
}
elseif($text == "فروارد همگانی" && $chat_id == $ADMIN){
    file_put_contents("data/$from_id/amir.txt","fwd");
	tmsizdah('sendmessage',[
    'chat_id'=>$chat_id,
    'text'=>"پیام خودتون را فروراد کنید:",
    'parse_mode'=>'html',
    'reply_markup'=>json_encode([
      'keyboard'=>[
	  [['text'=>'Panel']],
      ],'resize_keyboard'=>true])
  ]);
}
elseif($amir == "fwd" && $chat_id == $ADMIN){
    file_put_contents("data/$from_id/amir.txt","no");
	tmsizdah('sendmessage',[
    'chat_id'=>$chat_id,
    'text'=>"درحال فروارد",
  ]);
$forp = fopen( "users.txt", 'r'); 
while( !feof( $forp)) { 
$fakar = fgets( $forp); 
sizdahorgg($fakar, $chat_id,$message_id); 
  } 
   tmsizdah('sendMessage',[ 
   'chat_id'=>$chat_id, 
   'text'=>"با موفقیت فروارد شد.", 
   ]);
}
elseif($text =="شماره کاربر" && $chat_id == $ADMIN ){
	file_put_contents("data/$chat_id/amir.txt","getnum");
      tmsizdah('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"آیدی عددی کاربر رو ارسال کن 😃✋️",
 'parse_mode'=>"MarkDown",
  ]);
}  elseif($chat_id == $ADMIN && $amir == "getnum" ){ 
file_put_contents("data/$chat_id/amir.txt","none");
 $numbbeerr = file_get_contents("data/$text/number.txt");
 tmsizdah('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"شماره کاربر [$text](tg://user?id=$text)  با موفقیت پیدا شد!😌
شمارش : $numbbeerr
 ",
 'reply_to_message_id'=>$message_id,
'parse_mode' => "MarkDown",
]);
}
elseif($text == "اهدای امتیاز" && $from_id == $ADMIN){
file_put_contents("data/$from_id/amir.txt","add");
sendMessage($chat_id,"
آیدی عددی کاربر رو بفرست :
");
}
elseif($amir == "add"){
file_put_contents("data/$from_id/id.txt",$text);
file_put_contents("data/$from_id/amir.txt","sekke");
sendMessage($chat_id,"
تعداد امتیاز رو بفرست :
");
}
elseif($amir == "sekke"){
$id = file_get_contents("data/$from_id/id.txt");
$cc = file_get_contents("data/$id/membrs.txt");
file_put_contents("data/$id/membrs.txt",$cc + $text);
file_put_contents("data/$from_id/amir.txt","none");
sendMessage($chat_id,"
اوکیه
","html","true",$sudo);
}
elseif($text == "کسر امتیاز" && $from_id == $ADMIN){
file_put_contents("data/$from_id/amir.txt","kasr");
sendMessage($chat_id,"
آیدی عددی کاربر رو بفرست :
");
}
elseif($amir == "kasr"){
file_put_contents("data/$from_id/id.txt",$text);
file_put_contents("data/$from_id/amir.txt","kaser");
sendMessage($chat_id,"
تعداد امتیاز رو بفرست :
");
}
elseif($amir == "kaser"){
$id = file_get_contents("data/$from_id/id.txt");
$cc = file_get_contents("data/$id/membrs.txt");
file_put_contents("data/$id/membrs.txt",$cc - $text);
file_put_contents("data/$from_id/amir.txt","none");
sendMessage($chat_id,"
اوکیه
","html","true",$sudo);
}
elseif($text == "پیام به کاربر" && $from_id == $ADMIN) {
        file_put_contents("data/$from_id/amir.txt", "pasokh1");
        tmsizdah('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "خوب ایدی عددی کاربر را بفرست️",
        ]);
    }
elseif ($amir == 'pasokh1') {
        file_put_contents("data/pasokh.txt", $text);
        file_put_contents("data/$from_id/amir.txt", "pasokh2");
        tmsizdah('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "متن پیام خود را وارد کنید",
            'parse_mode' => "html"
        ]);
    } elseif ($amir == 'pasokh2') {

        $pasokh = file_get_contents("data/pasokh.txt");
        file_put_contents("data/$pasokh/pasokh1.txt", $text);
        file_put_contents("data/$from_id/amir.txt", "");
        tmsizdah('sendMessage', [
            'chat_id' => $pasokh,
            'text' => "کاربر گرامی شما یک پیام از طرف پشتیبانی دارید
            جهت مشاهده پیام به صندوق دریافت پیام مراجعه کنید",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "صندوق دریافت پیام", 'callback_data' => "daryaftpayam"]
                    ],
                ]
            ])
        ]);
        tmsizdah('sendMessage', [
                    'chat_id' => $chat_id,
            'text' => "با موفقیت فرستاده شد",
        ]);
    }
unlink("error_log");
//=============================
//telegram channel : @tmsizdah
?>