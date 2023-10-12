<?php

/**
 * Обходит чанками всю таблицу юзеров и добавляет в очередь запланированные нотификации
 */
function notifier_populate_queue(): void
{
    $last_id = 0;

    do {
        $insert = [];

        //  Используем более простой и топорный вариант запроса (проверяем только то, что подписка завершится в будущем)
        // с мыслью о том, что лучше мы напишем запрос, который будет читать +- столько же строк, но мы вынесем логику
        // в код для гибкости и облегчения отладки.
        $query = <<<EOQ
SELECT `id`, `validts`, `checked`, `valid`
FROM `users`
where `id` > $last_id and `validts` > unix_timestamp()
order by `id`
limit 10000
EOQ;
        $rows = db_execute_query($query)->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            foreach (notifier_get_notifications($row) as $queueRow) {
                $insert[] = $queueRow;
            }
        }

        if (!empty($insert)) {
            db_insert_multi('queue', $insert);
        }

        $last_id = $rows[count($rows) - 1]['id'];
    } while(true);
}

/**
 * Возвращает нотификации, которые надо отправить в будущем для заданного юзера
 */
function notifier_get_notifications(array $user, array $days = [1, 3]): array
{
    $now = (int)microtime(true);

    $notValidEmail        = $user['checked'] && !$user['valid'];
    $notSubscribed        = !$user['validts'];
    $inactiveSubscription = $user['validts'] < $now;

    if ($notValidEmail || $notSubscribed || $inactiveSubscription) {
        return [];
    }

    $notifications = [];
    foreach ($days as $day) {
        if ($user['validts'] - $now < $day * 86400) {
            continue;
        }

        $notify_ts = $user['validts'] - $day * 86400;

        //  Добавим час, чтобы покрыть юзеров, у которых нотифай должен был быть час назад (например 2дня23ч), но т.к.
        // мы "опоздали", то он бы не отправился. Так они получат хотя бы одно письмо, пусть и с небольшой неточностью
        // по времени.
        if ($notify_ts + 3600 < $now) {
            continue;
        }

        $notifications[] = ['user_id' => $user['id'], 'notify_ts' => $notify_ts];
    }

    return $notifications;
}

/**
 * Обрабатывает элемент очереди нотификаций - отправляет письмо юзеру, если это возможно
 */
function notifier_process(array $notification): void
{
    $user = db_execute_query('select * from `users` where `id`=?', [$notification['user_id']])->fetch_array(MYSQLI_ASSOC);
    if (!$user) {
        throw new Exception('user not found');
    }

    $canSendEmail = false;

    if ($user['confirmed'] || $user['valid']) {
        $canSendEmail = true;
    } elseif (!$user['checked']) {
        $canSendEmail = check_email($user['email']);

        db_execute_query('update `users` set `checked` = 1, `valid` = ? where `id` = ?', [(int)$canSendEmail, $user['id']]);
    }

    if ($canSendEmail) {
        send_email(
            'subscriptions@karma8.io',
            $user['email'],
            "{$user['username']}, your subscription is expiring soon"
        );

        syslog_send('time to subscription end: ' . number_format(($user['validts'] - (int)microtime(1)) / 86400, 2));
    }
}