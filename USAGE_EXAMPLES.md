# Usage Examples and Implementation Guide

## Quick Start Examples

### Basic Bot Setup

```php
<?php
// Include the bot file
require_once 'bot.php';

// Configuration
define('API_KEY', 'YOUR_BOT_TOKEN');
$ADMIN = "YOUR_ADMIN_ID";
$Channel = 'your_channel';

// Handle incoming update
$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$chat_id = $message->chat->id;
$text = $message->text;
?>
```

### Simple Welcome Message

```php
if($text == "/start") {
    SendMessage($chat_id, 
        "🤖 Welcome to our bot!\n\n" .
        "Available commands:\n" .
        "/help - Show help\n" .
        "/status - Check your status\n" .
        "/settings - Bot settings", 
        "HTML", false, "");
}
```

## User Management Examples

### User Registration System

```php
function registerUser($user_id, $first_name) {
    // Check if user already exists
    $users = file_get_contents('users.txt');
    $user_list = explode("\n", $users);
    
    if (!in_array($user_id, $user_list)) {
        // Add new user
        $users .= $user_id . "\n";
        file_put_contents('users.txt', $users);
        
        // Create user directory
        @mkdir("data/$user_id", 0777, true);
        
        // Initialize user data
        file_put_contents("data/$user_id/points.txt", "0");
        file_put_contents("data/$user_id/referrals.txt", "0");
        file_put_contents("data/$user_id/status.txt", "active");
        
        // Send welcome message
        SendMessage($user_id, 
            "Welcome $first_name! 🎉\n\n" .
            "Your account has been created successfully.\n" .
            "Type /help to see available commands.", 
            "HTML", false, "");
        
        return true;
    }
    return false;
}

// Usage
if($text == "/start") {
    $registered = registerUser($from_id, $from_first);
    if(!$registered) {
        SendMessage($chat_id, "Welcome back! 👋", "", false, "");
    }
}
```

### User Profile System

```php
function getUserProfile($user_id) {
    $points = @file_get_contents("data/$user_id/points.txt") ?: "0";
    $referrals = @file_get_contents("data/$user_id/referrals.txt") ?: "0";
    $join_date = @file_get_contents("data/$user_id/join_date.txt") ?: date('Y-m-d');
    
    return [
        'points' => intval($points),
        'referrals' => intval($referrals),
        'join_date' => $join_date
    ];
}

function showUserProfile($chat_id, $user_id, $first_name) {
    $profile = getUserProfile($user_id);
    
    $message = "👤 <b>Your Profile</b>\n\n" .
               "🏷 Name: $first_name\n" .
               "🆔 ID: $user_id\n" .
               "💰 Points: {$profile['points']}\n" .
               "👥 Referrals: {$profile['referrals']}\n" .
               "📅 Joined: {$profile['join_date']}";
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '🔄 Refresh', 'callback_data' => 'refresh_profile']],
            [['text' => '📊 Statistics', 'callback_data' => 'show_stats']]
        ]
    ]);
    
    SendMessage($chat_id, $message, "HTML", false, $keyboard);
}

// Usage
if($text == "/profile") {
    showUserProfile($chat_id, $from_id, $from_first);
}
```

## Referral System Examples

### Advanced Referral Tracking

```php
function processReferral($referrer_id, $new_user_id, $new_user_name) {
    // Validate referrer exists
    if(!file_exists("data/$referrer_id")) {
        return false;
    }
    
    // Check if new user already referred
    $referred_users = @file_get_contents("data/$referrer_id/referred_list.txt") ?: "";
    if(strpos($referred_users, "$new_user_id\n") !== false) {
        return false; // Already referred
    }
    
    // Add to referrer's list
    file_put_contents("data/$referrer_id/referred_list.txt", 
                     $referred_users . "$new_user_id\n", FILE_APPEND);
    
    // Update referrer points
    $current_points = intval(@file_get_contents("data/$referrer_id/points.txt") ?: "0");
    file_put_contents("data/$referrer_id/points.txt", $current_points + 10);
    
    // Update referral count
    $current_referrals = intval(@file_get_contents("data/$referrer_id/referrals.txt") ?: "0");
    file_put_contents("data/$referrer_id/referrals.txt", $current_referrals + 1);
    
    // Notify referrer
    SendMessage($referrer_id, 
        "🎉 New referral!\n\n" .
        "👤 $new_user_name joined using your link\n" .
        "💰 +10 points earned\n" .
        "👥 Total referrals: " . ($current_referrals + 1), 
        "HTML", false, "");
    
    return true;
}

// Enhanced start command with referral
if(preg_match('/^\/start (.+)$/', $text, $matches)) {
    $referrer_id = $matches[1];
    
    if($from_id == $referrer_id) {
        SendMessage($chat_id, "❌ You can't refer yourself!", "", false, "");
    } else {
        // Register user
        registerUser($from_id, $from_first);
        
        // Process referral
        if(processReferral($referrer_id, $from_id, $from_first)) {
            SendMessage($chat_id, 
                "✅ Welcome! You joined via referral link.\n" .
                "🎁 Bonus: +5 starting points", 
                "HTML", false, "");
            
            // Give bonus to new user
            file_put_contents("data/$from_id/points.txt", "5");
        }
    }
}
```

### Referral Link Generator

```php
function generateReferralLink($user_id, $bot_username) {
    $link = "https://t.me/$bot_username?start=$user_id";
    
    $message = "🔗 <b>Your Referral Link</b>\n\n" .
               "Share this link to earn rewards:\n" .
               "<code>$link</code>\n\n" .
               "💰 Earn 10 points per referral\n" .
               "🎁 Your friends get 5 bonus points";
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '📋 Copy Link', 'url' => $link]],
            [['text' => '📊 My Referrals', 'callback_data' => 'my_referrals']]
        ]
    ]);
    
    return ['message' => $message, 'keyboard' => $keyboard];
}

// Usage
if($text == "/referral" || $text == "/invite") {
    $referral_data = generateReferralLink($from_id, $Botid);
    SendMessage($chat_id, $referral_data['message'], "HTML", false, $referral_data['keyboard']);
}
```

## Admin Panel Examples

### Advanced Statistics

```php
function getDetailedStats() {
    // Total users
    $users = file_get_contents('users.txt');
    $user_list = array_filter(explode("\n", $users));
    $total_users = count($user_list);
    
    // Active today
    $today = date('Y-m-d');
    $active_today = 0;
    $total_points = 0;
    $total_referrals = 0;
    
    foreach($user_list as $user_id) {
        if(empty($user_id)) continue;
        
        // Check last activity
        $last_activity = @file_get_contents("data/$user_id/last_activity.txt");
        if($last_activity == $today) {
            $active_today++;
        }
        
        // Sum points and referrals
        $points = intval(@file_get_contents("data/$user_id/points.txt") ?: "0");
        $referrals = intval(@file_get_contents("data/$user_id/referrals.txt") ?: "0");
        
        $total_points += $points;
        $total_referrals += $referrals;
    }
    
    return [
        'total_users' => $total_users,
        'active_today' => $active_today,
        'total_points' => $total_points,
        'total_referrals' => $total_referrals,
        'avg_points' => $total_users > 0 ? round($total_points / $total_users, 2) : 0
    ];
}

function showDetailedStats($chat_id) {
    $stats = getDetailedStats();
    
    $message = "📊 <b>Bot Statistics</b>\n\n" .
               "👥 Total Users: {$stats['total_users']}\n" .
               "🟢 Active Today: {$stats['active_today']}\n" .
               "💰 Total Points: {$stats['total_points']}\n" .
               "🔗 Total Referrals: {$stats['total_referrals']}\n" .
               "📈 Avg Points/User: {$stats['avg_points']}\n\n" .
               "📅 Generated: " . date('Y-m-d H:i:s');
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats']],
            [['text' => '📈 Growth Chart', 'callback_data' => 'growth_chart']],
            [['text' => '👤 User List', 'callback_data' => 'user_list']]
        ]
    ]);
    
    SendMessage($chat_id, $message, "HTML", false, $keyboard);
}

// Usage
if($text == "امار" && $from_id == $ADMIN) {
    showDetailedStats($chat_id);
}
```

### Bulk Operations

```php
function bulkOperation($operation, $data = null) {
    $users = file_get_contents('users.txt');
    $user_list = array_filter(explode("\n", $users));
    $success_count = 0;
    $failed_count = 0;
    
    foreach($user_list as $user_id) {
        if(empty($user_id)) continue;
        
        try {
            switch($operation) {
                case 'broadcast':
                    SendMessage($user_id, $data['message'], $data['parse_mode'], false, "");
                    $success_count++;
                    break;
                    
                case 'add_points':
                    $current = intval(@file_get_contents("data/$user_id/points.txt") ?: "0");
                    file_put_contents("data/$user_id/points.txt", $current + $data['amount']);
                    $success_count++;
                    break;
                    
                case 'backup_data':
                    // Create backup of user data
                    $user_data = [
                        'points' => @file_get_contents("data/$user_id/points.txt"),
                        'referrals' => @file_get_contents("data/$user_id/referrals.txt"),
                        'phone' => @file_get_contents("data/$user_id/number.txt")
                    ];
                    file_put_contents("backups/user_$user_id.json", json_encode($user_data));
                    $success_count++;
                    break;
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second delay
            
        } catch(Exception $e) {
            $failed_count++;
        }
    }
    
    return ['success' => $success_count, 'failed' => $failed_count];
}

// Enhanced broadcast
if($text == "ارسال همگانی" && $from_id == $ADMIN) {
    file_put_contents("data/$from_id/amir.txt", "broadcast_advanced");
    SendMessage($chat_id, 
        "📢 <b>Advanced Broadcast</b>\n\n" .
        "Send your message with format:\n" .
        "<code>TEXT|PARSE_MODE</code>\n\n" .
        "Example:\n" .
        "<code>Hello <b>everyone</b>!|HTML</code>\n" .
        "<code>**Bold text**|MarkDown</code>", 
        "HTML", false, "");
}

if($amir == "broadcast_advanced" && $from_id == $ADMIN) {
    file_put_contents("data/$from_id/amir.txt", "no");
    
    $parts = explode('|', $text);
    $message = $parts[0];
    $parse_mode = isset($parts[1]) ? $parts[1] : "";
    
    $result = bulkOperation('broadcast', [
        'message' => $message,
        'parse_mode' => $parse_mode
    ]);
    
    SendMessage($chat_id, 
        "✅ Broadcast completed!\n\n" .
        "✅ Sent: {$result['success']}\n" .
        "❌ Failed: {$result['failed']}", 
        "", false, "");
}
```

## Advanced Features Examples

### Point System with Transactions

```php
function addPointTransaction($user_id, $amount, $type, $description) {
    $transaction = [
        'amount' => $amount,
        'type' => $type, // 'earn', 'spend', 'bonus', 'referral'
        'description' => $description,
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s')
    ];
    
    // Add to transaction log
    $log_file = "data/$user_id/transactions.json";
    $transactions = [];
    if(file_exists($log_file)) {
        $transactions = json_decode(file_get_contents($log_file), true) ?: [];
    }
    $transactions[] = $transaction;
    file_put_contents($log_file, json_encode($transactions));
    
    // Update balance
    $current_points = intval(@file_get_contents("data/$user_id/points.txt") ?: "0");
    $new_balance = $current_points + $amount;
    file_put_contents("data/$user_id/points.txt", $new_balance);
    
    return $new_balance;
}

function showTransactionHistory($chat_id, $user_id) {
    $log_file = "data/$user_id/transactions.json";
    
    if(!file_exists($log_file)) {
        SendMessage($chat_id, "No transaction history found.", "", false, "");
        return;
    }
    
    $transactions = json_decode(file_get_contents($log_file), true);
    $recent = array_slice(array_reverse($transactions), 0, 10); // Last 10 transactions
    
    $message = "💳 <b>Transaction History</b>\n\n";
    
    foreach($recent as $tx) {
        $emoji = $tx['amount'] > 0 ? '➕' : '➖';
        $message .= "$emoji {$tx['amount']} - {$tx['description']}\n";
        $message .= "📅 {$tx['date']}\n\n";
    }
    
    $current_balance = intval(@file_get_contents("data/$user_id/points.txt") ?: "0");
    $message .= "💰 Current Balance: $current_balance points";
    
    SendMessage($chat_id, $message, "HTML", false, "");
}

// Usage
if($text == "/transactions") {
    showTransactionHistory($chat_id, $from_id);
}
```

### Scheduled Tasks System

```php
function addScheduledTask($task_type, $data, $execute_time) {
    $task = [
        'id' => uniqid(),
        'type' => $task_type,
        'data' => $data,
        'execute_time' => $execute_time,
        'status' => 'pending',
        'created' => time()
    ];
    
    $tasks_file = 'scheduled_tasks.json';
    $tasks = [];
    if(file_exists($tasks_file)) {
        $tasks = json_decode(file_get_contents($tasks_file), true) ?: [];
    }
    $tasks[] = $task;
    file_put_contents($tasks_file, json_encode($tasks));
    
    return $task['id'];
}

function processScheduledTasks() {
    $tasks_file = 'scheduled_tasks.json';
    if(!file_exists($tasks_file)) return;
    
    $tasks = json_decode(file_get_contents($tasks_file), true) ?: [];
    $current_time = time();
    $updated = false;
    
    foreach($tasks as &$task) {
        if($task['status'] == 'pending' && $current_time >= $task['execute_time']) {
            try {
                switch($task['type']) {
                    case 'send_message':
                        SendMessage($task['data']['chat_id'], 
                                  $task['data']['message'], 
                                  $task['data']['parse_mode'], 
                                  false, "");
                        break;
                        
                    case 'add_points':
                        addPointTransaction($task['data']['user_id'], 
                                         $task['data']['amount'], 
                                         'bonus', 
                                         'Scheduled bonus');
                        break;
                        
                    case 'broadcast':
                        bulkOperation('broadcast', $task['data']);
                        break;
                }
                
                $task['status'] = 'completed';
                $task['completed_time'] = $current_time;
                $updated = true;
                
            } catch(Exception $e) {
                $task['status'] = 'failed';
                $task['error'] = $e->getMessage();
                $updated = true;
            }
        }
    }
    
    if($updated) {
        file_put_contents($tasks_file, json_encode($tasks));
    }
}

// Schedule a reminder
function scheduleReminder($user_id, $message, $delay_hours) {
    $execute_time = time() + ($delay_hours * 3600);
    
    $task_id = addScheduledTask('send_message', [
        'chat_id' => $user_id,
        'message' => $message,
        'parse_mode' => 'HTML'
    ], $execute_time);
    
    return $task_id;
}

// Usage
if($text == "/remind_me") {
    $task_id = scheduleReminder($from_id, 
        "⏰ This is your scheduled reminder!\n\nDon't forget to check your daily bonus!", 
        24); // 24 hours later
    
    SendMessage($chat_id, 
        "✅ Reminder set!\n\nYou'll receive a reminder in 24 hours.\n\nTask ID: $task_id", 
        "HTML", false, "");
}

// Call this function periodically (via cron job)
processScheduledTasks();
```

### Webhook Integration Example

```php
// webhook.php - Main webhook handler
<?php
require_once 'bot.php';

// Log all incoming updates for debugging
function logUpdate($update) {
    $log_entry = date('Y-m-d H:i:s') . " - " . json_encode($update) . "\n";
    file_put_contents('webhook_log.txt', $log_entry, FILE_APPEND);
}

// Get the incoming update
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log the update (remove in production)
logUpdate($update);

// Process scheduled tasks
processScheduledTasks();

// Handle the update
if(isset($update['message'])) {
    handleMessage($update['message']);
} elseif(isset($update['callback_query'])) {
    handleCallbackQuery($update['callback_query']);
} elseif(isset($update['inline_query'])) {
    handleInlineQuery($update['inline_query']);
}

function handleMessage($message) {
    global $ADMIN;
    
    $chat_id = $message['chat']['id'];
    $from_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    
    // Update user activity
    file_put_contents("data/$from_id/last_activity.txt", date('Y-m-d'));
    
    // Process commands
    if(strpos($text, '/') === 0) {
        processCommand($text, $chat_id, $from_id, $message);
    } else {
        processText($text, $chat_id, $from_id, $message);
    }
}

function handleCallbackQuery($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $from_id = $callback_query['from']['id'];
    $data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    
    // Answer callback query
    tmsizdah('answerCallbackQuery', [
        'callback_query_id' => $callback_query['id'],
        'text' => 'Processing...'
    ]);
    
    // Process callback data
    processCallback($data, $chat_id, $from_id, $message_id);
}

function processCallback($data, $chat_id, $from_id, $message_id) {
    switch($data) {
        case 'refresh_profile':
            // Update profile display
            $profile = getUserProfile($from_id);
            $updated_text = "👤 Profile updated!\n\n💰 Points: {$profile['points']}";
            EditMessageText($chat_id, $message_id, $updated_text, "HTML", false, "");
            break;
            
        case 'my_referrals':
            showReferralStats($chat_id, $from_id);
            break;
    }
}
?>
```

## Error Handling and Logging Examples

```php
// Enhanced error handling
function safeApiCall($method, $data, $retries = 3) {
    for($i = 0; $i < $retries; $i++) {
        $result = tmsizdah($method, $data);
        
        if($result !== null && isset($result->ok) && $result->ok) {
            return $result;
        }
        
        // Log error
        $error_log = date('Y-m-d H:i:s') . " - API Error: $method - " . 
                    json_encode($data) . " - Attempt " . ($i + 1) . "\n";
        file_put_contents('api_errors.log', $error_log, FILE_APPEND);
        
        // Wait before retry
        sleep(1);
    }
    
    return false;
}

// Usage
$result = safeApiCall('sendMessage', [
    'chat_id' => $chat_id,
    'text' => 'Hello!'
]);

if($result === false) {
    // Handle failure
    error_log("Failed to send message after 3 attempts");
}
```

These examples demonstrate practical implementations of the bot's functionality, from basic operations to advanced features like transaction logging, scheduled tasks, and robust error handling. Each example can be adapted and extended based on specific requirements.