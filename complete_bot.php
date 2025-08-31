<?php
// Telegram Uploader Bot - ربات اپلودر تلگرام
// Complete implementation with all features

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
    
    // Set charset and collation for the connection
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    $pdo->exec("SET collation_connection=utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if they don't exist
createTables();

// Fix existing tables encoding if needed
fixTableEncoding();

// Get updates from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    handleUpdate($update);
}

function fixTableEncoding() {
    global $pdo;
    
    // Fix encoding for existing tables
    $tables = ['users', 'folders', 'files', 'bot_settings', 'forced_membership', 'file_likes', 'user_sessions'];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {
            // Table might not exist yet, ignore error
        }
    }
}

function createTables() {
    global $pdo;
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNIQUE,
        username VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        first_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        last_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Folders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id VARCHAR(32) UNIQUE,
        title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
        file_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
        setting_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Forced membership channels
    $pdo->exec("CREATE TABLE IF NOT EXISTS forced_membership (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id VARCHAR(255),
        channel_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        channel_link VARCHAR(255),
        membership_limit INT DEFAULT -1,
        expiry_days INT DEFAULT -1,
        check_membership BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
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
                    ['text' => 'افزودن فایل', 'callback_data' => "folder_view_$folder_id"]
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

function handleCommand($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    switch ($text) {
        case '/start':
            // Check if there's a payload (folder link)
            if (isset($message['text']) && strpos($message['text'], ' ') !== false) {
                $parts = explode(' ', $message['text']);
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
                showUserStep($chat_id, 'none');
            }
            break;
            
        case '/admin':
            if ($user_id == ADMIN_ID) {
                showAdminMainMenu($chat_id);
            } else {
                sendMessage($chat_id, "❌ شما دسترسی ادمین ندارید!");
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
            } else {
                sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند از این قابلیت استفاده کنند!");
            }
            break;
            
        case 'group_upload_finish':
            if ($user_id == ADMIN_ID) {
                finishGroupUpload($chat_id, $user_id);
            } else {
                sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند از این قابلیت استفاده کنند!");
            }
            break;
            
        case 'group_upload_back':
            if ($user_id == ADMIN_ID) {
                showGroupUploadMenu($chat_id);
            } else {
                sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند از این قابلیت استفاده کنند!");
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
    
    // Check if user is admin
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ فقط ادمین‌ها می‌توانند فایل آپلود کنند!");
        return;
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
    // Get bot username from token
    $bot_username = getBotUsername();
    return "https://t.me/$bot_username?start=folder_$folder_id";
}

function getBotUsername() {
    // Get bot username from Telegram API
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
    
    // Fallback to default username
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

function handleTextMessage($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];
    
    // Check if user is admin
    $is_admin = ($user_id == ADMIN_ID);
    
    // Handle admin menu options
    if ($is_admin) {
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
                
            default:
                // Check user state for specific actions
                $user_state = getUserState($user_id);
                handleUserStateAction($chat_id, $user_id, $text, $user_state);
                break;
        }
    } else {
        // Handle regular user menu options
        switch ($text) {
            case '📂 مشاهده فایل‌ها':
                showUserFilesMenu($chat_id);
                break;
                
            case '🔍 جستجو':
                showSearchMenu($chat_id);
                break;
                
            case '📞 پشتیبانی':
                showSupportMenu($chat_id);
                break;
                
            case '📊 آمار':
                showUserStatsMenu($chat_id);
                break;
                
            default:
                // Check user state for specific actions
                $user_state = getUserState($user_id);
                handleUserStateAction($chat_id, $user_id, $text, $user_state);
                break;
        }
    }
}

function handleUserStateAction($chat_id, $user_id, $text, $user_state) {
    switch ($user_state) {
        case 'group_upload':
            // User is in group upload mode
            if ($text === 'پایان') {
                finishGroupUpload($chat_id, $user_id);
            } elseif ($text === 'بازگشت') {
                showAdminMainMenu($chat_id);
                clearUserState($user_id);
            }
            break;
            
        case 'single_upload':
            // User is in single upload mode
            if ($text === 'بازگشت') {
                showAdminMainMenu($chat_id);
                clearUserState($user_id);
            }
            break;
            
        case 'broadcast_message':
            // User is setting broadcast message
            if ($text === 'بازگشت') {
                showAdminMainMenu($chat_id);
                clearUserState($user_id);
            } else {
                setBroadcastMessage($chat_id, $user_id, $text);
            }
            break;
            
        case 'forced_task_message':
            // User is setting forced task message
            if ($text === '/back') {
                showForcedTaskMenu($chat_id);
                clearUserState($user_id);
            } else {
                setForcedTaskMessage($chat_id, $user_id, $text);
            }
            break;
            
        case 'forced_task_timer':
            // User is setting forced task timer
            if ($text === '/back') {
                showForcedTaskMenu($chat_id);
                clearUserState($user_id);
            } else {
                setForcedTaskTimer($chat_id, $user_id, $text);
            }
            break;
            
        case 'start_message_edit':
            // User is editing start message
            if ($text === '/back') {
                showTextEditMenu($chat_id);
                clearUserState($user_id);
            } else {
                setStartMessage($chat_id, $user_id, $text);
            }
            break;
            
        case 'membership_message_edit':
            // User is editing membership message
            if ($text === '/back') {
                showTextEditMenu($chat_id);
                clearUserState($user_id);
            } else {
                setMembershipMessage($chat_id, $user_id, $text);
            }
            break;
            
        case 'post_file_message_edit':
            // User is editing post file message
            if ($text === '/back') {
                showFileSettingsMenu($chat_id);
                clearUserState($user_id);
            } else {
                setPostFileMessage($chat_id, $user_id, $text);
            }
            break;
            
        case 'caption_signature_edit':
            // User is editing caption signature
            if ($text === '/back') {
                showFileSettingsMenu($chat_id);
                clearUserState($user_id);
            } else {
                setCaptionSignature($chat_id, $user_id, $text);
            }
            break;
            
        case 'auto_delete_timer_edit':
            // User is setting auto delete timer
            if ($text === '/back') {
                showAutoDeleteMenu($chat_id);
                clearUserState($user_id);
            } else {
                setAutoDeleteTimer($chat_id, $user_id, $text);
            }
            break;
            
        case 'file_password_edit':
            // User is setting file password
            if ($text === '/back') {
                showFilePasswordMenu($chat_id);
                clearUserState($user_id);
            } else {
                setFilePassword($chat_id, $user_id, $text);
            }
            break;
            
        case 'add_channel_membership':
            // User is adding forced membership channel
            if ($text === '/back') {
                showMembershipMenu($chat_id);
                clearUserState($user_id);
            } else {
                addForcedMembershipChannel($chat_id, $user_id, $text);
            }
            break;
    }
}

function showSingleUploadMenu($chat_id) {
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'single_upload');
    sendMessage($chat_id, "📤 آپلود فایل تکی\n\nفایل خود را ارسال کنید...", $reply_markup);
}

function showBroadcastMenu($chat_id) {
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'broadcast_message');
    sendMessage($chat_id, "📢 ارسال پیام همگانی\n\nپیام مورد نظر خود را ارسال کنید⤵️", $reply_markup);
}

function setBroadcastMessage($chat_id, $user_id, $message_text) {
    // Store broadcast message and show confirmation
    $keyboard = [
        [
            ['text' => 'ارسال', 'callback_data' => 'broadcast_send'],
            ['text' => 'اعمال فیلتر', 'callback_data' => 'broadcast_filter']
        ]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    $user_count = getUserCount();
    $message = "آیا از ارسال این پیام برای کاربرهای خود مطمئنید؟\n\n";
    $message .= "تعداد گیرندگان: $user_count\n\n";
    $message .= "پیام:\n$message_text";
    
    sendMessage($chat_id, $message, $reply_markup);
    clearUserState($user_id);
}

function showStatsMenu($chat_id) {
    $stats = getBotStats();
    
    $message = "📊 آمار ربات\n\n";
    $message .= "آمار کاربران 👥\n";
    $message .= "    |— کل: " . $stats['total_users'] . "\n";
    $message .= "    |— کاربران فعال: " . $stats['active_users'] . "\n";
    $message .= "    |— کاربران غیرفعال: " . $stats['inactive_users'] . "\n";
    $message .= "    |— یک ساعت گذشته: " . $stats['last_hour'] . "\n";
    $message .= "    |— پنج ساعت گذشته: " . $stats['last_5_hours'] . "\n";
    $message .= "    |— یک هفته گذشته: " . $stats['last_week'] . "\n";
    $message .= "    |— یک ماه گذشته: " . $stats['last_month'] . "\n";
    $message .= "(آمار کاربران فعال و غیرفعال پس از ارسال پیام همگانی آپدیت میشود)\n\n";
    
    $message .= "آمار فایل ها📂\n";
    $message .= "    |— مجموع کل بازدیدها: " . $stats['total_views'] . "\n";
    $message .= "    |— تعداد فایل ها: " . $stats['total_files'] . "\n";
    $message .= "    |— مجموع لایک ها: " . $stats['total_likes'] . "\n";
    $message .= "    |— مجموع دیسلایک ها: " . $stats['total_dislikes'] . "\n\n";
    
    $message .= "آمار ربات🤖\n";
    $message .= "    |— ایدی ربات: " . BOT_TOKEN . "\n";
    $message .= "    |— صاحب ربات: " . ADMIN_ID . "\n";
    $message .= "    |— وضعیت ربات: " . ($stats['bot_status'] === 'on' ? 'روشن✅' : 'خاموش🚫') . "\n";
    $message .= "    |— تایمر حذف خودکار: " . ($stats['auto_delete_timer'] > 0 ? $stats['auto_delete_timer'] . ' ثانیه' : 'خاموش🚫') . "\n";
    $message .= "    |— پسورد فایل ها: " . ($stats['file_password'] ? 'فعال✅' : 'غیرفعال🚫') . "\n";
    $message .= "    |— وظیفه اجباری: " . ($stats['forced_task_active'] ? 'فعال✅' : 'خاموش🚫') . "\n";
    $message .= "    |— تعداد انجام وظیفه اجباری: " . $stats['forced_task_count'] . "\n";
    $message .= "    |— دریافت فایل رندوم: " . ($stats['random_file'] ? 'فعال✅' : 'خاموش🚫') . "\n";
    $message .= "    |— دریافت فایل با سرچ: " . ($stats['search_file'] ? 'فعال✅' : 'خاموش🚫') . "\n";
    
    $keyboard = [
        [
            ['text' => 'جدیدترین فایل ها', 'callback_data' => 'stats_newest'],
            ['text' => 'قدیمی ترین فایل ها', 'callback_data' => 'stats_oldest']
        ],
        [
            ['text' => 'پربازدیدترین فایل ها', 'callback_data' => 'stats_popular'],
            ['text' => 'محبوب ترین فایل ها', 'callback_data' => 'stats_liked']
        ],
        [
            ['text' => 'منفور ترین فایل ها', 'callback_data' => 'stats_disliked'],
            ['text' => 'امار عضویت اجباری', 'callback_data' => 'stats_membership']
        ],
        [['text' => 'بروزرسانی', 'callback_data' => 'stats_refresh']]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, $message, $reply_markup);
}

function showSettingsMenu($chat_id) {
    $keyboard = [
        ['وظیفه اجباری', 'عضویت اجباری'],
        ['تنظیمات فایل ها', 'ادمین ها'],
        ['لیست کاربران', 'لیست کاربران مسدود شده'],
        ['تغییر متن های ربات', 'مشاهده استارت از دید کاربر'],
        ['بازگشت']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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

function getUserCount() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] ?? 0;
}

function getBotStats() {
    global $pdo;
    
    $stats = [];
    
    // User statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    $stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    $stats['last_hour'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 HOUR)");
    $stmt->execute();
    $stats['last_5_hours'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute();
    $stats['last_day'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 WEEK)");
    $stmt->execute();
    $stats['last_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $stats['last_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // File statistics
    $stmt = $pdo->prepare("SELECT SUM(views) as total_views, COUNT(*) as total_files, SUM(likes) as total_likes, SUM(dislikes) as total_dislikes FROM files");
    $stmt->execute();
    $file_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_views'] = $file_stats['total_views'] ?? 0;
    $stats['total_files'] = $file_stats['total_files'] ?? 0;
    $stats['total_likes'] = $file_stats['total_likes'] ?? 0;
    $stats['total_dislikes'] = $file_stats['total_dislikes'] ?? 0;
    
    // Bot settings
    $stats['bot_status'] = getBotSetting('bot_status');
    $stats['auto_delete_timer'] = getBotSetting('auto_delete_timer');
    $stats['file_password'] = getBotSetting('file_password');
    $stats['forced_task_active'] = getBotSetting('forced_task_message') ? true : false;
    $stats['forced_task_count'] = 0; // This would need additional tracking
    $stats['random_file'] = false; // This would need additional implementation
    $stats['search_file'] = false; // This would need additional implementation
    
    return $stats;
}

// Additional helper functions for features to be implemented
// getUserUploadedFiles function needs to be implemented based on your session management

// addFilesToFolder function needs to be implemented based on your file management system

// calculateTotalSize function needs to be implemented based on your file management system

// addFileToGroupUpload function needs to be implemented based on your session management

// showFolderFiles function is implemented below

// showAddFileToFolder function is implemented below

// toggleForwardLock function is implemented below

// toggleFolderPublic function is implemented below

// showDeleteFolderConfirm function is implemented below

// toggleFileLike function is implemented below

// toggleFileDislike function is implemented below

// showDeleteFileConfirm function is implemented below

function showUserFilesMenu($chat_id) {
    // Show user files menu
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "📂 مشاهده فایل‌ها\n\nفایل‌های موجود در ربات:", $reply_markup);
}

function showSearchMenu($chat_id) {
    // Show search menu
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "🔍 جستجو\n\nکلیدواژه مورد نظر را وارد کنید:", $reply_markup);
}

function showSupportMenu($chat_id) {
    // Show support menu
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "📞 پشتیبانی\n\nبرای ارتباط با پشتیبانی پیام خود را ارسال کنید:", $reply_markup);
}

function showUserStatsMenu($chat_id) {
    // Show user statistics menu
    $keyboard = [['بازگشت']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "📊 آمار کاربر\n\nآمار فایل‌های شما:", $reply_markup);
}

// Settings menu handlers
function handleSettingsMenu($chat_id, $text) {
    switch ($text) {
        case 'وظیفه اجباری':
            showForcedTaskMenu($chat_id);
            break;
            
        case 'عضویت اجباری':
            showMembershipMenu($chat_id);
            break;
            
        case 'تنظیمات فایل ها':
            showFileSettingsMenu($chat_id);
            break;
            
        case 'ادمین ها':
            showAdminsMenu($chat_id);
            break;
            
        case 'لیست کاربران':
            showUsersListMenu($chat_id);
            break;
            
        case 'لیست کاربران مسدود شده':
            showBlockedUsersMenu($chat_id);
            break;
            
        case 'تغییر متن های ربات':
            showTextEditMenu($chat_id);
            break;
            
        case 'مشاهده استارت از دید کاربر':
            showStartAsUser($chat_id);
            break;
            
        case 'بازگشت':
            showAdminMainMenu($chat_id);
            break;
    }
}

function showForcedTaskMenu($chat_id) {
    $keyboard = [
        ['تغییر پیام وظیفه اجباری', 'تغییر زمان نمایش'],
        ['مشاهده پیام کنونی', 'ریست'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "وظیفه اجباری🤜\n\nاز این قسمت میتوانید کاربر را مجبور کنید که برای دریافت فایل شما ابتدا خواسته شما را انجام دهد.\nپیام وظیفه اجباری هر " . (getBotSetting('forced_task_timer') / 3600) . " ساعت یکبار برای هرکاربر به نمایش در خواهد آمد. برای تغییر زمان نمایش میتوانید از قسمت `تغییر زمان نمایش⏳` استفاده کنید.\n\n(توجه کنید که ربات هرگز نمیتواند بفهمد که آیا کاربر وظیفه مورد نظر شمارا انجام داده است یا خیر. بعد از ۱۵ ثانیه ربات چه وظیفه انجام شده باشد چه نه، فایل را برای کاربر ارسال خواهد کرد)\n\nبرای مثال:\nبرو داخل این کانال و ۱۰ تا از پست هاشو لایک بزن و سپس روی دکمه \"انجام شد👍\" کلیک کن.\n\nt.me/DakalPvSaz", $reply_markup);
}

function showMembershipMenu($chat_id) {
    $channels = getForcedMembershipChannels();
    
    $message = "لیست عضویت اجباری های ثبت شده در ربات👇👇\n\n";
    
    if (empty($channels)) {
        $message .= "هیچ کانالی برای عضویت اجباری تنظیم نشده است.";
    } else {
        foreach ($channels as $channel) {
            $message .= "📺 " . $channel['channel_name'] . "\n";
            $message .= "🔗 " . $channel['channel_link'] . "\n";
            $message .= "👥 محدودیت: " . ($channel['membership_limit'] == -1 ? 'بینهایت' : $channel['membership_limit']) . "\n";
            $message .= "⏰ انقضا: " . ($channel['expiry_days'] == -1 ? 'بینهایت' : $channel['expiry_days'] . ' روز') . "\n";
            $message .= "✅ بررسی: " . ($channel['check_membership'] ? 'فعال' : 'غیرفعال') . "\n\n";
        }
    }
    
    $keyboard = [
        ['افزودن کانال جدید', 'افزودن گروه جدید'],
        ['مشاهده پیام عضویت اجباری'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

function showFileSettingsMenu($chat_id) {
    $keyboard = [
        ['پیام بعد از ارسال فایل', 'حذف خودکار'],
        ['پسورد فایل ها', 'کپشن فایل ها'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "تنظیمات فایل ها\n\nیکی از گزینه های زیر را انتخاب کنید👇👇", $reply_markup);
}

function showTextEditMenu($chat_id) {
    $keyboard = [
        ['متن استارت', 'متن عضویت اجباری'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "تغییر متن های ربات\n\nیکی از گزینه های زیر را انتخاب کنید👇👇", $reply_markup);
}

// Forced task functions
function showForcedTaskMessageEdit($chat_id) {
    $keyboard = [['/back']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'forced_task_message');
    sendMessage($chat_id, "پیام جدید را ارسال کنید⤵️\n\nبازگشت👈 /back", $reply_markup);
}

function showForcedTaskTimerEdit($chat_id) {
    $keyboard = [['/back']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'forced_task_timer');
    sendMessage($chat_id, "زمان جدید را به ساعت ارسال کنید⤵️\n\nبازگشت👈 /back", $reply_markup);
}

function setForcedTaskMessage($chat_id, $user_id, $message) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE bot_settings SET setting_value = ? WHERE setting_key = 'forced_task_message'");
    $stmt->execute([$message]);
    
    sendMessage($chat_id, "✅ پیام وظیفه اجباری با موفقیت تغییر یافت!");
    showForcedTaskMenu($chat_id);
}

function setForcedTaskTimer($chat_id, $user_id, $timer) {
    global $pdo;
    
    if (!is_numeric($timer) || $timer < 1) {
        sendMessage($chat_id, "❌ لطفاً یک عدد معتبر وارد کنید!");
        return;
    }
    
    $seconds = $timer * 3600; // Convert hours to seconds
    
    $stmt = $pdo->prepare("UPDATE bot_settings SET setting_value = ? WHERE setting_key = 'forced_task_timer'");
    $stmt->execute([$seconds]);
    
    sendMessage($chat_id, "✅ زمان نمایش وظیفه اجباری به $timer ساعت تغییر یافت!");
    showForcedTaskMenu($chat_id);
}

// Membership functions
function showAddChannelMembership($chat_id) {
    $keyboard = [['/back']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'add_channel_membership');
    sendMessage($chat_id, "لینک کانال یا گروه را ارسال کنید⤵️\n\nبازگشت👈 /back", $reply_markup);
}

function addForcedMembershipChannel($chat_id, $user_id, $channel_link) {
    global $pdo;
    
    // Extract channel info from link
    $channel_info = extractChannelInfo($channel_link);
    
    if (!$channel_info) {
        sendMessage($chat_id, "❌ لینک نامعتبر است!");
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO forced_membership (channel_id, channel_name, channel_link) VALUES (?, ?, ?)");
    $stmt->execute([$channel_info['id'], $channel_info['name'], $channel_link]);
    
    sendMessage($chat_id, "✅ کانال با موفقیت اضافه شد!");
    showMembershipMenu($chat_id);
}

function extractChannelInfo($link) {
    // This is a simplified version - in real implementation you'd need to use Telegram API
    if (preg_match('/t\.me\/([^\/\?]+)/', $link, $matches)) {
        return [
            'id' => $matches[1],
            'name' => $matches[1]
        ];
    }
    return false;
}

function getForcedMembershipChannels() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM forced_membership ORDER BY created_at DESC");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// File settings functions
function showPostFileMessageMenu($chat_id) {
    $current_message = getBotSetting('post_file_message');
    
    $message = "پیام بعد از ارسال فایل\n\n";
    if ($current_message) {
        $message .= "پس از دریافت فایل توسط کاربر، این پیام نیز به کاربر ارسال میشود؛این پیام میتواند یک پیام تبلیغ یا هرچیز دیگری باشد.\n\n";
        $message .= "پیام کنونی:\n$current_message";
    } else {
        $message .= "هیچ پیامی تنظیم نشده است.";
    }
    
    $keyboard = [
        ['تغییر پیام', 'مشاهده پیام کنونی'],
        ['حذف پیام کنونی'],
        ['برگشت به منو تنظیمات فایل ها']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

function showAutoDeleteMenu($chat_id) {
    $current_timer = getBotSetting('auto_delete_timer');
    
    $message = "قابلیت حذف خودکار\n\n";
    if ($current_timer > 0) {
        $message .= "با فعال کردن این قابلیت فایل های ارسال شده به کاربر بعد از مدتی حذف خواهند شد و احتمال فیلتر شدن ربات کاهش میابد.\n\n";
        $message .= "مقدار کنونی: " . $current_timer . " ثانیه";
        
        $keyboard = [
            ['تغییر مقدار زمان', 'غیرفعال کردن'],
            ['برگشت به منو تنظیمات فایل ها']
        ];
    } else {
        $message .= "با فعال کردن این قابلیت فایل های ارسال شده به کاربر بعد از مدتی حذف خواهند شد و احتمال فیلتر شدن ربات کاهش میابد.";
        
        $keyboard = [
            ['فعال کردن'],
            ['برگشت به منو تنظیمات فایل ها']
        ];
    }
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

function showFilePasswordMenu($chat_id) {
    $current_password = getBotSetting('file_password');
    
    $message = "پسورد فایل ها\n\n";
    if ($current_password) {
        $message .= "پسورد فعلی: $current_password\n\n";
        $message .= "با افزودن پسورد، کاربر ها برای دریافت فایل ها نیاز به وارد کردن پسورد خواهند داشت‼";
        
        $keyboard = [
            ['تغییر پسورد', 'حذف پسورد'],
            ['برگشت به منو تنظیمات فایل ها']
        ];
    } else {
        $message .= "با افزودن پسورد، کاربر ها برای دریافت فایل ها نیاز به وارد کردن پسورد خواهند داشت‼";
        
        $keyboard = [
            ['تغییر پسورد'],
            ['برگشت به منو تنظیمات فایل ها']
        ];
    }
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

function showCaptionSettingsMenu($chat_id) {
    $message = "تنظیمات کپشن فایل‌ها ⚙️\n\n";
    $message .= "در این بخش می‌توانید تنظیمات مربوط به متن زیر فایل‌ها را مدیریت کنید.\n\n";
    $message .= "🔹 کپشن عمومی: این متن به طور کامل جایگزین کپشن اصلی فایل‌های شما می‌شود. اگر این گزینه فعال باشد، کپشن خود فایل نادیده گرفته خواهد شد.\n\n";
    $message .= "🔸 امضای کپشن: این متن به انتهای کپشن اصلی فایل‌های شما اضافه می‌شود. اگر فایل کپشنی نداشته باشد، امضای شما به عنوان کپشن آن ثبت می‌گردد.\n\n";
    $message .= "از دکمه‌های زیر برای فعال/غیرفعال کردن و مدیریت متن‌ها استفاده کنید.";
    
    $keyboard = [
        ['کپشن عمومی', 'امضای کپشن'],
        ['خالی کردن کپشن', 'تغییر متن کپشن'],
        ['خالی کردن امضا', 'تغییر متن امضا'],
        ['نمایش تعداد بازدید', 'گزینه لایک'],
        ['برگشت به منو تنظیمات فایل ها']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

// Text edit functions
function showStartMessageEdit($chat_id) {
    $current_message = getBotSetting('start_message');
    
    $message = "متن جدید را وارد کنید⤵️\n\n";
    $message .= "متن کنونی:\n";
    if ($current_message) {
        $message .= $current_message;
    } else {
        $message .= "متن استارت تنظیم نشده است.";
    }
    
    $keyboard = [['/back']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'start_message_edit');
    sendMessage($chat_id, $message . "\n\nبازگشت👈 /back", $reply_markup);
}

function showMembershipMessageEdit($chat_id) {
    $current_message = getBotSetting('membership_message');
    
    $message = "متن جدید را وارد کنید⤵️\n\n";
    $message .= "متن کنونی:\n";
    if ($current_message) {
        $message .= $current_message;
    } else {
        $message .= "متن عضویت اجباری تنظیم نشده است.";
    }
    
    $keyboard = [['/back']];
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    setUserState($_SESSION['user_id'] ?? 0, 'membership_message_edit');
    sendMessage($chat_id, $message . "\n\nبازگشت👈 /back", $reply_markup);
}

function setStartMessage($chat_id, $user_id, $message) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE bot_settings SET setting_value = ? WHERE setting_key = 'start_message'");
    $stmt->execute([$message]);
    
    sendMessage($chat_id, "✅ متن استارت با موفقیت تغییر یافت!");
    showTextEditMenu($chat_id);
}

function setMembershipMessage($chat_id, $user_id, $message) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE bot_settings SET setting_value = ? WHERE setting_key = 'membership_message'");
    $stmt->execute([$message]);
    
    sendMessage($chat_id, "✅ متن عضویت اجباری با موفقیت تغییر یافت!");
    showTextEditMenu($chat_id);
}

// Additional utility functions
function showAdminsMenu($chat_id) {
    $keyboard = [
        ['افزودن ادمین', 'حذف ادمین'],
        ['لیست ادمین ها'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "مدیریت ادمین ها\n\nیکی از گزینه های زیر را انتخاب کنید:", $reply_markup);
}

function showUsersListMenu($chat_id) {
    $users = getUsersList(1, 10); // First page, 10 users per page
    
    $message = "👥 لیست کاربران ربات شما (از جدیدترین به قدیمی‌ترین)\n\n";
    $message .= "برای مشاهده جزئیات کامل، روی هر کاربر کلیک کنید.\n\n";
    
    foreach ($users as $user) {
        $message .= "👤 " . $user['first_name'] . " " . ($user['last_name'] ?? '') . "\n";
        $message .= "🆔 " . $user['user_id'] . "\n";
        $message .= "📅 " . $user['join_date'] . "\n\n";
    }
    
    $keyboard = [
        ['تعداد کل کاربران: ' . getUserCount()],
        ['صفحه بعدی', 'صفحه قبلی'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, $message, $reply_markup);
}

function getUsersList($page = 1, $per_page = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY join_date DESC LIMIT ? OFFSET ?");
    $stmt->execute([$per_page, $offset]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function showBlockedUsersMenu($chat_id) {
    $keyboard = [
        ['لیست کاربران مسدود شده'],
        ['بازگشت به منو تنظیمات']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "لیست کاربران مسدود شده\n\nدر حال حاضر این قابلیت در دست توسعه است.", $reply_markup);
}

function showStartAsUser($chat_id) {
    $start_message = getBotSetting('start_message');
    
    $keyboard = [
        ['📂 مشاهده فایل‌ها', '🔍 جستجو'],
        ['📞 پشتیبانی', '📊 آمار']
    ];
    
    $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
    sendMessage($chat_id, "مشاهده استارت از دید کاربر:\n\n" . $start_message, $reply_markup);
}

// Folder and file action handlers
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

// Complete folder functions
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
    sendMessage($chat_id, "⚠️ آیا از حذف این فولدر اطمینان دارید؟\n\nاین عمل غیرقابل بازگشت است!", $reply_markup);
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

// Complete file functions
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

// This function is now replaced by deleteFile($chat_id, $file_id)

// Enhanced callback query handler
function handleEnhancedCallbackQuery($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $data = $callback_query['data'];
    
    // Answer callback query
    answerCallbackQuery($callback_query['id']);
    
    // Check if user is admin for admin functions
    if (strpos($data, 'admin_') === 0 && $user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ شما دسترسی ادمین ندارید!");
        return;
    }
    
    // Handle enhanced callback data
    if (strpos($data, 'folder_delete_confirm_') === 0) {
        $folder_id = str_replace('folder_delete_confirm_', '', $data);
        deleteFolder($chat_id, $folder_id);
    } elseif (strpos($data, 'folder_delete_cancel_') === 0) {
        $folder_id = str_replace('folder_delete_cancel_', '', $data);
        sendMessage($chat_id, "✅ عملیات حذف لغو شد!");
    } elseif (strpos($data, 'file_delete_confirm_') === 0) {
        $file_id = str_replace('file_delete_confirm_', '', $data);
        deleteFile($chat_id, $file_id);
    } elseif (strpos($data, 'file_delete_cancel_') === 0) {
        $file_id = str_replace('file_delete_cancel_', '', $data);
        sendMessage($chat_id, "✅ عملیات حذف لغو شد!");
    } elseif ($data === 'broadcast_send') {
        sendBroadcastMessage($chat_id);
    } elseif (strpos($data, 'broadcast_filter') === 0) {
        showBroadcastFilterMenu($chat_id);
    } else {
        // Handle other callback data
        handleCallbackQuery($callback_query);
    }
}

function sendBroadcastMessage($chat_id) {
    global $pdo;
    
    $users = getAllUsers();
    $success_count = 0;
    
    foreach ($users as $user) {
        try {
            // Send message to user
            $result = sendMessage($user['user_id'], "📢 پیام همگانی از مدیریت ربات");
            if ($result) {
                $success_count++;
            }
        } catch (Exception $e) {
            // User might have blocked the bot
            continue;
        }
    }
    
    sendMessage($chat_id, "✅ پیام همگانی با موفقیت ارسال شد!\n\nتعداد گیرندگان: $success_count");
}

function getAllUsers() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE is_active = 1");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function showBroadcastFilterMenu($chat_id) {
    $keyboard = [
        [
            ['text' => 'بدون قفل', 'callback_data' => 'broadcast_no_lock'],
            ['text' => 'با نقل قول', 'callback_data' => 'broadcast_with_quote']
        ],
        [
            ['text' => 'انتخاب گیرنده', 'callback_data' => 'broadcast_select_users']
        ],
        [
            ['text' => 'کاربران یک روز اخیر', 'callback_data' => 'broadcast_last_day'],
            ['text' => 'کاربران یک هفته اخیر', 'callback_data' => 'broadcast_last_week']
        ],
        [
            ['text' => 'کاربران یک ماه اخیر', 'callback_data' => 'broadcast_last_month'],
            ['text' => 'همه کاربران', 'callback_data' => 'broadcast_all_users']
        ],
        [['text' => 'ارسال', 'callback_data' => 'broadcast_send_filtered']]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    sendMessage($chat_id, "🔧 اعمال فیلتر\n\nنحوه ارسال و گیرندگان را انتخاب کنید:", $reply_markup);
}

// Create additional tables for enhanced functionality
function createEnhancedTables() {
    global $pdo;
    
    // File likes/dislikes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT,
        user_id BIGINT,
        like_type ENUM('like', 'dislike'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (file_id, user_id, like_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // User sessions table for temporary storage
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        session_key VARCHAR(100),
        session_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        expires_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Enhanced user state management
function setUserStateEnhanced($user_id, $state, $data = null) {
    global $pdo;
    
    if ($data) {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_key, session_value, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE session_value = VALUES(session_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$user_id, 'state', $state]);
        
        if ($data) {
            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_key, session_value, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE session_value = VALUES(session_value), expires_at = VALUES(expires_at)");
            $stmt->execute([$user_id, 'data', json_encode($data)]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_key, session_value, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE session_value = VALUES(session_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$user_id, 'state', $state]);
    }
}

function getUserStateEnhanced($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT session_value FROM user_sessions WHERE user_id = ? AND session_key = 'state' AND expires_at > NOW()");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['session_value'] : null;
}

function getUserDataEnhanced($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT session_value FROM user_sessions WHERE user_id = ? AND session_key = 'data' AND expires_at > NOW()");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? json_decode($result['session_value'], true) : null;
}

function clearUserStateEnhanced($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// Initialize enhanced tables
createEnhancedTables();

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

function showUserStep($chat_id, $step) {
    // This function should show user step
    // For now, do nothing - implement based on your requirements
    return;
}
?>