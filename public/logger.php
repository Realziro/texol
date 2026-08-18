<?php
$logDir = __DIR__ . '/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', $logDir . '/php_errors.log');

error_reporting(E_ALL);

set_exception_handler(function ($exception) {

    error_log(sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
});

set_error_handler(function ($severity, $message, $file, $line) {

    error_log(sprintf(
        "[%s] PHP Error: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line
    ));

    return false;
});