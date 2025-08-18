<?php
declare(strict_types=1);

namespace App\Domain\Likes;

use PDO;

final class LikeRepo
{
    public function __construct(private PDO $pdo) {}

    public function add(int $userId, string $entity, int $entityId): bool
    {
        // returns true if inserted, false if existed
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO likes (user_id, entity, entity_id) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $entity, $entityId]);
        return $stmt->rowCount() > 0;
    }
}

