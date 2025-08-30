<?php

// ==================== تنظيمات اوليه ربات (مهم) ====================
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
    $channel = getSetting('force_join_channel');
    if (!$channel || $channel == '@YourChannelUsername') return true;
    try {
        $status = bot('getChatMember', ['chat_id' => $channel, 'user_id' => $user_id])['result']['status'] ?? 'left';
        return in_array($status, ['member', 'administrator', 'creator']);
    } catch (Exception $e) { return false; }
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

function sendFileWithButtons($chat_id, $file) {
    $keyboard = ['inline_keyboard' => []];
    $buttons = [];
    if (getSetting('show_views') == 'true') $buttons[] = ['text' => '👀 ' . $file['views'], 'callback_data' => 'noop'];
    if (getSetting('show_likes') == 'true') $buttons[] = ['text' => '👍 ' . $file['likes'], 'callback_data' => 'like_' . $file['id']];
    if (getSetting('show_comments') == 'true') $buttons[] = ['text' => '💬 ارسال نظر', 'callback_data' => 'comment_' . $file['id']];
    if (!empty($buttons)) $keyboard['inline_keyboard'][] = $buttons;
    
    $file_type = $file['file_type'];
    $method = 'send' . ucfirst($file_type);
    
    bot($method, [
        'chat_id' => $chat_id,
        $file_type => $file['file_id'],
        'caption' => $file['caption'],
        'reply_markup' => json_encode($keyboard)
    ]);
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
    $channel = getSetting('force_join_channel');
    $channel_link = 'https://t.me/' . str_replace('@', '', $channel);
    $keyboard = ['inline_keyboard' => [[['text' => 'عضویت در کانال', 'url' => $channel_link]], [['text' => '✅ بررسی عضویت', 'callback_data' => 'check_join']]]];
    sendMessage($chat_id, "برای استفاده از ربات، لطفاً ابتدا در کانال ما عضو شوید:\n$channel\n\nسپس دکمه بررسی را بزنید.", $keyboard);
    exit();
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
                        [['text' => '📂 مدیریت فایل‌ها', 'callback_data' => 'admin_files_0']],
                        [['text' => '⚙️ تنظیمات ربات', 'callback_data' => 'admin_settings']],
                        [['text' => '👥 مدیریت ادمین‌ها', 'callback_data' => 'admin_admins']],
                        [['text' => '📊 آمار', 'callback_data' => 'admin_stats']]
                    ]];
                    editMessageText($chat_id, $message_id, "به پنل مدیریت خوش آمدید. یکی از گزینه‌ها را انتخاب کنید:", $admin_keyboard);
                    break;
                case 'upload':
                    setUserStep($from_id, 'awaiting_file');
                    answerCallbackQuery($callback_query->id);
                    editMessageText($chat_id, $message_id, "لطفاً فایل مورد نظر خود را ارسال کنید (عکس، ویدیو، داکیومنت و...).");
                    break;
                case 'settings':
                    $likes_status = getSetting('show_likes') == 'true' ? '✅' : '❌';
                    $views_status = getSetting('show_views') == 'true' ? '✅' : '❌';
                    $comments_status = getSetting('show_comments') == 'true' ? '✅' : '❌';
                    $settings_keyboard = [ 'inline_keyboard' => [
                        [['text' => "تغییر متن استارت", 'callback_data' => 'admin_set_start']],
                        [['text' => "تغییر کانال عضویت", 'callback_data' => 'admin_set_channel']],
                        [['text' => "$likes_status دکمه لایک", 'callback_data' => 'admin_toggle_likes']],
                        [['text' => "$views_status دکمه بازدید", 'callback_data' => 'admin_toggle_views']],
                        [['text' => "$comments_status دکمه نظر", 'callback_data' => 'admin_toggle_comments']],
                        [['text' => ' بازگشت', 'callback_data' => 'admin_panel']]
                    ]];
                    editMessageText($chat_id, $message_id, "تنظیمات ربات:", $settings_keyboard);
                    break;
                case 'toggle':
                    $setting_name = 'show_' . $parts[2];
                    $current_value = getSetting($setting_name);
                    updateSetting($setting_name, $current_value == 'true' ? 'false' : 'true');
                    answerCallbackQuery($callback_query->id, 'تنظیمات با موفقیت تغییر کرد.');
                    
                    // Redraw settings panel
                    $likes_status = getSetting('show_likes') == 'true' ? '✅' : '❌';
                    $views_status = getSetting('show_views') == 'true' ? '✅' : '❌';
                    $comments_status = getSetting('show_comments') == 'true' ? '✅' : '❌';
                    $settings_keyboard = [ 'inline_keyboard' => [
                        [['text' => "تغییر متن استارت", 'callback_data' => 'admin_set_start']],
                        [['text' => "تغییر کانال عضویت", 'callback_data' => 'admin_set_channel']],
                        [['text' => "$likes_status دکمه لایک", 'callback_data' => 'admin_toggle_likes']],
                        [['text' => "$views_status دکمه بازدید", 'callback_data' => 'admin_toggle_views']],
                        [['text' => "$comments_status دکمه نظر", 'callback_data' => 'admin_toggle_comments']],
                        [['text' => ' بازگشت', 'callback_data' => 'admin_panel']]
                    ]];
                    editMessageText($chat_id, $message_id, "تنظیمات ربات:", $settings_keyboard);
                    break;
                case 'set':
                    answerCallbackQuery($callback_query->id);
                    if ($parts[2] == 'start') {
                        setUserStep($from_id, 'awaiting_start_text');
                        editMessageText($chat_id, $message_id, "لطفاً متن جدید خوشامدگویی (/start) را ارسال کنید:");
                    } elseif ($parts[2] == 'channel') {
                        setUserStep($from_id, 'awaiting_channel_username');
                        editMessageText($chat_id, $message_id, "لطفاً یوزرنیم کانال جدید را با @ ارسال کنید (مثال: @MyChannel):");
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
        [['text' => '📂 مدیریت فایل‌ها', 'callback_data' => 'admin_files_0']],
        [['text' => '⚙️ تنظیمات ربات', 'callback_data' => 'admin_settings']],
        [['text' => '👥 مدیریت ادمین‌ها', 'callback_data' => 'admin_admins']],
        [['text' => '📊 آمار', 'callback_data' => 'admin_stats']]
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
                    
                    $stmt = $db->prepare("INSERT INTO files (file_id, file_unique_id, file_type, caption, public_link, uploader_id, expire_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssis", $temp_data['file_id'], $temp_data['file_unique_id'], $temp_data['file_type'], $temp_data['caption'], $public_link, $from_id, $expire_time);
                    $stmt->execute();
                    
                    $bot_username = bot('getMe')['result']['username'];
                    $share_link = "https://t.me/$bot_username?start=$public_link";
                    
                    sendMessage($chat_id, "✅ فایل با موفقیت آپلود شد!\n\nلینک اشتراک‌گذاری:\n`$share_link`");
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
            case 'channel':
                if ($action == 'username' && preg_match('/^@[\w_]{5,}$/', $text)) {
                    updateSetting('force_join_channel', $text);
                    setUserStep($from_id, 'none');
                    sendMessage($chat_id, "✅ کانال عضویت اجباری به $text تغییر یافت.");
                } else {
                    sendMessage($chat_id, "فرمت یوزرنیم اشتباه است. با @ وارد کنید (مثال: @MyChannel) یا با /cancel لغو کنید.");
                }
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