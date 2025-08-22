<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class OrderService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function create(int $userId, int $itemId, ?string $note = null): int
	{
		return $this->db->insert('INSERT INTO orders (user_id, item_id, note, status) VALUES (?, ?, ?, "pending")', [
			$userId, $itemId, $note
		]);
	}

	public function setUnderReview(int $orderId, ?int $adminMessageId): void
	{
		$this->db->execute('UPDATE orders SET status = "under_review", admin_message_id = ? WHERE id = ?', [$adminMessageId, $orderId]);
	}

	public function approve(int $orderId, int $adminUserId): void
	{
		$this->db->execute('UPDATE orders SET status = "approved", admin_id = ? WHERE id = ?', [$adminUserId, $orderId]);
	}

	public function reject(int $orderId, int $adminUserId): void
	{
		$this->db->execute('UPDATE orders SET status = "rejected", admin_id = ? WHERE id = ?', [$adminUserId, $orderId]);
	}

	public function findById(int $id): ?array
	{
		return $this->db->fetchOne('SELECT * FROM orders WHERE id = ?', [$id]);
	}
}