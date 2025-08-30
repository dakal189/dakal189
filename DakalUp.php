<?php

// ==================== تنظيمات اوليه ربات (مهم) ====================
define('TOKEN', '7657246591:AAF9b-UEuyypu5tIhQ-KrMvqnxn56vIxIXQ'); // توکن ربات
define('ADMIN_ID', '5641303137'); // آیدی عددی ادمین اصلی

// اطلاعات دیتابیس
$db_host = 'localhost';
$db_name = 'dakallli_Test2';
$db_user = 'dakallli_Test2';
$db_pass = 'hosyarww123';

// ایجاد جداول مورد نیاز
function createTables() {
    global $db;
    
    // جدول کاربران
    $db->query("CREATE TABLE IF NOT EXISTS `users` (
        `id` bigint(20) NOT NULL,
        `first_name` varchar(255) DEFAULT NULL,
        `username` varchar(255) DEFAULT NULL,
        `step` varchar(100) DEFAULT 'none',
        `temp_data` text DEFAULT NULL,
        `join_date` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول فایل‌ها با قابلیت‌های جدید
    $db->query("CREATE TABLE IF NOT EXISTS `files` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `file_id` varchar(255) NOT NULL,
        `file_unique_id` varchar(255) NOT NULL,
        `file_type` enum('document','video','photo','audio','voice') NOT NULL,
        `caption` text DEFAULT NULL,
        `public_link` varchar(50) UNIQUE NOT NULL,
        `uploader_id` bigint(20) NOT NULL,
        `upload_date` timestamp DEFAULT CURRENT_TIMESTAMP,
        `expire_time` datetime DEFAULT NULL,
        `views` int(11) DEFAULT 0,
        `likes` int(11) DEFAULT 0,
        `downloads` int(11) DEFAULT 0,
        `file_size` bigint(20) DEFAULT NULL,
        `file_name` varchar(255) DEFAULT NULL,
        `is_private` tinyint(1) DEFAULT 0,
        `password` varchar(255) DEFAULT NULL,
        `force_join_channel` varchar(255) DEFAULT NULL,
        `auto_delete_seconds` int(11) DEFAULT NULL,
        `forward_lock` tinyint(1) DEFAULT 0,
        `search_code` varchar(20) DEFAULT NULL,
        `status` enum('active','inactive','deleted') DEFAULT 'active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول تنظیمات
    $db->query("CREATE TABLE IF NOT EXISTS `settings` (
        `name` varchar(100) NOT NULL,
        `value` text DEFAULT NULL,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول ادمین‌ها
    $db->query("CREATE TABLE IF NOT EXISTS `admins` (
        `id` bigint(20) NOT NULL,
        `username` varchar(255) DEFAULT NULL,
        `added_by` bigint(20) DEFAULT NULL,
        `added_date` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول لایک‌ها
    $db->query("CREATE TABLE IF NOT EXISTS `likes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `file_id` int(11) NOT NULL,
        `user_id` bigint(20) NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `file_user` (`file_id`,`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول کانال‌های عضویت اجباری
    $db->query("CREATE TABLE IF NOT EXISTS `force_join_channels` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `channel_username` varchar(255) NOT NULL,
        `channel_title` varchar(255) DEFAULT NULL,
        `invite_link` varchar(500) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `added_by` bigint(20) DEFAULT NULL,
        `added_date` timestamp DEFAULT CURRENT_TIMESTAMP,
        `bot_status` enum('member','administrator','left','kicked') DEFAULT 'left',
        PRIMARY KEY (`id`),
        UNIQUE KEY `channel_username` (`channel_username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول عضویت کاربران در کانال‌ها
    $db->query("CREATE TABLE IF NOT EXISTS `user_channel_memberships` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `channel_id` int(11) NOT NULL,
        `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `status` enum('member','left','kicked') DEFAULT 'member',
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_channel` (`user_id`,`channel_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // جدول آمار
    $db->query("CREATE TABLE IF NOT EXISTS `statistics` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `date` date NOT NULL,
        `total_users` int(11) DEFAULT 0,
        `new_users` int(11) DEFAULT 0,
        `total_files` int(11) DEFAULT 0,
        `new_files` int(11) DEFAULT 0,
        `total_downloads` int(11) DEFAULT 0,
        `total_views` int(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // تنظیمات پیش‌فرض
    $db->query("INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
        ('start_text', 'سلام! به ربات فایل‌شیر خوش آمدید. 👋'),
        ('force_join_channel', '@YourChannelUsername'),
        ('show_likes', 'true'),
        ('show_views', 'true'),
        ('show_comments', 'true'),
        ('auto_delete_default', '0'),
        ('forward_lock_default', '0')");
    
    // ادمین پیش‌فرض
    $db->query("INSERT IGNORE INTO `admins` (`id`, `username`) VALUES (5641303137, 'admin')");
}

// =================================================================

// --- اتصال به دیتابیس ---
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
    error_log("Database Connection Failed: " . $db->connect_error);
    die("A critical error occurred.");
}
$db->set_charset("utf8mb4");

// ایجاد جداول
createTables();

// --- دریافت آپدیت‌ها ---
$update = json_decode(file_get_contents('php://input'));
$message = $update->message ?? null;
$callback_query = $update->callback_query ?? null;
$channel_post = $update->channel_post ?? null;
$chat_id = $message->chat->id ?? $callback_query->message->chat->id ?? null;
$from_id = $message->from->id ?? $callback_query->from->id ?? null;
$text = $message->text ?? null;
$data = $callback_query->data ?? null;
$message_id = $message->message_id ?? $callback_query->message->message_id ?? null;

// ==================== توابع اصلی و کمکی ====================

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

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return bot('forwardMessage', ['chat_id' => $chat_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false) {
    return bot('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert]);
}

function getSetting($name) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['value'] : null;
}

function updateSetting($name, $value) {
    global $db;
    $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->bind_param("sss", $name, $value, $value);
    return $stmt->execute();
}

function isAdmin($user_id) {
    global $db;
    if ($user_id == ADMIN_ID) return true;
    $stmt = $db->prepare("SELECT id FROM admins WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function checkJoin($user_id) {
    global $db;
    
    // بررسی کانال‌های عضویت اجباری
    $stmt = $db->prepare("SELECT * FROM force_join_channels WHERE is_active = 1");
    $stmt->execute();
    $channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($channels)) return true;
    
    foreach ($channels as $channel) {
        try {
            $status = bot('getChatMember', ['chat_id' => $channel['channel_username'], 'user_id' => $user_id])['result']['status'] ?? 'left';
            if (!in_array($status, ['member', 'administrator', 'creator'])) {
                return false;
            }
            
            // بروزرسانی وضعیت عضویت کاربر
            $stmt_update = $db->prepare("INSERT INTO user_channel_memberships (user_id, channel_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
            $member_status = 'member';
            $stmt_update->bind_param("iiss", $user_id, $channel['id'], $member_status, $member_status);
            $stmt_update->execute();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    return true;
}

function getForceJoinChannels() {
    global $db;
    $stmt = $db->prepare("SELECT * FROM force_join_channels WHERE is_active = 1");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function updateUserChannelMembership($user_id, $channel_id, $status) {
    global $db;
    $stmt = $db->prepare("INSERT INTO user_channel_memberships (user_id, channel_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
    $stmt->bind_param("iiss", $user_id, $channel_id, $status, $status);
    return $stmt->execute();
}

// ==================== تابع اصلاح شده ====================
/**
 * @param int $user_id
 * @param string $step
 * @param string|null $data
 * @return bool
 */
function setUserStep($user_id, $step, $data = null) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET step = ?, temp_data = ? WHERE id = ?");
    
    // این بخش برای رفع خطا تغییر کرده است
    // ما متغیرها را به صورت صریح به bind_param می‌دهیم تا مشکل رفرنس حل شود
    $bound_step = $step;
    $bound_data = $data;
    $bound_user_id = $user_id;
    
    $stmt->bind_param("ssi", $bound_step, $bound_data, $bound_user_id);
    return $stmt->execute();
}
// =========================================================

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function generateSearchCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function getFileStats() {
    global $db;
    $stats = [];
    
    // تعداد کل فایل‌ها
    $result = $db->query("SELECT COUNT(*) as total FROM files WHERE status = 'active'");
    $stats['total_files'] = $result->fetch_assoc()['total'];
    
    // تعداد فایل‌های امروز
    $result = $db->query("SELECT COUNT(*) as today FROM files WHERE status = 'active' AND DATE(upload_date) = CURDATE()");
    $stats['today_files'] = $result->fetch_assoc()['today'];
    
    // تعداد کل کاربران
    $result = $db->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $result->fetch_assoc()['total'];
    
    // تعداد کاربران امروز
    $result = $db->query("SELECT COUNT(*) as today FROM users WHERE DATE(join_date) = CURDATE()");
    $stats['today_users'] = $result->fetch_assoc()['today'];
    
    // تعداد کل دانلودها
    $result = $db->query("SELECT SUM(downloads) as total FROM files WHERE status = 'active'");
    $stats['total_downloads'] = $result->fetch_assoc()['total'] ?? 0;
    
    // تعداد کل بازدیدها
    $result = $db->query("SELECT SUM(views) as total FROM files WHERE status = 'active'");
    $stats['total_views'] = $result->fetch_assoc()['total'] ?? 0;
    
    return $stats;
}

function updateFileViews($file_id) {
    global $db;
    $db->query("UPDATE files SET views = views + 1 WHERE id = $file_id");
}

function updateFileDownloads($file_id) {
    global $db;
    $db->query("UPDATE files SET downloads = downloads + 1 WHERE id = $file_id");
}

function getFilesBySearchCode($search_code) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM files WHERE search_code = ? AND status = 'active'");
    $stmt->bind_param("s", $search_code);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function deleteFile($file_id, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET status = 'deleted' WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    return $stmt->execute();
}

function editFile($file_id, $caption, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET caption = ? WHERE id = ?");
    $stmt->bind_param("si", $caption, $file_id);
    return $stmt->execute();
}

function setFilePassword($file_id, $password, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET password = ?, is_private = 1 WHERE id = ?");
    $stmt->bind_param("si", $password, $file_id);
    return $stmt->execute();
}

function setFileForceJoin($file_id, $channel_username, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET force_join_channel = ? WHERE id = ?");
    $stmt->bind_param("si", $channel_username, $file_id);
    return $stmt->execute();
}

function setFileAutoDelete($file_id, $seconds, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET auto_delete_seconds = ? WHERE id = ?");
    $stmt->bind_param("ii", $seconds, $file_id);
    return $stmt->execute();
}

function setFileForwardLock($file_id, $lock, $admin_id) {
    global $db;
    $stmt = $db->prepare("UPDATE files SET forward_lock = ? WHERE id = ?");
    $stmt->bind_param("ii", $lock, $file_id);
    return $stmt->execute();
}

function addForceJoinChannel($channel_username, $admin_id) {
    global $db;
    $stmt = $db->prepare("INSERT INTO force_join_channels (channel_username, added_by) VALUES (?, ?)");
    $stmt->bind_param("si", $channel_username, $admin_id);
    return $stmt->execute();
}

function removeForceJoinChannel($channel_id, $admin_id) {
    global $db;
    $stmt = $db->prepare("DELETE FROM force_join_channels WHERE id = ?");
    $stmt->bind_param("i", $channel_id);
    return $stmt->execute();
}

function getFilesList($page = 0, $limit = 10) {
    global $db;
    $offset = $page * $limit;
    $stmt = $db->prepare("SELECT f.*, u.first_name as uploader_name FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.status = 'active' ORDER BY f.upload_date DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getFilesCount() {
    global $db;
    $result = $db->query("SELECT COUNT(*) as count FROM files WHERE status = 'active'");
    return $result->fetch_assoc()['count'];
}

function sendFileWithButtons($chat_id, $file) {
    $keyboard = ['inline_keyboard' => []];
    $buttons = [];
    
    // بررسی قفل فوروارد
    if ($file['forward_lock']) {
        $keyboard['protect_content'] = true;
    }
    
    if (getSetting('show_views') == 'true') $buttons[] = ['text' => '👀 ' . $file['views'], 'callback_data' => 'noop'];
    if (getSetting('show_likes') == 'true') $buttons[] = ['text' => '👍 ' . $file['likes'], 'callback_data' => 'like_' . $file['id']];
    if (getSetting('show_comments') == 'true') $buttons[] = ['text' => '💬 ارسال نظر', 'callback_data' => 'comment_' . $file['id']];
    
    // دکمه دانلود
    $buttons[] = ['text' => '📥 دانلود', 'callback_data' => 'download_' . $file['id']];
    
    if (!empty($buttons)) $keyboard['inline_keyboard'][] = $buttons;
    
    $file_type = $file['file_type'];
    $method = 'send' . ucfirst($file_type);
    
    $params = [
        'chat_id' => $chat_id,
        $file_type => $file['file_id'],
        'caption' => $file['caption'],
        'reply_markup' => json_encode($keyboard)
    ];
    
    // اضافه کردن محافظت از محتوا اگر قفل فوروارد فعال باشد
    if ($file['forward_lock']) {
        $params['protect_content'] = true;
    }
    
    bot($method, $params);
    
    // بروزرسانی آمار بازدید
    updateFileViews($file['id']);
}

// ==================== پردازش اصلی آپدیت‌ها ====================

// 1. فوروارد پست از کانال
if ($channel_post) {
    $channel_username_setting = getSetting('force_join_channel');
    if (strtolower('@' . $channel_post->chat->username) == strtolower($channel_username_setting)) {
        $users_query = $db->query("SELECT id FROM users");
        while ($user = $users_query->fetch_assoc()) {
            forwardMessage($user['id'], $channel_post->chat->id, $channel_post->message_id);
        }
    }
    exit();
}

if (!$from_id) exit();

// 2. ثبت‌نام کاربر و چک کردن عضویت
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

if (!checkJoin($from_id)) {
    $channels = getForceJoinChannels();
    if (!empty($channels)) {
        $keyboard = ['inline_keyboard' => []];
        foreach ($channels as $channel) {
            $channel_link = 'https://t.me/' . str_replace('@', '', $channel['channel_username']);
            $keyboard['inline_keyboard'][] = [['text' => 'عضویت در ' . $channel['channel_username'], 'url' => $channel_link]];
        }
        $keyboard['inline_keyboard'][] = [['text' => '✅ بررسی عضویت', 'callback_data' => 'check_join']];
        
        $channels_text = "برای استفاده از ربات، لطفاً ابتدا در کانال‌های زیر عضو شوید:\n\n";
        foreach ($channels as $channel) {
            $channels_text .= "• " . $channel['channel_username'] . "\n";
        }
        $channels_text .= "\nسپس دکمه بررسی را بزنید.";
        
        sendMessage($chat_id, $channels_text, $keyboard);
        exit();
    }
}

// 3. پردازش Callback Query ها (دکمه‌های شیشه‌ای)
if ($data) {
    $parts = explode('_', $data);
    $action = $parts[0];

    if ($action == 'check' && $parts[1] == 'join') {
        if (checkJoin($from_id)) {
            answerCallbackQuery($callback_query->id, 'عضویت شما تایید شد. خوش آمدید!', true);
            editMessageText($chat_id, $message_id, "عضویت شما با موفقیت تایید شد. برای شروع /start را ارسال کنید.");
        } else {
            answerCallbackQuery($callback_query->id, 'هنوز در کانال عضو نشده‌اید!', true);
        }
        exit();
    }
    
    if ($action == 'like') {
        $file_id = intval($parts[1]);
        $stmt_check = $db->prepare("SELECT * FROM likes WHERE file_id = ? AND user_id = ?");
        $stmt_check->bind_param("ii", $file_id, $from_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
            $db->query("UPDATE files SET likes = likes + 1 WHERE id = $file_id");
            $stmt_insert = $db->prepare("INSERT INTO likes (file_id, user_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $file_id, $from_id);
            $stmt_insert->execute();
            answerCallbackQuery($callback_query->id, 'لایک شما ثبت شد!');
        } else {
            answerCallbackQuery($callback_query->id, 'شما قبلاً لایک کرده‌اید.');
        }
        exit();
    }

    if ($action == 'download') {
        $file_id = intval($parts[1]);
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if ($file) {
            // بررسی رمز عبور
            if ($file['is_private'] && $file['password']) {
                setUserStep($from_id, 'awaiting_password_' . $file_id);
                answerCallbackQuery($callback_query->id);
                sendMessage($chat_id, "این فایل محافظت شده است. لطفاً رمز عبور را وارد کنید:");
                exit();
            }
            
            // بررسی عضویت اجباری
            if ($file['force_join_channel']) {
                try {
                    $status = bot('getChatMember', ['chat_id' => $file['force_join_channel'], 'user_id' => $from_id])['result']['status'] ?? 'left';
                    if (!in_array($status, ['member', 'administrator', 'creator'])) {
                        answerCallbackQuery($callback_query->id, 'برای دانلود این فایل باید در کانال عضو شوید!', true);
                        exit();
                    }
                } catch (Exception $e) {
                    answerCallbackQuery($callback_query->id, 'خطا در بررسی عضویت!', true);
                    exit();
                }
            }
            
            // ارسال فایل
            $file_type = $file['file_type'];
            $method = 'send' . ucfirst($file_type);
            
            $params = [
                'chat_id' => $chat_id,
                $file_type => $file['file_id'],
                'caption' => $file['caption']
            ];
            
            if ($file['forward_lock']) {
                $params['protect_content'] = true;
            }
            
            bot($method, $params);
            
            // بروزرسانی آمار دانلود
            updateFileDownloads($file_id);
            
            answerCallbackQuery($callback_query->id, 'فایل ارسال شد!');
            
            // اگر فایل دارای حذف خودکار است
            if ($file['auto_delete_seconds'] && $file['auto_delete_seconds'] > 0) {
                sendMessage($chat_id, "⚠️ این فایل بعد از {$file['auto_delete_seconds']} ثانیه حذف خواهد شد. لطفاً آن را ذخیره کنید.");
            }
        } else {
            answerCallbackQuery($callback_query->id, 'فایل یافت نشد!', true);
        }
        exit();
    }

    if ($action == 'comment') {
        setUserStep($from_id, 'awaiting_comment_' . intval($parts[1]));
        sendMessage($chat_id, "نظر خود را برای این فایل بنویسید و ارسال کنید:");
        answerCallbackQuery($callback_query->id);
        exit();
    }

    // --- منطق دکمه‌های پنل ادمین ---
    if (isAdmin($from_id)) {
        if ($action == 'admin') {
            switch ($parts[1]) {
                case 'panel':
                    $admin_keyboard = [ 'inline_keyboard' => [
                        [['text' => '📤 آپلود فایل جدید', 'callback_data' => 'admin_upload']],
                        [['text' => '📤 آپلود چند فایل', 'callback_data' => 'admin_upload_multiple']],
                        [['text' => '📂 مدیریت فایل‌ها', 'callback_data' => 'admin_files_0']],
                        [['text' => '🔍 جستجوی فایل', 'callback_data' => 'admin_search']],
                        [['text' => '📊 آمار', 'callback_data' => 'admin_stats']],
                        [['text' => '👥 مدیریت عضویت اجباری', 'callback_data' => 'admin_force_join']],
                        [['text' => '⚙️ تنظیمات ربات', 'callback_data' => 'admin_settings']],
                        [['text' => '👥 مدیریت ادمین‌ها', 'callback_data' => 'admin_admins']]
                    ]];
                    editMessageText($chat_id, $message_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
                    break;
                case 'upload':
                    setUserStep($from_id, 'awaiting_file');
                    answerCallbackQuery($callback_query->id);
                    editMessageText($chat_id, $message_id, "لطفاً فایل مورد نظر خود را ارسال کنید (عکس، ویدیو، داکیومنت و...).");
                    break;
                    
                case 'upload_multiple':
                    setUserStep($from_id, 'awaiting_multiple_files');
                    answerCallbackQuery($callback_query->id);
                    editMessageText($chat_id, $message_id, "لطفاً فایل‌های مورد نظر خود را یکی یکی ارسال کنید. برای پایان، /done را ارسال کنید.");
                    break;
                    
                case 'files':
                    $page = intval($parts[2]);
                    $files = getFilesList($page, 5);
                    $total_files = getFilesCount();
                    $total_pages = ceil($total_files / 5);
                    
                    $files_text = "📂 مدیریت فایل‌ها\n\n";
                    foreach ($files as $file) {
                        $files_text .= "📄 " . ($file['file_name'] ?: 'فایل') . "\n";
                        $files_text .= "👤 آپلودکننده: " . ($file['uploader_name'] ?: 'نامشخص') . "\n";
                        $files_text .= "📅 تاریخ: " . date('Y/m/d', strtotime($file['upload_date'])) . "\n";
                        $files_text .= "👀 بازدید: " . $file['views'] . " | 📥 دانلود: " . $file['downloads'] . "\n";
                        $files_text .= "🔗 کد جستجو: " . ($file['search_code'] ?: 'ندارد') . "\n\n";
                    }
                    
                    $files_text .= "صفحه " . ($page + 1) . " از " . $total_pages;
                    
                    $keyboard = ['inline_keyboard' => []];
                    
                    // دکمه‌های ناوبری
                    $nav_buttons = [];
                    if ($page > 0) $nav_buttons[] = ['text' => '◀️ قبلی', 'callback_data' => 'admin_files_' . ($page - 1)];
                    if ($page < $total_pages - 1) $nav_buttons[] = ['text' => 'بعدی ▶️', 'callback_data' => 'admin_files_' . ($page + 1)];
                    if (!empty($nav_buttons)) $keyboard['inline_keyboard'][] = $nav_buttons;
                    
                    $keyboard['inline_keyboard'][] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_panel']];
                    
                    editMessageText($chat_id, $message_id, $files_text, $keyboard);
                    break;
                    
                case 'search':
                    setUserStep($from_id, 'awaiting_search_code');
                    answerCallbackQuery($callback_query->id);
                    editMessageText($chat_id, $message_id, "لطفاً کد جستجوی فایل را وارد کنید:");
                    break;
                    
                case 'stats':
                    $stats = getFileStats();
                    $stats_text = "📊 آمار ربات\n\n";
                    $stats_text .= "👥 کل کاربران: " . number_format($stats['total_users']) . "\n";
                    $stats_text .= "👥 کاربران امروز: " . number_format($stats['today_users']) . "\n";
                    $stats_text .= "📄 کل فایل‌ها: " . number_format($stats['total_files']) . "\n";
                    $stats_text .= "📄 فایل‌های امروز: " . number_format($stats['today_files']) . "\n";
                    $stats_text .= "👀 کل بازدیدها: " . number_format($stats['total_views']) . "\n";
                    $stats_text .= "📥 کل دانلودها: " . number_format($stats['total_downloads']) . "\n";
                    
                    $keyboard = ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_panel']]]];
                    editMessageText($chat_id, $message_id, $stats_text, $keyboard);
                    break;
                    
                case 'force_join':
                    $channels = getForceJoinChannels();
                    $channels_text = "👥 مدیریت عضویت اجباری\n\n";
                    
                    if (empty($channels)) {
                        $channels_text .= "هیچ کانالی تنظیم نشده است.\n";
                    } else {
                        foreach ($channels as $channel) {
                            $status_emoji = $channel['is_active'] ? '✅' : '❌';
                            $channels_text .= "$status_emoji " . $channel['channel_username'] . "\n";
                            $channels_text .= "📅 اضافه شده: " . date('Y/m/d', strtotime($channel['added_date'])) . "\n\n";
                        }
                    }
                    
                    $keyboard = ['inline_keyboard' => [
                        [['text' => '➕ افزودن کانال', 'callback_data' => 'admin_add_channel']],
                        [['text' => '🔙 بازگشت', 'callback_data' => 'admin_panel']]
                    ]];
                    editMessageText($chat_id, $message_id, $channels_text, $keyboard);
                    break;
                    
                case 'add_channel':
                    setUserStep($from_id, 'awaiting_channel_username');
                    answerCallbackQuery($callback_query->id);
                    editMessageText($chat_id, $message_id, "لطفاً یوزرنیم کانال را با @ وارد کنید:");
                    break;
                case 'settings':
                    $likes_status = getSetting('show_likes') == 'true' ? '✅' : '❌';
                    $views_status = getSetting('show_views') == 'true' ? '✅' : '❌';
                    $comments_status = getSetting('show_comments') == 'true' ? '✅' : '❌';
                    $auto_delete_default = getSetting('auto_delete_default') ?: '0';
                    $forward_lock_default = getSetting('forward_lock_default') == '1' ? '✅' : '❌';
                    
                    $settings_keyboard = [ 'inline_keyboard' => [
                        [['text' => "تغییر متن استارت", 'callback_data' => 'admin_set_start']],
                        [['text' => "$likes_status دکمه لایک", 'callback_data' => 'admin_toggle_likes']],
                        [['text' => "$views_status دکمه بازدید", 'callback_data' => 'admin_toggle_views']],
                        [['text' => "$comments_status دکمه نظر", 'callback_data' => 'admin_toggle_comments']],
                        [['text' => "زمان حذف خودکار: {$auto_delete_default} ثانیه", 'callback_data' => 'admin_set_auto_delete']],
                        [['text' => "$forward_lock_default قفل فوروارد پیش‌فرض", 'callback_data' => 'admin_toggle_forward_lock']],
                        [['text' => '🔙 بازگشت', 'callback_data' => 'admin_panel']]
                    ]];
                    editMessageText($chat_id, $message_id, "تنظیمات ربات:", $settings_keyboard);
                    break;
                case 'toggle':
                    if ($parts[2] == 'forward_lock') {
                        $current_value = getSetting('forward_lock_default');
                        updateSetting('forward_lock_default', $current_value == '1' ? '0' : '1');
                        answerCallbackQuery($callback_query->id, 'تنظیمات با موفقیت تغییر کرد.');
                    } else {
                        $setting_name = 'show_' . $parts[2];
                        $current_value = getSetting($setting_name);
                        updateSetting($setting_name, $current_value == 'true' ? 'false' : 'true');
                        answerCallbackQuery($callback_query->id, 'تنظیمات با موفقیت تغییر کرد.');
                    }
                    
                    // Redraw settings panel
                    $likes_status = getSetting('show_likes') == 'true' ? '✅' : '❌';
                    $views_status = getSetting('show_views') == 'true' ? '✅' : '❌';
                    $comments_status = getSetting('show_comments') == 'true' ? '✅' : '❌';
                    $auto_delete_default = getSetting('auto_delete_default') ?: '0';
                    $forward_lock_default = getSetting('forward_lock_default') == '1' ? '✅' : '❌';
                    
                    $settings_keyboard = [ 'inline_keyboard' => [
                        [['text' => "تغییر متن استارت", 'callback_data' => 'admin_set_start']],
                        [['text' => "$likes_status دکمه لایک", 'callback_data' => 'admin_toggle_likes']],
                        [['text' => "$views_status دکمه بازدید", 'callback_data' => 'admin_toggle_views']],
                        [['text' => "$comments_status دکمه نظر", 'callback_data' => 'admin_toggle_comments']],
                        [['text' => "زمان حذف خودکار: {$auto_delete_default} ثانیه", 'callback_data' => 'admin_set_auto_delete']],
                        [['text' => "$forward_lock_default قفل فوروارد پیش‌فرض", 'callback_data' => 'admin_toggle_forward_lock']],
                        [['text' => '🔙 بازگشت', 'callback_data' => 'admin_panel']]
                    ]];
                    editMessageText($chat_id, $message_id, "تنظیمات ربات:", $settings_keyboard);
                    break;
                case 'set':
                    answerCallbackQuery($callback_query->id);
                    if ($parts[2] == 'start') {
                        setUserStep($from_id, 'awaiting_start_text');
                        editMessageText($chat_id, $message_id, "لطفاً متن جدید خوشامدگویی (/start) را ارسال کنید:");
                    } elseif ($parts[2] == 'auto_delete') {
                        setUserStep($from_id, 'awaiting_auto_delete_time');
                        editMessageText($chat_id, $message_id, "لطفاً زمان حذف خودکار را به ثانیه وارد کنید (0 برای غیرفعال):");
                    }
                    break;
            }
        }
    }
    exit();
}

// 4. پردازش دستورات و پیام‌های متنی

if (isset($text) && $text == '/cancel' && isAdmin($from_id)) {
    setUserStep($from_id, 'none');
    sendMessage($chat_id, "عملیات لغو شد.");
    exit();
}

if (isset($text) && preg_match('/^\/start(?: (.+))?$/', $text, $matches)) {
    setUserStep($from_id, 'none'); // Cancel any pending operations on /start
    $payload = $matches[1] ?? null;
    if ($payload) {
        $stmt = $db->prepare("SELECT * FROM files WHERE public_link = ? AND (expire_time IS NULL OR expire_time > NOW())");
        $stmt->bind_param("s", $payload);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        if ($file) {
            $db->query("UPDATE files SET views = views + 1 WHERE id = " . $file['id']);
            $file['views']++;
            sendFileWithButtons($chat_id, $file);
        } else {
            sendMessage($chat_id, "فایل یافت نشد، منقضی شده یا لینک اشتباه است.");
        }
    } else {
        $start_text = getSetting('start_text');
        $keyboard = [['text' => 'راهنما']];
        if (isAdmin($from_id)) $keyboard[] = ['text' => '/admin'];
        sendMessage($chat_id, $start_text, ['keyboard' => [$keyboard], 'resize_keyboard' => true, 'one_time_keyboard' => true]);
    }
    exit();
}

if (isset($text) && $text == '/admin' && isAdmin($from_id)) {
    setUserStep($from_id, 'none');
    $admin_keyboard = [ 'inline_keyboard' => [
        [['text' => '📤 آپلود فایل جدید', 'callback_data' => 'admin_upload']],
        [['text' => '📤 آپلود چند فایل', 'callback_data' => 'admin_upload_multiple']],
        [['text' => '📂 مدیریت فایل‌ها', 'callback_data' => 'admin_files_0']],
        [['text' => '🔍 جستجوی فایل', 'callback_data' => 'admin_search']],
        [['text' => '📊 آمار', 'callback_data' => 'admin_stats']],
        [['text' => '👥 مدیریت عضویت اجباری', 'callback_data' => 'admin_force_join']],
        [['text' => '⚙️ تنظیمات ربات', 'callback_data' => 'admin_settings']],
        [['text' => '👥 مدیریت ادمین‌ها', 'callback_data' => 'admin_admins']]
    ]];
    sendMessage($chat_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
    exit();
}

// 5. پردازش ورودی‌ها بر اساس "مرحله" کاربر
if ($user['step'] != 'none') {
    $step_parts = explode('_', $user['step']);
    $main_step = $step_parts[0];

    if ($main_step == 'awaiting') {
        $action = $step_parts[1];
        switch ($action) {
            case 'file':
                $file = $message->document ?? $message->video ?? $message->photo[count($message->photo)-1] ?? $message->audio ?? null;
                if ($file) {
                    $file_id = $file->file_id;
                    $file_unique_id = $file->file_unique_id;
                    $file_type = $message->document ? 'document' : ($message->video ? 'video' : ($message->photo ? 'photo' : 'audio'));
                    $temp_data = json_encode(['file_id' => $file_id, 'file_unique_id' => $file_unique_id, 'file_type' => $file_type]);
                    setUserStep($from_id, 'awaiting_caption', $temp_data);
                    sendMessage($chat_id, "فایل دریافت شد. اکنون کپشن را ارسال کنید. برای رد شدن، /skip را بفرستید. برای لغو /cancel را ارسال کنید.");
                } else {
                    sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود. لطفاً یک فایل دیگر ارسال کنید یا با /cancel لغو کنید.");
                }
                break;
                
            case 'multiple_files':
                $file = $message->document ?? $message->video ?? $message->photo[count($message->photo)-1] ?? $message->audio ?? null;
                if ($file) {
                    $temp_data = json_decode($user['temp_data'], true);
                    if (!isset($temp_data['files'])) {
                        $temp_data['files'] = [];
                    }
                    
                    $file_info = [
                        'file_id' => $file->file_id,
                        'file_unique_id' => $file->file_unique_id,
                        'file_type' => $message->document ? 'document' : ($message->video ? 'video' : ($message->photo ? 'photo' : 'audio')),
                        'file_name' => $file->file_name ?? null,
                        'file_size' => $file->file_size ?? null
                    ];
                    
                    $temp_data['files'][] = $file_info;
                    setUserStep($from_id, 'awaiting_multiple_files', json_encode($temp_data));
                    
                    $count = count($temp_data['files']);
                    sendMessage($chat_id, "✅ فایل شماره $count اضافه شد. فایل بعدی را ارسال کنید یا برای پایان /done را بزنید.");
                } else {
                    sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود. لطفاً یک فایل دیگر ارسال کنید یا برای پایان /done را بزنید.");
                }
                break;
            case 'caption':
                $caption = ($text == '/skip') ? null : $text;
                $temp_data = json_decode($user['temp_data'], true);
                $temp_data['caption'] = $caption;
                setUserStep($from_id, 'awaiting_expire', json_encode($temp_data));
                sendMessage($chat_id, "کپشن ثبت شد. مدت زمان انقضای فایل را به روز وارد کنید (مثلا: 7). برای دائمی بودن، عدد 0 را ارسال کنید. برای لغو /cancel را ارسال کنید.");
                break;
            case 'expire':
                if (is_numeric($text)) {
                    $days = intval($text);
                    $expire_time = ($days == 0) ? null : date('Y-m-d H:i:s', strtotime("+$days days"));
                    $temp_data = json_decode($user['temp_data'], true);
                    $public_link = generateRandomString();
                    $search_code = generateSearchCode();
                    
                    // تنظیمات پیش‌فرض
                    $auto_delete = getSetting('auto_delete_default') ?: 0;
                    $forward_lock = getSetting('forward_lock_default') == '1' ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO files (file_id, file_unique_id, file_type, caption, public_link, uploader_id, expire_time, search_code, auto_delete_seconds, forward_lock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssssii", $temp_data['file_id'], $temp_data['file_unique_id'], $temp_data['file_type'], $temp_data['caption'], $public_link, $from_id, $expire_time, $search_code, $auto_delete, $forward_lock);
                    $stmt->execute();
                    
                    $bot_username = bot('getMe')['result']['username'];
                    $share_link = "https://t.me/$bot_username?start=$public_link";
                    
                    sendMessage($chat_id, "✅ فایل با موفقیت آپلود شد!\n\n🔗 لینک اشتراک‌گذاری:\n`$share_link`\n\n🔍 کد جستجو: `$search_code`");
                    setUserStep($from_id, 'none');
                } else {
                    sendMessage($chat_id, "لطفاً فقط یک عدد وارد کنید یا با /cancel لغو کنید.");
                }
                break;
            case 'start':
                if ($action == 'text') {
                    updateSetting('start_text', $text);
                    setUserStep($from_id, 'none');
                    sendMessage($chat_id, "✅ متن خوشامدگویی با موفقیت تغییر کرد.");
                }
                break;
                
            case 'auto_delete':
                if ($action == 'time' && is_numeric($text)) {
                    updateSetting('auto_delete_default', $text);
                    setUserStep($from_id, 'none');
                    sendMessage($chat_id, "✅ زمان حذف خودکار پیش‌فرض تغییر کرد.");
                } else {
                    sendMessage($chat_id, "لطفاً فقط یک عدد وارد کنید یا با /cancel لغو کنید.");
                }
                break;
                
            case 'search':
                if ($action == 'code') {
                    $files = getFilesBySearchCode($text);
                    if (!empty($files)) {
                        $files_text = "🔍 نتایج جستجو برای کد: $text\n\n";
                        foreach ($files as $file) {
                            $files_text .= "📄 " . ($file['file_name'] ?: 'فایل') . "\n";
                            $files_text .= "📅 تاریخ: " . date('Y/m/d', strtotime($file['upload_date'])) . "\n";
                            $files_text .= "👀 بازدید: " . $file['views'] . " | 📥 دانلود: " . $file['downloads'] . "\n\n";
                        }
                        sendMessage($chat_id, $files_text);
                    } else {
                        sendMessage($chat_id, "❌ فایلی با این کد جستجو یافت نشد.");
                    }
                    setUserStep($from_id, 'none');
                }
                break;
                
            case 'channel':
                if ($action == 'username' && preg_match('/^@[\w_]{5,}$/', $text)) {
                    if (addForceJoinChannel($text, $from_id)) {
                        sendMessage($chat_id, "✅ کانال $text با موفقیت به لیست عضویت اجباری اضافه شد.");
                    } else {
                        sendMessage($chat_id, "❌ خطا در افزودن کانال. ممکن است قبلاً اضافه شده باشد.");
                    }
                    setUserStep($from_id, 'none');
                } else {
                    sendMessage($chat_id, "فرمت یوزرنیم اشتباه است. با @ وارد کنید (مثال: @MyChannel) یا با /cancel لغو کنید.");
                }
                break;
                
            case 'multiple':
                if ($action == 'files') {
                    if ($text == '/done') {
                        $temp_data = json_decode($user['temp_data'], true);
                        if (isset($temp_data['files']) && !empty($temp_data['files'])) {
                            $uploaded_count = 0;
                            $bot_username = bot('getMe')['result']['username'];
                            
                            foreach ($temp_data['files'] as $file_info) {
                                $public_link = generateRandomString();
                                $search_code = generateSearchCode();
                                
                                // تنظیمات پیش‌فرض
                                $auto_delete = getSetting('auto_delete_default') ?: 0;
                                $forward_lock = getSetting('forward_lock_default') == '1' ? 1 : 0;
                                
                                $stmt = $db->prepare("INSERT INTO files (file_id, file_unique_id, file_type, file_name, file_size, public_link, uploader_id, search_code, auto_delete_seconds, forward_lock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("ssssssssii", $file_info['file_id'], $file_info['file_unique_id'], $file_info['file_type'], $file_info['file_name'], $file_info['file_size'], $public_link, $from_id, $search_code, $auto_delete, $forward_lock);
                                
                                if ($stmt->execute()) {
                                    $uploaded_count++;
                                }
                            }
                            
                            sendMessage($chat_id, "✅ تعداد $uploaded_count فایل با موفقیت آپلود شد!");
                        } else {
                            sendMessage($chat_id, "❌ هیچ فایلی آپلود نشد.");
                        }
                        setUserStep($from_id, 'none');
                    } else {
                        sendMessage($chat_id, "لطفاً فایل ارسال کنید یا برای پایان /done را بزنید.");
                    }
                }
                break;
                
            case 'password':
                $file_id = intval($step_parts[2]);
                $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND status = 'active'");
                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                $file = $stmt->get_result()->fetch_assoc();
                
                if ($file && $file['password'] == $text) {
                    // ارسال فایل
                    $file_type = $file['file_type'];
                    $method = 'send' . ucfirst($file_type);
                    
                    $params = [
                        'chat_id' => $chat_id,
                        $file_type => $file['file_id'],
                        'caption' => $file['caption']
                    ];
                    
                    if ($file['forward_lock']) {
                        $params['protect_content'] = true;
                    }
                    
                    bot($method, $params);
                    
                    // بروزرسانی آمار دانلود
                    updateFileDownloads($file_id);
                    
                    sendMessage($chat_id, "✅ رمز عبور صحیح است. فایل ارسال شد!");
                    
                    // اگر فایل دارای حذف خودکار است
                    if ($file['auto_delete_seconds'] && $file['auto_delete_seconds'] > 0) {
                        sendMessage($chat_id, "⚠️ این فایل بعد از {$file['auto_delete_seconds']} ثانیه حذف خواهد شد. لطفاً آن را ذخیره کنید.");
                    }
                } else {
                    sendMessage($chat_id, "❌ رمز عبور اشتباه است. لطفاً دوباره تلاش کنید.");
                }
                setUserStep($from_id, 'none');
                break;

            case 'comment':
                $file_id_to_comment = intval($step_parts[2]);
                $file_owner_stmt = $db->prepare("SELECT uploader_id FROM files WHERE id = ?");
                $file_owner_stmt->bind_param("i", $file_id_to_comment);
                $file_owner_stmt->execute();
                $uploader_id = $file_owner_stmt->get_result()->fetch_assoc()['uploader_id'] ?? ADMIN_ID;

                $user_info = $message->from;
                $user_full_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));
                
                $comment_text = "یک نظر جدید برای فایل شما ارسال شد:\n\n👤 کاربر: [$user_full_name](tg://user?id=$from_id)\n💬 نظر: $text";
                
                sendMessage($uploader_id, $comment_text);
                if ($uploader_id != ADMIN_ID) {
                    sendMessage(ADMIN_ID, $comment_text);
                }
                
                setUserStep($from_id, 'none');
                sendMessage($chat_id, "نظر شما با موفقیت برای مدیر ارسال شد. ممنون!");
                break;
        }
    }
    exit();
}