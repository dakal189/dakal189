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
    $action = $input['action'] ?? '';
    $user_id = $input['user_id'] ?? '';

    // بررسی دسترسی ادمین
    if ($user_id != $admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }

    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ایجاد جدول در صورت نیاز
    createCustomQuestionsTable($pdo);

    switch ($action) {
        case 'add':
            addQuestion($pdo, $input);
            break;
        case 'edit':
            editQuestion($pdo, $input);
            break;
        case 'delete':
            deleteQuestion($pdo, $input);
            break;
        case 'list':
            listQuestions($pdo);
            break;
        case 'toggle':
            toggleQuestion($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}

function addQuestion($pdo, $input) {
    $question = $input['question'] ?? '';
    $keywords = $input['keywords'] ?? '';
    $answer = $input['answer'] ?? '';
    $user_id = $input['user_id'] ?? '';

    if (empty($question) || empty($keywords) || empty($answer)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO ai_custom_questions (question, keywords, answer, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$question, $keywords, $answer, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'سوال با موفقیت اضافه شد',
        'id' => $pdo->lastInsertId()
    ]);
}

function editQuestion($pdo, $input) {
    $id = $input['id'] ?? '';
    $question = $input['question'] ?? '';
    $keywords = $input['keywords'] ?? '';
    $answer = $input['answer'] ?? '';

    if (empty($id) || empty($question) || empty($keywords) || empty($answer)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE ai_custom_questions SET question = ?, keywords = ?, answer = ? WHERE id = ?");
    $stmt->execute([$question, $keywords, $answer, $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'سوال با موفقیت ویرایش شد'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
    }
}

function deleteQuestion($pdo, $input) {
    $id = $input['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Question ID is required']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM ai_custom_questions WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'سوال با موفقیت حذف شد'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
    }
}

function listQuestions($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM ai_custom_questions ORDER BY created_at DESC");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'total' => count($questions)
    ]);
}

function toggleQuestion($pdo, $input) {
    $id = $input['id'] ?? '';
    $is_active = $input['is_active'] ?? 1;

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Question ID is required']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE ai_custom_questions SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active, $id]);

    if ($stmt->rowCount() > 0) {
        $status = $is_active ? 'فعال' : 'غیرفعال';
        echo json_encode([
            'success' => true,
            'message' => "سوال با موفقیت {$status} شد"
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
    }
}

function createCustomQuestionsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS ai_custom_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        keywords TEXT NOT NULL,
        answer TEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_by BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log("Error creating custom_questions table: " . $e->getMessage());
    }
}
?>