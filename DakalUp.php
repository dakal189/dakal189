<?php

// ==================== تنظیمات اولیه ربات (مهم) ====================
define('TOKEN', '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ'); // توکن ربات
define('ADMIN_ID', '5641303137'); // آیدی عددی ادمین اصلی

// اطلاعات دیتابیس
$db_host = 'localhost';
$db_name = 'dakallli_Test2';
$db_user = 'dakallli_Test2';
$db_pass = 'hosyarww123';
// =================================================================

// --- اتصال به دیتابیس ---
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
    error_log("Database Connection Failed: " . $db->connect_error);
    die("A critical error occurred.");
}
$db->set_charset("utf8mb4");

// ایجاد جداول مورد نیاز
createTables();

// --- دریافت آپدیت‌ها ---
$update = json_decode(file_get_contents('php://input'));
$message = $update->message ?? null;
$callback_query = $update->callback_query ?? null;
$chat_id = $message->chat->id ?? $callback_query->message->chat->id ?? null;
$from_id = $message->from->id ?? $callback_query->from->id ?? null;
$text = $message->text ?? null;
$data = $callback_query->data ?? null;
$message_id = $message->message_id ?? $callback_query->message->message_id ?? null;

// ==================== توابع اصلی و کمکی ====================

function createTables() {
    global $db;
    
    // جدول کاربران
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id BIGINT PRIMARY KEY,
        first_name VARCHAR(255),
        username VARCHAR(255),
        step VARCHAR(100) DEFAULT 'none',
        temp_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // جدول گروه‌های فایل
    $db->query("CREATE TABLE IF NOT EXISTS file_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        description TEXT,
        is_public BOOLEAN DEFAULT FALSE,
        forward_lock BOOLEAN DEFAULT FALSE,
        created_by BIGINT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // جدول فایل‌ها
    $db->query("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id VARCHAR(255),
        file_unique_id VARCHAR(255),
        file_type VARCHAR(50),
        file_name VARCHAR(255),
        file_size BIGINT,
        caption TEXT,
        group_id INT,
        uploader_id BIGINT,
        likes INT DEFAULT 0,
        dislikes INT DEFAULT 0,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES file_groups(id) ON DELETE CASCADE
    )");
    
    // جدول لایک‌ها
    $db->query("CREATE TABLE IF NOT EXISTS likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT,
        user_id BIGINT,
        is_like BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
    )");
    
    // جدول ادمین‌ها
    $db->query("CREATE TABLE IF NOT EXISTS admins (
        id BIGINT PRIMARY KEY,
        name VARCHAR(255),
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // جدول تنظیمات
    $db->query("CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(100) PRIMARY KEY,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . TOKEN . "/" . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chat_id, $text, $keyboard = null, $parse_mode = 'Markdown') {
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    return bot('sendMessage', $params);
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    return bot('editMessageText', $params);
}

function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false) {
    return bot('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert]);
}

function isAdmin($user_id) {
    global $db;
    if ($user_id == ADMIN_ID) return true;
    $stmt = $db->prepare("SELECT id FROM admins WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function setUserStep($user_id, $step, $data = null) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET step = ?, temp_data = ? WHERE id = ?");
    $stmt->bind_param("ssi", $step, $data, $user_id);
    return $stmt->execute();
}

function getUserStep($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT step, temp_data FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc() : ['step' => 'none', 'temp_data' => null];
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

function getGroupStats($group_id) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) as file_count, SUM(file_size) as total_size FROM files WHERE group_id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function generateShareLink($group_id) {
    $bot_username = bot('getMe')['result']['username'];
    return "https://t.me/$bot_username?start=group_$group_id";
}

// ==================== پردازش اصلی آپدیت‌ها ====================

if (!$from_id) exit();

// ثبت‌نام کاربر
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $from_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $stmt_insert = $db->prepare("INSERT INTO users (id, first_name, username) VALUES (?, ?, ?)");
    $first_name = $message->from->first_name ?? 'کاربر';
    $username = $message->from->username ?? null;
    $stmt_insert->bind_param("iss", $from_id, $first_name, $username);
    $stmt_insert->execute();
    $user = ['id' => $from_id, 'step' => 'none', 'temp_data' => null];
}

// پردازش Callback Query ها
if ($data) {
    $parts = explode('_', $data);
    $action = $parts[0];

    if ($action == 'user' && $parts[1] == 'file') {
        switch ($parts[2]) {
            case 'like':
                $file_id = intval($parts[3]);
                $stmt = $db->prepare("UPDATE files SET likes = likes + 1 WHERE id = ?");
                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                answerCallbackQuery($callback_query->id, "لایک شد!", true);
                break;
                
            case 'dislike':
                $file_id = intval($parts[3]);
                $stmt = $db->prepare("UPDATE files SET dislikes = dislikes + 1 WHERE id = ?");
                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                answerCallbackQuery($callback_query->id, "دیسلایک شد!", true);
                break;
        }
        exit();
    }
    
    if (isAdmin($from_id)) {
        if ($action == 'admin') {
            switch ($parts[1]) {
                case 'panel':
                    $admin_keyboard = [
                        'keyboard' => [
                            ['📤 اپلود گروهی'],
                            ['📤 اپلود فایل'],
                            ['📢 ارسال پیام همگانی'],
                            ['📊 مشاهده فایل و آمار'],
                            ['⚙️ تنظیمات'],
                            ['🔄 خاموش/روشن کردن ربات']
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    editMessageText($chat_id, $message_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
                    break;
                    
                case 'group':
                    switch ($parts[2]) {
                        case 'upload':
                            setUserStep($from_id, 'group_upload');
                            $keyboard = [
                                'keyboard' => [
                                    ['✅ پایان'],
                                    ['🔙 بازگشت']
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false
                            ];
                            editMessageText($chat_id, $message_id, "فایل‌ها را ارسال کنید تا به گروه اضافه شوند...", $keyboard);
                            break;
                            
                        case 'finish':
                            $user_step = getUserStep($from_id);
                            if ($user_step['step'] == 'group_upload' && $user_step['temp_data']) {
                                $group_data = json_decode($user_step['temp_data'], true);
                                if (isset($group_data['files']) && count($group_data['files']) > 0) {
                                    $stats = getGroupStats($group_data['group_id']);
                                    $share_link = generateShareLink($group_data['group_id']);
                                    
                                    $message_text = "فایل‌های شما آپلود شد✅\n\n";
                                    $message_text .= "تعداد فایل‌ها: " . count($group_data['files']) . "\n";
                                    $message_text .= "حجم فایل‌ها: " . formatFileSize($stats['total_size']) . "\n";
                                    $message_text .= "شناسه: " . $group_data['group_id'] . "\n\n";
                                    $message_text .= "لینک اشتراک گذاری: $share_link";
                                    
                                    $inline_keyboard = [
                                        'inline_keyboard' => [
                                            [['text' => '👁️ مشاهده فایل ها', 'callback_data' => 'admin_group_view_' . $group_data['group_id']]],
                                            [['text' => '➕ افزودن فایل', 'callback_data' => 'admin_group_add_' . $group_data['group_id']]],
                                            [['text' => '🔒 قفل فوروارد', 'callback_data' => 'admin_group_forward_' . $group_data['group_id']]],
                                            [['text' => '📁 فولدر عمومی', 'callback_data' => 'admin_group_public_' . $group_data['group_id']]],
                                            [['text' => '🗑️ حذف فولدر', 'callback_data' => 'admin_group_delete_' . $group_data['group_id']]]
                                        ]
                                    ];
                                    
                                    editMessageText($chat_id, $message_id, $message_text, $inline_keyboard);
                                    setUserStep($from_id, 'none');
                                }
                            }
                            break;
                            
                        case 'back':
                            setUserStep($from_id, 'none');
                            $admin_keyboard = [
                                'keyboard' => [
                                    ['📤 اپلود گروهی'],
                                    ['📤 اپلود فایل'],
                                    ['📢 ارسال پیام همگانی'],
                                    ['📊 مشاهده فایل و آمار'],
                                    ['⚙️ تنظیمات'],
                                    ['🔄 خاموش/روشن کردن ربات']
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false
                            ];
                            editMessageText($chat_id, $message_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
                            break;
                            
                        case 'view':
                            $group_id = intval($parts[3]);
                            $stmt = $db->prepare("SELECT * FROM files WHERE group_id = ? ORDER BY created_at ASC");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            
                            if (count($files) > 0) {
                                foreach ($files as $index => $file) {
                                    $keyboard = [
                                        'inline_keyboard' => [
                                            [['text' => '🗑️ حذف', 'callback_data' => 'admin_file_delete_' . $file['id']]]
                                        ]
                                    ];
                                    
                                    // اضافه کردن دکمه‌های لایک/دیسلایک برای آخرین فایل
                                    if ($index == count($files) - 1) {
                                        $keyboard['inline_keyboard'][0][] = ['text' => '👍', 'callback_data' => 'admin_file_like_' . $file['id']];
                                        $keyboard['inline_keyboard'][0][] = ['text' => '👎', 'callback_data' => 'admin_file_dislike_' . $file['id']];
                                    }
                                    
                                    bot('send' . ucfirst($file['file_type']), [
                                        'chat_id' => $chat_id,
                                        $file['file_type'] => $file['file_id'],
                                        'caption' => $file['caption'] ?? '',
                                        'reply_markup' => json_encode($keyboard)
                                    ]);
                                }
                            } else {
                                editMessageText($chat_id, $message_id, "هیچ فایلی در این گروه یافت نشد.");
                            }
                            break;
                            
                        case 'add':
                            $group_id = intval($parts[3]);
                            setUserStep($from_id, 'group_add_' . $group_id);
                            $keyboard = [
                                'keyboard' => [
                                    ['✅ پایان']
                                ],
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false
                            ];
                            editMessageText($chat_id, $message_id, "فایل ها را ارسال کنید تا به این فولدر اضافه شود...", $keyboard);
                            break;
                            
                        case 'delete':
                            $group_id = intval($parts[3]);
                            $stmt = $db->prepare("DELETE FROM file_groups WHERE id = ?");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            answerCallbackQuery($callback_query->id, "فولدر با موفقیت حذف شد.", true);
                            break;
                            
                        case 'forward':
                            $group_id = intval($parts[3]);
                            $stmt = $db->prepare("UPDATE file_groups SET forward_lock = NOT forward_lock WHERE id = ?");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            
                            $stmt = $db->prepare("SELECT forward_lock FROM file_groups WHERE id = ?");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            $result = $stmt->get_result()->fetch_assoc();
                            $status = $result['forward_lock'] ? 'فعال' : 'غیرفعال';
                            
                            answerCallbackQuery($callback_query->id, "قفل فوروارد $status شد.", true);
                            break;
                            
                        case 'public':
                            $group_id = intval($parts[3]);
                            $stmt = $db->prepare("UPDATE file_groups SET is_public = NOT is_public WHERE id = ?");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            
                            $stmt = $db->prepare("SELECT is_public FROM file_groups WHERE id = ?");
                            $stmt->bind_param("i", $group_id);
                            $stmt->execute();
                            $result = $stmt->get_result()->fetch_assoc();
                            $status = $result['is_public'] ? 'فعال' : 'غیرفعال';
                            
                            answerCallbackQuery($callback_query->id, "فولدر عمومی $status شد.", true);
                            break;
                    }
                    break;
                    
                case 'file':
                    switch ($parts[2]) {
                        case 'delete':
                            $file_id = intval($parts[3]);
                            // نمایش دکمه‌های تایید حذف
                            $confirm_keyboard = [
                                'inline_keyboard' => [
                                    [['text' => '❌ خیر، انصراف می‌دهم', 'callback_data' => 'admin_file_cancel_delete_' . $file_id]],
                                    [['text' => '✅ بله، مطمئنم', 'callback_data' => 'admin_file_confirm_delete_' . $file_id]]
                                ]
                            ];
                            editMessageText($chat_id, $message_id, "آیا مطمئن هستید که می‌خواهید این فایل را حذف کنید؟", $confirm_keyboard);
                            break;
                            
                        case 'confirm':
                            if ($parts[3] == 'delete') {
                                $file_id = intval($parts[4]);
                                $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
                                $stmt->bind_param("i", $file_id);
                                $stmt->execute();
                                answerCallbackQuery($callback_query->id, "فایل با موفقیت حذف شد.", true);
                                
                                // بررسی اینکه آیا گروه خالی شده یا نه
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM files WHERE group_id = (SELECT group_id FROM files WHERE id = ?)");
                                $stmt->bind_param("i", $file_id);
                                $stmt->execute();
                                $result = $stmt->get_result()->fetch_assoc();
                                
                                if ($result['count'] == 0) {
                                    // حذف گروه اگر خالی باشد
                                    $stmt = $db->prepare("DELETE FROM file_groups WHERE id = (SELECT group_id FROM files WHERE id = ?)");
                                    $stmt->bind_param("i", $file_id);
                                    $stmt->execute();
                                    answerCallbackQuery($callback_query->id, "گروه نیز حذف شد زیرا خالی بود.", true);
                                }
                            }
                            break;
                            
                        case 'cancel':
                            if ($parts[3] == 'delete') {
                                $file_id = intval($parts[4]);
                                // بازگشت به دکمه حذف اصلی
                                $keyboard = [
                                    'inline_keyboard' => [
                                        [['text' => '🗑️ حذف', 'callback_data' => 'admin_file_delete_' . $file_id]]
                                    ]
                                ];
                                editMessageText($chat_id, $message_id, "فایل:", $keyboard);
                            }
                            break;
                            
                        case 'like':
                            $file_id = intval($parts[3]);
                            $stmt = $db->prepare("UPDATE files SET likes = likes + 1 WHERE id = ?");
                            $stmt->bind_param("i", $file_id);
                            $stmt->execute();
                            answerCallbackQuery($callback_query->id, "لایک شد!", true);
                            break;
                            
                        case 'dislike':
                            $file_id = intval($parts[3]);
                            $stmt = $db->prepare("UPDATE files SET dislikes = dislikes + 1 WHERE id = ?");
                            $stmt->bind_param("i", $file_id);
                            $stmt->execute();
                            answerCallbackQuery($callback_query->id, "دیسلایک شد!", true);
                            break;
                    }
                    break;
            }
        }
    }
    exit();
}

// پردازش دستورات و پیام‌های متنی
if (isset($text)) {
    if (preg_match('/^\/start(?: (.+))?$/', $text, $matches)) {
        $payload = $matches[1] ?? null;
        
        if ($payload && strpos($payload, 'group_') === 0) {
            $group_id = intval(str_replace('group_', '', $payload));
            
            // بررسی وجود گروه
            $stmt = $db->prepare("SELECT * FROM file_groups WHERE id = ?");
            $stmt->bind_param("i", $group_id);
            $stmt->execute();
            $group = $stmt->get_result()->fetch_assoc();
            
            if ($group) {
                // دریافت فایل‌های گروه
                $stmt = $db->prepare("SELECT * FROM files WHERE group_id = ? ORDER BY created_at ASC");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (count($files) > 0) {
                    foreach ($files as $index => $file) {
                        $keyboard = null;
                        
                        // اضافه کردن دکمه‌های لایک/دیسلایک برای آخرین فایل
                        if ($index == count($files) - 1) {
                            $keyboard = [
                                'inline_keyboard' => [
                                    [['text' => '👍', 'callback_data' => 'user_file_like_' . $file['id']], 
                                     ['text' => '👎', 'callback_data' => 'user_file_dislike_' . $file['id']]]
                                ]
                            ];
                        }
                        
                        bot('send' . ucfirst($file['file_type']), [
                            'chat_id' => $chat_id,
                            $file['file_type'] => $file['file_id'],
                            'caption' => $file['caption'] ?? '',
                            'reply_markup' => $keyboard ? json_encode($keyboard) : null
                        ]);
                    }
                } else {
                    sendMessage($chat_id, "این گروه فایلی ندارد.");
                }
            } else {
                sendMessage($chat_id, "گروه یافت نشد یا حذف شده است.");
            }
        } else {
            setUserStep($from_id, 'none');
            if (isAdmin($from_id)) {
                $admin_keyboard = [
                    'keyboard' => [
                        ['📤 اپلود گروهی'],
                        ['📤 اپلود فایل'],
                        ['📢 ارسال پیام همگانی'],
                        ['📊 مشاهده فایل و آمار'],
                        ['⚙️ تنظیمات'],
                        ['🔄 خاموش/روشن کردن ربات']
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                sendMessage($chat_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
            } else {
                sendMessage($chat_id, "سلام! به ربات آپلودر خوش آمدید.");
            }
        }
        exit();
    }
    
    if (isAdmin($from_id)) {
        $user_step = getUserStep($from_id);
        
        if ($text == '📤 اپلود گروهی') {
            // ایجاد گروه جدید
            $stmt = $db->prepare("INSERT INTO file_groups (name, created_by) VALUES (?, ?)");
            $group_name = "گروه " . date('Y-m-d H:i:s');
            $stmt->bind_param("si", $group_name, $from_id);
            $stmt->execute();
            $group_id = $db->insert_id;
            
            setUserStep($from_id, 'group_upload', json_encode(['group_id' => $group_id, 'files' => []]));
            $keyboard = [
                'keyboard' => [
                    ['✅ پایان'],
                    ['🔙 بازگشت']
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            sendMessage($chat_id, "فایل‌ها را ارسال کنید تا به گروه اضافه شوند...", $keyboard);
            exit();
        }
        
        if ($text == '✅ پایان') {
            if ($user_step['step'] == 'group_upload' || strpos($user_step['step'], 'group_add_') === 0) {
                $group_data = json_decode($user_step['temp_data'], true);
                if (isset($group_data['files']) && count($group_data['files']) > 0) {
                    $stats = getGroupStats($group_data['group_id']);
                    $share_link = generateShareLink($group_data['group_id']);
                    
                    $message_text = "فایل‌های شما آپلود شد✅\n\n";
                    $message_text .= "تعداد فایل‌ها: " . count($group_data['files']) . "\n";
                    $message_text .= "حجم فایل‌ها: " . formatFileSize($stats['total_size']) . "\n";
                    $message_text .= "شناسه: " . $group_data['group_id'] . "\n\n";
                    $message_text .= "لینک اشتراک گذاری: $share_link";
                    
                    $inline_keyboard = [
                        'inline_keyboard' => [
                            [['text' => '👁️ مشاهده فایل ها', 'callback_data' => 'admin_group_view_' . $group_data['group_id']]],
                            [['text' => '➕ افزودن فایل', 'callback_data' => 'admin_group_add_' . $group_data['group_id']]],
                            [['text' => '🔒 قفل فوروارد', 'callback_data' => 'admin_group_forward_' . $group_data['group_id']]],
                            [['text' => '📁 فولدر عمومی', 'callback_data' => 'admin_group_public_' . $group_data['group_id']]],
                            [['text' => '🗑️ حذف فولدر', 'callback_data' => 'admin_group_delete_' . $group_data['group_id']]]
                        ]
                    ];
                    
                    sendMessage($chat_id, $message_text, $inline_keyboard);
                    setUserStep($from_id, 'none');
                }
            }
            exit();
        }
        
        if ($text == '🔙 بازگشت') {
            setUserStep($from_id, 'none');
            $admin_keyboard = [
                'keyboard' => [
                    ['📤 اپلود گروهی'],
                    ['📤 اپلود فایل'],
                    ['📢 ارسال پیام همگانی'],
                    ['📊 مشاهده فایل و آمار'],
                    ['⚙️ تنظیمات'],
                    ['🔄 خاموش/روشن کردن ربات']
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            sendMessage($chat_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
            exit();
        }
    }
}

// پردازش فایل‌های ارسالی
if ($message && (isset($message->document) || isset($message->video) || isset($message->photo) || isset($message->audio))) {
    $user_step = getUserStep($from_id);
    
    if (isAdmin($from_id) && ($user_step['step'] == 'group_upload' || strpos($user_step['step'], 'group_add_') === 0)) {
        $file = $message->document ?? $message->video ?? $message->photo[count($message->photo)-1] ?? $message->audio ?? null;
        
        if ($file) {
            $file_id = $file->file_id;
            $file_unique_id = $file->file_unique_id;
            $file_type = $message->document ? 'document' : ($message->video ? 'video' : ($message->photo ? 'photo' : 'audio'));
            $file_name = $file->file_name ?? $file->file_unique_id;
            $file_size = $file->file_size ?? 0;
            
            $group_data = json_decode($user_step['temp_data'], true);
            $group_id = $group_data['group_id'];
            
            // ذخیره فایل در دیتابیس
            $stmt = $db->prepare("INSERT INTO files (file_id, file_unique_id, file_type, file_name, file_size, group_id, uploader_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssii", $file_id, $file_unique_id, $file_type, $file_name, $file_size, $group_id, $from_id);
            $stmt->execute();
            
            $file_id_db = $db->insert_id;
            $group_data['files'][] = $file_id_db;
            
            setUserStep($from_id, $user_step['step'], json_encode($group_data));
            
            sendMessage($chat_id, "این فایل به لیست شما اضافه شد✔️\nبرای اتمام عملیات بر روی پایان کلیک کنید یا فایل جدیدی ارسال کنید.👇");
        }
    }
}
