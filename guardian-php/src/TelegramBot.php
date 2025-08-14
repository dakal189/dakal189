<?php
namespace Guardian;

use GuzzleHttp\Client;

class TelegramBot {
	private Client $http;
	private string $apiBase;

	public function __construct(private Db $db, private string $token) {
		$this->http = new Client(['timeout' => 20]);
		$this->apiBase = "https://api.telegram.org/bot{$this->token}";
	}

	public function setCommands(): void {
		$commands = [
			['command' => 'help', 'description' => 'راهنما'],
			['command' => 'settings', 'description' => 'لینک پنل'],
			['command' => 'warn', 'description' => 'اخطار به کاربر'],
			['command' => 'mute', 'description' => 'میوت کاربر'],
			['command' => 'ban', 'description' => 'بن کاربر'],
			['command' => 'lockdown', 'description' => 'قفل کردن گروه'],
		];
		$this->call('setMyCommands', ['commands' => $commands]);
	}

	public function runLongPolling(): void {
		$offset = 0;
		while (true) {
			$updates = $this->call('getUpdates', ['timeout' => 30, 'offset' => $offset]);
			foreach (($updates['result'] ?? []) as $u) {
				$offset = max($offset, (int)$u['update_id'] + 1);
				$this->handleUpdate($u);
			}
		}
	}

	private function handleUpdate(array $update): void {
		if (isset($update['message'])) {
			$this->handleMessage($update['message']);
		} elseif (isset($update['callback_query'])) {
			$this->handleCallback($update['callback_query']);
		}
	}

	private function handleCallback(array $cq): void {
		$data = $cq['data'] ?? '';
		$chatId = $cq['message']['chat']['id'] ?? null;
		$userId = $cq['from']['id'] ?? null;
		if (!$chatId || !$userId) return;
		if (str_starts_with($data, 'captcha:')) {
			$targetId = (int)substr($data, 8);
			if ($targetId === (int)$userId) {
				$this->restrict($chatId, $userId, [
					'can_send_messages' => true,
					'can_send_audios' => true,
					'can_send_documents' => true,
					'can_send_photos' => true,
					'can_send_videos' => true,
					'can_send_video_notes' => true,
					'can_send_voice_notes' => true,
					'can_send_polls' => true,
					'can_send_other_messages' => true,
				]);
				$this->call('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => 'تایید شد. خوش آمدید!']);
			}
		}
	}

	private function handleMessage(array $m): void {
		$chat = $m['chat'] ?? [];
		$chatId = (int)($chat['id'] ?? 0);
		$from = $m['from'] ?? [];
		$userId = (int)($from['id'] ?? 0);
		if (!$chatId || !$userId) return;

		// Ensure settings row
		$settings = $this->db->getSettings($chatId);

		// New member captcha
		if (!empty($m['new_chat_members'])) {
			if ((int)$settings['captcha_required'] === 1) {
				foreach ($m['new_chat_members'] as $mem) {
					$this->restrict($chatId, (int)$mem['id'], ['can_send_messages' => false]);
					$this->sendMessage($chatId, $mem['first_name'].' خوش آمدی! برای تایید روی دکمه بزنید.', [
						'reply_markup' => [
							'inline_keyboard' => [[[ 'text' => 'من ربات نیستم 🤖❌', 'callback_data' => 'captcha:'.$mem['id'] ]]]
						]
					]);
				}
			}
			return;
		}

		// Forwarded
		if (!empty($m['forward_date']) && (int)$settings['anti_forward'] === 1) {
			$this->deleteMessage($chatId, (int)$m['message_id']);
			$this->sendMessage($chatId, 'فوروارد پیام مجاز نیست.');
			return;
		}

		// Ignore admins for content filters
		if ($this->isAdmin($chatId, $userId)) return;

		$text = $m['text'] ?? $m['caption'] ?? '';
		if ((int)$settings['lockdown'] === 1) {
			$this->deleteMessage($chatId, (int)$m['message_id']);
			return;
		}
		if ((int)$settings['anti_link'] === 1 && Filters::containsLink($text)) {
			$this->warnAndMaybeMute($chatId, $userId, 'ارسال لینک ممنوع است');
			$this->deleteMessage($chatId, (int)$m['message_id']);
			return;
		}
		if ((int)$settings['anti_badwords'] === 1 && Filters::containsBadWord($text)) {
			$this->warnAndMaybeMute($chatId, $userId, 'کلمات نامناسب ممنوع است');
			$this->deleteMessage($chatId, (int)$m['message_id']);
			return;
		}

		// Commands
		if (!empty($m['text']) && str_starts_with($m['text'], '/')) {
			$this->handleCommand($m, $settings);
		}
	}

	private function handleCommand(array $m, array $settings): void {
		$chatId = (int)$m['chat']['id'];
		$fromId = (int)$m['from']['id'];
		$text = trim($m['text']);
		$cmd = explode(' ', $text);
		$command = explode('@', ltrim($cmd[0], '/'))[0];
		if (!$this->isAdmin($chatId, $fromId)) return;

		switch ($command) {
			case 'help':
				$this->sendMessage($chatId, "دستورات:\n/settings\n/warn\n/mute\n/ban\n/lockdown on|off");
				break;
			case 'settings':
				$ts = time();
				$hash = hash_hmac('sha256', $chatId.':'.$fromId.':'.$ts, $_ENV['WEB_APP_SECRET'] ?? 'devsecret');
				$url = rtrim($_ENV['WEB_ORIGIN'] ?? 'http://localhost:8080', '/')."/?chat_id={$chatId}&user_id={$fromId}&timestamp={$ts}&hash={$hash}";
				$this->sendMessage($chatId, "پنل مدیریت: {$url}");
				break;
			case 'warn':
				$target = $this->extractTargetUserId($m);
				if ($target) { $this->db->addWarn($chatId, $target); $this->db->logSanction($chatId, $target, 'warn', 'manual'); $this->sendMessage($chatId, 'اخطار ثبت شد'); }
				break;
			case 'mute':
				$target = $this->extractTargetUserId($m);
				$duration = $this->parseDuration($cmd[2] ?? '10m');
				if ($target) { $this->restrict($chatId, $target, ['can_send_messages' => false], time()+$duration); $this->db->logSanction($chatId, $target, 'mute', 'by admin', time()+$duration); $this->sendMessage($chatId, 'کاربر میوت شد'); }
				break;
			case 'ban':
				$target = $this->extractTargetUserId($m);
				if ($target) { $this->call('banChatMember', ['chat_id'=>$chatId, 'user_id'=>$target]); $this->db->logSanction($chatId, $target, 'ban', 'by admin'); $this->sendMessage($chatId, 'کاربر بن شد'); }
				break;
			case 'lockdown':
				$on = ($cmd[1] ?? '') === 'on' ? 1 : 0;
				$this->db->setSetting($chatId, 'lockdown', $on);
				$this->sendMessage($chatId, $on ? 'لاکدان فعال شد' : 'لاکدان غیرفعال شد');
				break;
		}
	}

	private function warnAndMaybeMute(int $chatId, int $userId, string $reason): void {
		$this->db->addWarn($chatId, $userId);
		$warns = $this->db->getWarn($chatId, $userId);
		$settings = $this->db->getSettings($chatId);
		$this->db->logSanction($chatId, $userId, 'warn', $reason);
		$this->sendMessage($chatId, "کاربر {$userId}: {$reason} (اخطار {$warns}/{$settings['max_warns']})");
		if ($warns >= (int)$settings['max_warns']) {
			$this->restrict($chatId, $userId, ['can_send_messages' => false], time()+3600);
			$this->db->logSanction($chatId, $userId, 'mute', 'auto mute after warns', time()+3600);
			$this->sendMessage($chatId, 'کاربر به مدت ۱ ساعت میوت شد');
		}
	}

	private function isAdmin(int $chatId, int $userId): bool {
		try {
			$res = $this->call('getChatMember', ['chat_id'=>$chatId, 'user_id'=>$userId]);
			$status = $res['result']['status'] ?? '';
			return in_array($status, ['creator','administrator'], true);
		} catch (\Throwable $e) { return false; }
	}

	private function extractTargetUserId(array $m): ?int {
		if (!empty($m['reply_to_message']['from']['id'])) return (int)$m['reply_to_message']['from']['id'];
		$parts = explode(' ', $m['text'] ?? '');
		$arg = $parts[1] ?? '';
		if ($arg !== '' && ctype_digit($arg)) return (int)$arg;
		return null;
	}

	private function parseDuration(string $s): int {
		if (!preg_match('/^(\d+)([smhd])$/i', $s, $m)) return 600;
		$v = (int)$m[1]; $u = strtolower($m[2]);
		return match($u) {
			's' => $v,
			'm' => $v*60,
			'h' => $v*3600,
			'd' => $v*86400,
			default => 600,
		};
	}

	private function sendMessage(int $chatId, string $text, array $extra = []): void {
		$this->call('sendMessage', array_merge(['chat_id'=>$chatId, 'text'=>$text], $extra));
	}
	private function deleteMessage(int $chatId, int $messageId): void {
		$this->call('deleteMessage', ['chat_id'=>$chatId, 'message_id'=>$messageId]);
	}
	private function restrict(int $chatId, int $userId, array $permissions, ?int $until = null): void {
		$payload = [
			'chat_id' => $chatId,
			'user_id' => $userId,
			'permissions' => $permissions,
		];
		if ($until) $payload['until_date'] = $until;
		$this->call('restrictChatMember', $payload);
	}

	private function call(string $method, array $params = []): array {
		$res = $this->http->post($this->apiBase.'/'.$method, ['json' => $params]);
		return json_decode((string)$res->getBody(), true) ?? [];
	}
}