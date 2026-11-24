<?php
// Local XAMPP defaults. Use a dedicated database user and HTTPS in production.
return [
    'host' => getenv('MONITOR_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('MONITOR_DB_PORT') ?: '3306',
    'name' => getenv('MONITOR_DB_NAME') ?: 'student_monitor',
    'user' => getenv('MONITOR_DB_USER') ?: 'root',
    'pass' => getenv('MONITOR_DB_PASS') !== false ? getenv('MONITOR_DB_PASS') : '',
];
