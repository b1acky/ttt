<?php

require_once __DIR__ . '/../../include.php';

$query = <<<EOQ
SELECT count(id)
FROM `queue`
WHERE `in_work` = 0 AND `notify_ts` < unix_timestamp()
EOQ;

$count = db_execute_query($query)->fetch_column();

syslog_send('notifications to send now: ' . $count);
