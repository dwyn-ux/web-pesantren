SET NAMES utf8mb4;
-- ═══════════════════════════════════════════════════════════════
-- 011: KOLOM POTONGAN PER DETAIL (PRESTASI & TAHFIDZ)
-- Tambah kolom ke jalur_potongan_admin kalau belum ada, satu per
-- satu (idempotent). Kalau kolom sudah ada tapi isinya NULL,
-- isi dengan nilai juknis.
-- ═══════════════════════════════════════════════════════════════

-- ── prestasi ──────────────────────────────────────────────────
SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'prestasi_kecamatan'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `prestasi_kecamatan` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan prestasi tingkat kecamatan"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'prestasi_kabkota'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `prestasi_kabkota` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan prestasi tingkat kabupaten/kota"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'prestasi_provinsi'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `prestasi_provinsi` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan prestasi tingkat provinsi"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'prestasi_nasional'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `prestasi_nasional` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan prestasi tingkat nasional/internasional"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- seed prestasi (hanya jika kolom ada dan isinya NULL)
UPDATE `jalur_potongan_admin` SET
  `prestasi_kecamatan` = COALESCE(`prestasi_kecamatan`, 20.00),
  `prestasi_kabkota`   = COALESCE(`prestasi_kabkota`,   30.00),
  `prestasi_provinsi`  = COALESCE(`prestasi_provinsi`,  40.00),
  `prestasi_nasional`  = COALESCE(`prestasi_nasional`,  50.00)
WHERE `jalur` = 'prestasi'
  AND EXISTS (SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'jalur_potongan_admin'
                AND COLUMN_NAME  = 'prestasi_kecamatan');

-- ── tahfidz ───────────────────────────────────────────────────
SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'tahfidz_juz2'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `tahfidz_juz2` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan tahfidz hafalan > 2 juz"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'tahfidz_juz3'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `tahfidz_juz3` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan tahfidz hafalan > 3 juz"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'tahfidz_juz5'
  ),
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `tahfidz_juz5` DECIMAL(5,2) DEFAULT NULL COMMENT "potongan tahfidz hafalan > 5 juz"',
  'SELECT 1'
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- seed tahfidz (hanya jika kolom ada dan isinya NULL)
UPDATE `jalur_potongan_admin` SET
  `tahfidz_juz2` = COALESCE(`tahfidz_juz2`, 20.00),
  `tahfidz_juz3` = COALESCE(`tahfidz_juz3`, 30.00),
  `tahfidz_juz5` = COALESCE(`tahfidz_juz5`, 50.00)
WHERE `jalur` = 'tahfidz'
  AND EXISTS (SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'jalur_potongan_admin'
                AND COLUMN_NAME  = 'tahfidz_juz2');
