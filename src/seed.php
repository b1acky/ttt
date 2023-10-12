<?php

/**
 *  Заполнить таблицу users сгенерированными данными.
 * Вставляет данные чанками по 10.000 строк за один раз.
 */
function seed_data(): void
{
    $count = 5 * 1e6;

    $now = (int)microtime(true);

    $chunk = [];
    for ($i = 1; $i <= $count; $i++) {
        $username  = uniqid('user_');

        $chunk[] = [
            'username'  => $username,
            'email'     => "$username@kek.xx",
            'validts'   => _roll_random(0.8) ? 0 : ($now + mt_rand(0, 86400 * 14)),
            'confirmed' => (int)_roll_random(0.15),
            'checked'   => 0,
            'valid'     => 0
        ];

        if ($i % 10000 == 0) {
            db_insert_multi('users', $chunk);

            $chunk = [];
        }
    }
}

function _roll_random(float $probability): bool
{
    if ($probability < 0 || $probability > 1) {
        throw new InvalidArgumentException('probability should be in interval [0, 1]');
    }

    return mt_rand() / mt_getrandmax() < $probability;
}