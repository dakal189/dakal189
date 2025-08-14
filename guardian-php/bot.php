<?php
// Inline env configuration (edit these values if you don't use a .env file)
$_ENV['TELEGRAM_BOT_TOKEN'] = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$_ENV['BOT_OWNER_ID'] = $_ENV['BOT_OWNER_ID'] ?? '123456789';
$_ENV['WEB_APP_SECRET'] = $_ENV['WEB_APP_SECRET'] ?? 'devsecret';
$_ENV['WEB_ORIGIN'] = $_ENV['WEB_ORIGIN'] ?? 'http://localhost:2083';
$_ENV['LOG_CHANNEL_ID'] = $_ENV['LOG_CHANNEL_ID'] ?? '';

$_ENV['MYSQL_HOST'] = $_ENV['MYSQL_HOST'] ?? '127.0.0.1';
$_ENV['MYSQL_PORT'] = $_ENV['MYSQL_PORT'] ?? '3306';
$_ENV['MYSQL_DB'] = $_ENV['MYSQL_DB'] ?? 'guardian';
$_ENV['MYSQL_USER'] = $_ENV['MYSQL_USER'] ?? 'root';
$_ENV['MYSQL_PASS'] = $_ENV['MYSQL_PASS'] ?? '';

require __DIR__.'/app.php';

$app = new App();

if (php_sapi_name() === 'cli') {
	$app->runBot();
	exit;
}

$app->handleWeb();