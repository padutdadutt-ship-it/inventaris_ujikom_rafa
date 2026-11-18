-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2026 at 08:41 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sarpras_smk_bn_666`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_kembalikan` (IN `p_id` INT)   BEGIN
  IF NOT EXISTS (SELECT 1 FROM peminjaman WHERE id_peminjaman = p_id AND status_peminjaman = 'Dipinjam') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Peminjaman tidak ditemukan atau sudah dikembalikan';
  END IF;
  UPDATE peminjaman
     SET status_peminjaman = 'Dikembalikan', tanggal_kembali = CURDATE(), dikembalikan_pada = NOW()
   WHERE id_peminjaman = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_laporan_peminjaman` (IN `p_dari` DATE, IN `p_sampai` DATE)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tambah_detail_pinjam` (IN `p_pinjam` INT, IN `p_inv` INT, IN `p_jml` INT)   BEGIN
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

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_stok_tersedia` (`p_id` INT) RETURNS INT(11) READS SQL DATA BEGIN
  DECLARE v_total  INT DEFAULT 0;
  DECLARE v_pinjam INT DEFAULT 0;
  SELECT jumlah INTO v_total FROM inventaris WHERE id_inventaris = p_id;
  SELECT COALESCE(SUM(d.jumlah), 0) INTO v_pinjam
    FROM detail_pinjam d JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
   WHERE d.id_inventaris = p_id AND p.status_peminjaman = 'Dipinjam';
  RETURN COALESCE(v_total, 0) - v_pinjam;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `detail_pinjam`
--

CREATE TABLE `detail_pinjam` (
  `id_detail_pinjam` int(11) NOT NULL,
  `id_peminjaman` int(11) NOT NULL,
  `id_inventaris` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL
) ;

--
-- Dumping data for table `detail_pinjam`
--

INSERT INTO `detail_pinjam` (`id_detail_pinjam`, `id_peminjaman`, `id_inventaris`, `jumlah`) VALUES
(1, 1, 1, 1),
(2, 1, 2, 2),
(3, 2, 5, 2),
(4, 3, 7, 3),
(5, 3, 8, 1),
(6, 4, 3, 1),
(7, 5, 1, 1),
(8, 6, 6, 4),
(9, 6, 1, 2),
(11, 8, 3, 1),
(15, 12, 59, 2),
(16, 13, 54, 1),
(17, 14, 82, 1),
(18, 15, 49, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventaris`
--

CREATE TABLE `inventaris` (
  `id_inventaris` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
  `keterangan` varchar(255) DEFAULT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `id_jenis` int(11) NOT NULL,
  `tanggal_register` date NOT NULL,
  `id_ruang` int(11) NOT NULL,
  `kode_inventaris` varchar(30) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `id_petugas` int(11) NOT NULL
) ;

--
-- Dumping data for table `inventaris`
--

INSERT INTO `inventaris` (`id_inventaris`, `nama`, `kondisi`, `keterangan`, `jumlah`, `id_jenis`, `tanggal_register`, `id_ruang`, `kode_inventaris`, `foto`, `id_petugas`) VALUES
(1, 'Proyektor Epson EB-X06', 'Baik', 'Dengan kabel HDMI', 4, 1, '2026-09-13', 4, 'ELK-001', NULL, 1),
(2, 'Laptop Lenovo ThinkPad', 'Baik', 'Untuk kegiatan luar kelas', 10, 1, '2026-09-18', 1, 'ELK-002', NULL, 1),
(3, 'Kamera Mirrorless Sony', 'Baik', 'Dokumentasi kegiatan', 2, 1, '2026-10-03', 4, 'ELK-003', NULL, 1),
(4, 'Kabel Roll 10 Meter', 'Rusak Ringan', 'Satu colokan longgar', 6, 3, '2026-10-13', 4, 'ALT-001', NULL, 1),
(5, 'Toolkit Jaringan', 'Baik', 'Crimping, LAN tester, kabel UTP', 5, 3, '2026-10-15', 2, 'ALT-002', NULL, 1),
(6, 'Kursi Lipat', 'Baik', 'Untuk acara sekolah', 40, 2, '2026-10-23', 4, 'PRB-001', NULL, 1),
(7, 'Bola Voli', 'Baik', 'Standar kompetisi', 8, 4, '2026-11-02', 4, 'OLR-001', NULL, 1),
(8, 'Speaker Portable', 'Baik', 'Dengan 2 mikrofon wireless', 3, 1, '2026-11-07', 3, 'ELK-004', NULL, 1),
(9, 'Akrilik Nama', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00001', NULL, 1),
(10, 'Filling cabinet', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00002', NULL, 1),
(11, 'Kursi Guru', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00003', NULL, 1),
(12, 'Kursi Rapat', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00004', NULL, 1),
(13, 'Kursi siswa', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00005', NULL, 1),
(14, 'Lemari Alat Bahan', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00006', NULL, 1),
(15, 'Lemari Arsip', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00007', NULL, 1),
(16, 'Loker Guru', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00008', NULL, 1),
(17, 'Meja Guru', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00009', NULL, 1),
(18, 'Meja Kerja', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00010', NULL, 1),
(19, 'Meja Komputer', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00011', NULL, 1),
(20, 'Meja Produksi', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00012', NULL, 1),
(21, 'Meja Rapat', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00013', NULL, 1),
(22, 'Meja Siswa', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00014', NULL, 1),
(23, 'Papan Pengumuman', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00015', NULL, 1),
(24, 'papan tulis', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00016', NULL, 1),
(25, 'Rak Piala', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00017', NULL, 1),
(26, 'Sofa Tamu', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00018', NULL, 1),
(28, 'Ranjang dan kasur', 'Baik', 'Stok awal sesuai permintaan.', 10, 2, '2026-11-16', 4, 'AUTO-CAT-00020', NULL, 1),
(31, 'Interactive Flat Panel', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00023', NULL, 1),
(34, 'Dispenser', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00026', NULL, 1),
(35, 'Kipas Angin/ Exhaust fan', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00027', NULL, 1),
(36, 'Layar Proyektor', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00028', NULL, 1),
(37, 'Mesin Fotokopi', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00029', NULL, 1),
(38, 'Microphone', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00030', NULL, 1),
(39, 'Mixer Suara', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00031', NULL, 1),
(40, 'Printer', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00032', NULL, 1),
(41, 'Proyektor/Infokus', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00033', NULL, 1),
(42, 'Scanner', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00034', NULL, 1),
(44, 'TV', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00036', NULL, 1),
(45, 'Sound Sistem', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00037', NULL, 1),
(46, 'Ampli Gitar', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00038', NULL, 1),
(48, 'Speaker Kecil', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00041', NULL, 1),
(49, 'Terminal stop kontak', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00042', NULL, 1),
(50, 'Komputer Akuntansi', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00043', NULL, 1),
(51, 'Komputer Animasi', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00044', NULL, 1),
(52, 'Komputer DKV', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00045', NULL, 1),
(53, 'Komputer Pemasaran', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00046', NULL, 1),
(54, 'Komputer PPLG 1', 'Rusak Ringan', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 18, 'AUTO-CAT-00047', NULL, 1),
(55, 'Komputer PPLG 2', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00048', NULL, 1),
(56, 'Komputer PPLG 3', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00049', NULL, 1),
(57, 'Komputer PPLG 4', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00050', NULL, 1),
(58, 'Komputer PPLG 5', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00051', NULL, 1),
(59, 'LapTop', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00052', NULL, 1),
(60, 'Mesin Cutting', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00053', NULL, 1),
(61, 'Mesin Laminasi', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00054', NULL, 1),
(62, 'Mesin Oven', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00055', NULL, 1),
(63, 'Mesin Pres', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00056', NULL, 1),
(64, 'Printer 3D', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00057', NULL, 1),
(65, 'Printer DTF', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00058', NULL, 1),
(66, 'Printer Foto', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00059', NULL, 1),
(67, 'Printer Sublim', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00060', NULL, 1),
(68, 'Printer UV', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00061', NULL, 1),
(69, 'Stabilizer/ PSU', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00062', NULL, 1),
(70, 'Stand Lighting', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00063', NULL, 1),
(71, 'Stand Microphone', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00064', NULL, 1),
(72, 'Tripod Camera', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00065', NULL, 1),
(73, 'Kamera MD 1000', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00066', NULL, 1),
(74, 'Tripod Kamera', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00067', NULL, 1),
(75, 'Soft Box Standar', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00068', NULL, 1),
(76, 'Lampu LED', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00069', NULL, 1),
(77, 'Tripod Kecil', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00070', NULL, 1),
(78, 'Tuled + Bracket', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00071', NULL, 1),
(79, 'Background Hijau', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00072', NULL, 1),
(80, 'Lampu Sorot', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00073', NULL, 1),
(81, 'Komputer Lab Broadcast', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00074', NULL, 1),
(82, 'Audio Mixer', 'Baik', 'Stok awal sesuai permintaan.', 10, 1, '2026-11-16', 4, 'AUTO-CAT-00075', NULL, 1),
(83, 'Video Mixer', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00076', NULL, 1),
(84, 'Rekording Pack', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00077', NULL, 1),
(85, 'Speaker', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00078', NULL, 1),
(86, 'Telepromter', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00079', NULL, 1),
(87, 'Mic Wireless', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00080', NULL, 1),
(88, 'Kamera Mirorrless', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00081', NULL, 1),
(89, 'Drone', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00082', NULL, 1),
(90, 'kamera Digital', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00083', NULL, 1),
(91, 'Oculus', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00084', NULL, 1),
(92, 'Lampu Studi', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00085', NULL, 1),
(93, 'Kamera DSRL', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00086', NULL, 1),
(94, 'Lensa', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00087', NULL, 1),
(95, 'Lampu Studio', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00088', NULL, 1),
(96, 'Flash', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00089', NULL, 1),
(98, 'Tripod Besar', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00091', NULL, 1),
(99, 'Soft Box Besar', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00092', NULL, 1),
(100, 'Soft Box Sedang', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00093', NULL, 1),
(101, 'Komputer Mac', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00094', NULL, 1),
(102, 'hub switch', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00095', NULL, 1),
(104, 'Pan Tablet', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00097', NULL, 1),
(105, 'Server', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00098', NULL, 1),
(106, 'Access Point', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00099', NULL, 1),
(107, 'Kalkulator', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00100', NULL, 1),
(108, 'Mesin Kasir', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00101', NULL, 1),
(109, 'meja cetak sablon', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00102', NULL, 1),
(110, 'Alat Cetak Pin', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00103', NULL, 1),
(111, 'Alat Cetak Mug', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00104', NULL, 1),
(112, 'Alat musik', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00105', NULL, 1),
(113, 'Kendang', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00106', NULL, 1),
(114, 'Gamelan Degung Pelog selap Salendro', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00107', NULL, 1),
(115, 'Angklung Melodi', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00108', NULL, 1),
(116, 'Angklung Bass Partay', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00109', NULL, 1),
(117, 'Accompanyemen', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00110', NULL, 1),
(119, 'Bass Pukul', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00112', NULL, 1),
(120, 'Gambang Pengiring', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00113', NULL, 1),
(121, 'Drum', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00114', NULL, 1),
(122, 'Gitar Akustik', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00115', NULL, 1),
(123, 'Gitar Bass', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00116', NULL, 1),
(124, 'Matras', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00117', NULL, 1),
(125, 'Bola Basket', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00119', NULL, 1),
(126, 'Bola Futsal', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00120', NULL, 1),
(127, 'Net Voli', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00121', NULL, 1),
(128, 'Net Badminton', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00122', NULL, 1),
(129, 'Stik Kasti', 'Baik', 'Stok awal sesuai permintaan.', 10, 3, '2026-11-16', 4, 'AUTO-CAT-00123', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `jenis`
--

CREATE TABLE `jenis` (
  `id_jenis` int(11) NOT NULL,
  `nama_jenis` varchar(60) NOT NULL,
  `kode_jenis` varchar(10) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jenis`
--

INSERT INTO `jenis` (`id_jenis`, `nama_jenis`, `kode_jenis`, `keterangan`) VALUES
(1, 'Elektronik', 'ELK', 'Laptop, proyektor, kamera'),
(2, 'Perabot', 'PRB', 'Kursi, meja, papan tulis portabel'),
(3, 'Alat Praktik', 'ALT', 'Peralatan praktik jurusan'),
(4, 'Olahraga', 'OLR', 'Perlengkapan olahraga');

-- --------------------------------------------------------

--
-- Table structure for table `katalog_barang_pinjam`
--

CREATE TABLE `katalog_barang_pinjam` (
  `id_katalog_barang` int(11) NOT NULL,
  `kategori` varchar(60) NOT NULL,
  `nama_barang` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `katalog_barang_pinjam`
--

INSERT INTO `katalog_barang_pinjam` (`id_katalog_barang`, `kategori`, `nama_barang`) VALUES
(99, 'Alat Praktik/Lab', 'Access Point'),
(110, 'Alat Praktik/Lab', 'Accompanyemen'),
(104, 'Alat Praktik/Lab', 'Alat Cetak Mug'),
(103, 'Alat Praktik/Lab', 'Alat Cetak Pin'),
(105, 'Alat Praktik/Lab', 'Alat musik'),
(109, 'Alat Praktik/Lab', 'Angklung Bass Partay'),
(108, 'Alat Praktik/Lab', 'Angklung Melodi'),
(75, 'Alat Praktik/Lab', 'Audio Mixer'),
(90, 'Alat Praktik/Lab', 'Background'),
(72, 'Alat Praktik/Lab', 'Background Hijau'),
(112, 'Alat Praktik/Lab', 'Bass Pukul'),
(119, 'Alat Praktik/Lab', 'Bola Basket'),
(120, 'Alat Praktik/Lab', 'Bola Futsal'),
(118, 'Alat Praktik/Lab', 'Bola Voli'),
(111, 'Alat Praktik/Lab', 'Cuk'),
(82, 'Alat Praktik/Lab', 'Drone'),
(114, 'Alat Praktik/Lab', 'Drum'),
(89, 'Alat Praktik/Lab', 'Flash'),
(113, 'Alat Praktik/Lab', 'Gambang Pengiring'),
(107, 'Alat Praktik/Lab', 'Gamelan Degung Pelog selap Salendro'),
(115, 'Alat Praktik/Lab', 'Gitar Akustik'),
(116, 'Alat Praktik/Lab', 'Gitar Bass'),
(96, 'Alat Praktik/Lab', 'Haedphone'),
(95, 'Alat Praktik/Lab', 'hub switch'),
(100, 'Alat Praktik/Lab', 'Kalkulator'),
(83, 'Alat Praktik/Lab', 'kamera Digital'),
(86, 'Alat Praktik/Lab', 'Kamera DSRL'),
(66, 'Alat Praktik/Lab', 'Kamera MD 1000'),
(81, 'Alat Praktik/Lab', 'Kamera Mirorrless'),
(106, 'Alat Praktik/Lab', 'Kendang'),
(43, 'Alat Praktik/Lab', 'Komputer Akuntansi'),
(44, 'Alat Praktik/Lab', 'Komputer Animasi'),
(45, 'Alat Praktik/Lab', 'Komputer DKV'),
(74, 'Alat Praktik/Lab', 'Komputer Lab Broadcast'),
(94, 'Alat Praktik/Lab', 'Komputer Mac'),
(46, 'Alat Praktik/Lab', 'Komputer Pemasaran'),
(47, 'Alat Praktik/Lab', 'Komputer PPLG 1'),
(48, 'Alat Praktik/Lab', 'Komputer PPLG 2'),
(49, 'Alat Praktik/Lab', 'Komputer PPLG 3'),
(50, 'Alat Praktik/Lab', 'Komputer PPLG 4'),
(51, 'Alat Praktik/Lab', 'Komputer PPLG 5'),
(69, 'Alat Praktik/Lab', 'Lampu LED'),
(73, 'Alat Praktik/Lab', 'Lampu Sorot'),
(85, 'Alat Praktik/Lab', 'Lampu Studi'),
(88, 'Alat Praktik/Lab', 'Lampu Studio'),
(52, 'Alat Praktik/Lab', 'LapTop'),
(87, 'Alat Praktik/Lab', 'Lensa'),
(117, 'Alat Praktik/Lab', 'Matras'),
(102, 'Alat Praktik/Lab', 'meja cetak sablon'),
(53, 'Alat Praktik/Lab', 'Mesin Cutting'),
(101, 'Alat Praktik/Lab', 'Mesin Kasir'),
(54, 'Alat Praktik/Lab', 'Mesin Laminasi'),
(55, 'Alat Praktik/Lab', 'Mesin Oven'),
(56, 'Alat Praktik/Lab', 'Mesin Pres'),
(80, 'Alat Praktik/Lab', 'Mic Wireless'),
(122, 'Alat Praktik/Lab', 'Net Badminton'),
(121, 'Alat Praktik/Lab', 'Net Voli'),
(84, 'Alat Praktik/Lab', 'Oculus'),
(97, 'Alat Praktik/Lab', 'Pan Tablet'),
(57, 'Alat Praktik/Lab', 'Printer 3D'),
(58, 'Alat Praktik/Lab', 'Printer DTF'),
(59, 'Alat Praktik/Lab', 'Printer Foto'),
(60, 'Alat Praktik/Lab', 'Printer Sublim'),
(61, 'Alat Praktik/Lab', 'Printer UV'),
(77, 'Alat Praktik/Lab', 'Rekording Pack'),
(98, 'Alat Praktik/Lab', 'Server'),
(92, 'Alat Praktik/Lab', 'Soft Box Besar'),
(93, 'Alat Praktik/Lab', 'Soft Box Sedang'),
(68, 'Alat Praktik/Lab', 'Soft Box Standar'),
(78, 'Alat Praktik/Lab', 'Speaker'),
(62, 'Alat Praktik/Lab', 'Stabilizer/ PSU'),
(63, 'Alat Praktik/Lab', 'Stand Lighting'),
(64, 'Alat Praktik/Lab', 'Stand Microphone'),
(123, 'Alat Praktik/Lab', 'Stik Kasti'),
(79, 'Alat Praktik/Lab', 'Telepromter'),
(91, 'Alat Praktik/Lab', 'Tripod Besar'),
(65, 'Alat Praktik/Lab', 'Tripod Camera'),
(67, 'Alat Praktik/Lab', 'Tripod Kamera'),
(70, 'Alat Praktik/Lab', 'Tripod Kecil'),
(71, 'Alat Praktik/Lab', 'Tuled + Bracket'),
(76, 'Alat Praktik/Lab', 'Video Mixer'),
(24, 'Elektronik', 'AC'),
(38, 'Elektronik', 'Ampli Gitar'),
(25, 'Elektronik', 'CCTV'),
(26, 'Elektronik', 'Dispenser'),
(40, 'Elektronik', 'Handphone'),
(23, 'Elektronik', 'Interactive Flat Panel'),
(27, 'Elektronik', 'Kipas Angin/ Exhaust fan'),
(28, 'Elektronik', 'Layar Proyektor'),
(29, 'Elektronik', 'Mesin Fotokopi'),
(30, 'Elektronik', 'Microphone'),
(31, 'Elektronik', 'Mixer Suara'),
(32, 'Elektronik', 'Printer'),
(33, 'Elektronik', 'Proyektor/Infokus'),
(34, 'Elektronik', 'Scanner'),
(37, 'Elektronik', 'Sound Sistem'),
(41, 'Elektronik', 'Speaker Kecil'),
(39, 'Elektronik', 'Speaker Portable'),
(35, 'Elektronik', 'Telepon Kantor'),
(42, 'Elektronik', 'Terminal stop kontak'),
(36, 'Elektronik', 'TV'),
(1, 'Perabotan', 'Akrilik Nama'),
(2, 'Perabotan', 'Filling cabinet'),
(3, 'Perabotan', 'Kursi Guru'),
(4, 'Perabotan', 'Kursi Rapat'),
(5, 'Perabotan', 'Kursi siswa'),
(22, 'Perabotan', 'Lap pel'),
(6, 'Perabotan', 'Lemari Alat Bahan'),
(7, 'Perabotan', 'Lemari Arsip'),
(8, 'Perabotan', 'Loker Guru'),
(9, 'Perabotan', 'Meja Guru'),
(10, 'Perabotan', 'Meja Kerja'),
(11, 'Perabotan', 'Meja Komputer'),
(12, 'Perabotan', 'Meja Produksi'),
(13, 'Perabotan', 'Meja Rapat'),
(14, 'Perabotan', 'Meja Siswa'),
(15, 'Perabotan', 'Papan Pengumuman'),
(16, 'Perabotan', 'papan tulis'),
(17, 'Perabotan', 'Rak Piala'),
(20, 'Perabotan', 'Ranjang dan kasur'),
(21, 'Perabotan', 'Sapu'),
(18, 'Perabotan', 'Sofa Tamu'),
(19, 'Perabotan', 'Tong Sampah');

-- --------------------------------------------------------

--
-- Table structure for table `level`
--

CREATE TABLE `level` (
  `id_level` int(11) NOT NULL,
  `nama_level` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level`
--

INSERT INTO `level` (`id_level`, `nama_level`) VALUES
(1, 'administrator'),
(2, 'operator');

-- --------------------------------------------------------

--
-- Table structure for table `pegawai`
--

CREATE TABLE `pegawai` (
  `id_pegawai` int(11) NOT NULL,
  `nama_pegawai` varchar(100) NOT NULL,
  `nip` varchar(30) NOT NULL,
  `jabatan` varchar(60) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pegawai`
--

INSERT INTO `pegawai` (`id_pegawai`, `nama_pegawai`, `nip`, `jabatan`, `alamat`, `aktif`) VALUES
(1, 'Budi Santoso', '198501012010011001', 'Guru Produktif RPL', 'Padalarang', 1),
(2, 'Siti Aminah', '199002152015022002', 'Guru Matematika', 'Cimahi', 1),
(3, 'Rahmat Hidayat', '198803202012011003', 'Kepala Lab TKJ', 'Bandung Barat', 1),
(4, 'Dewi Lestari', '199207102018022001', 'Staf Tata Usaha', 'Padalarang', 1),
(5, 'Agus Salim', '197905051999031004', 'Mantan Wakasek Kesiswaan', 'Cimahi', 0),
(6, 'ABDILAH MALIK AL - JABBAR', '2627020070001', 'Siswa - X PPLG 1', NULL, 1),
(7, 'ADITYA MUSYAFFA', '2627020070003', 'Siswa - X PPLG 1', NULL, 1),
(8, 'ALLIF FAKHRIZAL', '2627020070004', 'Siswa - X PPLG 1', NULL, 1),
(9, 'AMSHOR MUSTOFA ILHAM', '2627020070005', 'Siswa - X PPLG 1', NULL, 1),
(10, 'ANITA SITI NURJANAH', '2627020070006', 'Siswa - X PPLG 1', NULL, 1),
(11, 'DAVA HERDIANSYAH', '2627020070011', 'Siswa - X PPLG 1', NULL, 1),
(12, 'DICKY MAULANA', '2627020070013', 'Siswa - X PPLG 1', NULL, 1),
(13, 'EVIAN AFRI ANSYAH', '2627020070015', 'Siswa - X PPLG 1', NULL, 1),
(14, 'FADYL MAULANA YUSUF', '2627020070016', 'Siswa - X PPLG 1', NULL, 1),
(15, 'FAISAL RAMDHANI', '2627020070018', 'Siswa - X PPLG 1', NULL, 1),
(16, 'HAFIZHAN HANIF ABDULLAH', '2627020070020', 'Siswa - X PPLG 1', NULL, 1),
(17, 'KEANDRA FADHIL AZFAR', '2627020070023', 'Siswa - X PPLG 1', NULL, 1),
(18, 'KHEIDIRA AHMAD FAJAR', '2627020070025', 'Siswa - X PPLG 1', NULL, 1),
(19, 'LEANDRA MAHAWIRA ARDHANI', '2627020070026', 'Siswa - X PPLG 1', NULL, 1),
(20, 'M. IRVAN ABDILLAH', '2627020070027', 'Siswa - X PPLG 1', NULL, 1),
(21, 'MUHAMAD RAYTAMA DERALD', '2627020070029', 'Siswa - X PPLG 1', NULL, 1),
(22, 'MUHAMAD RYFQY', '2627020070030', 'Siswa - X PPLG 1', NULL, 1),
(23, 'MUHAMMAD DENDRA FADHILAH', '2627020070031', 'Siswa - X PPLG 1', NULL, 1),
(24, 'MUHAMMAD JULIYANSYAH', '2627020070034', 'Siswa - X PPLG 1', NULL, 1),
(25, 'MUHAMMAD RAFFY ALBANI', '2627020070035', 'Siswa - X PPLG 1', NULL, 1),
(26, 'PASYA NURUL OCTAVIANI', '2627020070036', 'Siswa - X PPLG 1', NULL, 1),
(27, 'RAFKA RADHITYA ANUGRAH', '2627020070039', 'Siswa - X PPLG 1', NULL, 1),
(28, 'RAKA PUTRA ADNESA', '2627020070041', 'Siswa - X PPLG 1', NULL, 1),
(29, 'REFKY ARDIANSYAH', '2627020070042', 'Siswa - X PPLG 1', NULL, 1),
(30, 'REGINA NIRWASITA PUTRI DAIMAN', '2627020070043', 'Siswa - X PPLG 1', NULL, 1),
(31, 'SAYYIDAH KAYYIS FADHILAH', '2627020070052', 'Siswa - X PPLG 1', NULL, 1),
(32, 'WIDYAN DAFFA HERMAWAN', '2627020070054', 'Siswa - X PPLG 1', NULL, 1),
(36, 'ABIYYU FADHLURRAHMAN', '2627020070002', 'Siswa - X PPLG 2', NULL, 1),
(37, 'ARYA NURIZKY WIRAYUDA', '2627020070007', 'Siswa - X PPLG 2', NULL, 1),
(38, 'AULIA PUTRI PRATIWI', '2627020070008', 'Siswa - X PPLG 2', NULL, 1),
(39, 'AZZAHRA VARIZTHA ZULKARNAEN', '2627020070009', 'Siswa - X PPLG 2', NULL, 1),
(40, 'CEP PAHMI MUHAMMAD ARYA', '2627020070010', 'Siswa - X PPLG 2', NULL, 1),
(41, 'DAVID VILA SEPTIAWAN', '2627020070012', 'Siswa - X PPLG 2', NULL, 1),
(42, 'DWI CAHAYA KUSUMAH', '2627020070014', 'Siswa - X PPLG 2', NULL, 1),
(43, 'FAHMI NUR AHZA', '2627020070017', 'Siswa - X PPLG 2', NULL, 1),
(44, 'HAFIZH FADHILLAH', '2627020070019', 'Siswa - X PPLG 2', NULL, 1),
(45, 'HUSNA KHOIRUNNISA', '2627020070021', 'Siswa - X PPLG 2', NULL, 1),
(46, 'JALU RAMDHAN AS-SYAF', '2627020070022', 'Siswa - X PPLG 2', NULL, 1),
(47, 'KEYRAN BILAL ASHARIANSYAH', '2627020070024', 'Siswa - X PPLG 2', NULL, 1),
(48, 'MUHAMAD PADHIL NURPAJRI', '2627020070028', 'Siswa - X PPLG 2', NULL, 1),
(49, 'MUHAMMAD FADLAN AZZAM', '2627020070032', 'Siswa - X PPLG 2', NULL, 1),
(50, 'MUHAMMAD FARHAN SEPTIAN ANDARI', '2627020070033', 'Siswa - X PPLG 2', NULL, 1),
(51, 'PUTRA ADITYA', '2627020070037', 'Siswa - X PPLG 2', NULL, 1),
(52, 'RAFFA SYAIBIL AXCEL', '2627020070038', 'Siswa - X PPLG 2', NULL, 1),
(53, 'RAIKHAN RIZKY PUTRA HERDIANA', '2627020070040', 'Siswa - X PPLG 2', NULL, 1),
(54, 'REZKY MAULANA', '2627020070044', 'Siswa - X PPLG 2', NULL, 1),
(55, 'RIKY NURBAGJA', '2627020070045', 'Siswa - X PPLG 2', NULL, 1),
(56, 'RIRIN ANJANI', '2627020070046', 'Siswa - X PPLG 2', NULL, 1),
(57, 'RIZKI PRASETYA HERMANSYAH', '2627020070047', 'Siswa - X PPLG 2', NULL, 1),
(58, 'RIZKY ALBYANNUR RAMADHAN', '2627020070048', 'Siswa - X PPLG 2', NULL, 1),
(59, 'SALSABILA NURAZIZAH', '2627020070049', 'Siswa - X PPLG 2', NULL, 1),
(60, 'SARAH AMELIA', '2627020070050', 'Siswa - X PPLG 2', NULL, 1),
(61, 'SATRIYA RAMADHANA PERMANA PUTRA', '2627020070051', 'Siswa - X PPLG 2', NULL, 1),
(62, 'TRIANA ANGGRA NUR BAGJA', '2627020070053', 'Siswa - X PPLG 2', NULL, 1),
(63, 'ALVIRA KAVALERA PONIMAN', '2627017084003', 'Siswa - X DKV 1', NULL, 1),
(64, 'ANNISA NUR FIRDAUS', '2627017084004', 'Siswa - X DKV 1', NULL, 1),
(65, 'ASHITA KIRANA DEWI', '2627017084007', 'Siswa - X DKV 1', NULL, 1),
(66, 'ASSHIRA SONIA ZULFA', '2627017084008', 'Siswa - X DKV 1', NULL, 1),
(67, 'BOSSANOVA ALIYYU DHAKIR', '2627017084009', 'Siswa - X DKV 1', NULL, 1),
(68, 'DIMAZ WIDYAN SAWUNGSONO', '2627017084010', 'Siswa - X DKV 1', NULL, 1),
(69, 'FAWWAZ CATRA PRASETIA', '2627017084011', 'Siswa - X DKV 1', NULL, 1),
(70, 'GHANIA ALQISHA', '2627017084013', 'Siswa - X DKV 1', NULL, 1),
(71, 'JASMINE RAFIFAH ROMADHONI', '2627017084016', 'Siswa - X DKV 1', NULL, 1),
(72, 'KEISYA AZ-ZAHIRA PUTRI NURDIMAJA', '2627017084018', 'Siswa - X DKV 1', NULL, 1),
(73, 'LUKI RAMDANI', '2627017084020', 'Siswa - X DKV 1', NULL, 1),
(74, 'MUHAMAD DIFKA PRADIPTA', '2627017084022', 'Siswa - X DKV 1', NULL, 1),
(75, 'MULYANA MALIK MATHOR RIZKI', '2627017084023', 'Siswa - X DKV 1', NULL, 1),
(76, 'NURIL SYA\'BANI', '2627017084028', 'Siswa - X DKV 1', NULL, 1),
(77, 'REVAN PERMANA AZIZ', '2627017084032', 'Siswa - X DKV 1', NULL, 1),
(78, 'RIFKI ADIKA WIDI', '2627017084034', 'Siswa - X DKV 1', NULL, 1),
(79, 'RIZKI MUKTI FADILLAH', '2627017084035', 'Siswa - X DKV 1', NULL, 1),
(80, 'SABRINA ANNISA SETIAWATI', '2627017084036', 'Siswa - X DKV 1', NULL, 1),
(81, 'SASKIA NURUL BAETI', '26270170840037', 'Siswa - X DKV 1', NULL, 1),
(82, 'ZOANNA QUROTA YASIN', '2627017084040', 'Siswa - X DKV 1', NULL, 1),
(83, 'AINUN NADIN ALHAWARIZMI KOSASIH', '2627017084001', 'Siswa - X DKV 2', NULL, 1),
(84, 'ALMIRA QUENTIN RENATA', '2627017084002', 'Siswa - X DKV 2', NULL, 1),
(85, 'ARKIA ABDAN SYAKIR', '2627017084005', 'Siswa - X DKV 2', NULL, 1),
(86, 'ARYA DWI PERMADI', '2627017084006', 'Siswa - X DKV 2', NULL, 1),
(87, 'GALLAN MAULEE ADAM', '2627017084012', 'Siswa - X DKV 2', NULL, 1),
(88, 'HAFIZH DZIKRA AL GHIFARI', '2627017084014', 'Siswa - X DKV 2', NULL, 1),
(89, 'IRHAM PUTRA HAMDANI', '2627017084015', 'Siswa - X DKV 2', NULL, 1),
(90, 'KARISSA FIDRIANI', '2627017084017', 'Siswa - X DKV 2', NULL, 1),
(91, 'LEIA ZAYDANISH', '2627017084019', 'Siswa - X DKV 2', NULL, 1),
(92, 'MOHAMAD HARISTIA RIYADI', '2627017084021', 'Siswa - X DKV 2', NULL, 1),
(93, 'NABILAH KHOIRUNNISA AZZAHRO', '2627017084024', 'Siswa - X DKV 2', NULL, 1),
(94, 'NAIMA NUR KHALIZHA', '2627017084025', 'Siswa - X DKV 2', NULL, 1),
(95, 'NAUFAL MARI FAUZI', '2627017084026', 'Siswa - X DKV 2', NULL, 1),
(96, 'NAYRA SHYFA AULIYA', '2627017084027', 'Siswa - X DKV 2', NULL, 1),
(97, 'OKTAFIANTO NUGRAHA', '2627017084029', 'Siswa - X DKV 2', NULL, 1),
(98, 'QIRANIA HAFSA ARUNI KUSWANDI', '2627017084030', 'Siswa - X DKV 2', NULL, 1),
(99, 'RAKA NURBAGJA', '2627017084031', 'Siswa - X DKV 2', NULL, 1),
(100, 'REZA ADITYA', '2627017084033', 'Siswa - X DKV 2', NULL, 1),
(101, 'WENDY KUSNADI', '2627017084038', 'Siswa - X DKV 2', NULL, 1),
(102, 'YOGA PRATAMA PUTRA', '2627017084039', 'Siswa - X DKV 2', NULL, 1),
(103, 'AZKA THALITA REZKIA', '2627012119002', 'Siswa - X AKT 1', NULL, 1),
(104, 'BELA SITI SANIA', '2627012119003', 'Siswa - X AKT 1', NULL, 1),
(105, 'GINA NUR INDAH LESTARI', '2627012119010', 'Siswa - X AKT 1', NULL, 1),
(106, 'INGE MILANDUNKA ROHMAH', '2627012119013', 'Siswa - X AKT 1', NULL, 1),
(107, 'KAMILA NUR IZZATI', '2627012119014', 'Siswa - X AKT 1', NULL, 1),
(108, 'KEISYA NURUL KHOTIMAH', '2627012119015', 'Siswa - X AKT 1', NULL, 1),
(109, 'KHAILA ADELIA PUTRI', '2627012119016', 'Siswa - X AKT 1', NULL, 1),
(110, 'KIRANA ANDRIANI SETIAWAN', '2627012119017', 'Siswa - X AKT 1', NULL, 1),
(111, 'N. NAJWA MARAH SOLIHAH', '2627012119019', 'Siswa - X AKT 1', NULL, 1),
(112, 'NAZILA NAURA YASMIN', '2627012119023', 'Siswa - X AKT 1', NULL, 1),
(113, 'NOVIA NURJANAH', '2627012119024', 'Siswa - X AKT 1', NULL, 1),
(114, 'NURIQHRA RAMBU MASHAE', '2627012119025', 'Siswa - X AKT 1', NULL, 1),
(115, 'ORYNA SYATIFA', '2627012119026', 'Siswa - X AKT 1', NULL, 1),
(116, 'RAHMANISSA DESTIANTI', '2627012119027', 'Siswa - X AKT 1', NULL, 1),
(117, 'RAMADHAN MULIA WARDHANI', '2627012119028', 'Siswa - X AKT 1', NULL, 1),
(118, 'RIZKY JONI SAPUTRA', '2627012119031', 'Siswa - X AKT 1', NULL, 1),
(119, 'SANDRIKA ALPRIANITRI', '2627012119033', 'Siswa - X AKT 1', NULL, 1),
(120, 'SASKIA AMELIA', '2627012119034', 'Siswa - X AKT 1', NULL, 1),
(121, 'SHALLYA ARNI', '2627012119036', 'Siswa - X AKT 1', NULL, 1),
(122, 'SHIERLEY SILAWARTI', '2627012119038', 'Siswa - X AKT 1', NULL, 1),
(123, 'SHOFA FUADI', '2627012119039', 'Siswa - X AKT 1', NULL, 1),
(124, 'SILVI RAHMAWATI', '2627012119040', 'Siswa - X AKT 1', NULL, 1),
(125, 'SILVIA ARDIANTI', '2627012119042', 'Siswa - X AKT 1', NULL, 1),
(126, 'TASYA MAHARANI', '2627012119047', 'Siswa - X AKT 1', NULL, 1),
(127, 'ARLITA SITI FATMAH', '2627012119001', 'Siswa - X AKT 2', NULL, 1),
(128, 'CAHYANI APRILIYANTI', '2627012119004', 'Siswa - X AKT 2', NULL, 1),
(129, 'CILA PUTRI PRASETIO', '2627012119005', 'Siswa - X AKT 2', NULL, 1),
(130, 'CYNARA SYIFA ELYSIA', '2627012119006', 'Siswa - X AKT 2', NULL, 1),
(131, 'FAKHIRA HAFIZA QURANIQUE', '2627012119007', 'Siswa - X AKT 2', NULL, 1),
(132, 'FATHIYA NUR HASANAH', '2627012119008', 'Siswa - X AKT 2', NULL, 1),
(133, 'FHATYA NUR ISMI', '2627012119009', 'Siswa - X AKT 2', NULL, 1),
(134, 'ILONA BELVA KHANSA', '2627012119011', 'Siswa - X AKT 2', NULL, 1),
(135, 'INDAH EKA PUTRI', '2627012119012', 'Siswa - X AKT 2', NULL, 1),
(136, 'LIDA FEBRIANI', '2627012119018', 'Siswa - X AKT 2', NULL, 1),
(137, 'N. NAZLA AMALIA NUROHMAH', '2627012119020', 'Siswa - X AKT 2', NULL, 1),
(138, 'NADHIRA KIRANASYA AZZAHRA', '2627012119021', 'Siswa - X AKT 2', NULL, 1),
(139, 'NAJWA NUR KARIMA', '2627012119022', 'Siswa - X AKT 2', NULL, 1),
(140, 'RIENETTA HUSNA PRIMA', '2627012119029', 'Siswa - X AKT 2', NULL, 1),
(141, 'RISTA NAURA HIDAYAT', '2627012119030', 'Siswa - X AKT 2', NULL, 1),
(142, 'SALWA SUMIYATI', '2627012119032', 'Siswa - X AKT 2', NULL, 1),
(143, 'SHAKILA SYAFITRI', '2627012119035', 'Siswa - X AKT 2', NULL, 1),
(144, 'SHAUMMI AGUSTINA', '2627012119037', 'Siswa - X AKT 2', NULL, 1),
(145, 'SILVI SRI RAHAYU', '2627012119041', 'Siswa - X AKT 2', NULL, 1),
(146, 'SILVIA ZAHRA', '2627012119043', 'Siswa - X AKT 2', NULL, 1),
(147, 'SITI MARYAM', '2627012119044', 'Siswa - X AKT 2', NULL, 1),
(148, 'SITI SARAH', '2627012119045', 'Siswa - X AKT 2', NULL, 1),
(149, 'SYABNA DIVA MAORINE', '2627012119046', 'Siswa - X AKT 2', NULL, 1),
(150, 'ZAHIRA NUR AZIZAH', '2627012119048', 'Siswa - X AKT 2', NULL, 1),
(151, 'ABBY MUHAMMAD RAFI\'U', '2526019070001', 'Siswa - XI RPL 1', NULL, 1),
(152, 'AKBAR FACHREZA WILYAS', '2526019070002', 'Siswa - XI RPL 1', NULL, 1),
(153, 'ALVINO NOUFAL SANJAYA', '2526019070006', 'Siswa - XI RPL 1', NULL, 1),
(154, 'ANDIENI SITI KHUMAIROH', '2526019070007', 'Siswa - XI RPL 1', NULL, 1),
(155, 'ARIFAL GHIFARI BUDIMAN', '2526019070009', 'Siswa - XI RPL 1', NULL, 1),
(156, 'BINTANG MARTYANA DUTA PUTERA', '2526019070010', 'Siswa - XI RPL 1', NULL, 1),
(157, 'CHERILLA AMNATI ISTIQOMAH', '2526019070012', 'Siswa - XI RPL 1', NULL, 1),
(158, 'CINDY RAHAYU', '2526019070013', 'Siswa - XI RPL 1', NULL, 1),
(159, 'DUDI SUPARJI', '2526019070047', 'Siswa - XI RPL 1', NULL, 1),
(160, 'FAHMI ADHARI AKBAR', '2526019070015', 'Siswa - XI RPL 1', NULL, 1),
(161, 'FAHRUL ROZI ARDIANSYAH', '2526019070016', 'Siswa - XI RPL 1', NULL, 1),
(162, 'FAUZAN RASYID SUMARNA', '2526019070018', 'Siswa - XI RPL 1', NULL, 1),
(163, 'M. RAFQA HIDAYAT', '2526019070022', 'Siswa - XI RPL 1', NULL, 1),
(164, 'MANIQ DWI PRADITA', '2526019070025', 'Siswa - XI RPL 1', NULL, 1),
(165, 'MIRZA LUTFIL HADI SUSILA', '2526019070026', 'Siswa - XI RPL 1', NULL, 1),
(166, 'MOCHAMAD BIMA PUTRA RAMADHAN', '2526019070027', 'Siswa - XI RPL 1', NULL, 1),
(167, 'MUHAMMAD AL FARIZI A H', '2526019070029', 'Siswa - XI RPL 1', NULL, 1),
(168, 'MUHAMMAD FAUZAN MA\'RUF', '2526019070030', 'Siswa - XI RPL 1', NULL, 1),
(169, 'MUHAMMAD IHSAN SUPRIADI', '2526019070031', 'Siswa - XI RPL 1', NULL, 1),
(170, 'NIDA FAUZIYAH INDIRAGESTY', '2526019070034', 'Siswa - XI RPL 1', NULL, 1),
(171, 'PRANATA JAYA HARTATO', '2526019070035', 'Siswa - XI RPL 1', NULL, 1),
(172, 'RISQI MUHAMAD PADHILAH', '2526019070040', 'Siswa - XI RPL 1', NULL, 1),
(173, 'TEGAR PERMANA', '2526019070043', 'Siswa - XI RPL 1', NULL, 1),
(174, 'ZIYAD AKBAR PUTRA', '2526019070046', 'Siswa - XI RPL 1', NULL, 1),
(175, 'ARDI FIRMANSYAH', '2526019070049', 'Siswa - XI RPL 1', NULL, 1),
(176, 'AL FIKRI NUR TAWAB', '2526019070003', 'Siswa - XI RPL 2', NULL, 1),
(177, 'ALDI NUROHMAN', '2526019070004', 'Siswa - XI RPL 2', NULL, 1),
(178, 'ALFIN AL GHAZALI', '2526019070005', 'Siswa - XI RPL 2', NULL, 1),
(179, 'ANDIKA ATHA FADHLULLAH', '2526019070008', 'Siswa - XI RPL 2', NULL, 1),
(180, 'BUSTAN NUR WILDAN', '2526019070011', 'Siswa - XI RPL 2', NULL, 1),
(181, 'DARIN MIS\'AN', '2526019070014', 'Siswa - XI RPL 2', NULL, 1),
(182, 'FAUZAN MAULANA', '2526019070017', 'Siswa - XI RPL 2', NULL, 1),
(183, 'JIHAN NUR AZIZAH', '2526019070019', 'Siswa - XI RPL 2', NULL, 1),
(184, 'KHOERUL ARKA MAULANA', '2526019070020', 'Siswa - XI RPL 2', NULL, 1),
(185, 'LUTFAN BAEHAQI', '2526019070021', 'Siswa - XI RPL 2', NULL, 1),
(186, 'M. RIFQY HIDAYAT', '2526019070023', 'Siswa - XI RPL 2', NULL, 1),
(187, 'MANGGALA WIRAFITRIAWAN', '2526019070024', 'Siswa - XI RPL 2', NULL, 1),
(188, 'MUHAMAD ADITYA MUHTAR', '2526019070028', 'Siswa - XI RPL 2', NULL, 1),
(189, 'MUHAMMAD NUFA AL- QALAMI', '2526019070032', 'Siswa - XI RPL 2', NULL, 1),
(190, 'NABILA HAIFA HADIAN', '2526019070033', 'Siswa - XI RPL 2', NULL, 1),
(191, 'RASYA PUTRA ANDIKA MULYANA', '2526019070036', 'Siswa - XI RPL 2', NULL, 1),
(192, 'RASYA RAHADIAN NIJAR', '2526019070037', 'Siswa - XI RPL 2', NULL, 1),
(193, 'RIFKY ELDIYAN PUTRA', '2526019070039', 'Siswa - XI RPL 2', NULL, 1),
(194, 'RIZKY MAULANA NUR HIDAYAT', '2526019070041', 'Siswa - XI RPL 2', NULL, 1),
(195, 'SABIAN AL FIQH', '2526019070042', 'Siswa - XI RPL 2', NULL, 1),
(196, 'WIBI YUDA PRATAMA', '2526019070044', 'Siswa - XI RPL 2', NULL, 1),
(197, 'WILDAN GALIH', '2526019070045', 'Siswa - XI RPL 2', NULL, 1),
(198, 'MUHAMAD ADLI MAULANA', '2526019070048', 'Siswa - XI RPL 2', NULL, 1),
(199, 'ALFARINI NUR AULIA', '2526016084001', 'Siswa - XI DKV', NULL, 1),
(200, 'ANGGRAENI PUSPITA DEWI', '2526016084002', 'Siswa - XI DKV', NULL, 1),
(201, 'ARFA RIZKY RADITYA RUKANDI', '2526016084003', 'Siswa - XI DKV', NULL, 1),
(202, 'ATHALAH SAKHA PUTRA ANDRIANSYAH', '2526016084004', 'Siswa - XI DKV', NULL, 1),
(203, 'AZRIL MUHAMMAD SOFYAN TSAURI', '2526016084005', 'Siswa - XI DKV', NULL, 1),
(204, 'BILAL BAIHAKI', '2526016084006', 'Siswa - XI DKV', NULL, 1),
(205, 'BILLY AL MUGNI DERMAWAN', '2526016084007', 'Siswa - XI DKV', NULL, 1),
(206, 'DEVINA SHEYLA RAMLAN', '2526016084008', 'Siswa - XI DKV', NULL, 1),
(207, 'FAWAZ AZFI ABDILLAH', '2526016084009', 'Siswa - XI DKV', NULL, 1),
(208, 'HILMY HAFID AMRULLOH', '2526016084010', 'Siswa - XI DKV', NULL, 1),
(209, 'MUHAMAD ARKAN MONTERDY', '2526016084011', 'Siswa - XI DKV', NULL, 1),
(210, 'MUHAMAD NAZRIL HERMANTO', '2526016084012', 'Siswa - XI DKV', NULL, 1),
(211, 'MUHAMAD ZAHRAN ALI', '2526016084013', 'Siswa - XI DKV', NULL, 1),
(212, 'MUHAMMAD TAUFIK', '2526016084014', 'Siswa - XI DKV', NULL, 1),
(213, 'MUHAMMAD WILLY ADRIAN', '2526016084022', 'Siswa - XI DKV', NULL, 1),
(214, 'RAFFI ZAENAL PRATAMA', '2526016084015', 'Siswa - XI DKV', NULL, 1),
(215, 'RIZKY NUR FADILAH', '2526016084017', 'Siswa - XI DKV', NULL, 1),
(216, 'SITI FAUZIAH', '2526016084018', 'Siswa - XI DKV', NULL, 1),
(217, 'WAROKA LAINATUSYIFA', '2526016084019', 'Siswa - XI DKV', NULL, 1),
(218, 'YARA ANAMI ALIFA', '2526016084020', 'Siswa - XI DKV', NULL, 1),
(219, 'ZULFIKRI HAMDANI MAHENDRA', '2526016084021', 'Siswa - XI DKV', NULL, 1),
(220, 'ANINDYA RANA MALAIKA', '2526016073001', 'Siswa - XI ANIMASI', NULL, 1),
(221, 'JAUZA SAJIDA ABDIA RAHIM', '2526016073002', 'Siswa - XI ANIMASI', NULL, 1),
(222, 'M. ALFARIZI LILFIRDAUS', '2526016073003', 'Siswa - XI ANIMASI', NULL, 1),
(223, 'NADILA DIANA PUTRI', '2526016073004', 'Siswa - XI ANIMASI', NULL, 1),
(224, 'NAUFAL AKBAR ALFAROBI', '2526016073005', 'Siswa - XI ANIMASI', NULL, 1),
(225, 'NAYLA ALFIYANTI NADHIFA', '2526016073006', 'Siswa - XI ANIMASI', NULL, 1),
(226, 'SABIA NAJLA GUSHAMISA', '2526016073007', 'Siswa - XI ANIMASI', NULL, 1),
(227, 'ZAHRA GHAINA FAKHIRAH', '2526016073008', 'Siswa - XI ANIMASI', NULL, 1),
(228, 'ALFIRA NUR REVANDA', '2526011119001', 'Siswa - XI AKT', NULL, 1),
(229, 'AMEERA NAYANA HIDAYAT', '2526011119002', 'Siswa - XI AKT', NULL, 1),
(230, 'ANGGITA ANGGRAINI KUSNAINDAR', '2526011119003', 'Siswa - XI AKT', NULL, 1),
(231, 'ANISA MEYDIATI PURNAMA', '2526011119004', 'Siswa - XI AKT', NULL, 1),
(232, 'DIKRA ASSYIFA', '2526011119005', 'Siswa - XI AKT', NULL, 1),
(233, 'DINA FEBRIYANI PUTRI', '2526011119006', 'Siswa - XI AKT', NULL, 1),
(234, 'DWI OKTAVIANI', '2526011119007', 'Siswa - XI AKT', NULL, 1),
(235, 'FEBI SAPARINGGA', '2526011119008', 'Siswa - XI AKT', NULL, 1),
(236, 'INDRI ANDRIANI', '2526011119009', 'Siswa - XI AKT', NULL, 1),
(237, 'KANAYA AULIA SALSABILA', '2526011119011', 'Siswa - XI AKT', NULL, 1),
(238, 'MUHAMMAD FAUZI RIDWAN SESFAO', '2526011119012', 'Siswa - XI AKT', NULL, 1),
(239, 'MUHAMMAD ZAKY ARDIANSYAH', '2526011119024', 'Siswa - XI AKT', NULL, 1),
(240, 'NAIMA PUTRI NURDIANSYAH', '2526011119013', 'Siswa - XI AKT', NULL, 1),
(241, 'NAJWA PUSPITA', '2526011119014', 'Siswa - XI AKT', NULL, 1),
(242, 'NAYSHA NUR SANNI', '2526011119015', 'Siswa - XI AKT', NULL, 1),
(243, 'NAZWA NAFISA PUTRI', '2526011119016', 'Siswa - XI AKT', NULL, 1),
(244, 'PUSPA FEBBYANTI', '2526011119017', 'Siswa - XI AKT', NULL, 1),
(245, 'PUTRI CARRISA NUR AZMY', '2526011119018', 'Siswa - XI AKT', NULL, 1),
(246, 'RINI AGUSTIN PUTRI FIRDAUS', '2526011119019', 'Siswa - XI AKT', NULL, 1),
(247, 'SAEKA AGUSTIANI', '2526011119020', 'Siswa - XI AKT', NULL, 1),
(248, 'SYALIA ZAHRATUSYIFA', '2526011119021', 'Siswa - XI AKT', NULL, 1),
(249, 'TYARA OKTAFYA SRIWAHYUNI', '2526011119022', 'Siswa - XI AKT', NULL, 1),
(250, 'ZAHRA NUR FARIDAH', '2526011119023', 'Siswa - XI AKT', NULL, 1),
(251, 'ANGGELLA BERLIANA LUSAMBUDI', '242518070006', 'Siswa - XII RPL 1', NULL, 1),
(252, 'ARHAM YAASIN', '242518070008', 'Siswa - XII RPL 1', NULL, 1),
(253, 'ARIS ALFARIZI', '242518070009', 'Siswa - XII RPL 1', NULL, 1),
(254, 'ARYA TEJA ARUM', '242518070011', 'Siswa - XII RPL 1', NULL, 1),
(255, 'AZKY FAUZAN', '242518070012', 'Siswa - XII RPL 1', NULL, 1),
(256, 'BENJAMIN ATHALLAH MUHAMMAD', '242518070013', 'Siswa - XII RPL 1', NULL, 1),
(257, 'FAHMI UMAR SYARIF', '242518070019', 'Siswa - XII RPL 1', NULL, 1),
(258, 'FAIZATUL ILHAM', '242518070022', 'Siswa - XII RPL 1', NULL, 1),
(259, 'FARDHAN DARMAWAN SUBRATA', '242518070023', 'Siswa - XII RPL 1', NULL, 1),
(260, 'FASHA SHAUMAN RAMADHAN', '242518070024', 'Siswa - XII RPL 1', NULL, 1),
(261, 'FAUZAN ALFARIZI SETIAWAN', '242518070025', 'Siswa - XII RPL 1', NULL, 1),
(262, 'FAUZI ABDURRAHMAN AZIZ', '242518070026', 'Siswa - XII RPL 1', NULL, 1),
(263, 'GAUTAMA RAMADHAN GARNIDA', '242518070029', 'Siswa - XII RPL 1', NULL, 1),
(264, 'HAIKAL HANAN ADELA', '242518070030', 'Siswa - XII RPL 1', NULL, 1),
(265, 'HISYAM FAISAL', '242518070031', 'Siswa - XII RPL 1', NULL, 1),
(266, 'KRISNA RAMDANI', '242518070032', 'Siswa - XII RPL 1', NULL, 1),
(267, 'LUTFI FEBRIASYAH', '242518070033', 'Siswa - XII RPL 1', NULL, 1),
(268, 'MUHAMAD GHIFARI NURRAZAQ', '242518070034', 'Siswa - XII RPL 1', NULL, 1),
(269, 'NURIL HUDA', '242518070050', 'Siswa - XII RPL 1', NULL, 1),
(270, 'PRIYAMUKTI AZALIA TRIHAPSORO', '242518070040', 'Siswa - XII RPL 1', NULL, 1),
(271, 'RAFA FAUZAN PRATAMA', '242518070042', 'Siswa - XII RPL 1', NULL, 1),
(272, 'RANDY RIZKY PUTRA MAHARDIKA', '242518070044', 'Siswa - XII RPL 1', NULL, 1),
(273, 'RIZKI NOVAL SAPUTRA', '242518070045', 'Siswa - XII RPL 1', NULL, 1),
(274, 'SALMAN AVICENNA ANDRIANSAH', '242518070046', 'Siswa - XII RPL 1', NULL, 1),
(275, 'ZAKIA ABDILLAH AZ-ZAHRA', '242518070049', 'Siswa - XII RPL 1', NULL, 1),
(276, 'SYIFA NURNABILLA', '242518070048', 'Siswa - XII RPL 1', NULL, 1),
(277, 'ADID YULIANTO', '242518070002', 'Siswa - XII RPL 2', NULL, 1),
(278, 'AHMAD FAUZAN', '242518070003', 'Siswa - XII RPL 2', NULL, 1),
(279, 'ALVI FAUZAN AL-FARIZI', '242518070005', 'Siswa - XII RPL 2', NULL, 1),
(280, 'ANWAR ABDULAH', '242518070007', 'Siswa - XII RPL 2', NULL, 1),
(281, 'ARKANA ALFARIZI DEVANTARA', '242518070010', 'Siswa - XII RPL 2', NULL, 1),
(282, 'DEDIN SYAUQI AL-FAHRI', '242518070014', 'Siswa - XII RPL 2', NULL, 1),
(283, 'DETRAN RIZKI FIRMANSYAH', '242518070015', 'Siswa - XII RPL 2', NULL, 1),
(284, 'DIKA MUHAMMAD AL-FATH BAHARIZQI', '242518070016', 'Siswa - XII RPL 2', NULL, 1),
(285, 'DIMAS RIZKI MAULANA YUSUF', '242518070017', 'Siswa - XII RPL 2', NULL, 1),
(286, 'FAIQ ADILA', '242518070020', 'Siswa - XII RPL 2', NULL, 1),
(287, 'FAIRUZ AL SAHABIR AFLI', '242518070021', 'Siswa - XII RPL 2', NULL, 1),
(288, 'FEBRIAN QAIDAN NASIK', '242518070027', 'Siswa - XII RPL 2', NULL, 1),
(289, 'FIKRUL ISLAM AL AMIQ', '242518070028', 'Siswa - XII RPL 2', NULL, 1),
(290, 'MUHAMMAD ALGIFFARI RAMADHANI', '242518070035', 'Siswa - XII RPL 2', NULL, 1),
(291, 'MUHAMMAD BAGIR ALMALIQY KASTAMAN', '242518070036', 'Siswa - XII RPL 2', NULL, 1),
(292, 'MUHAMMAD DZAKIY KAMIL', '242518070037', 'Siswa - XII RPL 2', NULL, 1),
(293, 'MUHAMMAD FAUZAN NURDIANA', '242518070038', 'Siswa - XII RPL 2', NULL, 1),
(294, 'MUHAMMAD NAZRIL PUTRA NATRABU', '242518070039', 'Siswa - XII RPL 2', NULL, 1),
(295, 'RADITYA BAGAS WICAKSONO', '242518070041', 'Siswa - XII RPL 2', NULL, 1),
(296, 'RAKHA AL FARIZI ABDUL JABAR', '242518070043', 'Siswa - XII RPL 2', NULL, 1),
(297, 'SEPTIAN RAMADHAN DWIYANTO', '242518070047', 'Siswa - XII RPL 2', NULL, 1),
(298, 'AFTAH ARDIANSYAH', '242515084001', 'Siswa - XII DKV', NULL, 1),
(299, 'AGHNIYA INAYATI MUTHMAINNAH', '242515084002', 'Siswa - XII DKV', NULL, 1),
(300, 'AHMAD ZAKI', '242515084003', 'Siswa - XII DKV', NULL, 1),
(301, 'ALLIFIAN NURRAHMAN', '242515084004', 'Siswa - XII DKV', NULL, 1),
(302, 'ANDIKA ZAFIANSYAH TS', '242515084005', 'Siswa - XII DKV', NULL, 1),
(303, 'ANISA FITRI AWALIAH', '242515084006', 'Siswa - XII DKV', NULL, 1),
(304, 'IKHWAN', '242515084011', 'Siswa - XII DKV', NULL, 1),
(305, 'JAKA BANDUNG RIJKIAWAN HAMDANI', '242515084012', 'Siswa - XII DKV', NULL, 1),
(306, 'KEYLA SAFA NURHIDAYAT', '242515084013', 'Siswa - XII DKV', NULL, 1),
(307, 'MARSYA MALIK MUSAFFA', '242515084014', 'Siswa - XII DKV', NULL, 1),
(308, 'MIQDAM ALWAN AFGHANI', '242515084015', 'Siswa - XII DKV', NULL, 1),
(309, 'MUHAMMAD DIMAS DZULFITRA', '242515084016', 'Siswa - XII DKV', NULL, 1),
(310, 'MUHAMMAD RASENDRIYA ARKANA', '242515084017', 'Siswa - XII DKV', NULL, 1),
(311, 'NOVAN AFGAN SOBARIMAN', '242515084018', 'Siswa - XII DKV', NULL, 1),
(312, 'RAISA RONA ZAHRA', '242515084023', 'Siswa - XII DKV', NULL, 1),
(313, 'RAISYA AGRASANDHYA PUTRI NURDIMAJA', '242515084019', 'Siswa - XII DKV', NULL, 1),
(314, 'RAISYA ALMAHIRA', '242515084020', 'Siswa - XII DKV', NULL, 1),
(315, 'RIFKI SURYA RAMDANI', '242515084021', 'Siswa - XII DKV', NULL, 1),
(316, 'ZAHRA NURSUKMA', '242515084022', 'Siswa - XII DKV', NULL, 1),
(317, 'DELLA DWIYANI', '242515073001', 'Siswa - XII ANIMASI', NULL, 1),
(318, 'GANIS RAMBU BASAE', '242515073002', 'Siswa - XII ANIMASI', NULL, 1),
(319, 'HANA HAMIDAH', '242515073003', 'Siswa - XII ANIMASI', NULL, 1),
(320, 'KIRANA VILOVA ALWAYSHA PUTRI AJI', '242515073004', 'Siswa - XII ANIMASI', NULL, 1),
(321, 'MUHAMMAD REZKYA RAMADHAN', '242515073005', 'Siswa - XII ANIMASI', NULL, 1),
(322, 'NAISHA SAQEENA ALMAHYRA', '242515073006', 'Siswa - XII ANIMASI', NULL, 1),
(323, 'QIERA RAAIHANAH ALMAHIRA', '242515073007', 'Siswa - XII ANIMASI', NULL, 1),
(324, 'SANIA PUTRI AZIZAH', '242515073008', 'Siswa - XII ANIMASI', NULL, 1),
(325, 'SASKIA NUR\'AINI AN-NAZWA', '242515073009', 'Siswa - XII ANIMASI', NULL, 1),
(326, 'ALFI LAILA KAMIL', '242510119001', 'Siswa - XII AKT', NULL, 1),
(327, 'A\'RASYAA SYOFHA NOER AL-FIYACH', '242510119002', 'Siswa - XII AKT', NULL, 1),
(328, 'AZKHA KHAIRUNISA', '242510119003', 'Siswa - XII AKT', NULL, 1),
(329, 'CARISSA ALVIRA SABILATUL KAMILAH', '242510119004', 'Siswa - XII AKT', NULL, 1),
(330, 'DARA ANGGRAENI', '242510119005', 'Siswa - XII AKT', NULL, 1),
(331, 'DERLIN GUSVIANI', '242510119035', 'Siswa - XII AKT', NULL, 1),
(332, 'DEWI SRI LESTARI', '242510119006', 'Siswa - XII AKT', NULL, 1),
(333, 'ELVIRA PUSPITA RAHAYU', '242510119007', 'Siswa - XII AKT', NULL, 1),
(334, 'FADIA REINA RAHAYU', '242510119008', 'Siswa - XII AKT', NULL, 1),
(335, 'FAUZIA FITRI', '242510119009', 'Siswa - XII AKT', NULL, 1),
(336, 'FELICIA FEBRIANY', '242510119010', 'Siswa - XII AKT', NULL, 1),
(337, 'HANIFA SHAFA AFIFAH', '242510119011', 'Siswa - XII AKT', NULL, 1),
(338, 'INTAN FITRI RAHMALIA', '242510119012', 'Siswa - XII AKT', NULL, 1),
(339, 'IREN SUKMA WATRI RADIUS', '242510119013', 'Siswa - XII AKT', NULL, 1),
(340, 'JIHAN FAHRUNNISA APRIADI', '242510119014', 'Siswa - XII AKT', NULL, 1),
(341, 'KHEIRADYA ZHAFIRA', '242510119015', 'Siswa - XII AKT', NULL, 1),
(342, 'MAUDY CITRA APREZIA', '242510119016', 'Siswa - XII AKT', NULL, 1),
(343, 'MEYFI RIZKATIARA', '242510119017', 'Siswa - XII AKT', NULL, 1),
(344, 'MILANDA APRILYANI', '242510119018', 'Siswa - XII AKT', NULL, 1),
(345, 'NAZWA OCTAVIA SETIAWAN', '242510119019', 'Siswa - XII AKT', NULL, 1),
(346, 'QIREY APRILLIA PUTRI', '242510119020', 'Siswa - XII AKT', NULL, 1),
(347, 'REVALINA OCTA NUR FADILAH', '242510119021', 'Siswa - XII AKT', NULL, 1),
(348, 'REYSA ALVIANY NUGRAHA', '242510119022', 'Siswa - XII AKT', NULL, 1),
(349, 'RIZQY ALFAHREZA', '242510119023', 'Siswa - XII AKT', NULL, 1),
(350, 'SHANNY AULIA', '242510119024', 'Siswa - XII AKT', NULL, 1),
(351, 'SHINTYA AYU RAMADHANI', '242510119025', 'Siswa - XII AKT', NULL, 1),
(352, 'SINDI YULIANTINI', '242510119026', 'Siswa - XII AKT', NULL, 1),
(353, 'TIARA ELITYA RAMADHANI', '242510119027', 'Siswa - XII AKT', NULL, 1),
(354, 'ULA AULIA', '242510119028', 'Siswa - XII AKT', NULL, 1),
(355, 'VERA CHINTIA SARI', '242510119029', 'Siswa - XII AKT', NULL, 1),
(356, 'VINNA PLANTIKA', '242510119030', 'Siswa - XII AKT', NULL, 1),
(357, 'WULAN SALSABILAH', '242510119031', 'Siswa - XII AKT', NULL, 1),
(358, 'ZACHRANI AGUSTIN HIDAYAT', '242510119032', 'Siswa - XII AKT', NULL, 1),
(359, 'ZAHRA ANDRIANI PUTRI', '242510119033', 'Siswa - XII AKT', NULL, 1),
(360, 'ZASKIA RIZKI SYAMMI', '242510119034', 'Siswa - XII AKT', NULL, 1),
(361, 'ALIFA MUTHIA', '242507121001', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(362, 'ARKANA PUTRA PERMANA', '242507121002', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(363, 'DEVI SITI NUR AULIA', '242507121003', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(364, 'FAIRUS NADHIR AMRULLAH', '242507121005', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(365, 'LUQITA FRANSISKA PUTRI', '242507121006', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(366, 'QISTY AUNI NURRANIA', '242507121007', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(367, 'RIFKAN SURYA RAMDAN', '242507121008', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(368, 'RIKI HABIB RIZIS TS', '242507121009', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(369, 'ZAHRA PUTRI MAHARANI', '242507121010', 'Siswa - XII BISNIS DIGITAL', NULL, 1),
(371, 'rafa fauzan pratama', '001', 'XII PPLG 1', NULL, 1),
(376, 'rizki noval', '0123', 'XII PPLG 2', NULL, 1),
(377, 'daduut', '01932347', 'XI PPLG 1', NULL, 1),
(378, 'FAUZAN', '0987', 'X DKV 1', NULL, 1),
(379, 'rafa', '0876', 'xii pplg 2', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` int(11) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `batas_kembali` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `status_peminjaman` enum('Dipinjam','Dikembalikan') NOT NULL DEFAULT 'Dipinjam',
  `id_pegawai` int(11) NOT NULL,
  `id_petugas` int(11) DEFAULT NULL,
  `dicatat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `dikembalikan_pada` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id_peminjaman`, `tanggal_pinjam`, `batas_kembali`, `tanggal_kembali`, `status_peminjaman`, `id_pegawai`, `id_petugas`, `dicatat_pada`, `dikembalikan_pada`) VALUES
(1, '2026-11-11', '2026-11-18', '2026-11-13', 'Dikembalikan', 1, 2, '2026-11-11 13:53:56', '2026-11-13 08:09:03'),
(2, '2026-10-31', '2026-11-07', '2026-11-13', 'Dikembalikan', 3, 2, '2026-10-31 13:53:56', '2026-11-13 08:09:01'),
(3, '2026-10-23', '2026-10-30', '2026-10-29', 'Dikembalikan', 2, 2, '2026-10-23 13:53:56', '2026-10-29 13:53:56'),
(4, '2026-10-08', '2026-10-15', '2026-10-14', 'Dikembalikan', 4, 2, '2026-10-08 13:53:56', '2026-10-14 13:53:56'),
(5, '2026-11-11', '2026-11-20', '2026-11-13', 'Dikembalikan', 3, 2, '2026-11-13 08:08:46', '2026-11-13 08:09:07'),
(6, '2026-11-13', '2026-11-20', '2026-11-13', 'Dikembalikan', 1, 2, '2026-11-13 11:35:20', '2026-11-13 12:53:31'),
(8, '2026-11-16', '2026-11-24', '2026-11-16', 'Dikembalikan', 371, 2, '2026-11-16 08:40:41', '2026-11-16 13:37:01'),
(12, '2026-11-16', '2026-11-23', '2026-11-17', 'Dikembalikan', 376, 2, '2026-11-16 13:45:11', '2026-11-17 08:03:22'),
(13, '2026-11-16', '2026-11-23', '2026-11-17', 'Dikembalikan', 377, 2, '2026-11-16 14:45:18', '2026-11-17 08:03:25'),
(14, '2026-11-17', '2026-11-24', NULL, 'Dipinjam', 378, 2, '2026-11-17 08:03:32', NULL),
(15, '2026-11-18', '2026-11-25', '2026-11-18', 'Dikembalikan', 379, 2, '2026-11-18 09:41:02', '2026-11-18 09:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_peminjaman`
--

CREATE TABLE `pengajuan_peminjaman` (
  `id_pengajuan` int(11) NOT NULL,
  `nama_peminjam` varchar(100) NOT NULL,
  `nis` varchar(30) NOT NULL,
  `kelas` varchar(60) NOT NULL,
  `id_inventaris` int(11) DEFAULT NULL,
  `id_katalog_barang` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `batas_kembali` date NOT NULL,
  `keperluan` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  `diajukan_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `id_petugas` int(11) DEFAULT NULL,
  `ditangani_pada` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `pengajuan_peminjaman`
--

INSERT INTO `pengajuan_peminjaman` (`id_pengajuan`, `nama_peminjam`, `nis`, `kelas`, `id_inventaris`, `id_katalog_barang`, `jumlah`, `tanggal_pinjam`, `batas_kembali`, `keperluan`, `status`, `diajukan_pada`, `id_petugas`, `ditangani_pada`) VALUES
(4, 'rafa fauzan pratama', '001', 'XII PPLG 1', 3, NULL, 1, '2026-11-16', '2026-11-24', 'KUNJIN', 'Disetujui', '2026-11-16 08:37:18', 2, '2026-11-16 08:40:41'),
(8, 'rafa fauzan', '001', 'xii pplg 1', NULL, 66, 1, '2026-11-16', '2026-11-23', NULL, 'Ditolak', '2026-11-16 10:34:03', 2, '2026-11-16 11:07:19'),
(10, 'rizki noval', '0123', 'XII PPLG 2', 59, 52, 2, '2026-11-16', '2026-11-23', 'MENGERJAKAN TUGAS SEKOLAH', 'Disetujui', '2026-11-16 13:36:28', 2, '2026-11-16 13:45:11'),
(11, 'daduut', '01932347', 'XI PPLG 1', 54, 47, 1, '2026-11-16', '2026-11-23', 'ngerjakan tugas', 'Disetujui', '2026-11-16 14:34:28', 2, '2026-11-16 14:45:18'),
(12, 'FAUZAN', '0987', 'X DKV 1', 82, 75, 1, '2026-11-17', '2026-11-24', 'ACARA SEMINAR', 'Disetujui', '2026-11-17 08:02:26', 2, '2026-11-17 08:03:32'),
(13, 'rafa', '0123', 'XII PPLG2', NULL, 42, 1, '2026-11-18', '2026-11-25', 'BUAT NGECAS LAPTOP', 'Ditolak', '2026-11-18 09:39:14', 1, '2026-11-18 14:16:42'),
(14, 'rafa', '0876', 'xii pplg 2', 49, 42, 1, '2026-11-18', '2026-11-25', 'buat ngecas laptop', 'Disetujui', '2026-11-18 09:40:44', 2, '2026-11-18 09:41:02');

-- --------------------------------------------------------

--
-- Table structure for table `petugas`
--

CREATE TABLE `petugas` (
  `id_petugas` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_petugas` varchar(100) NOT NULL,
  `id_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `petugas`
--

INSERT INTO `petugas` (`id_petugas`, `username`, `password`, `nama_petugas`, `id_level`) VALUES
(1, 'admin', '$2y$12$OmKXiMWrMdZOzkzDYaFxLemVm.Bt92Ylg7tZRbhGfs3zkrY7ScVbG', 'Administrator Sarpras', 1),
(2, 'operator', '$2y$12$NtTIKs8ZG21rWNYnxEHgwOVf1CnFNWJD3zA8AwPm5mXu86vtmM49q', 'Operator Sarpras', 2);

-- --------------------------------------------------------

--
-- Table structure for table `ruang`
--

CREATE TABLE `ruang` (
  `id_ruang` int(11) NOT NULL,
  `nama_ruang` varchar(60) NOT NULL,
  `kode_ruang` varchar(10) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ruang`
--

INSERT INTO `ruang` (`id_ruang`, `nama_ruang`, `kode_ruang`, `keterangan`) VALUES
(1, 'Lab RPL', 'LAB-RPL', 'Laboratorium Rekayasa Perangkat Lunak'),
(2, 'Lab TKJ', 'LAB-TKJ', 'Laboratorium Teknik Komputer & Jaringan'),
(3, 'Perpustakaan', 'PERPUS', 'Ruang perpustakaan'),
(4, 'Gudang Sarpras', 'GDG', 'Gudang penyimpanan barang pinjaman'),
(5, 'Kepala Sekolah', 'SMK-R001', NULL),
(6, 'Guru dan Wks. Kurikulum', 'SMK-R002', NULL),
(7, 'Wks kesiswaan dan Bk', 'SMK-R003', NULL),
(8, 'Wks Hubin dan BKK', 'SMK-R004', NULL),
(9, 'TU Keuangan', 'SMK-R005', NULL),
(10, 'TU Administrasi', 'SMK-R006', NULL),
(11, 'UKS', 'SMK-R007', NULL),
(12, 'Osis', 'SMK-R008', NULL),
(13, 'Pramuka', 'SMK-R009', NULL),
(14, 'Paskibra', 'SMK-R010', NULL),
(15, 'Kantor Perusahaan DUDI', 'SMK-R011', NULL),
(16, 'Sarana Prasarana dan Gudang', 'SMK-R012', NULL),
(17, 'IT SUPPORT', 'SMK-R013', NULL),
(18, 'LAB 1 PPLG', 'SMK-R014', NULL),
(19, 'LAB 2 PPLG', 'SMK-R015', NULL),
(20, 'LAB 3D ANIMASI', 'SMK-R016', NULL),
(21, 'LAB GAMBAR', 'SMK-R017', NULL),
(22, 'LAB DKV', 'SMK-R018', NULL),
(23, 'LAB AKUNTANSI', 'SMK-R019', NULL),
(24, 'LAB BAHASA', 'SMK-R020', NULL),
(25, 'LAB PEMASARAN', 'SMK-R021', NULL),
(26, 'LAB PPLG 3', 'SMK-R022', NULL),
(27, 'LAB PPLG 4', 'SMK-R023', NULL),
(28, 'LAB PPLG 5', 'SMK-R024', NULL),
(29, 'LAB PPLG IOT', 'SMK-R025', NULL),
(30, 'LAB BROADCAST', 'SMK-R026', NULL),
(31, 'SABLON', 'SMK-R027', NULL),
(32, 'LAB CETAK', 'SMK-R028', NULL),
(33, 'STUDIO FOTO', 'SMK-R029', NULL),
(34, 'KESENIAN', 'SMK-R030', NULL),
(35, 'XII AKUNTANSI 1', 'SMK-R032', NULL),
(36, 'XII AKUNTANSI 2', 'SMK-R033', NULL),
(37, 'XII DKV', 'SMK-R034', NULL),
(38, 'XII ANIMASI', 'SMK-R035', NULL),
(39, 'GUDANG KURSI', 'SMK-R036', 'gudang untuk menyimpan kursi'),
(40, 'XII PPLG 1', 'SMK-R037', NULL),
(41, 'XII PPLG 2', 'SMK-R038', NULL),
(42, 'XI AKT', 'SMK-R039', NULL),
(43, 'KOSONG', 'SMK-R040', NULL),
(44, 'XI PPLG 1', 'SMK-R041', NULL),
(45, 'XI PPLG 2', 'SMK-R042', NULL),
(46, 'X PPLG 1', 'SMK-R043', NULL),
(47, 'X PPLG 2', 'SMK-R044', NULL),
(48, 'XI DKV', 'SMK-R045', NULL),
(49, 'XI ANIMASI', 'SMK-R046', NULL),
(50, 'X DKV 2', 'SMK-R047', NULL),
(51, 'X DKV 1', 'SMK-R048', NULL),
(52, 'KOSONG', 'SMK-R049', NULL),
(53, 'X AKUNTANSI 2', 'SMK-R050', NULL),
(54, 'GUDANG KURSI', 'SMK-R051', NULL),
(55, 'X AKUNTANSI 1', 'SMK-R052', NULL),
(56, 'KELAS KOSONG 1', 'SMK-R053', NULL),
(57, 'KELAS KOSONG 2', 'SMK-R054', NULL),
(58, 'KELAS KOSONG 3', 'SMK-R055', NULL),
(59, 'KLAS KOSONG GD DEPAN 1', 'SMK-R056', NULL),
(60, 'KLAS KOSONG GD DEPAN 2', 'SMK-R057', NULL),
(61, 'KLAS KOSONG GD DEPAN 3', 'SMK-R058', NULL),
(62, 'KLAS KOSONG GD DEPAN 4', 'SMK-R059', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `detail_pinjam`
--
ALTER TABLE `detail_pinjam`
  ADD PRIMARY KEY (`id_detail_pinjam`),
  ADD UNIQUE KEY `uq_pinjam_barang` (`id_peminjaman`,`id_inventaris`),
  ADD KEY `id_inventaris` (`id_inventaris`);

--
-- Indexes for table `inventaris`
--
ALTER TABLE `inventaris`
  ADD PRIMARY KEY (`id_inventaris`),
  ADD UNIQUE KEY `kode_inventaris` (`kode_inventaris`),
  ADD KEY `id_jenis` (`id_jenis`),
  ADD KEY `id_ruang` (`id_ruang`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `jenis`
--
ALTER TABLE `jenis`
  ADD PRIMARY KEY (`id_jenis`),
  ADD UNIQUE KEY `kode_jenis` (`kode_jenis`);

--
-- Indexes for table `katalog_barang_pinjam`
--
ALTER TABLE `katalog_barang_pinjam`
  ADD PRIMARY KEY (`id_katalog_barang`),
  ADD UNIQUE KEY `uq_katalog_barang` (`kategori`,`nama_barang`);

--
-- Indexes for table `level`
--
ALTER TABLE `level`
  ADD PRIMARY KEY (`id_level`),
  ADD UNIQUE KEY `nama_level` (`nama_level`);

--
-- Indexes for table `pegawai`
--
ALTER TABLE `pegawai`
  ADD PRIMARY KEY (`id_pegawai`),
  ADD UNIQUE KEY `nip` (`nip`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `id_pegawai` (`id_pegawai`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `pengajuan_peminjaman`
--
ALTER TABLE `pengajuan_peminjaman`
  ADD PRIMARY KEY (`id_pengajuan`),
  ADD KEY `id_inventaris` (`id_inventaris`),
  ADD KEY `id_petugas` (`id_petugas`),
  ADD KEY `idx_pengajuan_status` (`status`,`diajukan_pada`),
  ADD KEY `fk_pengajuan_katalog_barang` (`id_katalog_barang`);

--
-- Indexes for table `petugas`
--
ALTER TABLE `petugas`
  ADD PRIMARY KEY (`id_petugas`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_level` (`id_level`);

--
-- Indexes for table `ruang`
--
ALTER TABLE `ruang`
  ADD PRIMARY KEY (`id_ruang`),
  ADD UNIQUE KEY `kode_ruang` (`kode_ruang`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detail_pinjam`
--
ALTER TABLE `detail_pinjam`
  MODIFY `id_detail_pinjam` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventaris`
--
ALTER TABLE `inventaris`
  MODIFY `id_inventaris` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jenis`
--
ALTER TABLE `jenis`
  MODIFY `id_jenis` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `katalog_barang_pinjam`
--
ALTER TABLE `katalog_barang_pinjam`
  MODIFY `id_katalog_barang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `level`
--
ALTER TABLE `level`
  MODIFY `id_level` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pegawai`
--
ALTER TABLE `pegawai`
  MODIFY `id_pegawai` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=380;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_peminjaman` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pengajuan_peminjaman`
--
ALTER TABLE `pengajuan_peminjaman`
  MODIFY `id_pengajuan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `petugas`
--
ALTER TABLE `petugas`
  MODIFY `id_petugas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ruang`
--
ALTER TABLE `ruang`
  MODIFY `id_ruang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_pinjam`
--
ALTER TABLE `detail_pinjam`
  ADD CONSTRAINT `detail_pinjam_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pinjam_ibfk_2` FOREIGN KEY (`id_inventaris`) REFERENCES `inventaris` (`id_inventaris`);

--
-- Constraints for table `inventaris`
--
ALTER TABLE `inventaris`
  ADD CONSTRAINT `inventaris_ibfk_1` FOREIGN KEY (`id_jenis`) REFERENCES `jenis` (`id_jenis`),
  ADD CONSTRAINT `inventaris_ibfk_2` FOREIGN KEY (`id_ruang`) REFERENCES `ruang` (`id_ruang`),
  ADD CONSTRAINT `inventaris_ibfk_3` FOREIGN KEY (`id_petugas`) REFERENCES `petugas` (`id_petugas`);

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_petugas`) REFERENCES `petugas` (`id_petugas`);

--
-- Constraints for table `pengajuan_peminjaman`
--
ALTER TABLE `pengajuan_peminjaman`
  ADD CONSTRAINT `fk_pengajuan_katalog_barang` FOREIGN KEY (`id_katalog_barang`) REFERENCES `katalog_barang_pinjam` (`id_katalog_barang`),
  ADD CONSTRAINT `pengajuan_peminjaman_ibfk_1` FOREIGN KEY (`id_inventaris`) REFERENCES `inventaris` (`id_inventaris`),
  ADD CONSTRAINT `pengajuan_peminjaman_ibfk_2` FOREIGN KEY (`id_petugas`) REFERENCES `petugas` (`id_petugas`);

--
-- Constraints for table `petugas`
--
ALTER TABLE `petugas`
  ADD CONSTRAINT `petugas_ibfk_1` FOREIGN KEY (`id_level`) REFERENCES `level` (`id_level`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
