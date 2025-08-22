<?php

$root = dirname(__DIR__);
$config = require $root . '/config.php';
require_once $root . '/src/Autoloader.php';

use App\Autoloader;
use App\Infrastructure\Database\Database;

$autoloader = new Autoloader($root . '/src');
$autoloader->register();

$db = new Database($config['db']);
$pdo = $db->pdo();

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255) UNIQUE, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");

$dir = $root . '/database/migrations';
if (!is_dir($dir)) {
	mkdir($dir, 0777, true);
}

$files = glob($dir . '/*.sql');
sort($files);

$applied = $db->fetchAll('SELECT filename FROM migrations');
$appliedSet = array_flip(array_map(fn($r) => $r['filename'], $applied));

foreach ($files as $file) {
	$filename = basename($file);
	if (isset($appliedSet[$filename])) {
		continue;
	}
	$sql = file_get_contents($file);
	if ($sql === false) {
		throw new RuntimeException('Cannot read ' . $file);
	}
	$pdo->beginTransaction();
	try {
		$pdo->exec($sql);
		$stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
		$stmt->execute([$filename]);
		$pdo->commit();
		echo "Applied: $filename\n";
	} catch (Throwable $e) {
		$pdo->rollBack();
		fwrite(STDERR, "Migration failed $filename: " . $e->getMessage() . "\n");
		exit(1);
	}
}

echo "Migrations up to date.\n";