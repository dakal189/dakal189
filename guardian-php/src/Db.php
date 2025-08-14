<?php
namespace Guardian;

use PDO;

class Db {
	public function __construct(private PDO $pdo) {}

	public function migrate(string $sqlPath): void {
		$sql = file_get_contents($sqlPath);
		$this->pdo->exec($sql);
	}

	public function getSettings(int $chatId): array {
		$st = $this->pdo->prepare('SELECT * FROM settings WHERE chat_id = ?');
		$st->execute([$chatId]);
		$row = $st->fetch();
		if ($row) return $row;
		$this->pdo->prepare('INSERT INTO settings (chat_id) VALUES (?)')->execute([$chatId]);
		$st = $this->pdo->prepare('SELECT * FROM settings WHERE chat_id = ?');
		$st->execute([$chatId]);
		return $st->fetch();
	}

	public function setSetting(int $chatId, string $key, $value): void {
		$key = preg_replace('/[^a-z_]/', '', $key);
		$this->pdo->prepare("UPDATE settings SET {$key} = :v WHERE chat_id = :c")->execute([':v'=>$value, ':c'=>$chatId]);
	}

	public function ensureUser(int $chatId, array $user): void {
		$this->pdo->prepare('INSERT IGNORE INTO users (user_id, chat_id, first_name, last_name, username) VALUES (?,?,?,?,?)')
			->execute([$user['id'], $chatId, $user['first_name'] ?? '', $user['last_name'] ?? '', $user['username'] ?? '']);
	}

	public function addWarn(int $chatId, int $userId): void {
		$this->pdo->prepare('INSERT INTO users (user_id, chat_id, warn_count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE warn_count = warn_count + 1')
			->execute([$userId, $chatId]);
	}

	public function getWarn(int $chatId, int $userId): int {
		$st = $this->pdo->prepare('SELECT warn_count FROM users WHERE chat_id = ? AND user_id = ?');
		$st->execute([$chatId, $userId]);
		$row = $st->fetch();
		return (int)($row['warn_count'] ?? 0);
	}

	public function logSanction(int $chatId, int $userId, string $action, string $reason = '', ?int $expiresAt = null): void {
		$this->pdo->prepare('INSERT INTO sanctions (chat_id, user_id, action, reason, expires_at) VALUES (?,?,?,?,?)')
			->execute([$chatId, $userId, $action, $reason, $expiresAt]);
	}

	public function getSanctions(int $chatId, int $limit = 200): array {
		$st = $this->pdo->prepare('SELECT * FROM sanctions WHERE chat_id = ? ORDER BY id DESC LIMIT ?');
		$st->bindValue(1, $chatId, PDO::PARAM_INT);
		$st->bindValue(2, $limit, PDO::PARAM_INT);
		$st->execute();
		return $st->fetchAll();
	}
}