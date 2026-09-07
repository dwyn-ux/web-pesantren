SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════
-- 011: KOLOM POTONGAN PER DETAIL (PRESTASI & TAHFIDZ)
-- Tabel jalur_potongan_admin diperluas supaya admin bisa mengatur
-- potongan per tingkat prestasi / kategori hafalan, bukan hanya
-- potongan global per jalur.
--
-- Kolom baru (semua DECIMAL(5,2), NULL = belum diatur):
--   prestasi_kecamatan
--   prestasi_kabkota
--   prestasi_provinsi
--   prestasi_nasional
--   tahfidz_juz2
--   tahfidz_juz3
--   tahfidz_juz5
--
-- Jika admin belum mengatur, sistem tetap pakai nilai juknis
-- dari jalurDetailOptions().
-- ═══════════════════════════════════════════════════════════════

-- tambah kolom kalau belum ada
SET @add = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'jalur_potongan_admin'
      AND COLUMN_NAME  = 'prestasi_kecamatan'
  ),
  'SELECT 1',
  'ALTER TABLE `jalur_potongan_admin`
     ADD COLUMN `prestasi_kecamatan` DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan prestasi tingkat kecamatan',
     ADD COLUMN `prestasi_kabkota`   DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan prestasi tingkat kabupaten/kota',
     ADD COLUMN `prestasi_provinsi`  DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan prestasi tingkat provinsi',
     ADD COLUMN `prestasi_nasional`  DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan prestasi tingkat nasional/internasional',
     ADD COLUMN `tahfidz_juz2`       DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan tahfidz hafalan > 2 juz',
     ADD COLUMN `tahfidz_juz3`       DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan tahfidz hafalan > 3 juz',
     ADD COLUMN `tahfidz_juz5`       DECIMAL(5,2) DEFAULT NULL COMMENT 'potongan tahfidz hafalan > 5 juz'
  '
);
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- seed nilai juknis ke baris yang sudah ada (idempotent: hanya jika masih NULL)
UPDATE `jalur_potongan_admin` SET
  `prestasi_kecamatan` = 20.00,
  `prestasi_kabkota`   = 30.00,
  `prestasi_provinsi`  = 40.00,
  `prestasi_nasional`  = 50.00
WHERE `jalur` = 'prestasi'
  AND (`prestasi_kecamatan` IS NULL OR `prestasi_kabkota` IS NULL
       OR `prestasi_provinsi` IS NULL OR `prestasi_nasional` IS NULL)
  AND EXISTS (SELECT 1 FROM `jalur_potongan_admin` WHERE `jalur` = 'prestasi');

UPDATE `jalur_potongan_admin` SET
  `tahfidz_juz2` = 20.00,
  `tahfidz_juz3` = 30.00,
  `tahfidz_juz5` = 50.00
WHERE `jalur` = 'tahfidz'
  AND (`tahfidz_juz2` IS NULL OR `tahfidz_juz3` IS NULL OR `tahfidz_juz5` IS NULL)
  AND EXISTS (SELECT 1 FROM `jalur_potongan_admin` WHERE `jalur` = 'tahfidz');
