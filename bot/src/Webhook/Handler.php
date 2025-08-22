<?php

namespace App\Webhook;

use App\Infrastructure\Logger;
use App\Infrastructure\Telegram\TelegramClient;
use App\Infrastructure\Database\Database;
use App\Services\UserService;
use App\Services\ReferralService;
use App\Services\MembershipService;
use App\Services\ItemService;
use App\Services\OrderService;
use App\Services\AdminService;
use App\Services\SettingsService;
use App\Support\Keyboards;

class Handler
{
	private TelegramClient $tg;
	private Database $db;
	private Logger $logger;
	private array $config;

	private UserService $users;
	private ReferralService $referrals;
	private MembershipService $membership;
	private ItemService $items;
	private OrderService $orders;
	private AdminService $admins;
	private SettingsService $settings;

	public function __construct(TelegramClient $tg, Database $db, Logger $logger, array $config)
	{
		$this->tg = $tg;
		$this->db = $db;
		$this->logger = $logger;
		$this->config = $config;

		$this->users = new UserService($db);
		$this->referrals = new ReferralService($db);
		$this->membership = new MembershipService($db, $tg);
		$this->items = new ItemService($db);
		$this->orders = new OrderService($db);
		$this->admins = new AdminService($db);
		$this->settings = new SettingsService($db, [
			'points_per_referral' => $this->config['settings']['points_per_referral'] ?? 10,
			'min_hours_before_qualify' => $this->config['settings']['min_hours_before_qualify'] ?? 0,
			'days_revoke_if_left' => $this->config['settings']['days_revoke_if_left'] ?? 0,
		]);
	}

	public function handle(array $update): void
	{
		if (isset($update['message'])) {
			$this->onMessage($update['message']);
			return;
		}
		if (isset($update['callback_query'])) {
			$this->onCallback($update['callback_query']);
			return;
		}
	}

	private function onMessage(array $message): void
	{
		$chatId = $message['chat']['id'];
		$from = $message['from'] ?? $message['chat'];
		$user = $this->users->upsertFromTelegram($from);

		$text = trim($message['text'] ?? '');
		$botUsername = $message['via_bot']['username'] ?? ($this->config['app']['name'] ?? '');

		if (strpos($text, '/start') === 0) {
			$refTelegramId = null;
			if (preg_match('/^\/start\s+ref_(\d+)/', $text, $m)) {
				$refTelegramId = (int)$m[1];
			}
			$this->handleStart($chatId, $user, $refTelegramId);
			return;
		}

		if ($text === '/shop') {
			$this->handleShop($chatId, $user);
			return;
		}

		$this->tg->sendMessage([
			'chat_id' => $chatId,
			'text' => "سلام! برای شروع از /start استفاده کن.",
		]);
	}

	private function handleStart(int $chatId, array $user, ?int $refTelegramId): void
	{
		if ($refTelegramId) {
			$inviter = $this->users->findByTelegramId($refTelegramId);
			if ($inviter) {
				$this->referrals->createPending((int)$inviter['id'], (int)$user['id']);
			}
		}

		list($ok, $missing) = $this->membership->checkAllJoined((int)$user['telegram_id']);
		if (!$ok) {
			$this->tg->sendMessage([
				'chat_id' => $chatId,
				'text' => "لطفاً ابتدا در کانال‌های اجباری عضو شوید و سپس روی بررسی عضویت بزنید.",
				'reply_markup' => json_encode(Keyboards::joinCheck($missing), JSON_UNESCAPED_UNICODE),
			]);
			return;
		}

		// If joined, qualify referral and add points
		$pointsPerReferral = (int)$this->settings->get('points_per_referral', 10);
		$ref = $this->referrals->qualifyIfPending((int)$user['id'], $pointsPerReferral);
		if ($ref) {
			$this->users->addPoints((int)$ref['inviter_user_id'], $pointsPerReferral, 'referral_reward', (int)$ref['id'], 'Referral qualified');
		}

		$this->tg->sendMessage([
			'chat_id' => $chatId,
			'text' => "خوش آمدید! از /shop برای مشاهده آیتم‌ها استفاده کنید.",
		]);
	}

	private function handleShop(int $chatId, array $user): void
	{
		$items = $this->items->listActive();
		if (!$items) {
			$this->tg->sendMessage([
				'chat_id' => $chatId,
				'text' => 'فعلاً آیتمی موجود نیست.'
			]);
			return;
		}
		$text = "فروشگاه:\n";
		foreach ($items as $it) {
			$text .= sprintf("- %s | %d امتیاز\n", $it['name'], $it['required_points']);
		}
		$this->tg->sendMessage([
			'chat_id' => $chatId,
			'text' => $text,
		]);
	}

	private function onCallback(array $cb): void
	{
		$data = $cb['data'] ?? '';
		$message = $cb['message'] ?? null;
		$chatId = $message['chat']['id'] ?? null;
		$from = $cb['from'];
		$user = $this->users->upsertFromTelegram($from);

		if ($data === 'check_join') {
			list($ok, $missing) = $this->membership->checkAllJoined((int)$user['telegram_id']);
			if ($ok) {
				$pointsPerReferral = (int)$this->settings->get('points_per_referral', 10);
				$ref = $this->referrals->qualifyIfPending((int)$user['id'], $pointsPerReferral);
				if ($ref) {
					$this->users->addPoints((int)$ref['inviter_user_id'], $pointsPerReferral, 'referral_reward', (int)$ref['id'], 'Referral qualified');
				}
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'عضویت تایید شد.']);
				if ($chatId) {
					$this->tg->editMessageText([
						'chat_id' => $chatId,
						'message_id' => $message['message_id'],
						'text' => 'عضویت شما تایید شد. از /shop استفاده کنید.',
					]);
				}
			} else {
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'عضویت ناقص است.']);
			}
			return;
		}

		if (strpos($data, 'order_approve:') === 0 || strpos($data, 'order_reject:') === 0) {
			$orderId = (int)substr($data, strpos($data, ':') + 1);
			$isApprove = str_starts_with($data, 'order_approve:');
			if (!$this->admins->isAdminUserId((int)$user['id'])) {
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'دسترسی ندارید.']);
				return;
			}
			$order = $this->orders->findById($orderId);
			if (!$order) {
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'سفارش یافت نشد.']);
				return;
			}
			if ($isApprove) {
				$this->orders->approve($orderId, (int)$user['id']);
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'تایید شد.']);
				$this->tg->editMessageText([
					'chat_id' => $message['chat']['id'],
					'message_id' => $message['message_id'],
					'text' => 'درخواست تایید شد ✅',
				]);
			} else {
				$this->orders->reject($orderId, (int)$user['id']);
				$this->tg->answerCallbackQuery(['callback_query_id' => $cb['id'], 'text' => 'رد شد.']);
				$this->tg->editMessageText([
					'chat_id' => $message['chat']['id'],
					'message_id' => $message['message_id'],
					'text' => 'درخواست رد شد ❌',
				]);
			}
			return;
		}
	}
}