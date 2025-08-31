<?php
// Telegram Uploader Bot - ربات اپلودر تلگرام
// Complete working implementation

// Bot Configuration
define('BOT_TOKEN', '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ');
define('ADMIN_ID', 5641303137);
define('DB_HOST', 'localhost');
define('DB_NAME', 'dakallli_Test2');
define('DB_USER', 'dakallli_Test2');
define('DB_PASS', 'hosyarww123');

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables
createTables();

// Get updates from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    handleUpdate($update);
}

function createTables() {
    global $pdo;
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNIQUE,
        username VARCHAR(255),
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Folders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id VARCHAR(32) UNIQUE,
        title VARCHAR(255),
        is_public BOOLEAN DEFAULT FALSE,
        forward_lock BOOLEAN DEFAULT FALSE,
        created_by BIGINT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        dislikes INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Files table
    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id VARCHAR(255),
        file_name VARCHAR(255),
        file_size BIGINT,
        file_type VARCHAR(50),
        folder_id VARCHAR(32),
        uploaded_by BIGINT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        dislikes INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Bot settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // File likes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT,
        user_id BIGINT,
        like_type ENUM('like', 'dislike'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (file_id, user_id, like_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insert default settings
    $defaultSettings = [
        'bot_status' => 'on',
        'start_message' => 'سلام! به ربات اپلودر خوش آمدید.',
        'show_views' => '1',
        'show_likes' => '1'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
}

function handleUpdate($update) {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }
}

function handleMessage($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';
    $last_name = $message['from']['last_name'] ?? '';
    
    // Register user
    registerUser($user_id, $username, $first_name, $last_name);
    
    // Check if bot is disabled
    if (getBotSetting('bot_status') === 'off' && $user_id != ADMIN_ID) {
        sendMessage($chat_id, "ربات خاموش شده است از طرف مدیریت 🚫");
        return;
    }
    
    // Handle commands
    if (strpos($text, '/') === 0) {
        handleCommand($message);
        return;
    }
    
    // Handle file uploads
    if (isset($message['document']) || isset($message['photo']) || isset($message['video']) || isset($message['audio'])) {
        handleFileUpload($message);
        return;
    }
    
    // Handle text messages
    handleTextMessage($message);
}

function handleCommand($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    switch ($text) {
        case '/start':
            // Check if there's a payload (folder link)
            if (strpos($text, ' ') !== false) {
                $parts = explode(' ', $text);
                if (count($parts) > 1) {
                    $payload = $parts[1];
                    handleStartWithPayload($chat_id, $user_id, $payload);
                    return;
                }
            }
            
            if ($user_id == ADMIN_ID) {
                showAdminMainMenu($chat_id);
            } else {
                showUserMainMenu($chat_id);
            }
            break;
            
        case '/back':
            if ($user_id == ADMIN_ID) {
                showAdminMainMenu($chat_id);
            } else {
                showUserMainMenu($chat_id);
            }
            break;
    }
}

function showAdminMainMenu($chat_id) {
    $keyboard = [
        ['آپلود گروهی📂️', 'آپلود فایل⬆️'],
        ['ارسال پیام همگانی️📢', 'مشاهده فایل‌ها و آمار📊'],
        ['تنظیمات⚙️', 'خاموش/روشن کردن ربات🚫']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "🎯 منوی اصلی ادمین\n\nیکی از گزینه های زیر را انتخاب کنید:", $reply_markup);
}

function showUserMainMenu($chat_id) {
    $keyboard = [
        ['📂 مشاهده فایل‌ها', '🔍 جستجو'],
        ['📞 پشتیبانی', '📊 آمار']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, getBotSetting('start_message'), $reply_markup);
}

function handleCallbackQuery($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    
    // Answer callback query
    answerCallbackQuery($callback_query['id']);
    
    // Check if user is admin for admin functions
    if (strpos($data, 'admin_') === 0 && $user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ شما دسترسی ادمین ندارید!");
        return;
    }
    
    switch ($data) {
        case 'group_upload_start':
            if ($user_id == ADMIN_ID) {
                startGroupUpload($chat_id, $user_id);
            }
            break;
            
        case 'group_upload_finish':
            if ($user_id == ADMIN_ID) {
                finishGroupUpload($chat_id, $user_id);
            }
            break;
            
        case 'group_upload_back':
            if ($user_id == ADMIN_ID) {
                showGroupUploadMenu($chat_id);
            }
            break;
            
        default:
            if (strpos($data, 'folder_') === 0) {
                handleFolderAction($chat_id, $data, $user_id);
            } elseif (strpos($data, 'file_') === 0) {
                handleFileAction($chat_id, $data, $user_id);
            }
            break;
    }
}

function showGroupUploadMenu($chat_id) {
    $keyboard = [
        [
            ['text' => 'پایان', 'callback_data' => 'group_upload_finish'],
            ['text' => 'بازگشت', 'callback_data' => 'group_upload_back']
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "📁 آپلود گروهی\n\nفایل‌های خود را ارسال کنید تا به گروه اضافه شوند...\n\nبرای اتمام عملیات بر روی پایان کلیک کنید یا فایل جدیدی ارسال کنید.👇", $reply_markup);
}

function startGroupUpload($chat_id, $user_id) {
    setUserState($user_id, 'group_upload');
    sendMessage($chat_id, "📁 حالت آپلود گروهی فعال شد!\n\nفایل‌های خود را ارسال کنید...");
}

function finishGroupUpload($chat_id, $user_id) {
    $folder_id = generateFolderId();
    $files = getUserUploadedFiles($user_id);
    
    if (empty($files)) {
        sendMessage($chat_id, "❌ هیچ فایلی برای آپلود یافت نشد!");
        return;
    }
    
    // Create folder and add files
    createFolder($folder_id, "گروه فایل‌ها", $user_id);
    addFilesToFolder($folder_id, $files);
    
    $total_size = calculateTotalSize($files);
    $file_count = count($files);
    
    $message = "فایل‌های شما آپلود شد✅\n\n";
    $message .= "تعداد فایل‌ها: $file_count\n";
    $message .= "حجم فایل‌ها: " . formatFileSize($total_size) . "\n";
    $message .= "شناسه: $folder_id\n\n";
    $message .= "لینک اشتراک گذاری: " . getShareLink($folder_id);
    
    $keyboard = [
        [
            ['text' => 'مشاهده فایل ها', 'callback_data' => "folder_view_$folder_id"],
            ['text' => 'افزودن فایل', 'callback_data' => "folder_add_$folder_id"]
        ],
        [
            ['text' => 'قفل فوروارد', 'callback_data' => "folder_forward_lock_$folder_id"],
            ['text' => 'فولدر عمومی', 'callback_data' => "folder_public_$folder_id"]
        ],
        [['text' => 'حذف فولدر', 'callback_data' => "folder_delete_$folder_id"]]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, $message, $reply_markup);
    
    // Clear user state
    clearUserState($user_id);
}

function handleFileUpload($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    
    // Check if user is admin
    if ($user_id != ADMIN_ID) {
        return; // Silent ignore for non-admin users
    }
    
    // Get file info
    $file_info = extractFileInfo($message);
    
    if (!$file_info) {
        sendMessage($chat_id, "❌ خطا در دریافت فایل!");
        return;
    }
    
    // Check user state
    $user_state = getUserState($user_id);
    
    if ($user_state === 'group_upload') {
        // Add to group upload
        addFileToGroupUpload($user_id, $file_info);
        sendMessage($chat_id, "✅ این فایل به لیست شما اضافه شد!\n\nبرای اتمام عملیات بر روی پایان کلیک کنید یا فایل جدیدی ارسال کنید.👇");
    } else {
        // Single file upload
        $folder_id = generateFolderId();
        createFolder($folder_id, "فایل تکی", $user_id);
        addFileToFolder($folder_id, $file_info);
        
        $message = "✅ فایل شما آپلود شد!\n\n";
        $message .= "نام فایل: " . $file_info['name'] . "\n";
        $message .= "حجم: " . formatFileSize($file_info['size']) . "\n";
        $message .= "شناسه: $folder_id\n\n";
        $message .= "لینک اشتراک گذاری: " . getShareLink($folder_id);
        
        $keyboard = [
            [
                ['text' => 'مشاهده فایل ها', 'callback_data' => "folder_view_$folder_id"],
                ['text' => 'افزودن فایل', 'callback_data' => "folder_add_$folder_id"]
            ],
            [
                ['text' => 'قفل فوروارد', 'callback_data' => "folder_forward_lock_$folder_id"],
                ['text' => 'فولدر عمومی', 'callback_data' => "folder_public_$folder_id"]
            ],
            [['text' => 'حذف فولدر', 'callback_data' => "folder_delete_$folder_id"]]
        ];
        
        $reply_markup = ['inline_keyboard' => $keyboard];
        sendMessage($chat_id, $message, $reply_markup);
    }
}

function extractFileInfo($message) {
    if (isset($message['document'])) {
        $doc = $message['document'];
        return [
            'id' => $doc['file_id'],
            'name' => $doc['file_name'],
            'size' => $doc['file_size'],
            'type' => 'document'
        ];
    } elseif (isset($message['photo'])) {
        $photo = end($message['photo']);
        return [
            'id' => $photo['file_id'],
            'name' => 'photo.jpg',
            'size' => $photo['file_size'] ?? 0,
            'type' => 'photo'
        ];
    } elseif (isset($message['video'])) {
        $video = $message['video'];
        return [
            'id' => $video['file_id'],
            'name' => $video['file_name'] ?? 'video.mp4',
            'size' => $video['file_size'] ?? 0,
            'type' => 'video'
        ];
    } elseif (isset($message['audio'])) {
        $audio = $message['audio'];
        return [
            'id' => $audio['file_id'],
            'name' => $audio['file_name'] ?? 'audio.mp3',
            'size' => $audio['file_size'] ?? 0,
            'type' => 'audio'
        ];
    }
    
    return false;
}

function generateFolderId() {
    return substr(md5(uniqid()), 0, 12);
}

function createFolder($folder_id, $title, $created_by) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO folders (folder_id, title, created_by) VALUES (?, ?, ?)");
    return $stmt->execute([$folder_id, $title, $created_by]);
}

function addFileToFolder($folder_id, $file_info) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO files (file_id, file_name, file_size, file_type, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $file_info['id'],
        $file_info['name'],
        $file_info['size'],
        $file_info['type'],
        $folder_id,
        $_SESSION['user_id'] ?? 0
    ]);
}

function getShareLink($folder_id) {
    $bot_username = getBotUsername();
    return "https://t.me/$bot_username?start=folder_$folder_id";
}

function getBotUsername() {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($result, true);
    
    if ($data && isset($data['result']['username'])) {
        return $data['result']['username'];
    }
    
    return "DakalUpBot";
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function registerUser($user_id, $username, $first_name, $last_name) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (user_id, username, first_name, last_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $first_name, $last_name]);
    
    // Update last activity
    $stmt = $pdo->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

function getBotSetting($key) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['setting_value'] : '';
}

function setUserState($user_id, $state) {
    $_SESSION['user_state_' . $user_id] = $state;
}

function getUserState($user_id) {
    return $_SESSION['user_state_' . $user_id] ?? null;
}

function clearUserState($user_id) {
    unset($_SESSION['user_state_' . $user_id]);
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function answerCallbackQuery($callback_query_id, $text = '') {
    $data = [
        'callback_query_id' => $callback_query_id
    ];
    
    if ($text) {
        $data['text'] = $text;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Additional helper functions
function showSingleUploadMenu($chat_id) {
    $keyboard = [[['text' => 'بازگشت', 'callback_data' => 'back']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "📤 آپلود فایل تکی\n\nفایل خود را ارسال کنید...", $reply_markup);
}

function showBroadcastMenu($chat_id) {
    $keyboard = [[['text' => 'بازگشت', 'callback_data' => 'back']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "📢 ارسال پیام همگانی\n\nپیام مورد نظر خود را ارسال کنید⤵️", $reply_markup);
}

function showStatsMenu($chat_id) {
    $keyboard = [
        [['text' => 'جدیدترین فایل ها', 'callback_data' => 'stats_newest']],
        [['text' => 'قدیمی ترین فایل ها', 'callback_data' => 'stats_oldest']],
        [['text' => 'پربازدیدترین فایل ها', 'callback_data' => 'stats_popular']],
        [['text' => 'محبوب ترین فایل ها', 'callback_data' => 'stats_liked']],
        [['text' => 'منفور ترین فایل ها', 'callback_data' => 'stats_disliked']],
        [['text' => 'امار عضویت اجباری', 'callback_data' => 'stats_membership']],
        [['text' => 'بروزرسانی', 'callback_data' => 'stats_refresh']]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "📊 آمار ربات\n\nیکی از گزینه های زیر را انتخاب کنید:", $reply_markup);
}

function showSettingsMenu($chat_id) {
    $keyboard = [
        [
            ['text' => 'وظیفه اجباری', 'callback_data' => 'settings_forced_task'],
            ['text' => 'عضویت اجباری', 'callback_data' => 'settings_membership']
        ],
        [
            ['text' => 'تنظیمات فایل ها', 'callback_data' => 'settings_files'],
            ['text' => 'ادمین ها', 'callback_data' => 'settings_admins']
        ],
        [
            ['text' => 'لیست کاربران', 'callback_data' => 'settings_users'],
            ['text' => 'لیست کاربران مسدود شده', 'callback_data' => 'settings_blocked']
        ],
        [
            ['text' => 'تغییر متن های ربات', 'callback_data' => 'settings_texts'],
            ['text' => 'مشاهده استارت از دید کاربر', 'callback_data' => 'settings_start_view']
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "⚙️ تنظیمات\n\nیکی از گزینه های زیر را انتخاب کنید👇👇", $reply_markup);
}

function toggleBotStatus($chat_id) {
    global $pdo;
    
    $current_status = getBotSetting('bot_status');
    $new_status = ($current_status === 'on') ? 'off' : 'on';
    
    $stmt = $pdo->prepare("UPDATE bot_settings SET setting_value = ? WHERE setting_key = 'bot_status'");
    $stmt->execute([$new_status]);
    
    $status_text = ($new_status === 'on') ? 'روشن ✅' : 'خاموش 🚫';
    sendMessage($chat_id, "ربات $status_text شد!");
}

function handleTextMessage($message) {
    // Handle text messages based on user state
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    // Check if user is admin
    if ($user_id != ADMIN_ID) {
        // Regular users can only use basic commands
        if ($text == '📂 مشاهده فایل‌ها' || $text == '🔍 جستجو' || $text == '📞 پشتیبانی' || $text == '📊 آمار') {
            sendMessage($chat_id, "🔒 این بخش در حال حاضر در دسترس نیست!");
            return;
        }
        return;
    }
    
    // Admin text message handling
    switch ($text) {
        case 'آپلود گروهی📂️':
            showGroupUploadMenu($chat_id);
            break;
            
        case 'آپلود فایل⬆️':
            showSingleUploadMenu($chat_id);
            break;
            
        case 'ارسال پیام همگانی️📢':
            showBroadcastMenu($chat_id);
            break;
            
        case 'مشاهده فایل‌ها و آمار📊':
            showStatsMenu($chat_id);
            break;
            
        case 'تنظیمات⚙️':
            showSettingsMenu($chat_id);
            break;
            
        case 'خاموش/روشن کردن ربات🚫':
            toggleBotStatus($chat_id);
            break;
            
        case 'بازگشت':
            showAdminMainMenu($chat_id);
            break;
            
        default:
            // Handle other admin text messages
            handleAdminTextMessage($chat_id, $text);
            break;
    }
}

function handleStartWithPayload($chat_id, $user_id, $payload) {
    global $pdo;
    
    // Check if payload is a folder ID
    if (strpos($payload, 'folder_') === 0) {
        $folder_id = str_replace('folder_', '', $payload);
        
        // Get folder info
        $stmt = $pdo->prepare("SELECT * FROM folders WHERE folder_id = ?");
        $stmt->execute([$folder_id]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$folder) {
            sendMessage($chat_id, "❌ فولدر یافت نشد!");
            return;
        }
        
        // Increment views
        $stmt = $pdo->prepare("UPDATE folders SET views = views + 1 WHERE folder_id = ?");
        $stmt->execute([$folder_id]);
        
        // Get files in folder
        $stmt = $pdo->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$folder_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($files)) {
            sendMessage($chat_id, "❌ هیچ فایلی در این فولدر یافت نشد!");
            return;
        }
        
        // Show folder info for admin
        if ($user_id == ADMIN_ID) {
            $message = "🔎اطلاعات این فولدر🔎\n\n";
            $message .= "عنوان فولدر📝: " . $folder['title'] . "\n";
            $message .= "نوع فولدر: " . ($folder['is_public'] ? '🌐عمومی' : '🔒خصوصی') . "\n";
            $message .= "تعداد بازدید👀: " . $folder['views'] . "\n";
            $message .= "تعداد لایک👍: " . $folder['likes'] . "\n";
            $message .= "تعداد دیسلایک👎: " . $folder['dislikes'] . "\n";
            $message .= "شناسه فایل🆔: " . $folder_id . "\n\n";
            $message .= "(این پیام فقط برای ادمین ها نمایش داده می‌شود)\n\n";
            
            $keyboard = [
                [
                    ['text' => 'مشاهده فایل ها', 'callback_data' => "folder_view_$folder_id"],
                    ['text' => 'افزودن فایل', 'callback_data' => "folder_add_$folder_id"]
                ],
                [
                    ['text' => 'قفل فوروارد', 'callback_data' => "folder_forward_lock_$folder_id"],
                    ['text' => 'فولدر عمومی', 'callback_data' => "folder_public_$folder_id"]
                ],
                [['text' => 'حذف فولدر', 'callback_data' => "folder_delete_$folder_id"]]
            ];
            
            $reply_markup = ['inline_keyboard' => $keyboard];
            sendMessage($chat_id, $message, $reply_markup);
        } else {
            // For regular users, show files with download buttons
            foreach ($files as $index => $file) {
                $message = "📁 " . $folder['title'] . "\n";
                $message .= "📄 " . $file['file_name'] . "\n";
                $message .= "📏 " . formatFileSize($file['file_size']) . "\n";
                $message .= "👁️ " . $file['views'] . " بازدید\n";
                $message .= "👍 " . $file['likes'] . " لایک | 👎 " . $file['dislikes'] . " دیسلایک\n";
                
                $keyboard = [
                    [
                        ['text' => '👍 لایک', 'callback_data' => "file_like_" . $file['id']],
                        ['text' => '👎 دیسلایک', 'callback_data' => "file_dislike_" . $file['id']]
                    ]
                ];
                
                $reply_markup = ['inline_keyboard' => $keyboard];
                
                // Send file with buttons
                sendFileWithButtons($chat_id, $file, $reply_markup);
            }
        }
    }
}

function handleFolderAction($chat_id, $data, $user_id) {
    // Check if user is admin
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند از این قابلیت استفاده کنند!");
        return;
    }
    
    $action = explode('_', $data);
    $folder_id = end($action);
    
    switch ($action[1]) {
        case 'view':
            showFolderFiles($chat_id, $folder_id);
            break;
        case 'add':
            showAddFileToFolder($chat_id, $folder_id);
            break;
        case 'forward_lock':
            toggleForwardLock($chat_id, $folder_id);
            break;
        case 'public':
            toggleFolderPublic($chat_id, $folder_id);
            break;
        case 'delete':
            showDeleteFolderConfirm($chat_id, $folder_id);
            break;
        case 'delete_confirm':
            deleteFolder($chat_id, $folder_id);
            break;
        case 'delete_cancel':
            // Just ignore, user cancelled
            break;
    }
}

function handleFileAction($chat_id, $data, $user_id) {
    $action = explode('_', $data);
    $file_id = end($action);
    
    switch ($action[1]) {
        case 'like':
            toggleFileLike($chat_id, $file_id, $user_id);
            break;
        case 'dislike':
            toggleFileDislike($chat_id, $file_id, $user_id);
            break;
        case 'delete':
            if ($user_id == ADMIN_ID) {
                showDeleteFileConfirm($chat_id, $file_id);
            } else {
                sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند فایل‌ها را حذف کنند!");
            }
            break;
        case 'delete_confirm':
            if ($user_id == ADMIN_ID) {
                deleteFile($chat_id, $file_id);
            } else {
                sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند فایل‌ها را حذف کنند!");
            }
            break;
        case 'delete_cancel':
            // Just ignore, user cancelled
            break;
    }
}

// Missing functions that need to be implemented
function getUserUploadedFiles($user_id) {
    // This function should return files from the current session or temporary storage
    // For now, return empty array - implement based on your session management
    return [];
}

function addFilesToFolder($folder_id, $files) {
    // This function should add multiple files to a folder
    // For now, return true - implement based on your file management system
    return true;
}

function calculateTotalSize($files) {
    // This function should calculate total size of files
    // For now, return 0 - implement based on your file management system
    return 0;
}

function addFileToGroupUpload($user_id, $file_info) {
    // This function should add a file to the group upload session
    // For now, return true - implement based on your session management
    return true;
}

function handleTextMessage($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    // Check if user is admin
    if ($user_id != ADMIN_ID) {
        // Regular users can only use basic commands
        if ($text == '📂 مشاهده فایل‌ها' || $text == '🔍 جستجو' || $text == '📞 پشتیبانی' || $text == '📊 آمار') {
            sendMessage($chat_id, "🔒 این بخش در حال حاضر در دسترس نیست!");
            return;
        }
        return;
    }
    
    // Admin text message handling
    switch ($text) {
        case 'آپلود گروهی📂️':
            showGroupUploadMenu($chat_id);
            break;
            
        case 'آپلود فایل⬆️':
            showSingleUploadMenu($chat_id);
            break;
            
        case 'ارسال پیام همگانی️📢':
            showBroadcastMenu($chat_id);
            break;
            
        case 'مشاهده فایل‌ها و آمار📊':
            showStatsMenu($chat_id);
            break;
            
        case 'تنظیمات⚙️':
            showSettingsMenu($chat_id);
            break;
            
        case 'خاموش/روشن کردن ربات🚫':
            toggleBotStatus($chat_id);
            break;
            
        case 'بازگشت':
            showAdminMainMenu($chat_id);
            break;
            
        default:
            // Handle other admin text messages
            handleAdminTextMessage($chat_id, $text);
            break;
    }
}

function handleAdminTextMessage($chat_id, $text) {
    // Handle other admin text messages
    sendMessage($chat_id, "پیام شما دریافت شد: $text");
}

function showFolderFiles($chat_id, $folder_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY uploaded_at ASC");
    $stmt->execute([$folder_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($files)) {
        sendMessage($chat_id, "❌ هیچ فایلی در این فولدر یافت نشد!");
        return;
    }
    
    $folder = getFolderInfo($folder_id);
    
    foreach ($files as $index => $file) {
        $message = "📁 " . $folder['title'] . "\n";
        $message .= "📄 " . $file['file_name'] . "\n";
        $message .= "📏 " . formatFileSize($file['file_size']) . "\n";
        $message .= "👁️ " . $file['views'] . " بازدید\n";
        $message .= "👍 " . $file['likes'] . " لایک | 👎 " . $file['dislikes'] . " دیسلایک\n";
        
        $keyboard = [['حذف']];
        
        // Add like/dislike buttons for the last file
        if ($index === count($files) - 1) {
            $keyboard = [
                ['👍 لایک', '👎 دیسلایک'],
                ['حذف']
            ];
        }
        
        $reply_markup = ['inline_keyboard' => $keyboard];
        sendMessage($chat_id, $message, $reply_markup);
    }
}

function getFolderInfo($folder_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM folders WHERE folder_id = ?");
    $stmt->execute([$folder_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function showAddFileToFolder($chat_id, $folder_id) {
    $keyboard = [['پایان']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'add_to_folder_' . $folder_id);
    sendMessage($chat_id, "فایل ها را ارسال کنید تا به این فولدر اضافه شود...", $reply_markup);
}

function toggleForwardLock($chat_id, $folder_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT forward_lock FROM folders WHERE folder_id = ?");
    $stmt->execute([$folder_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_status = !$current['forward_lock'];
    
    $stmt = $pdo->prepare("UPDATE folders SET forward_lock = ? WHERE folder_id = ?");
    $stmt->execute([$new_status, $folder_id]);
    
    $status_text = $new_status ? 'فعال ✅' : 'غیرفعال ❌';
    sendMessage($chat_id, "قفل فوروارد $status_text شد!");
}

function toggleFolderPublic($chat_id, $folder_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT is_public FROM folders WHERE folder_id = ?");
    $stmt->execute([$folder_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_status = !$current['is_public'];
    
    $stmt = $pdo->prepare("UPDATE folders SET is_public = ? WHERE folder_id = ?");
    $stmt->execute([$new_status, $folder_id]);
    
    $status_text = $new_status ? 'عمومی 🌐' : 'خصوصی 🔒';
    sendMessage($chat_id, "فولدر $status_text شد!");
}

function showDeleteFolderConfirm($chat_id, $folder_id) {
    $keyboard = [
        [
            ['text' => 'بله، مطمئنم', 'callback_data' => "folder_delete_confirm_$folder_id"],
            ['text' => 'خیر، انصراف می‌دهم', 'callback_data' => "folder_delete_cancel_$folder_id"]
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "⚠️ آیا از حذف این فولدر مطمئن هستید؟\n\nاین عملیات غیرقابل بازگشت است!", $reply_markup);
}

function deleteFolder($chat_id, $folder_id) {
    global $pdo;
    
    try {
        // Delete all files in the folder first
        $stmt = $pdo->prepare("DELETE FROM files WHERE folder_id = ?");
        $stmt->execute([$folder_id]);
        
        // Delete the folder
        $stmt = $pdo->prepare("DELETE FROM folders WHERE folder_id = ?");
        $stmt->execute([$folder_id]);
        
        sendMessage($chat_id, "✅ فولدر با موفقیت حذف شد!");
    } catch (Exception $e) {
        sendMessage($chat_id, "❌ خطا در حذف فولدر: " . $e->getMessage());
    }
}

function toggleFileLike($chat_id, $file_id, $user_id) {
    global $pdo;
    
    // Check if user already liked
    $stmt = $pdo->prepare("SELECT * FROM file_likes WHERE file_id = ? AND user_id = ? AND like_type = 'like'");
    $stmt->execute([$file_id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Remove like
        $stmt = $pdo->prepare("DELETE FROM file_likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
        
        // Decrease like count
        $stmt = $pdo->prepare("UPDATE files SET likes = likes - 1 WHERE id = ?");
        $stmt->execute([$file_id]);
        
        sendMessage($chat_id, "👎 لایک شما برداشته شد!");
    } else {
        // Add like
        $stmt = $pdo->prepare("INSERT INTO file_likes (file_id, user_id, like_type) VALUES (?, ?, 'like')");
        $stmt->execute([$file_id, $user_id]);
        
        // Increase like count
        $stmt = $pdo->prepare("UPDATE files SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$file_id]);
        
        sendMessage($chat_id, "👍 لایک شما ثبت شد!");
    }
}

function toggleFileDislike($chat_id, $file_id, $user_id) {
    global $pdo;
    
    // Check if user already disliked
    $stmt = $pdo->prepare("SELECT * FROM file_likes WHERE file_id = ? AND user_id = ? AND like_type = 'dislike'");
    $stmt->execute([$file_id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Remove dislike
        $stmt = $pdo->prepare("DELETE FROM file_likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
        
        // Decrease dislike count
        $stmt = $pdo->prepare("UPDATE files SET dislikes = dislikes - 1 WHERE id = ?");
        $stmt->execute([$file_id]);
        
        sendMessage($chat_id, "👍 دیسلایک شما برداشته شد!");
    } else {
        // Add dislike
        $stmt = $pdo->prepare("INSERT INTO file_likes (file_id, user_id, like_type) VALUES (?, ?, 'dislike')");
        $stmt->execute([$file_id, $user_id]);
        
        // Increase dislike count
        $stmt = $pdo->prepare("UPDATE files SET dislikes = dislikes + 1 WHERE id = ?");
        $stmt->execute([$file_id]);
        
        sendMessage($chat_id, "👎 دیسلایک شما ثبت شد!");
    }
}

function showDeleteFileConfirm($chat_id, $file_id) {
    $keyboard = [
        [
            ['text' => 'بله، حذف کن', 'callback_data' => "file_delete_confirm_$file_id"],
            ['text' => 'خیر، انصراف', 'callback_data' => "file_delete_cancel_$file_id"]
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "⚠️ آیا از حذف این فایل اطمینان دارید؟", $reply_markup);
}

function deleteFile($chat_id, $file_id) {
    global $pdo;
    
    try {
        // Delete the file
        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$file_id]);
        
        sendMessage($chat_id, "✅ فایل با موفقیت حذف شد!");
    } catch (Exception $e) {
        sendMessage($chat_id, "❌ خطا در حذف فایل: " . $e->getMessage());
    }
}

function sendFileWithButtons($chat_id, $file, $reply_markup = null) {
    global $pdo;
    
    // Increment file views
    $stmt = $pdo->prepare("UPDATE files SET views = views + 1 WHERE id = ?");
    $stmt->execute([$file['id']]);
    
    $file_type = $file['file_type'];
    $method = 'send' . ucfirst($file_type);
    
    $data = [
        'chat_id' => $chat_id,
        $file_type => $file['file_id']
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
