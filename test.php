<?php
// سورس کد بات تلگرامی جنگ - نسخه اصلاح‌شده با رفع باگ‌ها و اضافه کردن بخش اتحاد
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

define('BOT_TOKEN', '8114188003:AAFZU5QDdW2OE93hPxIOwIqGQL2G3FRiMqc');
define('MAIN_ADMIN_ID', 5641303137);
define('DB_HOST', 'localhost');
define('DB_USER', 'dakallli_ModernWar');
define('DB_PASS', 'hosyarww123');
define('DB_NAME', 'dakallli_ModernWar');
define('CHANNEL_ID', '@xgxyxyxyxy');
date_default_timezone_set('Asia/Tehran');

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    error_log("DB Connection Failed: " . $db->connect_error);
    die("Database connection failed.");
}
$db->set_charset("utf8mb4");

$update = json_decode(file_get_contents("php://input"), TRUE);
if (!$update) exit();

if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $first_name = $message["from"]["first_name"];
    $username = $message["from"]["username"] ?? "ندارد";
    $text = $message["text"] ?? "";
    $message_id = $message["message_id"];
    $photo = $message['photo'] ?? null;
    $caption = $message['caption'] ?? '';
    $forward_from = $message['forward_from'] ?? null;
} elseif (isset($update["callback_query"])) {
    $callback_query = $update["callback_query"];
    $chat_id = $callback_query["message"]["chat"]["id"];
    $user_id = $callback_query["from"]["id"];
    $first_name = $callback_query["from"]["first_name"];
    $username = $callback_query["from"]["username"] ?? "ندارد";
    $data = $callback_query["data"];
    $message_id = $callback_query["message"]["message_id"];
    $callback_query_id = $callback_query['id'];
    answerCallbackQuery($callback_query_id);
} else {
    exit();
}

function apiRequest($method, $parameters) {
    $parameters["method"] = $method;
    $handle = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/');
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    $response = curl_exec($handle);
    curl_close($handle);
    return json_decode($response, true);
}

function sendMessage($chat_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    return apiRequest('sendMessage', $params);
}

function editMessage($chat_id, $message_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    return apiRequest('editMessageText', $params);
}

function sendPhoto($chat_id, $photo_id, $caption = '', $keyboard = null, $parse_mode = 'HTML') {
    $params = ['chat_id' => $chat_id, 'photo' => $photo_id, 'caption' => $caption, 'parse_mode' => $parse_mode];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    return apiRequest('sendPhoto', $params);
}

function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false) {
    return apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert]);
}

function query($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        error_log("DB Prepare failed: " . $db->error . " | SQL: " . $sql);
        return false;
    }
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("DB Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function getUser($user_id) {
    $result = query("SELECT * FROM users WHERE telegram_id = ?", [$user_id]);
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}

function registerOrUpdateUser($user_id, $first_name, $username) {
    $user = getUser($user_id);
    if ($user) {
        query("UPDATE users SET first_name = ?, username = ? WHERE telegram_id = ?", [$first_name, $username, $user_id]);
    } else {
        query("INSERT INTO users (telegram_id, first_name, username) VALUES (?, ?, ?)", [$user_id, $first_name, $username]);
    }
}

function setUserState($user_id, $state, $data = null) {
    $json_data = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    query("UPDATE users SET current_state = ?, state_data = ? WHERE telegram_id = ?", [$state, $json_data, $user_id]);
}

function getButtonLabel($key, $default) {
    $result = query("SELECT button_label, is_enabled FROM button_settings WHERE button_key = ?", [$key]);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['is_enabled'] ? $row['button_label'] : null;
    }
    query("INSERT INTO button_settings (button_key, button_label, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE button_label=?", [$key, $default, $default]);
    return $default;
}

function isAdmin($user_id) {
    if ($user_id == MAIN_ADMIN_ID) return true;
    $result = query("SELECT * FROM admins WHERE telegram_id = ?", [$user_id]);
    return $result && $result->num_rows > 0;
}

function hasPermission($user_id, $permission) {
    if ($user_id == MAIN_ADMIN_ID) return true;
    $result = query("SELECT permissions FROM admins WHERE telegram_id = ?", [$user_id]);
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $permissions = json_decode($admin['permissions'], true);
        return is_array($permissions) && (in_array($permission, $permissions) || in_array('all', $permissions));
    }
    return false;
}

function notifyAdmins($permission_needed, $message_text) {
    $notification_text = "🔔 " . $message_text;
    sendMessage(MAIN_ADMIN_ID, $notification_text);
    $result = query("SELECT telegram_id, permissions FROM admins");
    if ($result && $result->num_rows > 0) {
        while ($admin = $result->fetch_assoc()) {
            if ($admin['telegram_id'] != MAIN_ADMIN_ID) {
                $permissions = json_decode($admin['permissions'], true);
                if (is_array($permissions) && (in_array($permission_needed, $permissions) || in_array('all', $permissions))) {
                    sendMessage($admin['telegram_id'], $notification_text);
                }
            }
        }
    }
}

function makeUserLink($user_id, $name) {
    return "<a href='tg://user?id={$user_id}'>".htmlspecialchars($name)."</a>";
}

function getPaginationKeyboard($base_callback, $current_page, $total_items, $per_page = 5) {
    $total_pages = ceil($total_items / $per_page);
    $keyboard = [];
    if ($total_pages > 1) {
        $row = [];
        if ($current_page > 1) {
            $row[] = ['text' => '◀️ قبلی', 'callback_data' => $base_callback . '_p' . ($current_page - 1)];
        }
        $row[] = ['text' => "صفحه $current_page از $total_pages", 'callback_data' => 'noop'];
        if ($current_page < $total_pages) {
            $row[] = ['text' => 'بعدی ▶️', 'callback_data' => $base_callback . '_p' . ($current_page + 1)];
        }
        $keyboard[] = $row;
    }
    return $keyboard;
}

function getMainMenu($user_id) {
    $user = getUser($user_id);
    $buttons = [];
    $row = [];
    $button_map = [
        'lashkar_keshi' => 'لشکر کشی', 'hamle_mooshaki' => 'حمله موشکی',
        'defa' => 'دفاع', 'rolls' => 'رول‌ها',
        'bayan_ie' => 'بیانیه', 'elam_jang' => 'اعلام جنگ',
        'list_darayi' => 'لیست دارایی', 'support' => 'پشتیبانی',
        'alliance' => 'اتحاد'
    ];

    if (!$user || !$user['is_registered'] || $user['is_banned']) {
        $support_label = getButtonLabel('support', 'پشتیبانی');
        return $support_label ? ['inline_keyboard' => [[['text' => $support_label, 'callback_data' => 'support']]]] : null;
    }

    foreach ($button_map as $key => $default_label) {
        $label = getButtonLabel($key, $default_label);
        if ($label) {
            $row[] = ['text' => $label, 'callback_data' => str_replace('_', '', $key)];
            if (count($row) == 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
    }
    if (!empty($row)) $buttons[] = $row;
    return ['inline_keyboard' => $buttons];
}

function getAdminPanelKeyboard($user_id) {
    $keyboard = [];
    if(hasPermission($user_id, 'support')) $keyboard[] = [['text' => '✉️ پیام‌های پشتیبانی', 'callback_data' => 'admin_support_p1']];
    if(hasPermission($user_id, 'users')) $keyboard[] = [['text' => '👥 مدیریت کاربران', 'callback_data' => 'admin_users']];
    if(hasPermission($user_id, 'military')) $keyboard[] = [['text' => '⚔️ اقدامات نظامی', 'callback_data' => 'admin_military']];
    if(hasPermission($user_id, 'declarations')) $keyboard[] = [['text' => '📜 بیانیه و اعلام جنگ', 'callback_data' => 'admin_declarations']];
    if(hasPermission($user_id, 'rolls')) $keyboard[] = [['text' => '🎲 رول‌ها', 'callback_data' => 'admin_rolls_p1']];
    if(hasPermission($user_id, 'assets')) $keyboard[] = [['text' => '💰 مدیریت دارایی‌ها', 'callback_data' => 'admin_assets_p1']];
    if($user_id == MAIN_ADMIN_ID) {
        $keyboard[] = [['text' => '⚙️ تنظیمات دکمه‌ها', 'callback_data' => 'admin_buttons']];
        $keyboard[] = [['text' => '👑 مدیریت ادمین‌ها', 'callback_data' => 'admin_admins_p1']];
    }
    if(hasPermission($user_id, 'lottery')) $keyboard[] = [['text' => '🎉 گردونه شانس', 'callback_data' => 'admin_lottery']];
    if(hasPermission($user_id, 'alliance')) $keyboard[] = [['text' => '🤝 مدیریت اتحادها', 'callback_data' => 'admin_alliances_p1']];
    
    return ['inline_keyboard' => $keyboard];
}

function getAdminPermissionsKeyboard($target_id, $current_perms_json) {
    $current_perms = json_decode($current_perms_json, true) ?: [];
    $permissions_map = [
        'support' => 'پشتیبانی', 'users' => 'کاربران', 'military' => 'اقدامات نظامی',
        'declarations' => 'بیانیه‌ها', 'rolls' => 'رول‌ها', 'assets' => 'دارایی‌ها',
        'lottery' => 'گردونه شانس', 'alliance' => 'اتحادها', 'all' => 'دسترسی کامل'
    ];
    $keyboard_buttons = [];
    $row = [];
    foreach ($permissions_map as $key => $label) {
        $is_set = in_array($key, $current_perms);
        $icon = $is_set ? '✅' : '☑️';
        $row[] = ['text' => $icon . ' ' . $label, 'callback_data' => 'toggle_perm_' . $target_id . '_' . $key];
        if (count($row) == 2) {
            $keyboard_buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) $keyboard_buttons[] = $row;
    $keyboard_buttons[] = [['text' => '💾 ذخیره و بازگشت', 'callback_data' => 'admin_admins_p1']];
    return ['inline_keyboard' => $keyboard_buttons];
}

function getButtonSettingsKeyboard() {
    $buttons = query("SELECT * FROM button_settings");
    $keyboard = [];
    while ($btn = $buttons->fetch_assoc()) {
        $status = $btn['is_enabled'] ? '✅' : '❌';
        $keyboard[] = [
            ['text' => $status . ' ' . $btn['button_label'], 'callback_data' => 'admin_toggle_button_' . $btn['button_key']],
            ['text' => '✏️ تغییر نام', 'callback_data' => 'admin_rename_button_' . $btn['button_key']]
        ];
    }
    $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
    return ['inline_keyboard' => $keyboard];
}

registerOrUpdateUser($user_id, $first_name, $username);
$user_data = getUser($user_id);

if ($user_data['is_banned']) {
    sendMessage($chat_id, "شما از ربات بن شده‌اید و دسترسی ندارید.");
    exit();
}

if ($user_data && $user_data['current_state'] && isset($text)) {
    $state = $user_data['current_state'];
    $state_data = json_decode($user_data['state_data'], true);

    switch ($state) {
        case 'awaiting_support_message':
            $user_link = makeUserLink($user_id, $first_name);
            $message_content = "📩 <b>پیام پشتیبانی جدید</b>\n\n" .
                               "👤 <b>از طرف:</b> " . $user_link . "\n" .
                               "🆔 <b>آیدی:</b> <code>" . $user_id . "</code>\n\n" .
                               "📝 <b>متن پیام:</b>\n" . htmlspecialchars($text);
            query("INSERT INTO support_messages (user_id, message_text) VALUES (?, ?)", [$user_id, $message_content]);
            sendMessage($chat_id, "پیام شما با موفقیت برای پشتیبانی ارسال شد.");
            setUserState($user_id, null, null);
            notifyAdmins('support', "یک پیام پشتیبانی تازه دارید.");
            exit();

        case 'awaiting_military_action':
            $action_type = $state_data['type'];
            $action_label = $state_data['label'];
            $file_id = $photo ? end($photo)['file_id'] : null;
            $file_type = $photo ? 'photo' : null;
            $message_text = $photo ? $caption : $text;
            query("INSERT INTO military_actions (user_id, action_type, message_text, file_id, file_type) VALUES (?, ?, ?, ?, ?)", [$user_id, $action_type, $message_text, $file_id, $file_type]);
            sendMessage($chat_id, "اقدام «{$action_label}» شما با موفقیت ثبت و برای ادمین ارسال شد.");
            setUserState($user_id, null, null);
            notifyAdmins('military', "یک اقدام نظامی جدید ثبت شد: " . $action_label);
            exit();

        case 'awaiting_declaration':
            $declaration_type = $state_data['type'];
            $declaration_label = $state_data['label'];
            $file_id = $photo ? end($photo)['file_id'] : null;
            $file_type = $photo ? 'photo' : null;
            $message_text = $photo ? $caption : $text;
            query("INSERT INTO declarations (user_id, declaration_type, message_text, file_id, file_type) VALUES (?, ?, ?, ?, ?)", [$user_id, $declaration_type, $message_text, $file_id, $file_type]);
            sendMessage($chat_id, "پیام «{$declaration_label}» شما با موفقیت ثبت و برای ادمین ارسال شد.");
            setUserState($user_id, null, null);
            notifyAdmins('declarations', "یک {$declaration_label} جدید ثبت شد.");
            exit();

        case 'awaiting_roll':
            query("INSERT INTO rolls (user_id, roll_text) VALUES (?, ?)", [$user_id, $text]);
            sendMessage($chat_id, "رول شما با موفقیت برای بررسی ارسال شد.");
            setUserState($user_id, null, null);
            notifyAdmins('rolls', "یک رول جدید برای بررسی ثبت شد.");
            exit();

        case 'awaiting_user_to_register':
        case 'awaiting_ban_user':
        case 'awaiting_unban_user':
        case 'awaiting_admin_to_add':
            $target_id = 0;
            $target_first_name = 'کاربر';
            $target_username = 'ندارد';
            if ($forward_from) {
                $target_id = $forward_from['id'];
                $target_first_name = $forward_from['first_name'];
                $target_username = $forward_from['username'] ?? 'ندارد';
            } elseif (is_numeric($text)) {
                $target_id = (int)$text;
            }
            if ($target_id > 0) {
                registerOrUpdateUser($target_id, $target_first_name, $target_username);
                if ($state == 'awaiting_user_to_register') {
                    $target_user = getUser($target_id);
                    if ($target_user && $target_user['is_registered']) {
                        sendMessage($chat_id, "این کاربر قبلا ثبت‌نام شده است.");
                    } else {
                        setUserState($user_id, 'awaiting_country_name', $target_id);
                        sendMessage($chat_id, "کاربر با آیدی <code>$target_id</code> یافت شد. لطفاً نام کشور مورد نظر برای این کاربر را وارد کنید:");
                    }
                } elseif ($state == 'awaiting_ban_user') {
                    if ($target_id == MAIN_ADMIN_ID) {
                        sendMessage($chat_id, "شما نمی‌توانید ادمین اصلی را بن کنید!");
                    } else {
                        query("UPDATE users SET is_banned = 1 WHERE telegram_id = ?", [$target_id]);
                        sendMessage($chat_id, "کاربر با آیدی <code>$target_id</code> با موفقیت از ربات بن شد.");
                        sendMessage($target_id, "شما توسط ادمین از ربات بن شدید.");
                    }
                } elseif ($state == 'awaiting_unban_user') {
                    query("UPDATE users SET is_banned = 0 WHERE telegram_id = ?", [$target_id]);
                    sendMessage($chat_id, "کاربر با آیدی <code>$target_id</code> با موفقیت از بن خارج شد.");
                    sendMessage($target_id, "شما توسط ادمین از بن خارج شدید و دوباره به ربات دسترسی دارید.");
                } elseif ($state == 'awaiting_admin_to_add') {
                    if ($target_id == MAIN_ADMIN_ID) {
                        sendMessage($chat_id, "ادمین اصلی نیازی به افزودن ندارد.");
                    } elseif (isAdmin($target_id)) {
                        sendMessage($chat_id, "این کاربر در حال حاضر ادمین است. برای ویرایش دسترسی‌ها از لیست ادمین‌ها اقدام کنید.");
                    } else {
                        query("INSERT INTO admins (telegram_id, permissions, added_by) VALUES (?, ?, ?)", [$target_id, '[]', $user_id]);
                        $keyboard = getAdminPermissionsKeyboard($target_id, '[]');
                        sendMessage($chat_id, "ادمین جدید با آیدی <code>$target_id</code> اضافه شد. لطفا دسترسی‌های او را مشخص کنید:", $keyboard);
                    }
                }
                setUserState($user_id, null, null);
            } else {
                sendMessage($chat_id, "خطا: لطفاً یک پیام از کاربر فوروارد کنید یا آیدی عددی او را به درستی وارد کنید.");
            }
            exit();

        case 'awaiting_user_to_register':
    $target_id = 0;
    $target_first_name = 'کاربر';
    $target_username = 'ندارد';
    if ($forward_from) {
        $target_id = $forward_from['id'];
        $target_first_name = $forward_from['first_name'];
        $target_username = $forward_from['username'] ?? 'ندارد';
    } elseif (is_numeric($text)) {
        $target_id = (int)$text;
    }
    if ($target_id > 0) {
        registerOrUpdateUser($target_id, $target_first_name, $target_username);
        $target_user = getUser($target_id);
        if ($target_user && $target_user['is_registered']) {
            sendMessage($chat_id, "این کاربر قبلا ثبت‌نام شده است.");
            setUserState($user_id, null, null);
        } else {
            setUserState($user_id, 'awaiting_country_name', $target_id);
            sendMessage($chat_id, "کاربر با آیدی <code>$target_id</code> یافت شد. لطفاً نام کشور مورد نظر برای این کاربر را وارد کنید:");
        }
    } else {
        sendMessage($chat_id, "خطا: لطفاً یک پیام از کاربر فوروارد کنید یا آیدی عددی او را به درستی وارد کنید.");
        setUserState($user_id, null, null);
    }
    exit();

case 'awaiting_country_name':
    $target_id = (int)$state_data;
    $country_name = trim($text);
    if (empty($country_name)) {
        sendMessage($chat_id, "لطفاً یک نام کشور معتبر وارد کنید.");
        exit();
    }
    error_log("Attempting to register user $target_id with country $country_name");
    $existing_user = getUser($target_id);
    if (!$existing_user) {      
        registerOrUpdateUser($target_id, "ثبت‌شده توسط ادمین", "ندارد");
    }
    $result = query("UPDATE users SET is_registered = 1, country_name = ? WHERE telegram_id = ?", [$country_name, $target_id]);
    if ($result === false) {
        error_log("Failed to register user $target_id with country $country_name");
        sendMessage($chat_id, "خطا در ثبت کاربر. لطفاً دوباره تلاش کنید.");
    } else {
        sendMessage($chat_id, "✅ کاربر با آیدی <code>$target_id</code> با موفقیت به عنوان کشور <b>$country_name</b> ثبت شد.");
        sendMessage($target_id, "🎉 شما توسط ادمین در ربات ثبت شدید! اکنون به تمام امکانات دسترسی دارید.", getMainMenu($target_id));  
    }
    setUserState($user_id, null, null);
    exit();


        case 'awaiting_roll_cost':
            $roll_id = (int)$state_data;
            $cost = (int)$text;
            if($cost > 0){
                query("UPDATE rolls SET cost = ?, status = 'cost_proposed' WHERE id = ?", [$cost, $roll_id]);
                $roll_info_res = query("SELECT user_id FROM rolls WHERE id = ?", [$roll_id]);
                if ($roll_info_res && $roll_info_res->num_rows > 0) {
                    $roll_info = $roll_info_res->fetch_assoc();
                    sendMessage($chat_id, "هزینه برای رول با موفقیت ثبت شد و برای کاربر ارسال گردید.");
                    $keyboard = ['inline_keyboard' => [[
                        ['text' => '✅ تایید هزینه', 'callback_data' => 'roll_acceptcost_' . $roll_id],
                        ['text' => '❌ رد هزینه', 'callback_data' => 'roll_rejectcost_' . $roll_id]
                    ]]];
                    sendMessage($roll_info['user_id'], "ادمین برای رول شما هزینه <code>$cost</code> را تعیین کرده است. آیا تایید می‌کنید؟", $keyboard);
                }
                setUserState($user_id, null, null);
            } else {
                sendMessage($chat_id, "لطفا یک عدد معتبر برای هزینه وارد کنید.");
            }
            handleAdminCallback($chat_id, $message_id, $user_id, 'admin_view_roll_' . $roll_id, 1, ['admin_view_roll', $roll_id]);
            break;

        case 'awaiting_button_rename':
            $button_key = $state_data;
            query("UPDATE button_settings SET button_label = ? WHERE button_key = ?", [$text, $button_key]);
            sendMessage($chat_id, "نام دکمه با موفقیت به «$text» تغییر یافت.");
            setUserState($user_id, null, null);
            handleAdminCallback($chat_id, $message_id, $user_id, 'admin_buttons', 1, []);
            break;

        case 'awaiting_lottery_prize':
            query("INSERT INTO lottery_prizes (prize_name) VALUES (?)", [$text]);
            sendMessage($chat_id, "جایزه «$text» با موفقیت برای گردونه شانس ثبت شد.");
            setUserState($user_id, null, null);
            handleAdminCallback($chat_id, $message_id, $user_id, 'admin_lottery', 1, []);
            break;

        case 'awaiting_asset_text':
        case 'awaiting_asset_profit':
        case 'awaiting_asset_money':
            $country_name = $state_data['country_name'];
            if ($state == 'awaiting_asset_text') {
                query("INSERT INTO assets (country_name, asset_text) VALUES (?, ?) ON DUPLICATE KEY UPDATE asset_text = ?", [$country_name, $text, $text]);
                sendMessage($chat_id, "دارایی متنی کشور $country_name با موفقیت ثبت/ویرایش شد.");
            } elseif ($state == 'awaiting_asset_profit') {
                $profit = (int)$text;
                query("INSERT INTO assets (country_name, daily_profit) VALUES (?, ?) ON DUPLICATE KEY UPDATE daily_profit = ?", [$country_name, $profit, $profit]);
                sendMessage($chat_id, "سود روزانه کشور $country_name با موفقیت ثبت/ویرایش شد.");
            } elseif ($state == 'awaiting_asset_money') {
                $money = (int)$text;
                query("INSERT INTO assets (country_name, money) VALUES (?, ?) ON DUPLICATE KEY UPDATE money = ?", [$country_name, $money, $money]);
                sendMessage($chat_id, "پول کشور $country_name با موفقیت ثبت/ویرایش شد.");
            }
            setUserState($user_id, null, null);
            handleAdminCallback($chat_id, $message_id, $user_id, 'admin_assets_p1', 1, []);
            break;

        case 'awaiting_alliance_name':
            $alliance_name = trim($text);
            $check = query("SELECT * FROM alliances WHERE name = ?", [$alliance_name]);
            if ($check && $check->num_rows > 0) {
                sendMessage($chat_id, "اتحادی با این نام قبلاً وجود دارد. لطفاً نام دیگری انتخاب کنید.");
            } else {
                query("INSERT INTO alliances (name, leader_id) VALUES (?, ?)", [$alliance_name, $user_id]);
                query("INSERT INTO alliance_members (alliance_id, user_id, country_name) VALUES ((SELECT id FROM alliances WHERE name = ?), ?, ?)", [$alliance_name, $user_id, $user_data['country_name']]);
                sendMessage($chat_id, "اتحاد «$alliance_name» با موفقیت ایجاد شد. شما رهبر این اتحاد هستید.");
            }
            setUserState($user_id, null, null);
            exit();

        case 'awaiting_alliance_invite':
            $alliance_id = (int)$state_data;
            $target_id = 0;
            $target_first_name = 'کاربر';
            $target_username = 'ندارد';
            if ($forward_from) {
                $target_id = $forward_from['id'];
                $target_first_name = $forward_from['first_name'];
                $target_username = $forward_from['username'] ?? 'ندارد';
            } elseif (is_numeric($text)) {
                $target_id = (int)$text;
            }
            if ($target_id > 0 && $target_id != $user_id) {
                $target_user = getUser($target_id);
                if ($target_user && $target_user['is_registered'] && !$target_user['is_banned']) {
                    $alliance_res = query("SELECT name FROM alliances WHERE id = ? AND leader_id = ?", [$alliance_id, $user_id]);
                    if ($alliance_res && $alliance_res->num_rows > 0) {
                        $alliance = $alliance_res->fetch_assoc();
                        $member_count = query("SELECT COUNT(*) as total FROM alliance_members WHERE alliance_id = ?", [$alliance_id])->fetch_assoc()['total'];
                        if ($member_count >= 4) {
                            sendMessage($chat_id, "اتحاد پر است و نمی‌توانید عضو جدید دعوت کنید.");
                        } else {
                            $check_member = query("SELECT * FROM alliance_members WHERE alliance_id = ? AND user_id = ?", [$alliance_id, $target_id]);
                            if ($check_member && $check_member->num_rows > 0) {
                                sendMessage($chat_id, "این کاربر قبلاً در اتحاد است.");
                            } else {
                                $keyboard = ['inline_keyboard' => [
                                    [['text' => '✅ بله', 'callback_data' => 'join_alliance_' . $alliance_id]],
                                    [['text' => '❌ خیر', 'callback_data' => 'decline_alliance_' . $alliance_id]]
                                ]];
                                sendMessage($target_id, "شما به اتحاد «" . $alliance['name'] . "» دعوت شده‌اید. آیا می‌خواهید بپیوندید؟", $keyboard);
                                sendMessage($chat_id, "دعوت‌نامه برای کاربر با آیدی <code>$target_id</code> ارسال شد.");
                            }
                        }
                    }
                } else {
                    sendMessage($chat_id, "کاربر مورد نظر ثبت‌نام نشده یا بن شده است.");
                }
            } else {
                sendMessage($chat_id, "لطفاً آیدی معتبر یا پیام فوروارد شده از کاربر دیگر ارسال کنید.");
            }
            setUserState($user_id, null, null);
            exit();

        case 'awaiting_alliance_slogan':
            $alliance_id = (int)$state_data;
            query("UPDATE alliances SET slogan = ? WHERE id = ?", [$text, $alliance_id]);
            sendMessage($chat_id, "شعار اتحاد با موفقیت به «$text» تغییر یافت.");
            setUserState($user_id, null, null);
            handleAdminCallback($chat_id, $message_id, $user_id, 'alliance_view_' . $alliance_id, 1, ['alliance_view', $alliance_id]);
            exit();

        case 'awaiting_alliance_edit_member':
            $data = json_decode($user_data['state_data'], true);
            $alliance_id = (int)$data['alliance_id'];
            $member_id = (int)$data['member_id'];
            query("UPDATE alliance_members SET country_name = ? WHERE alliance_id = ? AND user_id = ?", [$text, $alliance_id, $member_id]);
            sendMessage($chat_id, "نام کشور عضو با موفقیت به «$text» تغییر یافت.");
            setUserState($user_id, null, null);
            handleAdminCallback($chat_id, $message_id, $user_id, 'alliance_view_' . $alliance_id, 1, ['alliance_view', $alliance_id]);
            exit();
    }
}

if (isset($text)) {
    if ($text == "/start") {
        sendMessage($chat_id, "به ربات جنگ خوش آمدید. از دکمه‌های زیر استفاده کنید:", getMainMenu($user_id));
        exit();
    } elseif ($text == "/panel" && isAdmin($user_id)) {
        sendMessage($chat_id, "به پنل مدیریت خوش آمدید.", getAdminPanelKeyboard($user_id));
        exit();
    }
}

if (isset($data)) {
    $user_actions = ['support', 'lashkarkeshi', 'hamlemooshaki', 'defa', 'rolls', 'bayan_ie', 'elamjang', 'listdarayi', 'mainmenu', 'roll_acceptcost', 'roll_rejectcost', 'alliance', 'create_alliance', 'view_alliances', 'join_alliance', 'decline_alliance', 'leave_alliance', 'invite_alliance', 'edit_slogan', 'edit_member'];
    $action_part = explode('_', $data)[0];

    if (in_array($action_part, $user_actions)) {
        if (!$user_data['is_registered'] && !in_array($action_part, ['support', 'mainmenu'])) {
            answerCallbackQuery($callback_query_id, "شما هنوز توسط ادمین ثبت‌نام نشده‌اید.", true);
            exit();
        }

        switch ($action_part) {
            case 'mainmenu':
                setUserState($user_id, null, null);
                editMessage($chat_id, $message_id, "منوی اصلی:", getMainMenu($user_id));
                break;

            case 'support':
                setUserState($user_id, 'awaiting_support_message');
                editMessage($chat_id, $message_id, "لطفاً پیام خود را برای ارسال به پشتیبانی بنویسید و ارسال کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]]]);
                break;

            case 'lashkarkeshi':
            case 'hamlemooshaki':
            case 'defa':
                $action_map = ['lashkarkeshi' => 'lashkar_keshi', 'hamlemooshaki' => 'hamle_mooshaki', 'defa' => 'defa'];
                $db_action = $action_map[$action_part];
                $label = getButtonLabel($db_action, '');
                setUserState($user_id, 'awaiting_military_action', ['type' => $db_action, 'label' => $label]);
                editMessage($chat_id, $message_id, "لطفا متن یا عکس مربوط به «{$label}» را ارسال کنید.", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]]]);
                break;

            case 'bayan_ie':
            case 'elamjang':
                $action_map = ['bayan_ie' => 'bayan_ie', 'elamjang' => 'elam_jang'];
                $db_action = $action_map[$action_part];
                $label = getButtonLabel($db_action, '');
                $prompt = ($db_action == 'bayan_ie') ? "لطفا متن یا عکس بیانیه خود را ارسال کنید." : "لطفا اعلام جنگ خود را با فرمت زیر ارسال کنید:\n\n<code>نام کشور حمله کننده:\nنام کشور دفاع کننده:</code>";
                setUserState($user_id, 'awaiting_declaration', ['type' => $db_action, 'label' => $label]);
                editMessage($chat_id, $message_id, $prompt, ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]]], 'HTML');
                break;

            case 'rolls':
                setUserState($user_id, 'awaiting_roll');
                editMessage($chat_id, $message_id, "لطفا متن رول خود را ارسال کنید.", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]]]);
                break;

            case 'listdarayi':
                if($user_data && $user_data['country_name']){
                    $assets_res = query("SELECT asset_text, daily_profit, money FROM assets WHERE country_name = ?", [$user_data['country_name']]);
                    $asset_text = ($assets_res && $assets_res->num_rows > 0) ? $assets_res->fetch_assoc() : ['asset_text' => "دارایی ثبت نشده", 'daily_profit' => 0, 'money' => 0];
                    $text = "<b>لیست دارایی‌های کشور {$user_data['country_name']}</b>\n\n" .
                            "📝 <b>دارایی‌ها:</b> {$asset_text['asset_text']}\n" .
                            "💸 <b>سود روزانه:</b> دست نزن زشته\n" .
                            "💰 <b>پول شما:</b> دست نزن زشته";
                    editMessage($chat_id, $message_id, $text, ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]]]);
                }
                break;

            case 'roll_acceptcost':
                $roll_id = (int)explode('_', $data)[1];
                $roll_res = query("SELECT * FROM rolls WHERE id = ? AND user_id = ?", [$roll_id, $user_id]);
                if($roll_res && $roll_res->num_rows > 0){
                    $roll = $roll_res->fetch_assoc();
                    if($roll['status'] == 'cost_proposed'){
                        query("UPDATE rolls SET status = 'pending' WHERE id = ?", [$roll_id]);
                        editMessage($chat_id, $message_id, "شما هزینه رول را تایید کردید. رول مجددا برای بررسی نهایی به ادمین ارسال شد.");
                        notifyAdmins('rolls', "کاربر ".makeUserLink($user_id, $first_name)." هزینه رول #$roll_id را تایید کرد.");
                    } else {
                        editMessage($chat_id, $message_id, "این درخواست دیگر معتبر نیست.");
                    }
                }
                break;

            case 'roll_rejectcost':
                $roll_id = (int)explode('_', $data)[1];
                $roll_res = query("SELECT * FROM rolls WHERE id = ? AND user_id = ?", [$roll_id, $user_id]);
                if($roll_res && $roll_res->num_rows > 0){
                    $roll = $roll_res->fetch_assoc();
                    if($roll['status'] == 'cost_proposed'){
                        query("DELETE FROM rolls WHERE id = ?", [$roll_id]);
                        editMessage($chat_id, $message_id, "شما هزینه رول را رد کردید. رول حذف شد.");
                        notifyAdmins('rolls', "کاربر ".makeUserLink($user_id, $first_name)." هزینه رول #$roll_id را رد کرد و رول حذف شد.");
                    } else {
                        editMessage($chat_id, $message_id, "این درخواست دیگر معتبر نیست.");
                    }
                }
                break;

            case 'alliance':
                $keyboard = ['inline_keyboard' => [
                    [['text' => '➕ ایجاد اتحاد', 'callback_data' => 'create_alliance']],
                    [['text' => '📜 لیست اتحادها', 'callback_data' => 'view_alliances_p1']],
                    [['text' => '🔙 بازگشت', 'callback_data' => 'mainmenu']]
                ]];
                $current_alliance = query("SELECT a.id, a.name FROM alliance_members am JOIN alliances a ON am.alliance_id = a.id WHERE am.user_id = ?", [$user_id]);
                if ($current_alliance && $current_alliance->num_rows > 0) {
                    $alliance = $current_alliance->fetch_assoc();
                    $keyboard['inline_keyboard'][0][] = ['text' => '👀 مشاهده اتحاد', 'callback_data' => 'alliance_view_' . $alliance['id']];
                }
                editMessage($chat_id, $message_id, "بخش اتحاد:", $keyboard);
                break;

            case 'create_alliance':
                $check = query("SELECT * FROM alliance_members WHERE user_id = ?", [$user_id]);
                if ($check && $check->num_rows > 0) {
                    answerCallbackQuery($callback_query_id, "شما قبلاً در یک اتحاد هستید.", true);
                } else {
                    setUserState($user_id, 'awaiting_alliance_name');
                    editMessage($chat_id, $message_id, "لطفاً نام اتحاد را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'alliance']]]]);
                }
                break;

            case 'view_alliances':
                $count_res = query("SELECT COUNT(*) as total FROM alliances");
                $total = $count_res->fetch_assoc()['total'];
                $per_page = 5;
                $offset = ($page - 1) * $per_page;
                $alliances = query("SELECT id, name FROM alliances ORDER BY name ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
                $text = "لیست اتحادها:";
                $keyboard = [];
                if ($alliances && $alliances->num_rows > 0) {
                    while($a = $alliances->fetch_assoc()){
                        $keyboard[] = [['text' => $a['name'], 'callback_data' => 'alliance_view_' . $a['id']]];
                    }
                } else {
                    $text = "هیچ اتحادی وجود ندارد.";
                }
                $pagination = getPaginationKeyboard('view_alliances', $page, $total, $per_page);
                $keyboard = array_merge($keyboard, $pagination);
                $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'alliance']];
                editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
                break;

            case 'join_alliance':
                $alliance_id = (int)explode('_', $data)[1];
                $check = query("SELECT * FROM alliance_members WHERE user_id = ?", [$user_id]);
                if ($check && $check->num_rows > 0) {
                    answerCallbackQuery($callback_query_id, "شما قبلاً در یک اتحاد هستید.", true);
                } else {
                    $alliance_res = query("SELECT name FROM alliances WHERE id = ?", [$alliance_id]);
                    if ($alliance_res && $alliance_res->num_rows > 0) {
                        $alliance = $alliance_res->fetch_assoc();
                        $member_count = query("SELECT COUNT(*) as total FROM alliance_members WHERE alliance_id = ?", [$alliance_id])->fetch_assoc()['total'];
                        if ($member_count >= 4) {
                            answerCallbackQuery($callback_query_id, "این اتحاد پر است.", true);
                        } else {
                            query("INSERT INTO alliance_members (alliance_id, user_id, country_name) VALUES (?, ?, ?)", [$alliance_id, $user_id, $user_data['country_name']]);
                            sendMessage($chat_id, "شما با موفقیت به اتحاد «" . $alliance['name'] . "» پیوستید.");
                            notifyAdmins('alliance', "کاربر ".makeUserLink($user_id, $first_name)." به اتحاد «" . $alliance['name'] . "» پیوست.");
                        }
                    }
                    editMessage($chat_id, $message_id, "به منوی اصلی بازگشتید:", getMainMenu($user_id));
                }
                break;

            case 'decline_alliance':
                $alliance_id = (int)explode('_', $data)[1];
                editMessage($chat_id, $message_id, "شما دعوت به اتحاد را رد کردید.", getMainMenu($user_id));
                break;

            case 'leave_alliance':
                $alliance_id = (int)explode('_', $data)[1];
                $alliance_res = query("SELECT leader_id, name FROM alliances WHERE id = ?", [$alliance_id]);
                if ($alliance_res && $alliance_res->num_rows > 0) {
                    $alliance = $alliance_res->fetch_assoc();
                    if ($alliance['leader_id'] == $user_id) {
                        query("DELETE FROM alliances WHERE id = ?", [$alliance_id]);
                        query("DELETE FROM alliance_members WHERE alliance_id = ?", [$alliance_id]);
                        sendMessage($chat_id, "شما رهبر اتحاد بودید و اتحاد «" . $alliance['name'] . "» منحل شد.");
                        notifyAdmins('alliance', "اتحاد «" . $alliance['name'] . "» توسط رهبر منحل شد.");
                    } else {
                        query("DELETE FROM alliance_members WHERE alliance_id = ? AND user_id = ?", [$alliance_id, $user_id]);
                        sendMessage($chat_id, "شما از اتحاد «" . $alliance['name'] . "» خارج شدید.");
                    }
                }
                editMessage($chat_id, $message_id, "به منوی اصلی بازگشتید:", getMainMenu($user_id));
                break;

            case 'invite_alliance':
                $alliance_id = (int)explode('_', $data)[1];
                $alliance_res = query("SELECT leader_id FROM alliances WHERE id = ?", [$alliance_id]);
                if ($alliance_res && $alliance_res->num_rows > 0 && $alliance_res->fetch_assoc()['leader_id'] == $user_id) {
                    setUserState($user_id, 'awaiting_alliance_invite', $alliance_id);
                    editMessage($chat_id, $message_id, "لطفاً پیام کاربر مورد نظر را فوروارد کنید یا آیدی عددی او را بفرستید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'alliance_view_' . $alliance_id]]]]);
                } else {
                    answerCallbackQuery($callback_query_id, "فقط رهبر اتحاد می‌تواند عضو دعوت کند.", true);
                }
                break;

            case 'edit_slogan':
                $alliance_id = (int)explode('_', $data)[1];
                $alliance_res = query("SELECT leader_id FROM alliances WHERE id = ?", [$alliance_id]);
                if ($alliance_res && $alliance_res->num_rows > 0 && $alliance_res->fetch_assoc()['leader_id'] == $user_id) {
                    setUserState($user_id, 'awaiting_alliance_slogan', $alliance_id);
                    editMessage($chat_id, $message_id, "لطفاً شعار جدید اتحاد را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'alliance_view_' . $alliance_id]]]]);
                } else {
                    answerCallbackQuery($callback_query_id, "فقط رهبر اتحاد می‌تواند شعار را ویرایش کند.", true);
                }
                break;

            case 'edit_member':
                $alliance_id = (int)explode('_', $data)[1];
                $member_id = (int)explode('_', $data)[2];
                $alliance_res = query("SELECT leader_id FROM alliances WHERE id = ?", [$alliance_id]);
                if ($alliance_res && $alliance_res->num_rows > 0 && $alliance_res->fetch_assoc()['leader_id'] == $user_id) {
                    setUserState($user_id, 'awaiting_alliance_edit_member', ['alliance_id' => $alliance_id, 'member_id' => $member_id]);
                    editMessage($chat_id, $message_id, "لطفاً نام جدید کشور برای این عضو را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'alliance_view_' . $alliance_id]]]]);
                } else {
                    answerCallbackQuery($callback_query_id, "فقط رهبر اتحاد می‌تواند نام اعضا را ویرایش کند.", true);
                }
                break;
        }
        exit();
    }

    if (strpos($data, 'admin_') === 0 || strpos($data, 'toggle_perm_') === 0 || strpos($data, 'alliance_view_') === 0) {
        if (!isAdmin($user_id) && strpos($data, 'alliance_view_') !== 0) {
            answerCallbackQuery($callback_query_id, 'شما ادمین نیستید.', true);
            exit();
        }

        $parts = explode('_', $data);
        $page = 1;
        if (count($parts) > 1 && $parts[count($parts)-1][0] == 'p' && is_numeric(substr(end($parts), 1))) {
            $page = (int)substr(array_pop($parts), 1);
        }
        $action = implode('_', $parts);

        handleAdminCallback($chat_id, $message_id, $user_id, $action, $page, $parts);
    } elseif ($data == 'noop') {
        // Do nothing
    }
}

function handleAdminCallback($chat_id, $message_id, $admin_id, $action, $page, $parts) {
    global $callback_query_id;

    $permission_map = [
        'admin_support' => 'support', 'admin_users' => 'users', 'admin_register' => 'users', 'admin_list' => 'users', 'admin_ban' => 'users', 'admin_unban' => 'users', 'admin_delete' => 'users',
        'admin_military' => 'military', 'admin_view_military' => 'military', 'admin_delete_military' => 'military',
        'admin_declarations' => 'declarations', 'admin_view_declaration' => 'declarations', 'admin_sendchannel' => 'declarations', 'admin_delete_declaration' => 'declarations',
        'admin_rolls' => 'rolls', 'admin_approve' => 'rolls', 'admin_reject' => 'rolls', 'admin_cost' => 'rolls',
        'admin_assets' => 'assets', 'admin_edit_asset' => 'assets', 'admin_edit_profit' => 'assets', 'admin_edit_money' => 'assets',
        'admin_lottery' => 'lottery', 'admin_add_prize' => 'lottery', 'admin_start_lottery' => 'lottery', 'admin_confirm_lottery' => 'lottery',
        'admin_buttons' => 'all', 'admin_toggle_button' => 'all', 'admin_rename_button' => 'all',
        'admin_admins' => 'all', 'toggle_perm' => 'all',
        'admin_alliances' => 'alliance'
    ];
    $required_permission = null;
    foreach($permission_map as $key => $perm) {
        if (strpos($action, $key) === 0) {
            $required_permission = $perm;
            break;
        }
    }
    if ($required_permission === 'all' && $admin_id != MAIN_ADMIN_ID) {
        answerCallbackQuery($callback_query_id, 'شما به این بخش دسترسی ندارید.', true);
        return;
    }
    if ($required_permission && $required_permission !== 'all' && !hasPermission($admin_id, $required_permission) && $action !== 'alliance_view') {
        answerCallbackQuery($callback_query_id, 'شما به این بخش دسترسی ندارید.', true);
        return;
    }

    switch ($action) {
        case 'admin_panel':
            editMessage($chat_id, $message_id, "به پنل مدیریت خوش آمدید.", getAdminPanelKeyboard($admin_id));
            break;

        case 'admin_support':
            query("DELETE FROM support_messages WHERE timestamp < NOW() - INTERVAL 1 DAY");
            $count_res = query("SELECT COUNT(*) as total FROM support_messages");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $messages = query("SELECT s.*, u.first_name, u.username FROM support_messages s JOIN users u ON s.user_id = u.telegram_id ORDER BY s.timestamp ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $text = "لیست پیام‌های پشتیبانی (قدیمی‌ترین‌ها در ابتدا):";
            $keyboard = [];
            if ($messages && $messages->num_rows > 0) {
                while($msg = $messages->fetch_assoc()){
                    $date = date('Y-m-d H:i', strtotime($msg['timestamp']));
                    $keyboard[] = [['text' => 'پیام از: '.htmlspecialchars($msg['first_name'])." | {$date}", 'callback_data' => 'admin_view_support_'.$msg['id']]];
                }
            } else {
                $text = "هیچ پیام پشتیبانی برای نمایش وجود ندارد.";
            }
            $pagination = getPaginationKeyboard('admin_support', $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_view_support':
            $msg_id = (int)$parts[3];
            $msg_res = query("SELECT s.*, u.first_name, u.username FROM support_messages s JOIN users u ON s.user_id = u.telegram_id WHERE s.id = ?", [$msg_id]);
            if ($msg_res && $msg_res->num_rows > 0) {
                $message = $msg_res->fetch_assoc();
                $keyboard = ['inline_keyboard' => [
                    [['text' => '🗑 حذف پیام', 'callback_data' => 'admin_delete_support_'.$msg_id]],
                    [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'admin_support_p1']]
                ]];
                editMessage($chat_id, $message_id, $message['message_text'], $keyboard);
            }
            break;

        case 'admin_delete_support':
            $msg_id = (int)$parts[3];
            query("DELETE FROM support_messages WHERE id = ?", [$msg_id]);
            answerCallbackQuery($callback_query_id, 'پیام با موفقیت حذف شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_support', 1, []);
            break;

        case 'admin_users':
            $keyboard = ['inline_keyboard' => [
                [['text' => '➕ ثبت کاربر جدید', 'callback_data' => 'admin_register_user']],
                [['text' => '📝 لیست کاربران ثبت شده', 'callback_data' => 'admin_list_registered_p1']],
                [['text' => '🚫 بن کردن کاربر', 'callback_data' => 'admin_ban_user']],
                [['text' => '✅ آنبن کردن کاربر', 'callback_data' => 'admin_unban_user']],
                [['text' => '📜 لیست کاربران بن شده', 'callback_data' => 'admin_list_banned_p1']],
                [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']],
            ]];
            editMessage($chat_id, $message_id, "بخش مدیریت کاربران:", $keyboard);
            break;

        case 'admin_register_user':
            setUserState($admin_id, 'awaiting_user_to_register');
            editMessage($chat_id, $message_id, "برای ثبت کاربر، پیام کاربر مورد نظر را فوروارد کنید یا آیدی عددی او را بفرستید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_users']]]]);
            break;

        case 'admin_ban_user':
            setUserState($admin_id, 'awaiting_ban_user');
            editMessage($chat_id, $message_id, "برای بن کردن، پیام کاربر مورد نظر را فوروارد کنید یا آیدی عددی او را بفرستید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_users']]]]);
            break;

        case 'admin_unban_user':
            setUserState($admin_id, 'awaiting_unban_user');
            editMessage($chat_id, $message_id, "برای آنبن کردن، پیام کاربر مورد نظر را فوروارد کنید یا آیدی عددی او را بفرستید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_users']]]]);
            break;

        case 'admin_list_registered':
        case 'admin_list_banned':
            $is_banned = ($action == 'admin_list_banned');
            $where_clause = $is_banned ? "is_banned = 1" : "is_registered = 1";
            $count_res = query("SELECT COUNT(*) as total FROM users WHERE $where_clause");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $users = query("SELECT * FROM users WHERE $where_clause ORDER BY country_name ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $title = $is_banned ? "کاربران بن شده" : "کاربران ثبت شده";
            $text = "لیست $title:";
            $keyboard = [];
            if ($users && $users->num_rows > 0) {
                while($u = $users->fetch_assoc()){
                    $btn_text = htmlspecialchars($u['first_name']) . ($u['country_name'] ? " (" . htmlspecialchars($u['country_name']) . ")" : '');
                    if ($is_banned) {
                        $keyboard[] = [['text' => $btn_text, 'callback_data' => 'noop'], ['text' => '✅ آنبن', 'callback_data' => 'admin_perform_unban_' . $u['telegram_id']]];
                    } else {
                        $keyboard[] = [
                            ['text' => $btn_text, 'callback_data' => 'noop'],
                            ['text' => '🚫 بن', 'callback_data' => 'admin_perform_ban_' . $u['telegram_id']],
                            ['text' => '🗑 حذف', 'callback_data' => 'admin_delete_user_' . $u['telegram_id']]
                        ];
                    }
                }
            } else {
                $text = "هیچ کاربری در این لیست وجود ندارد.";
            }
            $pagination = getPaginationKeyboard($action, $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_users']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_delete_user':
            $target_id = (int)$parts[3];
            query("UPDATE users SET is_registered = 0, country_name = NULL WHERE telegram_id = ?", [$target_id]);
            answerCallbackQuery($callback_query_id, 'کاربر با موفقیت حذف (لغو ثبت‌نام) شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_list_registered', $page, []);
            break;

        case 'admin_perform_ban':
            $target_id = (int)$parts[3];
            if ($target_id == MAIN_ADMIN_ID) {
                answerCallbackQuery($callback_query_id, 'امکان بن کردن ادمین اصلی وجود ندارد.', true);
            } else {
                query("UPDATE users SET is_banned = 1 WHERE telegram_id = ?", [$target_id]);
                answerCallbackQuery($callback_query_id, 'کاربر با موفقیت بن شد.', false);
                sendMessage($target_id, "شما توسط ادمین از ربات بن شدید.");
                handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_list_registered', $page, []);
            }
            break;

        case 'admin_perform_unban':
            $target_id = (int)$parts[3];
            query("UPDATE users SET is_banned = 0 WHERE telegram_id = ?", [$target_id]);
            answerCallbackQuery($callback_query_id, 'کاربر با موفقیت از بن خارج شد.', false);
            sendMessage($target_id, "شما توسط ادمین از بن خارج شدید و دوباره به ربات دسترسی دارید.");
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_list_banned', $page, []);
            break;

        case 'admin_military':
            $keyboard = ['inline_keyboard' => [
                [['text' => 'لشکر کشی', 'callback_data' => 'admin_military_lashkar_keshi_p1']],
                [['text' => 'حمله موشکی', 'callback_data' => 'admin_military_hamle_mooshaki_p1']],
                [['text' => 'دفاع', 'callback_data' => 'admin_military_defa_p1']],
                [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']],
            ]];
            editMessage($chat_id, $message_id, "بخش اقدامات نظامی:", $keyboard);
            break;

        case 'admin_military_lashkar_keshi':
        case 'admin_military_hamle_mooshaki':
        case 'admin_military_defa':
            $action_type = str_replace('admin_military_', '', $action);
            $count_res = query("SELECT COUNT(*) as total FROM military_actions ma JOIN users u ON ma.user_id = u.telegram_id WHERE ma.action_type = ?", [$action_type]);
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $actions = query("SELECT ma.*, u.first_name, u.username, u.country_name FROM military_actions ma JOIN users u ON ma.user_id = u.telegram_id WHERE ma.action_type = ? ORDER BY u.country_name ASC, ma.timestamp DESC LIMIT ? OFFSET ?", [$action_type, $per_page, $offset]);
            $text = "لیست اقدامات: " . getButtonLabel($action_type, '');
            $keyboard = [];
            if ($actions && $actions->num_rows > 0) {
                while($action = $actions->fetch_assoc()){
                    $date = date('Y-m-d H:i', strtotime($action['timestamp']));
                    $btn_text = htmlspecialchars($action['country_name']) . " | {$date}";
                    $keyboard[] = [['text' => $btn_text, 'callback_data' => 'admin_view_military_' . $action['id'] . '_' . $action_type]];
                }
            } else {
                $text = "هیچ اقدامی برای نمایش وجود ندارد.";
            }
            $pagination = getPaginationKeyboard('admin_military_' . $action_type, $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_military']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_view_military':
            $action_id = (int)$parts[3];
            $action_type = $parts[4];
            $action_res = query("SELECT ma.*, u.first_name, u.username, u.country_name FROM military_actions ma JOIN users u ON ma.user_id = u.telegram_id WHERE ma.id = ?", [$action_id]);
            if ($action_res && $action_res->num_rows > 0) {
                $action = $action_res->fetch_assoc();
                $user_link = makeUserLink($action['user_id'], $action['first_name']);
                $date = date('Y-m-d H:i', strtotime($action['timestamp']));
                $text = "<b>جزئیات اقدام نظامی</b>\n\n" .
                        "👤 <b>فرستنده:</b> " . $user_link . "\n" .
                        "🆔 <b>آیدی:</b> <code>" . $action['user_id'] . "</code>\n" .
                        "🌍 <b>کشور:</b> " . htmlspecialchars($action['country_name']) . "\n" .
                        "📅 <b>تاریخ و ساعت:</b> {$date}\n\n" .
                        "📝 <b>متن:</b>\n" . htmlspecialchars($action['message_text']);
                $keyboard = ['inline_keyboard' => [
                    [['text' => '🗑 حذف', 'callback_data' => 'admin_delete_military_' . $action_id . '_' . $action_type]],
                    [['text' => '🔙 بازگشت', 'callback_data' => 'admin_military_' . $action_type . '_p1']]
                ]];
                if ($action['file_type'] == 'photo') {
                    sendPhoto($chat_id, $action['file_id'], $text, $keyboard);
                    deleteMessage($chat_id, $message_id);
                } else {
                    editMessage($chat_id, $message_id, $text, $keyboard);
                }
            }
            break;

        case 'admin_delete_military':
            $action_id = (int)$parts[3];
            $action_type = $parts[4];
            query("DELETE FROM military_actions WHERE id = ?", [$action_id]);
            answerCallbackQuery($callback_query_id, 'اقدام با موفقیت حذف شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_military_' . $action_type, 1, []);
            break;

        case 'admin_declarations':
            $keyboard = ['inline_keyboard' => [
                [['text' => '📜 بیانیه‌ها', 'callback_data' => 'admin_declarations_bayan_ie_p1']],
                [['text' => '⚔️ اعلام جنگ‌ها', 'callback_data' => 'admin_declarations_elam_jang_p1']],
                [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']],
            ]];
            editMessage($chat_id, $message_id, "بخش بیانیه و اعلام جنگ:", $keyboard);
            break;

        case 'admin_declarations_bayan_ie':
        case 'admin_declarations_elam_jang':
            $declaration_type = str_replace('admin_declarations_', '', $action);
            $count_res = query("SELECT COUNT(*) as total FROM declarations d JOIN users u ON d.user_id = u.telegram_id WHERE d.declaration_type = ?", [$declaration_type]);
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $declarations = query("SELECT d.*, u.first_name, u.username, u.country_name FROM declarations d JOIN users u ON d.user_id = u.telegram_id WHERE d.declaration_type = ? ORDER BY u.country_name ASC, d.timestamp DESC LIMIT ? OFFSET ?", [$declaration_type, $per_page, $offset]);
            $text = "لیست " . ($declaration_type == 'bayan_ie' ? 'بیانیه‌ها' : 'اعلام جنگ‌ها') . ":";
            $keyboard = [];
            if ($declarations && $declarations->num_rows > 0) {
                while($dec = $declarations->fetch_assoc()){
                    $date = date('Y-m-d H:i', strtotime($dec['timestamp']));
                    $btn_text = htmlspecialchars($dec['country_name']) . " | {$date}";
                    $keyboard[] = [['text' => $btn_text, 'callback_data' => 'admin_view_declaration_' . $dec['id'] . '_' . $declaration_type]];
                }
            } else {
                $text = "هیچ " . ($declaration_type == 'bayan_ie' ? 'بیانیه‌ای' : 'اعلام جنگی') . " برای نمایش وجود ندارد.";
            }
            $pagination = getPaginationKeyboard('admin_declarations_' . $declaration_type, $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_declarations']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_view_declaration':
            $dec_id = (int)$parts[3];
            $dec_type = $parts[4];
            $dec_res = query("SELECT d.*, u.first_name, u.username, u.country_name FROM declarations d JOIN users u ON d.user_id = u.telegram_id WHERE d.id = ?", [$dec_id]);
            if ($dec_res && $dec_res->num_rows > 0) {
                $dec = $dec_res->fetch_assoc();
                $user_link = makeUserLink($dec['user_id'], $dec['first_name']);
                $date = date('Y-m-d H:i', strtotime($dec['timestamp']));
                $text = "<b>جزئیات " . ($dec_type == 'bayan_ie' ? 'بیانیه' : 'اعلام جنگ') . "</b>\n\n" .
                        "👤 <b>فرستنده:</b> " . $user_link . "\n" .
                        "🆔 <b>آیدی:</b> <code>" . $dec['user_id'] . "</code>\n" .
                        "🌍 <b>کشور:</b> " . htmlspecialchars($dec['country_name']) . "\n" .
                        "📅 <b>تاریخ و ساعت:</b> {$date}\n\n" .
                        "📝 <b>متن:</b>\n" . htmlspecialchars($dec['message_text']);
                $keyboard = ['inline_keyboard' => [
                    [['text' => '📢 ارسال به کانال', 'callback_data' => 'admin_sendchannel_' . $dec_id . '_' . $dec_type]],
                    [['text' => '🗑 حذف', 'callback_data' => 'admin_delete_declaration_' . $dec_id . '_' . $dec_type]],
                    [['text' => '🔙 بازگشت', 'callback_data' => 'admin_declarations_' . $dec_type . '_p1']]
                ]];
                if ($dec['file_type'] == 'photo') {
                    sendPhoto($chat_id, $dec['file_id'], $text, $keyboard);
                    deleteMessage($chat_id, $message_id);
                } else {
                    editMessage($chat_id, $message_id, $text, $keyboard);
                }
            }
            break;

        case 'admin_sendchannel':
            $dec_id = (int)$parts[3];
            $dec_type = $parts[4];
            $dec_res = query("SELECT d.*, u.first_name, u.username, u.country_name FROM declarations d JOIN users u ON d.user_id = u.telegram_id WHERE d.id = ?", [$dec_id]);
            if ($dec_res && $dec_res->num_rows > 0) {
                $dec = $dec_res->fetch_assoc();
                $user_link = makeUserLink($dec['user_id'], $dec['first_name']);
                $channel_text = "<b>" . ($dec_type == 'bayan_ie' ? 'بیانیه' : 'اعلام جنگ') . "</b>\n\n" .
                                "🌍 <b>کشور:</b> " . htmlspecialchars($dec['country_name']) . "\n" .
                                "👤 <b>فرستنده:</b> " . $user_link . "\n\n" .
                                htmlspecialchars($dec['message_text']);
                if ($dec['file_type'] == 'photo') {
                    sendPhoto(CHANNEL_ID, $dec['file_id'], $channel_text);
                } else {
                    sendMessage(CHANNEL_ID, $channel_text);
                }
                answerCallbackQuery($callback_query_id, 'پیام با موفقیت به کانال ارسال شد.', false);
                handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_declarations_' . $dec_type, 1, []);
            }
            break;

        case 'admin_delete_declaration':
            $dec_id = (int)$parts[3];
            $dec_type = $parts[4];
            query("DELETE FROM declarations WHERE id = ?", [$dec_id]);
            answerCallbackQuery($callback_query_id, 'پیام با موفقیت حذف شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_declarations_' . $dec_type, 1, []);
            break;

        case 'admin_rolls':
            $count_res = query("SELECT COUNT(*) as total FROM rolls r JOIN users u ON r.user_id = u.telegram_id WHERE r.status = 'pending'");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $rolls = query("SELECT r.*, u.first_name, u.username, u.country_name FROM rolls r JOIN users u ON r.user_id = u.telegram_id WHERE r.status = 'pending' ORDER BY u.country_name ASC, r.timestamp DESC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $text = "لیست رول‌های در انتظار بررسی:";
            $keyboard = [];
            if ($rolls && $rolls->num_rows > 0) {
                while($roll = $rolls->fetch_assoc()){
                    $date = date('Y-m-d H:i', strtotime($roll['timestamp']));
                    $btn_text = htmlspecialchars($roll['country_name']) . " | {$date}";
                    $keyboard[] = [['text' => $btn_text, 'callback_data' => 'admin_view_roll_' . $roll['id']]];
                }
            } else {
                $text = "هیچ رول در انتظاری برای نمایش وجود ندارد.";
            }
            $pagination = getPaginationKeyboard('admin_rolls', $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_view_roll':
            $roll_id = (int)$parts[3];
            $roll_res = query("SELECT r.*, u.first_name, u.username, u.country_name FROM rolls r JOIN users u ON r.user_id = u.telegram_id WHERE r.id = ?", [$roll_id]);
            if ($roll_res && $roll_res->num_rows > 0) {
                $roll = $roll_res->fetch_assoc();
                $user_link = makeUserLink($roll['user_id'], $roll['first_name']);
                $date = date('Y-m-d H:i', strtotime($roll['timestamp']));
                $text = "<b>جزئیات رول</b>\n\n" .
                        "👤 <b>فرستنده:</b> " . $user_link . "\n" .
                        "🆔 <b>آیدی:</b> <code>" . $roll['user_id'] . "</code>\n" .
                        "🌍 <b>کشور:</b> " . htmlspecialchars($roll['country_name']) . "\n" .
                        "📅 <b>تاریخ و ساعت:</b> {$date}\n\n" .
                        "📝 <b>متن رول:</b>\n" . htmlspecialchars($roll['roll_text']);
                $keyboard = ['inline_keyboard' => [
                    [['text' => '✅ تأیید رول', 'callback_data' => 'admin_approve_' . $roll_id]],
                    [['text' => '💰 تعیین هزینه', 'callback_data' => 'admin_cost_' . $roll_id]],
                    [['text' => '❌ رد رول', 'callback_data' => 'admin_reject_' . $roll_id]],
                    [['text' => '🔙 بازگشت', 'callback_data' => 'admin_rolls_p1']]
                ]];
                editMessage($chat_id, $message_id, $text, $keyboard);
            }
            break;

        case 'admin_approve':
            $roll_id = (int)$parts[2];
            query("UPDATE rolls SET status = 'approved' WHERE id = ?", [$roll_id]);
            $roll_info = query("SELECT user_id FROM rolls WHERE id = ?", [$roll_id]);
            if ($roll_info && $roll_info->num_rows > 0) {
                $roll = $roll_info->fetch_assoc();
                sendMessage($roll['user_id'], "رول شما توسط ادمین تأیید شد.");
            }
            answerCallbackQuery($callback_query_id, 'رول با موفقیت تأیید شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_rolls', 1, []);
            break;

        case 'admin_reject':
            $roll_id = (int)$parts[2];
            query("DELETE FROM rolls WHERE id = ?", [$roll_id]);
            $roll_info = query("SELECT user_id FROM rolls WHERE id = ?", [$roll_id]);
            if ($roll_info && $roll_info->num_rows > 0) {
                $roll = $roll_info->fetch_assoc();
                sendMessage($roll['user_id'], "رول شما توسط ادمین رد شد.");
            }
            answerCallbackQuery($callback_query_id, 'رول با موفقیت رد شد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_rolls', 1, []);
            break;

        case 'admin_cost':
            $roll_id = (int)$parts[2];
            setUserState($admin_id, 'awaiting_roll_cost', $roll_id);
            editMessage($chat_id, $message_id, "لطفا هزینه رول را به عدد وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_view_roll_' . $roll_id]]]]);
            break;

        case 'admin_assets':
            $count_res = query("SELECT COUNT(*) as total FROM assets");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $assets = query("SELECT country_name FROM assets ORDER BY country_name ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $text = "لیست دارایی‌های کشورها:";
            $keyboard = [];
            if ($assets && $assets->num_rows > 0) {
                while($asset = $assets->fetch_assoc()){
                    $keyboard[] = [
                        ['text' => htmlspecialchars($asset['country_name']), 'callback_data' => 'admin_edit_asset_' . $asset['country_name']],
                        ['text' => '💸 سود', 'callback_data' => 'admin_edit_profit_' . $asset['country_name']],
                        ['text' => '💰 پول', 'callback_data' => 'admin_edit_money_' . $asset['country_name']]
                    ];
                }
            } else {
                $text = "هیچ دارایی‌ای ثبت نشده است.";
            }
            $pagination = getPaginationKeyboard('admin_assets', $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_edit_asset':
           $country_name = $parts[3];
           setUserState($admin_id, 'awaiting_asset_text', ['country_name' => $country_name]);
           $current_asset = query("SELECT asset_text FROM assets WHERE country_name = ?", [$country_name]);
           $asset_text = ($current_asset && $current_asset->num_rows > 0) ? $current_asset->fetch_assoc()['asset_text'] : "ثبت نشده";
           editMessage($chat_id, $message_id, "دارایی فعلی کشور <b>$country_name</b>:\n$asset_text\n\nلطفاً دارایی جدید را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_assets_p1']]]]);
           break;

       case 'admin_edit_asset':
           $country_name = $parts[3];
           setUserState($admin_id, 'awaiting_asset_text', ['country_name' => $country_name]);
           $current_asset = query("SELECT asset_text FROM assets WHERE country_name = ?", [$country_name]);
           $asset_text = ($current_asset && $current_asset->num_rows > 0) ? $current_asset->fetch_assoc()['asset_text'] : "ثبت نشده";
           editMessage($chat_id, $message_id, "دارایی فعلی کشور <b>$country_name</b>:\n$asset_text\n\nلطفاً دارایی جدید را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_assets_p1']]]]);
           break;

     case 'admin_edit_money':
         $country_name = $parts[3];
         setUserState($admin_id, 'awaiting_asset_money', ['country_name' => $country_name]);
         $current_asset = query("SELECT money FROM assets WHERE country_name = ?", [$country_name]);
         $money = ($current_asset && $current_asset->num_rows > 0) ? $current_asset->fetch_assoc()['money'] : 0;
         editMessage($chat_id, $message_id, "پول فعلی کشور <b>$country_name</b>: $money\n\nلطفاً مقدار پول جدید را به عدد وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_assets_p1']]]]);
         break;

        case 'admin_buttons':
            editMessage($chat_id, $message_id, "تنظیمات دکمه‌ها:", getButtonSettingsKeyboard());
            break;

        case 'admin_toggle_button':
            $button_key = $parts[3];
            query("UPDATE button_settings SET is_enabled = NOT is_enabled WHERE button_key = ?", [$button_key]);
            answerCallbackQuery($callback_query_id, 'وضعیت دکمه تغییر کرد.', false);
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_buttons', 1, []);
            break;

        case 'admin_rename_button':
            $button_key = $parts[3];
            setUserState($admin_id, 'awaiting_button_rename', $button_key);
            editMessage($chat_id, $message_id, "لطفا نام جدید برای دکمه را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_buttons']]]]);
            break;

        case 'admin_admins':
            $count_res = query("SELECT COUNT(*) as total FROM admins");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $admins = query("SELECT a.*, u.first_name, u.username FROM admins a JOIN users u ON a.telegram_id = u.telegram_id ORDER BY a.created_at ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $text = "لیست ادمین‌ها:";
            $keyboard = [];
            if ($admins && $admins->num_rows > 0) {
                while($admin = $admins->fetch_assoc()){
                    $btn_text = htmlspecialchars($admin['first_name']);
                    $keyboard[] = [
                        ['text' => $btn_text, 'callback_data' => 'admin_edit_admin_' . $admin['telegram_id']],
                        ['text' => '🗑 حذف', 'callback_data' => 'admin_delete_admin_' . $admin['telegram_id']]
                    ];
                }
            } else {
                $text = "هیچ ادمینی ثبت نشده است.";
            }
            $keyboard[] = [['text' => '➕ افزودن ادمین', 'callback_data' => 'admin_add_admin']];
            $pagination = getPaginationKeyboard('admin_admins', $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;

        case 'admin_add_admin':
            setUserState($admin_id, 'awaiting_admin_to_add');
            editMessage($chat_id, $message_id, "برای افزودن ادمین جدید، پیام او را فوروارد کنید یا آیدی عددی‌اش را بفرستید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_admins_p1']]]]);
            break;

        case 'admin_edit_admin':
            $target_id = (int)$parts[3];
            $admin_res = query("SELECT a.permissions, u.first_name FROM admins a JOIN users u ON a.telegram_id = u.telegram_id WHERE a.telegram_id = ?", [$target_id]);
            if ($admin_res && $admin_res->num_rows > 0) {
                $admin_data = $admin_res->fetch_assoc();
                $keyboard = getAdminPermissionsKeyboard($target_id, $admin_data['permissions']);
                editMessage($chat_id, $message_id, "درحال ویرایش دسترسی‌های " . htmlspecialchars($admin_data['first_name']), $keyboard);
            }
            break;

        case 'admin_delete_admin':
            $target_id = (int)$parts[3];
            if ($target_id == MAIN_ADMIN_ID) {
                answerCallbackQuery($callback_query_id, 'نمی‌توانید ادمین اصلی را حذف کنید.', true);
            } else {
                query("DELETE FROM admins WHERE telegram_id = ?", [$target_id]);
                answerCallbackQuery($callback_query_id, 'ادمین با موفقیت حذف شد.', false);
                handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_admins', 1, []);
            }
            break;

        case 'toggle_perm':
            $target_id = (int)$parts[2];
            $perm_key = $parts[3];
            $admin_res = query("SELECT permissions FROM admins WHERE telegram_id = ?", [$target_id]);
            if ($admin_res && $admin_res->num_rows > 0) {
                $current_perms = json_decode($admin_res->fetch_assoc()['permissions'], true) ?: [];
                if (in_array($perm_key, $current_perms)) {
                    $current_perms = array_diff($current_perms, [$perm_key]);
                } else {
                    $current_perms[] = $perm_key;
                }
                if ($perm_key == 'all' && in_array('all', $current_perms)) {
                    $current_perms = ['all'];
                } elseif ($perm_key != 'all') {
                    $current_perms = array_diff($current_perms, ['all']);
                }
                $new_perms_json = json_encode(array_values($current_perms), JSON_UNESCAPED_UNICODE);
                query("UPDATE admins SET permissions = ? WHERE telegram_id = ?", [$new_perms_json, $target_id]);
                $keyboard = getAdminPermissionsKeyboard($target_id, $new_perms_json);
                editMessage($chat_id, $message_id, "دسترسی‌ها آپدیت شد.", $keyboard);
            }
            break;

        case 'admin_lottery':
            $prizes = query("SELECT * FROM lottery_prizes");
            $text = "🎉 بخش گردونه شانس\n\n";
            if ($prizes && $prizes->num_rows > 0) {
                $text .= "<b>جوایز ثبت‌شده:</b>\n";
                while ($prize = $prizes->fetch_assoc()) {
                    $text .= "- " . htmlspecialchars($prize['prize_name']) . "\n";
                }
            } else {
                $text .= "هیچ جایزه‌ای ثبت نشده است.";
            }
            $keyboard = ['inline_keyboard' => [
                [['text' => '➕ ثبت جایزه جدید', 'callback_data' => 'admin_add_prize']],
                [['text' => '🎰 شروع گردونه', 'callback_data' => 'admin_confirm_lottery']],
                [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']]
            ]];
            editMessage($chat_id, $message_id, $text, $keyboard);
            break;

        case 'admin_add_prize':
            setUserState($admin_id, 'awaiting_lottery_prize');
            editMessage($chat_id, $message_id, "لطفاً نام جایزه جدید را وارد کنید:", ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_lottery']]]]);
            break;

        case 'admin_confirm_lottery':
            $keyboard = ['inline_keyboard' => [
                [['text' => '✅ بله', 'callback_data' => 'admin_start_lottery']],
                [['text' => '❌ خیر', 'callback_data' => 'admin_lottery']]
            ]];
            editMessage($chat_id, $message_id, "آیا مطمئنید که می‌خواهید گردونه شانس را شروع کنید؟", $keyboard);
            break;

        case 'admin_start_lottery':
            $prizes = query("SELECT * FROM lottery_prizes ORDER BY RAND() LIMIT 1");
            if ($prizes && $prizes->num_rows > 0) {
                $prize = $prizes->fetch_assoc();
                $users = query("SELECT u.telegram_id, u.first_name, u.username, u.country_name FROM users u WHERE u.is_registered = 1 AND u.is_banned = 0 ORDER BY RAND() LIMIT 1");
                if ($users && $users->num_rows > 0) {
                    $winner = $users->fetch_assoc();
                    $winner_link = makeUserLink($winner['telegram_id'], $winner['first_name']);
                    $channel_text = "🎉 <b>نتیجه گردونه شانس</b>\n\n" .
                                    "🏆 <b>برنده:</b> " . $winner_link . "\n" .
                                    "🌍 <b>کشور:</b> " . htmlspecialchars($winner['country_name']) . "\n" .
                                    "🎁 <b>جایزه:</b> " . htmlspecialchars($prize['prize_name']);
                    sendMessage(CHANNEL_ID, $channel_text);
                    sendMessage($winner['telegram_id'], "تبریک! شما برنده جایزه «" . $prize['prize_name'] . "» در گردونه شانس شدید!");
                    query("DELETE FROM lottery_prizes WHERE id = ?", [$prize['id']]);
                    answerCallbackQuery($callback_query_id, 'گردونه با موفقیت اجرا شد.', false);
                } else {
                    answerCallbackQuery($callback_query_id, 'هیچ کاربری برای انتخاب برنده وجود ندارد.', true);
                }
            } else {
                answerCallbackQuery($callback_query_id, 'هیچ جایزه‌ای برای گردونه ثبت نشده است.', true);
            }
            handleAdminCallback($chat_id, $message_id, $admin_id, 'admin_lottery', 1, []);
            break;

        case 'alliance_view':
            $alliance_id = (int)$parts[2];
            $alliance_res = query("SELECT a.*, u.first_name, u.username FROM alliances a JOIN users u ON a.leader_id = u.telegram_id WHERE a.id = ?", [$alliance_id]);
            if ($alliance_res && $alliance_res->num_rows > 0) {
                $alliance = $alliance_res->fetch_assoc();
                $members = query("SELECT am.*, u.first_name, u.username FROM alliance_members am JOIN users u ON am.user_id = u.telegram_id WHERE am.alliance_id = ?", [$alliance_id]);
                $text = "<b>جزئیات اتحاد: " . htmlspecialchars($alliance['name']) . "</b>\n\n" .
                        "👑 <b>رهبر:</b> " . htmlspecialchars($alliance['country_name']) . " (" . makeUserLink($alliance['leader_id'], $alliance['first_name']) . ")\n\n" .
                        "<b>اعضا:</b>\n";
                $member_count = 0;
                $keyboard = [];
                while ($member = $members->fetch_assoc()) {
                    $member_count++;
                    $text .= "- " . htmlspecialchars($member['country_name']) . " (" . makeUserLink($member['user_id'], $member['first_name']) . ")\n";
                    if ($alliance['leader_id'] == $admin_id && $member['user_id'] != $admin_id) {
                        $keyboard[] = [['text' => "✏️ ویرایش " . htmlspecialchars($member['country_name']), 'callback_data' => 'edit_member_' . $alliance_id . '_' . $member['user_id']]];
                    }
                }
                for ($i = $member_count; $i < 4; $i++) {
                    $text .= "- خالی\n";
                }
                $text .= "\n<b>شعار اتحاد:</b> " . (empty($alliance['slogan']) ? "خالی" : htmlspecialchars($alliance['slogan']));
                if ($alliance['leader_id'] == $admin_id) {
                    $keyboard[] = [['text' => '➕ دعوت عضو', 'callback_data' => 'invite_alliance_' . $alliance_id]];
                    $keyboard[] = [['text' => '✏️ ویرایش شعار', 'callback_data' => 'edit_slogan_' . $alliance_id]];
                }
                if ($member_count > 0) {
                    $keyboard[] = [['text' => '🚪 خروج از اتحاد', 'callback_data' => 'leave_alliance_' . $alliance_id]];
                }
                $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'alliance']];
                editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            }
            break;

        case 'admin_alliances':
            $count_res = query("SELECT COUNT(*) as total FROM alliances");
            $total = $count_res->fetch_assoc()['total'];
            $per_page = 5;
            $offset = ($page - 1) * $per_page;
            $alliances = query("SELECT a.id, a.name, u.first_name, u.country_name FROM alliances a JOIN users u ON a.leader_id = u.telegram_id ORDER BY a.name ASC LIMIT ? OFFSET ?", [$per_page, $offset]);
            $text = "لیست اتحادها:";
            $keyboard = [];
            if ($alliances && $alliances->num_rows > 0) {
                while($alliance = $alliances->fetch_assoc()){
                    $btn_text = htmlspecialchars($alliance['name']) . " | رهبر: " . htmlspecialchars($alliance['country_name']);
                    $keyboard[] = [['text' => $btn_text, 'callback_data' => 'alliance_view_' . $alliance['id']]];
                }
            } else {
                $text = "هیچ اتحادی وجود ندارد.";
            }
            $pagination = getPaginationKeyboard('admin_alliances', $page, $total, $per_page);
            $keyboard = array_merge($keyboard, $pagination);
            $keyboard[] = [['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']];
            editMessage($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
            break;
    }
}

// --- End of Script ---
?>