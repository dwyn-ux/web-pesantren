SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `dokumentasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `judul` VARCHAR(200) NOT NULL, `deskripsi` TEXT DEFAULT NULL,
  `nama_file` VARCHAR(255) NOT NULL, `nama_asli` VARCHAR(255) NOT NULL,
  `tipe` ENUM('pdf','word','video','foto') NOT NULL, `mime_type` VARCHAR(100) NOT NULL,
  `ukuran` BIGINT UNSIGNED NOT NULL DEFAULT 0, `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `uploaded_by` INT UNSIGNED NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), INDEX `idx_dokumentasi_publish` (`is_published`,`created_at`),
  CONSTRAINT `fk_dokumentasi_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alumni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `nama` VARCHAR(120) NOT NULL,
  `tahun_kelulusan` YEAR NOT NULL, `alamat` TEXT NOT NULL,
  `aktivitas` ENUM('kuliah','bekerja') NOT NULL,
  `tempat_kuliah` VARCHAR(180) DEFAULT NULL, `jurusan` VARCHAR(180) DEFAULT NULL,
  `tempat_bekerja` VARCHAR(180) DEFAULT NULL, `jabatan` VARCHAR(180) DEFAULT NULL,
  `pesan_kesan` TEXT NOT NULL, `saran` TEXT DEFAULT NULL, `foto` VARCHAR(255) NOT NULL,
  `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), INDEX `idx_alumni_status` (`status`,`tahun_kelulusan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
