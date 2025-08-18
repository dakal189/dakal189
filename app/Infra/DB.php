<?php
declare(strict_types=1);

namespace App\Infra;

use PDO;
use PDOException;
use RuntimeException;

final class DB
{
    private PDO $pdo;

    public function __construct(array $cfg)
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('DB connection failed: ' . $e->getMessage());
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}

