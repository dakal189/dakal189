<?php
namespace Guardian;

use Dotenv\Dotenv;
use PDO;

class Bootstrap {
	public PDO $pdo;

	public function __construct(string $projectRoot) {
		if (file_exists($projectRoot.'/.env')) {
			Dotenv::createImmutable($projectRoot)->load();
		}
		$this->pdo = $this->createPdo();
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	private function createPdo(): PDO {
		$host = $_ENV['MYSQL_HOST'] ?? '127.0.0.1';
		$port = (int)($_ENV['MYSQL_PORT'] ?? 3306);
		$db = $_ENV['MYSQL_DB'] ?? 'guardian';
		$user = $_ENV['MYSQL_USER'] ?? 'root';
		$pass = $_ENV['MYSQL_PASS'] ?? '';
		$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
		return new PDO($dsn, $user, $pass, [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}
}