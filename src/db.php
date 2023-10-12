<?php

function db_get_mysqli(): mysqli
{
    static $mysqli;

    if (is_null($mysqli)) {
        $mysqli = mysqli_connect('localhost', 'karma8', 'karma8', 'karma8');
    }

    return $mysqli;
}

function db_execute_query(string $query, array $params = []): mysqli_result|bool
{
    $result = db_get_mysqli()->execute_query($query, $params);

    if (db_get_mysqli()->errno) {
        throw new Exception(sprintf('db exception: %s', db_get_mysqli()->error));
    }

    return $result;
}

function db_insert_multi(string $table, array $rows): mysqli_result|bool
{
    $columns = array_keys($rows[0]);
    $values  = [];

    foreach ($rows as $row) {
        $rowValues = [];
        foreach ($columns as $column) {
            $rowValues[] = is_string($row[$column]) ? "'$row[$column]'" : $row[$column];
        }
        $values[] = '(' . implode(', ', $rowValues) . ')';
    }

    $query = sprintf('INSERT INTO `%s` (%s) VALUES %s', $table, implode(', ', $columns), implode(', ', $values));

    return db_execute_query($query);
}
