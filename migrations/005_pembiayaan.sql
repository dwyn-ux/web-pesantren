SET NAMES utf8mb4;

-- ── Tabel: pembiayaan_tarif (tarif global, diatur admin) ────────
-- pendaftaran/infak/laundry: 1 baris aktif per jenis.
-- administrasi/wakaf: bisa beberapa baris (model/pilihan), santri pilih satu.
-- laundry: gender L/P untuk beda harga laki-laki/perempuan.
CREATE TABLE IF NOT EXISTS `pembiayaan_tarif` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `jenis`        ENUM('pendaftaran','administrasi','wakaf','laundry','infak') NOT NULL,
  `nama`         VARCHAR(150)   DEFAULT NULL,
  `harga_asli`   DECIMAL(12,2)  NOT NULL DEFAULT 0,
  `harga_diskon` DECIMAL(12,2)  DEFAULT NULL,
  `gratis`       TINYINT(1)     NOT NULL DEFAULT 0,
  `gender`       ENUM('all','L','P') NOT NULL DEFAULT 'all',
  `is_active`    TINYINT(1)     NOT NULL DEFAULT 1,
  `urutan`       INT            NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tarif_jenis` (`jenis`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabel: pembiayaan (snapshot per-santri saat daftar) ──────────
-- Di-snapshot dari pembiayaan_tarif agar perubahan tarif global
-- tidak mengubah tagihan santri yang sudah mendaftar.
CREATE TABLE IF NOT EXISTS `pembiayaan` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` INT UNSIGNED  NOT NULL,
  `jenis`          ENUM('pendaftaran','administrasi','wakaf','laundry','infak') NOT NULL,
  `nama`           VARCHAR(150)  DEFAULT NULL,
  `harga_asli`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `harga_diskon`   DECIMAL(12,2) DEFAULT NULL,
  `gratis`         TINYINT(1)    NOT NULL DEFAULT 0,
  `nominal`        DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Jumlah yang harus dibayar',
  `status`         ENUM('belum','menunggu','lunas','gratis','ditolak') NOT NULL DEFAULT 'belum',
  `dipilih`        TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Model/pilihan yang dipilih santri',
  `kesanggupan`    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Disanggupi saat tanda tangan',
  `urutan`         INT           NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pembiayaan_pendaftar` (`pendaftaran_id`,`jenis`),
  CONSTRAINT `fk_pembiayaan_pendaftaran` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ALTER pendaftaran: kesanggupan + tanda tangan ────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='kesanggupan_setuju'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `kesanggupan_setuju` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status_pembayaran`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='kesanggupan_sign'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `kesanggupan_sign` VARCHAR(255) DEFAULT NULL AFTER `kesanggupan_setuju`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='kesanggupan_at'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `kesanggupan_at` DATETIME DEFAULT NULL AFTER `kesanggupan_sign`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── ALTER berkas_santri: jenis baru + tautan ke item pembiayaan ──
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='berkas_santri' AND COLUMN_NAME='pembiayaan_id'),
  'SELECT 1',
  'ALTER TABLE `berkas_santri` ADD COLUMN `pembiayaan_id` INT UNSIGNED DEFAULT NULL AFTER `pendaftaran_id`, ADD INDEX `idx_berkas_pembiayaan` (`pembiayaan_id`)'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='berkas_santri' AND COLUMN_NAME='jenis' AND COLUMN_TYPE LIKE '%ktp-ortu%'),
  'SELECT 1',
  'ALTER TABLE `berkas_santri` MODIFY COLUMN `jenis` ENUM(''kartu-keluarga'',''akta-lahir'',''ijazah'',''foto'',''bukti-bayar'',''lainnya'',''ktp-ortu'',''sertifikat-tka'') NOT NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── Seed tarif default (hanya jika tabel kosong) ─────────────────
INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'pendaftaran' AS jenis, NULL AS nama, 2500000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'all' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif`);

INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'administrasi' AS jenis, 'Paket Standar' AS nama, 1500000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'all' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='administrasi');

INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'wakaf' AS jenis, 'Wakaf Pembangunan' AS nama, 1000000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'all' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='wakaf');

INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'laundry' AS jenis, 'Laundry Santri' AS nama, 500000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'L' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='laundry' AND `gender`='L');

INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'laundry' AS jenis, 'Laundry Santri' AS nama, 500000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'P' AS gender, 2 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='laundry' AND `gender`='P');

INSERT INTO `pembiayaan_tarif` (`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gratis`,`gender`,`urutan`)
SELECT * FROM (SELECT 'infak' AS jenis, 'Infak Wajib' AS nama, 50000 AS harga_asli, NULL AS harga_diskon, 0 AS gratis, 'all' AS gender, 1 AS urutan) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='infak');
