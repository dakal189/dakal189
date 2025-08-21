<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $user_id = $input['user_id'] ?? ($_GET['user_id'] ?? null);

    $response = [
        'success' => true,
        'admin_id' => (int)$admin,
        'is_admin' => $user_id ? ((string)$user_id === (string)$admin) : false,
        'timestamp' => time()
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}