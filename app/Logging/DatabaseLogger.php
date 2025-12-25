<?php

namespace App\Logging;

use Monolog\Logger;

class DatabaseLogger
{
    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler($config['level'] ?? Logger::DEBUG));

        return $logger;
    }
}
