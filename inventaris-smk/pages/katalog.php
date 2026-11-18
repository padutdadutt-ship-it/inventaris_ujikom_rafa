<?php
require __DIR__ . '/../app/init.php';

// Ambil kata kunci pencarian dari URL.
$searchQuery = trim((string)($_GET['q'] ?? ''));
$openItemRequestId = (int)($_GET['pengajuan_item'] ?? 0);
$openRoomRequestId = (int)($_GET['pengajuan_ruang'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    try {
        if ($action === 'room_request') {
            $requestId = Peminjaman::ajukanRuang(
                post('nama_peminjam'),
                post('nis'),
                post('kelas'),
                (int)($_POST['id_ruang'] ?? 0),
                post('waktu_mulai'),
                post('waktu_selesai'),
                post('keperluan')
            );
            flash('success', 'Pengajuan ruangan #' . str_pad((string)$requestId, 5, '0', STR_PAD_LEFT) . ' berhasil dikirim dan menunggu persetujuan operator.');
            redirect('pages/katalog.php');
        }

        $requestId = Peminjaman::ajukan(
            post('nama_peminjam'),
            post('nis'),
            post('kelas'),
            (int)($_POST['id_katalog_barang'] ?? 0),
            (int)($_POST['jumlah'] ?? 0),
            post('tanggal_pinjam'),
            post('batas_kembali'),
            post('keperluan')
        );
        flash('success', 'Pengajuan #' . str_pad((string)$requestId, 5, '0', STR_PAD_LEFT) . ' berhasil dikirim dan menunggu persetujuan operator.');
        redirect('pages/katalog.php');
    } catch (Throwable $error) {
        flash('danger', errmsg($error));
    }

    if ($action === 'room_request') {
        $roomId = (int)($_POST['id_ruang'] ?? 0);
        redirect('pages/katalog.php?pengajuan_ruang=' . $roomId . '#room-' . $roomId);
    }

    $itemId = (int)($_POST['id_katalog_barang'] ?? 0);
    redirect('pages/katalog.php?pengajuan_item=' . $itemId . '#item-' . $itemId);
}

$borrowableItems = Peminjaman::barangKatalog($searchQuery);
$otherInventoryItems = Peminjaman::barangInventarisLainnya($searchQuery);

// =========================================================================
// 1. FILTER BARANG TIDAK LOGIS (Sesuai Permintaan Anda)
// =========================================================================
function isBarangLogis($nama) {
    // Daftar barang yang wajib dihapus
    $barangTidakLogis = [
        'handphone', 'cctv', 'sapu', 'tong sampah', 'despenser', 'dispenser', 
        'background', 'headphone', 'hub switch', 'lap pel', 
        'drone', 'kamera', 'gitar', 'microphone', 'mic', 'speaker', 'mixer'
    ];
    
    $namaBarang = strtolower($nama);
    foreach ($barangTidakLogis as $kataKunci) {
        if (strpos($namaBarang, $kataKunci) !== false) {
            return false; // Sembunyikan
        }
    }
    
    // Cek kata "AC" dan "HP" secara spesifik (agar tidak menghapus kata seperti "Macam" atau "HP" di tengah kata)
    if (preg_match('/(^|\s)ac(\s|$)/i', $namaBarang)) return false;
    if (preg_match('/(^|\s)hp(\s|$)/i', $namaBarang)) return false;
    
    return true; // Tampilkan
}

// Terapkan filter ke data katalog
$borrowableItems = array_filter($borrowableItems, function($item) { 
    return isBarangLogis($item['nama_barang']); 
});

// Terapkan filter ke data inventaris lainnya
$otherInventoryItems = array_filter($otherInventoryItems, function($item) { 
    return isBarangLogis($item['nama']); 
});

// =========================================================================
// 2. KELOMPOKKAN BARANG BERDASARKAN KATEGORI DATABASE (BUKAN TEBAKAN)
// =========================================================================
function kelompokkanBarangDatabase($items, $fieldKategori) {
    $groups = [];
    foreach ($items as $item) {
        $kategori = trim($item[$fieldKategori] ?? 'Lainnya');
        if (empty($kategori)) {
            $kategori = 'Lainnya';
        }
        $groups[$kategori][] = $item;
    }
    ksort($groups); // Urutkan kategori sesuai abjad (A-Z)
    return $groups;
}

// Kelompokkan berdasarkan field 'kategori' (untuk Barang yang Bisa Diajukan)
$groupedBorrowableItems = kelompokkanBarangDatabase($borrowableItems, 'kategori');

// Kelompokkan berdasarkan field 'nama_jenis' (untuk Inventaris Lainnya)
$groupedOtherItems = kelompokkanBarangDatabase($otherInventoryItems, 'nama_jenis');
// =========================================================================


// =========================================================================
// 3. DATA SISWA (KELAS X & XI ASLI, XII DUMMY)
// =========================================================================
$dataSiswa = [
    // --- X PPLG 1 ---
    ['nama' => 'ABDILAH MALIK AL - JABBAR', 'nis' => '2627020070001', 'kelas' => 'X PPLG 1'],
    ['nama' => 'ADITYA MUSYAFFA', 'nis' => '2627020070003', 'kelas' => 'X PPLG 1'],
    ['nama' => 'ALLIF FAKHRIZAL', 'nis' => '2627020070004', 'kelas' => 'X PPLG 1'],
    ['nama' => 'AMSHOR MUSTOFA ILHAM', 'nis' => '2627020070005', 'kelas' => 'X PPLG 1'],
    ['nama' => 'ANITA SITI NURJANAH', 'nis' => '2627020070006', 'kelas' => 'X PPLG 1'],
    ['nama' => 'DAVA HERDIANSYAH', 'nis' => '2627020070011', 'kelas' => 'X PPLG 1'],
    ['nama' => 'DICKY MAULANA', 'nis' => '2627020070013', 'kelas' => 'X PPLG 1'],
    ['nama' => 'EVIAN AFRI ANSYAH', 'nis' => '2627020070015', 'kelas' => 'X PPLG 1'],
    ['nama' => 'FADYL MAULANA YUSUF', 'nis' => '2627020070016', 'kelas' => 'X PPLG 1'],
    ['nama' => 'FAISAL RAMDHANI', 'nis' => '2627020070018', 'kelas' => 'X PPLG 1'],
    ['nama' => 'HAFIZHAN HANIF ABDULLAH', 'nis' => '2627020070020', 'kelas' => 'X PPLG 1'],
    ['nama' => 'KEANDRA FADHIL AZFAR', 'nis' => '2627020070023', 'kelas' => 'X PPLG 1'],
    ['nama' => 'KHEIDIRA AHMAD FAJAR', 'nis' => '2627020070025', 'kelas' => 'X PPLG 1'],
    ['nama' => 'LEANDRA MAHAWIRA ARDHANI', 'nis' => '2627020070026', 'kelas' => 'X PPLG 1'],
    ['nama' => 'M. IRVAN ABDILLAH', 'nis' => '2627020070027', 'kelas' => 'X PPLG 1'],
    ['nama' => 'MUHAMAD RAYTAMA DERALD', 'nis' => '2627020070029', 'kelas' => 'X PPLG 1'],
    ['nama' => 'MUHAMAD RYFQY', 'nis' => '2627020070030', 'kelas' => 'X PPLG 1'],
    ['nama' => 'MUHAMMAD DENDRA FADHILAH', 'nis' => '2627020070031', 'kelas' => 'X PPLG 1'],
    ['nama' => 'MUHAMMAD JULIYANSYAH', 'nis' => '2627020070034', 'kelas' => 'X PPLG 1'],
    ['nama' => 'MUHAMMAD RAFFY ALBANI', 'nis' => '2627020070035', 'kelas' => 'X PPLG 1'],
    ['nama' => 'PASYA NURUL OCTAVIANI', 'nis' => '2627020070036', 'kelas' => 'X PPLG 1'],
    ['nama' => 'RAFKA RADHITYA ANUGRAH', 'nis' => '2627020070039', 'kelas' => 'X PPLG 1'],
    ['nama' => 'RAKA PUTRA ADNESA', 'nis' => '2627020070041', 'kelas' => 'X PPLG 1'],
    ['nama' => 'REFKY ARDIANSYAH', 'nis' => '2627020070042', 'kelas' => 'X PPLG 1'],
    ['nama' => 'REGINA NIRWASITA PUTRI DAIMAN', 'nis' => '2627020070043', 'kelas' => 'X PPLG 1'],
    ['nama' => 'SAYYIDAH KAYYIS FADHILAH', 'nis' => '2627020070052', 'kelas' => 'X PPLG 1'],
    ['nama' => 'WIDYAN DAFFA HERMAWAN', 'nis' => '2627020070054', 'kelas' => 'X PPLG 1'],

    // --- X PPLG 2 ---
    ['nama' => 'ABIYYU FADHLURRAHMAN', 'nis' => '2627020070002', 'kelas' => 'X PPLG 2'],
    ['nama' => 'ARYA NURIZKY WIRAYUDA', 'nis' => '2627020070007', 'kelas' => 'X PPLG 2'],
    ['nama' => 'AULIA PUTRI PRATIWI', 'nis' => '2627020070008', 'kelas' => 'X PPLG 2'],
    ['nama' => 'AZZAHRA VARIZTHA ZULKARNAEN', 'nis' => '2627020070009', 'kelas' => 'X PPLG 2'],
    ['nama' => 'CEP PAHMI MUHAMMAD ARYA', 'nis' => '2627020070010', 'kelas' => 'X PPLG 2'],
    ['nama' => 'DAVID VILA SEPTIAWAN', 'nis' => '2627020070012', 'kelas' => 'X PPLG 2'],
    ['nama' => 'DWI CAHAYA KUSUMAH', 'nis' => '2627020070014', 'kelas' => 'X PPLG 2'],
    ['nama' => 'FAHMI NUR AHZA', 'nis' => '2627020070017', 'kelas' => 'X PPLG 2'],
    ['nama' => 'HAFIZH FADHILLAH', 'nis' => '2627020070019', 'kelas' => 'X PPLG 2'],
    ['nama' => 'HUSNA KHOIRUNNISA', 'nis' => '2627020070021', 'kelas' => 'X PPLG 2'],
    ['nama' => 'JALU RAMDHAN AS-SYAF', 'nis' => '2627020070022', 'kelas' => 'X PPLG 2'],
    ['nama' => 'KEYRAN BILAL ASHARIANSYAH', 'nis' => '2627020070024', 'kelas' => 'X PPLG 2'],
    ['nama' => 'MUHAMAD PADHIL NURPAJRI', 'nis' => '2627020070028', 'kelas' => 'X PPLG 2'],
    ['nama' => 'MUHAMMAD FADLAN AZZAM', 'nis' => '2627020070032', 'kelas' => 'X PPLG 2'],
    ['nama' => 'MUHAMMAD FARHAN SEPTIAN ANDARI', 'nis' => '2627020070033', 'kelas' => 'X PPLG 2'],
    ['nama' => 'PUTRA ADITYA', 'nis' => '2627020070037', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RAFFA SYAIBIL AXCEL', 'nis' => '2627020070038', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RAIKHAN RIZKY PUTRA HERDIANA', 'nis' => '2627020070040', 'kelas' => 'X PPLG 2'],
    ['nama' => 'REZKY MAULANA', 'nis' => '2627020070044', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RIKY NURBAGJA', 'nis' => '2627020070045', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RIRIN ANJANI', 'nis' => '2627020070046', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RIZKI PRASETYA HERMANSYAH', 'nis' => '2627020070047', 'kelas' => 'X PPLG 2'],
    ['nama' => 'RIZKY ALBYANNUR RAMADHAN', 'nis' => '2627020070048', 'kelas' => 'X PPLG 2'],
    ['nama' => 'SALSABILA NURAZIZAH', 'nis' => '2627020070049', 'kelas' => 'X PPLG 2'],
    ['nama' => 'SARAH AMELIA', 'nis' => '2627020070050', 'kelas' => 'X PPLG 2'],
    ['nama' => 'SATRIYA RAMADHANA PERMANA PUTRA', 'nis' => '2627020070051', 'kelas' => 'X PPLG 2'],
    ['nama' => 'TRIANA ANGGRA NUR BAGJA', 'nis' => '2627020070053', 'kelas' => 'X PPLG 2'],

    // --- XI PPLG 1 (RPL 1) ---
    ['nama' => "ABBY MUHAMMAD RAFI'U", 'nis' => '2526019070001', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'AKBAR FACHREZA WILYAS', 'nis' => '2526019070002', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'ALVINO NOUFAL SANJAYA', 'nis' => '2526019070006', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'ANDIENI SITI KHUMAIROH', 'nis' => '2526019070007', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'ARIFAL GHIFARI BUDIMAN', 'nis' => '2526019070009', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'BINTANG MARTYANA DUTA PUTERA', 'nis' => '2526019070010', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'CHERILLA AMNATI ISTIQOMAH', 'nis' => '2526019070012', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'CINDY RAHAYU', 'nis' => '2526019070013', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'DUDI SUPARJI', 'nis' => '2526019070047', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'FAHMI ADHARI AKBAR', 'nis' => '2526019070015', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'FAHRUL ROZI ARDIANSYAH', 'nis' => '2526019070016', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'FAUZAN RASYID SUMARNA', 'nis' => '2526019070018', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'M. RAFQA HIDAYAT', 'nis' => '2526019070022', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'MANIQ DWI PRADITA', 'nis' => '2526019070025', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'MIRZA LUTFIL HADI SUSILA', 'nis' => '2526019070026', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'MOCHAMAD BIMA PUTRA RAMADHAN', 'nis' => '2526019070027', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'MUHAMMAD AL FARIZI A H', 'nis' => '2526019070029', 'kelas' => 'XI PPLG 1'],
    ['nama' => "MUHAMMAD FAUZAN MA'RUF", 'nis' => '2526019070030', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'MUHAMMAD IHSAN SUPRIADI', 'nis' => '2526019070031', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'NIDA FAUZIYAH INDIRAGESTY', 'nis' => '2526019070034', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'PRANATA JAYA HARTATO', 'nis' => '2526019070035', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'RISQI MUHAMAD PADHILAH', 'nis' => '2526019070040', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'TEGAR PERMANA', 'nis' => '2526019070043', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'ZIYAD AKBAR PUTRA', 'nis' => '2526019070046', 'kelas' => 'XI PPLG 1'],
    ['nama' => 'ARDI FIRMANSYAH', 'nis' => '2526019070049', 'kelas' => 'XI PPLG 1'],

    // --- XI PPLG 2 (RPL 2) ---
    ['nama' => 'AL FIKRI NUR TAWAB', 'nis' => '2526019070003', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'ALDI NUROHMAN', 'nis' => '2526019070004', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'ALFIN AL GHAZALI', 'nis' => '2526019070005', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'ANDIKA ATHA FADHLULLAH', 'nis' => '2526019070008', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'BUSTAN NUR WILDAN', 'nis' => '2526019070011', 'kelas' => 'XI PPLG 2'],
    ['nama' => "DARIN MIS'AN", 'nis' => '2526019070014', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'FAUZAN MAULANA', 'nis' => '2526019070017', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'JIHAN NUR AZIZAH', 'nis' => '2526019070019', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'KHOERUL ARKA MAULANA', 'nis' => '2526019070020', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'LUTFAN BAEHAQI', 'nis' => '2526019070021', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'M. RIFQY HIDAYAT', 'nis' => '2526019070023', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'MANGGALA WIRAFITRIAWAN', 'nis' => '2526019070024', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'MUHAMAD ADITYA MUHTAR', 'nis' => '2526019070028', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'MUHAMMAD NUFA AL- QALAMI', 'nis' => '2526019070032', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'NABILA HAIFA HADIAN', 'nis' => '2526019070033', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'RASYA PUTRA ANDIKA MULYANA', 'nis' => '2526019070036', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'RASYA RAHADIAN NIJAR', 'nis' => '2526019070037', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'RIFKY ELDIYAN PUTRA', 'nis' => '2526019070039', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'RIZKY MAULANA NUR HIDAYAT', 'nis' => '2526019070041', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'SABIAN AL FIQH', 'nis' => '2526019070042', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'WIBI YUDA PRATAMA', 'nis' => '2526019070044', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'WILDAN GALIH', 'nis' => '2526019070045', 'kelas' => 'XI PPLG 2'],
    ['nama' => 'MUHAMAD ADLI MAULANA', 'nis' => '2526019070048', 'kelas' => 'XI PPLG 2'],

    // --- XII (DUMMY SEMENTARA) ---
    ['nama' => 'Contoh Siswa XII PPLG 1', 'nis' => '23230101', 'kelas' => 'XII PPLG 1'],
    ['nama' => 'Contoh Siswa XII PPLG 2', 'nis' => '23230102', 'kelas' => 'XII PPLG 2'],
    ['nama' => 'Contoh Siswa XII AKT', 'nis' => '23230201', 'kelas' => 'XII AKT'],
    ['nama' => 'Contoh Siswa XII DKV', 'nis' => '23230301', 'kelas' => 'XII DKV'],
    ['nama' => 'Contoh Siswa XII ANM', 'nis' => '23230401', 'kelas' => 'XII ANM'],
    ['nama' => 'Contoh Siswa XII PEMASARAN', 'nis' => '23230501', 'kelas' => 'XII PEMASARAN'],
];

// Bersihkan spasi tak terlihat pada nama
foreach ($dataSiswa as &$siswa) {
    $siswa['nama'] = trim($siswa['nama']);
    $siswa['nis'] = trim($siswa['nis']);
    $siswa['kelas'] = trim($siswa['kelas']);
}
unset($siswa);

$uniqueNames = array_unique(array_column($dataSiswa, 'nama'));
sort($uniqueNames);

$daftarKelas = [
    'XII PPLG 1', 'XII PPLG 2', 'XII AKT', 'XII DKV', 'XII ANM', 'XII PEMASARAN',
    'XI PPLG 1', 'XI PPLG 2', 'XI AKT', 'XI DKV', 'XI ANM',
    'X PPLG 1', 'X PPLG 2', 'X AKT 1', 'X AKT 2', 'X DKV 1', 'X DKV 2'
];
// =========================================================================

$roomSql = 'SELECT id_ruang, nama_ruang, kode_ruang FROM ruang';
$roomParams = [];
if ($searchQuery !== '') {
    $roomSql .= ' WHERE nama_ruang LIKE ? OR kode_ruang LIKE ?';
    $searchPattern = '%' . $searchQuery . '%';
    $roomParams = [$searchPattern, $searchPattern];
}
$roomSql .= ' ORDER BY nama_ruang, kode_ruang';
$publicRooms = Database::all($roomSql, $roomParams);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventaris · <?= APP_NAME ?></title>
  <link href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="app-modern catalog-modern">
<div class="app-layout catalog-layout">
  <!-- Navbar katalog publik -->
  <aside class="app-sidebar">
    <a class="app-brand" href="<?= url('') ?>">
      <span class="app-brand-mark"><img src="<?= url('assets/images/logo-baknus.png') ?>" alt="Logo SMK Baknus 666"></span>
      <span>Sarpras<span class="app-brand-light">SMK Baknus 666</span></span>
    </a>
    <div class="catalog-side-card">
      <span class="catalog-side-icon"><?= icon('box', 20) ?></span>
      <b>Katalog Inventaris</b>
      <small>Lihat barang dan ketersediaan stok.</small>
    </div>
    <div class="app-sidebar-bottom">
      <a class="app-nav-link" href="<?= url('login.php') ?>"><?= icon('user', 18) ?><span>Masuk sebagai petugas</span></a>
    </div>
  </aside>

  <main class="app-main">
    <!-- Header -->
    <header class="app-topbar">
      <form class="app-search" method="get" role="search">
        <?= icon('search', 17) ?>
        <input name="q" aria-label="Cari inventaris" value="<?= e($searchQuery) ?>" placeholder="Cari nama, kode, kategori, ruang...">
        <button class="catalog-search-button" type="submit">Cari</button>
      </form>
      <div class="catalog-top-actions">
        <a class="catalog-staff-link" href="<?= url('login.php') ?>"><?= icon('user', 17) ?> Masuk petugas</a>
      </div>
    </header>

    <!-- Judul -->
    <div class="page-head">
      <div>
        <p class="app-page-eyebrow">SARANA &amp; PRASARANA · PUBLIK</p>
        <h1>Daftar Inventaris</h1>
        <p>Lihat daftar barang dan ruangan sekolah, lalu ajukan peminjaman kepada operator.</p>
      </div>
      <span class="catalog-count"><?= count($borrowableItems) ?> jenis barang · <?= count($publicRooms) ?> ruangan</span>
    </div>

    <?= flash_show() ?>

    <?php if ($searchQuery !== ''): ?>
      <p class="catalog-query">Hasil pencarian untuk <b><?= e($searchQuery) ?></b></p>
    <?php endif ?>
    
    <!-- ================================================================== -->
    <!-- DAFTAR BARANG (OTOMATIS BERDASARKAN KATEGORI DATABASE)             -->
    <!-- ================================================================== -->
    <?php if ($groupedBorrowableItems): ?>
        <?php foreach ($groupedBorrowableItems as $kategoriNama => $items): ?>
        <section class="catalog-borrow-list" style="margin-top: 30px;">
          <div class="catalog-list-heading">
            <div>
              <p class="app-page-eyebrow">KATEGORI: <?= strtoupper(e($kategoriNama)) ?></p>
              <h2>Barang yang Bisa Diajukan</h2>
            </div>
            <span class="catalog-count"><?= count($items) ?> jenis barang</span>
          </div>
          <div class="catalog-borrow-grid">
            <?php foreach ($items as $item): ?>
              <?php $itemId = (int)$item['id_katalog_barang']; $itemFormOpen = $openItemRequestId === $itemId; ?>
              <article class="catalog-borrow-item" id="item-<?= $itemId ?>">
                <?php if (!empty($item['foto'])): ?>
                  <img class="catalog-borrow-photo" src="<?= e(url($item['foto'])) ?>" alt="<?= e($item['nama_barang']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="catalog-borrow-icon"><?= icon('box', 17) ?></span>
                <?php endif ?>
                <span class="catalog-borrow-name">
                  <b><?= e($item['nama_barang']) ?></b>
                  <small><?= e($item['kategori']) ?></small>
                </span>
                
                <!-- Kondisi Barang -->
                <div class="catalog-borrow-conditions mb-2 d-flex flex-wrap gap-1">
                  <?php 
                    $kondisiList = explode(',', $item['ringkasan_kondisi'] ?? '');
                    if (empty(trim($item['ringkasan_kondisi'] ?? ''))) {
                        echo '<span class="badge bg-secondary">Belum ada data kondisi</span>';
                    } else {
                        foreach ($kondisiList as $kondisi) {
                            $kondisi = trim($kondisi);
                            $badgeClass = 'bg-secondary';
                            if (stripos($kondisi, 'Baik') !== false) $badgeClass = 'bg-success';
                            elseif (stripos($kondisi, 'Rusak Ringan') !== false) $badgeClass = 'bg-warning text-dark';
                            elseif (stripos($kondisi, 'Rusak Berat') !== false) $badgeClass = 'bg-danger';
                            echo '<span class="badge ' . $badgeClass . '" style="font-size: 0.75rem; padding: 0.35em 0.65em;">' . e($kondisi) . '</span>';
                        }
                    }
                  ?>
                </div>

                <span class="catalog-borrow-stock"><?= icon('clock', 14) ?> Tersedia: <?= (int)$item['total_stok'] ?> unit · konfirmasi operator</span>
                <span class="catalog-item-actions">
                  <button type="button" class="catalog-item-action" data-form-toggle="item-request-<?= $itemId ?>" aria-controls="item-request-<?= $itemId ?>" aria-expanded="<?= $itemFormOpen ? 'true' : 'false' ?>"><?= $itemFormOpen ? 'Tutup' : 'Ajukan Peminjaman' ?></button>
                </span>
                <div class="catalog-inline-form" id="item-request-<?= $itemId ?>" <?= $itemFormOpen ? '' : 'hidden' ?>>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_katalog_barang" value="<?= $itemId ?>">
                    <div class="catalog-request-fields">
                      <label>Nama lengkap
                        <select class="form-select" name="nama_peminjam" required onchange="updateNisDropdown(this)">
                            <option value="">-- Pilih Nama Siswa --</option>
                            <?php foreach ($uniqueNames as $nama): ?>
                                <option value="<?= e($nama) ?>"><?= e($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                      </label>
                      <label>NIS
                        <select class="form-select" name="nis" required onchange="updateKelasDropdown(this)">
                            <option value="">-- Pilih NIS --</option>
                        </select>
                      </label>
                      <label>Kelas
                        <select class="form-select" name="kelas" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftarKelas as $kls): ?>
                                <option value="<?= e($kls) ?>"><?= e($kls) ?></option>
                            <?php endforeach; ?>
                        </select>
                      </label>
                      <label>Jumlah unit
                        <input class="form-control" type="number" name="jumlah" min="1" max="<?= max(1, (int)$item['total_stok']) ?>" value="1" required>
                      </label>
                      <label>Tanggal pinjam
                        <input class="form-control" type="date" name="tanggal_pinjam" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                      </label>
                      <label>Rencana tanggal kembali
                        <input class="form-control" type="date" name="batas_kembali" value="<?= e(date('Y-m-d', strtotime('+7 day'))) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                      </label>
                      <label class="catalog-request-purpose">Keperluan <span>(opsional)</span>
                        <input class="form-control" type="text" name="keperluan" maxlength="255" placeholder="Contoh: Kegiatan praktik">
                      </label>
                    </div>
                    <button class="catalog-request-submit" type="submit"><?= icon('file', 16) ?><span>Kirim Pengajuan</span></button>
                  </form>
                </div>
              </article>
            <?php endforeach ?>
          </div>
        </section>
        <?php endforeach ?>
    <?php else: ?>
        <div class="catalog-empty"><?= icon('search', 25) ?><b>Barang tidak ditemukan</b><span>Coba kata kunci lain, misalnya nama barang atau kategori.</span></div>
    <?php endif ?>

    <!-- ================================================================== -->
    <!-- BAGIAN INVENTARIS LAINNYA (OTOMATIS BERDASARKAN KATEGORI DATABASE) -->
    <!-- ================================================================== -->
    <?php if ($groupedOtherItems): ?>
        <?php foreach ($groupedOtherItems as $kategoriNama => $items): ?>
        <section class="catalog-borrow-list" style="margin-top: 30px;">
          <div class="catalog-list-heading">
            <div>
              <p class="app-page-eyebrow">INVENTARIS LAINNYA: <?= strtoupper(e($kategoriNama)) ?></p>
              <h2>Barang Inventaris Lainnya</h2>
            </div>
            <span class="catalog-count"><?= count($items) ?> jenis barang</span>
          </div>
          <p class="catalog-list-note">Kondisi dan stok dari inventaris tercatat yang belum ada di daftar pengajuan publik. Hubungi operator untuk peminjaman barang-barang ini.</p>
          <div class="catalog-borrow-grid">
            <?php foreach ($items as $index => $item): ?>
              <?php 
                $otherItemId = isset($item['id_katalog_barang']) ? (int)$item['id_katalog_barang'] : 'other-' . md5($item['nama'] . $index); 
                $otherFormOpen = (isset($item['id_katalog_barang']) && $openItemRequestId === (int)$item['id_katalog_barang']);
              ?>
              <article class="catalog-borrow-item" id="item-<?= $otherItemId ?>">
                <?php if (!empty($item['foto'])): ?>
                  <img class="catalog-borrow-photo" src="<?= e(url($item['foto'])) ?>" alt="<?= e($item['nama']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="catalog-borrow-icon"><?= icon('box', 17) ?></span>
                <?php endif ?>
                <span class="catalog-borrow-name">
                  <b><?= e($item['nama']) ?></b>
                  <small><?= e($item['nama_jenis']) ?></small>
                </span>
                
                <div class="catalog-borrow-conditions mb-2 d-flex flex-wrap gap-1">
                  <?php 
                    $kondisiList = explode(',', $item['ringkasan_kondisi'] ?? '');
                    if (empty(trim($item['ringkasan_kondisi'] ?? ''))) {
                        echo '<span class="badge bg-secondary">Belum ada data kondisi</span>';
                    } else {
                        foreach ($kondisiList as $kondisi) {
                            $kondisi = trim($kondisi);
                            $badgeClass = 'bg-secondary';
                            if (stripos($kondisi, 'Baik') !== false) $badgeClass = 'bg-success';
                            elseif (stripos($kondisi, 'Rusak Ringan') !== false) $badgeClass = 'bg-warning text-dark';
                            elseif (stripos($kondisi, 'Rusak Berat') !== false) $badgeClass = 'bg-danger';
                            echo '<span class="badge ' . $badgeClass . '" style="font-size: 0.75rem; padding: 0.35em 0.65em;">' . e($kondisi) . '</span>';
                        }
                    }
                  ?>
                </div>

                <span class="catalog-borrow-stock"><?= icon('clock', 14) ?> Tersedia: <?= (int)$item['total_stok'] ?> unit</span>
                
                <span class="catalog-item-actions">
                  <button type="button" class="catalog-item-action" data-form-toggle="other-request-<?= $otherItemId ?>" aria-controls="other-request-<?= $otherItemId ?>" aria-expanded="<?= $otherFormOpen ? 'true' : 'false' ?>"><?= $otherFormOpen ? 'Tutup' : 'Ajukan Peminjaman' ?></button>
                </span>
                
                <div class="catalog-inline-form" id="other-request-<?= $otherItemId ?>" <?= $otherFormOpen ? '' : 'hidden' ?>>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_katalog_barang" value="<?= isset($item['id_katalog_barang']) ? (int)$item['id_katalog_barang'] : 0 ?>">
                    <div class="catalog-request-fields">
                      <label>Nama lengkap
                        <select class="form-select" name="nama_peminjam" required onchange="updateNisDropdown(this)">
                            <option value="">-- Pilih Nama Siswa --</option>
                            <?php foreach ($uniqueNames as $nama): ?>
                                <option value="<?= e($nama) ?>"><?= e($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                      </label>
                      <label>NIS
                        <select class="form-select" name="nis" required onchange="updateKelasDropdown(this)">
                            <option value="">-- Pilih NIS --</option>
                        </select>
                      </label>
                      <label>Kelas
                        <select class="form-select" name="kelas" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftarKelas as $kls): ?>
                                <option value="<?= e($kls) ?>"><?= e($kls) ?></option>
                            <?php endforeach; ?>
                        </select>
                      </label>
                      <label>Jumlah unit
                        <input class="form-control" type="number" name="jumlah" min="1" max="<?= max(1, (int)$item['total_stok']) ?>" value="1" required>
                      </label>
                      <label>Tanggal pinjam
                        <input class="form-control" type="date" name="tanggal_pinjam" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                      </label>
                      <label>Rencana tanggal kembali
                        <input class="form-control" type="date" name="batas_kembali" value="<?= e(date('Y-m-d', strtotime('+7 day'))) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                      </label>
                      <label class="catalog-request-purpose">Keperluan <span>(opsional)</span>
                        <input class="form-control" type="text" name="keperluan" maxlength="255" placeholder="Contoh: Kegiatan praktik">
                      </label>
                    </div>
                    <button class="catalog-request-submit" type="submit"><?= icon('file', 16) ?><span>Kirim Pengajuan</span></button>
                  </form>
                </div>
              </article>
            <?php endforeach ?>
          </div>
        </section>
        <?php endforeach ?>
    <?php endif ?>

    <!-- ================================================================== -->
    <!-- DAFTAR RUANGAN                                                     -->
    <!-- ================================================================== -->
    <section class="catalog-borrow-list" style="margin-top: 30px;">
      <div class="catalog-list-heading">
        <div>
          <p class="app-page-eyebrow">DAFTAR RUANGAN</p>
          <h2>Ruangan Sekolah</h2>
        </div>
        <span class="catalog-count"><?= count($publicRooms) ?> ruangan</span>
      </div>
      <p class="catalog-list-note">Daftar ruangan sekolah untuk informasi lokasi dan kebutuhan peminjaman ruangan.</p>
      <div class="catalog-borrow-grid">
        <?php foreach ($publicRooms as $room): ?>
          <?php $roomId = (int)$room['id_ruang']; $roomFormOpen = $openRoomRequestId === $roomId; ?>
          <article class="catalog-borrow-item catalog-room-item" id="room-<?= $roomId ?>">
            <span class="catalog-borrow-icon"><?= icon('home', 17) ?></span>
            <span class="catalog-borrow-name">
              <b><?= e($room['nama_ruang']) ?></b>
              <small>Ruangan sekolah</small>
            </span>
            <span class="catalog-item-actions">
              <button type="button" class="catalog-item-action" data-form-toggle="room-request-<?= $roomId ?>" aria-controls="room-request-<?= $roomId ?>" aria-expanded="<?= $roomFormOpen ? 'true' : 'false' ?>"><?= $roomFormOpen ? 'Tutup' : 'Ajukan Peminjaman' ?></button>
            </span>
            <div class="catalog-inline-form" id="room-request-<?= $roomId ?>" <?= $roomFormOpen ? '' : 'hidden' ?>>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="room_request">
                <input type="hidden" name="id_ruang" value="<?= $roomId ?>">
                <div class="catalog-request-fields">
                  <label>Nama lengkap
                    <select class="form-select" name="nama_peminjam" required onchange="updateNisDropdown(this)">
                        <option value="">-- Pilih Nama Siswa --</option>
                        <?php foreach ($uniqueNames as $nama): ?>
                            <option value="<?= e($nama) ?>"><?= e($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                  </label>
                  <label>NIS
                    <select class="form-select" name="nis" required onchange="updateKelasDropdown(this)">
                        <option value="">-- Pilih NIS --</option>
                    </select>
                  </label>
                  <label>Kelas
                    <select class="form-select" name="kelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftarKelas as $kls): ?>
                            <option value="<?= e($kls) ?>"><?= e($kls) ?></option>
                        <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Waktu mulai
                    <input class="form-control" type="datetime-local" name="waktu_mulai" value="<?= e(date('Y-m-d\T08:00', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required>
                  </label>
                  <label>Waktu selesai
                    <input class="form-control" type="datetime-local" name="waktu_selesai" value="<?= e(date('Y-m-d\T09:00', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required>
                  </label>
                  <label class="catalog-request-purpose">Keperluan
                    <input class="form-control" type="text" name="keperluan" maxlength="255" placeholder="Contoh: Rapat OSIS" required>
                  </label>
                </div>
                <button class="catalog-request-submit" type="submit"><?= icon('home', 16) ?><span>Kirim Pengajuan Ruangan</span></button>
              </form>
            </div>
          </article>
        <?php endforeach ?>
        <?php if (!$publicRooms): ?>
          <div class="catalog-empty"><?= icon('search', 25) ?><b>Ruangan tidak ditemukan</b><span>Coba kata kunci lain.</span></div>
        <?php endif ?>
      </div>
    </section>

    <p class="catalog-help">Setelah mengirim pengajuan, tunggu konfirmasi operator sekolah.</p>
  </main>
</div>
<script>
  // Script Toggle Form
  document.querySelectorAll('[data-form-toggle]').forEach((toggle) => {
    const form = document.getElementById(toggle.dataset.formToggle);
    if (!form) return;

    toggle.addEventListener('click', () => {
      const isOpening = form.hidden;
      form.hidden = !isOpening;
      toggle.setAttribute('aria-expanded', String(isOpening));
      toggle.textContent = isOpening ? 'Tutup' : 'Ajukan Peminjaman';

      if (isOpening) {
        const firstInput = form.querySelector('select:not([name="nis"]):not([name="kelas"]), input:not([type="hidden"])');
        if (firstInput) firstInput.focus();
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  });

  // Data Siswa untuk Javascript
  const siswaData = <?= json_encode($dataSiswa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  function updateNisDropdown(namaSelect) {
    const form = namaSelect.closest('form');
    const nisSelect = form.querySelector('select[name="nis"]');
    const kelasSelect = form.querySelector('select[name="kelas"]');

    nisSelect.innerHTML = '<option value="">-- Pilih NIS --</option>';
    kelasSelect.value = ''; 

    const selectedNama = namaSelect.value;
    if (!selectedNama) return;

    const matchingSiswa = siswaData.filter(s => s.nama.toLowerCase().trim() === selectedNama.toLowerCase().trim());

    matchingSiswa.forEach(s => {
      const option = document.createElement('option');
      option.value = s.nis;
      option.textContent = s.nis;
      option.setAttribute('data-kelas', s.kelas);
      nisSelect.appendChild(option);
    });

    if (matchingSiswa.length === 1) {
      nisSelect.selectedIndex = 1;
      updateKelasDropdown(nisSelect);
    }
  }

  function updateKelasDropdown(nisSelect) {
    const form = nisSelect.closest('form');
    const kelasSelect = form.querySelector('select[name="kelas"]');

    const selectedNis = nisSelect.value;
    if (!selectedNis) {
      kelasSelect.value = ''; 
      return;
    }

    const selectedOption = nisSelect.options[nisSelect.selectedIndex];
    const kelas = selectedOption.getAttribute('data-kelas');

    if (kelas) {
      let exists = Array.from(kelasSelect.options).some(opt => opt.value === kelas);
      if (!exists) {
        const option = document.createElement('option');
        option.value = kelas;
        option.textContent = kelas;
        kelasSelect.appendChild(option);
      }
      kelasSelect.value = kelas;
    }
  }
</script>
</body>
</html>