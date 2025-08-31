<?php
// Telegram Uploader Bot - ربات اپلودر تلگرام
// Created with comprehensive features

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
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if they don't exist
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
        is_active BOOLEAN DEFAULT TRUE,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
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
    )");
    
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
    )");
    
    // Bot settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default settings
    $defaultSettings = [
        'bot_status' => 'on',
        'auto_delete_timer' => '0',
        'file_password' => '',
        'forced_task_message' => 'برای دریافت فایل، ابتدا وظیفه مورد نظر را انجام دهید.',
        'forced_task_timer' => '3600',
        'start_message' => 'سلام! به ربات اپلودر خوش آمدید.',
        'membership_message' => 'برای دریافت فایل مورد نظر باید در کانال های زیر عضو شوید👇',
        'post_file_message' => '',
        'caption_signature' => '',
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
    
    // Handle text messages based on user state
    handleTextMessage($message);
}

function handleCommand($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    switch ($text) {
        case '/start':
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
        [
            ['text' => 'آپلود گروهی📂️', 'callback_data' => 'group_upload'],
            ['text' => 'آپلود فایل⬆️', 'callback_data' => 'single_upload']
        ],
        [
            ['text' => 'ارسال پیام همگانی️📢', 'callback_data' => 'broadcast'],
            ['text' => 'مشاهده فایل‌ها و آمار📊', 'callback_data' => 'stats']
        ],
        [
            ['text' => 'تنظیمات⚙️', 'callback_data' => 'settings'],
            ['text' => 'خاموش/روشن کردن ربات🚫', 'callback_data' => 'toggle_bot']
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "🎯 منوی اصلی ادمین\n\nیکی از گزینه های زیر را انتخاب کنید:", $reply_markup);
}

function showUserMainMenu($chat_id) {
    $keyboard = [
        [['text' => '📂 مشاهده فایل‌ها', 'callback_data' => 'view_files']],
        [['text' => '🔍 جستجو', 'callback_data' => 'search_files']],
        [['text' => '📞 پشتیبانی', 'callback_data' => 'support']]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
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
        case 'group_upload':
            showGroupUploadMenu($chat_id);
            break;
            
        case 'single_upload':
            showSingleUploadMenu($chat_id);
            break;
            
        case 'broadcast':
            showBroadcastMenu($chat_id);
            break;
            
        case 'stats':
            showStatsMenu($chat_id);
            break;
            
        case 'settings':
            showSettingsMenu($chat_id);
            break;
            
        case 'toggle_bot':
            toggleBotStatus($chat_id);
            break;
            
        case 'group_upload_start':
            startGroupUpload($chat_id, $user_id);
            break;
            
        case 'group_upload_finish':
            finishGroupUpload($chat_id, $user_id);
            break;
            
        case 'group_upload_back':
            showGroupUploadMenu($chat_id);
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
    sendMessage($chat_id, "📁 آپلود گروهی\n\nفایل‌های خود را ارسال کنید تا به گروه اضافه شوند...\n\nبرای اتمام عملیات بر روی پایان کلیک کنید یا فایل جدیدی ارسال کنید.��", $reply_markup);
}

function startGroupUpload($chat_id, $user_id) {
    // Set user state to group upload mode
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
    return "https://t.me/" . str_replace('bot', '', BOT_TOKEN) . "?start=folder_$folder_id";
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
    // In a real implementation, you'd store this in database or session
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
    
    // This function will handle various text inputs based on user state
    // Implementation depends on the specific features you want
}

function handleFolderAction($chat_id, $data, $user_id) {
    // Handle folder-related actions
    $action = explode('_', $data);
    $folder_id = end($action);
    
    switch ($action[1]) {
        case 'view':
            showFolderFiles($chat_id, $folder_id);
            break;
        case 'add':
            showAddFileToFolder($chat_id, $folder_id);
            break;
        case 'forward':
            toggleForwardLock($chat_id, $folder_id);
            break;
        case 'public':
            toggleFolderPublic($chat_id, $folder_id);
            break;
        case 'delete':
            showDeleteFolderConfirm($chat_id, $folder_id);
            break;
    }
}

function handleFileAction($chat_id, $data, $user_id) {
    // Handle file-related actions
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
            showDeleteFileConfirm($chat_id, $file_id);
            break;
    }
}

// Placeholder functions for features to be implemented
function getUserUploadedFiles($user_id) { return []; }
function addFilesToFolder($folder_id, $files) { return true; }
function calculateTotalSize($files) { return 0; }
function addFileToGroupUpload($user_id, $file_info) { return true; }
function showFolderFiles($chat_id, $folder_id) { return true; }
function showAddFileToFolder($chat_id, $folder_id) { return true; }
function toggleForwardLock($chat_id, $folder_id) { return true; }
function toggleFolderPublic($chat_id, $folder_id) { return true; }
function showDeleteFolderConfirm($chat_id, $folder_id) { return true; }
function toggleFileLike($chat_id, $file_id, $user_id) { return true; }
function toggleFileDislike($chat_id, $file_id, $user_id) { return true; }
function showDeleteFileConfirm($chat_id, $file_id) { return true; }

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
