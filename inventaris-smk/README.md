# Sarpras SMK BN 666
Aplikasi Inventaris Sarana & Prasarana berbasis web — **PHP native (OOP) + MySQL + Bootstrap 5**, berjalan offline di localhost.

## Cara menjalankan (XAMPP / Laragon)
1. Salin folder proyek ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Jalankan **Apache** dan **MySQL**, buka phpMyAdmin → tab **Import** → pilih `database/sarpras_smk_bn_666.sql`. File SQL membuat database `sarpras_smk_bn_666` sekaligus akun demo.
3. Pastikan `config/config.php` memakai nama database dan `BASE_URL` yang sesuai dengan folder proyek. Untuk folder `inventaris-smk`, gunakan `BASE_URL` `/inventaris-smk`.
4. Buka `http://localhost/inventaris-smk/` untuk katalog publik dan pengajuan peminjaman. Petugas dapat masuk melalui tombol **Masuk petugas** dengan akun administrator atau operator di bawah.

Persyaratan: **PHP 8.1+** dan **MySQL 8 / MariaDB 10.4+**.

> **Penting:** Import SQL hanya untuk pemasangan awal. Skrip akan menghapus dan membuat ulang tabel aplikasi; jangan import ulang jika sudah ada data yang ingin disimpan.

| Level | Username | Password |
|---|---|---|
| Administrator | `admin` | `admin123` |
| Operator | `operator` | `operator123` |

Peminjam tidak memiliki akun dan tidak login ke aplikasi ini. Halaman awal menampilkan katalog publik dengan foto, ringkasan kondisi per jumlah unit, stok tersedia, daftar ruang, serta formulir untuk mengajukan peminjaman barang atau ruangan tanpa masuk ke halaman login. Inventaris yang belum tercantum di daftar pengajuan juga ditampilkan dengan kondisi dan stoknya; peminjam dapat menghubungi operator untuk meminjam barang tersebut. Pengajuan barang masuk ke menu **Data Peminjam** untuk ditinjau operator. Operator mencocokkan barang fisik sebelum menyetujui; pengajuan ruangan berisi waktu mulai/selesai serta keperluan, dan sistem menolak persetujuan bila jadwal berbenturan.

Menu **Inventaris** administrator juga menampilkan master nama barang tersebut. Barang yang belum memiliki stok dapat dipilih untuk mengisi kode, jumlah sebenarnya, kondisi, lokasi, dan foto tanpa mengubah barang/stok yang sudah tercatat. Foto inventaris dapat diunggah atau diganti melalui formulir tambah/ubah barang (JPG, PNG, atau WebP, maksimal 5 MB) dan akan tampil pada katalog publik. Jika memakai database lama, jalankan `database/inventaris_foto_migration.sql` satu kali untuk menambahkan kolom foto.

Menu **Ruang** administrator berisi daftar 59 nama ruang dari data yang dikirim. Karena sumber tidak mencantumkan kode ruang, kode `SMK-R001` hingga `SMK-R059` dibuat untuk identifikasi; nama ruang yang berulang tetap dipertahankan.

Jika aplikasi memakai database lama, jalankan `database/pengajuan_peminjaman_migration.sql` untuk membuat daftar barang booking dan memperbarui tabel pengajuan, `database/ruang_master_migration.sql` untuk menambahkan daftar ruang, dan `database/peminjaman_ruang_migration.sql` untuk mengaktifkan pengajuan serta persetujuan peminjaman ruangan. Jangan impor ulang berkas SQL pemasangan awal pada database berisi data karena skrip pemasangan menghapus dan membuat ulang tabel aplikasi.

## Hak akses
| Fitur | Administrator | Operator | Peminjam |
|---|:-:|:-:|:-:|
| Login / Logout | ✔ | ✔ | — (tidak login, hanya diprofiling) |
| Lihat katalog inventaris | ✔ | ✔ | ✔ (publik) |
| Kelola data induk (inventaris, kategori, ruang, user petugas) | ✔ | ✘ | ✘ |
| Kelola data peminjam (guru/pegawai/siswa) | ✔ | ✘ | ✘ |
| Setujui/tolak pengajuan peminjaman | ✘ | ✔ | ✘ |
| Peminjaman & pengembalian | ✘ | ✔ | ✘ |
| Generate laporan, export & import | ✔ | ✘ | ✘ |

## Struktur folder
```
sarpras-smk-bn-666/
├── index.php  login.php  logout.php
├── config/config.php            # BASE_URL & koneksi database
├── app/                         # logika OOP
│   ├── init.php  helpers.php    # bootstrap, helper, ikon SVG, menu
│   ├── Database.php  Auth.php   # PDO singleton, login & hak akses
│   ├── Crud.php  master_config.php   # CRUD generik berbasis konfigurasi
│   ├── Peminjaman.php           # transaksi pinjam / kembali
│   └── Report.php               # dashboard, laporan, import CSV
├── includes/header.php footer.php
├── pages/
│   ├── dashboard.php  master.php  peminjaman.php  pengembalian.php
│   └── laporan.php  export.php  import.php  katalog.php (publik + pengajuan)
├── assets/ css/style.css  js/app.js  vendor/bootstrap/ (lokal, offline)
└── database/
    ├── sarpras_smk_bn_666.sql
    ├── pengajuan_peminjaman_migration.sql
    ├── ruang_master_migration.sql
    ├── peminjaman_ruang_migration.sql
    └── inventaris_foto_migration.sql
```

## Poin teknis
- **Database** mengikuti PDM soal. Field tambahan: `pegawai.jabatan`, `pegawai.aktif`, `peminjaman.batas_kembali`, `peminjaman.id_petugas`, waktu catat/kembali.
- **Stored program**: `fn_stok_tersedia`, `sp_tambah_detail_pinjam` (validasi stok + row lock), `sp_kembalikan`, `sp_laporan_peminjaman`.
- **Anti duplikat**: kolom UNIQUE (`kode_inventaris`, `nip`, `username`, kode jenis/ruang), cek di PHP, import CSV melewati kode yang sudah ada, dan satu peminjam tidak bisa meminjam barang yang sama sebelum mengembalikannya.
- **Export** (CSV / Excel .xls) dan **import** (CSV) memakai rentang tanggal. Dari Excel simpan sebagai CSV sebelum import.
- **Import siswa**: buka menu **Laporan**, pilih template CSV, lalu isi kolom `nis`, `nama`, dan `kelas`. Siswa akan muncul di menu **Data Peminjam** dan bisa dipilih saat peminjaman.
- **Import inventaris**: gunakan template CSV yang tersedia di menu **Laporan**. Data pada lembar `Data Inventaris` harus diisi terlebih dahulu sebelum dapat diimpor.
- Keamanan: password `password_hash`, PDO prepared statement, token CSRF, escape output.
- Bootstrap disertakan lokal (`assets/vendor/bootstrap`) agar tetap jalan tanpa internet. Boleh diganti dengan build resmi dari getbootstrap.com.
