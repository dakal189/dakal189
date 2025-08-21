<?php
require 'config.php';
set_time_limit(55);
##------------------------
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
$pdo->exec('SET NAMES utf8mb4');

$res = $pdo->query("SELECT * FROM `bots_sendlist` LIMIT 7;");
$fetch = $res->fetchAll();
$count = count($fetch);

if ($count > 0) {
        foreach ($fetch as $send_data) {
                $type = $send_data['type'];
                $offset = $send_data['offset'];
                $data = json_decode($send_data['data'], true);
                $post_data = [];

                if ($type == 'text') {
                        $method = 'sendMessage';
                        $post_data['text'] = utf8_decode($data['text']);
                }
                elseif ($type == 'media_group') {
                        $method = 'sendMediaGroup';
                        $post_data['media'] = file_get_contents('Bots/' . $send_data['bot_username'] . '/data/album-' . $data['media_group_id'] . '.json');
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
                                'chat_id'=>$send_data['user_id'],
                                'text'=>"🔰 عملیات {$name} پیام مورد نظر شما برای کاربران ربات آغاز گردید."
                        ], $send_data['token'])->result->message_id;
                        $prepare = $pdo->prepare("UPDATE `bots_sendlist` SET `message_id`={$msg_id} WHERE `user_id`={$send_data['user_id']} AND `time`={$send_data['time']};");
                        $prepare->execute();
                }
                else {
                        $sent_count = $offset;
                        $sent_count_format = number_format($sent_count);
                        bot('editMessageText', [
                                'chat_id'=>$send_data['user_id'],
                                'message_id'=>$send_data['message_id'],
                                'parse_mode'=>'html',
                                'text'=>"✅ پیام شما تاکنون برای <b>{$sent_count_format}</b> کاربر ربات ارسال شده است."
                        ], $send_data['token']);
                }

                $result = $pdo->query("SELECT * FROM `{$send_data['bot_username']}_members` LIMIT 40 OFFSET {$offset};");
                $fetch_members = $result->fetchAll();

                $post_data_2 = $post_data;

                $i = 0; 
                foreach ($fetch_members as $member) {
                        $post_data['chat_id'] = $member['user_id'];
                        if (!empty($post_data['text'])) {
                                $post_data['text'] = replace($post_data_2['text'], $member['user_id'], $send_data['token']);
                        }
                        elseif (!empty($post_data['caption'])) {
                                $post_data['caption'] = replace($post_data_2['caption'], $member['user_id'], $send_data['token']);
                        }
                        elseif (!empty($post_data['media'])) {
                                $post_data['media'] = replace($post_data_2['media'], $member['user_id'], $send_data['token']);
                        }

                        $response = bot($method, $post_data, $send_data['token']);
                        $i++;

                        if (isset($response->error_code) && $response->error_code == 403) {
                                $prepare = $pdo->prepare("DELETE FROM `{$send_data['bot_username']}_members` WHERE `user_id`={$member['user_id']};");
                                $prepare->execute();
                                deleteFolder("Bots/{$send_data['bot_username']}/data/{$member['user_id']}/");
                        }
                        usleep(200000);
                }

                if ($i < 40) {
                        $prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `user_id`={$send_data['user_id']} AND `time`={$send_data['time']};");
                        $prepare->execute();
                        $sent_count += $i;
                        $sent_count_format = number_format($sent_count);
                        bot('editMessageText', [
                                'chat_id'=>$send_data['user_id'],
                                'message_id'=>$send_data['message_id'],
                                'parse_mode'=>'html',
                                'text'=>"✅ پیام مورد نظر شما برای <b>{$sent_count_format}</b> کاربر ربات {$name} گردید."
                        ], $send_data['token']);
                        bot('sendMessage', [
                                'chat_id'=>$send_data['user_id'],
                                'parse_mode'=>'html',
                                'text'=>"🔰 عملیات {$name} پیام مورد نظر شما برای کاربران ربات به پایان رسید و پیام مورد نظر شما برای <b>{$sent_count_format}</b> کاربر هدایت گردید."
                        ], $send_data['token']);
                }
                else {
                        $offset += $i;
                        $prepare = $pdo->prepare("UPDATE `bots_sendlist` SET `offset`={$offset} WHERE `user_id`={$send_data['user_id']} AND `time`={$send_data['time']};");
                        $prepare->execute();
                }
        }
}

function bot($method, $data = [], $bot_token)
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
function replace(string $text, int $user_id, string $token)
{
        $get_chat = bot('getChat', [
                'chat_id'=>$user_id
        ], $token);

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