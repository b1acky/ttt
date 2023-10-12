<?php

function migrate(): void
{
    $migrations = [
        'DROP TABLE IF EXISTS `users`',

        'DROP TABLE IF EXISTS `queue`',

        <<<EOQ
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `email` varchar(256) NOT NULL,
  `validts` int(10) unsigned NOT NULL,
  `confirmed` int(10) unsigned NOT NULL,
  `checked` int(10) unsigned NOT NULL,
  `valid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOQ,

        <<<EOQ
CREATE TABLE `queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `notify_ts` int(10) unsigned NOT NULL,
  `in_work` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `notify_ts` (`notify_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

EOQ
    ];

    foreach ($migrations as $migration) {
        db_execute_query($migration);
    }
}