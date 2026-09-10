<?php
if (file_exists(__DIR__ . "/../core/config.php")) {
    require_once __DIR__ . "/../core/config.php";
}

defined('BKASH_NUMBER') or define('BKASH_NUMBER', '01788674353');
defined('NAGAD_NUMBER') or define('NAGAD_NUMBER', '01788674353');
defined('ROCKET_NUMBER') or define('ROCKET_NUMBER', '01788674353');

return [
    'bkash' => [
        'name'   => 'bKash (Send Money)',
        'number' => BKASH_NUMBER
    ],
    'nagad' => [
        'name'   => 'Nagad (Send Money)',
        'number' => NAGAD_NUMBER
    ],
    'rocket' => [
        'name'   => 'Rocket (Send Money)',
        'number' => ROCKET_NUMBER
    ]
];
