<?php

openlog('karma8', 0, LOG_LOCAL5);

register_shutdown_function(fn() => closelog());

function syslog_send($msg): void
{
    static $uid;

    if (!$uid) {
        $uid = crc32(uniqid());
    }

    syslog(LOG_INFO, "[$uid] $msg");
}