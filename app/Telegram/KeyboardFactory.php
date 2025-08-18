<?php
declare(strict_types=1);

namespace App\Telegram;

use App\I18n\Translator;

final class KeyboardFactory
{
    public static function languagePicker(Translator $t): array
    {
        return [
            'keyboard' => [
                [['text' => 'فارسی'], ['text' => 'English'], ['text' => 'Русский']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }

    public static function mainMenu(Translator $t): array
    {
        return [
            'keyboard' => [
                [['text' => $t->t('menu.skins')], ['text' => $t->t('menu.vehicles')]],
                [['text' => $t->t('menu.colors')], ['text' => $t->t('menu.weather')]],
                [['text' => $t->t('menu.objects')], ['text' => $t->t('menu.weapons')]],
                [['text' => $t->t('menu.maps')]],
                [['text' => $t->t('menu.color_ai')]],
                [['text' => $t->t('menu.rules')], ['text' => $t->t('menu.favorites')]],
                [['text' => $t->t('menu.random')]],
            ],
            'resize_keyboard' => true,
        ];
    }

    public static function chooseSearch(Translator $t): array
    {
        return [
            'keyboard' => [
                [['text' => $t->t('search.by_id')], ['text' => $t->t('search.by_name')]],
                [['text' => $t->t('panel.back')]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    public static function inlineActions(Translator $t, string $entity, int $id, int $likeCount, bool $isFav): array
    {
        $favText = $isFav ? $t->t('button.fav_remove') : $t->t('button.fav_add');
        return [
            'inline_keyboard' => [
                [
                    ['text' => str_replace('{count}', (string)$likeCount, $t->t('button.like')), 'callback_data' => self::cb('like', $entity, $id)],
                    ['text' => $t->t('button.share'), 'callback_data' => self::cb('share', $entity, $id)],
                    ['text' => $favText, 'callback_data' => self::cb('fav', $entity, $id)],
                ],
            ],
        ];
    }

    public static function forceJoinButtons(Translator $t, array $channels): array
    {
        $rows = [];
        foreach ($channels as $ch) {
            $title = isset($ch['username']) && $ch['username'] ? '@' . $ch['username'] : (string)$ch['chat_id'];
            $url = isset($ch['username']) && $ch['username'] ? 'https://t.me/' . $ch['username'] : null;
            if ($url) {
                $rows[] = [ ['text' => $title, 'url' => $url] ];
            }
        }
        $rows[] = [ ['text' => $t->t('check_membership'), 'callback_data' => self::cb('check_membership','sys',0)] ];
        return ['inline_keyboard' => $rows];
    }

    public static function panelMain(Translator $t): array
    {
        return [
            'keyboard' => [
                [['text' => $t->t('panel.skins')]],
                [['text' => $t->t('panel.back')]],
            ],
            'resize_keyboard' => true,
        ];
    }

    private static function cb(string $action, string $entity, int $id): string
    {
        $nonce = substr(bin2hex(random_bytes(2)), 0, 4);
        return '1|' . $action . '|' . $entity . '|' . $id . '|' . $nonce;
    }
}

