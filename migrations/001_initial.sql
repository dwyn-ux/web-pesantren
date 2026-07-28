-- ============================================================
-- Migration 001 — Schema awal Pondok Pesantren Ash-Shiddiq
-- Jalankan sekali saat setup pertama
-- MySQL 8.0+ | charset: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';
SET SESSION explicit_defaults_for_timestamp = 1;

-- ── Tabel: users ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)   NOT NULL,
  `email`      VARCHAR(150)   NOT NULL,
  `password`   VARCHAR(255)   NOT NULL,
  `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
  `is_active`  TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin default — GANTI PASSWORD SETELAH PERTAMA LOGIN
-- Password: Admin@Ashiddiq2025
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `is_active`) VALUES
('Administrator', 'admin@ponpesashiddiq.or.id', '$2y$12$QzVAOtf4KZbFk1PZqvI/..i3nRgOi3B5XNj7MnlDvOJJN3aTtHJry', 'admin', 1);


-- ── Tabel: artikel ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `artikel` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(200)   NOT NULL,
  `judul`       VARCHAR(300)   NOT NULL,
  `ringkasan`   TEXT,
  `isi`         LONGTEXT       NOT NULL,
  `foto`        VARCHAR(255)   DEFAULT NULL,
  `kategori`    ENUM('tahfidz','akhlak','kajian','kegiatan','psb-info','alumni') NOT NULL DEFAULT 'kajian',
  `status`      ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `penulis_id`  INT UNSIGNED   NOT NULL,
  `views`       INT UNSIGNED   NOT NULL DEFAULT 0,
  `published_at` DATETIME      DEFAULT NULL,
  `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  INDEX `idx_kategori` (`kategori`),
  INDEX `idx_status` (`status`),
  INDEX `idx_published_at` (`published_at`),
  CONSTRAINT `fk_artikel_penulis` FOREIGN KEY (`penulis_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Tabel: pendaftaran (PSB) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `pendaftaran` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nomor_daftar`    VARCHAR(20)   NOT NULL COMMENT 'Format: ASQ-YYYY-XXXX',
  -- Data calon santri
  `nama_lengkap`    VARCHAR(100)  NOT NULL,
  `tempat_lahir`    VARCHAR(100)  NOT NULL,
  `tanggal_lahir`   DATE          NOT NULL,
  `jenis_kelamin`   ENUM('L','P') NOT NULL,
  `jenjang`         ENUM('mts','ma','tahfidz-intensif') NOT NULL,
  `whatsapp`        VARCHAR(20)   NOT NULL,
  -- Data orang tua
  `nama_ayah`       VARCHAR(100)  NOT NULL,
  `nama_ibu`        VARCHAR(100)  NOT NULL,
  `hp_ortu`         VARCHAR(20)   NOT NULL,
  `pekerjaan_ortu`  VARCHAR(100),
  `alamat`          TEXT          NOT NULL,
  -- Data akademik
  `asal_sekolah`    VARCHAR(150)  NOT NULL,
  `tahun_lulus`     YEAR          NOT NULL,
  `kemampuan_quran` ENUM('belum-bisa','bisa-membaca','tartil','hafal-juz-30','hafal-lebih') NOT NULL,
  `jumlah_hafalan`  VARCHAR(50)   DEFAULT NULL,
  `motivasi`        TEXT          DEFAULT NULL,
  -- Status
  `status`          ENUM('pending','diterima','ditolak','daftar-ulang') NOT NULL DEFAULT 'pending',
  `catatan_admin`   TEXT          DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nomor_daftar` (`nomor_daftar`),
  INDEX `idx_status` (`status`),
  INDEX `idx_jenjang` (`jenjang`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Tabel: foto_galeri ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `foto_galeri` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nama_file`   VARCHAR(255)  NOT NULL,
  `judul`       VARCHAR(200)  DEFAULT NULL,
  `keterangan`  TEXT          DEFAULT NULL,
  `kategori`    VARCHAR(50)   DEFAULT 'umum',
  `urutan`      INT UNSIGNED  NOT NULL DEFAULT 0,
  `is_aktif`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_is_aktif` (`is_aktif`),
  INDEX `idx_urutan` (`urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Tabel: pengaturan (key-value) ────────────────────────────
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `key_name`    VARCHAR(100)  NOT NULL,
  `value`       TEXT          DEFAULT NULL,
  `label`       VARCHAR(200)  DEFAULT NULL,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nilai default pengaturan
INSERT IGNORE INTO `pengaturan` (`key_name`, `value`, `label`) VALUES
('psb_status',       'buka',                             'Status Penerimaan Santri Baru'),
('psb_tahun',        '2025/2026',                        'Tahun Ajaran PSB'),
('psb_batas_daftar', '2025-06-30',                       'Batas Akhir Pendaftaran'),
('kontak_whatsapp',  '6281234567890',                    'Nomor WhatsApp Kontak'),
('kontak_email',     'info@ponpesashiddiq.or.id',        'Email Kontak'),
('kontak_alamat',    'Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat', 'Alamat Lengkap'),
('kontak_telepon',   '(0265) 123-4567',                  'Nomor Telepon');


SET FOREIGN_KEY_CHECKS = 1;
