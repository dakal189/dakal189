<?php

use App\Autoloader;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Telegram\TelegramClient;
use App\Infrastructure\Logger;
use App\Webhook\Handler;

require_once __DIR__ . '/../src/Autoloader.php';

$autoloader = new Autoloader(__DIR__ . '/../src');
$autoloader->register();

$config = require __DIR__ . '/../config.php';

date_default_timezone_set($config['app']['timezone']);

$logger = new Logger(__DIR__ . '/../storage/logs/app.log');

$secret = $_GET['secret'] ?? '';
if (!empty($config['app']['webhook_secret']) && $secret !== $config['app']['webhook_secret']) {
	http_response_code(403);
	echo 'forbidden';
	exit;
}

$input = file_get_contents('php://input');
if (!$input) {
	http_response_code(200);
	echo 'ok';
	exit;
}

$update = json_decode($input, true);
if (!$update) {
	$logger->error('Invalid JSON in webhook');
	http_response_code(200);
	echo 'ok';
	exit;
}

$db = new Database($config['db']);
$tg = new TelegramClient($config['telegram']['token'], $config['telegram']['api_base']);

$handler = new Handler($tg, $db, $logger, $config);
try {
	$handler->handle($update);
} catch (Throwable $e) {
	$logger->error('Webhook error', ['e' => $e->getMessage()]);
}

http_response_code(200);

echo 'ok';