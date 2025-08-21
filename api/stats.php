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

// اجازه هر دو متد POST/GET برای انعطاف بیشتر
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // دریافت داده‌ها (در صورت نبود user_id، همچنان آمار کلی برگردد)
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $user_id = $input['user_id'] ?? ($_GET['user_id'] ?? null);

    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // دریافت آمار کاربران کل
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members");
    $stmt->execute();
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار ربات‌های فعال
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vip_bots WHERE `end` > UNIX_TIMESTAMP()");
    $stmt->execute();
    $active_bots = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار پیام‌های ارسالی (از جدول sendlist)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sendlist");
    $stmt->execute();
    $total_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار کاربر خاص (اختیاری)
    $user_exists = false;
    $user_bots = 0;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_exists = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        if ($user_exists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vip_bots WHERE admin = ? AND `end` > UNIX_TIMESTAMP()");
            $stmt->execute([$user_id]);
            $user_bots = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }

    // دریافت آمار روزانه
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE DATE(FROM_UNIXTIME(time)) = CURDATE()");
    $stmt->execute();
    $today_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار هفتگی
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))");
    $stmt->execute();
    $weekly_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // دریافت آمار ماهانه
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $stmt->execute();
    $monthly_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // محاسبه رشد
    $growth_rate = 0;
    if ($monthly_users > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE time >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 60 DAY)) AND time < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))");
        $stmt->execute();
        $previous_month = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($previous_month > 0) {
            $growth_rate = round((($monthly_users - $previous_month) / $previous_month) * 100, 1);
        }
    }

    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'data' => [
            'total_users' => (int)$total_users,
            'active_bots' => (int)$active_bots,
            'total_messages' => (int)$total_messages,
            'user_bots' => (int)$user_bots,
            'today_users' => (int)$today_users,
            'weekly_users' => (int)$weekly_users,
            'monthly_users' => (int)$monthly_users,
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