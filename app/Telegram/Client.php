<?php
declare(strict_types=1);

namespace App\Telegram;

use GuzzleHttp\Client as GuzzleClient;

final class Client
{
    private string $token;
    private GuzzleClient $http;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->http = new GuzzleClient([
            'base_uri' => 'https://api.telegram.org/bot' . $token . '/',
            'timeout' => 10.0,
        ]);
    }

    public function call(string $method, array $params = []): array
    {
        $response = $this->http->post($method, [
            'json' => $params,
        ]);
        $data = json_decode((string)$response->getBody(), true);
        if (!($data['ok'] ?? false)) {
            throw new \RuntimeException('Telegram API error: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        return $data['result'];
    }
}

