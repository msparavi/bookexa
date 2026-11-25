<?php
/**
 * Bookexa — Configuration Sample Template
 * Copied safely inside exported site packages. Replaced during /install.
 */

return [
    'app_name'  => 'Bookexa',
    'app_env'   => 'production',
    'timezone'  => 'Asia/Dhaka',

    'base_url'  => '',
    'base_path' => '',

    'db' => [
        'host'    => 'localhost',
        'port'    => '3306',
        'name'    => 'your_database',
        'user'    => 'your_db_user',
        'pass'    => 'your_db_password',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'app_secret'        => 'CHANGE_ME_ON_INSTALL',
        'pdf_cache_enabled' => true,
        'pdf_cache_ttl'     => 21600,
    ],

    'cdn' => [
        'enabled'    => false,
        'driver'     => 'filesystem', // 'filesystem' | 'ftp' | 'http'
        'base_url'   => '',           // e.g. 'https://cdn.domain.com/bookexa'
        'path'       => '',           // cPanel: '/home/USER/cdn.domain.com'
        'ftp'        => [
            'host' => '',
            'port' => 21,
            'user' => '',
            'pass' => '',
            'root' => '/',
            'ssl'  => true,
        ],
        'http'       => [
            'endpoint' => '',
            'secret'   => '',
        ],
        'sync_batch' => 10,
    ],
];
