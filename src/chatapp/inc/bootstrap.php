<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Auth.php';

ini_set('display_errors', Config::isProd() ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[%s] %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    Http::json(
        Config::isProd()
            ? ['error' => 'Internal server error']
            : ['error' => $e->getMessage(), 'type' => $e::class],
        500,
    );
});
