SET NAMES utf8mb4;

-- ============================================================
-- Migration 017: Voucher undangan jalur Alumni SD Ashidiq
-- Jalur alumni hanya bisa diakses siswa SD Muhammadiyah Unggulan
-- Ashidiq (satu yayasan). Panitia membuat voucher per siswa:
-- kode undangan acak terikat ke NISN siswa.
-- Calon mengklaim dengan memasukkan kode + NISN di portal.
-- Aman dijalankan ulang (idempotent).
-- ============================================================

CREATE TABLE IF NOT EXISTS `voucher_alumni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(20) NOT NULL COMMENT 'kode undangan acak (prefix ASQ-)',
  `nisn` VARCHAR(20) NOT NULL COMMENT 'NISN siswa SD Ashidiq yang berhak',
  `nama_siswa` VARCHAR(120) DEFAULT NULL COMMENT 'nama siswa (untuk kejelasan admin)',
  `jalur` VARCHAR(30) NOT NULL DEFAULT 'alumni-sdmua',
  `pendaftaran_id` INT UNSIGNED DEFAULT NULL COMMENT 'terpakai oleh pendaftaran ini (NULL = belum)',
  `expire_at` DATE DEFAULT NULL COMMENT 'NULL = tidak ada masa berlaku',
  `dibuat_oleh` INT UNSIGNED DEFAULT NULL COMMENT 'user id admin pembuat',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kode` (`kode`),
  UNIQUE KEY `uk_nisn` (`nisn`),
  INDEX `idx_voucher_status` (`jalur`, `pendaftaran_id`),
  CONSTRAINT `fk_voucher_pendaftaran`
    FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;