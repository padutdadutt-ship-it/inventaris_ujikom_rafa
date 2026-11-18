<?php
$user = Auth::user();
$availableMenuItems = array_values(array_filter(
    menu_items(),
    fn($item) => in_array($user['level'], $item[3], true)
));
$currentPage = 'pages/' . basename($_SERVER['SCRIPT_NAME'])
    . (isset($_GET['m']) ? '?m=' . $_GET['m'] : '');

// Kelompokkan menu untuk navbar samping.
$groups = [
    'MAIN MENU' => ['Dashboard'],
    'INVENTARIS' => ['Inventaris', 'Kategori', 'Ruang'],
    'OPERASIONAL' => ['Peminjaman', 'Pengembalian', 'Data Peminjam', 'Persetujuan Pengajuan', 'User Petugas'],
    'LAPORAN' => ['Laporan'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Halaman') ?> · <?= APP_NAME ?></title>
  <link href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="app-modern">
<div class="app-layout">
  <!-- Navbar utama -->
  <aside class="app-sidebar">
    <a class="app-brand" href="<?= url('pages/dashboard.php') ?>">
      <span class="app-brand-mark"><img src="<?= url('assets/images/logo-baknus.png') ?>" alt="Logo SMK Baknus 666"></span>
      <span>Sarpras<span class="app-brand-light">SMK Baknus 666</span></span>
    </a>
    <nav class="app-nav" aria-label="Navigasi utama">
      <?php foreach ($groups as $group => $labels): ?>
        <?php $groupItems = array_values(array_filter($availableMenuItems, fn($item) => in_array($item[1], $labels, true))); ?>
        <?php if ($groupItems): ?>
          <div class="app-nav-label"><?= e($group) ?></div>
          <?php foreach ($groupItems as [$path, $label, $symbol]): ?>
            <a class="app-nav-link <?= $path === $currentPage ? 'active' : '' ?>" href="<?= url($path) ?>">
              <?= icon($symbol, 18) ?><span><?= e($label) ?></span>
            </a>
          <?php endforeach ?>
        <?php endif ?>
      <?php endforeach ?>
    </nav>
    <div class="app-sidebar-bottom">
      <a class="app-nav-link" href="<?= url('pages/katalog.php') ?>"><?= icon('search', 18) ?><span>Katalog Publik</span></a>
      <a class="app-nav-link app-logout" href="<?= url('logout.php') ?>"><?= icon('logout', 18) ?><span>Keluar</span></a>
    </div>
  </aside>

  <main class="app-main">
    <!-- Header: pencarian dan profil petugas -->
    <header class="app-topbar">
      <form class="app-search" method="get" action="<?= url('pages/katalog.php') ?>">
        <?= icon('search', 17) ?>
        <input name="q" aria-label="Cari inventaris" placeholder="Cari inventaris, kategori, ruang...">
        <span class="app-search-key">/</span>
      </form>
      <div class="app-profile">
        <span class="app-date"><?= date('d M Y') ?></span>
        <span class="app-profile-avatar"><?= e(strtoupper(substr($user['nama'], 0, 1))) ?></span>
        <span class="app-profile-name">
          <b><?= e($user['nama']) ?></b>
          <small><?= e(ucfirst($user['level'])) ?></small>
        </span>
      </div>
    </header>

    <?= flash_show() ?>

    <!-- Judul halaman dan tombol aksi -->
    <div class="page-head">
      <div>
        <p class="app-page-eyebrow"><?= e(strtoupper($title ?? 'HALAMAN')) ?> · SARANA &amp; PRASARANA</p>
        <h1><?= e($heading ?? $title ?? '') ?></h1>
        <?php if (!empty($sub)): ?><p><?= e($sub) ?></p><?php endif ?>
      </div>
      <?php if (!empty($actions)): ?><div class="page-actions no-print"><?= $actions ?></div><?php endif ?>
    </div>
