<?php
declare(strict_types=1);

namespace App\Infra;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class LoggerFactory
{
    public static function create(string $name = 'app'): Logger
    {
        $logger = new Logger($name);
        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0777, true);
        }
        $logger->pushHandler(new StreamHandler($logFile, Level::Info));
        return $logger;
    }
}

