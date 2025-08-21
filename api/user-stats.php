<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// بررسی درخواست OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// بررسی متد درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // دریافت داده‌ها
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit();
    }

    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // بررسی وجود کاربر
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_exists = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

    if (!$user_exists) {
        echo json_encode([
            'success' => false,
            'error' => 'User not found',
            'message' => 'کاربر در سیستم ثبت نشده است'
        ]);
        exit();
    }

    // دریافت ربات‌های کاربر
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vip_bots WHERE admin = ? AND `end` > UNIX_TIMESTAMP()");
    $stmt->execute([$user_id]);
    $user_bots = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت کل کاربران ربات‌های کاربر
    $total_users = 0;
    if ($user_bots > 0) {
        $stmt = $pdo->prepare("SELECT bot FROM vip_bots WHERE admin = ? AND `end` > UNIX_TIMESTAMP()");
        $stmt->execute([$user_id]);
        $user_bot_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($user_bot_list as $bot) {
            $bot_username = $bot['bot'];
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$bot_username}_members`");
            $stmt->execute();
            $bot_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_users += $bot_users;
        }
    }

    // دریافت پیام‌های ارسالی کاربر
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $sent_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // بررسی وضعیت VIP
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vip_bots WHERE admin = ? AND `end` > UNIX_TIMESTAMP()");
    $stmt->execute([$user_id]);
    $is_vip = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

    // دریافت آمار روزانه
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist WHERE user_id = ? AND DATE(FROM_UNIXTIME(time)) = CURDATE()");
    $stmt->execute([$user_id]);
    $today_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار هفتگی
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist WHERE user_id = ? AND time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))");
    $stmt->execute([$user_id]);
    $weekly_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار ماهانه
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist WHERE user_id = ? AND time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $stmt->execute([$user_id]);
    $monthly_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // محاسبه رشد کاربران
    $growth_rate = 0;
    if ($total_users > 0) {
        // محاسبه رشد در 30 روز گذشته
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM (
                SELECT DISTINCT user_id FROM (
                    SELECT user_id FROM vip_bots vb 
                    JOIN `{$bot_username}_members` bm ON vb.bot = '{$bot_username}'
                    WHERE vb.admin = ? AND bm.time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                ) as recent_users
            ) as unique_users
        ");
        $stmt->execute([$user_id]);
        $recent_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($recent_users > 0) {
            $growth_rate = round((($total_users - $recent_users) / $recent_users) * 100, 1);
        }
    }

    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'data' => [
            'user_bots' => (int)$user_bots,
            'total_users' => (int)$total_users,
            'sent_messages' => (int)$sent_messages,
            'is_vip' => $is_vip,
            'today_messages' => (int)$today_messages,
            'weekly_messages' => (int)$weekly_messages,
            'monthly_messages' => (int)$monthly_messages,
            'growth_rate' => $growth_rate,
            'user_exists' => $user_exists
        ],
        'timestamp' => time()
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>