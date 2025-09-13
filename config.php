<?php
return [
    'site' => [
        'title' => 'Sigara Sayaç',
    ],
    'timezone' => 'Europe/Istanbul',
'labels' => [
        'user1' => 'Elif',
        'user2' => 'Kerem',
    ],
    'db' => [
        'host' => 'localhost', // veya hosting'inizin veritabanı host'u
        'port' => 3306,
        'user' => 'qwedsa', // hosting'inizdeki veritabanı kullanıcı adı
        'pass' => 'Qwedsa12.', // hosting'inizdeki veritabanı şifresi
        'name' => 'sigara', // hosting'inizdeki veritabanı adı
        'charset' => 'utf8mb4',
    ],
    'auth' => [
        'user1_password' => 'sifre1',
        'user2_password' => 'sifre2',
    ],
'security' => [
        'session_name' => 'sigara_sess',
        'remember_me_secret' => 'change_this_secret',
        'remember_me_days' => 30,
        'remember_cookie_name' => 'sigara_remember',
    ],
    'smtp' => [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'user@example.com',
        'password' => 'your_password',
        'secure' => 'tls',
        'from_email' => 'no-reply@example.com',
        'from_name' => 'Sigara Sayaç',
    ],
];

