<?php

function check_email(string $email): bool
{
    $checking_time = mt_rand(1, 60);

    sleep($checking_time);

    $valid = (bool)mt_rand(0, 1);

    syslog_send("checked email $email (delay: $checking_time): " . ($valid ? 'valid' : 'not valid'));

    return $valid;
}

function send_email(string $from, string $to, string $text): void
{
    $sending_time = mt_rand(1, 10);

    sleep($sending_time);

    syslog_send("sent mail $from $to $text (delay: $sending_time)");
}