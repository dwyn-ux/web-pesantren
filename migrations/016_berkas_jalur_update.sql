SET NAMES utf8mb4;

-- ============================================================
-- Migration 016: Jenis berkas jalur baru
--   - sertifikat-tahfidz  (jalur tahfidz — akan diuji tes hafalan)
--   - mou-kaderisasi      (jalur kaderisasi — download template MOU,
--                          tanda tangan, lalu upload ulang)
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- Perluas ENUM berkas_santri.jenis kalau belum mengandung keduanya
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='berkas_santri'
      AND COLUMN_NAME='jenis'
      AND COLUMN_TYPE LIKE '%sertifikat-tahfidz%'
      AND COLUMN_TYPE LIKE '%mou-kaderisasi%'),
  'SELECT 1',
  'ALTER TABLE `berkas_santri` MODIFY COLUMN `jenis` ENUM(
    ''kartu-keluarga'',''akta-lahir'',''ijazah'',''foto'',''bukti-bayar'',''lainnya'',
    ''ktp-ortu'',''sertifikat-tka'',''sertifikat-tahfidz'',
    ''surat-rekomendasi'',''sktm'',''surat-pernyataan'',''mou-kaderisasi''
  ) NOT NULL'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;