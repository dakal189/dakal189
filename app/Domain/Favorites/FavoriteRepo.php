<?php
declare(strict_types=1);

namespace App\Domain\Favorites;

use PDO;

final class FavoriteRepo
{
    public function __construct(private PDO $pdo) {}

    public function toggle(int $userId, string $entity, int $entityId): void
    {
        if ($this->exists($userId, $entity, $entityId)) {
            $stmt = $this->pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND entity = ? AND entity_id = ?');
            $stmt->execute([$userId, $entity, $entityId]);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO favorites (user_id, entity, entity_id) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $entity, $entityId]);
    }

    public function exists(int $userId, string $entity, int $entityId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND entity = ? AND entity_id = ?');
        $stmt->execute([$userId, $entity, $entityId]);
        return (bool)$stmt->fetchColumn();
    }
}

