<?php

namespace App\Support;

class Keyboards
{
	public static function joinCheck(array $channels): array
	{
		$rows = [];
		foreach ($channels as $ch) {
			$rows[] = [
				['text' => 'عضویت در ' . ($ch['title'] ?: $ch['channel_id']), 'url' => 'https://t.me/' . ltrim($ch['channel_id'], '@')],
			];
		}
		$rows[] = [[ 'text' => 'بررسی عضویت ✅', 'callback_data' => 'check_join' ]];
		return [ 'inline_keyboard' => $rows ];
	}

	public static function orderReview(int $orderId): array
	{
		return [
			'inline_keyboard' => [
				[
					['text' => 'تایید درخواست ✅', 'callback_data' => 'order_approve:' . $orderId],
					['text' => 'رد درخواست ❌', 'callback_data' => 'order_reject:' . $orderId],
				],
			],
		];
	}
}