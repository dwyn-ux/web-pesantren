SET NAMES utf8mb4;

-- ============================================================
-- Migration 013: Tabel gelombang/tahap PSB
-- Seed sesuai juknis TA 2027/2028
-- Aman dijalankan ulang (idempotent).
-- ============================================================

CREATE TABLE IF NOT EXISTS `pendaftaran_gelombang` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(30) NOT NULL COMMENT 'indent|gelombang-1|gelombang-2|gelombang-3',
  `label` VARCHAR(100) NOT NULL,
  `tanggal_buka` DATE NOT NULL,
  `tanggal_tutup` DATE NOT NULL,
  `tanggal_tes` DATE DEFAULT NULL,
  `target_kuota` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tidak ditampilkan publik',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  INDEX `idx_active_tgl` (`is_active`, `tanggal_buka`, `tanggal_tutup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sesuai juknis TA 2027/2028
INSERT IGNORE INTO `pendaftaran_gelombang`
  (`slug`,`label`,`tanggal_buka`,`tanggal_tutup`,`tanggal_tes`,`target_kuota`,`urutan`) VALUES
  ('indent',     'Tahap Indent',     '2026-08-01','2026-11-28','2026-11-28', 25, 1),
  ('gelombang-1','Gelombang 1',      '2026-11-29','2027-02-27','2027-02-27', 15, 2),
  ('gelombang-2','Gelombang 2',      '2027-02-28','2027-04-24','2027-04-24', 15, 3),
  ('gelombang-3','Gelombang 3',      '2027-04-25','2027-06-26','2027-06-26',  5, 4);

-- Tambah gelombang_id di pendaftaran
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='gelombang_id'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran`
    ADD COLUMN `gelombang_id` INT UNSIGNED DEFAULT NULL AFTER `jenjang`,
    ADD INDEX `idx_gelombang_id` (`gelombang_id`),
    ADD CONSTRAINT `fk_pendaftaran_gelombang`
      FOREIGN KEY (`gelombang_id`) REFERENCES `pendaftaran_gelombang` (`id`) ON DELETE SET NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
