#!/usr/bin/env php
<?php
require __DIR__.'/../vendor/autoload.php';

use Guardian\Bootstrap;
use Guardian\Db;
use Guardian\TelegramBot;

$root = dirname(__DIR__);
$bootstrap = new Bootstrap($root);
$db = new Db($bootstrap->pdo);
$db->migrate($root.'/migrations/001_init.sql');

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if ($token === '') {
	fwrite(STDERR, "TELEGRAM_BOT_TOKEN is not set in .env\n");
	exit(1);
}

$bot = new TelegramBot($db, $token);
$bot->setCommands();
$bot->runLongPolling();