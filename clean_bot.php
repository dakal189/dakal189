<?php
// Telegram Uploader Bot - ربات اپلودر تلگرام
// Clean version without duplicate functions

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
    
    // Forced membership channels
    $pdo->exec("CREATE TABLE IF NOT EXISTS forced_membership (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id VARCHAR(255),
        channel_name VARCHAR(255),
        channel_link VARCHAR(255),
        membership_limit INT DEFAULT -1,
        expiry_days INT DEFAULT -1,
        check_membership BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // File likes/dislikes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT,
        user_id BIGINT,
        like_type ENUM('like', 'dislike'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (file_id, user_id, like_type)
    )");
    
    // User sessions table for temporary storage
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        session_key VARCHAR(100),
        session_value TEXT,
        expires_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
        sendMessage($chat_id, "ربات خاموش شده از طرف مدیریت 🚫");
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
    sendMessage($chat_id, "📁 آپلود گروهی\n\nفایل‌های خود را ارسال کنید تا به گروه اضافه شوند...\n\nبرای اتمام عملیات بر روی پایان کلیک کنید یا فایل جدیدی ارسال کنید.👇", $reply_markup);
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

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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
    // This function should handle text messages based on user state
    // For now, do nothing - implement based on your requirements
    return;
}

function handleFolderAction($chat_id, $data, $user_id) {
    // This function should handle folder-related actions
    // For now, do nothing - implement based on your requirements
    return;
}

function handleFileAction($chat_id, $data, $user_id) {
    // This function should handle file-related actions
    // For now, do nothing - implement based on your requirements
    return;
}
?>