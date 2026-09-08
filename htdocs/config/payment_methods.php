<?php
require_once __DIR__ . "/../core/config.php";

return [
    'bkash' => [
        'name'   => 'bKash (Send Money)',
        'number' => defined('BKASH_NUMBER') ? BKASH_NUMBER : '01788674353'
    ],
    'nagad' => [
        'name'   => 'Nagad (Send Money)',
        'number' => defined('NAGAD_NUMBER') ? NAGAD_NUMBER : '01788674353'
    ],
    'rocket' => [
        'name'   => 'Rocket (Send Money)',
        'number' => '01788674353'
    ]
];
