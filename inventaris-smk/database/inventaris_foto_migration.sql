-- Jalankan satu kali untuk menambahkan foto pada database aplikasi yang sudah ada.
USE sarpras_smk_bn_666;

ALTER TABLE inventaris
  ADD COLUMN foto VARCHAR(255) NULL AFTER kode_inventaris;
