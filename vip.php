<?php
set_time_limit(55);
require 'config.php';
date_default_timezone_set('Asia/Tehran');
##------------------------
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
$pdo->exec('SET NAMES utf8mb4');

##end
$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `end`<=UNIX_TIMESTAMP();");
$prepared_vip->execute();
$fetch_vip = $prepared_vip->fetchAll();
if (count($fetch_vip) > 0) {
        foreach ($fetch_vip as $bot) {
                $prepare = $pdo->prepare("DELETE FROM `vip_bots` WHERE `bot`='{$bot['bot']}';");
                $prepare->execute();
                $config = file_get_contents("Bots/{$bot['bot']}/config.php");
                preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
                $token = $match[1];

		bot('sendMessage', [
			'chat_id'=>$bot['admin'],
			'text'=>"⚠️ زمان اشتراک ویژه ربات شما به پایان رسید."
                ], $token);

                $start_time = jdate('Y/m/j H:i:s', $bot['start']);
		$end_time = jdate('Y/m/j H:i:s', $bot['end']);
		$time_elapsed = timeElapsed($bot['end']-$bot['start']);
                
                bot('sendMessage', [
                        'chat_id'=>$logchannel,
                        'parse_mode'=>'html',
                        'text'=>"⚠️ زمان اشتراک ویژه ربات @{$bot['bot']} به پایان رسید.
                        
⏳ زمان شروع : <b>{$start_time}</b>
🧭 زمان سپری شده : {$time_elapsed}
⌛️ زمان پایان : <b>{$end_time}</b>

👤 <code>{$bot['admin']}</code>"
                ], API_KEY_CR);
        }
}

##1 day
$prepared_vip = $pdo->prepare("SELECT * FROM `vip_bots` WHERE `alert`!=1 AND `end`-UNIX_TIMESTAMP() <= 24*60*60;");
$prepared_vip->execute();
$fetch_vip = $prepared_vip->fetchAll();
if (count($fetch_vip) > 0) {
        foreach ($fetch_vip as $bot) {
                $time_elapsed = timeElapsed($bot['end']-time());
		$prepared = $pdo->prepare("UPDATE `vip_bots` SET `alert`=1 WHERE `bot`='{$bot['bot']}';");
                $prepared->execute();
                $config = file_get_contents("Bots/{$bot['bot']}/config.php");
                preg_match('/\$Token\s=\s"(.*?)";/', $config, $match);
                $token = $match[1];

		bot('sendMessage', [
			'chat_id'=>$bot['admin'],
			'parse_mode'=>'html',
			'text'=>"⚠️ تنها {$time_elapsed} از زمان اشتراک ویژه ربات شما باقی مانده است."
                ], $token);
        }
}

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