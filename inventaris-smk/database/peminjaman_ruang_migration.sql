-- Jalankan satu kali setelah database memiliki daftar ruang sekolah.
USE sarpras_smk_bn_666;

CREATE TABLE IF NOT EXISTS pengajuan_peminjaman_ruang (
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
