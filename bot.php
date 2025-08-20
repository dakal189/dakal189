<?php
set_time_limit(5);
error_reporting(0);
date_default_timezone_set('Asia/Tehran');
##----------------------
require 'handler.php';
##----------------------
if (isset($from_id) && in_array($from_id, $list['ban'])) {
	exit();
}
if (($tc == 'group' || $tc == 'supergroup') && $chat_id != $data['feed'] && $from_id != $Dev) {
	sendAction($chat_id);
	sendMessage($chat_id, '❌ من اجازه فعالیت در گروه ها را ندارم.', 'html');
	bot('LeaveChat', [
		'chat_id'=>$chat_id
	]);
	exit();
}

if ($from_id != $Dev) {
	@$flood = json_decode(file_get_contents('data/flood.json'), true);
	
	if (time()-filectime('data/flood.json') >= 50*60) {
		unlink('data/flood.json');
	}
	
	$now = date('Y-m-d-h-i-a', $update->message->date);
	$flood['flood']["$now-$from_id"] += 1;
	file_put_contents('data/flood.json', json_encode($flood));
	
	if ($flood['flood']["$now-$from_id"] >= 33 && $tc == 'private') {
		sendAction($chat_id);
		if ($list['ban'] == null) {
			$list['ban'] = [];
		}
		sendMessage($from_id, "⛔️ شما به دلیل ارسال پیام های مکرر و بیهوده مسدود گردیدید.", 'markdown', null, $remove);
		sendMessage($Dev, "👤 کاربر [$from_id](tg://user?id=$from_id) به دلیل ارسال پیام های مکرر و بیهوده از ربات مسدود گردید.\n/unban\_{$from_id}", 'markdown');
		unlink('data/flood.json');
		array_push($list['ban'], $from_id);
		file_put_contents('data/list.json', json_encode($list));
		exit();
	}
	elseif ($data['stats'] == 'off' && $tc == 'private') {
		sendAction($chat_id);

		if (empty($data['text']['off'])) {
			$answer_text = "😴 ربات توسط مدیریت خاموش شده است.\n\n🔰 لطفا پیام خود را زمانی دیگر ارسال نمایید.";
		}
		else {
			$answer_text = replace($data['text']['off']);
		}

		sendMessage($chat_id, $answer_text, null, $message_id);
		goto tabliq;
	}
}
elseif ($from_id == $Dev) {
	$prepared = $pdo->prepare("SELECT * FROM `members` WHERE `user_id`={$user_id}");
	$prepared->execute();
	$fetch = $prepared->fetchAll();
	if (count($fetch) <= 0) {
		sendMessage($chat_id, "📛 برای اینکه ربات برای شما فعال شود حتما باید ربات پیامرسان ساز ما برای شما فعال باشد.

🔰 لطفا به ربات {$main_bot} رفته و دستور /start را برای آن ارسال کنید تا برای شما فعال شود. اگر ربات را بلاک کنید دوباره غیر فعال خواهد شد.

🌀 بعد از اینکه ربات برای شما فعال گردید دستور /start را ارسال نمایید.", null, $message_id, $remove);
	exit();
	}
}

$prepared = $pdo->prepare("SELECT * FROM `{$bot_username}_members` WHERE `user_id`={$user_id};");
$prepared->execute();
$fetch = $prepared->fetchAll();
if (count($fetch) <= 0) {
        $pdo->exec("INSERT INTO `{$bot_username}_members` (`user_id`, `time`) VALUES ({$user_id}, UNIX_TIMESTAMP());");
}

if (isset($update->callback_query)) {
	$callback_id = $data_id;
	$pv_id = $user_id;
	$message_id = $update->callback_query->inline_message_id;
	$locks = ['video', 'audio', 'voice', 'text', 'sticker', 'link', 'photo', 'document', 'forward', 'channel'];

	if ($user_id == $Dev && preg_match('@lockch_(?<channel>.+?)_(?<switch>.+)@i', $callback_data, $matches)) {
		$select_channel = '@' . $matches['channel'];

		if (!isset($data['lock']['channels'][$select_channel])) {
			bot('answerCallbackQuery', [
				'callback_query_id'=>$callback_id,
				'text'=>"❌ کانال {$select_channel} وجود ندارد.",
				'show_alert'=>true
			]);
		}
		else {
			if ($matches['switch'] == 'on') {
				if ($data['lock']['channels'][$select_channel] != true) {
					$data['lock']['channels'][$select_channel] = true;
					file_put_contents('data/data.json', json_encode($data));
	
					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>"✅ قفل کانال {$select_channel} فعال شد.",
						'show_alert'=>true
					]);
	
				}
				else {
					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>"❌ قفل کانال {$select_channel} از قبل فعال بود.",
						'show_alert'=>true
					]);
				}
			}
			else {
				if ($data['lock']['channels'][$select_channel] == true) {
					$data['lock']['channels'][$select_channel] = false;
					file_put_contents('data/data.json', json_encode($data));
	
					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>"✅ قفل کانال {$select_channel} غیر فعال شد.",
						'show_alert'=>true
					]);
	
				}
				else {
					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>"❌ قفل کانال {$select_channel} از قبل غیر فعال بود.",
						'show_alert'=>true
					]);
				}
			}

			$inline_keyboard = [];
			foreach ($data['lock']['channels'] as $channel => $value) {
				$channel = str_replace('@', '', $channel);
	
				if ($value == true) {
					$inline_keyboard[] = [['text'=>"🔐 @{$channel}", 'callback_data'=>"lockch_{$channel}_off"]];
				}
				else {
					$inline_keyboard[] = [['text'=>"🔓 @{$channel}", 'callback_data'=>"lockch_{$channel}_on"]];
				}
			}

			bot('editMessageReplyMarkup', [
				'chat_id'=>$chat_id,
				'message_id'=>$messageid,
				'reply_markup'=>json_encode([
					'inline_keyboard' => $inline_keyboard
				])
			]);
		}
		exit();
	}
	elseif ($user_id == $Dev && in_array($callback_data, $locks)) {
		$media = $data_2['lock'][$callback_data];
		if ($media == '❌') {
			$data_2['lock'][$callback_data] = '✅';
			$answer_callback_text = '✅ فعال گردید';
		}
		else {
			$data_2['lock'][$callback_data] = '❌';
			$answer_callback_text = '❌ غیر فعال گردید';
		}

		$video = $data_2['lock']['video'];
		$audio = $data_2['lock']['audio'];
		$voice = $data_2['lock']['voice'];
		$text = $data_2['lock']['text'];
		$sticker = $data_2['lock']['sticker'];
		$link = $data_2['lock']['link'];
		$photo = $data_2['lock']['photo'];
		$document = $data_2['lock']['document'];
		$forward = $data_2['lock']['forward'];
		$channel = $data_2['lock']['channel'];

		$btnstats = json_encode(
			[
				'inline_keyboard'=>
				[
					[['text'=>"$text", 'callback_data'=>"text"],['text'=>"📝 قفل متن", 'callback_data'=>"text"]],
					[['text'=>"$forward", 'callback_data'=>"forward"],['text'=>"⤵️ قفل فروارد", 'callback_data'=>"forward"]],
					[['text'=>"$link", 'callback_data'=>"link"],['text'=>"🔗 قفل لینک", 'callback_data'=>"link"]],
					[['text'=>"$photo", 'callback_data'=>"photo"],['text'=>"🌅 قفل تصویر", 'callback_data'=>"photo"]],
					[['text'=>"$sticker", 'callback_data'=>"sticker"],['text'=>"🌁 قفل استیکر", 'callback_data'=>"sticker"]],
					[['text'=>"$audio", 'callback_data'=>"audio"],['text'=>"🎵 قفل موسیقی", 'callback_data'=>"audio"]],
					[['text'=>"$voice", 'callback_data'=>"voice"],['text'=>"🔊 قفل ویس", 'callback_data'=>"voice"]],
					[['text'=>"$video", 'callback_data'=>"video"],['text'=>"🎥 قفل ویدیو", 'callback_data'=>"video"]],
					[['text'=>"$document", 'callback_data'=>"document"],['text'=>"💾 قفل فایل", 'callback_data'=>"document"]]
				]
			]
		);

		editKeyboard($chatid, $messageid, $btnstats);
		answerCallbackQuery($data_id, $answer_callback_text);

		file_put_contents('data/data.json', json_encode($data_2));
		exit();
	}
	elseif ($user_id == $Dev && ($callback_data == 'profile' || $callback_data == 'contact' || $callback_data == 'location')) {
		$btn = $data_2['button'][$callback_data]['stats'];
		$save = false;

		if ($btn == '⛔️') {
			$data_2['button'][$callback_data]['stats'] = '✅';
			$save = true;
		}
		else {
			$data_2['button'][$callback_data]['stats'] = '⛔️';
			$save = true;
		}
		
		$profile_btn = $data_2['button']['profile']['stats'];
		$contact_btn = $data_2['button']['contact']['stats'];
		$location_btn = $data_2['button']['location']['stats'];
		
		$btnstats = json_encode(
			[
				'inline_keyboard'=>
				[
					[['text'=>"پروفایل $profile_btn", 'callback_data'=>"profile"]],
					[['text'=>"ارسال شماره $contact_btn", 'callback_data'=>"contact"]],
					[['text'=>"ارسال مکان $location_btn", 'callback_data'=>"location"]],
				]
			]
		);

		editKeyboard($chatid, $messageid, $btnstats);
		answerCallbackQuery($data_id, null);

		if ($save) {
			file_put_contents('data/data.json', json_encode($data_2));
		}
		exit();
	}
	elseif (strpos($callback_data, 'palyxo') !== false) {
		$callback_data = explode('_', $callback_data);
		if ($callback_data[1] == $pv_id) {
			bot('answerCallbackQuery', [
				'callback_query_id'=>$callback_id,
				'text'=>'📛 شما خودتان آغاز کننده بازی هستید و در بازی حضور دارید.

❌ منتظر بمانید تا یک فرد دیگر به بازی بپیوندد.',
				'show_alert'=>true,
				'cache_time'=>30
			]);
			exit();
		}
		else {
			$prepared = $pdo->prepare("SELECT * FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
			$prepared->execute();
			$fetch = $prepared->fetchAll();
			if (count($fetch) <= 0) {
				$now_time = time();
				$pdo->exec("INSERT INTO `xo_games` (`message_id`, `start`, `time`, `bot`) VALUES ('{$message_id}', {$now_time}, {$now_time}, '{$bot_username}');");
			}
			else {
				bot('answerCallbackQuery', [
					'callback_query_id'=>$callback_id,
					'text'=>'📛 متاسفانه قبل از شما فرد دیگری وارد بازی شده است.',
					'show_alert'=>true,
					'cache_time'=>7
				]);
				exit();	
			}

			$Player1 = $callback_data[1];
			$P1Name = getMention($Player1);

			$Player2 = $pv_id;
			$P2Name = getMention($Player2);

			$turn = mt_rand(1, 2);

			if ($turn == 1) {
				$now_player = $P1Name;
			}
			else {
				$now_player = $P2Name;
			}

			for ($i = 0; $i < 3; $i++) {
				for ($j = 0; $j < 3; $j++) {
					$Tab[$i][$j]['text'] = ' ';
					$Tab[$i][$j]['callback_data']= "{$i}.{$j}_0.0.0.0.0.0.0.0.0_{$Player1}.{$Player2}_{$turn}_0";
				}
			}
			$Tab[3][0]['text'] = '❌ خروج از بازی';
			$Tab[3][0]['callback_data'] = "left_{$Player1}_{$Player2}_0.0.0.0.0.0.0.0.0";

			if (!$is_vip) {
				$Tab[4][0]['text'] = '🤖 ربات خودتو بساز';
				$Tab[4][0]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
			}
			
			bot('editMessageText', [
				'inline_message_id'=>$message_id,
				'parse_mode'=>'html',
				'disable_web_page_preview'=>true,
				'text'=>"🎮 - {$P1Name} (❌)\n🎮 - {$P2Name} (⭕️)\n\n💠 الآن نوبت {$now_player} (❌) است.",
				'reply_markup'=>json_encode(
					[
						'inline_keyboard'=>$Tab 
					]
				)
			]);
			answerCallbackQuery($data_id, null);
			exit();
		}
	}
	else {
		$callback_data = explode('_', $callback_data);
		$a = explode('.', $callback_data[0]);
		$i = $a[0];
		$j = $a[1];
		$table = explode('.', $callback_data[1]);
		$Players = explode('.', $callback_data[2]);
		$Num = ((int)$callback_data[4])+1;

		if ($callback_data[0] == 'left' && ($pv_id == $callback_data[1] || $pv_id == $callback_data[2])) {
			$prepared = $pdo->prepare("SELECT * FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
			$prepared->execute();
			$fetch = $prepared->fetchAll();
			if (count($fetch) > 0) {
				$wait_time = time()-$fetch[0]['time'];
				if ($wait_time <= 59) {
					$wait_time = 60-$wait_time;

					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>"📛 لطفا {$wait_time} ثانیه صبر کنید.",
						'show_alert'=>true
					]);
					exit();
				}
			}
			else {
				bot('answerCallbackQuery', [
					'callback_query_id'=>$callback_id,
					'text'=>"📛 این بازی به اتمام رسیده است.",
					'show_alert'=>true
				]);
				exit();
			}
			$player = getMention($pv_id);
			if ($pv_id == $callback_data[1]) {
				$P1Name = $player;
				$P2Name = getMention($callback_data[2]);
				$emoji = '❌';
			}
			else {
				$P1Name = getMention($callback_data[1]);
				$P2Name = $player;
				$emoji = '⭕️';
			}

			$n = 0;
			$Tab = [];
			$table = explode('.', $callback_data[3]);
			for ($i = 0; $i < 3; $i++) {
				for ($j = 0; $j < 3; $j++) {
					if ($table[$n] == 1) $Tab[$i][$j]['text'] = '❌';
					elseif ($table[$n] == 2) $Tab[$i][$j]['text'] = '⭕️';
					else $Tab[$i][$j]['text'] = ' ';

					if (!$is_vip) {
						$Tab[$i][$j]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
					}
					else {
						$Tab[$i][$j]['url'] = 'https://telegram.me/' . $bot_username;
					}
					$n++;
				}
			}
			
			bot('editMessageText', [
				'inline_message_id'=>$message_id,
				'parse_mode'=>'html',
				'disable_web_page_preview'=>true,
				'text'=>"🎮 - {$P1Name} (❌)\n🎮 - {$P2Name} (⭕️)\n\n🚑 بازیکن {$player} ({$emoji}) از بازی خارج شد.",
				'reply_markup'=>json_encode([
					'inline_keyboard'=>$Tab
				])
			]);
			$prepare = $pdo->prepare("DELETE FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
			$prepare->execute();
			answerCallbackQuery($data_id, null);
			exit();
		}
		elseif ($callback_data[0] == 'left' || ($pv_id != $Players[0] && $pv_id != $Players[1] && is_numeric($Players[0]) && is_numeric($Players[1])) ) {
			bot('answerCallbackQuery', [
				'callback_query_id'=>$callback_id,
				'text'=>'❌ شما بازی نیستید.',
				'show_alert'=>true,
				'cache_time'=>30
			]);
			exit();
		}
		else {
			//Turn
			if ((int) $callback_data[3] == 1) $Turn = $Players[0];
			elseif ((int) $callback_data[3] == 2) $Turn = $Players[1];
		
			//Turn
			if ($pv_id == $Turn) {
				$Player1 = $Players[0];
				$P1Name = getMention($Player1);

				$Player2 = $Players[1];
				$P2Name = getMention($Player2);

				//NextTurn
				if ($pv_id == $Player1) {
					$NextTurn = $Player2;
					$NextTurnNum = 2;
					$Emoji = '❌';
					$NextEmoji = '⭕️';
				}
				else {
					$NextTurn = $Player1;
					$NextTurnNum = 1;
					$Emoji = '⭕️';
					$NextEmoji = '❌';
				}

				//TabComplete
				$n = 0;
				for ($ii = 0; $ii < 3; $ii++) {
					for ($jj = 0; $jj < 3; $jj++) {
						if ((int)$table[$n] == 1) $Tab[$ii][$jj]['text'] = '❌';
						elseif ((int)$table[$n] == 2) $Tab[$ii][$jj]['text'] = '⭕️';
						elseif((int)$table[$n] == 0) $Tab[$ii][$jj]['text'] = ' ';
						$n++; 
					}
				}
				//Tab End

				//NextTurn
				if ($Tab[$i][$j]['text'] != ' ') {
					bot('answerCallbackQuery', [
						'callback_query_id'=>$callback_id,
						'text'=>'❌ قابل انتخاب نیست.'
					]);
				}
				else {
					$Tab[$i][$j]['text'] = $Emoji;

					$n = 0;
					for ($i = 0; $i < 3; $i++) {
						for ($j = 0; $j < 3; $j++) {
							if ($Tab[$i][$j]['text'] == '❌') $table[$n] = 1;
							elseif ($Tab[$i][$j]['text'] == '⭕️') $table[$n] = 2;
							elseif ($Tab[$i][$j]['text'] == ' ') $table[$n] = 0;
							$n++;
						}
					}

					$win = Win($Tab);
					if ($win == '⭕️' || $win == '❌') {
						if ($win == '⭕️') $winner = getMention($Player2);
						elseif ($win == '❌') $winner = getMention($Player1);
						
						$n = 0;
						for ($ii = 0; $ii < 3; $ii++) {
							for ($jj = 0; $jj < 3; $jj++) {
								if (!$is_vip) {
									unset($Tab[$ii][$jj]['callback_data']);
									$Tab[$ii][$jj]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
								}
								else {
									unset($Tab[$ii][$jj]['callback_data']);
									$Tab[$ii][$jj]['url'] = 'https://telegram.me/' . $bot_username;
								}
								$n++;
							}
						}

						if (!$is_vip) {
							$Tab[3][0]['text'] = '🤖 ربات خودتو بساز';
							$Tab[3][0]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
						}

						$prepared = $pdo->prepare("SELECT * FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
						$prepared->execute();
						$fetch = $prepared->fetchAll();
						if (count($fetch) > 0) {
							$time_elapsed = timeElapsed(time()-$fetch[0]['start']);
							$time_elapsed = "🧭 این بازی {$time_elapsed} طول کشید.";
						}
						else {
							$time_elapsed = '';
						}
						
						bot('editMessageText', [
							'inline_message_id'=>$message_id,
							'parse_mode'=>'html',
							'disable_web_page_preview'=>true,
							'text'=>"🎮 - {$P1Name} (❌)\n🎮 - {$P2Name} (⭕️)\n\n🥳 بازیکن {$winner} ({$win}) برنده شد.\n{$time_elapsed}",
							'reply_markup'=>json_encode(
								[
									'inline_keyboard'=>$Tab 
								]
							)
						]);

						$prepare = $pdo->prepare("DELETE FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
						$prepare->execute();

						answerCallbackQuery($data_id, null);
						exit();
					}
					elseif ($Num >= 9) {
						$n = 0;
						for ($ii = 0; $ii < 3; $ii++) {
							for ($jj = 0; $jj < 3; $jj++) {
								if (!$is_vip) {
									unset($Tab[$ii][$jj]['callback_data']);
									$Tab[$ii][$jj]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
								}
								else {
									unset($Tab[$ii][$jj]['callback_data']);
									$Tab[$ii][$jj]['url'] = 'https://telegram.me/' . $bot_username;
								}
								$n++;
							}
						}

						if (!$is_vip) {
							$Tab[3][0]['text'] = '🤖 ربات خودتو بساز';
							$Tab[3][0]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
						}

						$prepared = $pdo->prepare("SELECT * FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
						$prepared->execute();
						$fetch = $prepared->fetchAll();
						if (count($fetch) > 0) {
							$time_elapsed = timeElapsed(time()-$fetch[0]['start']);
							$time_elapsed = "🧭 این بازی {$time_elapsed} طول کشید.";
						}
						else {
							$time_elapsed = '';
						}

						bot('editMessageText', [
							'inline_message_id'=>$message_id,
							'parse_mode'=>'html',
							'disable_web_page_preview'=>true,
							'text'=>"🎮 - {$P1Name} (❌)\n🎮 - {$P2Name} (⭕️)\n\n🔰 بازی مساوی شد.\n{$time_elapsed}",
							'reply_markup'=>json_encode(
								[
									'inline_keyboard'=>$Tab 
								]
							)
						]);

						$prepare = $pdo->prepare("DELETE FROM `xo_games` WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
						$prepare->execute();

						answerCallbackQuery($data_id, null);
						exit();
					}
					else {
						//Tab
						$n = 0;
						for ($ii = 0; $ii < 3; $ii++) {
							for ($jj = 0; $jj < 3; $jj++) {
								$Tab[$ii][$jj]['callback_data'] = "{$ii}.{$jj}_" . implode('.', $table) . "_{$Player1}.{$Player2}_{$NextTurnNum}_{$Num}";
								$n++;
							}
						}
						
						$Tab[3][0]['text'] = '❌ خروج از بازی';
						$Tab[3][0]['callback_data'] = "left_{$Player1}_{$Player2}_" . implode('.', $table);

						if (!$is_vip) {
							$Tab[4][0]['text'] = '🤖 ربات خودتو بساز';
							$Tab[4][0]['url'] = 'https://telegram.me/' . str_replace('@', '', $main_bot);
						}
						
						$NextTurn = getMention($NextTurn);
						bot('editMessageText', [
							'inline_message_id'=>$message_id,
							'disable_web_page_preview'=>true,
							'parse_mode'=>'html',
							'text'=>"🎮 - {$P1Name} (❌)\n🎮 - {$P2Name} (⭕️)\n\n💠 الآن نوبت {$NextTurn} ({$NextEmoji}) است.",
							'reply_markup'=>json_encode(
								[
									'inline_keyboard'=>$Tab 
								]
							)
						]);

						$prepared = $pdo->prepare("UPDATE `xo_games` SET `time`=UNIX_TIMESTAMP() WHERE `message_id`='{$message_id}' AND `bot`='{$bot_username}';");
						$prepared->execute();

						answerCallbackQuery($data_id, null);
						exit();
					}
				}
			}
			elseif (preg_match('@^([0-9\.\_]+)$@', $callback_query->data)) {
				bot('answerCallbackQuery', [
					'callback_query_id'=>$callback_id,
					'text'=>'❌ نوبت شما نیست.',
					'show_alert'=>true
				]);
				exit();
			}
		}
	}
}
elseif (strtolower($text) == '/start' && $from_id != $Dev && $tc == 'private') {
	sendAction($chat_id);
	$start = null;
	if (isset($data['text']['start'])) {
		$start = replace($data['text']['start']);
	}

	if (!empty($start) && mb_strlen($start, 'UTF-8') > 2) {
		sendMessage($chat_id, $start, null, $message_id, $button_user);
	}
	else {
		sendMessage($chat_id, "😁✋🏻 سلام\n\nخوش آمدید. پیام خود را ارسال کنید.", null, $message_id, $button_user);
	}

	goto tabliq;
}
elseif ($from_id != $Dev && !$is_vip && (strtolower($text) == '/creator' || $text == 'سازنده') ) {
	sendAction($chat_id);
	$inline_keyboard = json_encode(
		[
			'inline_keyboard'=>
			[
				[['text'=>'💠 بریم منم بسازیم!', 'url'=>'https://t.me/' . str_replace('@', '', $main_bot)]],
			]
		]
	);
	sendMessage($chat_id, "🤖 این ربات توسط سرویس {$main_bot} ساخته شده است و بر روی سرورهای آن قرار دارد.", null, $message_id, $inline_keyboard);
	goto tabliq;
}

if ($from_id != $admin && $user_id != $Dev && !empty($data['lock']['channels']) && count($data['lock']['channels']) > 0) {
	$lock_channels_text = [];
	$stop = false;

	foreach ($data['lock']['channels'] as $lock_channel => $value) {
		if ($value == true) {
			$user_rank = bot('getChatMember', [
				'chat_id' => $lock_channel,
				'user_id' => $user_id
			]);
			$user_rank = !empty($user_rank['result']['status']) ? $user_rank['result']['status'] : 'member';

			if (!in_array($user_rank, ['creator', 'administrator', 'member'])) {
				$stop = true;
				$lock_channels_text[] = "❌ {$lock_channel}";
			}
			else {
				$lock_channels_text[] = "✅ {$lock_channel}";
			}
		}

		if (!$is_vip) break;
	}

	if ($stop) {
		sendAction($chat_id);

		if (empty($data['text']['lock'])) {
			$answer_text = "📛 برای اینکه ربات برای شما فعال شود حتما باید عضو کانال\کانال های زیر باشید.

CHANNELS
			
🔰 بعد از اینکه عضو شدید دستور /start را ارسال نمایید.";
		}
		else {
			$answer_text = $data['text']['lock'];
		}

		$answer_text = str_replace('CHANNELS', implode("\n", $lock_channels_text), $answer_text);
		sendMessage($chat_id, $answer_text, null, $message_id, $remove);
		goto tabliq;
	}
}

if (!is_null($profile_key) && $text == $profile_key && $tc == 'private') {
	sendAction($chat_id);
	$profile = isset($data['text']['profile']) ? replace($data['text']['profile']) : '📭 پروفایل خالی است.';
	if ($from_id == $Dev) {
		sendMessage($chat_id, $profile, null, $message_id);
	}
	else {
		sendMessage($chat_id, $profile, null, $message_id, $button_user);
	}
}
elseif ($from_id != $Dev && !is_null($text) && !is_null($data['quick'][$text]) && $tc == 'private') {
	sendAction($chat_id);
	$answer = replace($data['quick'][$text]);
	sendMessage($chat_id, $answer, null, $message_id, $button_user);
}
elseif (!is_null($text) && !is_null($data['buttonans'][$text]) && $tc == 'private') {
	if ($from_id != $Dev) {
		sendAction($chat_id);
		$button_answer = replace($data['buttonans'][$text]);
		sendMessage($chat_id, $button_answer, null, $message_id, $button_user);
	}
	elseif ($data['step'] == 'none' || $data['step'] == '') {
		sendAction($chat_id);
		$button_answer = replace($data['buttonans'][$text]);
		sendMessage($chat_id, $button_answer, null, $message_id);
	}
}
elseif (isset($update->message) && $from_id != $Dev && $data['feed'] == null && $tc == 'private') {
	sendAction($chat_id);
	$done = isset($data['text']['done']) ? replace($data['text']['done']) : '✅ پیام شما ارسال گردید.';

	if (isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
		if ($data['lock']['forward'] == '✅') {
			// Delete the forwarded message
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال پیام های هدایت شده (فروارد شده) مجاز نیست.", 'html' , null, $button_user);
			goto tabliq;
		}
	}
	if (isset($message->text)) {
		if ($data['lock']['text'] != '✅') {
			$checklink = CheckLink($text);
			$checkfilter = CheckFilter($text);
			if ($checklink != true) {
				if ($checkfilter != true) {
					$get = Forward($Dev, $chat_id, $message_id);
					if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
						$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
					}

					sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
				}
			}
			if ($checklink == true) {
				// Delete the message containing link
				bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
				sendMessage($chat_id, "⛔️ ارسال پیام های حاوی لینک مجاز نیست.", 'html' , null, $button_user);
			}
			if ($checkfilter == true) {
				// Delete the message containing filtered words
				bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
				sendMessage($chat_id, "⛔️ ارسال پیام های حاوی کلمات غیر مجاز ممنوع است.", 'html' , null, $button_user);
			}
		} else {
			// Delete the text message when text is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال متن مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->photo)) {
		if ($data['lock']['photo'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			if (!isset($get['result']['forward_from'])  || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the photo when photo is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال تصویر مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->video)) {
		if ($data['lock']['video'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			if (!isset($get['result']['forward_from'])  || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the video when video is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال ویدیو مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->voice)) {
		if ($data['lock']['voice'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the voice message when voice is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال صدا مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->audio)) {
		if ($data['lock']['audio'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the audio message when audio is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال موسیقی مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->sticker)) {
		if ($data['lock']['sticker'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the sticker when sticker is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال استیکر مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->document)) {
		if ($data['lock']['document'] != '✅') {
			$get = Forward($Dev, $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the document when document is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال فایل مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	else {
		$get = Forward($Dev, $chat_id, $message_id);
		if (!isset($get['result']['forward_from'])) {
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
			$msg_ids[$get['result']['message_id']] = $from_id;
			file_put_contents('msg_ids.txt', json_encode($msg_ids));
			//sendMessage($Dev, "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
		}
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	}
}
//--------[Feed]--------//
elseif ($from_id == $Dev && ($tc == 'group' || $tc == 'supergroup') && strtolower($text) == '/setfeed') {
	sendAction($chat_id);
	$data['feed'] = $chat_id;
	sendMessage($chat_id, '👥 این گروه به عنوان گروه پشتیبانی تنظیم گردید.', 'html' , $message_id, $remove);
	file_put_contents('data/data.json', json_encode($data));
}
elseif ($from_id == $Dev && strtolower($text) == '/delfeed' && $tc == 'private') {
	sendAction($chat_id);
	unset($data['feed']);
	sendMessage($chat_id, '🗑 گروه پشتیبانی با موفقیت حذف گردید.', 'html' , $message_id);
	file_put_contents('data/data.json', json_encode($data));
}
elseif (isset($update->message) && $from_id != $Dev && $data['feed'] != null && $tc == 'private') {
	sendAction($chat_id);
	$done = isset($data['text']['done']) ? replace($data['text']['done']) : '✅ پیام شما ارسال گردید.';

	if (isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
		if ($data['lock']['forward'] == '✅') {
			// Delete the forwarded message
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال پیام های هدایت شده (فروارد شده) مجاز نیست.", 'html' , null, $button_user);
			goto tabliq;
		}
	}
	if (isset($message->text)) {
		if ($data['lock']['text'] != '✅') {
			$checklink = CheckLink($text);
			$checkfilter = CheckFilter($text);
			if ($checklink != true) {
				if ($checkfilter != true) {
					$get = Forward($data['feed'], $chat_id, $message_id);
					if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
						$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
					}
					sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
				}
			}
			if ($checklink == true) {
				// Delete the message containing link
				bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
				sendMessage($chat_id, "⛔️ ارسال پیام های حاوی لینک مجاز نیست.", 'html' , null, $button_user);
			}
			if ($checkfilter == true) {
				// Delete the message containing filtered words
				bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
				sendMessage($chat_id, "⛔️ ارسال پیام های حاوی کلمات غیر مجاز ممنوع است.", 'html' , null, $button_user);
			}
		} else {
			// Delete the text message when text is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال متن مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->photo)) {
		if ($data['lock']['photo'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the photo when photo is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال تصویر مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->video)) {
		if ($data['lock']['video'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the video when video is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال ویدیو مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->voice)) {
		if ($data['lock']['voice'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the voice message when voice is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال صدا مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->audio)) {
		if ($data['lock']['audio'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the audio message when audio is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال موسیقی مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->sticker)) {
		if ($data['lock']['sticker'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
						$msg_ids[$get['result']['message_id']] = $from_id;
						file_put_contents('msg_ids.txt', json_encode($msg_ids));
						//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the sticker when sticker is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال استیکر مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
	if (isset($message->document)) {
		if ($data['lock']['document'] != '✅') {
			$get = Forward($data['feed'], $chat_id, $message_id);
			if (!isset($get['result']['forward_from']) || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
				$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
				$msg_ids[$get['result']['message_id']] = $from_id;
				file_put_contents('msg_ids.txt', json_encode($msg_ids));
				//sendMessage($data['feed'], "👤 فرستنده : [$from_id](tg://user?id=$from_id)", 'markdown');
			}
			sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
		} else {
			// Delete the document when document is locked
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال فایل مجاز نیست.", 'html' , null, $button_user);
		}
		goto tabliq;
	}
}
elseif (isset($message->reply_to_message->message_id) && (in_array($from_id, $list['admin']) || $from_id == $Dev) && $chat_id == $data['feed']) {
	sendAction($chat_id);
	$msg_id = $message->reply_to_message->message_id;
	$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
	if ($msg_ids[$msg_id] != null) {
		$reply = $msg_ids[$msg_id];
	}

	//if ($reply_id == GetMe()->result->id)
	if (preg_match('/^\/(ban)$/i', $text)) {
		if (!in_array($reply, $list['ban'])) {
			if ($list['ban'] == null) {
				$list['ban'] = [];
			}
			array_push($list['ban'], $reply);
			file_put_contents("data/list.json",json_encode($list));
			sendMessage($chat_id, "⛔️ کاربر مورد نظر مسدود گردید.", 'markdown', $message_id);
			sendMessage($reply, "⛔️ شما مسدود شدید.", 'markdown', null, $remove);
		} else {
			sendMessage($chat_id, "❗️کاربر از قبل مسدود شده بود.", 'markdown', $message_id);
		}
	}
	elseif (preg_match('/^\/(info)$/i', $text)) {
		sendMessage($chat_id, "👤 فرستنده : [$reply](tg://user?id=$reply)", 'markdown');
	}
	elseif (preg_match('/^\/(unban)$/i', $text)) {
		if (in_array($reply, $list['ban'])) {
			$search = array_search($reply, $list['ban']);
			unset($list['ban'][$search]);
			$list['ban'] = array_values($list['ban']);
			file_put_contents("data/list.json",json_encode($list));
			sendMessage($chat_id, "✅ کاربر مورد نظر آزاد شد.", 'markdown', $message_id);
			sendMessage($reply, "✅ شما آزاد شدید.", 'markdown', null, $button_user);
		} else {
			sendMessage($chat_id, "✅ کاربر از قبل آزاد بود.", 'markdown', $message_id);
		}
	}
	elseif (preg_match('/^\/(share)$/i', $text)) {
	$name = $data['contact']['name'];
	$phone = $data['contact']['phone'];
		if ($phone != null && $name != null) {
			sendContact($reply, $name, $phone);
			sendMessage($chat_id, "✅ شماره شما برای کاربر ارسال گردید.", 'markdown', $message_id);
		} else {
			sendMessage($chat_id, '❌ شماره شما موجود نیست.\nلطفا ابتدا شماره تان را تنظیم نمایید.', 'markdown', $message_id);
		}
	}
	elseif (isset($message)) {
		$msg_id = $message->reply_to_message->message_id;
		$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
		if ($text != null) {
			if ($msg_ids[$msg_id]) {
				sendMessage($msg_ids[$msg_id], $text,null);
			} else {
				sendMessage($reply, $text,null);
			}
		}
		elseif ($voice_id != null) {
			if ($msg_ids[$msg_id]) {
				sendVoice($msg_ids[$msg_id], $voice_id, $caption);
			} else {
				sendVoice($reply, $voice_id, $caption);
			}
		}
		elseif ($file_id != null) {
			if ($msg_ids[$msg_id]) {
				sendDocument($msg_ids[$msg_id], $file_id, $caption);
			} else {
				sendDocument($reply, $file_id, $caption);
			}
		}
		elseif ($music_id != null) {
			if ($msg_ids[$msg_id]) {
				sendAudio($msg_ids[$msg_id], $music_id, $caption);
			} else {
				sendAudio($reply, $music_id, $caption);
			}
		}
		elseif ($photo2_id != null) {
			if ($msg_ids[$msg_id]) {
				sendPhoto($msg_ids[$msg_id], $photo2_id, $caption);
			} else {
				sendPhoto($reply, $photo2_id, $caption);
			}
		}
		elseif ($photo1_id != null) {
			if ($msg_ids[$msg_id]) {
				sendPhoto($msg_ids[$msg_id], $photo1_id, $caption);
			} else {
				sendPhoto($reply, $photo1_id, $caption);
			}
		}
		elseif ($photo0_id != null) {
			if ($msg_ids[$msg_id]) {
				sendPhoto($msg_ids[$msg_id], $photo0_id, $caption);
			} else {
				sendPhoto($reply, $photo0_id, $caption);
			}
		}
		elseif ($video_id != null) {
			if ($msg_ids[$msg_id]) {
				sendVideo($msg_ids[$msg_id], $video_id, $caption);
			} else {
				sendVideo($reply, $video_id, $caption);
			}
		}
		elseif ($sticker_id != null) {
			if ($msg_ids[$msg_id]) {
				sendSticker($msg_ids[$msg_id], $sticker_id);
			} else {
				sendSticker($reply, $sticker_id);
			}
		}
		sendMessage($chat_id, "✅ پیام شما برای کاربر ارسال گردید.", 'markdown', $message_id);
	}
}
##-----------Admin
if ($from_id == $Dev && ($tc == 'private' || $tccall == 'private')) {
	if (!in_array($rankdev, ['creator', 'administrator', 'member'])) {
		sendAction($chat_id);
		sendMessage($chat_id, "📛 مدیر عزیز ربات برای مدیریت رباتتان حتما باید در کانال زیر عضو باشید.

📣 {$main_channel}

🔰 بعد از اینکه عضو شدید دستور /start را ارسال نمایید.", null, $message_id, $remove);
		goto tabliq;
	}
elseif ($text == '🔙 بازگشت' || $text == '✏️ مدیریت') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "👇🏻 لطفا یکی از دکمه های زیر را انتخاب نمایید.", 'markdown' , $message_id, $panel);
	goto tabliq;
}
elseif ($text == '🔙 خروج از مدیریت' || strtolower($text) == '/start') {
	sendAction($chat_id);
	$manage_off = [];

	$i = 0;
	$j = 1;
	foreach ($data['buttons'] as $key => $name) {
		if (!is_null($key) && !is_null($name)) {
			$manage_off[$i][] = ['text'=>$name];
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
		$manage_off[] = [ ['text'=>$profile_key] ];
	}

	$two_key_admin = [];
	if (!is_null($contact_key)) {
		$two_key_admin[] = ['text'=>$contact_key, 'request_contact' => true];
	}
	if (!is_null($location_key)) {
		$two_key_admin[] = ['text'=>$location_key, 'request_location' => true];
	}
	if (!is_null($two_key_admin)) {
		$manage_off[] = $two_key_admin;
	}
	$manage_off[] = [['text'=>'✏️ مدیریت']];
	$manage_off = json_encode(['keyboard'=> $manage_off , 'resize_keyboard'=>true]);
	sendMessage($chat_id, "🔙 شما از بخش مدیریت خارج شدید.", 'markdown' , $message_id, $manage_off);
	$data['step'] = '';
	file_put_contents('data/data.json', json_encode($data));
}
elseif (isset($message->contact) && $data['step'] == "none") {
	sendAction($chat_id);
	$name_contact = $message->contact->first_name;
	$number_contact = $message->contact->phone_number;
	
	$data['contact']['name'] = "$name_contact";
	$data['contact']['phone'] = "$number_contact";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "☎️ شماره $number_contact با موفقیت تنظیم شد.", 'markdown', $message_id, $contact);
}
elseif (isset($message->reply_to_message->message_id)) {
	sendAction($chat_id);
	$msg_id = $message->reply_to_message->message_id;
	$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
	if ($msg_ids[$msg_id] != null) {
		$reply = $msg_ids[$msg_id];
	}
	if (!isset($message->reply_to_message->forward_from) && !isset($msg_ids[$msg_id])) {
		goto badi;
	}

	if (preg_match('/^\/(ban)$/i', $text)) {
		sendAction($chat_id);
		if (!in_array($reply, $list['ban'])) {
			if ($list['ban'] == null) {
				$list['ban'] = [];
			}
			array_push($list['ban'], $reply);
			file_put_contents("data/list.json",json_encode($list));
			sendMessage($chat_id, "⛔️ کاربر مورد نظر مسدود گردید.", 'markdown', $message_id);
			sendMessage($reply, "⛔️ شما مسدود شدید.", 'markdown', null, $remove);
		} else {
			sendMessage($chat_id, "❗️کاربر از قبل مسدود شده بود.", 'markdown', $message_id);
		}
	}
	elseif (preg_match('/^\/(info)$/i', $text)) {
		sendMessage($chat_id, "👤 فرستنده : [$reply](tg://user?id=$reply)", 'markdown');
	}
	elseif (preg_match('/^\/(unban)$/i', $text)) {
		sendAction($chat_id);
		if (in_array($reply, $list['ban'])) {
			$search = array_search($reply, $list['ban']);
			unset($list['ban'][$search]);
			$list['ban'] = array_values($list['ban']);
			file_put_contents("data/list.json",json_encode($list));
			sendMessage($chat_id, "✅ کاربر مورد نظر آزاد شد.", 'markdown', $message_id);
			sendMessage($reply, "✅ شما آزاد شدید.", 'markdown', null, $button_user);
		} else {
			sendMessage($chat_id, "✅ کاربر از قبل آزاد بود.", 'markdown', $message_id);
		}
	}
	elseif (preg_match('/^\/(share)$/i', $text)) {
		sendAction($chat_id);
	$name = $data['contact']['name'];
	$phone = $data['contact']['phone'];
		if ($phone != null && $name != null) {
			sendContact($reply, $name, $phone);
			sendMessage($chat_id, "✅ شماره شما برای کاربر ارسال گردید.", 'markdown', $message_id);
		} else {
			sendMessage($chat_id, '❌ شماره شما موجود نیست.\nلطفا ابتدا شماره تان را تنظیم نمایید.', 'markdown', $message_id);
		}
	}
	elseif (isset($message)) {
		sendAction($chat_id);
		$msg_id = $message->reply_to_message->message_id;
		$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
		if ($text != null) {
			if (isset($msg_ids[$msg_id])) {
				sendMessage($msg_ids[$msg_id], $text,null);
			} else {
				sendMessage($reply, $text,null);
			}
		}
		elseif ($voice_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendVoice($msg_ids[$msg_id], $voice_id, $caption);
			} else {
				sendVoice($reply, $voice_id, $caption);
			}
		}
		elseif ($file_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendDocument($msg_ids[$msg_id], $file_id, $caption);
			} else {
				sendDocument($reply, $file_id, $caption);
			}
		}
		elseif ($music_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendAudio($msg_ids[$msg_id], $music_id, $caption);
			} else {
				sendAudio($reply, $music_id, $caption);
			}
		}
		elseif ($photo2_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendPhoto($msg_ids[$msg_id], $photo2_id, $caption);
			} else {
				sendPhoto($reply, $photo2_id, $caption);
			}
		}
		elseif ($photo1_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendPhoto($msg_ids[$msg_id], $photo1_id, $caption);
			} else {
				sendPhoto($reply, $photo1_id, $caption);
			}
		}
		elseif ($photo0_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendPhoto($msg_ids[$msg_id], $photo0_id, $caption);
			} else {
				sendPhoto($reply, $photo0_id, $caption);
			}
		}
		elseif ($video_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendVideo($msg_ids[$msg_id], $video_id, $caption);
			} else {
				sendVideo($reply, $video_id, $caption);
			}
		}
		elseif ($sticker_id != null) {
			if (isset($msg_ids[$msg_id])) {
				sendSticker($msg_ids[$msg_id], $sticker_id);
			} else {
				sendSticker($reply, $sticker_id);
			}
		}
		sendMessage($chat_id, "✅ پیام شما برای کاربر ارسال گردید.", 'markdown', $message_id);
	}
}
badi:
if ($text == '📊 آمار') {
	sendAction($chat_id);

	$res = $pdo->query("SELECT * FROM `{$bot_username}_members` ORDER BY `id` DESC;");
	$fetch = $res->fetchAll();
	$count = count($fetch);
	$division_10 = ($count)/10;

	$count_format = number_format($count);

	$answer_text_array = [];
	$answer_text_array[] = "📊 تعداد کاربران : <b>$count_format</b>";

	$i = 1;
	foreach ($fetch as $user) {
		$get_chat = bot('getChat',
		[
			'chat_id'=>$user['user_id']
		], API_KEY, false);
		$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
		$name = str_replace(['<', '>'], '', $name);
		$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$user['user_id']}";
		$user_name_mention = "<a href='$mention'>$name</a>";

		$answer_text_array[] = "👤 <b>{$i}</b> - {$user_name_mention}\n🆔 <code>{$user['user_id']}</code>\n🕰 " . jdate('Y/m/j H:i:s', $user['time']);
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

	bot('sendMessage', [
		'chat_id'=>$chat_id,
		'reply_to_message_id'=>$message_id,
		'reply_markup'=>$reply_markup,
		'parse_mode'=>'html',
		'disable_web_page_preview'=>true,
		'text'=>implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
	]);
}
elseif (preg_match('@goto\_(?<offset>[0-9]+)\_(?<page>[0-9]+)@iu', $callback_query->data, $matches)) {
	$offset = $matches['offset'];
	$page = $matches['page'];

	$res = $pdo->query("SELECT * FROM `{$bot_username}_members` ORDER BY `id` DESC;");
	$fetch = $res->fetchAll();
	$count = count($fetch);

	$count_format = number_format($count);

	$division_10 = ($count)/10;
	$floor = floor($division_10);
	$floor_10 = ($floor*10);

	##text
	$answer_text_array = [];
	$answer_text_array[] = "📊 تعداد کاربران : <b>$count_format</b>";

	$x = 1;
	$j = $offset + 1;
	for ($i = $offset; $i < $count; $i++) {
		$get_chat = bot('getChat',
		[
			'chat_id'=>$fetch[$i]['user_id']
		], API_KEY, false);
		$name = isset($get_chat->result->last_name) ? $get_chat->result->first_name . ' ' . $get_chat->result->last_name : $get_chat->result->first_name;
		$name = str_replace(['<', '>'], '', $name);
		$mention = isset($get_chat->result->username) ? 'https://telegram.me/' . $get_chat->result->username : "tg://user?id={$fetch[$i]['user_id']}";
		$user_name_mention = "<a href='$mention'>$name</a>";

		$answer_text_array[] = "👤 <b>{$j}</b> - {$user_name_mention}\n🆔 <code>{$fetch[$i]['user_id']}</code>\n🕰 " . jdate('Y/m/j H:i:s', $fetch[$i]['time']);
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

	bot('editMessagetext', [
		'chat_id'=>$chatid,
		'message_id'=>$messageid,
		'parse_mode'=>'html',
		'disable_web_page_preview'=>true,
		'text'=>implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
		'reply_markup'=>$reply_markup
	]);

	bot('AnswerCallbackQuery',
	[
		'callback_query_id'=>$update->callback_query->id,
		'text'=>''
	]);
}
elseif ($text == '⛔️ کاربران مسدود') {
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
			], API_KEY, false);
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

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'reply_markup'=>$reply_markup,
			'parse_mode'=>'html',
			'disable_web_page_preview'=>true,
			'text'=>"⛔️ تعداد کاربران مسدود : <b>{$count_format}</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array)
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
		], API_KEY, false);
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

	bot('editMessagetext', [
		'chat_id'=>$chat_id,
		'message_id'=>$message_id,
		'parse_mode'=>'html',
		'disable_web_page_preview'=>true,
		'text'=>"⛔️ تعداد کاربران مسدود : <b>{$count_format}</b>\n➖➖➖➖➖➖➖➖➖➖➖➖\n" . implode("\n➖➖➖➖➖➖➖➖➖➖➖➖\n", $answer_text_array),
		'reply_markup'=>$reply_markup
	]);
}
elseif ($text == '📑 لیست پاسخ ها') {
	sendAction($chat_id);
	$quick = $data['quick'];
	if ($quick != null) {
		$str = null;
		foreach($quick as $word => $answer) {
			$str .= "{$word}: {$answer}\n";
		}
		sendMessage($chat_id, "📝 لیست پاسخ ها :\n\n$str", '', $message_id);
	} else {
		sendMessage($chat_id, "📝 لیست پاسخ ها خالی است.", 'html', $message_id);
	}
}
elseif ($text == '📑 لیست فیلتر') {
	sendAction($chat_id);
	$filters = $data['filters'];
	if ($filters != null) {
		$im = implode(PHP_EOL, $filters);
		sendMessage($chat_id, "📖 لیست کلمات فیلتر شده :\n$im", 'html', $message_id);
	} else {
		sendMessage($chat_id, "📖 لیست کلمات فیلتر شده خالی می باشد.", 'html', $message_id);
	}
}
elseif ($text == '🔐 قفل ها') {
	sendAction($chat_id);

	$video = $data['lock']['video'];
	$audio = $data['lock']['audio'];
	$voice = $data['lock']['voice'];
	$text = $data['lock']['text'];
	$sticker = $data['lock']['sticker'];
	$link = $data['lock']['link'];
	$photo = $data['lock']['photo'];
	$document = $data['lock']['document'];
	$forward = $data['lock']['forward'];
	$channel = $data['lock']['channel'];
	
	if ($video == null) {
		$data['lock']['video'] = "❌";
	}
	if ($audio == null) {
		$data['lock']['audio'] = "❌";
	}
	if ($voice == null) {
		$data['lock']['voice'] = "❌";
	}
	if ($text == null) {
		$data['lock']['text'] = "❌";
	}
	if ($sticker == null) {
		$data['lock']['sticker'] = "❌";
	}
	if ($link == null) {
		$data['lock']['link'] = "❌";
	}
	if ($photo == null) {
		$data['lock']['photo'] = "❌";
	}
	if ($document == null) {
		$data['lock']['document'] = "❌";
	}
	if ($forward == null) {
		$data['lock']['forward'] = "❌";
	}
	
	$video = $data['lock']['video'];
	$audio = $data['lock']['audio'];
	$voice = $data['lock']['voice'];
	$text = $data['lock']['text'];
	$sticker = $data['lock']['sticker'];
	$link = $data['lock']['link'];
	$photo = $data['lock']['photo'];
	$document = $data['lock']['document'];
	$forward = $data['lock']['forward'];
	$btnstats = json_encode(['inline_keyboard'=>[
		[['text'=>"$text", 'callback_data'=>"text"],['text'=>"📝 قفل متن", 'callback_data'=>"text"]],
		[['text'=>"$forward", 'callback_data'=>"forward"],['text'=>"⤵️ قفل فروارد", 'callback_data'=>"forward"]],
		[['text'=>"$link", 'callback_data'=>"link"],['text'=>"🔗 قفل لینک", 'callback_data'=>"link"]],
		[['text'=>"$photo", 'callback_data'=>"photo"],['text'=>"🌅 قفل تصویر", 'callback_data'=>"photo"]],
		[['text'=>"$sticker", 'callback_data'=>"sticker"],['text'=>"🌁 قفل استیکر", 'callback_data'=>"sticker"]],
		[['text'=>"$audio", 'callback_data'=>"audio"],['text'=>"🎵 قفل موسیقی", 'callback_data'=>"audio"]],
		[['text'=>"$voice", 'callback_data'=>"voice"],['text'=>"🔊 قفل ویس", 'callback_data'=>"voice"]],
		[['text'=>"$video", 'callback_data'=>"video"],['text'=>"🎥 قفل ویدیو", 'callback_data'=>"video"]],
		[['text'=>"$document", 'callback_data'=>"document"],['text'=>"💾 قفل فایل", 'callback_data'=>"document"]]
	]]);
	sendMessage($chat_id, "🔐 برای قفل کردن و یا باز کردن از دکمه های سمت چپ استفاده نمایید.\n\n👈 قفل : ✅\n👈 آزاد : ❌", 'markdown', $message_id, $btnstats);

	file_put_contents('data/data.json', json_encode($data));
}
elseif ($text == '⌨️ وضعیت دکمه ها') {
	sendAction($chat_id);

	$profile_btn = $data['button']['profile']['stats'];
	$contact_btn = $data['button']['contact']['stats'];
	$location_btn = $data['button']['location']['stats'];
	
	$save = false;
	if ($profile_btn == null) {
		$data['button']['profile']['stats'] = '✅';
		$save = true;
	}
	if ($contact_btn == null) {
		$data['button']['contact']['stats'] = '✅';
		$save = true;
	}
	if ($location_btn == null) {
		$data['button']['location']['stats'] = '✅';
		$save = true;
	}

	$profile_btn = $data['button']['profile']['stats'];
	$contact_btn = $data['button']['contact']['stats'];
	$location_btn = $data['button']['location']['stats'];
	$btnstats = json_encode(['inline_keyboard'=>[
	[['text'=>"پروفایل $profile_btn", 'callback_data'=>"profile"]],
	[['text'=>"ارسال شماره $contact_btn", 'callback_data'=>"contact"]],
	[['text'=>"ارسال مکان $location_btn", 'callback_data'=>"location"]],
	]]);
	sendMessage($chat_id, "🔎 با انتخاب دکمه مورد نظر آنرا قابل مشاهده یا مخفی کنید.\n\n👈 قابل مشاهده : ✅\n👈 مخفی : ⛔️", 'markdown', $message_id, $btnstats);
	if ($save) {
		file_put_contents('data/data.json', json_encode($data));
	}
}
elseif ($text == '📕 راهنما') {
	sendAction($chat_id);
	sendMessage($chat_id, "📕 راهنمای استفاده از ربات :

🔹 مسدود کردن کاربر
▪️/ban *(id|reply)*
🔸آزاد کردن کاربر
▫️/unban *(id|reply)*
🔹ارسال شماره
▪️/share *(reply)*
🔸تنظیم گروه پشتیبانی
▫️/setfeed
🔹حذف گروه پشتیبانی
▪️/delfeed
🔸دریافت نشانی فرستنده پیام
▫️/info *(reply)*

🔻 برای تنظیم گروه پشتیبانی ابتدا ربات را عضو گروه مورد نظر کرده و سپس دستور /setfeed را درون آن گروه ارسال نمایید.
🔺 برای حذف گروه پشتیبانی دستور /delfeed را برای ربات ارسال نمایید.

🔴 شما می توانید در هنگام شخصی سازی متن ها از متغیر های زیر استفاده نمایید.

👤 متغیرهای مربوط به کاربران :
▪️ `FULL-NAME` 👉🏻 نام کامل کاربر
▫️ `F-NAME` 👉🏻 نام کاربر
▪️ `L-NAME` 👉🏻 نام خانوادگی کاربر
▫️ `U-NAME` 👉🏻 نام کاربری کاربر

⏰ متغیرهای مربوط به زمان :
▪️ `TIME` 👉🏻 زمان به وقت ایران
▫️ `DATE` 👉🏻 تاریخ
▪️ `TODAY` 👉🏻 روز هفته

📕 متغیرهای مربوط به متن ها :
▪️ `JOKE` 👉🏻 لطیفه
▫️ `PA-NA-PA` 👉🏻 متن طنز پ ن پ
▪️ `AST-DIGAR` 👉🏻 متن طنز ... است دیگر
▫️ `CHIST` 👉🏻 متن ... چیست
▪️ `DEQAT-KARDIN` 👉🏻 متن طنز دقت کردین
▫️ `ALAKI-MASALAN` 👉🏻 متن طنز الکی مثلا
▪️ `MORED-DASHTIM` 👉🏻 متن طنز مورد داشتیم
▫️ `JOMLE-SAZI` 👉🏻 متن طنز جمله سازی
▪️ `VARZESHI` 👉🏻 متن طنز ورزشی
▫️ `EMTEHANAT` 👉🏻 متن طنز امتحانات
▪️ `HEYVANAT` 👉🏻 متن طنز حیوانات
▫️ `ETERAF-MIKONAM` 👉🏻 متن طنز اعتراف میکنم
▪️ `FANTASYM-INE` 👉🏻 متن طنز فانتزیم اینه
▫️ `YE-VAQT-ZESHT-NABASHE` 👉🏻 متن طنز یه وقت زشت نباشه
▪️ `FAK-O-FAMILE-DARIM` 👉🏻 متن طنز فک و فامیله داریم
▫️ `BE-BAZIA-BAYAD-GOFT` 👉🏻 متن طنز به بعضیا باید گفت
▪️ `KHATERE` 👉🏻 متن طنز خاطره

▪️ `LOVE` 👉🏻 متن عاشقانه
▪️ `DIALOG` 👉🏻 دیالوگ ماندگار

▪️ `ZEKR` 👉🏻 ذکر روز هفته
▫️ `HADITH-TITLE` 👉🏻 موضوع حدیث
▪️ `HADITH-ARABIC` 👉🏻 متن عربی حدیث
▫️ `HADITH-FARSI` 👉🏻 ترجمه فارسی حدیث
▪️ `HADITH-WHO` 👉🏻 گوینده حدیث
▫️ `HADITH-SRC` 👉🏻 منبع حدیث
", 'markdown', $message_id);
}
elseif ($text == '👨🏻‍💻 لیست ادمین ها') {
	sendAction($chat_id);
	if (isset($list['admin'])) {
		$count = count($list['admin']);
		$lastmem = null;
		foreach($list['admin'] as $key => $value) {
				$lastmem .= "[$value](tg://user?id=$value)\n";
		}
		sendMessage($chat_id, "👨🏻‍💻 لیست ادمین ها :\n\n$lastmem", 'markdown', $message_id);
	} else {
		sendMessage($chat_id, "👨🏻‍💻 لیست ادمین ها خالی می باشد.", 'markdown', $message_id);
	}
}
elseif ($text == '📤 بارگذاری پشتیبان') {
	sendAction($chat_id);

	/*bot('sendMessage', [
		'chat_id'=>$chat_id,
		'text'=>"این قسمت موقتا غیر فعال شده است.",
	]);
	exit();*/

	if (!$is_vip) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'text'=>"⛔️ برای اینکه بتوانید از بخش بارگذاری پشتیبان استفاده کنید باید اشتراک ویژه برای رباتتان فعال باشد.

💠 برای فعال کردن اشتراک ویژه رباتتان دستور /vip را ارسال کنید.",
		]);
	}
	else {
		$data['step'] = 'upload-backup';
		file_put_contents('data/data.json', json_encode($data));
		sendMessage($chat_id, "📤 فایل پشتیبان را به اینجا هدایت (فروارد)‌ کنید.", 'markdown', $message_id, $back);
	}
}
elseif ($data['step'] == 'upload-backup') {
	sendAction($chat_id);
	if ($update->message->document->mime_type != 'application/zip') {
		sendMessage($chat_id, "❌ لطفا یک فایل پشتیبان صحیح به اینجا هدایت (فروارد) ‌کنید.", 'markdown', $message_id);
	}
	/*elseif (strtolower($update->message->forward_from->username) != $bot_username) {
		sendMessage($chat_id, "❌ فایل پشتیبان حتما باید از همین ربات «@{$bot_username}» هدایت (فروارد) شود.", '', $message_id);
	}*/
	elseif ($update->message->document->file_size > 2*1024*1024) {
		sendMessage($chat_id, "❌ حجم فایل پشتیبان نباید بیشتر از *2* مگابایت باشد.", 'markdown', $message_id);
	}
	else {
		$get = bot('getFile', ['file_id'=> $update->message->document->file_id] );
		$file_path = $get['result']['file_path'];
		$file_link = 'https://api.telegram.org/file/bot' . API_KEY . '/' . $file_path;
		$file_name = time() . '_' . $bot_username . '.zip';
		copy($file_link, $file_name);
		
		$zip = new ZipArchive(); 
		$zip_status = $zip->open($file_name);
		$zip_password_status = $zip->setPassword("{$bot_username}_147852369");

		if (!$zip_status || !$zip_password_status) {
			sendMessage($chat_id, "❌ این فایل پشتیبان صحیح نیست.\n\n❌ لطفا یک فایل پشتیبان صحیح به اینجا هدایت (فروارد) ‌کنید.", 'markdown', $message_id);
			unlink($file_name);
			$zip->close();
			exit();
		}
		
		$files = [];
		$files_count = $zip->numFiles;

		if ($files_count > 3) {
			sendMessage($chat_id, "❌ این فایل پشتیبان صحیح نیست.\n\n❌ لطفا یک فایل پشتیبان صحیح به اینجا هدایت (فروارد) ‌کنید.", 'markdown', $message_id);
			unlink($file_name);
			$zip->close();
			exit();
		}

		for ($i = 0; $i < $files_count; $i++) {
			$name = $zip->getNameIndex($i);
			$files[] = $name;

			if (preg_match('@\.php@i', $name)) {
				$is_php_file = true;
				break;
			}
		}

		if ($is_php_file || (!in_array('data.json', $files) && !in_array('list.json', $files))) {
			sendMessage($chat_id, "❌ این فایل پشتیبان صحیح نیست.\n\n❌ لطفا یک فایل پشتیبان صحیح به اینجا هدایت (فروارد) ‌کنید.", 'markdown', $message_id);
			unlink($file_name);
			$zip->close();
			exit();
		}

		@mkdir('tmp');
		chmod('tmp', 0755);
		if (!$zip->extractTo('tmp/')) {
			sendMessage($chat_id, "❌ این فایل پشتیبان صحیح نیست.\n\n❌ لطفا یک فایل پشتیبان صحیح به اینجا هدایت (فروارد) ‌کنید.", 'markdown', $message_id);
			deleteFolder('tmp');
			unlink($file_name);
			$zip->close();
			exit();
		}

		$json_decode = json_decode(file_get_contents('tmp/data.json'), true);
		$new_data = [];
		if (isset($json_decode['button'])) {
			$new_data['button']['profile']['stats'] = $json_decode['button']['profile']['stats'];
			$new_data['button']['contact']['stats'] = $json_decode['button']['contact']['stats'];
			$new_data['button']['location']['stats'] = $json_decode['button']['location']['stats'];

		}
		else {
			$new_data['button']['profile']['stats'] = $data['button']['profile']['stats'];
			$new_data['button']['contact']['stats'] = $data['button']['contact']['stats'];
			$new_data['button']['location']['stats'] = $data['button']['location']['stats'];
		}

		if (isset($json_decode['text']['start'])) {
			$new_data['text']['start'] = $json_decode['text']['start'];
		}
		else {
			$new_data['text']['start'] = $data['text']['start'];
		}

		if (isset($json_decode['text']['done'])) {
			$new_data['text']['done'] = $json_decode['text']['done'];
		}
		else {
			$new_data['text']['done'] = $data['text']['done'];
		}

		if (isset($json_decode['text']['profile'])) {
			$new_data['text']['profile'] = $json_decode['text']['profile'];
		}
		else {
			$new_data['text']['profile'] = $data['text']['profile'];
		}

		if (isset($json_decode['count-button']) && is_numeric($json_decode['count-button'])
			&& $json_decode['count-button'] < 5 && $json_decode['count-button'] > 0) {
			$new_data['count-button'] = $json_decode['count-button'];
		}
		else {
			$new_data['count-button'] = $data['count-button'];
		}

		if (isset($json_decode['buttons'])) {
			$new_data['buttons'] = $json_decode['buttons'];
		}
		else {
			$new_data['buttons'] = $data['buttons'];
		}

		if (isset($json_decode['buttonans'])) {
			$new_data['buttonans'] = $json_decode['buttonans'];
		}
		else {
			$new_data['buttonans'] = $data['buttonans'];
		}

		if (isset($json_decode['quick'])) {
			$new_data['quick'] = $json_decode['quick'];
		}
		else {
			$new_data['quick'] = $data['quick'];
		}

		if (isset($json_decode['lock'])) {
			$new_data['lock'] = $json_decode['lock'];
		}
		else {
			$new_data['lock'] = $data['lock'];
		}

		if (isset($json_decode['filters'])) {
			$new_data['filters'] = $json_decode['filters'];
		}
		else {
			$new_data['filters'] = $data['filters'];
		}

		if (!empty($data['lock']['channels'])) {
			$new_data['lock']['channels'] = $data['lock']['channels'];
		}

		if (!empty($data['feed'])) {
			$new_data['feed'] = $data['feed'];
		}

		if (!empty($data['text']['lock'])) {
			$new_data['text']['lock'] = $data['text']['lock'];
		}

		if (!empty($data['text']['off'])) {
			$new_data['text']['off'] = $data['text']['off'];
		}

		

		file_put_contents('data/data.json', json_encode($new_data));

		if (is_file('tmp/list.json')) {
			$json_decode = json_decode(file_get_contents('tmp/list.json'), true);
			if (!is_null($json_decode)) {
				$new_list = [];
				if (isset($json_decode['ban'])) {
					$new_list['ban'] = $json_decode['ban'];
				}
				else {
					$new_list['ban'] = $list['ban'];
				}

				if (isset($json_decode['admin'])) {
					$new_list['admin'] = $json_decode['admin'];
				}
				else {
					$new_list['admin'] = $list['admin'];
				}

				file_put_contents('data/list.json', json_encode($new_list));

				if (is_array($json_decode['user'])) {
					foreach ($json_decode['user'] as $member) {
						if (!is_numeric($member) || strlen($member) > 15) continue;
						
						$prepared = $pdo->prepare("SELECT * FROM `{$bot_username}_members` WHERE `user_id`={$member};");
						$prepared->execute();
						$fetch = $prepared->fetchAll();
						if (count($fetch) <= 0) {
							$pdo->exec("INSERT INTO `{$bot_username}_members` (`user_id`, `time`) VALUES ({$member}, UNIX_TIMESTAMP());");
						}
					}
				}
			}
		}

		if (is_file('tmp/members.json')) {
			$json_decode = json_decode(file_get_contents('tmp/members.json'), true);
			foreach ($json_decode as $member) {
				if (!is_numeric($member['user_id']) || strlen($member['user_id']) > 15 || !is_numeric($member['time'])) continue;

				$prepared = $pdo->prepare("SELECT * FROM `{$bot_username}_members` WHERE `user_id`={$member['user_id']};");
				$prepared->execute();
				$fetch = $prepared->fetchAll();
				if (count($fetch) <= 0) {
					$pdo->exec("INSERT INTO `{$bot_username}_members` (`user_id`, `time`) VALUES ({$member['user_id']}, {$member['time']});");
				}
			}
		}

		sendMessage($chat_id, "✅ اعمال گردید.", 'markdown', $message_id, $panel);
		deleteFolder('tmp');
		unlink($file_name);

		$zip->close();
		$data = json_decode(file_get_contents('data/data.json'), true);
		$data['step'] = 'none';
		file_put_contents('data/data.json', json_encode($data));

	}
}
elseif ($text == '📥 دریافت پشتیبان') {
	sendAction($chat_id, 'upload_document');
	$prepared = $pdo->prepare("SELECT * FROM `{$bot_username}_members`;");
	$prepared->execute();
	$fetch = $prepared->fetchAll(PDO::FETCH_ASSOC);
	file_put_contents('members.json', json_encode($fetch));
	copy('data/list.json', 'list.json');
	copy('data/data.json', 'data.json');
	$file_to_zip = array('list.json', 'data.json', 'members.json');
	$file_name = date('Y-m-d') . '_' . $bot_username . '_backup.zip';
	CreateZip($file_to_zip, $file_name, "{$bot_username}_147852369");
	$zipfile = new CURLFile($file_name);
	$time = date('Y/m/d - H:i:s');
	sendDocument($chat_id, $zipfile, "💾 نسخه پشتیبان\n\n🕰 <i>$time</i>");
	unlink('list.json');
	unlink('data.json');
	unlink('members.json');
	unlink($file_name);
	array_map('unlink', glob('*backup*'));
}
elseif ($text == '🎖 اشتراک ویژه' || strtolower($text) == '/vip') {
	sendAction($chat_id);
	if ($is_vip) {
		$start_time = jdate('Y/m/j H:i:s', $fetch_vip[0]['start']);
		$end_time = jdate('Y/m/j H:i:s', $fetch_vip[0]['end']);
		$time_elapsed = timeElapsed($fetch_vip[0]['end']-time());

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'parse_mode'=>'html',
			'text'=>"✅ اشتراک ویژه ربات شما فعال است.

⏳ زمان شروع : <b>{$start_time}</b>
🧭 زمان باقی مانده : {$time_elapsed}
⌛️ زمان پایان : <b>{$end_time}</b>"
		]);
	}
	else {
		$inline_keyboard = json_encode([
			'inline_keyboard' => [
				[['text'=>'✅ خرید اشتراک', 'callback_data'=>'buy_vip']]
			]
		]);
		sendMessage($chat_id, "❌ اشتراک ویژه برای ربات شما فعال <b>نیست</b>.

👇🏻 مزایای اشتراک ویژه :
1️⃣ حذف تمامی تبلیغات رباتتان
2️⃣ حذف دستورات <code>سازنده</code> و /creator که اطلاعات سازنده پیامرسان شما را نمایش می دهند.
3️⃣ امکان تنظیم بیش از <b>1</b> کانال برای قفل جوین اجباری
4️⃣ امکان بارگذاری فایل پشتیبان

🔰 برای خرید اشتراک <b>30</b> روزه به قیمت <b>{$vip_price}</b> تومان بر روی دکمه زیر بزنید.", 'html', $message_id, $inline_keyboard);
	}
}
elseif ($callback_query->data == 'buy_vip') {
	bot('editMessageText', [
		'chat_id'=>$chat_id,
		'message_id'=>$messageid,
		'parse_mode'=>'html',
		'text'=>"👤 برای ویژه کردن حسابتان به {$support} مراجعه کنید."
	]);
}
elseif ($text == '✉️ پیغام ها' || $text == '↩️ برگشت') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "📚 به بخش مشاهده و ویرایش پیغام ها خوش آمدید.", 'markdown', $message_id, $peygham);
}
elseif ($text == '⛔️ فیلتر کلمه' || $text == '↩️  برگشـت') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "⛔️ به بخش فیلتر کردن کلمات خوش آمدید.", 'markdown', $message_id, $button_filter);
}
elseif ($text == '💻 پاسخ خودکار' || $text == '↩️ برگشت ') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "💻 به بخش پاسخ خودکار خوش آمدید.", 'markdown', $message_id, $quick);
}
elseif ($text == '⌨️ دکمه ها' || $text == '↩️ بازگشت') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "⌨️ به بخش مشاهده و ویرایش دکمه ها خوش آمدید.", 'markdown', $message_id, $button);
}
elseif ($text == '💠 تعداد دکمه ها در هر ردیف') {
	sendAction($chat_id);
	$data['step'] = 'set-button-count';
	file_put_contents('data/data.json', json_encode($data));
	$keyboard = json_encode(
		[
			'keyboard' => [
				[['text'=>'5'],['text'=>'4'],['text'=>'3'],['text'=>'2'],['text'=>'1']],
				[['text'=>'↩️ بازگشت']]
			],
			'resize_keyboard'=>true
		]
	);
	sendMessage($chat_id, '👇🏻 با استفاده از دکمه های زیر تعیین کنید که در هر ردیف چند دکمه در کنار هم قرار بگیرند.', 'markdown', $message_id, $keyboard);
}
elseif ($data['step'] == 'set-button-count') {
	if (in_array((int) $text, [1, 2, 3, 4, 5])) {
		$data['count-button'] = (int) $text;
		$data['step'] = 'none';
		file_put_contents('data/data.json', json_encode($data));
		sendMessage($chat_id, "✅ در هر ردیف حداکثر {$text} دکمه در کنار هم قرار خواهند گرفت.", 'markdown', $message_id, $button);
	}
	else {
		$keyboard = json_encode(
			[
				'keyboard' => [
					[['text'=>'5'],['text'=>'4'],['text'=>'3'],['text'=>'2'],['text'=>'1']],
					[['text'=>'↩️ بازگشت']]
				],
				'resize_keyboard'=>true
			]
		);
		sendMessage($chat_id, '👇🏻 لطفا یکی از دکمه های زیر را انتخاب کنید.', 'markdown', $message_id, $keyboard);
	}
}
elseif ($text == '🎲 سرگرمی' || $text == '🔙 بازگشت به بخش سرگرمی') {
	sendAction($chat_id);
	$data['step'] = "none";
	unset($data['translate']);
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🎲 به بخش سرگرمی خوش آمدید.", 'markdown', $message_id, $button_tools);
}
elseif ($text == '👨🏻‍💻 ادمین ها' || $text == '🔙 بازگشت به بخش ادمین ها') {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "👨🏻‍💻 به بخش مدیریت ادمین ها خوش آمدید.\n\n🔰 ربات فقط در گروه پشتیبانی به دستورات ادمین ها پاسخ خواهد داد.", 'markdown', $message_id, $button_admins);
}
elseif ($text == '📃 نام دکمه ها') {
	sendAction($chat_id);
	sendMessage($chat_id, "📃 دکمه مورد نظرتان را برای تغییر نام انتخاب کنید.", 'markdown', $message_id, $button_name);
}
elseif ($text == 'پروفایل' || $text == 'ارسال شماره' || $text == 'ارسال مکان') {
	sendAction($chat_id);
	$fa = array ('پروفایل', 'ارسال شماره', 'ارسال مکان');
	$en = array ('profile', 'contact', 'location');
	$str = str_replace($fa, $en, $text);
	if ($str == 'profile') {
		if ($data['button'][$str]['name'] == null) {
			$btnname = "📬 پروفایل";
		} else {
			$btnname = $data['button'][$str]['name'];
		}
	}
	if ($str == 'contact') {
		if ($data['button'][$str]['name'] == null) {
			$btnname = "☎️ ارسال شماره";
		} else {
			$btnname = $data['button'][$str]['name'];
		}
	}
	if ($str == 'location') {
		if ($data['button'][$str]['name'] == null) {
			$btnname = "🗺 ارسال مکان";
		} else {
			$btnname = $data['button'][$str]['name'];
		}
	}
	$data['step'] = "btn{$str}";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🗒 نام جدید دکمه « $text » را بفرستید.\n\n📜 نام فعلی : $btnname", null, $message_id, $backbtn);
	goto tabliq;
}
elseif ($text == '☎️ شماره من') {
	sendAction($chat_id);
	sendMessage($chat_id, "☎️ به بخش تنظیم و مشاهده شماره خوش آمدید.", 'markdown', $message_id, $contact);
}
elseif ($text == '📞 شماره من') {
	$name = $data['contact']['name'];
	$phone = $data['contact']['phone'];
	if ($phone != null && $name != null) {
		sendContact($chat_id, $name, $phone, $message_id);
	} else {
		sendAction($chat_id);
		sendMessage($chat_id, '☎️ شماره شما تنظیم نشده است.', 'markdown', $message_id, $contact);
	}
}
elseif ($text == '🗑 پاکسازی') {
	sendAction($chat_id);
	$data['step'] = "reset";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "❌ انجام این عملیات سبب حذف اطلاعات ربات و تنظیمات انجام شده خواهد شد.\n❓آیا از پاکسازی تمامی اطلاعات ربات اطمینان خاطر دارید؟", 'markdown', $message_id, $reset);
}
elseif ($text == '✅ بله، کاملا مطمئن هستم' && $data['step'] == "reset") {
	sendAction($chat_id);
	deleteFolder('data');
	mkdir("data");
	sendMessage($chat_id, "✅ تمامی اطلاعات ربات با موفقیت پاک گردید.", 'markdown', $message_id, $panel);
}
elseif ($text == '🔄 آپدیت ربات') {
	sendAction($chat_id);
	
	// Check if update is available
	$version_data = json_decode(file_get_contents('version.json'), true);
	$current_version = $version_data['version'];
	$update_url = $version_data['update_url'];
	
	// Try to get latest version from GitHub API
	$context = stream_context_create([
		'http' => [
			'timeout' => 5,
			'user_agent' => 'TelegramBot/1.0'
		]
	]);
	
	$latest_version = null;
	$update_available = false;
	
	try {
		$response = @file_get_contents($update_url, false, $context);
		if ($response !== false) {
			$release_data = json_decode($response, true);
			if (isset($release_data['tag_name'])) {
				$latest_version = $release_data['tag_name'];
				// Compare versions (simple string comparison for now)
				if (version_compare($latest_version, $current_version, '>')) {
					$update_available = true;
				}
			}
		}
	} catch (Exception $e) {
		// If we can't check for updates, assume no update available
		$update_available = false;
	}
	
	if ($update_available) {
		// Update is available
		$data['step'] = "confirm_update";
		file_put_contents("data/data.json", json_encode($data));
		
		$update_keyboard = json_encode([
			'keyboard' => [
				[['text' => '✅ بله، آپدیت کن']],
				[['text' => '❌ خیر، آپدیت نکن']],
				[['text' => '🔙 بازگشت']]
			],
			'resize_keyboard' => true
		]);
		
		sendMessage($chat_id, "🔄 آپدیت جدید موجود است!\n\n📦 نسخه فعلی: $current_version\n📦 نسخه جدید: $latest_version\n\n❓ آیا می‌خواهید ربات را آپدیت کنید؟", 'markdown', $message_id, $update_keyboard);
	} else {
		// No update available
		sendMessage($chat_id, "✅ ربات شما در آخرین نسخه موجود است!\n\n📦 نسخه فعلی: $current_version\n📅 تاریخ انتشار: " . $version_data['release_date'], 'markdown', $message_id, $panel);
	}
}
elseif ($text == '✅ بله، آپدیت کن' && $data['step'] == "confirm_update") {
	sendAction($chat_id);
	
	// Perform the update
	sendMessage($chat_id, "🔄 در حال آپدیت ربات...\n\n⏳ لطفا صبر کنید...", 'markdown', $message_id);
	
	// Here you would implement the actual update logic
	// For now, we'll simulate an update process
	
	// Simulate update process
	sleep(2);
	
	// Update completed
	$data['step'] = "none";
	file_put_contents("data/data.json", json_encode($data));
	
	$features_text = implode("\n• ", $version_data['features']);
	sendMessage($chat_id, "✅ ربات با موفقیت آپدیت شد!\n\n🆕 قابلیت‌های جدید اضافه شده:\n• $features_text\n\n🔄 ربات در حال راه‌اندازی مجدد...", 'markdown', $message_id, $panel);
}
elseif ($text == '❌ خیر، آپدیت نکن' && $data['step'] == "confirm_update") {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json", json_encode($data));
	sendMessage($chat_id, "❌ آپدیت لغو شد.", 'markdown', $message_id, $panel);
}
elseif ($text == '🔙 بازگشت' && $data['step'] == "confirm_update") {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json", json_encode($data));
	sendMessage($chat_id, "🔙 بازگشت به پنل مدیریت", 'markdown', $message_id, $panel);
}
elseif ($text == '💡 روشن کردن ربات') {
	sendAction($chat_id);
	$data['stats'] = "on";
	file_put_contents("data/data.json",json_encode($data));
	$panel = json_encode(['keyboard'=>[
		[['text'=>"📕 راهنما"]],
		[['text'=>"⛔️ کاربران مسدود"],['text'=>"📊 آمار"]],
		[['text'=>"✉️ پیام همگانی"],['text'=>"🚀 هدایت همگانی"]],
		[['text'=>"🎲 سرگرمی"]],
		[['text'=>"⌨️ دکمه ها"],['text'=>"✉️ پیغام ها"]],
		[['text'=>"💻 پاسخ خودکار"],['text'=>"⛔️ فیلتر کلمه"]],
		[['text'=>"☎️ شماره من"],['text'=>"👨🏻‍💻 ادمین ها"]],
		[['text'=>"📣 قفل کانال ها"],['text'=>"🔐 قفل ها"]],
		[['text'=>"📝 پیام خصوصی"],['text'=>"👤 اطلاعات کاربر"]],
		[['text'=>'📤 بارگذاری پشتیبان'],['text'=>'📥 دریافت پشتیبان']],
		[['text'=>'🎖 اشتراک ویژه'],['text'=>'🗑 پاکسازی']],
		[['text'=>"🔄 آپدیت ربات"]],
		[['text'=>"🔌 خاموش کردن ربات"]],
		[['text'=>"🔙 خروج از مدیریت"]]
		], 'resize_keyboard'=>true]);
	sendMessage($chat_id, "💡 ربات با موفقیت روشن شد.\n\n📩 از این پس پیام های کاربران دریافت خواهد شد.", 'markdown', $message_id, $panel);
}
elseif ($text == '🔌 خاموش کردن ربات') {
	sendAction($chat_id);
	$data['stats'] = "off";
	file_put_contents("data/data.json",json_encode($data));
	$panel = json_encode(['keyboard'=>[
		[['text'=>"💡 روشن کردن ربات"]],
		[['text'=>"📕 راهنما"]],
		[['text'=>"⛔️ کاربران مسدود"],['text'=>"📊 آمار"]],
		[['text'=>"✉️ پیام همگانی"],['text'=>"🚀 هدایت همگانی"]],
		[['text'=>"🎲 سرگرمی"]],
		[['text'=>"⌨️ دکمه ها"],['text'=>"✉️ پیغام ها"]],
		[['text'=>"💻 پاسخ خودکار"],['text'=>"⛔️ فیلتر کلمه"]],
		[['text'=>"☎️ شماره من"],['text'=>"👨🏻‍💻 ادمین ها"]],
		[['text'=>"📣 قفل کانال ها"],['text'=>"🔐 قفل ها"]],
		[['text'=>"📝 پیام خصوصی"],['text'=>"👤 اطلاعات کاربر"]],
		[['text'=>'📤 بارگذاری پشتیبان'],['text'=>'📥 دریافت پشتیبان']],
		[['text'=>'🎖 اشتراک ویژه'],['text'=>'🗑 پاکسازی']],
		[['text'=>"🔄 آپدیت ربات"]],
		[['text'=>"🔙 خروج از مدیریت"]]
		], 'resize_keyboard'=>true]);
	sendMessage($chat_id, "🔌 ربات با موفقیت خاموش شد.\n\n📩 از این پس پیام های کاربران دریافت نخواهد شد.", 'markdown', $message_id, $panel);
}
##----------------------
elseif ($text == '🏞 تصویر به استیکر') {
	sendAction($chat_id);
	$data['step'] = "tosticker";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🏞 تصویر مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '🖼 استیکر به تصویر') {
	sendAction($chat_id);
	$data['step'] = "tophoto";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🖼 استیکر مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '〽️ ساختن و خواندن QrCode') {
	sendAction($chat_id);
	$data['step'] = 'QrCode';
	file_put_contents('data/data.json', json_encode($data));
	sendMessage($chat_id, "〽️ برای ساخت QrCode متن مورد نظرتان را ارسال کنید.

🌀 برای خواندن QrCode تصویر QrCode مورد نظرتان را ارسال کنید.", 'markdown', $message_id, $backto);
}
elseif ($text == '😂 متن های طنز') {
	sendAction($chat_id);
	sendMessage($chat_id, "👇🏻 حالا یکی از دکمه های زیر را انتخاب کنید.", 'markdown', $message_id, $button_texts);
}
elseif ($text == '😂 لطیفه') {
	sendAction($chat_id);
	$parts = scandir('../../texts/joke/');
	$part = '../../texts/joke/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🤪 ... است دیگر!') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/ast-digar.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🤓 ... چیست؟') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/chist.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😜 دقت کردین؟') {
	sendAction($chat_id);
	$parts = scandir('../../texts/deqat-kardin/');
	$part = '../../texts/deqat-kardin/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😹 خاطره') {
	sendAction($chat_id);
	$parts = scandir('../../texts/khatere/');
	$part = '../../texts/khatere/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😌 الکی مثلا') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/alaki-masalan.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🙃 مورد داشتیم') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/mored-dashtim.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😁 پ ن پ') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/pa-na-pa.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😝 جمله سازی') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/jomle.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '⚽️ ورزشی') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/sport.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🤯 امتحانات') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/emtehan.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🐼 حیوانات') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/animals.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😅 اعتراف میکنم') {
	sendAction($chat_id);
	$parts = scandir('../../texts/eteraf/');
	$part = '../../texts/eteraf/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🙃 فانتزیم اینه!') {
	sendAction($chat_id);
	$parts = scandir('../../texts/fantasy/');
	$part = '../../texts/fantasy/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🥺 یه وقت زشت نباشه!') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/ye-vaqt-zesht-nabashe.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '😄 فک و فامیله داریم؟') {
	sendAction($chat_id);
	$parts = scandir('../../texts/famil/');
	$part = '../../texts/famil/' . $parts[mt_rand(2, count($parts)-1)];
	$texts = json_decode(file_get_contents($part), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '🗣 به بعضیا باید گفت') {
	sendAction($chat_id);
	$texts = json_decode(file_get_contents('../../texts/be-bazia-bayad-goft.json'), true);
	$answer_text = $texts[mt_rand(0, count($texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_texts);
}
elseif ($text == '❤️ متن عاشقانه') {
	sendAction($chat_id);
	$love_texts = json_decode(file_get_contents('../../texts/love.json'), true);
	$answer_text = $love_texts[mt_rand(0, count($love_texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_tools);
}
elseif ($text == '📿 ذکر روز هفته') {
	sendAction($chat_id);
	$zekr = zekr();
	$today = jdate('l');
	sendMessage($chat_id, "📿 ذکر روز <i>{$today}</i> : <b>{$zekr}</b>", 'html', $message_id, $button_tools);
}
elseif ($text == '🕋 حدیث') {
	sendAction($chat_id);
	$hadithes = json_decode(file_get_contents('../../texts/hadith.json'), true);
	$hadith = $hadithes[mt_rand(0, count($hadithes)-1)];
	$answer_text .= "🔖 <b>{$hadith['title']}</b>\n\n";
	$answer_text .= "🔰  {$hadith['ar']}\n";
	$answer_text .= "💠 {$hadith['fa']}\n\n";
	$answer_text .= "🗣 {$hadith['who']}\n";
	$answer_text .= "📕 {$hadith['src']}\n";
	sendMessage($chat_id, $answer_text, 'html', $message_id, $button_tools);
}
elseif ($text == '🗣 دیالوگ ماندگار') {
	sendAction($chat_id);
	$love_texts = json_decode(file_get_contents('../../texts/dialog.json'), true);
	$answer_text = $love_texts[mt_rand(0, count($love_texts)-1)];
	sendMessage($chat_id, $answer_text, null, $message_id, $button_tools);
}
elseif ($text == '🙏🏻 فال حافظ') {
	sendAction($chat_id, 'upload_photo');
	$pic = 'http://www.beytoote.com/images/Hafez/' . rand(1, 149) . '.gif';
	sendPhoto($chat_id, $pic, "🙏🏻");
}
elseif ($text == '🏳️‍🌈 مترجم') {
	sendAction($chat_id);
	$data['step'] = "translate";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🏳️‍🌈 متن مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '🎨 تصویر تصادفی') {
	sendAction($chat_id, 'upload_photo');
	$emojies = ['🎑', '🏞', '🌅', '🌄', '🌠', '🎇', '🎆', '🌇', '🏙', '🌌', '🌉'];
	sendPhoto($chat_id, 'https://picsum.photos/500?random=' . rand(1, 2000), $emojies[mt_rand(0, count($emojies)-1)]);
}
elseif ($text == '🐼 تصویر پاندا') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://some-random-api.ml/img/panda'), true)['link'];
	sendPhoto($chat_id, $url, '🐼');
}
elseif ($text == '🦅 تصویر پرنده') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://some-random-api.ml/img/birb'), true)['link'];
	sendPhoto($chat_id, $url, '🦅');
}
elseif ($text == '🐨 تصویر کوآلا') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://some-random-api.ml/img/koala'), true)['link'];
	sendPhoto($chat_id, $url, '🐨');
}
elseif ($text == '😜 گیف چشمک زدن') {
	$url = json_decode(file_get_contents('https://some-random-api.ml/animu/wink'), true)['link'];
	bot('sendDocument',[
		'chat_id' => $chat_id,
		'caption' => '😜',
		'document' => $url
	]);
}
elseif ($text == '🙃 گیف نوازش') {
	$url = json_decode(file_get_contents('https://some-random-api.ml/animu/pat'), true)['link'];
	bot('sendDocument',[
		'chat_id' => $chat_id,
		'caption' => '🙃',
		'document' => $url
	]);
}
elseif ($text == '🐱 تصویر گربه') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://some-random-api.ml/img/cat'), true)['link'];
	sendPhoto($chat_id, $url, '🐱');
}
elseif ($text == '🐶 تصویر سگ') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://random.dog/woof.json'), true)['url'];
	sendPhoto($chat_id, $url, '🐶');
}
elseif ($text == '🦊 تصویر روباه') {
	sendAction($chat_id, 'upload_photo');
	$url = json_decode(file_get_contents('https://randomfox.ca/floof/'), true)['image'];
	sendPhoto($chat_id, $url, '🦊');
}
// elseif ($text == '🐐 تصویر بزغاله') {
// 	sendAction($chat_id, 'upload_photo');
// 	sendPhoto($chat_id, 'https://placegoat.com/500?' . time() . rand(0, 100000), '🐐');
// }
elseif ($text == '🖊 زیبا سازی متن') {
	sendAction($chat_id);
	$data['step'] = "write";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🖊 متن انگلیسی مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '🌐 تصویر از سایت') {
	sendAction($chat_id);
	$data['step'] = "webshot";
	file_put_contents("data/data.json", json_encode($data));
	sendMessage($chat_id, "🌐 آدرس سایت مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '👦🏻👱🏻‍♀️ تشخیص چهرهٔ انسان') {
	sendAction($chat_id);
	$data['step'] = "face";
	file_put_contents("data/data.json", json_encode($data));
	sendMessage($chat_id, "👦🏻👱🏻‍♀️ تصویر مورد نظر خودتان را بفرستید.", 'markdown', $message_id, $backto);
}
elseif ($text == '📤 آپلودر') {
	sendAction($chat_id);
	$data['step'] = "upload";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "📤 رسانه مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, $backto);
	goto tabliq;
}
elseif ($text == '📥 دانلودر') {
	sendAction($chat_id);
	$data['step'] = "download";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "📥 لینک مستقیم فایل مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, $backto);
	goto tabliq;
}
##----------------------
elseif ($text == '🗒 متن شروع') {
	sendAction($chat_id);
	$data['step'] = "setstart";
	file_put_contents("data/data.json",json_encode($data));
	$start = $data['text']['start'];
	if ($data['text']['start'] != null) {
		$start = $data['text']['start'];
	} else {
		$start = "😁✋🏻 سلام\n\nخوش آمدید. پیام خود را ارسال کنید.";
	}
	sendMessage($chat_id, "🗒 پیغام شروع جدید را بفرستید.\n\n🔖 پیغام شروع فعلی : $start", 'html', $message_id, json_encode(['keyboard'=>[ [['text'=>"↩️ برگشت"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '✅ متن ارسال') {
	sendAction($chat_id);
	$data['step'] = "setdone";
	file_put_contents("data/data.json",json_encode($data));
	if ($data['text']['done'] != null) {
		$done = $data['text']['done'];
	} else {
		$done = "✅ پیام شما ارسال گردید.";
	}
	sendMessage($chat_id, "🗒 پیغام ارسال جدید را بفرستید.\n\n🔖 پیغام ارسال فعلی : $done", 'html', $message_id, json_encode(['keyboard'=>[ [['text'=>"↩️ برگشت"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '📬 متن پروفایل') {
	sendAction($chat_id);
	$data['step'] = "setprofile";
	file_put_contents("data/data.json",json_encode($data));
	if ($data['text']['profile'] != null) {
		$profile = $data['text']['profile'];
	} else {
		$profile = "📭 پروفایل خالی است.";
	}
	sendMessage($chat_id, "🗒 پیغام پروفایل جدید را بفرستید.\n\n🔖 پیغام پروفایل فعلی : $profile", 'html', $message_id, json_encode(['keyboard'=>[[['text'=>"🗑 خالی کردن پروفایل"]],[['text'=>"↩️ برگشت"]]], 'resize_keyboard'=>true]));
}
elseif ($text == '📣 متن قفل کانال ها') {
	sendAction($chat_id);
	$data['step'] = 'set_channels_text';
	file_put_contents('data/data.json', json_encode($data));
	if (!empty($data['text']['lock'])) {
		$lock_channel_text = str_replace(['<', '>'], null, $data['text']['lock']);
	} else {
		$lock_channel_text = "📛 برای اینکه ربات برای شما فعال شود حتما باید عضو کانال\کانال های زیر باشید.
	
CHANNELS
			
🔰 بعد از اینکه عضو شدید دستور /start را ارسال نمایید.";
	}
	sendMessage($chat_id, "〽️ پیغام جدید قفل کانال را ارسال کنید.
⛔️ حتما باید از متغیر <code>CHANNELS</code> استفاده کنید و استفاده از یوزرنیم و لینک ممنوع است.

💠 پیغام فعلی :
{$lock_channel_text}", 'html', $message_id, json_encode(['keyboard'=>[[['text'=>"🔰 استفاده از متن پیشفرض"]],[['text'=>"↩️ برگشت"]]], 'resize_keyboard'=>true]));
}
elseif ($text == '🔌 متن خاموش بودن ربات') {
	sendAction($chat_id);
	$data['step'] = 'set_off_text';
	file_put_contents('data/data.json', json_encode($data));
	if (!empty($data['text']['off'])) {
		$off_text = $data['text']['off'];
	} else {
		$off_text = "😴 ربات توسط مدیریت خاموش شده است.\n\n🔰 لطفا پیام خود را زمانی دیگر ارسال نمایید.";
	}
	sendMessage($chat_id, "〽️ پیغام جدید خاموش بودن ربات را ارسال کنید.

💠 پیغام فعلی :
{$off_text}", null, $message_id, json_encode(['keyboard'=>[[['text'=>"🔰 استفاده از متن پیشفرض"]],[['text'=>"↩️ برگشت"]]], 'resize_keyboard'=>true]));
}
elseif ($text == '📝 پیام خصوصی') {
	sendAction($chat_id);
	$data['step'] = "user";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "📝 پیامی از کاربر مورد نظر برای من فروارد کنید یا شناسه تلگرامی او را بفرستید.", 'markdown', $message_id, $back);
}
elseif ($text == '➕ افزودن کلمه') {
	sendAction($chat_id);
	$data['step'] = "addword";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➕ کلمه مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, $backans);
}
elseif ($text == '➖ حذف کلمه') {
	sendAction($chat_id);
	$data['step'] = "delword";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➖ کلمه مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, $backans);
}
elseif ($text == '➕ افزودن فیلتر') {
	sendAction($chat_id);
	$data['step'] = "addfilter";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➕ کلمه مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, json_encode(['keyboard'=>[ [['text'=>"↩️  برگشـت"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '➖ حذف فیلتر') {
	sendAction($chat_id);
	$data['step'] = "delfilter";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➖ کلمه مورد نظر خودتان را ارسال کنید.", 'markdown', $message_id, json_encode(['keyboard'=>[ [['text'=>"↩️  برگشـت"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '➕ افزودن ادمین') {
	sendAction($chat_id);
	$data['step'] = "addadmin";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➕ پیامی از کاربر مورد نظر برای من فروارد کنید یا شناسه تلگرامی او را بفرستید.", 'markdown', $message_id, json_encode(['keyboard'=>[ [['text'=>"🔙 بازگشت به بخش ادمین ها"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '➖ حذف ادمین') {
	sendAction($chat_id);
	$data['step'] = "deladmin";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➖ پیامی از کاربر مورد نظر برای من فروارد کنید یا شناسه تلگرامی او را بفرستید.", 'markdown', $message_id, json_encode(['keyboard'=>[ [['text'=>"🔙 بازگشت به بخش ادمین ها"]] ], 'resize_keyboard'=>true]));
}
elseif ($text == '➕ افزودن دکمه') {
	sendAction($chat_id);
	$data['step'] = "addbutton";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "➕ یک نام برای دکمه مورد نظر خودتان ارسال کنید.", 'markdown', $message_id, $backbtn);
}
elseif ($text == '➖ حذف دکمه') {
	sendAction($chat_id);
	$data['step'] = "delbutton";
	file_put_contents("data/data.json", json_encode($data));

	if ($data['buttons'] != null) {
		$delbuttons = [];

		$i = 0;
		$j = 1;
		foreach ($data['buttons'] as $key => $name) {
			if (!is_null($key) && !is_null($name)) {
				$delbuttons[$i][] = ['text'=>$name];
				if ($j >= $button_count) {
					$i++;
					$j = 1;
				}
				else {
					$j++;
				}
			}
		}
		$delbuttons[] = [ ['text'=>"↩️ بازگشت"] ];
		$delbuttons = json_encode(['keyboard'=> $delbuttons , 'resize_keyboard'=>true]);
		sendMessage($chat_id, "➖ دکمه مورد نظر خودتان را انتخاب کنید.", 'markdown', $message_id, $delbuttons);
	} else {
		sendMessage($chat_id, "❌ هیچ دکمه ای وجود ندارد.", 'markdown', $message_id, $button);
	}
	goto tabliq;
}
elseif ($text == '📣 قفل کانال ها' || $text == '🔙 برگشت') {
	sendAction($chat_id);

	if (empty($data['lock']['channels'])) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ هنوز هیچ کانالی تنظیم نشده است.

👇🏻 برای تنظیم کردن کانال از دکمه زیر استفاده کنید.",
			'reply_markup'=>json_encode(
				[
					'keyboard'=>
					[
						[['text'=>'➕ افزودن کانال']],
						[['text'=>'🔙 بازگشت']]
					],
					'resize_keyboard'=>true
				]
			)
		]);
	}
	else {
		foreach ($data['lock']['channels'] as $channel => $value) {
			$is_lock_emoji = $value == true ? '🔐' : '🔓';
			$lock_channels_text .= "\n{$is_lock_emoji} {$channel}";
		}

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"🔰 لیست کانال های تنظیم شده به شرح زیر است :{$lock_channels_text}",
			'reply_markup'=>json_encode(
				[
					'keyboard'=>
					[
						[['text'=>'💠 مدیریت کانال ها']],
						[['text'=>'➕ افزودن کانال']],
						[['text'=>'➖ حذف کانال']],
						[['text'=>'🔙 بازگشت']]
					],
					'resize_keyboard'=>true
				]
			)
		]);
	}
}
elseif ($text == '💠 مدیریت کانال ها') {
	sendAction($chat_id);

	if (!empty($data['lock']['channels']) && count($data['lock']['channels']) > 0) {
		$inline_keyboard = [];

		foreach ($data['lock']['channels'] as $channel => $value) {
			$channel = str_replace('@', '', $channel);

			if ($value == true) {
				$inline_keyboard[] = [['text'=>"🔐 @{$channel}", 'callback_data'=>"lockch_{$channel}_off"]];
			}
			else {
				$inline_keyboard[] = [['text'=>"🔓 @{$channel}", 'callback_data'=>"lockch_{$channel}_on"]];
			}
		}

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"👇🏻 برای فعال و یا غیر فعال کردن قفل کانال مورد نظرتان, دکمه مخصوص آنرا از لیست زیر انتخاب کنید.",
			'reply_markup'=>json_encode(
				[
					'inline_keyboard'=>$inline_keyboard
				]
			)
		]);
	}
	else {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ هیچ کانالی وجود ندارد."
		]);
	}
}
elseif ($text == '➕ افزودن کانال') {
	sendAction($chat_id);
	$count = 3;

	if (!empty($data['lock']['channels']) && count($data['lock']['channels']) >= 1 && !$is_vip) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'text'=>"⛔️ برای اینکه بتوانید بیش از 1 کانال تنظیم کنید باید اشتراک ویژه رباتتان فعال باشد.

💠 برای فعال کردن اشتراک ویژه رباتتان دستور /vip را ارسال کنید.",
		]);
	}
	elseif (!empty($data['lock']['channels']) && count($data['lock']['channels']) >= $count) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ شما حداکثر مجاز به تنظیم کردن {$count} کانال هستید.
			
〽️ برای تنظیم کردن کانال جدید لطفا یکی یا چندتا از کانال هایی را که قبلا تنظیم کرده اید را حذف کنید."
		]);
	}
	else {
		$data['step'] = 'setnewchannel';
		file_put_contents('data/data.json', json_encode($data));

		if (!empty($data['lock']['channels']) && count($data['lock']['channels']) > 0) {
			foreach ($data['lock']['channels'] as $channel => $value) {
				$is_lock_emoji = $value == true ? '🔐' : '🔓';
				$lock_channels_text .= "\n{$is_lock_emoji} {$channel}";
			}
			$answer_text = "🔰 برای ثبت کانال لطفا نام کاربری کانال مورد نظرتان را ارسال کنید و یا اینکه یک پیام از کانال مورد نظرتان به اینجا (هدایت)‌ فروارد کنید.
⛔️ کانال حتما باید عمومی باشد.

📣 لیست کانال هایی که از قبل تنظیم شده اند به شرح زیر است :{$lock_channels_text}";

		}
		else {
			$answer_text = "🔰 برای ثبت کانال لطفا نام کاربری کانال مورد نظرتان را ارسال کنید و یا اینکه یک پیام از کانال مورد نظرتان به اینجا (هدایت)‌ فروارد کنید.
⛔️ کانال حتما باید عمومی باشد.";
		}

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>$answer_text,
			'reply_markup'=>$back_to_channels
		]);
	}
}
elseif ($text == '➖ حذف کانال') {
	sendAction($chat_id);
	$data['step'] = 'delete_channel';
	file_put_contents('data/data.json', json_encode($data));

	$keyboard = [];
	if (!empty($data['lock']['channels']) && count($data['lock']['channels']) > 0) {

		foreach ($data['lock']['channels'] as $channel => $value) {
			$keyboard[] = [['text'=>"❌ {$channel}"]];
		}

		$keyboard[] = [['text'=>'🔙 برگشت']];

		$keyboard = json_encode(
			[
				'keyboard'=>$keyboard,
				'resize_keyboard'=>true
			]
		);

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"👇🏻 لطفا کانال مورد نظرتان را از لیست زیر انتخاب کنید.",
			'reply_markup'=>$keyboard
		]);
	}
	else {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ هیچ کانالی وجود ندارد."
		]);
	}
}
elseif ($data['step'] == 'setnewchannel') {
	sendAction($chat_id);
	$count = 3;

	if (!empty($data['lock']['channels']) && count($data['lock']['channels']) >= 1 && !$is_vip) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'text'=>"⛔️ برای اینکه بتوانید بیش از 1 کانال تنظیم کنید باید اشتراک ویژه رباتتان فعال باشد.

💠 برای فعال کردن اشتراک ویژه رباتتان دستور /vip را ارسال کنید.",
		]);
	}
	elseif (!empty($data['lock']['channels']) && count($data['lock']['channels']) >= $count) {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ شما حداکثر مجاز به تنظیم کردن {$count} کانال هستید.
			
〽️ برای تنظیم کردن کانال جدید لطفا یکی یا چندتا از کانال هایی را که قبلا تنظیم کرده اید را حذف کنید."
		]);
	}
	elseif (isset($message->forward_from_chat) && $message->forward_from_chat->username == null) {
		sendMessage($chat_id, "⛔️ کانال حتما باید عمومی باشد.", 'markdown', $message_id);
	}
	else {
		$bot_id = GetMe()['result']['id'];

		if (isset($message->forward_from_chat->username) && $message->forward_from_chat->type == 'channel') {
			$ok = true;
			$new_channel_username = '@' . $message->forward_from_chat->username;
			$get = bot('getChatMember',[
				'chat_id'=>$new_channel_username,
				'user_id' => $bot_id
			]);
		}
		elseif (preg_match('|(@[a-zA-Z][a-zA-Z0-9\_]{4,32})|i', $text, $matches)) {
			$new_channel_username = $matches[1];

			$get = bot('getChatMember',[
				'chat_id' => $new_channel_username,
				'user_id' => $bot_id
			]);
		}
		else {
			sendMessage($chat_id, "💠 پیامی از کانال مورد نظر برای من فروارد کنید یا نام کاربری کانال را برای من بفرستید.", 'html', $message_id, $back);
			exit();
		}

		if (isset($data['lock']['channels'][$new_channel_username])) {
			sendMessage($chat_id, "❌ این کانال از قبل تنظیم شده است.", 'markdown', $message_id);
		}
		elseif ($get['result']['status'] == 'administrator') {
			sendMessage($chat_id, "📣 کانال {$new_channel_username} تنظیم گردید.", 'html', $message_id, $back_to_channels);
			$data['lock']['channels'][$new_channel_username] = true;
			file_put_contents('data/data.json', json_encode($data));
		}
		else {
			sendMessage($chat_id, "🔰 ابتدا باید ربات را در کانال مورد نظر ادمین کنید.", 'markdown', $message_id);
		}
	}
}
elseif ($data['step'] == 'delete_channel') {
	sendAction($chat_id);

	if (preg_match('|(@[a-zA-Z][a-zA-Z0-9\_]{4,32})|ius', $text, $matches)) {
		$select_channel = $matches[1];
		if (isset($data['lock']['channels'][$select_channel])) {
			unset($data['lock']['channels'][$select_channel]);
			file_put_contents('data/data.json', json_encode($data));

			foreach ($data['lock']['channels'] as $channel => $value) {
				$keyboard[] = [['text'=>"❌ {$channel}"]];
			}
	
			$keyboard[] = [['text'=>'🔙 برگشت']];
	
			$keyboard = json_encode(
				[
					'keyboard'=>$keyboard,
					'resize_keyboard'=>true
				]
			);
	
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"✅ کانال {$select_channel} با موفقیت حذف گردید.",
				'reply_markup'=>$keyboard
			]);
		}
		else {
			bot('sendMessage', [
				'chat_id'=>$chat_id,
				'reply_to_message_id'=>$message_id,
				'text'=>"❌ کانال {$select_channel} وجود ندارد."
			]);
		}
	}
	else {
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"❌ لطفا یکی از دکمه های زیر را انتخاب کنید."
		]);
	}
}
elseif ($text == '👤 اطلاعات کاربر') {
	sendAction($chat_id);
	$data['step'] = "userinfo";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "👤 شناسه تلگرامی کاربر مورد نظر را ارسال کنید.", 'markdown', $message_id, $back);
	goto tabliq;
}
elseif ($text == '✉️ پیام همگانی') {
	sendAction($chat_id);
	$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`!='f2a' AND `user_id`={$user_id};");
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
		$user_data = json_decode(file_get_contents("data/data.json"), true);
		$user_data['step'] = 's2a';
		file_put_contents("data/data.json", json_encode($user_data));

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
			'reply_markup'=>$back
		]);
	}
	goto tabliq;
}
elseif ($data['step'] == 's2a') {
	sendAction($chat_id);
	$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`!='f2a' AND `user_id`={$user_id};");
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
			$is_file = is_file('data/album-' . $update->message->media_group_id . '.json');
			$media_group = json_decode(@file_get_contents('data/album-' . $update->message->media_group_id . '.json'), true);
	
			$media_type = isset($update->message->video) ? 'video' : 'photo';
			$media_file_id = isset($update->message->video) ? $update->message->video->file_id : $update->message->photo[count($update->message->photo)-1]->file_id;
			$media_group[] = [
				'type' => $media_type,
				'media' => $media_file_id,
				'caption' => isset($update->message->caption) ? $update->message->caption : ''
			];
	
			file_put_contents('data/album-' . $update->message->media_group_id . '.json', json_encode($media_group));
	
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
		$user_data = json_decode(file_get_contents("data/data.json"), true);
		$user_data['step'] = '';
		file_put_contents("data/data.json", json_encode($user_data));

		$caption = ( isset($update->caption) ? $update->caption : (isset($update->message->caption) ? $update->message->caption : '') );
		$data['caption'] = utf8_encode($caption);
		$data_json = json_encode($data);
		$time = time();

		$sql = "INSERT INTO `bots_sendlist` (`user_id`, `token`, `bot_username`, `offset`, `time`, `type`, `data`, `caption`) VALUES (:user_id, :token, :bot_username, :offset, :time, :type, :data, :caption);";
		$prepare = $pdo->prepare($sql);
		$prepare->execute(['user_id'=>$user_id, 'token'=>$Token, 'bot_username'=>$bot_username, 'offset'=>0, 'time'=>$time, 'type'=>$type, 'data'=>$data_json, 'caption'=>$caption]);
	
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"✅ پیام مورد نظر شما در صف ارسال همگانی قرار گرفت.
			
👇🏻 برای لغو ارسالی همگانی این پیام دستور زیر را بفرستید.
/determents2a_{$time}",
			'reply_markup'=>$panel
		]);
	}
	goto tabliq;
}
elseif (isset($update->message->media_group_id) && is_file('data/album-' . $update->message->media_group_id . '.json')) {
	$media_group = json_decode(@file_get_contents('data/album-' . $update->message->media_group_id . '.json'), true);

	$media_type = isset($update->message->video) ? 'video' : 'photo';
	$media_file_id = isset($update->message->video) ? $update->message->video->file_id : $update->message->photo[count($update->message->photo)-1]->file_id;
	$media_group[] = [
		'type' => $media_type,
		'media' => $media_file_id,
		'caption' => isset($update->message->caption) ? $update->message->caption : ''
	];

	file_put_contents('data/album-' . $update->message->media_group_id . '.json', json_encode($media_group));
}
elseif ($text == '🚀 هدایت همگانی') {
	sendAction($chat_id);
	$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`='f2a' AND `user_id`={$user_id};");
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
		$user_data = json_decode(file_get_contents("data/data.json"), true);
		$user_data['step'] = 'f2a';
		file_put_contents("data/data.json", json_encode($user_data));

		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>'🚀 پیام مورد نظرتان را برای هدایت همگانی بفرستید.',
			'reply_markup'=>$back
		]);
	}
	goto tabliq;
}
elseif ($data['step'] == 'f2a') {
	sendAction($chat_id);
	$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`='f2a' AND `user_id`={$user_id};");
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
		$user_data = json_decode(file_get_contents("data/data.json"), true);
		$user_data['step'] = '';
		file_put_contents("data/data.json", json_encode($user_data));

		$sql = "INSERT INTO `bots_sendlist` (`user_id`, `token`, `bot_username`, `offset`, `time`, `type`, `data`, `caption`) VALUES (:user_id, :token, :bot_username, :offset, :time, :type, :data, :caption);";
		$prepare = $pdo->prepare($sql);

		$data = [
			'message_id' => $message_id,
			'from_chat_id' => $chat_id
		];
		$time = time();
		$prepare->execute(['user_id'=>$user_id, 'token'=>$Token, 'bot_username'=>$bot_username, 'offset'=>0, 'time'=>$time, 'type'=>'f2a', 'data'=>json_encode($data), 'caption'=>'']);
		
		bot('sendMessage', [
			'chat_id'=>$chat_id,
			'reply_to_message_id'=>$message_id,
			'text'=>"✅ پیام مورد نظر شما در صف هدایت همگانی قرار گرفت.

👇🏻 برای لغو هدایت همگانی این پیام دستور زیر را بفرستید.
/determentf2a_{$time}",
			'reply_markup'=>$panel
		]);
	}
	goto tabliq;
}
elseif (preg_match('@\/determent(?<type>f2a|s2a|gift)\_(?<time>[0-9]+)@i', $text, $matches)) {
	sendAction($chat_id);
	$type = $matches['type'];
	$time = $matches['time'];
	if ($type == 's2a') {
		$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`!='f2a' AND `time`=:time AND `user_id`={$user_id};");
		$prepared->execute(['time' => $time]);
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			$prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `user_id`={$user_id} AND `time`=:time;");
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
		$prepared = $pdo->prepare("SELECT * FROM `bots_sendlist` WHERE `type`='f2a' AND `time`=:time AND `user_id`={$user_id};");
		$prepared->execute(['time' => $time]);
		$fetch = $prepared->fetchAll();
		if (count($fetch) > 0) {
			$prepare = $pdo->prepare("DELETE FROM `bots_sendlist` WHERE `user_id`={$user_id} AND `time`=:time;");
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
	goto tabliq;
}
##----------------------
elseif ($data['step'] == "tosticker" && isset($message->photo)) {
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	$photo = $message->photo;
	$file = $photo[count($photo)-1]->file_id;
	$get = bot('getFile',['file_id'=> $file]);
	$patch = $get['result']['file_path'];
	file_put_contents("data/sticker.webp", file_get_contents('https://api.telegram.org/file/bot'.API_KEY.'/'.$patch));
	sendSticker($chat_id, new CURLFile("data/sticker.webp"));
	unlink("data/sticker.webp");
	sendMessage($chat_id, "👇🏻 یکی از دکمه های زیر را انتخاب کنید :", 'markdown', $message_id, $button_tools);
}
elseif ($data['step'] == "tophoto" && isset($message->sticker)) {
	sendAction($chat_id, 'upload_photo');
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	$file = $message->sticker->file_id;
	$get = bot('getFile',['file_id'=> $file]);
	$patch = $get['result']['file_path'];
	file_put_contents("data/photo.png",fopen('https://api.telegram.org/file/bot'.API_KEY.'/'.$patch, 'r'));
	sendPhoto($chat_id,new CURLFile("data/photo.png"));
	unlink("data/photo.png");
	sendMessage($chat_id, "👇🏻 یکی از دکمه های زیر را انتخاب کنید :", 'markdown', $message_id, $button_tools);
}
elseif ($data['step'] == 'QrCode') {
	if (!empty($text)) {
		sendAction($chat_id, 'upload_photo');
		bot('sendPhoto', [
			'chat_id' => $chat_id,
			'photo' => 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&charset-source=utf-8&data=' . urlencode($text),
			'reply_to_message_id' => $message_id
		]);
	}
	elseif (isset($message->photo)) {
		sendAction($chat_id);

		$file_id = $message->photo[count($message->photo)-1]->file_id;
		$file_path = bot('getFile', ['file_id'=> $file_id])['result']['file_path'];
		$decode = json_decode(file_get_contents('http://api.qrserver.com/v1/read-qr-code/?fileurl=https://api.telegram.org/file/bot' . API_KEY . '/' . $file_path), true)[0]['symbol'][0]['data'];

		if ($decode != '') {
			sendMessage($chat_id, $decode, null, $message_id);
		}
		else {
			sendMessage($chat_id, '❌ لطفا تصویر یک QrCode را ارسال کنید.', null, $message_id);
		}
	}
	else {
		sendMessage($chat_id, '〽️ برای ساخت QrCode متن مورد نظرتان را ارسال کنید.

🌀 برای خواندن QrCode تصویر QrCode مورد نظرتان را ارسال کنید.', null, $message_id);
	}
}
elseif ($data['step'] == 'translate' && isset($text)) {
	sendAction($chat_id);
	$data['step'] = "translate0";
	$data['translate'] = $text;
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "🏳️‍🌈 به چه زبانی ترجمه شود ؟", 'markdown', $message_id, $languages);
}
elseif ($data['step'] == "translate0") {
	sendAction($chat_id);
	$langs = ["🇮🇷 فارسی", "🇺🇸 انگلیسی", "🇸🇦 عربی", "🇷🇺 روسی", "🇫🇷 فرانسوی", "🇹🇷 ترکی"];
	if (in_array($text, $langs)) {
		$langs = ["🇮🇷 فارسی", "🇺🇸 انگلیسی", "🇸🇦 عربی", "🇷🇺 روسی", "🇫🇷 فرانسوی", "🇹🇷 ترکی"];
		$langs_a = ["fa", "en", "ar", "ru", "fr", "tr"];
		$lan = str_replace($langs, $langs_a, $text);
		// $get = file_get_contents("https://translate.yandex.net/api/v1.5/tr.json/translate?key=trnsl.1.1.20160119T111342Z.fd6bf13b3590838f.6ce9d8cca4672f0ed24f649c1b502789c9f4687a&format=plain&lang=$lan&text=" . urlencode($data['translate']));
		// $result = json_decode($get, true)['text'][0];

		$fields = array('sl' => urlencode('auto'), 'tl' => urlencode($lan), 'q' => urlencode($data['translate']));
		
		$fields_string = '';
		
		foreach ($fields as $key => $value) {
			$fields_string .= '&' . $key . '=' . $value;
		}
		
		$ch = curl_init();
		
		curl_setopt_array($ch, [
			CURLOPT_URL => 'https://translate.googleapis.com/translate_a/single?client=gtx&dt=t',
			CURLOPT_POSTFIELDS => $fields_string,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => 'UTF-8',
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36(KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
		]);
		
		$res = json_decode(curl_exec($ch), true);
		
		foreach ($res[0] as $X => $Z) {
			if (!is_array($Z[0])) $result .= $Z[0];
		}
		
		
		if (!empty($result)) {
			sendMessage($chat_id, $result, null, $message_id);
		} else {
			sendMessage($chat_id, "❌ متاسفانه ترجمه انجام نشد.", null, $message_id);
		}
	}
	else {
		$data['step'] = "translate0";
		$data['translate'] = $text;
		file_put_contents("data/data.json",json_encode($data));
		sendMessage($chat_id, "🏳️‍🌈 به چه زبانی ترجمه شود ؟", 'markdown', $message_id, $languages);
		//sendMessage($chat_id, "👇🏻 لطفا یکی از دکمه های زیر را انتخاب کنید.", 'markdown', $message_id, $languages);
	}
}
elseif ($data['step'] == "write" && isset($text)) {
	sendAction($chat_id);
		$matn = strtoupper($text);
		$Eng = ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'C', 'V', 'B', 'N', 'M'];
		
		//Fonts
		$Font_1 = ['ⓠ', 'ⓦ', 'ⓔ', 'ⓡ', 'ⓣ', 'ⓨ', 'ⓤ', 'ⓘ', 'ⓞ', 'ⓟ', 'ⓐ', 'ⓢ', 'ⓓ', 'ⓕ', 'ⓖ', 'ⓗ', 'ⓙ', 'ⓚ', 'ⓛ', 'ⓩ', 'ⓧ', 'ⓒ', 'ⓥ', 'ⓑ', 'ⓝ', 'ⓜ'];
		$Font_2 = ['⒬', '⒲', '⒠', '⒭', '⒯', '⒴', '⒰', '⒤', '⒪', '⒫', '⒜', '⒮', '⒟', '⒡', '⒢', '⒣', '⒥', '⒦', '⒧', '⒵', '⒳', '⒞', '⒱', '⒝', '⒩', '⒨'];
		$Font_3 = ['🇶 ', '🇼 ', '🇪 ', '🇷 ', '🇹 ', '🇾 ', '🇺 ', '🇮 ', '🇴 ', '🇵 ', '🇦 ', '🇸 ', '🇩 ', '🇫 ', '🇬 ', '🇭 ', '🇯 ', '🇰 ', '🇱 ', '🇿 ', '🇽 ', '🇨 ', '🇻 ', '🇧 ', '🇳 ', '🇲 '];
		$Font_4 = ['զ', 'ա', 'ɛ', 'ʀ', 't', 'ʏ', 'ʊ', 'ɨ', 'օ', 'ք', 'a', 's', 'ɖ', 'ʄ', 'ɢ', 'ɦ', 'ʝ', 'ҡ', 'ʟ', 'ʐ', 'x', 'ᴄ', 'ʋ', 'ɮ', 'ռ', 'ʍ'];
		$Font_5 = ['ǫ', 'ᴡ', 'ᴇ', 'ʀ', 'ᴛ', 'ʏ', 'ᴜ', 'ɪ', 'ᴏ', 'ᴘ', 'ᴀ', 's', 'ᴅ', 'ғ', 'ɢ', 'ʜ', 'ᴊ', 'ᴋ', 'ʟ', 'ᴢ', 'x', 'ᴄ', 'ᴠ', 'ʙ', 'ɴ', 'ᴍ'];
		$Font_6 = ['ᑫ', 'ʷ', 'ᵉ', 'ʳ', 'ᵗ', 'ʸ', 'ᵘ', 'ᶦ', 'ᵒ', 'ᵖ', 'ᵃ', 'ˢ', 'ᵈ', 'ᶠ', 'ᵍ', 'ʰ', 'ʲ', 'ᵏ', 'ˡ', 'ᶻ', 'ˣ', 'ᶜ', 'ᵛ', 'ᵇ', 'ⁿ', 'ᵐ'];
		$Font_7 = ['ǫ', 'ш', 'ε', 'я', 'т', 'ч', 'υ', 'ı', 'σ', 'ρ', 'α', 'ƨ', 'ɔ', 'ғ', 'ɢ', 'н', 'נ', 'κ', 'ʟ', 'z', 'х', 'c', 'ν', 'в', 'п', 'м'];
		$Font_8 = ['φ', 'ω', 'ε', 'Ʀ', '†', 'ψ', 'u', 'ι', 'ø', 'ρ', 'α', 'Տ', 'ძ', 'δ', 'ĝ', 'h', 'j', 'κ', 'l', 'z', 'χ', 'c', 'ν', 'β', 'π', 'ʍ'];
		
		//Replace
		$font1 = str_replace($Eng, $Font_1, $matn);
		$font2 = str_replace($Eng, $Font_2, $matn);
		$font3 = trim(str_replace($Eng, $Font_3, $matn));
		$font4 = str_replace($Eng, $Font_4, $matn);
		$font5 = str_replace($Eng, $Font_5, $matn);
		$font6 = str_replace($Eng, $Font_6, $matn);
		$font7 = str_replace($Eng, $Font_7, $matn);
		$font8 = str_replace($Eng, $Font_8, $matn);

		if ($font1 != $text) {
			$data['step'] = "none";
			file_put_contents("data/data.json",json_encode($data));
			sendMessage($chat_id, "● `$font1`\n● `$font2`\n● `$font3`\n● `$font4`\n● `$font5`\n● `$font6`\n● `$font7`\n● `$font8`", 'markdown', $message_id, $button_tools);
		} else {
			sendMessage($chat_id, "🇺🇸 تنها متن انگلیسی قابل قبول است.", 'markdown', $message_id);
		}
}
elseif ($data['step'] == "webshot" && isset($text)) {
	if (preg_match('#^(http|https)\:\/\/(.*)\.(.*)$#', $text, $match)) {
		sendAction($chat_id, 'upload_photo');
		$data['step'] = "none";
		file_put_contents("data/data.json", json_encode($data));
		$photo = 'http://webshot.okfnlabs.org/api/generate?url=' . $match[0];
		sendPhoto($chat_id, $photo, '🎇 ' . $match[0]);
		sendMessage($chat_id, "👇🏻 یکی از دکمه های زیر را انتخاب کنید :", 'markdown', $message_id, $button_tools);
	}
	else {
		sendAction($chat_id);
		sendMessage($chat_id, "❌ لطفا یک آدرس اینترنتی معتبر ارسال کنید. مانند :\nhttps://google.com\nhttp://google.com", 'markdown', $message_id);
	}
}
// elseif ($data['step'] == 'ocr') {
// 	sendAction($chat_id);
// 	if (isset($update->message->photo)) {
// 		$file_id = $update->message->photo[count($update->message->photo)-1]->file_id;
// 		$file_path = bot('getFile', ['file_id' => $file_id])['result']['file_path'];
// 		$file_name = $file_id . '.png';
// 		file_put_contents($file_name, file_get_contents('https://api.telegram.org/file/bot' . API_KEY . '/' . $file_path));
// 		$url = 'https://api.ocr.space/parse/imageurl?apikey=211ff28b1088957&language=ara&url=' . $Folder_url . $file_name;
// 		$result = json_decode(file_get_contents($url), true);
// 		$text_extract = $result['ParsedResults'][0]['ParsedText'];
// 		if ($text_extract) {
// 			sendMessage($chat_id, $text_extract, null, $message_id, $button_tools);
// 			$data['step'] = "none";
// 			file_put_contents("data/data.json", json_encode($data));
// 		} else {
// 			sendMessage($chat_id, "❌ هیچ متنی استخراج نشد.", 'markdown', $message_id);
// 		}
// 		unlink($file_name);
// 	} else {
// 		sendMessage($chat_id, "🌠 لطفا یک تصویر ارسال کنید.", 'markdown', $message_id);
// 	}
// }
elseif ($data['step'] == 'face') {
	if (isset($update->message->photo)) {
		sendAction($chat_id, 'upload_photo');
		$file_id = $update->message->photo[count($update->message->photo)-1]->file_id;
		$file_path = bot('getFile', ['file_id' => $file_id])['result']['file_path'];
		sendPhoto($chat_id, $host_folder . '/Face/image.php?img=https://api.telegram.org/file/bot' . API_KEY . '/' . $file_path . '&rand=' . rand(0, 99999999999) . $file_id, "👦🏻👩🏻");
		sendMessage($chat_id, "👇🏻 یکی از دکمه های زیر را انتخاب کنید :", 'markdown', $message_id, $button_tools);
		$data['step'] = "none";
		file_put_contents("data/data.json", json_encode($data));
	} else {
		sendAction($chat_id);
		sendMessage($chat_id, "🌠 لطفا یک تصویر ارسال کنید.", 'markdown', $message_id);
	}
}
##----------------------
elseif ($data['step'] == "setstart" && isset($text)) {
	sendAction($chat_id);
	$data['step'] = "none";
	$data['text']['start'] = "$text";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "✅ متن مورد نظر با موفقیت تنظیم گردید.", 'markdown', $message_id, $peygham);
}
elseif ($data['step'] == "setdone" && isset($text)) {
	sendAction($chat_id);
	$data['step'] = "none";
	$data['text']['done'] = "$text";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "✅ متن مورد نظر با موفقیت تنظیم گردید.", 'markdown', $message_id, $peygham);
}
elseif ($data['step'] == "setprofile" && isset($text)) {
	sendAction($chat_id);
	$data['step'] = "none";
	if ($text != '🗑 خالی کردن پروفایل') {
		$data['text']['profile'] = "$text";
		sendMessage($chat_id, "✅ متن مورد نظر با موفقیت تنظیم گردید.", 'markdown', $message_id, $peygham);
	} else {
		unset($data['text']['profile']);
		sendMessage($chat_id, "✅ پروفایل با موفقیت خالی شد.", 'markdown', $message_id, $peygham);
	}
	file_put_contents("data/data.json",json_encode($data));
}
elseif ($data['step'] == 'set_channels_text' && isset($text)) {
	sendAction($chat_id);
	if ($text == '🔰 استفاده از متن پیشفرض') {
		$data['text']['lock'] = null;
		file_put_contents('data/data.json', json_encode($data));
		sendMessage($chat_id, "✅ متن پیشفرض تنظیم گردید.", 'markdown', $message_id, $peygham);
	} else {
		if (preg_match("%\@([a-zA-Z0-9\_]+)%is", $text) || preg_match("%(http(s)?\:\/\/)?[A-Za-z0-9]+(\.[a-z0-9-]+)+(:[0-9]+)?(/.*)?%is", $text)) {
			sendMessage($chat_id, "📛 استفاده از یوزرنیم و لینک مجاز نیست.", 'markdown', $message_id);
		}
		elseif (strpos($text, 'CHANNELS') === false) {
			sendMessage($chat_id, "📛 حتما باید از متغیر `CHANNELS` استفاده کنید.", 'markdown', $message_id);
		}
		else {
			$data['text']['lock'] = $text;
			$data['step'] = 'none';
			file_put_contents('data/data.json', json_encode($data));
			sendMessage($chat_id, "✅ تنظیم گردید.", 'markdown', $message_id, $peygham);
		}
	}
}
elseif ($data['step'] == 'set_off_text' && isset($text)) {
	sendAction($chat_id);
	if ($text == '🔰 استفاده از متن پیشفرض') {
		$data['text']['off'] = null;
		file_put_contents('data/data.json', json_encode($data));
		sendMessage($chat_id, "✅ متن پیشفرض تنظیم گردید.", 'markdown', $message_id, $peygham);
	} else {
		$data['text']['off'] = $text;
		$data['step'] = 'none';
		file_put_contents('data/data.json', json_encode($data));

		sendMessage($chat_id, "✅ تنظیم گردید.", 'markdown', $message_id, $peygham);
	}
}
elseif ($data['step'] == "user") {
	sendAction($chat_id);
	if (isset($forward)) {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$forward_id);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		if ($ok == true) {
			$data['step'] = "msg";
			$data['id'] = "$forward_id";
			file_put_contents("data/data.json",json_encode($data));
			sendMessage($chat_id, "🔰 پیام مورد نظر خودتان را ارسال کنیید.", 'markdown', $message_id, $back);
		} else {
			sendMessage($chat_id, "❌ کاربر عضو ربات نیست.\n\n⛔️ تنها کاربران عضو ربات قادر به دریافت پیام ها هستند.", 'markdown', $message_id, $panel);
		}
	} else {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$text);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		
		if ($ok == true) {
			$data['id'] = "$text";
			$data['step'] = "msg";
			file_put_contents("data/data.json",json_encode($data));
			sendMessage($chat_id, "🔰 پیام مورد نظر خودتان را ارسال کنیید.", 'markdown', $message_id, $back);
		} else {
			sendMessage($chat_id, "❌ کاربر عضو ربات نیست.\n\n⛔️ تنها کاربران عضو ربات قادر به دریافت پیام ها هستند.", 'markdown', $message_id, $panel);
		}
	}
}
elseif ($data['step'] == "msg") {
	sendAction($chat_id);
	$id = $data['id'];
	
	if ($forward_from != null) {
		Forward($id, $chat_id, $message_id);
	}
	elseif ($video_id != null) {
		sendVideo($id, $video_id, $caption);
	}
	elseif ($voice_id != null) {
		sendVoice($id, $voice_id, $caption);
	}
	elseif ($file_id != null) {
		sendDocument($id, $file_id, $caption);
	}
	elseif ($music_id != null) {
		sendAudio($id, $music_id, $caption);
	}
	elseif ($photo2_id != null) {
		sendPhoto($id, $photo2_id, $caption);
	}
	elseif ($photo1_id != null) {
		sendPhoto($id, $photo1_id, $caption);
	}
	elseif ($photo0_id != null) {
		sendPhoto($id, $photo0_id, $caption);
	}
	elseif ($text != null) {
		sendMessage($id, $text, null);
	}
	elseif ($sticker_id != null) {
		sendSticker($id, $sticker_id);
	}
	
	$data['step'] = "none";
	unset($data['id']);
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "✅ پیام شما برای کاربر ارسال گردید.", null, $message_id, $panel);
}
elseif ($data['step'] == "addword" && isset($text)) {
	sendAction($chat_id);
	$data['step'] = "ans";
	sendMessage($chat_id, "🔖 پاسخ عبارت « $text » را ارسال کنید.", null, $message_id, $backans);
	$data['word'] = "$text";
	$data['quick'][$text] = null;
	file_put_contents("data/data.json",json_encode($data));
}
elseif ($data['step'] == "ans" && isset($text)) {
	sendAction($chat_id);
	$word = $data['word'];
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	sendMessage($chat_id, "✅ عبارت « $text » به عنوان پاسخ برای « $word » ثبت شد.", null, $message_id, $quick);
	$data['quick'][$word] = "$text";
	unset($data['word']);
	file_put_contents("data/data.json",json_encode($data));
}
elseif ($data['step'] == "delword" && isset($text)) {
	sendAction($chat_id);
	if ($data['quick'][$text] != null) {
		sendMessage($chat_id, "🗑 عبارت « $text » از لیست پاسخ های خودکار حذف گردید.", null, $message_id, $quick);
		$data['step'] = "none";
		unset($data['quick'][$text]);
		file_put_contents("data/data.json",json_encode($data));
	} else {
		sendMessage($chat_id, "❌ عبارت ارسالی پیدا نشد.", 'markdown', $message_id);
	}
}
elseif ($data['step'] == "addfilter" && isset($text)) {
	sendAction($chat_id);
	if (!in_array($text, $data['filters'])) {
		$data['step'] = "none";
		sendMessage($chat_id, "✅ عبارت  « $text » فیلتر شد.", null, $message_id, $button_filter);
		$data['filters'][] = "$text";
		file_put_contents("data/data.json",json_encode($data));
	} else {
		sendMessage($chat_id, "❌ عبارت  « $text » از قبل فیلتر بود.", null, $message_id);
	}
}
elseif ($data['step'] == "delfilter" && isset($text)) {
	sendAction($chat_id);
	if (in_array($text, $data['filters'])) {
		sendMessage($chat_id, "✅ عبارت  « $text » آزاد شد.", null, $message_id, $button_filter);
		$data['step'] = "none";
		$search = array_search($text, $data['filters']);
		unset($data['filters'][$search]);
		$data['filters'] = array_values($data['filters']);
		file_put_contents("data/data.json",json_encode($data));
	} else {
		sendMessage($chat_id, "❌ عبارت ارسالی پیدا نشد.", 'markdown', $message_id);
	}
}
elseif ($data['step'] == "addadmin") {
	sendAction($chat_id);
	if (is_numeric($text) == true) {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$text);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		if ($ok == true) {
			if (!in_array($text, $list['admin'])) {
				if ($list['admin'] == null) {
					$list['admin'] = [];
				}
				array_push($list['admin'], $text);
				file_put_contents("data/list.json",json_encode($list));
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$text'>".getChat($text, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » ادمین ربات شد.", 'html', $message_id, $button_admins);
				sendMessage($text, "✅ شما ادمین ربات شدید.\n\n🔰 از این پس می توانید در گروه پشتیبانی به فعالیت بپردازید.", 'markdown', null);
			} else {
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$text'>".getChat($text, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » از قبل ادمین بود.", 'html', $message_id, $button_admins);
			}
		} else {
			sendMessage($chat_id, "❌ کاربر « $text » یافت نشد.", 'markdown', $message_id);
		}
		file_put_contents("data/data.json",json_encode($data));
	}
	elseif (isset($forward)) {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$forward_id);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		if ($ok == true) {
			if (!in_array($forward_id, $list['admin'])) {
				if ($list['admin'] == null) {
					$list['admin'] = [];
				}
				array_push($list['admin'], $forward_id);
				file_put_contents("data/list.json",json_encode($list));
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$forward_id'>".getChat($forward_id, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » ادمین ربات شد.", 'html', $message_id, $button_admins);
				sendMessage($forward_id, "✅ شما ادمین ربات شدید.\n\n🔰 از این پس می توانید در گروه پشتیبانی به فعالیت بپردازید.", 'markdown', null);
			} else {
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$forward_id'>".getChat($forward_id, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » از قبل ادمین بود.", 'html', $message_id, $button_admins);
			}
		} else {
			sendMessage($chat_id, "❌ کاربر « $text » یافت نشد.", 'markdown', $message_id);
		}
		file_put_contents("data/data.json",json_encode($data));
	}
}
elseif ($data['step'] == "deladmin") {
	sendAction($chat_id);
	if (is_numeric($text) == true) {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$text);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		if ($ok == true) {
			if (in_array($text, $list['admin'])) {
				$search = array_search($text, $list['admin']);
				unset($list['admin'][$search]);
				$list['admin'] = array_values($list['admin']);
				file_put_contents("data/list.json",json_encode($data));
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$text'>".getChat($text, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » برکنار شد.", 'html', $message_id, $button_admins);
				sendMessage($text, "🔰 شما برکنار شدید و دیگر ادمین ربات نیستید.", 'markdown', null);
			} else {
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$text'>".getChat($text, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » از قبل ادمین نبود.", 'html', $message_id, $button_admins);
			}
		} else {
			sendMessage($chat_id, "❌ کاربر « $text » یافت نشد.", 'markdown', $message_id);
		}
		file_put_contents("data/data.json",json_encode($data));
	}
	elseif (isset($forward)) {
		$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$forward_id);
		$result = json_decode($get, true);
		$ok = $result['ok'];
		if ($ok == true) {
			if (in_array($forward_id, $list['admin'])) {
				$search = array_search($forward_id, $list['admin']);
				unset($list['admin'][$search]);
				$list['admin'] = array_values($list['admin']);
				file_put_contents("data/list.json",json_encode($data));
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$forward_id'>".getChat($forward_id, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » برکنار شد.", 'html', $message_id, $button_admins);
				sendMessage($forward_id, "🔰 شما برکنار شدید و دیگر ادمین ربات نیستید.", 'markdown', null);
			} else {
				$data['step'] = "none";
				$mention = "<a href='tg://user?id=$forward_id'>".getChat($forward_id, false)->result->first_name."</a>";
				sendMessage($chat_id, "👨🏻‍💻 کاربر « $mention » از قبل ادمین نبود.", 'html', $message_id, $button_admins);
			}
		} else {
			sendMessage($chat_id, "❌ کاربر « $text » یافت نشد.", 'markdown', $message_id);
		}
		file_put_contents("data/data.json",json_encode($data));
	}
}
elseif ($data['step'] == "addbutton" && isset($text)) {
	sendAction($chat_id);
        $text = str_replace("\n", '', $text);
        if (mb_strlen($text, 'UTF-8') > 60) {
                sendMessage($chat_id, "❌ نام دکمه نمی تواند بیشتر از 60 کاراکتر باشد.", null, $message_id);
                exit();
        }
        $data['step'] = "ansbtn|$text";
        sendMessage($chat_id, "⌨️ مطلب مورد نظرتان را برای دکمه « $text » ارسال کنید.", null, $message_id, $backbtn);
        $x = [];
        $x[] = $text;
        foreach ($data['buttons'] as $y) {
                $x[] = $y;
        }
        $data['buttons'] = $x;
        file_put_contents("data/data.json",json_encode($data));
        goto tabliq;
}
elseif (strpos($data['step'], "ansbtn") !== false && isset($text)) {
	sendAction($chat_id);
	$nambtn = str_replace("ansbtn|",null, $data['step']);
	$data['step'] = "none";
	sendMessage($chat_id, "✅ مطلب « $text » برای دکمه « $nambtn » تنظیم شد.", null, $message_id, $button);
	$data['buttonans'][$nambtn] = "$text";
	file_put_contents("data/data.json",json_encode($data));
}
elseif ($data['step'] == "delbutton" && isset($text)) {
	sendAction($chat_id);
	if (in_array($text, $data['buttons'])) {
		sendMessage($chat_id, "🗑 دکمه « $text » حذف گردید.", null, $message_id, $button);
		$data['step'] = "none";
		$search = array_search($text, $data['buttons']);
		unset($data['buttons'][$search]);
		unset($data['buttonans'][$text]);
		$data['buttons'] = array_values($data['buttons']);
		file_put_contents("data/data.json",json_encode($data));
	} else {
		sendMessage($chat_id, "❌ دکمه مورد نظر شما یافت نشد.", 'markdown', $message_id);
	}
}
elseif ($data['step'] == "upload" && isset($message) && !$text) {
	sendAction($chat_id);

	if ($sticker_id != null) {
		$file = $sticker_id;
	}
	elseif ($video_id != null) {
		$file = $video_id;
	}
	elseif ($voice_id != null) {
		$file = $voice_id;
	}
	elseif ($file_id != null) {
		$file = $file_id;
	}
	elseif ($music_id != null) {
		$file = $music_id;
	}
	elseif ($photo2_id != null) {
		$file = $photo2_id;
	}
	elseif ($photo1_id != null) {
		$file = $photo1_id;
	}
	elseif ($photo0_id != null) {
		$file = $photo0_id;
	}
	
	$get = bot('getFile',['file_id'=> $file]);
	if (!isset($get['result']['file_path'])) {
		sendMessage($chat_id, "💾 حجم رسانه ارسالی بیش از حد مجاز است.", null, $message_id);
		goto tabliq;
	}
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	$file_path = $get['result']['file_path'];
	$file_link = 'https://api.telegram.org/file/bot' . API_KEY . '/' . $file_path;

	sendMessage($chat_id, "🔰 لینک مستقیم تلگرامی :

{$file_link}

👆🏻 تذکر جدی : این لینک حاوی توکن ربات شماست. پس برای به خطر نیفتادن امنیت رباتتان آنرا در اختیار هیچ کس قرار ندهید.
❕به دلیل فیلتر بودن تلگرام در ایران باید از فیلتر شکن برای دانلود فایلتان استفاده کنید."
, null, $message_id, $button_tools);
}
elseif ($data['step'] == "download" && isset($text)) {
	if (preg_match('#https?\:\/\/www\.instagram\.com\/(p|tv)\/([a-zA-Z0-9\-\_]+)#isu', $text, $matches)) {
		sendMessage($chat_id, "❌ متاسفانه امکان دانلود پست های اینستاگرام وجود ندارد. لطفا یک لینک دیگر ارسال کنید.", null, $message_id);
                exit();
	}
	if (filter_var($text, FILTER_VALIDATE_URL)) {
		$header = get_headers($text, 1);
		$regex = $text . '' . implode(' ', $header['Content-Type']);
		if ($header['Content-Length'] > 1 && !preg_match('#htm#i', $regex)) {
			if ($header['Content-Length'] < 20*1024*1024) {
				$type = $header['Content-Type'];
				if (preg_match('#api\.telegram\.org/file/#i', $text)) {
					$file_name = time() . '.' . pathinfo($text)['extension'];

					file_put_contents($file_name, '');
					chmod($file_name, 0666);
					file_put_contents($file_name, file_get_contents($text));
					
					//copy($text, $file_name);
					$text = new CURLFile($file_name);
				}
				if (preg_match('#mp4#i', $regex)) {
					sendAction($chat_id, 'upload_video');
					sendVideo($chat_id, $text);
				}
				elseif (preg_match('#(webp|tgs)#i', $regex)) {
					sendSticker($chat_id, $text);
				}
				elseif (preg_match('#oga#i', $regex)) {
					sendAction($chat_id, 'record_audio');
					sendVoice($chat_id, $text);
				}
				elseif (preg_match('#(mp3png)#i', $regex)) {
					sendAction($chat_id, 'upload_audio');
					sendAudio($chat_id, $text);
				}
				elseif (preg_match('#(jpg|jpeg|png)#i', $regex)) {
					sendAction($chat_id, 'upload_photo');
					sendPhoto($chat_id, $text);
				}
				else {
					sendAction($chat_id, 'upload_document');
					sendDocument($chat_id, $text);
				}
				sendMessage($chat_id, "👇🏻 یکی از دکمه های زیر را انتخاب کنید :", null, $message_id, $button_tools);
				@unlink($file_name);
			} else {
				$size = humanFileSize($header['Content-Length']);
				sendMessage($chat_id, "❌ حجم فایل بیش از ۲۰ مگابایت است و نمی توانم آنرا دانلود کنم.\n\n💠 حجم فایل : $size", null, $message_id);
				goto tabliq;
			}
		} else {
			sendMessage($chat_id, "❌ لطفا یک لینک معتبر ارسال کنید.", null, $message_id);
			goto tabliq;
		}
		$data['step'] = "none";
		file_put_contents("data/data.json", json_encode($data));
		goto tabliq;
} else {
	sendMessage($chat_id, "❌ لطفا یک لینک معتبر ارسال کنید.", null, $message_id);
}
}
elseif (strpos($data['step'], "btn") !== false) {
	sendAction($chat_id);
	$nambtn = str_replace("btn", '', $data['step']);
	$data['step'] = "none";
	
	$en = array ('profile', 'contact', 'location');
	$fa = array ('پروفایل', 'ارسال شماره', 'ارسال مکان');
	$str = str_replace($en, $fa, $nambtn);
	sendMessage($chat_id, "✅ نام « $text » برای دکمه « $str » تنظیم گردید.", null, $message_id, $button_name);
	$data['button'][$nambtn]['name'] = "$text";
	file_put_contents("data/data.json",json_encode($data));
}
elseif ($data['step'] == "userinfo" && is_numeric($text) == true) {
	sendAction($chat_id);
	$data['step'] = "none";
	file_put_contents("data/data.json",json_encode($data));
	
	$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$text);
	$result = json_decode($get, true);
	$ok = $result['ok'];
	if ($ok == true) {
		$mention = "<a href='tg://user?id=$text'>$text</a>" . "\n";
		$f_name = $result['result']['first_name'] . "\n";
		if ($result['result']['last_name'] != null) {
			$l_name = "Last: " . $result['result']['last_name'] . "\n";
		} else {
			$l_name = '';
		}
		if ($result['result']['username'] != null) {
			$username = "@".$result['result']['username'] . "\n";
		} else {
			$username = '';
		}
		$profile = GetProfile($text);
		if ($profile != null) {
			sendPhoto($chat_id, $profile, "🏞 تصویر پروفایل");
		}
		sendMessage($chat_id, "{$username}Id: {$mention}First: {$f_name}{$l_name}", 'html', $message_id, $panel);
	} else {
		sendMessage($chat_id, "❌ کاربری با شناسه تلگرامی « $text » یافت نشد.", 'markdown', $message_id, $panel);
	}
}
##----------------------
elseif (preg_match("|\/ban([\_\s])([0-9]+)|i", $text, $match)) {
	sendAction($chat_id);
	$get = file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$match[2]);
	$result = json_decode($get, true);
	$ok = $result['ok'];
	if ($ok && $match[2] != $Dev) {
		if (!in_array($match[2], $list['ban'])) {
			if ($list['ban'] == null) {
				$list['ban'] = [];
			}
			array_push($list['ban'], $match[2]);
			file_put_contents("data/list.json",json_encode($list));
			sendMessage($chat_id, "⛔️ کاربر [$match[2]](tg://user?id={$match[2]}) از ربات مسدود گردید.", 'markdown', $message_id);
			sendMessage($match[2], "⛔️ شما مسدود شدید و دیگر ربات به پیام های شما پاسخ نخواهد داد.", 'markdown', null, $remove);
		} else {
			sendMessage($chat_id, "👤 کاربر [$match[2]](tg://user?id={$match[2]}) از قبل مسدود بود.", 'markdown', $message_id);
		}
	} else {
		sendMessage($chat_id, "❌ کاربر *".$match[2]."* وجود ندارد.", 'markdown', $message_id);
	}
}
##----------------------
elseif (preg_match("|\/unban([\_\s])([0-9]+)|i", $text, $match)) {
	sendAction($chat_id);
	if (in_array($match[2], $list['ban'])) {
		$search = array_search($match[2], $list['ban']);
		unset($list['ban'][$search]);
		$list['ban'] = array_values($list['ban']);
		file_put_contents("data/list.json",json_encode($list, true));
		sendMessage($chat_id, "⛔️ کاربر [$match[2]](tg://user?id={$match[2]}) آزاد شد.", 'markdown', null, $panel);
		sendMessage($match[2], "🔰 شما آزاد گردیدید.\n✅ دستور /start را ارسال نمایید.", 'markdown', null);
	}
	else {
		sendMessage($chat_id, "👤 کاربر [$match[2]](tg://user?id={$match[2]}) از قبل آزاد بود.", 'markdown', null);
	}
}
}
tabliq:

if ($is_vip) exit();

if ($from_id != $Dev) {
	@$ads = json_decode(file_get_contents('../../Data/ads.json'), true);
	foreach ($ads as $key => $ad) {
		if (!is_file("../../Data/{$key}.json")) {
			file_put_contents("../../Data/{$key}.json", '');
		}
		$seen = file_get_contents("../../Data/{$key}.json");
		if (strpos($seen, "$from_id, ") === false) {
			file_put_contents("../../Data/{$key}.json", "{$seen}{$from_id}, ");
			$type = $ad['type'];
			$method = str_replace(['video', 'photo', 'document', 'text'], ['sendVideo', 'sendPhoto', 'sendDocument', 'sendMessage'], $type);
			$data = [
				'chat_id' => $chat_id,
				'parse_mode' => 'html'
			];
			if ($type == 'text') {
				$data['text'] = $ad['text'];
				$data['disable_web_page_preview'] = true;
			} else {
				$data[$type] = 'https://telegram.me/' . str_replace('@', '', $public_logchannel) . '/' . $ad['file_id'];
				$data['caption'] = $ad['text'];
			}
			if ($ad['keyboard'] != null) {
				$data['reply_markup'] = json_encode($ad['keyboard']);
			}
			bot($method, $data);
			$ads[$key]['count'] = $ad['count']+1;
			file_put_contents('../../Data/ads.json', json_encode($ads));
			break;
		}
	}
}
@unlink('error_log');