SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════
-- 009: JALUR PENDAFTARAN PSB (sesuai Juknis PSB TA 2027/2028)
-- Jalur: reguler, prestasi, tahfidz, kaderisasi, alumni-sdmua, dhuafa
-- Aman dijalankan ulang (idempotent).
-- ═══════════════════════════════════════════════════════════════

-- ── Kolom jalur di tabel pendaftaran ────────────────────────────
-- jalur_detail : tingkat prestasi / kategori hafalan
-- jalur_status : none (tidak perlu verifikasi) | pending | disetujui | ditolak
-- jalur_potongan : persen potongan yang ditetapkan admin (alumni/dhuafa)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='jalur'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran`
     ADD COLUMN `jalur` ENUM(''reguler'',''prestasi'',''tahfidz'',''kaderisasi'',''alumni-sdmua'',''dhuafa'') NOT NULL DEFAULT ''reguler'' AFTER `jumlah_hafalan`,
     ADD COLUMN `jalur_detail` VARCHAR(50) DEFAULT NULL AFTER `jalur`,
     ADD COLUMN `jalur_status` ENUM(''none'',''pending'',''disetujui'',''ditolak'') NOT NULL DEFAULT ''none'' AFTER `jalur_detail`,
     ADD COLUMN `jalur_potongan` DECIMAL(5,2) DEFAULT NULL COMMENT ''Persen potongan oleh admin (jalur alumni/dhuafa)'' AFTER `jalur_status`,
     ADD INDEX `idx_jalur` (`jalur`)'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── Jenis berkas baru untuk jalur verifikasi ────────────────────
-- alumni-sdmua : surat-rekomendasi
-- dhuafa       : sktm, surat-rekomendasi (PCM/PDM), surat-pernyataan
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='berkas_santri' AND COLUMN_NAME='jenis' AND COLUMN_TYPE LIKE '%surat-rekomendasi%'),
  'SELECT 1',
  'ALTER TABLE `berkas_santri` MODIFY COLUMN `jenis` ENUM(''kartu-keluarga'',''akta-lahir'',''ijazah'',''foto'',''bukti-bayar'',''lainnya'',''ktp-ortu'',''sertifikat-tka'',''surat-rekomendasi'',''sktm'',''surat-pernyataan'') NOT NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── Seed pengaturan tarif jalur kaderisasi (Juknis VIII) ────────
INSERT INTO `pengaturan` (`key_name`,`value`,`label`)
SELECT * FROM (SELECT 'jalur_kaderisasi_adm' AS key_name, '5000000' AS `value`, 'ADM Awal Khusus Jalur Kaderisasi (Rp)' AS label) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pengaturan` WHERE `key_name`='jalur_kaderisasi_adm');

INSERT INTO `pengaturan` (`key_name`,`value`,`label`)
SELECT * FROM (SELECT 'jalur_kaderisasi_spp_l' AS key_name, '650000' AS `value`, 'SPP Jalur Kaderisasi Putra (Rp/bulan)' AS label) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pengaturan` WHERE `key_name`='jalur_kaderisasi_spp_l');

INSERT INTO `pengaturan` (`key_name`,`value`,`label`)
SELECT * FROM (SELECT 'jalur_kaderisasi_spp_p' AS key_name, '750000' AS `value`, 'SPP Jalur Kaderisasi Putri (Rp/bulan)' AS label) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pengaturan` WHERE `key_name`='jalur_kaderisasi_spp_p');
