<?php
set_time_limit(5);
error_reporting(0);
date_default_timezone_set('Asia/Tehran');
##----------------------
require 'handler.php';
##----------------------

// ========================================
// ربات پیام‌رسان - نسخه 1.0.0
// ========================================

if (isset($from_id) && in_array($from_id, $list['ban'])) {
	exit();
}

// بررسی قفل زبان فارسی برای پیام‌های ویرایش شده
if (isset($message->text) && $is_edited) {
	if ($data['lock']['persian'] == '✅') {
		$checkpersian = CheckPersianLanguage($text);
		if ($checkpersian == true) {
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ استفاده از زبان های غیر فارسی در ربات ممنوع است.", 'html' , null, $button_user);
			goto tabliq;
		}
	}
}

// بررسی قفل زبان فارسی برای پیام‌های عادی
if (isset($message->text)) {
	if ($data['lock']['persian'] == '✅') {
		$checkpersian = CheckPersianLanguage($text);
		if ($checkpersian == true) {
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ استفاده از زبان های غیر فارسی در ربات ممنوع است.", 'html' , null, $button_user);
			goto tabliq;
		}
	}
	
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
				}
				sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
			}
		}
		if ($checklink == true) {
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال پیام های حاوی لینک مجاز نیست.", 'html' , null, $button_user);
		}
		if ($checkfilter == true) {
			bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
			sendMessage($chat_id, "⛔️ ارسال پیام های حاوی کلمات غیر مجاز ممنوع است.", 'html' , null, $button_user);
		}
	} else {
		bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
		sendMessage($chat_id, "⛔️ ارسال متن مجاز نیست.", 'html' , null, $button_user);
	}
	goto tabliq;
}

// بررسی سایر انواع پیام‌ها
if (isset($message->photo)) {
	if ($data['lock']['photo'] != '✅') {
		$get = Forward($Dev, $chat_id, $message_id);
		if (!isset($get['result']['forward_from'])  || isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
			$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
			$msg_ids[$get['result']['message_id']] = $from_id;
			file_put_contents('msg_ids.txt', json_encode($msg_ids));
		}
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
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
		}
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
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
		}
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
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
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
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
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
		bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
		sendMessage($chat_id, "⛔️ ارسال استیکر مجاز نیست.", 'html' , null, $button_user);
	}
	goto tabliq;
}

if (isset($message->document)) {
	if ($data['lock']['document'] != '✅') {
		$get = Forward($Dev, $chat_id, $message_id);
		$msg_ids = json_decode(file_get_contents('msg_ids.txt'), true);
		$msg_ids[$get['result']['message_id']] = $from_id;
		file_put_contents('msg_ids.txt', json_encode($msg_ids));
		sendMessage($chat_id, "$done", 'html' , $message_id, $button_user);
	} else {
		bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
		sendMessage($chat_id, "⛔️ ارسال فایل مجاز نیست.", 'html' , null, $button_user);
	}
	goto tabliq;
}

// بررسی پیام‌های فروارد
if (isset($update->message->forward_from) || isset($update->message->forward_from_chat)) {
	if ($data['lock']['forward'] == '✅') {
		bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
		sendMessage($chat_id, "⛔️ ارسال پیام های هدایت شده (فروارد شده) مجاز نیست.", 'html' , null, $button_user);
		goto tabliq;
	}
}

// ========================================
// دستورات ربات
// ========================================

if ($text == '/start') {
	sendMessage($chat_id, "🎉 به ربات پیام‌رسان خوش آمدید!\n\n📝 این ربات برای ارسال پیام‌ها طراحی شده است.", 'html', $message_id, $button_user);
	goto tabliq;
}

// ========================================
// پنل مدیریت
// ========================================

if ($text == '⚙️ پنل مدیریت' && $from_id == $Dev) {
	$panel = json_encode(['keyboard'=>[
		[['text'=>"📊 آمار"],['text'=>"🔐 قفل ها"]],
		[['text'=>"✉️ پیام همگانی"],['text'=>"🚀 هدایت همگانی"]],
		[['text'=>"🔄 آپدیت ربات"]],
		[['text'=>"💡 روشن کردن ربات"],['text'=>"😴 خاموش کردن ربات"]]
	],'resize_keyboard'=>true]);
	
	sendMessage($chat_id, "🔧 پنل مدیریت ربات\n\nلطفا یکی از گزینه‌ها را انتخاب کنید:", 'html', $message_id, $panel);
	goto tabliq;
}

// ========================================
// سیستم آپدیت
// ========================================

elseif ($text == '🔄 آپدیت ربات') {
	sendAction($chat_id);
	
	// Check if update is available
	$version_data = json_decode(file_get_contents('version.json'), true);
	$current_version = $version_data['version'];
	$update_url = $version_data['update_url'];
	$update_file = $version_data['update_file'];
	
	// Check if update file exists on server
	$update_available = false;
	$file_size = 0;
	$file_date = "";
	
	try {
		// Check if update file exists
		$headers = @get_headers($update_url);
		if ($headers && strpos($headers[0], '200') !== false) {
			$update_available = true;
			
			// Get file information
			$context = stream_context_create([
				'http' => [
					'timeout' => 10,
					'user_agent' => 'TelegramBot/1.0'
				]
			]);
			
			// Get file size
			$file_info = @file_get_contents($update_url, false, $context);
			if ($file_info !== false) {
				$file_size = strlen($file_info);
				$file_date = date('Y-m-d H:i:s');
			}
		}
	} catch (Exception $e) {
		// If we can't check for updates, assume no update available
		$update_available = false;
	}
	
	if ($update_available) {
		// Update is available
		$data['step'] = "confirm_update";
		$data['update_info'] = [
			'update_url' => $update_url,
			'update_file' => $update_file,
			'file_size' => $file_size,
			'file_date' => $file_date
		];
		file_put_contents("data/data.json", json_encode($data));
		
		$update_keyboard = json_encode([
			'keyboard' => [
				[['text' => '✅ بله، آپدیت کن']],
				[['text' => '❌ خیر، آپدیت نکن']],
				[['text' => '🔙 بازگشت']]
			],
			'resize_keyboard' => true
		]);
		
		$update_message = "🔄 آپدیت جدید موجود است!\n\n";
		$update_message .= "📦 نسخه فعلی: `$current_version`\n";
		$update_message .= "📁 فایل آپدیت: `$update_file`\n";
		if ($file_size > 0) {
			$update_message .= "📏 حجم فایل: `" . number_format($file_size / 1024, 2) . " KB`\n";
		}
		if ($file_date) {
			$update_message .= "📅 تاریخ فایل: `$file_date`\n";
		}
		$update_message .= "\n❓ آیا می‌خواهید ربات را آپدیت کنید؟";
		
		sendMessage($chat_id, $update_message, 'markdown', $message_id, $update_keyboard);
	} else {
		// No update available
		$status_message = "✅ ربات شما در آخرین نسخه موجود است!\n\n";
		$status_message .= "📦 نسخه فعلی: `$current_version`\n";
		$status_message .= "📅 تاریخ انتشار: `" . $version_data['release_date'] . "`\n";
		$status_message .= "📁 فایل آپدیت: `$update_file`\n";
		$status_message .= "🔗 آدرس فایل: `$update_url`";
		
		sendMessage($chat_id, $status_message, 'markdown', $message_id, $panel);
	}
}

elseif ($text == '✅ بله، آپدیت کن' && $data['step'] == "confirm_update") {
	sendAction($chat_id);
	
	// Get update info
	$update_info = $data['update_info'];
	$update_url = $update_info['update_url'];
	$update_file = $update_info['update_file'];
	
	// Perform the update
	sendMessage($chat_id, "🔄 در حال آپدیت ربات...\n\n⏳ لطفا صبر کنید...", 'markdown', $message_id);
	
	// Create backup if enabled
	$version_data = json_decode(file_get_contents('version.json'), true);
	if ($version_data['backup_enabled']) {
		$backup_dir = 'backups/';
		if (!is_dir($backup_dir)) {
			mkdir($backup_dir, 0755, true);
		}
		
		$backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
		$backup_path = $backup_dir . $backup_name;
		
		// Create backup of current files
		$zip = new ZipArchive();
		if ($zip->open($backup_path, ZipArchive::CREATE) === TRUE) {
			// Add current files to backup
			$files_to_backup = ['bot.php', 'handler.php', 'config.php', 'index.php'];
			foreach ($files_to_backup as $file) {
				if (file_exists($file)) {
					$zip->addFile($file, $file);
				}
			}
			$zip->close();
		}
	}
	
	// Download and extract update
	try {
		// Download update file
		$update_content = file_get_contents($update_url);
		if ($update_content === false) {
			throw new Exception("خطا در دانلود فایل آپدیت");
		}
		
		// Save update file temporarily
		$temp_update_file = 'temp_update.zip';
		file_put_contents($temp_update_file, $update_content);
		
		// Extract update
		$zip = new ZipArchive();
		if ($zip->open($temp_update_file) === TRUE) {
			$zip->extractTo('./');
			$zip->close();
			
			// Remove temporary file
			unlink($temp_update_file);
			
			// Update version.json with new version
			$new_version = $version_data['version'];
			$version_parts = explode('.', $new_version);
			$version_parts[2] = intval($version_parts[2]) + 1; // Increment patch version
			$new_version = implode('.', $version_parts);
			
			$version_data['version'] = $new_version;
			$version_data['release_date'] = date('Y-m-d');
			file_put_contents('version.json', json_encode($version_data, JSON_PRETTY_PRINT));
			
			$data['step'] = "none";
			unset($data['update_info']);
			file_put_contents("data/data.json", json_encode($data));
			
			$features_text = implode("\n• ", $version_data['features']);
			$update_complete_message = "✅ ربات با موفقیت آپدیت شد!\n\n";
			$update_complete_message .= "📦 نسخه جدید: `$new_version`\n";
			$update_complete_message .= "📅 تاریخ آپدیت: `" . date('Y-m-d H:i:s') . "`\n";
			if ($version_data['backup_enabled']) {
				$update_complete_message .= "💾 پشتیبان: `$backup_name`\n";
			}
			$update_complete_message .= "\n🆕 قابلیت‌های جدید:\n• $features_text\n\n";
			$update_complete_message .= "🔄 ربات در حال راه‌اندازی مجدد...";
			
			sendMessage($chat_id, $update_complete_message, 'markdown', $message_id, $panel);
		} else {
			throw new Exception("خطا در استخراج فایل آپدیت");
		}
	} catch (Exception $e) {
		// Update failed
		$data['step'] = "none";
		unset($data['update_info']);
		file_put_contents("data/data.json", json_encode($data));
		
		$error_message = "❌ خطا در آپدیت ربات!\n\n";
		$error_message .= "🔍 خطا: `" . $e->getMessage() . "`\n";
		$error_message .= "📞 لطفا با پشتیبانی تماس بگیرید.";
		
		sendMessage($chat_id, $error_message, 'markdown', $message_id, $panel);
	}
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

// ========================================
// اسکریپت ایجاد فایل آپدیت
// ========================================

/**
 * تابع ایجاد فایل آپدیت
 * این تابع برای ایجاد فایل ZIP آپدیت استفاده می‌شود
 */
function createUpdateFile($version = null) {
    // اگر نسخه مشخص نشده، نسخه فعلی را افزایش دهید
    if ($version === null) {
        $version_data = json_decode(file_get_contents('version.json'), true);
        $current_version = $version_data['version'];
        $version_parts = explode('.', $current_version);
        $version_parts[2] = intval($version_parts[2]) + 1; // افزایش patch version
        $version = implode('.', $version_parts);
    }
    
    $update_name = "update_v{$version}.zip";
    $files_to_include = [
        'bot.php',
        'handler.php', 
        'config.php',
        'index.php',
        'version.json'
    ];
    
    // ایجاد فایل ZIP
    $zip = new ZipArchive();
    if ($zip->open($update_name, ZipArchive::CREATE) === TRUE) {
        
        // اضافه کردن فایل‌ها
        foreach ($files_to_include as $file) {
            if (file_exists($file)) {
                $zip->addFile($file, $file);
            }
        }
        
        // به‌روزرسانی version.json در فایل آپدیت
        $version_data = json_decode(file_get_contents('version.json'), true);
        $version_data['version'] = $version;
        $version_data['release_date'] = date('Y-m-d');
        
        $zip->addFromString('version.json', json_encode($version_data, JSON_PRETTY_PRINT));
        
        $zip->close();
        
        return [
            'success' => true,
            'filename' => $update_name,
            'version' => $version,
            'size' => filesize($update_name),
            'date' => date('Y-m-d H:i:s')
        ];
        
    } else {
        return [
            'success' => false,
            'error' => 'خطا در ایجاد فایل ZIP'
        ];
    }
}

/**
 * تابع بررسی و ایجاد آپدیت از طریق دستور
 */
if (isset($_GET['action']) && $_GET['action'] === 'create_update') {
    header('Content-Type: application/json; charset=utf-8');
    
    $version = isset($_GET['version']) ? $_GET['version'] : null;
    $result = createUpdateFile($version);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * تابع بررسی و ایجاد آپدیت از طریق CLI
 */
if (php_sapi_name() === 'cli') {
    $args = $argv;
    if (isset($args[1]) && $args[1] === 'create_update') {
        $version = isset($args[2]) ? $args[2] : null;
        $result = createUpdateFile($version);
        
        if ($result['success']) {
            echo "✅ فایل آپدیت با موفقیت ایجاد شد!\n";
            echo "📁 نام فایل: {$result['filename']}\n";
            echo "📦 نسخه: {$result['version']}\n";
            echo "📅 تاریخ: {$result['date']}\n";
            echo "📏 حجم: " . number_format($result['size'] / 1024, 2) . " KB\n";
            echo "\n📋 راهنمای استفاده:\n";
            echo "1. فایل {$result['filename']} را در هاست آپلود کنید\n";
            echo "2. آدرس فایل را در version.json تنظیم کنید\n";
            echo "3. از پنل مدیریت ربات آپدیت کنید\n";
        } else {
            echo "❌ خطا در ایجاد فایل آپدیت: {$result['error']}\n";
        }
        exit;
    }
}

tabliq:
?>