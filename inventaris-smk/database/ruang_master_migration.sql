-- Tambahkan daftar ruang sekolah tanpa menghapus atau menggandakan ruang yang sudah ada.
-- Kode SMK-R### dibuat berurutan karena gambar sumber tidak mencantumkan kode ruang.
USE sarpras_smk_bn_666;

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
