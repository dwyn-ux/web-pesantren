SET NAMES utf8mb4;

-- ============================================================
-- Migration 021: Voucher Akashi (Prestasi Internal)
-- Kompetisi internal (Akashi) untuk SD umum di luar Ashidiq.
-- Voucher terikat hadiah fisik, BUKAN NISN: tiap pemenang punya
-- kode unik (format AKS-XXXXXXXXXX), 1x pakai, klaim dengan kode
-- saja di portal (jalur prestasi > tingkat internal).
-- Potongan: nominal Rp pengurang ADM awal (juara 1: 2jt,
-- juara 2: 1,5jt, juara 3: 1jt). Masa berlaku: 3 tahun.
-- Aman dijalankan ulang (idempotent).
-- ============================================================

CREATE TABLE IF NOT EXISTS `voucher_akashi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(20) NOT NULL COMMENT 'kode voucher unik (prefix AKS-)',
  `juara` ENUM('juara-1','juara-2','juara-3') NOT NULL DEFAULT 'juara-3' COMMENT 'tingkat juara lomba Akashi',
  `nominal_potongan` DECIMAL(15,2) NOT NULL DEFAULT 1000000.00 COMMENT 'potongan ADM awal (Rp)',
  `nama_pemenang` VARCHAR(120) DEFAULT NULL COMMENT 'nama pemenang (opsional, untuk kejelasan admin)',
  `jalur` VARCHAR(30) NOT NULL DEFAULT 'prestasi',
  `jalur_detail` VARCHAR(30) NOT NULL DEFAULT 'internal',
  `pendaftaran_id` INT UNSIGNED DEFAULT NULL COMMENT 'terpakai oleh pendaftaran ini (NULL = belum)',
  `expire_at` DATE DEFAULT NULL COMMENT 'NULL = tidak ada masa berlaku',
  `dibuat_oleh` INT UNSIGNED DEFAULT NULL COMMENT 'user id admin pembuat',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kode` (`kode`),
  INDEX `idx_akashi_status` (`jalur_detail`, `pendaftaran_id`),
  CONSTRAINT `fk_akashi_pendaftaran`
    FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom nominal Akashi per juara di jalur_potongan_admin (jika belum ada)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jalur_potongan_admin'
      AND COLUMN_NAME='akashi_juara1'),
  'SELECT 1',
  'ALTER TABLE `jalur_potongan_admin`
     ADD COLUMN `akashi_juara1` DECIMAL(15,2) DEFAULT NULL AFTER `wakaf_khusus`,
     ADD COLUMN `akashi_juara2` DECIMAL(15,2) DEFAULT NULL AFTER `akashi_juara1`,
     ADD COLUMN `akashi_juara3` DECIMAL(15,2) DEFAULT NULL AFTER `akashi_juara2`'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
