<?php
require __DIR__ . '/app/init.php';

// Halaman awal dapat digunakan publik; petugas yang login menuju dashboard.
redirect(Auth::user() ? 'pages/dashboard.php' : 'pages/katalog.php');
