<?php

/**
 *  Получить элемент очереди для обработки.
 * Для гарантии, что только один процесс сможет захватить строку, используется оптимистичная блокировка - процесс,
 * который сможет выполнить запрос UPDATE будет являться "владельцем" строки. Остальные процессы получат affected_rows
 * равный нулю и будут искать другую строку.
 */
function queue_acquire_row(): array|false
{
    $query = <<<EOQ
SELECT *
FROM `queue`
WHERE `in_work` = 0 AND `notify_ts` < unix_timestamp()
LIMIT 100
EOQ;

    $rows = db_execute_query($query)->fetch_all(MYSQLI_ASSOC);

    // значительно уменьшает конфликты воркеров между собой
    shuffle($rows);

    foreach ($rows as $row) {
        db_execute_query('update `queue` set `in_work` = 1 where `in_work` = 0 and `id`=' . $row['id']);
        if (db_get_mysqli()->affected_rows == 0) {
            continue;
        }

        return $row;
    }

    return false;
}