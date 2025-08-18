<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Bootstrap;
use App\Domain\Admin\AdminRepo;
use App\Domain\Items\SkinRepo;
use App\Domain\Users\UserRepo;
use App\Domain\Likes\LikeRepo;
use App\Domain\Favorites\FavoriteRepo;
use App\I18n\Translator;
use Throwable;

final class WebhookHandler
{
    private Bootstrap $app;
    private Client $tg;
    private Translator $t;

    private UserRepo $users;
    private SkinRepo $skins;
    private LikeRepo $likes;
    private FavoriteRepo $favorites;
    private AdminRepo $admins;

    public function __construct(Bootstrap $app)
    {
        $this->app = $app;
        $this->tg = new Client($app->getConfig()['bot_token']);
        $this->t = $app->translator();
        $pdo = $app->db()->pdo();
        $this->users = new UserRepo($pdo);
        $this->skins = new SkinRepo($pdo);
        $this->likes = new LikeRepo($pdo);
        $this->favorites = new FavoriteRepo($pdo);
        $this->admins = new AdminRepo($pdo);
    }

    public function handle(array $update): void
    {
        try {
            if (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
                return;
            }
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
                return;
            }
        } catch (Throwable $e) {
            error_log('handle error: ' . $e->getMessage());
        }
    }

    private function handleMessage(array $m): void
    {
        $chatId = $m['chat']['id'];
        $fromId = $m['from']['id'] ?? $chatId;
        $text = trim((string)($m['text'] ?? ''));

        $user = $this->users->ensure((int)$fromId, $this->t->getLang());
        $this->t->setLang($user['lang']);

        if (isset($m['entities'])) {
            foreach ($m['entities'] as $ent) {
                if (($ent['type'] ?? '') === 'bot_command') {
                    $cmd = substr($text, $ent['offset'], $ent['length']);
                    $payload = null;
                    if (str_starts_with($text, '/start') && str_contains($text, ' ')) {
                        $payload = trim(substr($text, strpos($text, ' ')));
                    }
                    $this->handleCommand($chatId, $fromId, $cmd, $payload);
                    return;
                }
            }
        }

        // Language selection by text
        if (in_array($text, ['فارسی','English','Русский'], true)) {
            $lang = $text === 'English' ? 'en' : ($text === 'Русский' ? 'ru' : 'fa');
            $this->users->setLang((int)$fromId, $lang);
            $this->t->setLang($lang);
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $this->t->t('language_set', ['lang' => $lang]),
                'reply_markup' => json_encode(KeyboardFactory::mainMenu($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        // Main menu handling basics (Skins only for now)
        if ($text === $this->t->t('menu.skins')) {
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $this->t->t('prompt.choose_search'),
                'reply_markup' => json_encode(KeyboardFactory::chooseSearch($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            $this->users->setState((int)$fromId, 'skins_menu');
            return;
        }

        $state = $this->users->getState((int)$fromId);
        if ($state === 'skins_menu') {
            if ($text === $this->t->t('search.by_id')) {
                $this->users->setState((int)$fromId, 'skins_wait_id');
                $this->tg->call('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $this->t->t('prompt.search_by_id'),
                ]);
                return;
            }
            if ($text === $this->t->t('search.by_name')) {
                $this->users->setState((int)$fromId, 'skins_wait_name');
                $this->tg->call('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $this->t->t('prompt.search_by_name'),
                ]);
                return;
            }
        }
        if ($state === 'skins_wait_id') {
            $id = (int)$text;
            $skin = $this->skins->findById($id);
            if (!$skin) {
                $this->tg->call('sendMessage', [ 'chat_id' => $chatId, 'text' => $this->t->t('not_found') ]);
                return;
            }
            $this->sendSkin($chatId, (int)$fromId, $skin);
            $this->users->setState((int)$fromId, null);
            return;
        }
        if ($state === 'skins_wait_name') {
            $skin = $this->skins->findByName($text);
            if (!$skin) {
                $this->tg->call('sendMessage', [ 'chat_id' => $chatId, 'text' => $this->t->t('not_found') ]);
                return;
            }
            $this->sendSkin($chatId, (int)$fromId, $skin);
            $this->users->setState((int)$fromId, null);
            return;
        }

        // default send main menu
        $this->tg->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $this->t->t('main_menu'),
            'reply_markup' => json_encode(KeyboardFactory::mainMenu($this->t), JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function handleCommand(int $chatId, int $userId, string $cmd, ?string $payload): void
    {
        if ($cmd === '/start') {
            // Force join check
            if ($this->needForceJoin($userId)) {
                $channels = $this->admins->getForceChannels();
                $this->tg->call('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $this->t->t('force_join'),
                    'reply_markup' => json_encode(KeyboardFactory::forceJoinButtons($this->t, $channels), JSON_UNESCAPED_UNICODE),
                ]);
                return;
            }
            // payload deep link
            if ($payload) {
                $this->dispatchDeepLink($chatId, $userId, $payload);
                return;
            }
            // ensure user exists
            $this->users->ensure($userId, $this->t->getLang());
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $this->t->t('main_menu'),
                'reply_markup' => json_encode(KeyboardFactory::mainMenu($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        if ($cmd === '/lang') {
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $this->t->t('choose_language'),
                'reply_markup' => json_encode(KeyboardFactory::languagePicker($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        if ($cmd === '/panel') {
            if (!$this->admins->isAdmin($userId)) {
                return;
            }
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $this->t->t('panel.title'),
                'reply_markup' => json_encode(KeyboardFactory::panelMain($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }
    }

    private function handleCallback(array $cb): void
    {
        $data = $cb['data'] ?? '';
        $fromId = (int)($cb['from']['id'] ?? 0);
        $chatId = (int)($cb['message']['chat']['id'] ?? 0);
        $messageId = (int)($cb['message']['message_id'] ?? 0);

        $parts = explode('|', $data);
        if (count($parts) < 5) {
            return;
        }
        [$ver, $action, $entity, $id] = $parts;
        $id = (int)$id;

        if ($action === 'check_membership') {
            if ($this->needForceJoin($fromId)) {
                // still need
                return;
            }
            $this->tg->call('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $this->t->t('main_menu'),
                'reply_markup' => json_encode(KeyboardFactory::mainMenu($this->t), JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        if ($entity === 'skin') {
            if ($action === 'like') {
                if ($this->likes->add($fromId, 'skin', $id)) {
                    $this->skins->incrementLike($id);
                }
                $skin = $this->skins->findById($id);
                if ($skin) {
                    $isFav = $this->favorites->exists($fromId, 'skin', $id);
                    $this->tg->call('editMessageReplyMarkup', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'reply_markup' => json_encode(KeyboardFactory::inlineActions($this->t, 'skin', $id, (int)$skin['like_count'], $isFav), JSON_UNESCAPED_UNICODE),
                    ]);
                }
                return;
            }
            if ($action === 'fav') {
                $this->favorites->toggle($fromId, 'skin', $id);
                $skin = $this->skins->findById($id);
                if ($skin) {
                    $isFav = $this->favorites->exists($fromId, 'skin', $id);
                    $this->tg->call('editMessageReplyMarkup', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'reply_markup' => json_encode(KeyboardFactory::inlineActions($this->t, 'skin', $id, (int)$skin['like_count'], $isFav), JSON_UNESCAPED_UNICODE),
                    ]);
                }
                return;
            }
            if ($action === 'share') {
                // simply answer callback with t.me link
                $deep = 'https://t.me/' . $this->app->getConfig()['bot_username'] . '?start=item_skin_' . $id;
                $this->tg->call('answerCallbackQuery', [
                    'callback_query_id' => $cb['id'],
                    'text' => $deep,
                    'show_alert' => false,
                ]);
                return;
            }
        }
    }

    private function sendSkin(int $chatId, int $userId, array $skin): void
    {
        $caption = "{$skin['name']}\nID: {$skin['id']}\nGroup: {$skin['group']}\nModel: {$skin['model']}";
        if (!empty($skin['story'])) {
            $caption .= "\n\n\"{$skin['story']}\"";
        }
        $sponsorTail = $this->admins->sponsorTail();
        if ($sponsorTail) {
            $caption .= "\n\n" . str_replace('{sponsors}', $sponsorTail, $this->t->t('share.caption'));
        }

        $isFav = $this->favorites->exists($userId, 'skin', (int)$skin['id']);
        $markup = KeyboardFactory::inlineActions($this->t, 'skin', (int)$skin['id'], (int)$skin['like_count'], $isFav);

        if (!empty($skin['photo_file_id'])) {
            $this->tg->call('sendPhoto', [
                'chat_id' => $chatId,
                'photo' => $skin['photo_file_id'],
                'caption' => $caption,
                'reply_markup' => json_encode($markup, JSON_UNESCAPED_UNICODE),
            ]);
        } else {
            $this->tg->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $caption,
                'reply_markup' => json_encode($markup, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $this->skins->incrementSearch((int)$skin['id']);
    }

    private function dispatchDeepLink(int $chatId, int $userId, string $payload): void
    {
        if (str_starts_with($payload, 'item_skin_')) {
            $id = (int)substr($payload, strlen('item_skin_'));
            $skin = $this->skins->findById($id);
            if ($skin) {
                $this->sendSkin($chatId, $userId, $skin);
                return;
            }
        }
        $this->tg->call('sendMessage', [ 'chat_id' => $chatId, 'text' => $this->t->t('not_found') ]);
    }

    private function needForceJoin(int $userId): bool
    {
        if (!$this->app->getConfig()['force_join_required']) {
            return false;
        }
        $channels = $this->admins->getForceChannels();
        if (!$channels) {
            return false;
        }
        foreach ($channels as $ch) {
            try {
                $res = $this->tg->call('getChatMember', [
                    'chat_id' => $ch['chat_id'],
                    'user_id' => $userId,
                ]);
                $status = $res['status'] ?? '';
                if (!in_array($status, ['member','administrator','creator'], true)) {
                    return true;
                }
            } catch (Throwable $e) {
                return true;
            }
        }
        return false;
    }
}

