<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Telegram\WebhookHandler;

require __DIR__ . '/../vendor/autoload.php';

// Basic secret check to avoid random hits
$secret = $_GET['secret'] ?? '';

$bootstrap = new Bootstrap();
$config = $bootstrap->getConfig();

if (!empty($config['webhook_secret']) && $secret !== $config['webhook_secret']) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$handler = new WebhookHandler($bootstrap);
$raw = file_get_contents('php://input') ?: '';

if ($raw === '') {
    echo 'ok';
    exit;
}

// Telegram requires 200 within short time, keep minimal blocking
try {
    $update = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $handler->handle($update);
} catch (Throwable $e) {
    // minimal logging
    error_log('Webhook error: ' . $e->getMessage());
}

echo 'ok';

