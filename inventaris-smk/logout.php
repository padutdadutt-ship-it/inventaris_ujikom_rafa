<?php
require __DIR__ . '/app/init.php';

// Hapus session login, lalu tampilkan pesan bahwa pengguna sudah keluar.
Auth::logout();
session_start();
flash('success', 'Anda telah keluar.');
redirect('login.php');
