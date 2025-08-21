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
    $message = $input['message'] ?? '';
    $user_id = $input['user_id'] ?? '';
    $context = $input['context'] ?? [];

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'Message is required']);
        exit();
    }

    // بررسی پاسخ‌های پیش‌فرض
    $default_response = checkDefaultResponse($message);
    if ($default_response) {
        echo json_encode([
            'success' => true,
            'response' => $default_response,
            'type' => 'default'
        ]);
        exit();
    }

    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ذخیره پیام کاربر
    saveChatMessage($pdo, $user_id, $message, 'user');

    // دریافت پاسخ از AI
    $ai_response = getAIResponse($message, $context, $user_id);

    // ذخیره پاسخ AI
    saveChatMessage($pdo, $user_id, $ai_response, 'ai');

    echo json_encode([
        'success' => true,
        'response' => $ai_response,
        'type' => 'ai'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}

function checkDefaultResponse($message) {
    $message = strtolower(trim($message));
    
    $responses = [
        'چطور ربات بسازم' => "🤖 برای ساخت ربات در Dakal:\n\n1️⃣ ابتدا به ربات اصلی @Creatorbotdakalbot مراجعه کنید\n2️⃣ دستور /start را ارسال کنید\n3️⃣ از منوی \"ساخت ربات جدید\" استفاده کنید\n4️⃣ نام و توکن ربات خود را وارد کنید\n5️⃣ تنظیمات اولیه را انجام دهید\n\n✅ ربات شما آماده خواهد بود!",
        
        'مشکلات رایج' => "⚠️ مشکلات رایج و راه‌حل‌ها:\n\n🔸 ربات پاسخ نمی‌دهد:\n• توکن را بررسی کنید\n• ربات را restart کنید\n• اتصال اینترنت را چک کنید\n\n🔸 خطای 403:\n• ربات را unblock کنید\n• دسترسی‌ها را بررسی کنید\n\n🔸 پیام‌ها ارسال نمی‌شوند:\n• محدودیت‌های تلگرام را بررسی کنید\n• از flood control استفاده کنید",
        
        'بهینه‌سازی' => "⚡ نکات بهینه‌سازی ربات:\n\n🚀 عملکرد:\n• از کش استفاده کنید\n• کوئری‌های دیتابیس را بهینه کنید\n• فایل‌های بزرگ را فشرده کنید\n\n🔒 امنیت:\n• توکن‌ها را محافظت کنید\n• ورودی‌ها را validate کنید\n• از HTTPS استفاده کنید\n\n📊 آمار:\n• لاگ‌ها را بررسی کنید\n• عملکرد را مانیتور کنید\n• خطاها را رفع کنید",
        
        'قیمت' => "💰 قیمت‌های Dakal:\n\n🆓 رایگان:\n• 1 ربات\n• 100 کاربر\n• امکانات پایه\n\n💎 VIP (20,000 تومان/ماه):\n• 10 ربات\n• کاربران نامحدود\n• امکانات پیشرفته\n• پشتیبانی 24/7\n\n💎 Premium (50,000 تومان/ماه):\n• ربات‌های نامحدود\n• API اختصاصی\n• سفارشی‌سازی کامل\n• پشتیبانی فوری",
        
        'سلام' => "سلام! 👋 چطور می‌تونم کمکتون کنم؟",
        
        'خداحافظ' => "خداحافظ! 👋 امیدوارم بتونم کمکتون کرده باشم. اگر سوال دیگه‌ای داشتید، در خدمت هستم! 😊",
        
        'تشکر' => "خواهش می‌کنم! 😊 خوشحالم که بتونم کمکتون کنم. اگر سوال دیگه‌ای داشتید، حتماً بپرسید!",
        
        'کمک' => "🔧 چطور می‌تونم کمکتون کنم؟\n\n• ساخت ربات\n• حل مشکلات\n• بهینه‌سازی\n• قیمت‌گذاری\n• راهنمایی فنی\n\nهر کدوم از این موارد رو انتخاب کنید یا سوال خودتون رو بپرسید!",
        
        'امکانات' => "🚀 امکانات Dakal:\n\n🤖 ساخت ربات:\n• ربات‌های چندگانه\n• تنظیمات پیشرفته\n• قالب‌های آماده\n\n📊 مدیریت:\n• آمار کامل\n• گزارش‌گیری\n• کنترل کاربران\n\n💬 ارتباطات:\n• ارسال انبوه\n• پیام‌های زمان‌بندی شده\n• چت با AI\n\n🔒 امنیت:\n• احراز هویت\n• رمزنگاری\n• پشتیبان‌گیری"
    ];

    foreach ($responses as $keyword => $response) {
        if (strpos($message, $keyword) !== false) {
            return $response;
        }
    }

    return null;
}

function getAIResponse($message, $context, $user_id) {
    // در اینجا می‌تونید از API های مختلف AI استفاده کنید
    // مثال با ChatGPT یا سایر سرویس‌ها
    
    // برای حال حاضر، از پاسخ‌های هوشمند استفاده می‌کنیم
    $smart_response = getSmartResponse($message, $context);
    
    if ($smart_response) {
        return $smart_response;
    }

    // پاسخ عمومی
    return "متأسفانه در حال حاضر قادر به پاسخگویی به این سوال نیستم. لطفاً سوال خود را به شکل دیگری مطرح کنید یا از منوی راهنما استفاده کنید. 😊";
}

function getSmartResponse($message, $context) {
    $message = strtolower($message);
    
    // تشخیص نوع سوال
    if (strpos($message, 'چطور') !== false || strpos($message, 'چگونه') !== false) {
        return "برای راهنمایی کامل، لطفاً موضوع مورد نظر خود را مشخص کنید:\n\n• ساخت ربات\n• تنظیمات\n• مشکلات فنی\n• بهینه‌سازی\n\nیا از دکمه‌های سریع استفاده کنید! 🚀";
    }
    
    if (strpos($message, 'خطا') !== false || strpos($message, 'مشکل') !== false) {
        return "برای حل مشکل، لطفاً اطلاعات بیشتری ارائه دهید:\n\n• نوع خطا\n• زمان رخداد\n• مراحل انجام شده\n\nیا از بخش \"مشکلات رایج\" استفاده کنید! 🔧";
    }
    
    if (strpos($message, 'قیمت') !== false || strpos($message, 'هزینه') !== false) {
        return "💰 قیمت‌های Dakal:\n\n🆓 رایگان: 1 ربات، 100 کاربر\n💎 VIP: 20,000 تومان/ماه\n💎 Premium: 50,000 تومان/ماه\n\nبرای اطلاعات بیشتر، \"قیمت‌گذاری\" را بپرسید!";
    }
    
    if (strpos($message, 'ربات') !== false) {
        return "🤖 درباره ربات‌ها:\n\n• ساخت ربات جدید\n• مدیریت ربات‌ها\n• تنظیمات ربات\n• مشکلات ربات\n\nکدوم مورد رو می‌خواید؟";
    }
    
    if (strpos($message, 'تلگرام') !== false) {
        return "📱 درباره تلگرام:\n\n• API تلگرام\n• محدودیت‌ها\n• بهترین روش‌ها\n• امنیت\n\nچه اطلاعاتی نیاز دارید؟";
    }
    
    return null;
}

function saveChatMessage($pdo, $user_id, $message, $type) {
    try {
        $stmt = $pdo->prepare("INSERT INTO ai_chat_logs (user_id, message, type, timestamp) VALUES (?, ?, ?, UNIX_TIMESTAMP())");
        $stmt->execute([$user_id, $message, $type]);
    } catch (Exception $e) {
        // در صورت خطا، لاگ کنید اما ادامه دهید
        error_log("Error saving chat message: " . $e->getMessage());
    }
}

// تابع برای ایجاد جدول chat_logs اگر وجود نداشته باشد
function createChatLogsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS ai_chat_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        message TEXT NOT NULL,
        type ENUM('user', 'ai') NOT NULL,
        timestamp INT NOT NULL,
        INDEX idx_user_time (user_id, timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log("Error creating chat_logs table: " . $e->getMessage());
    }
}

// ایجاد جدول در صورت نیاز
try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
    createChatLogsTable($pdo);
} catch (Exception $e) {
    // جدول ایجاد نمی‌شود اما برنامه ادامه می‌یابد
}
?>