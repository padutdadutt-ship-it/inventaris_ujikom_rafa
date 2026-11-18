<?php
require __DIR__ . '/../app/init.php';

// Ambil data pengguna dan data untuk dashboard.
$user = Auth::guard(['administrator', 'operator']);
$isOperator = $user['level'] === 'operator';
$stats = Report::stats();
$monthlyLoans = Report::perBulan();
$activityLog = Report::aktivitas(8);

// Hitung jumlah barang untuk setiap kondisi.
$conditionCounts = array_column(
    Database::all('SELECT kondisi, COALESCE(SUM(jumlah), 0) AS total FROM inventaris GROUP BY kondisi'),
    'total',
    'kondisi'
);
$conditionColors = [
    'Baik' => '#8ed9c6',
    'Rusak Ringan' => '#c4e989',
    'Rusak Berat' => '#f1a4b4',
];
$totalConditionCount = array_sum($conditionCounts);
$conditionSegments = [];
$conditionOffset = 0;
foreach ($conditionColors as $name => $color) {
    $count = (int)($conditionCounts[$name] ?? 0);

    if ($totalConditionCount === 0 || $count === 0) {
        continue;
    }

    $nextOffset = $conditionOffset + ($count / $totalConditionCount * 360);
    $conditionSegments[] = $color . ' ' . round($conditionOffset, 1) . 'deg ' . round($nextOffset, 1) . 'deg';
    $conditionOffset = $nextOffset;
}

// Siapkan warna grafik dan angka ringkasan.
$donutStyle = $conditionSegments
    ? 'background:conic-gradient(' . implode(',', $conditionSegments) . ')'
    : 'background:#e8f0ef';
$maxMonthlyLoans = max(max($monthlyLoans), 1);
$availableUnits = max((int)$stats['unit_total'] - (int)$stats['unit_dipinjam'], 0);
$userInitial = strtoupper(substr($user['nama'], 0, 1));

// Kelompok menu navbar.
$groups = [
    'MAIN MENU' => ['Dashboard'],
    'INVENTARIS' => ['Inventaris', 'Kategori', 'Ruang'],
    'OPERASIONAL' => ['Peminjaman', 'Pengembalian', 'Data Peminjam', 'User Petugas'],
    'LAPORAN' => ['Laporan'],
];
$menu = array_values(array_filter(
    menu_items(),
    fn($item) => in_array($user['level'], $item[3], true)
));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · <?= APP_NAME ?></title>
  <link href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="dashboard-modern">
<div class="dash-layout">
  <!-- Navbar samping -->
  <aside class="dash-sidebar">
    <a class="dash-brand" href="<?= url('pages/dashboard.php') ?>">
      <span class="dash-brand-mark"><img src="<?= url('assets/images/logo-baknus.png') ?>" alt="Logo SMK Baknus 666"></span>
      <span>Sarpras<span class="dash-brand-light">SMK Baknus 666</span></span>
    </a>
    <nav class="dash-nav" aria-label="Navigasi utama">
      <?php foreach ($groups as $group => $labels): ?>
        <?php $groupItems = array_values(array_filter($menu, fn($item) => in_array($item[1], $labels, true))); ?>
        <?php if ($groupItems): ?>
          <div class="dash-nav-label"><?= e($group) ?></div>
          <?php foreach ($groupItems as [$path, $label, $symbol]): ?>
            <a class="dash-nav-link <?= $label === 'Dashboard' ? 'active' : '' ?>" href="<?= url($path) ?>">
              <?= icon($symbol, 18) ?><span><?= e($label) ?></span>
            </a>
          <?php endforeach ?>
        <?php endif ?>
      <?php endforeach ?>
    </nav>
    <div class="dash-sidebar-bottom">
      <a class="dash-nav-link" href="<?= url('pages/katalog.php') ?>"><?= icon('search', 18) ?><span>Katalog Publik</span></a>
      <a class="dash-nav-link dash-logout" href="<?= url('logout.php') ?>"><?= icon('logout', 18) ?><span>Keluar</span></a>
    </div>
  </aside>

  <main class="dash-main">
    <!-- Header: pencarian dan profil pengguna -->
    <header class="dash-topbar">
      <form class="dash-search" method="get" action="<?= url('pages/katalog.php') ?>">
        <?= icon('search', 17) ?>
        <input name="q" aria-label="Cari inventaris" placeholder="Cari inventaris, kategori, ruang...">
        <span class="dash-search-key">/</span>
      </form>
      <div class="dash-profile">
        <span class="dash-date"><?= date('d M Y') ?></span>
        <span class="dash-profile-avatar"><?= e($userInitial) ?></span>
        <span class="dash-profile-name">
          <b><?= e($user['nama']) ?></b>
          <small><?= e(ucfirst($user['level'])) ?></small>
        </span>
      </div>
    </header>

    <!-- Sambutan dan tautan laporan -->
    <section class="dash-welcome">
      <div>
        <p class="dash-eyebrow">RINGKASAN SARANA &amp; PRASARANA</p>
        <h1>Selamat datang, <?= e($user['nama']) ?>!</h1>
        <p class="dash-welcome-sub">Berikut ringkasan inventaris dan aktivitas peminjaman sekolah.</p>
      </div>
      <a class="dash-month" href="<?= url('pages/laporan.php') ?>"><?= icon('clock', 16) ?> Lihat laporan <span>↗</span></a>
    </section>

    <!-- Kartu ringkasan inventaris -->
    <section class="dash-stat-grid" aria-label="Statistik inventaris">
      <article class="dash-stat-card stat-lime">
        <div class="dash-stat-top"><span>Total Inventaris</span><span class="dash-stat-icon"><?= icon('box', 18) ?></span></div>
        <div class="dash-stat-number"><?= number_format((int)$stats['unit_total'], 0, ',', '.') ?><small>unit</small></div>
        <div class="dash-stat-foot"><span class="dash-stat-dot"></span><?= (int)$stats['jenis_barang'] ?> jenis barang terdaftar</div>
        <svg class="dash-sparkline" viewBox="0 0 95 36" aria-hidden="true"><path d="M2 30 C14 28 13 16 26 21 S41 29 49 15 S64 21 73 9 S85 12 93 3" /></svg>
      </article>
      <article class="dash-stat-card stat-mint">
        <div class="dash-stat-top"><span>Stok Tersedia</span><span class="dash-stat-icon"><?= icon('check', 18) ?></span></div>
        <div class="dash-stat-number"><?= number_format($availableUnits, 0, ',', '.') ?><small>unit</small></div>
        <div class="dash-stat-foot"><span class="dash-stat-dot"></span><?= (int)$stats['persen_tersedia'] ?>% siap dipinjam</div>
        <svg class="dash-sparkline" viewBox="0 0 95 36" aria-hidden="true"><path d="M2 29 C13 27 17 10 29 15 S43 27 52 16 S66 18 76 8 S87 12 93 4" /></svg>
      </article>
      <article class="dash-stat-card stat-rose">
        <div class="dash-stat-top"><span>Sedang Dipinjam</span><span class="dash-stat-icon"><?= icon('out', 18) ?></span></div>
        <div class="dash-stat-number"><?= number_format((int)$stats['unit_dipinjam'], 0, ',', '.') ?><small>unit</small></div>
        <div class="dash-stat-foot"><span class="dash-stat-dot"></span><?= (int)$stats['trx_aktif'] ?> transaksi aktif</div>
        <svg class="dash-sparkline" viewBox="0 0 95 36" aria-hidden="true"><path d="M2 9 C13 11 17 25 28 19 S42 8 50 21 S65 18 74 28 S87 21 93 32" /></svg>
      </article>
      <article class="dash-stat-card stat-blue">
        <div class="dash-stat-top"><span>Peminjam Aktif</span><span class="dash-stat-icon"><?= icon('users', 18) ?></span></div>
        <div class="dash-stat-number"><?= number_format((int)$stats['pegawai_aktif'], 0, ',', '.') ?><small>orang</small></div>
        <div class="dash-stat-foot"><span class="dash-stat-dot"></span>terdaftar sebagai peminjam</div>
        <svg class="dash-sparkline" viewBox="0 0 95 36" aria-hidden="true"><path d="M2 28 C14 22 17 26 28 16 S41 20 51 12 S67 22 76 10 S88 12 93 4" /></svg>
      </article>
    </section>

    <!-- Grafik kondisi barang dan tren peminjaman -->
    <section class="dash-chart-grid">
      <article class="dash-panel dash-donut-panel">
        <div class="dash-panel-heading">
          <div><h2>Kondisi Inventaris</h2><p>Ringkasan kondisi barang saat ini</p></div>
          <span class="dash-more" aria-hidden="true">···</span>
        </div>
        <div class="dash-donut-wrap">
          <div class="dash-donut" style="<?= e($donutStyle) ?>">
            <div class="dash-donut-center">
              <strong><?= number_format((int)$stats['jenis_barang'], 0, ',', '.') ?></strong>
              <span>jenis barang</span>
            </div>
          </div>
        </div>
        <div class="dash-legend">
          <?php foreach ($conditionColors as $name => $color): ?>
            <span>
              <i style="--legend-color:<?= e($color) ?>"></i>
              <?= e($name) ?>
              <b><?= (int)($conditionCounts[$name] ?? 0) ?></b>
            </span>
          <?php endforeach ?>
        </div>
      </article>

      <article class="dash-panel dash-bars-panel">
        <div class="dash-panel-heading">
          <div><h2>Tren Peminjaman</h2><p>Jumlah transaksi dalam 6 bulan terakhir</p></div>
          <span class="dash-period"><?= icon('clock', 14) ?> 6 bulan</span>
        </div>
        <div class="dash-chart-summary">
          <strong><?= array_sum($monthlyLoans) ?></strong>
          <span>transaksi tercatat</span>
        </div>
        <div class="dash-bars" role="img" aria-label="Grafik jumlah peminjaman per bulan">
          <?php foreach ($monthlyLoans as $label => $count): ?>
            <div class="dash-bar-column" title="<?= (int)$count ?> transaksi">
              <span class="dash-bar-value"><?= (int)$count ?></span>
              <div class="dash-bar-track">
                <i style="height:<?= $count ? max(8, round($count / $maxMonthlyLoans * 100)) : 3 ?>%"></i>
              </div>
              <span class="dash-bar-label"><?= e($label) ?></span>
            </div>
          <?php endforeach ?>
        </div>
      </article>
    </section>

    <!-- Tabel aktivitas terbaru -->
    <section class="dash-panel dash-activity-panel">
      <div class="dash-panel-heading dash-activity-heading">
        <div><h2>Aktivitas Terbaru</h2><p>Transaksi peminjaman dan pengembalian terakhir</p></div>
        <div class="dash-activity-actions">
          <label class="dash-table-search"><?= icon('search', 15) ?><input data-filter=".dash-activity-table" aria-label="Cari aktivitas" placeholder="Cari aktivitas..."></label>
          <?php if ($isOperator): ?>
            <a class="dash-text-link" href="<?= url('pages/peminjaman.php') ?>">
              Buat transaksi <?= icon('out', 15) ?>
            </a>
          <?php endif ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="dash-activity-table">
          <thead><tr><th>Aktivitas</th><th>Peminjam</th><th>Waktu</th><th>ID Transaksi</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($activityLog as $activity): ?>
            <?php $borrow = $activity['tipe'] === 'pinjam'; ?>
            <tr>
              <td><span class="dash-activity-icon <?= $borrow ? 'activity-borrow' : 'activity-return' ?>"><?= icon($borrow ? 'out' : 'back', 17) ?></span><b><?= $borrow ? 'Peminjaman' : 'Pengembalian' ?></b></td>
              <td><?= e($activity['nama_pegawai']) ?></td>
              <td><?= date('d M Y · H.i', strtotime($activity['waktu'])) ?></td>
              <td><span class="dash-id">#<?= str_pad($activity['id_peminjaman'], 5, '0', STR_PAD_LEFT) ?></span></td>
              <td><span class="dash-status <?= $borrow ? 'status-borrow' : 'status-return' ?>"><?= $borrow ? 'Dipinjam' : 'Dikembalikan' ?></span></td>
            </tr>
          <?php endforeach ?>
          <?php if (!$activityLog): ?>
            <tr>
              <td colspan="5" class="dash-empty">Belum ada aktivitas.</td>
            </tr>
          <?php endif ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Footer halaman -->
    <footer class="dash-footer"><?= e(APP_NAME) ?> <span>· Sistem informasi inventaris sekolah</span></footer>
  </main>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
