<?php
set_time_limit(55);
require 'config.php';
##------------------------
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
$pdo->exec('SET NAMES utf8mb4');

$res = $pdo->query("SELECT * FROM `sendlist`;");
$fetch = $res->fetchAll();
$count = count($fetch);
if ($count > 0) {
        $type = $fetch[0]['type'];
        $offset = $fetch[0]['offset'];
        $data = json_decode($fetch[0]['data'], true);
        $post_data = [];

        if ($type == 'text') {
                $method = 'sendMessage';
                $post_data['text'] = utf8_decode($data['text']);
        }
        elseif ($type == 'media_group') {
                $method = 'sendMediaGroup';
                $post_data['media'] = file_get_contents('Data/album-' . $data['media_group_id'] . '.json');
        }
        elseif ($type == 'contact') {
                $method = 'sendContact';
                $post_data['phone_number'] = $data['phone_number'];
                $post_data['first_name'] = $data['phone_first'];
                $post_data['last_name'] = $data['phone_last'];
        }
        elseif ($type == 'location') {
                $method = 'sendLocation';
                $post_data['latitude'] = $data['latitude'];
                $post_data['longitude'] = $data['longitude'];
        }
        elseif ($type == 'f2a') {
                $method = 'forwardMessage';
                $post_data['from_chat_id'] = $data['from_chat_id'];
                $post_data['message_id'] = $data['message_id'];
        }
        else {
                $method = 'send' . str_replace('_', '', ucwords($type));
                $post_data[$type] = $data['file_id'];
                $post_data['caption'] = utf8_decode($data['caption']);
        }

        $name = $type == 'f2a' ? 'هدایت' : 'ارسال';

        if ($offset <= 0) {
                $msg_id = bot('sendMessage', [
                        'chat_id'=>$fetch[0]['user_id'],
                        'text'=>"🔰 عملیات {$name} پیام مورد نظر شما برای کاربران ربات آغاز گردید."
                ])->result->message_id;
                $prepare = $pdo->prepare("UPDATE `sendlist` SET `message_id`={$msg_id} WHERE `user_id`={$fetch[0]['user_id']} AND `time`={$fetch[0]['time']};");
                $prepare->execute();
        }
        else {
                $sent_count = $offset;
                $sent_count_format = number_format($sent_count);
                bot('editMessageText', [
                        'chat_id'=>$fetch[0]['user_id'],
                        'message_id'=>$fetch[0]['message_id'],
                        'parse_mode'=>'html',
                        'text'=>"✅ پیام شما تاکنون برای <b>{$sent_count_format}</b> کاربر ربات ارسال شده است."
                ]);
        }

        $result = $pdo->query("SELECT * FROM `members` LIMIT 50 OFFSET {$offset};");
        $fetch_members = $result->fetchAll();

        $post_data_2 = $post_data;

        $i = 0;
        foreach ($fetch_members as $member) {
                $post_data['chat_id'] = $member['user_id'];
                if (!empty($post_data['text'])) {
                        $post_data['text'] = replace($post_data_2['text'], $member['user_id']);
                }
                elseif (!empty($post_data['caption'])) {
                        $post_data['caption'] = replace($post_data_2['caption'], $member['user_id']);
                }
                elseif (!empty($post_data['media'])) {
                        $post_data['media'] = replace($post_data_2['media'], $member['user_id']);
                }

                $response = bot($method, $post_data);
                $i++;

                if (isset($response->error_code) && $response->error_code == 403) {
                        $prepare = $pdo->prepare("DELETE FROM `members` WHERE `user_id`={$member['user_id']};");
                        $prepare->execute();

                        $user_data = json_decode(file_get_contents("Data/{$member['user_id']}/data.json"), true);
                        if (count($user_data['bots']) < 1) {
                                deleteFolder("Data/{$member['user_id']}/");
                        }
                }
                usleep(300000);
        }

        if ($i < 50) {
                $prepare = $pdo->prepare("DELETE FROM `sendlist` WHERE `user_id`={$fetch[0]['user_id']} AND `time`={$fetch[0]['time']};");
                $prepare->execute();
                $sent_count += $i;
                $sent_count_format = number_format($sent_count);
                bot('editMessageText', [
                        'chat_id'=>$fetch[0]['user_id'],
                        'message_id'=>$fetch[0]['message_id'],
                        'parse_mode'=>'html',
                        'text'=>"✅ پیام مورد نظر شما برای <b>{$sent_count_format}</b> کاربر ربات {$name} گردید."
                ]);
                bot('sendMessage', [
                        'chat_id'=>$fetch[0]['user_id'],
                        'parse_mode'=>'html',
                        'text'=>"🔰 عملیات {$name} پیام مورد نظر شما برای کاربران ربات به پایان رسید و پیام مورد نظر شما برای <b>{$sent_count_format}</b> کاربر هدایت گردید."
                ]);
        }
        else {
                $offset += $i;
                $prepare = $pdo->prepare("UPDATE `sendlist` SET `offset`={$offset} WHERE `user_id`={$fetch[0]['user_id']} AND `time`={$fetch[0]['time']};");
                $prepare->execute();
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
function replace(string $text, int $user_id)
{
        $get_chat = bot('getChat', [
                'chat_id'=>$user_id
        ]);

        $last_name = empty($get_chat->result->last_name) ? '' : $get_chat->result->last_name;
        $full_name = empty($get_chat->result->last_name) ? $get_chat->result->first_name : $get_chat->result->first_name . ' ' . $get_chat->result->last_name;
        $username = !empty($get_chat->result->username) ? '@' . $get_chat->result->username : 'بدون یوزرنیم';

        $text = str_replace('FULL-NAME', $full_name, $text);
        $text = str_replace('F-NAME', $get_chat->result->first_name, $text);
        $text = str_replace('L-NAME', $last_name, $text);
        $text = str_replace('U-NAME', $username, $text);
        $text = str_replace('TIME', jdate('H:i:s'), $text);
        $text = str_replace('DATE', jdate('Y/n/j'), $text);
        $text = str_replace('TODAY', jdate('l'), $text);

        return $text;
}