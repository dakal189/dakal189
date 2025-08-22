<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class ItemService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function listActive(): array
	{
		return $this->db->fetchAll('SELECT * FROM items WHERE is_active = 1 ORDER BY priority DESC, id ASC');
	}

	public function add(string $name, int $requiredPoints, int $priority = 0, ?int $stock = null): int
	{
		return $this->db->insert('INSERT INTO items (name, required_points, priority, stock) VALUES (?, ?, ?, ?)', [
			$name, $requiredPoints, $priority, $stock
		]);
	}

	public function deactivate(int $id): void
	{
		$this->db->execute('UPDATE items SET is_active = 0 WHERE id = ?', [$id]);
	}

	public function findById(int $id): ?array
	{
		return $this->db->fetchOne('SELECT * FROM items WHERE id = ?', [$id]);
	}
}