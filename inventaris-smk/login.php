<?php
require __DIR__ . '/app/init.php';

// Pengguna yang sudah login langsung menuju dashboard.
if (Auth::user()) redirect('pages/dashboard.php');

// Periksa formulir login yang dikirim.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (Auth::attempt(post('username'), $_POST['password'] ?? '')) redirect('pages/dashboard.php');
        flash('danger', 'Username atau password salah.');
    } catch (Throwable $e) {
        flash('danger', 'Tidak dapat terhubung ke database. Pastikan MySQL aktif dan database sudah di-import.');
    }
    redirect('login.php');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk · <?= APP_NAME ?></title>
  <link href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-body login-modern">
  <main class="login-layout">
    <!-- Informasi singkat tentang aplikasi -->
    <section class="login-intro">
      <a class="app-brand login-brand" href="<?= url('login.php') ?>">
        <span class="app-brand-mark"><img src="<?= url('assets/images/logo-baknus.png') ?>" alt="Logo SMK Baknus 666"></span>
        <span>Sarpras<span class="app-brand-light">SMK Baknus 666</span></span>
      </a>
      <div class="login-intro-copy">
        <span class="login-intro-tag">SISTEM INFORMASI SEKOLAH</span>
        <h1>Kelola sarana sekolah dengan lebih mudah.</h1>
        <p>Pantau inventaris, pencatatan peminjaman, dan pengembalian barang dalam satu tempat.</p>
        <div class="login-feature-list">
          <span><?= icon('check', 17) ?> Data inventaris terpusat</span>
          <span><?= icon('check', 17) ?> Pemantauan stok tersedia</span>
          <span><?= icon('check', 17) ?> Riwayat transaksi tercatat</span>
        </div>
      </div>
      <div class="login-intro-footer"><?= e(APP_NAME) ?> <span>· Sarana &amp; Prasarana</span></div>
    </section>

    <!-- Formulir login dan tautan katalog publik -->
    <section class="login-card">
      <div class="login-card-heading">
        <span class="login-greeting">SELAMAT DATANG KEMBALI</span>
        <h2>Masuk ke akun</h2>
        <p>Gunakan akun petugas untuk melanjutkan.</p>
      </div>
      <?= flash_show() ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="login-username">Username</label>
          <input id="login-username" name="username" class="form-control form-control-lg" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label" for="login-password">Password</label>
          <input id="login-password" type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary btn-lg w-100">Masuk <span aria-hidden="true">→</span></button>
      </form>
      <div class="login-divider"><span>ATAU</span></div>
      <a class="login-public-link" href="<?= url('pages/katalog.php') ?>"><?= icon('box', 18) ?><span><b>Lihat katalog inventaris</b><small>Akses publik untuk peminjam, tanpa login</small></span><span class="login-link-arrow">↗</span></a>
      <p class="login-role-note">Login hanya untuk administrator dan operator.</p>
    </section>
  </main>
</body>
</html>
