<?php
declare(strict_types=1);

namespace App\Domain\Admin;

use PDO;

final class AdminRepo
{
    public function __construct(private PDO $pdo) {}

    public function isAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM admins WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getForceChannels(): array
    {
        $stmt = $this->pdo->query('SELECT chat_id, username FROM force_channels WHERE active = 1');
        return $stmt->fetchAll() ?: [];
    }

    public function sponsorTail(): string
    {
        $stmt = $this->pdo->query('SELECT username FROM sponsors WHERE active = 1');
        $rows = $stmt->fetchAll();
        $names = [];
        foreach ($rows as $r) {
            if (!empty($r['username'])) {
                $names[] = '@' . $r['username'];
            }
        }
        return implode(' ', $names);
    }
}

