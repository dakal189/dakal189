<?php
declare(strict_types=1);

namespace App\Domain\Users;

use PDO;

final class UserRepo
{
    public function __construct(private PDO $pdo) {}

    public function ensure(int $userId, string $defaultLang): array
    {
        $row = $this->find($userId);
        if ($row) {
            return $row;
        }
        $stmt = $this->pdo->prepare('INSERT INTO users (id, lang, is_admin) VALUES (?, ?, 0)');
        $stmt->execute([$userId, $defaultLang]);
        return ['id' => $userId, 'lang' => $defaultLang, 'is_admin' => 0];
    }

    public function find(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setLang(int $userId, string $lang): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET lang = ? WHERE id = ?');
        $stmt->execute([$lang, $userId]);
    }

    public function setState(int $userId, ?string $state): void
    {
        // store in settings table as per-user state to avoid extra column
        $key = 'state:' . $userId;
        if ($state === null) {
            $stmt = $this->pdo->prepare('DELETE FROM settings WHERE `key` = ?');
            $stmt->execute([$key]);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute([$key, $state]);
    }

    public function getState(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $stmt->execute(['state:' . $userId]);
        $row = $stmt->fetch();
        return $row['value'] ?? null;
    }
}

