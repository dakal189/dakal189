<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class AdminService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function isAdminUserId(int $userId): bool
	{
		$row = $this->db->fetchOne('SELECT role FROM admins WHERE user_id = ?', [$userId]);
		return (bool)$row;
	}

	public function addAdmin(int $userId, string $role = 'admin'): void
	{
		$this->db->execute('INSERT INTO admins (user_id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)', [$userId, $role]);
	}

	public function removeAdmin(int $userId): void
	{
		$this->db->execute('DELETE FROM admins WHERE user_id = ?', [$userId]);
	}
}