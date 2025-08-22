<?php

namespace App\Infrastructure\Database;

use PDO;
use PDOException;

class Database
{
	private PDO $pdo;

	public function __construct(array $config)
	{
		$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
			$config['host'],
			$config['port'],
			$config['database'],
			$config['charset'] ?? 'utf8mb4'
		);
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		$this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
	}

	public function pdo(): PDO
	{
		return $this->pdo;
	}

	public function fetchOne(string $sql, array $params = []): ?array
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch();
		return $row !== false ? $row : null;
	}

	public function fetchAll(string $sql, array $params = []): array
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public function execute(string $sql, array $params = []): int
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->rowCount();
	}

	public function insert(string $sql, array $params = []): int
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return (int)$this->pdo->lastInsertId();
	}
}