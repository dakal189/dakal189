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
    $stmt = $pdo->prepare("
        SELECT 
            vb.bot as username,
            vb.start,
            vb.end,
            vb.is_active,
            vb.created_at
        FROM vip_bots vb 
        WHERE vb.admin = ? AND vb.end > UNIX_TIMESTAMP()
        ORDER BY vb.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bots_data = [];
    
    foreach ($bots as $bot) {
        $bot_username = $bot['username'];
        
        // دریافت تعداد کاربران ربات
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$bot_username}_members`");
        $stmt->execute();
        $members_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // دریافت تعداد پیام‌های ارسالی
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist WHERE bot_username = ?");
        $stmt->execute([$bot_username]);
        $messages_sent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // محاسبه زمان باقی‌مانده
        $time_remaining = $bot['end'] - time();
        $days_remaining = floor($time_remaining / 86400);
        
        // بررسی وضعیت فعال بودن
        $is_active = $bot['is_active'] == 1 && $time_remaining > 0;
        
        // فرمت تاریخ ساخت
        $created_date = date('Y/m/d', $bot['created_at']);
        
        $bots_data[] = [
            'username' => $bot_username,
            'members_count' => (int)$members_count,
            'messages_sent' => (int)$messages_sent,
            'is_active' => $is_active,
            'days_remaining' => $days_remaining,
            'created_date' => $created_date,
            'start_date' => date('Y/m/d', $bot['start']),
            'end_date' => date('Y/m/d', $bot['end']),
            'created_at' => $bot['created_at']
        ];
    }

    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'bots' => $bots_data,
        'total_bots' => count($bots_data),
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