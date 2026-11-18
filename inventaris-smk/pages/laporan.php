<?php
// Halaman laporan: pratinjau, cetak, export, dan import inventaris.
require __DIR__ . '/../app/init.php';
Auth::guard(['administrator']);

// Ambil jenis laporan dan rentang tanggal dari URL.
$reportType = ($_GET['jenis'] ?? 'peminjaman') === 'inventaris' ? 'inventaris' : 'peminjaman';
$startDate = $_GET['dari'] ?? date('Y-m-01');
$endDate = $_GET['sampai'] ?? date('Y-m-d');

if (!strtotime($startDate) || !strtotime($endDate) || $startDate > $endDate) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

// Ambil data laporan berdasarkan jenis yang dipilih.
$reportRows = $reportType === 'peminjaman'
    ? Report::peminjaman($startDate, $endDate)
    : Report::inventaris($startDate, $endDate);
$reportQuery = http_build_query([
    'jenis' => $reportType,
    'dari' => $startDate,
    'sampai' => $endDate,
]);

$title = 'Laporan';
$sub = 'Lihat laporan, export data, dan import inventaris atau siswa.';
$actions = '<button class="btn btn-grey-soft" onclick="window.print()">' . icon('print', 18) . ' Cetak</button>'
         . '<a class="btn btn-blue-soft" href="' . url("pages/export.php?$reportQuery&format=xls") . '">' . icon('download', 18) . ' Excel</a>'
         . '<a class="btn btn-primary" href="' . url("pages/export.php?$reportQuery&format=csv") . '">' . icon('download', 18) . ' CSV</a>';
require __DIR__ . '/../includes/header.php';
?>
<!-- Filter jenis laporan dan rentang tanggal -->
<form method="get" class="panel panel-white mb-4 no-print">
  <div class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Jenis Laporan</label>
      <select name="jenis" class="form-select">
        <option value="peminjaman" <?= $reportType === 'peminjaman' ? 'selected' : '' ?>>Peminjaman</option>
        <option value="inventaris" <?= $reportType === 'inventaris' ? 'selected' : '' ?>>Inventaris</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="dari" class="form-control" value="<?= e($startDate) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" name="sampai" class="form-control" value="<?= e($endDate) ?>" required>
    </div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><?= icon('search', 18) ?> Tampilkan</button></div>
  </div>
</form>

<!-- Tabel pratinjau laporan -->
<div class="panel panel-mint mb-4 print-area">
  <div class="print-only text-center mb-3">
    <img class="report-print-logo" src="<?= e(url('assets/images/logo-baknus.png')) ?>" alt="Logo SMK Baknus 666">
    <h3><?= APP_NAME ?></h3>
    <div>Laporan <?= ucfirst($reportType) ?> · <?= tgl($startDate) ?> s.d. <?= tgl($endDate) ?></div>
  </div>
  <div class="panel-title no-print">
    Laporan <?= ucfirst($reportType) ?>
    <span class="text-muted fw-normal">· <?= tgl($startDate) ?> s.d. <?= tgl($endDate) ?> (<?= count($reportRows) ?> data)</span>
  </div>
  <div class="table-responsive">
    <table class="tbl">
      <?php if ($reportType === 'peminjaman'): ?>
        <thead><tr><th>ID</th><th>Tgl Pinjam</th><th>Peminjam</th><th>Jabatan</th><th>Barang</th><th>Unit</th><th>Batas</th><th>Kembali</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($reportRows as $row): ?>
          <tr>
            <td>#<?= str_pad($row['id_peminjaman'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= tgl($row['tanggal_pinjam']) ?></td>
            <td><?= e($row['nama_pegawai']) ?><br><small class="text-muted"><?= e($row['nip']) ?></small></td>
            <td><?= e($row['jabatan']) ?></td>
            <td><?= e($row['barang']) ?></td>
            <td><?= $row['total_unit'] ?></td>
            <td><?= tgl($row['batas_kembali']) ?></td>
            <td><?= tgl($row['tanggal_kembali']) ?></td>
            <td><?= $row['status_peminjaman'] === 'Dipinjam' ? '<span class="st st-warn">Dipinjam</span>' : '<span class="st st-ok">Dikembalikan</span>' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      <?php else: ?>
        <thead><tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Ruang</th><th>Kondisi</th><th>Total</th><th>Tersedia</th><th>Tgl Register</th></tr></thead>
        <tbody>
        <?php foreach ($reportRows as $row): ?>
          <tr>
            <td><?= e($row['kode_inventaris']) ?></td>
            <td><?= e($row['nama']) ?></td>
            <td><?= e($row['nama_jenis']) ?></td>
            <td><?= e($row['nama_ruang']) ?></td>
            <td><?= e($row['kondisi']) ?></td>
            <td><?= $row['jumlah'] ?></td>
            <td><?= $row['tersedia'] ?></td>
            <td><?= tgl($row['tanggal_register']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      <?php endif ?>
      <?php if (!$reportRows): ?>
        <tbody><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data pada rentang tanggal ini.</td></tr></tbody>
      <?php endif ?>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
