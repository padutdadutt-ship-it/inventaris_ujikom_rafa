<?php
// Halaman pengembalian barang. Hanya operator yang dapat memproses pengembalian.
require __DIR__ . '/../app/init.php';
Auth::guard(['operator']);

// Proses pengembalian saat tombol pada tabel ditekan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        Peminjaman::kembalikan((int)($_POST['id'] ?? 0));
        flash('success', 'Pengembalian berhasil dicatat, stok barang sudah diperbarui.');
    } catch (Throwable $e) {
        flash('danger', errmsg($e));
    }
    redirect('pages/pengembalian.php');
}

// Ambil daftar barang yang masih dipinjam dan riwayat terakhir.
$activeLoans = Peminjaman::daftar('Dipinjam');
$recentReturns = Peminjaman::daftar('Dikembalikan', 8);

$title = 'Pengembalian';
$sub = 'Proses pengembalian barang yang sedang dipinjam.';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel panel-mint mb-4">
  <!-- Daftar peminjaman yang menunggu pengembalian -->
  <div class="panel-title">Menunggu Dikembalikan <span class="text-muted fw-normal">(<?= count($activeLoans) ?>)</span></div>
  <div class="table-responsive">
    <table class="tbl">
      <thead><tr><th>ID</th><th>Peminjam</th><th>Barang</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($activeLoans as $loan): ?>
        <tr>
          <td>#<?= str_pad($loan['id_peminjaman'], 5, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($loan['nama_pegawai']) ?><br><small class="text-muted"><?= e($loan['jabatan']) ?></small></td>
          <td><?= e($loan['barang']) ?></td>
          <td><?= tgl($loan['tanggal_pinjam']) ?></td>
          <td><?= tgl($loan['batas_kembali']) ?></td>
          <td><?= $loan['terlambat'] ? '<span class="st st-danger">Terlambat</span>' : '<span class="st st-warn">Dipinjam</span>' ?></td>
          <td class="text-end">
            <form method="post" data-confirm="Proses pengembalian peminjaman ini?">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $loan['id_peminjaman'] ?>">
              <button class="btn btn-sm btn-ok-soft"><?= icon('check', 15) ?> Kembalikan</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$activeLoans): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Semua barang sudah dikembalikan.</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel panel-blue">
  <!-- Riwayat pengembalian terbaru -->
  <div class="panel-title">Riwayat Pengembalian Terakhir</div>
  <div class="table-responsive">
    <table class="tbl">
      <thead><tr><th>ID</th><th>Peminjam</th><th>Barang</th><th>Tgl Pinjam</th><th>Tgl Kembali</th></tr></thead>
      <tbody>
      <?php foreach ($recentReturns as $loan): ?>
        <tr>
          <td>#<?= str_pad($loan['id_peminjaman'], 5, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($loan['nama_pegawai']) ?></td>
          <td><?= e($loan['barang']) ?></td>
          <td><?= tgl($loan['tanggal_pinjam']) ?></td>
          <td><span class="st st-ok"><?= tgl($loan['tanggal_kembali']) ?></span></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$recentReturns): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
