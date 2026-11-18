<?php
// Konfigurasi data induk. Menambah modul baru cukup menambah satu blok di sini.
// Field: n=nama kolom, l=label, t=tipe (text|textarea|number|date|select|password|bool),
//        r=wajib, u=unik (anti duplikat), up=huruf besar, o=opsi select, d=nilai default.
function master_config(): array
{
    $administrator = 'administrator';
    $operator = 'operator';
    $userId = Auth::user()['id'] ?? 0;
    $today = date('Y-m-d');

    return [
        // Pengaturan form dan tabel kategori barang.
        'jenis' => [
            'title' => 'Kategori Barang', 'sub' => 'Kelompokkan barang pinjaman berdasarkan jenisnya',
            'table' => 'jenis', 'pk' => 'id_jenis', 'roles' => [$administrator],
            'sql' => 'SELECT j.*, (SELECT COUNT(*) FROM inventaris i WHERE i.id_jenis=j.id_jenis) AS total_barang FROM jenis j ORDER BY j.nama_jenis',
            'cols' => ['kode_jenis' => 'Kode', 'nama_jenis' => 'Kategori', 'keterangan' => 'Keterangan', 'total_barang' => 'Jml Barang'],
            'fields' => [
                ['n' => 'nama_jenis', 'l' => 'Nama Kategori', 't' => 'text', 'r' => 1],
                ['n' => 'kode_jenis', 'l' => 'Kode', 't' => 'text', 'r' => 1, 'u' => 1, 'up' => 1],
                ['n' => 'keterangan', 'l' => 'Keterangan', 't' => 'textarea'],
            ],
        ],
        // Pengaturan form dan tabel ruang penyimpanan.
        'ruang' => [
            'title' => 'Ruang', 'sub' => 'Daftar ruangan sekolah untuk kebutuhan peminjaman ruangan',
            'table' => 'ruang', 'pk' => 'id_ruang', 'roles' => [$administrator],
            'sql' => 'SELECT r.*, (SELECT COUNT(*) FROM inventaris i WHERE i.id_ruang=r.id_ruang) AS total_barang FROM ruang r ORDER BY r.nama_ruang',
            'cols' => ['kode_ruang' => 'Kode', 'nama_ruang' => 'Nama Ruang', 'keterangan' => 'Keterangan', 'total_barang' => 'Jml Barang'],
            'fields' => [
                ['n' => 'nama_ruang', 'l' => 'Nama Ruang', 't' => 'text', 'r' => 1],
                ['n' => 'kode_ruang', 'l' => 'Kode', 't' => 'text', 'r' => 1, 'u' => 1, 'up' => 1],
                ['n' => 'keterangan', 'l' => 'Keterangan', 't' => 'textarea'],
            ],
        ],
        // Pengaturan form dan tabel inventaris.
        'inventaris' => [
            'title' => 'Inventaris Barang', 'sub' => 'Daftar barang yang tersedia untuk dipinjam (bukan seluruh aset sekolah)',
            'table' => 'inventaris', 'pk' => 'id_inventaris', 'roles' => [$administrator],
            'auto' => ['id_petugas' => $userId],
            'sql' => 'SELECT i.*, j.nama_jenis, r.nama_ruang, fn_stok_tersedia(i.id_inventaris) AS tersedia
                        FROM inventaris i JOIN jenis j ON j.id_jenis=i.id_jenis JOIN ruang r ON r.id_ruang=i.id_ruang ORDER BY i.nama',
            'cols' => ['foto' => ['Foto', 'image'], 'kode_inventaris' => 'Kode', 'nama' => 'Nama Barang', 'nama_jenis' => 'Kategori', 'nama_ruang' => 'Ruang',
                       'kondisi' => ['Kondisi', 'kondisi'], 'jumlah' => 'Total', 'tersedia' => 'Tersedia'],
            'fields' => [
                ['n' => 'kode_inventaris', 'l' => 'Kode Inventaris', 't' => 'text', 'r' => 1, 'u' => 1, 'up' => 1],
                ['n' => 'nama', 'l' => 'Nama Barang', 't' => 'text', 'r' => 1],
                ['n' => 'id_jenis', 'l' => 'Kategori', 't' => 'select', 'r' => 1, 'o' => 'SELECT id_jenis v, nama_jenis t FROM jenis ORDER BY nama_jenis'],
                ['n' => 'id_ruang', 'l' => 'Ruang', 't' => 'select', 'r' => 1, 'o' => 'SELECT id_ruang v, nama_ruang t FROM ruang ORDER BY nama_ruang'],
                ['n' => 'kondisi', 'l' => 'Kondisi', 't' => 'select', 'r' => 1, 'o' => ['Baik', 'Rusak Ringan', 'Rusak Berat']],
                ['n' => 'jumlah', 'l' => 'Jumlah', 't' => 'number', 'r' => 1, 'min' => 1, 'd' => 1],
                ['n' => 'tanggal_register', 'l' => 'Tanggal Register', 't' => 'date', 'r' => 1, 'd' => $today],
                ['n' => 'keterangan', 'l' => 'Keterangan', 't' => 'textarea'],
            ],
        ],
        // Profil guru/pegawai dan siswa yang dapat meminjam barang.
        'pegawai' => [
            'title' => 'Data Peminjam', 'sub' => 'Data guru, pegawai, dan siswa. Isi kolom Jabatan / Kelas untuk peminjam.',
            'table' => 'pegawai', 'pk' => 'id_pegawai', 'roles' => [$administrator, $operator], 'del' => [$administrator],
            'sql' => 'SELECT * FROM pegawai ORDER BY aktif DESC, nama_pegawai',
            'cols' => ['nama_pegawai' => 'Nama', 'nip' => 'NIS / NIP', 'jabatan' => 'Jabatan / Kelas', 'alamat' => 'Alamat', 'aktif' => ['Status', 'bool']],
            'fields' => [
                ['n' => 'nama_pegawai', 'l' => 'Nama Lengkap', 't' => 'text', 'r' => 1],
                ['n' => 'nip', 'l' => 'NIS / NIP', 't' => 'text', 'r' => 1, 'u' => 1],
                ['n' => 'jabatan', 'l' => 'Jabatan / Kelas', 't' => 'text', 'r' => 1],
                ['n' => 'alamat', 'l' => 'Alamat', 't' => 'textarea'],
                ['n' => 'aktif', 'l' => 'Aktif (boleh meminjam)', 't' => 'bool', 'd' => 1],
            ],
        ],
        // Pengaturan akun petugas.
        'petugas' => [
            'title' => 'User Petugas', 'sub' => 'Akun administrator dan operator',
            'table' => 'petugas', 'pk' => 'id_petugas', 'roles' => [$administrator],
            'sql' => 'SELECT p.*, l.nama_level FROM petugas p JOIN level l ON l.id_level=p.id_level ORDER BY p.id_level, p.nama_petugas',
            'cols' => ['nama_petugas' => 'Nama', 'username' => 'Username', 'nama_level' => 'Level'],
            'fields' => [
                ['n' => 'nama_petugas', 'l' => 'Nama Petugas', 't' => 'text', 'r' => 1],
                ['n' => 'username', 'l' => 'Username', 't' => 'text', 'r' => 1, 'u' => 1],
                ['n' => 'password', 'l' => 'Password (kosongkan jika tidak diubah)', 't' => 'password'],
                ['n' => 'id_level', 'l' => 'Level', 't' => 'select', 'r' => 1, 'o' => 'SELECT id_level v, nama_level t FROM level ORDER BY id_level'],
            ],
        ],
    ];
}
