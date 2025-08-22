<?php

namespace App\Services;

use App\Infrastructure\Database\Database;
use App\Infrastructure\Telegram\TelegramClient;

class MembershipService
{
	private Database $db;
	private TelegramClient $tg;

	public function __construct(Database $db, TelegramClient $tg)
	{
		$this->db = $db;
		$this->tg = $tg;
	}

	public function getForcedChannels(): array
	{
		return $this->db->fetchAll('SELECT * FROM forced_channels WHERE is_required = 1');
	}

	public function checkAllJoined(int $userTelegramId): array
	{
		$channels = $this->getForcedChannels();
		$missing = [];
		foreach ($channels as $ch) {
			try {
				$member = $this->tg->getChatMember([
					'chat_id' => $ch['channel_id'],
					'user_id' => $userTelegramId,
				]);
				$status = $member['status'] ?? 'left';
				if (!in_array($status, ['member','administrator','creator'], true)) {
					$missing[] = $ch;
				}
			} catch (\Throwable $e) {
				$missing[] = $ch; // treat errors as not joined
			}
		}
		return [count($missing) === 0, $missing];
	}
}