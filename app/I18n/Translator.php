<?php
declare(strict_types=1);

namespace App\I18n;

final class Translator
{
    private string $lang;
    private array $catalogs = [];

    public function __construct(string $defaultLang)
    {
        $this->lang = $defaultLang;
        $this->load('fa');
        $this->load('en');
        $this->load('ru');
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, ['fa','en','ru'], true) ? $lang : 'fa';
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    private function load(string $lang): void
    {
        $file = dirname(__DIR__) . '/I18n/' . $lang . '.php';
        if (is_file($file)) {
            $this->catalogs[$lang] = require $file;
        } else {
            $this->catalogs[$lang] = [];
        }
    }

    public function t(string $key, array $params = []): string
    {
        $value = $this->catalogs[$this->lang][$key] ?? $this->catalogs['en'][$key] ?? $key;
        foreach ($params as $k => $v) {
            $value = str_replace('{' . $k . '}', (string)$v, $value);
        }
        return $value;
    }
}

