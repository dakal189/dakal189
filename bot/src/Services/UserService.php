<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class UserService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function upsertFromTelegram(array $tgUser): array
	{
		$telegramId = (int)$tgUser['id'];
		$existing = $this->db->fetchOne('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
		if ($existing) {
			$this->db->execute('UPDATE users SET username = ?, first_name = ?, last_name = ?, language_code = ? WHERE id = ?', [
				$tgUser['username'] ?? null,
				$tgUser['first_name'] ?? null,
				$tgUser['last_name'] ?? null,
				$tgUser['language_code'] ?? null,
				$existing['id'],
			]);
			return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$existing['id']]);
		}
		$id = $this->db->insert('INSERT INTO users (telegram_id, username, first_name, last_name, language_code) VALUES (?, ?, ?, ?, ?)', [
			$telegramId,
			$tgUser['username'] ?? null,
			$tgUser['first_name'] ?? null,
			$tgUser['last_name'] ?? null,
			$tgUser['language_code'] ?? null,
		]);
		return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
	}

	public function findByTelegramId(int $telegramId): ?array
	{
		return $this->db->fetchOne('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
	}

	public function addPoints(int $userId, int $amount, string $type, ?int $referenceId = null, ?string $description = null): void
	{
		$this->db->execute('INSERT INTO points_transactions (user_id, amount, type, reference_id, description) VALUES (?, ?, ?, ?, ?)', [
			$userId, $amount, $type, $referenceId, $description
		]);
		$this->db->execute('UPDATE users SET points = points + ? WHERE id = ?', [$amount, $userId]);
	}

	public function getReferralLink(int $telegramId, string $botUsername): string
	{
		return 'https://t.me/' . $botUsername . '?start=ref_' . $telegramId;
	}
}