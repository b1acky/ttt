<?php

require_once __DIR__ . '/../include.php';

migrate();
seed_data();
notifier_populate_queue();
