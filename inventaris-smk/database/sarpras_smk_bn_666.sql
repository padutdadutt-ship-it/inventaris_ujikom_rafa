-- =====================================================================
-- Database  : sarpras_smk_bn_666  (Aplikasi Inventaris Sarana & Prasarana SMK BN 666)
-- Import via phpMyAdmin (tab Import) atau: mysql -u root < sarpras_smk_bn_666.sql
-- Akun demo : admin / admin123   |   operator / operator123
-- =====================================================================
CREATE DATABASE IF NOT EXISTS sarpras_smk_bn_666 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sarpras_smk_bn_666;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detail_pinjam, pengajuan_peminjaman_ruang, pengajuan_peminjaman, peminjaman, katalog_barang_pinjam, pegawai, inventaris, ruang, jenis, petugas, level;
DROP FUNCTION  IF EXISTS fn_stok_tersedia;
DROP PROCEDURE IF EXISTS sp_tambah_detail_pinjam;
DROP PROCEDURE IF EXISTS sp_kembalikan;
DROP PROCEDURE IF EXISTS sp_laporan_peminjaman;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------- TABEL
CREATE TABLE level (
  id_level   INT AUTO_INCREMENT PRIMARY KEY,
  nama_level VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE petugas (
  id_petugas   INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(30)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  nama_petugas VARCHAR(100) NOT NULL,
  id_level     INT NOT NULL,
  FOREIGN KEY (id_level) REFERENCES level(id_level)
) ENGINE=InnoDB;

-- jenis = kategori barang
CREATE TABLE jenis (
  id_jenis   INT AUTO_INCREMENT PRIMARY KEY,
  nama_jenis VARCHAR(60) NOT NULL,
  kode_jenis VARCHAR(10) NOT NULL UNIQUE,
  keterangan VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE ruang (
  id_ruang   INT AUTO_INCREMENT PRIMARY KEY,
  nama_ruang VARCHAR(60) NOT NULL,
  kode_ruang VARCHAR(10) NOT NULL UNIQUE,
  keterangan VARCHAR(255)
) ENGINE=InnoDB;

-- inventaris = hanya barang yang DIPINJAMKAN (bukan seluruh aset sekolah)
CREATE TABLE inventaris (
  id_inventaris    INT AUTO_INCREMENT PRIMARY KEY,
  nama             VARCHAR(100) NOT NULL,
  kondisi          ENUM('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
  keterangan       VARCHAR(255),
  jumlah           INT NOT NULL DEFAULT 1,
  id_jenis         INT NOT NULL,
  tanggal_register DATE NOT NULL,
  id_ruang         INT NOT NULL,
  kode_inventaris  VARCHAR(30) NOT NULL UNIQUE,          -- anti duplikat
  foto             VARCHAR(255) NULL,
  id_petugas       INT NOT NULL,
  CONSTRAINT chk_jumlah CHECK (jumlah >= 0),
  FOREIGN KEY (id_jenis)   REFERENCES jenis(id_jenis),
  FOREIGN KEY (id_ruang)   REFERENCES ruang(id_ruang),
  FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas)
) ENGINE=InnoDB;

CREATE TABLE katalog_barang_pinjam (
  id_katalog_barang INT AUTO_INCREMENT PRIMARY KEY,
  kategori          VARCHAR(60) NOT NULL,
  nama_barang       VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_katalog_barang (kategori, nama_barang)
) ENGINE=InnoDB;

-- pegawai = profil peminjam (tidak punya login); NIP unik agar tidak double
CREATE TABLE pegawai (
  id_pegawai   INT AUTO_INCREMENT PRIMARY KEY,
  nama_pegawai VARCHAR(100) NOT NULL,
  nip          VARCHAR(30)  NOT NULL UNIQUE,
  jabatan      VARCHAR(60)  NOT NULL,
  alamat       VARCHAR(255),
  aktif        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE peminjaman (
  id_peminjaman     INT AUTO_INCREMENT PRIMARY KEY,
  tanggal_pinjam    DATE NOT NULL,
  batas_kembali     DATE NOT NULL,                        -- rencana pengembalian
  tanggal_kembali   DATE NULL,                            -- realisasi pengembalian
  status_peminjaman ENUM('Dipinjam','Dikembalikan') NOT NULL DEFAULT 'Dipinjam',
  id_pegawai        INT NOT NULL,
  id_petugas        INT NULL,                             -- operator yang mencatat
  dicatat_pada      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dikembalikan_pada DATETIME NULL,
  FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai),
  FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas)
) ENGINE=InnoDB;

CREATE TABLE pengajuan_peminjaman (
  id_pengajuan   INT AUTO_INCREMENT PRIMARY KEY,
  nama_peminjam  VARCHAR(100) NOT NULL,
  nis            VARCHAR(30) NOT NULL,
  kelas          VARCHAR(60) NOT NULL,
  id_inventaris  INT NULL,
  id_katalog_barang INT NULL,
  jumlah         INT NOT NULL,
  tanggal_pinjam DATE NOT NULL,
  batas_kembali  DATE NOT NULL,
  keperluan      VARCHAR(255) NULL,
  status         ENUM('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  diajukan_pada  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_petugas     INT NULL,
  ditangani_pada DATETIME NULL,
  CONSTRAINT chk_pengajuan_jumlah CHECK (jumlah > 0),
  FOREIGN KEY (id_inventaris) REFERENCES inventaris(id_inventaris),
  FOREIGN KEY (id_katalog_barang) REFERENCES katalog_barang_pinjam(id_katalog_barang),
  FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas),
  INDEX idx_pengajuan_status (status, diajukan_pada)
) ENGINE=InnoDB;

CREATE TABLE pengajuan_peminjaman_ruang (
  id_pengajuan_ruang INT AUTO_INCREMENT PRIMARY KEY,
  nama_peminjam      VARCHAR(100) NOT NULL,
  nis                VARCHAR(30) NOT NULL,
  kelas              VARCHAR(60) NOT NULL,
  id_ruang           INT NOT NULL,
  waktu_mulai        DATETIME NOT NULL,
  waktu_selesai      DATETIME NOT NULL,
  keperluan          VARCHAR(255) NOT NULL,
  status             ENUM('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  diajukan_pada      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_petugas         INT NULL,
  ditangani_pada     DATETIME NULL,
  CONSTRAINT chk_pengajuan_ruang_waktu CHECK (waktu_selesai > waktu_mulai),
  FOREIGN KEY (id_ruang) REFERENCES ruang(id_ruang),
  FOREIGN KEY (id_petugas) REFERENCES petugas(id_petugas),
  INDEX idx_pengajuan_ruang_jadwal (id_ruang, status, waktu_mulai, waktu_selesai)
) ENGINE=InnoDB;

CREATE TABLE detail_pinjam (
  id_detail_pinjam INT AUTO_INCREMENT PRIMARY KEY,
  id_peminjaman    INT NOT NULL,
  id_inventaris    INT NOT NULL,
  jumlah           INT NOT NULL,
  CONSTRAINT chk_dp_jumlah CHECK (jumlah > 0),
  UNIQUE KEY uq_pinjam_barang (id_peminjaman, id_inventaris),
  FOREIGN KEY (id_peminjaman) REFERENCES peminjaman(id_peminjaman) ON DELETE CASCADE,
  FOREIGN KEY (id_inventaris) REFERENCES inventaris(id_inventaris)
) ENGINE=InnoDB;

-- ------------------------------------------------- STORED FUNCTION & PROCEDURE
DELIMITER $$

-- stok tersedia = jumlah total - jumlah yang sedang dipinjam
CREATE FUNCTION fn_stok_tersedia(p_id INT) RETURNS INT
READS SQL DATA
BEGIN
  DECLARE v_total  INT DEFAULT 0;
  DECLARE v_pinjam INT DEFAULT 0;
  SELECT jumlah INTO v_total FROM inventaris WHERE id_inventaris = p_id;
  SELECT COALESCE(SUM(d.jumlah), 0) INTO v_pinjam
    FROM detail_pinjam d JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
   WHERE d.id_inventaris = p_id AND p.status_peminjaman = 'Dipinjam';
  RETURN COALESCE(v_total, 0) - v_pinjam;
END$$

-- tambah barang ke peminjaman + validasi stok (row lock agar aman dari balapan data)
CREATE PROCEDURE sp_tambah_detail_pinjam(IN p_pinjam INT, IN p_inv INT, IN p_jml INT)
BEGIN
  DECLARE v_total INT;
  SELECT jumlah INTO v_total FROM inventaris WHERE id_inventaris = p_inv FOR UPDATE;
  IF v_total IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Barang tidak ditemukan';
  END IF;
  IF p_jml < 1 OR p_jml > fn_stok_tersedia(p_inv) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok barang tidak mencukupi';
  END IF;
  INSERT INTO detail_pinjam (id_peminjaman, id_inventaris, jumlah) VALUES (p_pinjam, p_inv, p_jml);
END$$

-- proses pengembalian (stok otomatis kembali karena dihitung dari status)
CREATE PROCEDURE sp_kembalikan(IN p_id INT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM peminjaman WHERE id_peminjaman = p_id AND status_peminjaman = 'Dipinjam') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Peminjaman tidak ditemukan atau sudah dikembalikan';
  END IF;
  UPDATE peminjaman
     SET status_peminjaman = 'Dikembalikan', tanggal_kembali = CURDATE(), dikembalikan_pada = NOW()
   WHERE id_peminjaman = p_id;
END$$

-- laporan peminjaman berdasarkan rentang tanggal pinjam
CREATE PROCEDURE sp_laporan_peminjaman(IN p_dari DATE, IN p_sampai DATE)
BEGIN
  SELECT p.id_peminjaman, p.tanggal_pinjam, p.batas_kembali, p.tanggal_kembali, p.status_peminjaman,
         g.nama_pegawai, g.nip, g.jabatan,
         GROUP_CONCAT(CONCAT(i.nama, ' (', d.jumlah, ')') SEPARATOR ', ') AS barang,
         SUM(d.jumlah) AS total_unit
    FROM peminjaman p
    JOIN pegawai g       ON g.id_pegawai = p.id_pegawai
    JOIN detail_pinjam d ON d.id_peminjaman = p.id_peminjaman
    JOIN inventaris i    ON i.id_inventaris = d.id_inventaris
   WHERE p.tanggal_pinjam BETWEEN p_dari AND p_sampai
   GROUP BY p.id_peminjaman
   ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC;
END$$

DELIMITER ;

-- ---------------------------------------------------------------- DATA AWAL
INSERT INTO level (id_level, nama_level) VALUES (1,'administrator'), (2,'operator');

INSERT INTO petugas (username, password, nama_petugas, id_level) VALUES
('admin',    '$2y$12$OmKXiMWrMdZOzkzDYaFxLemVm.Bt92Ylg7tZRbhGfs3zkrY7ScVbG', 'Administrator Sarpras', 1),
('operator', '$2y$12$NtTIKs8ZG21rWNYnxEHgwOVf1CnFNWJD3zA8AwPm5mXu86vtmM49q', 'Operator Sarpras',      2);

INSERT INTO jenis (nama_jenis, kode_jenis, keterangan) VALUES
('Elektronik','ELK','Laptop, proyektor, kamera'),
('Perabot','PRB','Kursi, meja, papan tulis portabel'),
('Alat Praktik','ALT','Peralatan praktik jurusan'),
('Olahraga','OLR','Perlengkapan olahraga');

INSERT INTO katalog_barang_pinjam (kategori, nama_barang) VALUES
('Perabotan','Akrilik Nama'),
('Perabotan','Filling cabinet'),
('Perabotan','Kursi Guru'),
('Perabotan','Kursi Rapat'),
('Perabotan','Kursi siswa'),
('Perabotan','Lemari Alat Bahan'),
('Perabotan','Lemari Arsip'),
('Perabotan','Loker Guru'),
('Perabotan','Meja Guru'),
('Perabotan','Meja Kerja'),
('Perabotan','Meja Komputer'),
('Perabotan','Meja Produksi'),
('Perabotan','Meja Rapat'),
('Perabotan','Meja Siswa'),
('Perabotan','Papan Pengumuman'),
('Perabotan','papan tulis'),
('Perabotan','Rak Piala'),
('Perabotan','Sofa Tamu'),
('Perabotan','Tong Sampah'),
('Perabotan','Ranjang dan kasur'),
('Perabotan','Sapu'),
('Perabotan','Lap pel'),
('Elektronik','Interactive Flat Panel'),
('Elektronik','AC'),
('Elektronik','CCTV'),
('Elektronik','Dispenser'),
('Elektronik','Kipas Angin/ Exhaust fan'),
('Elektronik','Layar Proyektor'),
('Elektronik','Mesin Fotokopi'),
('Elektronik','Microphone'),
('Elektronik','Mixer Suara'),
('Elektronik','Printer'),
('Elektronik','Proyektor/Infokus'),
('Elektronik','Scanner'),
('Elektronik','Telepon Kantor'),
('Elektronik','TV'),
('Elektronik','Sound Sistem'),
('Elektronik','Ampli Gitar'),
('Elektronik','Speaker Portable'),
('Elektronik','Handphone'),
('Elektronik','Speaker Kecil'),
('Elektronik','Terminal stop kontak'),
('Alat Praktik/Lab','Komputer Akuntansi'),
('Alat Praktik/Lab','Komputer Animasi'),
('Alat Praktik/Lab','Komputer DKV'),
('Alat Praktik/Lab','Komputer Pemasaran'),
('Alat Praktik/Lab','Komputer PPLG 1'),
('Alat Praktik/Lab','Komputer PPLG 2'),
('Alat Praktik/Lab','Komputer PPLG 3'),
('Alat Praktik/Lab','Komputer PPLG 4'),
('Alat Praktik/Lab','Komputer PPLG 5'),
('Alat Praktik/Lab','LapTop'),
('Alat Praktik/Lab','Mesin Cutting'),
('Alat Praktik/Lab','Mesin Laminasi'),
('Alat Praktik/Lab','Mesin Oven'),
('Alat Praktik/Lab','Mesin Pres'),
('Alat Praktik/Lab','Printer 3D'),
('Alat Praktik/Lab','Printer DTF'),
('Alat Praktik/Lab','Printer Foto'),
('Alat Praktik/Lab','Printer Sublim'),
('Alat Praktik/Lab','Printer UV'),
('Alat Praktik/Lab','Stabilizer/ PSU'),
('Alat Praktik/Lab','Stand Lighting'),
('Alat Praktik/Lab','Stand Microphone'),
('Alat Praktik/Lab','Tripod Camera'),
('Alat Praktik/Lab','Kamera MD 1000'),
('Alat Praktik/Lab','Tripod Kamera'),
('Alat Praktik/Lab','Soft Box Standar'),
('Alat Praktik/Lab','Lampu LED'),
('Alat Praktik/Lab','Tripod Kecil'),
('Alat Praktik/Lab','Tuled + Bracket'),
('Alat Praktik/Lab','Background Hijau'),
('Alat Praktik/Lab','Lampu Sorot'),
('Alat Praktik/Lab','Komputer Lab Broadcast'),
('Alat Praktik/Lab','Audio Mixer'),
('Alat Praktik/Lab','Video Mixer'),
('Alat Praktik/Lab','Rekording Pack'),
('Alat Praktik/Lab','Speaker'),
('Alat Praktik/Lab','Telepromter'),
('Alat Praktik/Lab','Mic Wireless'),
('Alat Praktik/Lab','Kamera Mirorrless'),
('Alat Praktik/Lab','Drone'),
('Alat Praktik/Lab','kamera Digital'),
('Alat Praktik/Lab','Oculus'),
('Alat Praktik/Lab','Lampu Studi'),
('Alat Praktik/Lab','Kamera DSRL'),
('Alat Praktik/Lab','Lensa'),
('Alat Praktik/Lab','Lampu Studio'),
('Alat Praktik/Lab','Flash'),
('Alat Praktik/Lab','Background'),
('Alat Praktik/Lab','Tripod Besar'),
('Alat Praktik/Lab','Soft Box Besar'),
('Alat Praktik/Lab','Soft Box Sedang'),
('Alat Praktik/Lab','Komputer Mac'),
('Alat Praktik/Lab','Printer Foto'),
('Alat Praktik/Lab','hub switch'),
('Alat Praktik/Lab','Haedphone'),
('Alat Praktik/Lab','Pan Tablet'),
('Alat Praktik/Lab','Server'),
('Alat Praktik/Lab','Access Point'),
('Alat Praktik/Lab','Kalkulator'),
('Alat Praktik/Lab','Mesin Kasir'),
('Alat Praktik/Lab','meja cetak sablon'),
('Alat Praktik/Lab','Alat Cetak Pin'),
('Alat Praktik/Lab','Alat Cetak Mug'),
('Alat Praktik/Lab','Alat musik'),
('Alat Praktik/Lab','Kendang'),
('Alat Praktik/Lab','Gamelan Degung Pelog selap Salendro'),
('Alat Praktik/Lab','Angklung Melodi'),
('Alat Praktik/Lab','Angklung Bass Partay'),
('Alat Praktik/Lab','Accompanyemen'),
('Alat Praktik/Lab','Cuk'),
('Alat Praktik/Lab','Bass Pukul'),
('Alat Praktik/Lab','Gambang Pengiring'),
('Alat Praktik/Lab','Drum'),
('Alat Praktik/Lab','Gitar Akustik'),
('Alat Praktik/Lab','Gitar Bass'),
('Alat Praktik/Lab','Matras'),
('Alat Praktik/Lab','Bola Voli'),
('Alat Praktik/Lab','Bola Basket'),
('Alat Praktik/Lab','Bola Futsal'),
('Alat Praktik/Lab','Net Voli'),
('Alat Praktik/Lab','Net Badminton'),
('Alat Praktik/Lab','Stik Kasti');

INSERT INTO ruang (nama_ruang, kode_ruang, keterangan) VALUES
('Lab RPL','LAB-RPL','Laboratorium Rekayasa Perangkat Lunak'),
('Lab TKJ','LAB-TKJ','Laboratorium Teknik Komputer & Jaringan'),
('Perpustakaan','PERPUS','Ruang perpustakaan'),
('Gudang Sarpras','GDG','Gudang penyimpanan barang pinjaman');

CREATE TEMPORARY TABLE seed_ruang_sekolah (
  urutan TINYINT NOT NULL PRIMARY KEY,
  nama_ruang VARCHAR(60) NOT NULL,
  kode_ruang VARCHAR(10) NOT NULL,
  kemunculan TINYINT NOT NULL
) ENGINE=Memory;

INSERT INTO seed_ruang_sekolah (urutan, nama_ruang, kode_ruang, kemunculan) VALUES
(1,'Kepala Sekolah','SMK-R001',1),
(2,'Guru dan Wks. Kurikulum','SMK-R002',1),
(3,'Wks kesiswaan dan Bk','SMK-R003',1),
(4,'Wks Hubin dan BKK','SMK-R004',1),
(5,'TU Keuangan','SMK-R005',1),
(6,'TU Administrasi','SMK-R006',1),
(7,'UKS','SMK-R007',1),
(8,'Osis','SMK-R008',1),
(9,'Pramuka','SMK-R009',1),
(10,'Paskibra','SMK-R010',1),
(11,'Kantor Perusahaan DUDI','SMK-R011',1),
(12,'Sarana Prasarana dan Gudang','SMK-R012',1),
(13,'IT SUPPORT','SMK-R013',1),
(14,'LAB 1 PPLG','SMK-R014',1),
(15,'LAB 2 PPLG','SMK-R015',1),
(16,'LAB 3D ANIMASI','SMK-R016',1),
(17,'LAB GAMBAR','SMK-R017',1),
(18,'LAB DKV','SMK-R018',1),
(19,'LAB AKUNTANSI','SMK-R019',1),
(20,'LAB BAHASA','SMK-R020',1),
(21,'LAB PEMASARAN','SMK-R021',1),
(22,'LAB PPLG 3','SMK-R022',1),
(23,'LAB PPLG 4','SMK-R023',1),
(24,'LAB PPLG 5','SMK-R024',1),
(25,'LAB PPLG IOT','SMK-R025',1),
(26,'LAB BROADCAST','SMK-R026',1),
(27,'SABLON','SMK-R027',1),
(28,'LAB CETAK','SMK-R028',1),
(29,'STUDIO FOTO','SMK-R029',1),
(30,'KESENIAN','SMK-R030',1),
(31,'PERPUSTAKAAN','SMK-R031',1),
(32,'XII AKUNTANSI 1','SMK-R032',1),
(33,'XII AKUNTANSI 2','SMK-R033',1),
(34,'XII DKV','SMK-R034',1),
(35,'XII ANIMASI','SMK-R035',1),
(36,'GUDANG KURSI','SMK-R036',1),
(37,'XII PPLG 1','SMK-R037',1),
(38,'XII PPLG 2','SMK-R038',1),
(39,'XI AKT','SMK-R039',1),
(40,'KOSONG','SMK-R040',1),
(41,'XI PPLG 1','SMK-R041',1),
(42,'XI PPLG 2','SMK-R042',1),
(43,'X PPLG 1','SMK-R043',1),
(44,'X PPLG 2','SMK-R044',1),
(45,'XI DKV','SMK-R045',1),
(46,'XI ANIMASI','SMK-R046',1),
(47,'X DKV 2','SMK-R047',1),
(48,'X DKV 1','SMK-R048',1),
(49,'KOSONG','SMK-R049',2),
(50,'X AKUNTANSI 2','SMK-R050',1),
(51,'GUDANG KURSI','SMK-R051',2),
(52,'X AKUNTANSI 1','SMK-R052',1),
(53,'KELAS KOSONG 1','SMK-R053',1),
(54,'KELAS KOSONG 2','SMK-R054',1),
(55,'KELAS KOSONG 3','SMK-R055',1),
(56,'KLAS KOSONG GD DEPAN 1','SMK-R056',1),
(57,'KLAS KOSONG GD DEPAN 2','SMK-R057',1),
(58,'KLAS KOSONG GD DEPAN 3','SMK-R058',1),
(59,'KLAS KOSONG GD DEPAN 4','SMK-R059',1);

INSERT INTO ruang (nama_ruang, kode_ruang)
SELECT seed.nama_ruang, seed.kode_ruang
  FROM seed_ruang_sekolah seed
  LEFT JOIN (
    SELECT LOWER(TRIM(nama_ruang)) AS nama_normal, COUNT(*) AS jumlah
      FROM ruang
     GROUP BY LOWER(TRIM(nama_ruang))
  ) existing ON existing.nama_normal = LOWER(TRIM(seed.nama_ruang))
 WHERE COALESCE(existing.jumlah, 0) < seed.kemunculan
   AND NOT EXISTS (
     SELECT 1 FROM ruang current WHERE current.kode_ruang = seed.kode_ruang
   );

DROP TEMPORARY TABLE seed_ruang_sekolah;

INSERT INTO inventaris (nama, kondisi, keterangan, jumlah, id_jenis, tanggal_register, id_ruang, kode_inventaris, id_petugas) VALUES
('Proyektor Epson EB-X06','Baik','Dengan kabel HDMI',4,1,CURDATE() - INTERVAL 60 DAY,4,'ELK-001',1),
('Laptop Lenovo ThinkPad','Baik','Untuk kegiatan luar kelas',10,1,CURDATE() - INTERVAL 55 DAY,1,'ELK-002',1),
('Kamera Mirrorless Sony','Baik','Dokumentasi kegiatan',2,1,CURDATE() - INTERVAL 40 DAY,4,'ELK-003',1),
('Kabel Roll 10 Meter','Rusak Ringan','Satu colokan longgar',6,3,CURDATE() - INTERVAL 30 DAY,4,'ALT-001',1),
('Toolkit Jaringan','Baik','Crimping, LAN tester, kabel UTP',5,3,CURDATE() - INTERVAL 28 DAY,2,'ALT-002',1),
('Kursi Lipat','Baik','Untuk acara sekolah',40,2,CURDATE() - INTERVAL 20 DAY,4,'PRB-001',1),
('Bola Voli','Baik','Standar kompetisi',8,4,CURDATE() - INTERVAL 10 DAY,4,'OLR-001',1),
('Speaker Portable','Baik','Dengan 2 mikrofon wireless',3,1,CURDATE() - INTERVAL 5 DAY,3,'ELK-004',1);

INSERT INTO pegawai (nama_pegawai, nip, jabatan, alamat, aktif) VALUES
('Budi Santoso','198501012010011001','Guru Produktif RPL','Padalarang',1),
('Siti Aminah','199002152015022002','Guru Matematika','Cimahi',1),
('Rahmat Hidayat','198803202012011003','Kepala Lab TKJ','Bandung Barat',1),
('Dewi Lestari','199207102018022001','Staf Tata Usaha','Padalarang',1),
('Agus Salim','197905051999031004','Mantan Wakasek Kesiswaan','Cimahi',0);

-- contoh transaksi: 1 aktif tepat waktu, 1 aktif terlambat, 2 sudah dikembalikan
INSERT INTO peminjaman (tanggal_pinjam, batas_kembali, tanggal_kembali, status_peminjaman, id_pegawai, id_petugas, dicatat_pada, dikembalikan_pada) VALUES
(CURDATE() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 6 DAY, NULL, 'Dipinjam', 1, 2, NOW() - INTERVAL 1 DAY, NULL),
(CURDATE() - INTERVAL 12 DAY, CURDATE() - INTERVAL 5 DAY, NULL, 'Dipinjam', 3, 2, NOW() - INTERVAL 12 DAY, NULL),
(CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 13 DAY, CURDATE() - INTERVAL 14 DAY, 'Dikembalikan', 2, 2, NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 14 DAY),
(CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 28 DAY, CURDATE() - INTERVAL 29 DAY, 'Dikembalikan', 4, 2, NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 29 DAY);

INSERT INTO detail_pinjam (id_peminjaman, id_inventaris, jumlah) VALUES
(1,1,1),(1,2,2),
(2,5,2),
(3,7,3),(3,8,1),
(4,3,1);
