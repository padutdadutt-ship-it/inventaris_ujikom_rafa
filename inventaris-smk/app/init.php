<?php
// Muat pengaturan aplikasi.
require_once __DIR__ . '/../config/config.php';

// Mulai session untuk menyimpan status login, token CSRF, dan pesan.
session_start();

// Muat kelas dari folder app saat kelas digunakan.
spl_autoload_register(function (string $className): void {
    $classFile = __DIR__ . '/' . $className . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
});

// Muat fungsi bantuan yang dipakai oleh halaman.
require_once __DIR__ . '/helpers.php';
