SET NAMES utf8mb4;

-- Migration ini aman dijalankan ulang jika proses sebelumnya berhenti di tengah.
-- Menggunakan PREPARE agar tidak memerlukan privilege CREATE ROUTINE.
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alumni' AND COLUMN_NAME='tampil_landing'),
  'SELECT 1', 'ALTER TABLE `alumni` ADD COLUMN `tampil_landing` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='nomor_induk'),
  'SELECT 1', 'ALTER TABLE `pendaftaran` ADD COLUMN `nomor_induk` VARCHAR(40) DEFAULT NULL AFTER `nomor_daftar`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='portal_password'),
  'SELECT 1', 'ALTER TABLE `pendaftaran` ADD COLUMN `portal_password` VARCHAR(255) DEFAULT NULL AFTER `nomor_induk`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='pilihan_biaya'),
  'SELECT 1', 'ALTER TABLE `pendaftaran` ADD COLUMN `pilihan_biaya` ENUM(''penuh'',''ringan'',''beasiswa'') DEFAULT NULL AFTER `portal_password`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='nominal_biaya'),
  'SELECT 1', 'ALTER TABLE `pendaftaran` ADD COLUMN `nominal_biaya` DECIMAL(12,2) DEFAULT NULL AFTER `pilihan_biaya`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND INDEX_NAME='uk_nomor_induk'),
  'SELECT 1', 'ALTER TABLE `pendaftaran` ADD UNIQUE KEY `uk_nomor_induk` (`nomor_induk`)'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `berkas_santri` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `pendaftaran_id` INT UNSIGNED NOT NULL,
  `jenis` ENUM('kartu-keluarga','akta-lahir','ijazah','foto','bukti-bayar','lainnya') NOT NULL,
  `nama_file` VARCHAR(255) NOT NULL, `nama_asli` VARCHAR(255) NOT NULL, `mime_type` VARCHAR(100) NOT NULL,
  `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending', `catatan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), INDEX `idx_berkas_pendaftar` (`pendaftaran_id`,`jenis`),
  CONSTRAINT `fk_berkas_pendaftaran` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pengaturan` (`key_name`,`value`,`label`) VALUES
('map_latitude','-7.325000','Latitude Google Maps'),('map_longitude','108.350000','Longitude Google Maps'),
('map_zoom','15','Zoom Google Maps'),('biaya_penuh','2500000','Biaya pendaftaran penuh'),
('biaya_ringan','1500000','Biaya pendaftaran ringan'),('biaya_beasiswa','0','Biaya jalur beasiswa'),
('rekening_pembayaran','Bank Syariah Indonesia 1234567890 a.n. Pondok Ash-Shiddiq','Rekening pembayaran');
