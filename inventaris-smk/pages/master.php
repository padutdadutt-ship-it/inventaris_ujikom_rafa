<?php
// Satu halaman untuk mengelola kategori, ruang, inventaris, peminjam, dan petugas.
require __DIR__ . '/../app/init.php';
require __DIR__ . '/../app/master_config.php';

function store_inventory_photo(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto gagal diunggah. Pilih ulang gambar dan coba lagi.');
    }
    if (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 5 MB.');
    }
    if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('File foto yang diunggah tidak valid.');
    }

    $image = getimagesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!$image || !isset($extensions[$mime]) || ($image['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Format foto harus berupa JPG, PNG, atau WebP yang valid.');
    }

    $directory = __DIR__ . '/../uploads/inventaris';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Foto tidak dapat disimpan ke folder inventaris.');
    }

    return 'uploads/inventaris/' . $filename;
}

function remove_inventory_photo(?string $photoPath): void
{
    if (!$photoPath || !str_starts_with($photoPath, 'uploads/inventaris/')) {
        return;
    }

    $filename = substr($photoPath, strlen('uploads/inventaris/'));
    if ($filename !== basename($filename)) {
        return;
    }

    $filePath = __DIR__ . '/../uploads/inventaris/' . $filename;
    if (is_file($filePath) && !unlink($filePath)) {
        throw new RuntimeException('Foto lama tidak dapat dihapus dari penyimpanan.');
    }
}

$masterConfigs = master_config();
$moduleKey = $_GET['m'] ?? '';

if (!isset($masterConfigs[$moduleKey])) {
    redirect('pages/dashboard.php');
}

$config = $masterConfigs[$moduleKey];
$user = Auth::guard($config['roles']);
$isOperatorApprovals = $moduleKey === 'pegawai' && $user['level'] === 'operator';
$crud = new Crud($config);
$canDelete = in_array($user['level'], $config['del'] ?? $config['roles'], true);

// Proses tambah, ubah, dan hapus data.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        $action = post('action');
        if ($moduleKey === 'pegawai' && $action === 'approve_request') {
            $loanId = Peminjaman::setujuiPengajuan(
                (int)($_POST['id_pengajuan'] ?? 0),
                $user['id'],
                (int)($_POST['id_inventaris'] ?? 0)
            );
            flash('success', 'Pengajuan disetujui dan tercatat sebagai peminjaman #' . str_pad((string)$loanId, 5, '0', STR_PAD_LEFT) . '.');
        } elseif ($moduleKey === 'pegawai' && $action === 'approve_room_request') {
            Peminjaman::setujuiPengajuanRuang((int)($_POST['id_pengajuan_ruang'] ?? 0), $user['id']);
            flash('success', 'Pengajuan peminjaman ruangan berhasil disetujui.');
        } elseif ($moduleKey === 'pegawai' && $action === 'reject_room_request') {
            Peminjaman::tolakPengajuanRuang((int)($_POST['id_pengajuan_ruang'] ?? 0), $user['id']);
            flash('success', 'Pengajuan peminjaman ruangan berhasil ditolak.');
        } elseif ($moduleKey === 'pegawai' && $action === 'reject_request') {
            Peminjaman::tolakPengajuan((int)($_POST['id_pengajuan'] ?? 0), $user['id']);
            flash('success', 'Pengajuan berhasil ditolak.');
        } elseif ($isOperatorApprovals) {
            throw new RuntimeException('Operator hanya dapat menyetujui atau menolak pengajuan pada halaman ini.');
        } elseif (($_POST['do'] ?? '') === 'delete') {
            if (!$canDelete) {
                throw new RuntimeException('Anda tidak berhak menghapus data ini.');
            }

            $recordId = (int)($_POST['id'] ?? 0);
            $oldPhotoPath = $moduleKey === 'inventaris'
                ? Database::val('SELECT foto FROM inventaris WHERE id_inventaris=?', [$recordId])
                : null;
            if ($moduleKey === 'petugas' && $recordId === $user['id']) {
                throw new RuntimeException('Akun yang sedang dipakai tidak dapat dihapus.');
            }

            $crud->delete($recordId);
            if ($moduleKey === 'inventaris') {
                remove_inventory_photo($oldPhotoPath);
            }
            flash('success', 'Data berhasil dihapus.');
        } else {
            $recordId = (int)($_POST['_id'] ?? 0);
            if ($moduleKey === 'inventaris') {
                $newPhotoPath = store_inventory_photo($_FILES['foto'] ?? []);
                $oldPhotoPath = $recordId
                    ? Database::val('SELECT foto FROM inventaris WHERE id_inventaris=?', [$recordId])
                    : null;
                $database = Database::conn();
                $database->beginTransaction();

                try {
                    $crud->save($_POST, $recordId);
                    $savedId = $recordId ?: (int)$database->lastInsertId();
                    if ($newPhotoPath !== null) {
                        Database::q('UPDATE inventaris SET foto=? WHERE id_inventaris=?', [$newPhotoPath, $savedId]);
                    } elseif (!empty($_POST['hapus_foto'])) {
                        Database::q('UPDATE inventaris SET foto=NULL WHERE id_inventaris=?', [$savedId]);
                    }
                    $database->commit();
                } catch (Throwable $error) {
                    if ($database->inTransaction()) {
                        $database->rollBack();
                    }
                    remove_inventory_photo($newPhotoPath);
                    throw $error;
                }

                if ($newPhotoPath !== null || !empty($_POST['hapus_foto'])) {
                    remove_inventory_photo($oldPhotoPath);
                }
            } else {
                $crud->save($_POST, $recordId);
            }
            flash('success', $recordId ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.');
        }
    } catch (Throwable $e) {
        flash('danger', errmsg($e));
    }

    redirect("pages/master.php?m=$moduleKey");
}

// Ubah nilai tabel menjadi teks atau label status.
function cell($value, $format): string
{
    if ($format === 'bool') {
        return $value
            ? '<span class="st st-ok">Aktif</span>'
            : '<span class="st st-danger">Nonaktif</span>';
    }

    if ($format === 'kondisi') {
        $statusClasses = [
            'Baik' => 'ok',
            'Rusak Ringan' => 'warn',
            'Rusak Berat' => 'danger',
        ];
        $statusClass = $statusClasses[$value] ?? 'warn';

        return '<span class="st st-' . $statusClass . '">' . e($value) . '</span>';
    }

    if ($format === 'image') {
        return $value
            ? '<img class="inventory-table-photo" src="' . e(url($value)) . '" alt="Foto inventaris" loading="lazy">'
            : '<span class="text-muted">Belum ada foto</span>';
    }

    return e($value);
}

$rows = $isOperatorApprovals ? [] : $crud->rows();
$pendingRequests = $moduleKey === 'pegawai' ? Peminjaman::pengajuanMenunggu() : [];
$roomRequestsTableExists = $moduleKey === 'pegawai'
    && (bool)Database::val(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?",
        ['pengajuan_peminjaman_ruang']
    );
$pendingRoomRequests = $roomRequestsTableExists ? Peminjaman::pengajuanRuangMenunggu() : [];
$catalogItems = $moduleKey === 'inventaris'
    ? Database::all(
        "SELECT k.id_katalog_barang, k.kategori, k.nama_barang, j.id_jenis,
                i.kode_inventaris, i.total_jumlah
           FROM katalog_barang_pinjam k
           LEFT JOIN jenis j
             ON j.nama_jenis = CASE k.kategori
                  WHEN 'Perabotan' THEN 'Perabot'
                  WHEN 'Elektronik' THEN 'Elektronik'
                  ELSE 'Alat Praktik'
                END
           LEFT JOIN (
                SELECT nama, GROUP_CONCAT(kode_inventaris ORDER BY kode_inventaris SEPARATOR ', ') AS kode_inventaris,
                       SUM(jumlah) AS total_jumlah
                  FROM inventaris
                 GROUP BY nama
           ) i ON i.nama=k.nama_barang
          ORDER BY FIELD(k.kategori, 'Perabotan', 'Elektronik', 'Alat Praktik/Lab'), k.nama_barang"
    )
    : [];
$title = $isOperatorApprovals ? 'Persetujuan Pengajuan' : $config['title'];
$sub = $isOperatorApprovals
    ? 'Tinjau dan setujui atau tolak pengajuan peminjaman barang dan ruangan.'
    : $config['sub'];
$borrowerGrade = static function (string $position): string {
    if (preg_match('/^(?:Siswa\s*-\s*)?(XII|XI|X)(?:\s|$)/iu', trim($position), $match)) {
        return strtolower($match[1]);
    }

    return 'other';
};
$actions = $isOperatorApprovals
    ? ''
    : '<button class="btn btn-primary" data-add data-bs-toggle="modal" data-bs-target="#modalMaster">' . icon('plus', 18) . ' Tambah</button>';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($moduleKey === 'pegawai'): ?>
<div class="panel panel-white mb-4">
  <div class="panel-title">Pengajuan Peminjaman Menunggu Persetujuan <span class="text-muted fw-normal">(<?= count($pendingRequests) ?>)</span></div>
  <div class="table-responsive">
    <table class="tbl">
      <thead><tr><th>Siswa</th><th>Barang Diajukan</th><th>Barang Inventaris</th><th>Jumlah</th><th>Tanggal</th><th>Keperluan</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($pendingRequests as $request): ?>
        <?php $matchingInventory = Peminjaman::barangTersediaUntukKatalog((int)$request['id_katalog_barang']); ?>
        <tr>
          <td><b><?= e($request['nama_peminjam']) ?></b><br><small><?= e($request['nis']) ?> · <?= e($request['kelas']) ?></small></td>
          <td><?= e($request['nama_barang']) ?><br><small><?= e($request['kategori_barang']) ?></small></td>
          <td>
            <?php if ($matchingInventory): ?>
              <select class="form-select form-select-sm" name="id_inventaris" form="approve-request-<?= (int)$request['id_pengajuan'] ?>" required>
                <option value="">— cocokkan stok —</option>
                <?php foreach ($matchingInventory as $inventory): ?>
                  <option value="<?= (int)$inventory['id_inventaris'] ?>">
                    <?= e($inventory['nama']) ?> · <?= e($inventory['nama_jenis']) ?> · <?= e($inventory['kode_inventaris']) ?> · tersedia <?= (int)$inventory['tersedia'] ?>
                  </option>
                <?php endforeach ?>
              </select>
            <?php else: ?>
              <small class="text-muted">Belum ada stok dengan nama yang sama di daftar terbaru</small>
            <?php endif ?>
          </td>
          <td><?= (int)$request['jumlah'] ?> unit</td>
          <td><?= tgl($request['tanggal_pinjam']) ?><br><small>s/d <?= tgl($request['batas_kembali']) ?></small></td>
          <td><?= e($request['keperluan'] ?: '-') ?></td>
          <td>
            <div class="d-flex gap-1">
              <form method="post" id="approve-request-<?= (int)$request['id_pengajuan'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve_request">
                <input type="hidden" name="id_pengajuan" value="<?= (int)$request['id_pengajuan'] ?>">
                <button class="btn btn-sm btn-blue-soft" type="submit" <?= $matchingInventory ? '' : 'disabled' ?>>Setujui</button>
              </form>
              <form method="post" data-confirm="Tolak pengajuan ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject_request">
                <input type="hidden" name="id_pengajuan" value="<?= (int)$request['id_pengajuan'] ?>">
                <button class="btn btn-sm btn-red-soft" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$pendingRequests): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan peminjaman yang menunggu.</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
  <small class="text-muted">Pilihan dibatasi pada inventaris dengan nama persis sesuai daftar barang terbaru dan stok tersedia. Lengkapi inventaris bila barang yang diajukan belum tercatat. Profil siswa dibuat saat pengajuan disetujui jika NIS belum terdaftar.</small>
</div>
<?php endif ?>

<?php if ($moduleKey === 'pegawai'): ?>
<div class="panel panel-white mb-4">
  <div class="panel-title">Pengajuan Peminjaman Ruangan Menunggu Persetujuan <span class="text-muted fw-normal">(<?= count($pendingRoomRequests) ?>)</span></div>
  <div class="table-responsive">
    <table class="tbl">
      <thead><tr><th>Peminjam</th><th>Ruangan</th><th>Jadwal</th><th>Keperluan</th><th>Diajukan</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($pendingRoomRequests as $request): ?>
        <tr>
          <td><b><?= e($request['nama_peminjam']) ?></b><br><small><?= e($request['nis']) ?> · <?= e($request['kelas']) ?></small></td>
          <td><?= e($request['nama_ruang']) ?><br><small><?= e($request['kode_ruang']) ?></small></td>
          <td><?= e(date('d/m/Y H:i', strtotime($request['waktu_mulai']))) ?><br><small>s/d <?= e(date('d/m/Y H:i', strtotime($request['waktu_selesai']))) ?></small></td>
          <td><?= e($request['keperluan']) ?></td>
          <td><?= e(date('d/m/Y H:i', strtotime($request['diajukan_pada']))) ?></td>
          <td>
            <div class="d-flex gap-1">
              <form method="post" data-confirm="Setujui penggunaan ruangan pada jadwal ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve_room_request">
                <input type="hidden" name="id_pengajuan_ruang" value="<?= (int)$request['id_pengajuan_ruang'] ?>">
                <button class="btn btn-sm btn-blue-soft" type="submit">Setujui</button>
              </form>
              <form method="post" data-confirm="Tolak pengajuan ruangan ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject_room_request">
                <input type="hidden" name="id_pengajuan_ruang" value="<?= (int)$request['id_pengajuan_ruang'] ?>">
                <button class="btn btn-sm btn-red-soft" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$pendingRoomRequests): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan peminjaman ruangan yang menunggu.</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
  <small class="text-muted">Jadwal yang sudah disetujui tidak dapat ditimpa oleh pengajuan lain pada waktu yang bertabrakan.</small>
</div>
<?php endif ?>

<!-- Tabel data dan tombol aksi -->
<?php if (!$isOperatorApprovals): ?>
<div class="panel panel-mint">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <div class="panel-title mb-0"><?= e($config['title']) ?> <span class="text-muted fw-normal">(<?= count($rows) ?>)</span></div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if ($moduleKey === 'pegawai'): ?>
        <select class="form-select" data-row-filter="#tblMaster" aria-label="Filter angkatan" style="width: auto">
          <option value="all">Semua Angkatan</option>
          <option value="x">Kelas 10 (X)</option>
          <option value="xi">Kelas 11 (XI)</option>
          <option value="xii">Kelas 12 (XII)</option>
          <option value="other">Guru / Pegawai</option>
        </select>
      <?php endif ?>
      <div class="search-box"><?= icon('search', 16) ?><input class="form-control" placeholder="Cari data..." data-filter="#tblMaster"></div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="tbl" id="tblMaster">
      <thead><tr><th>#</th>
        <?php foreach ($config['cols'] as $columnKey => $column): ?><th><?= e(is_array($column) ? $column[0] : $column) ?></th><?php endforeach ?>
        <th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $index => $row): unset($row['password']); ?>
        <tr<?= $moduleKey === 'pegawai' ? ' data-grade="' . e($borrowerGrade((string)$row['jabatan'])) . '"' : '' ?>>
          <td><?= $index + 1 ?></td>
          <?php foreach ($config['cols'] as $columnKey => $column): ?>
            <td><?= cell($row[$columnKey], is_array($column) ? $column[1] : null) ?></td>
          <?php endforeach ?>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-blue-soft" data-edit="<?= e(json_encode($row, JSON_UNESCAPED_UNICODE)) ?>" data-bs-toggle="modal" data-bs-target="#modalMaster" title="Ubah"><?= icon('edit', 15) ?></button>
            <?php if ($canDelete): ?>
            <form method="post" class="d-inline" data-confirm="Hapus data ini?">
              <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $row[$config['pk']] ?>">
              <button class="btn btn-sm btn-red-soft" title="Hapus"><?= icon('trash', 15) ?></button>
            </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$rows): ?><tr><td colspan="<?= count($config['cols']) + 2 ?>" class="text-center text-muted py-4">Belum ada data.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal untuk tambah atau ubah data -->
<div class="modal fade" id="modalMaster" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content" id="formMaster" data-pk="<?= e($config['pk']) ?>" <?= $moduleKey === 'inventaris' ? 'enctype="multipart/form-data"' : '' ?>>
      <?= csrf_field() ?><input type="hidden" name="_id" value="">
      <div class="modal-header"><h5 class="modal-title" id="modalTitle"><?= e($config['title']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if ($moduleKey === 'inventaris'): ?>
          <div class="mb-3">
            <label class="form-label" for="f_foto">Foto Barang (JPG, PNG, WebP; maks. 5 MB)</label>
            <input id="f_foto" name="foto" class="form-control" type="file" accept="image/jpeg,image/png,image/webp">
            <img id="inventoryPhotoPreview" class="inventory-form-photo" alt="Pratinjau foto barang" hidden>
            <label class="form-check mt-2" id="removePhotoOption" hidden>
              <input class="form-check-input" type="checkbox" name="hapus_foto" value="1">
              <span class="form-check-label">Hapus foto saat ini</span>
            </label>
          </div>
        <?php endif ?>
        <?php foreach ($config['fields'] as $field): $name = $field['n']; $required = !empty($field['r']) ? 'required' : ''; $default = $field['d'] ?? ''; ?>
          <div class="mb-3">
            <?php if ($field['t'] === 'bool'): ?>
              <input type="hidden" name="<?= $name ?>" value="0">
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="<?= $name ?>" value="1" id="f_<?= $name ?>" <?= $default ? 'checked' : '' ?>><label class="form-check-label" for="f_<?= $name ?>"><?= e($field['l']) ?></label></div>
            <?php else: ?>
              <label class="form-label"><?= e($field['l']) ?></label>
              <?php if ($field['t'] === 'select'): ?>
                <select name="<?= $name ?>" class="form-select" <?= $required ?>><option value="">— pilih —</option>
                  <?php foreach ($crud->options($field) as $option): ?><option value="<?= e($option['v']) ?>"><?= e($option['t']) ?></option><?php endforeach ?>
                </select>
              <?php elseif ($field['t'] === 'textarea'): ?>
                <textarea name="<?= $name ?>" class="form-control" rows="2"></textarea>
              <?php else: ?>
                <input name="<?= $name ?>" class="form-control" type="<?= $field['t'] === 'password' ? 'password' : ($field['t'] === 'number' ? 'number' : ($field['t'] === 'date' ? 'date' : 'text')) ?>"
                       value="<?= e($default) ?>" <?= $required ?> <?= $field['t'] === 'number' ? 'min="' . ($field['min'] ?? 0) . '"' : '' ?> <?= $field['t'] === 'password' ? 'autocomplete="new-password"' : '' ?>>
              <?php endif ?>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-grey-soft" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div>
</div>
<?php endif ?>
<?php if ($moduleKey === 'inventaris'): ?>
<script>
  const inventoryPhoto = document.getElementById('f_foto');
  const inventoryPhotoPreview = document.getElementById('inventoryPhotoPreview');
  const removePhotoOption = document.getElementById('removePhotoOption');
  const showInventoryPhoto = source => {
    inventoryPhotoPreview.src = source;
    inventoryPhotoPreview.hidden = false;
  };

  inventoryPhoto.addEventListener('change', () => {
    const file = inventoryPhoto.files[0];
    if (file) {
      showInventoryPhoto(URL.createObjectURL(file));
      removePhotoOption.hidden = true;
    }
  });

  document.querySelectorAll('[data-add]').forEach(button => {
    button.addEventListener('click', () => {
      inventoryPhotoPreview.hidden = true;
      inventoryPhotoPreview.removeAttribute('src');
      removePhotoOption.hidden = true;
    });
  });

  document.querySelectorAll('[data-edit]').forEach(button => {
    button.addEventListener('click', () => {
      const rowData = JSON.parse(button.dataset.edit);
      if (rowData.foto) {
        showInventoryPhoto('<?= e(url('')) ?>' + rowData.foto);
        removePhotoOption.hidden = false;
      } else {
        inventoryPhotoPreview.hidden = true;
        inventoryPhotoPreview.removeAttribute('src');
        removePhotoOption.hidden = true;
      }
    });
  });

  document.querySelectorAll('[data-catalog-prefill]').forEach(button => {
    button.addEventListener('click', () => {
      const form = document.getElementById('formMaster');
      form.reset();
      form.elements._id.value = '';
      form.elements.namedItem('nama').value = button.dataset.catalogName;
      form.elements.namedItem('id_jenis').value = button.dataset.catalogType;
      form.elements.namedItem('jumlah').value = '';
      inventoryPhotoPreview.hidden = true;
      inventoryPhotoPreview.removeAttribute('src');
      removePhotoOption.hidden = true;
      document.getElementById('modalTitle').textContent = 'Tambah Inventaris Barang';
      form.elements.namedItem('kode_inventaris').focus();
    });
  });
</script>
<?php endif ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
