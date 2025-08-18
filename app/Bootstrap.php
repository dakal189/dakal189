<?php
declare(strict_types=1);

namespace App;

use App\Infra\DB;
use App\Infra\Env;
use App\Infra\LoggerFactory;
use App\I18n\Translator;

final class Bootstrap
{
    private array $config;
    private DB $db;
    private Translator $translator;

    public function __construct()
    {
        Env::load(base_path('.env'));

        $this->config = [
            'bot_token' => Env::get('BOT_TOKEN', ''),
            'bot_username' => Env::get('BOT_USERNAME', ''),
            'webhook_secret' => Env::get('WEBHOOK_SECRET', ''),
            'default_lang' => Env::get('DEFAULT_LANG', 'fa'),
            'force_join_required' => filter_var(Env::get('FORCE_JOIN_REQUIRED', 'true'), FILTER_VALIDATE_BOOLEAN),
        ];

        $this->db = new DB([
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => (int)Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME', 'samp_bot'),
            'user' => Env::get('DB_USER', 'root'),
            'pass' => Env::get('DB_PASS', ''),
        ]);

        $this->translator = new Translator($this->config['default_lang']);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function db(): DB
    {
        return $this->db;
    }

    public function translator(): Translator
    {
        return $this->translator;
    }
}

function base_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    return $path ? $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $root;
}

