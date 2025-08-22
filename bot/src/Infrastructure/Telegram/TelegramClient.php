<?php

namespace App\Infrastructure\Telegram;

class TelegramClient
{
	private string $token;
	private string $apiBase;

	public function __construct(string $token, string $apiBase = 'https://api.telegram.org')
	{
		$this->token = $token;
		$this->apiBase = rtrim($apiBase, '/');
	}

	private function endpoint(string $method): string
	{
		return $this->apiBase . '/bot' . $this->token . '/' . $method;
	}

	public function call(string $method, array $params = []): array
	{
		$ch = curl_init($this->endpoint($method));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$response = curl_exec($ch);
		if ($response === false) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new \RuntimeException('Telegram API request failed: ' . $err);
		}
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$data = json_decode($response, true) ?: [];
		if ($code !== 200 || !($data['ok'] ?? false)) {
			throw new \RuntimeException('Telegram API error: HTTP ' . $code . ' - ' . ($data['description'] ?? 'unknown'));
		}
		return $data['result'];
	}

	public function sendMessage(array $params): array
	{
		return $this->call('sendMessage', $params);
	}

	public function editMessageText(array $params): array
	{
		return $this->call('editMessageText', $params);
	}

	public function answerCallbackQuery(array $params): array
	{
		return $this->call('answerCallbackQuery', $params);
	}

	public function getChatMember(array $params): array
	{
		return $this->call('getChatMember', $params);
	}
}