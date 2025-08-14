<?php
require __DIR__.'/../vendor/autoload.php';

use Guardian\Bootstrap;
use Guardian\Db;

$root = dirname(__DIR__);
$bootstrap = new Bootstrap($root);
$db = new Db($bootstrap->pdo);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/' && $method === 'GET') {
	header('Content-Type: text/html; charset=utf-8');
	readfile(__DIR__.'/index.html');
	exit;
}

if ($path === '/api/settings' && $method === 'GET') {
	$chatId = (int)($_GET['chat_id'] ?? 0);
	if (!$chatId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }
	header('Content-Type: application/json');
	echo json_encode(['ok'=>true, 'settings'=>$db->getSettings($chatId)]);
	exit;
}

if ($path === '/api/settings' && $method === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true) ?? [];
	$chatId = (int)($input['chat_id'] ?? 0);
	unset($input['chat_id']);
	foreach ($input as $k=>$v) { $db->setSetting($chatId, $k, $v); }
	header('Content-Type: application/json');
	echo json_encode(['ok'=>true, 'settings'=>$db->getSettings($chatId)]);
	exit;
}

if ($path === '/api/sanctions' && $method === 'GET') {
	$chatId = (int)($_GET['chat_id'] ?? 0);
	header('Content-Type: application/json');
	echo json_encode(['ok'=>true, 'sanctions'=>$db->getSanctions($chatId)]);
	exit;
}

http_response_code(404);
echo 'Not found';