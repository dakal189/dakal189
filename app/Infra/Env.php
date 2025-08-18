<?php
declare(strict_types=1);

namespace App\Infra;

use Dotenv\Dotenv;

final class Env
{
    private static bool $loaded = false;

    public static function load(string $envPath): void
    {
        if (self::$loaded) {
            return;
        }
        $dir = dirname($envPath);
        if (is_file($envPath)) {
            $dotenv = Dotenv::createImmutable($dir, basename($envPath));
            $dotenv->safeLoad();
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }
        return (string)$val;
    }
}

