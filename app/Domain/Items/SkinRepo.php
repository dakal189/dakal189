<?php
declare(strict_types=1);

namespace App\Domain\Items;

use PDO;

final class SkinRepo
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM skins WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM skins WHERE name LIKE ? LIMIT 1');
        $stmt->execute(['%' . $name . '%']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function incrementSearch(int $id): void
    {
        $this->pdo->prepare('UPDATE skins SET search_count = search_count + 1 WHERE id = ?')->execute([$id]);
    }

    public function incrementLike(int $id): void
    {
        $this->pdo->prepare('UPDATE skins SET like_count = like_count + 1 WHERE id = ?')->execute([$id]);
    }
}

