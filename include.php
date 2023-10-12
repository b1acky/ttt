<?php

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/email.php';
require_once __DIR__ . '/src/migrate.php';
require_once __DIR__ . '/src/notifier.php';
require_once __DIR__ . '/src/queue.php';
require_once __DIR__ . '/src/seed.php';
require_once __DIR__ . '/utils/syslog.php';

set_exception_handler(function (Throwable $e) {
    syslog_send("exception: " . $e->getMessage());
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    syslog_send('error: ' . json_encode(compact('errno', 'errstr', 'errfile', 'errline')));
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    syslog_send("exception: " . json_encode($error));
});