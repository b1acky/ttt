<?php

require_once __DIR__ . '/../../include.php';

$t_start = microtime(true);

while (microtime(true) - $t_start < 60) {
    $row = queue_acquire_row();

    if (!$row) {
        sleep(1);

        continue;
    }

    try {
        notifier_process($row);

        db_execute_query('delete from `queue` where `id` = ?', [$row['id']]);
    } catch (Throwable $exception) {
        syslog_send('queue exception: ' . $exception->getMessage());

        // todo например, положить в отдельную таблицу
    }
}
