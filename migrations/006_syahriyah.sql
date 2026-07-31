SET NAMES utf8mb4;

-- Tambah jenis 'syahriyah' (biaya bulanan) ke enum pembiayaan.
-- Berjalan aman jika dijalankan ulang.

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pembiayaan_tarif' AND COLUMN_NAME='jenis' AND COLUMN_TYPE LIKE '%syahriyah%'),
  'SELECT 1',
  'ALTER TABLE `pembiayaan_tarif` MODIFY COLUMN `jenis` ENUM(''pendaftaran'',''administrasi'',''wakaf'',''laundry'',''infak'',''syahriyah'') NOT NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pembiayaan' AND COLUMN_NAME='jenis' AND COLUMN_TYPE LIKE '%syahriyah%'),
  'SELECT 1',
  'ALTER TABLE `pembiayaan` MODIFY COLUMN `jenis` ENUM(''pendaftaran'',''administrasi'',''wakaf'',''laundry'',''infak'',''syahriyah'') NOT NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed pilihan syahriyah default (hanya jika belum ada)
INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'syahriyah' AS jenis, 'Syahriyah Standar' AS nama, 750000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'all' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='syahriyah');
