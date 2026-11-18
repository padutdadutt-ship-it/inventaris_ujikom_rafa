<?php
// Halaman peminjaman barang. Pengajuan publik ditinjau di Data Peminjam.
require __DIR__ . '/../app/init.php';
$user = Auth::guard(['operator']);

// Simpan transaksi saat formulir dikirim.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        $loanId = Peminjaman::buat(
            (int)($_POST['id_pegawai'] ?? 0),
            post('tanggal_pinjam'),
            post('batas_kembali'),
            $_POST['items'] ?? [],
            $user['id']
        );
        flash('success', 'Peminjaman #' . str_pad((string)$loanId, 5, '0', STR_PAD_LEFT) . ' berhasil disimpan.');
    } catch (Throwable $e) {
        flash('danger', errmsg($e));
    }
    redirect('pages/peminjaman.php');
}

// Ambil pilihan formulir dan daftar peminjaman yang masih aktif.
$activeBorrowers = Peminjaman::pegawaiAktif();
$availableItems = Peminjaman::barangTersedia();
$activeLoans = Peminjaman::daftar('Dipinjam', 8);

$title = 'Peminjaman';
$sub = 'Catat peminjaman barang oleh guru, pegawai, atau siswa.';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <!-- Formulir peminjaman -->
  <div class="col-lg-5">
    <form method="post" class="panel panel-white">
      <?= csrf_field() ?>
      <div class="panel-title">Form Peminjaman</div>
      <div class="mb-3">
        <label class="form-label">Peminjam</label>
        <select name="id_pegawai" class="form-select" required>
          <option value="">— pilih peminjam —</option>
          <?php foreach ($activeBorrowers as $borrower): ?>
            <option value="<?= $borrower['id_pegawai'] ?>">
              <?= e($borrower['nama_pegawai']) ?> — <?= e($borrower['jabatan']) ?> (<?= e($borrower['nip']) ?>)
            </option>
          <?php endforeach ?>
        </select>
        <small class="text-muted">Belum terdaftar? <a href="<?= url('pages/master.php?m=pegawai') ?>">Daftarkan peminjam dulu</a> agar tidak double.</small>
      </div>
      <div class="row">
        <div class="col-6 mb-3"><label class="form-label">Tanggal Pinjam</label><input type="date" name="tanggal_pinjam" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-6 mb-3"><label class="form-label">Batas Kembali</label><input type="date" name="batas_kembali" class="form-control" value="<?= date('Y-m-d', strtotime('+7 day')) ?>" required></div>
      </div>
      <label class="form-label">Barang yang dipinjam</label>
      <div id="items"></div>
      <button type="button" id="addItem" class="btn btn-sm btn-blue-soft mb-4"><?= icon('plus', 15) ?> Tambah barang</button>
      <button class="btn btn-primary w-100">Simpan Peminjaman</button>
    </form>
    <template id="tplItem">
      <div class="item-row">
        <select name="items[__i__][id]" class="form-select" required>
          <option value="">— pilih barang —</option>
          <?php foreach ($availableItems as $item): ?>
            <option value="<?= $item['id_inventaris'] ?>" data-max="<?= $item['tersedia'] ?>">
              <?= e($item['nama']) ?> (<?= e($item['kode_inventaris']) ?>) · tersedia <?= $item['tersedia'] ?>
            </option>
          <?php endforeach ?>
        </select>
        <input type="number" name="items[__i__][jumlah]" class="form-control" value="1" min="1" required>
        <button type="button" class="btn btn-red-soft rm-item" title="Hapus baris"><?= icon('trash', 15) ?></button>
      </div>
    </template>
  </div>

  <!-- Tabel peminjaman yang masih aktif -->
  <div class="col-lg-7">
    <div class="panel panel-mint">
      <div class="panel-title">Sedang Dipinjam</div>
      <div class="table-responsive">
        <table class="tbl">
          <thead><tr><th>ID</th><th>Peminjam</th><th>Barang</th><th>Batas</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($activeLoans as $loan): ?>
            <tr>
              <td>#<?= str_pad($loan['id_peminjaman'], 5, '0', STR_PAD_LEFT) ?></td>
              <td><?= e($loan['nama_pegawai']) ?></td>
              <td><?= e($loan['barang']) ?></td>
              <td><?= tgl($loan['batas_kembali']) ?></td>
              <td><?= $loan['terlambat'] ? '<span class="st st-danger">Terlambat</span>' : '<span class="st st-warn">Dipinjam</span>' ?></td>
            </tr>
          <?php endforeach ?>
          <?php if (!$activeLoans): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada peminjaman aktif.</td></tr>
          <?php endif ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
