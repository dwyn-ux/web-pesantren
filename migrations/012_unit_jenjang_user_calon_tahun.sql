SET NAMES utf8mb4;

-- ============================================================
-- Migration 012: Unit pendidikan + role calon-santri + user_id FK
-- + Update tahun ajaran ke TA 2027/2028 (juknis)
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- ── 1. Unit pendidikan sesuai juknis ──────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'
      AND COLUMN_NAME='jenjang' AND COLUMN_TYPE LIKE '%smp%'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` MODIFY COLUMN `jenjang`
    ENUM(''smp'',''sma'',''tahfidz-intensif'') NOT NULL'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Role ''calon-santri'' di users ─────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'
      AND COLUMN_NAME='role' AND COLUMN_TYPE LIKE '%calon-santri%'),
  'SELECT 1',
  'ALTER TABLE `users` MODIFY COLUMN `role`
    ENUM(''user'',''admin'',''calon-santri'') NOT NULL DEFAULT ''user'''
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. user_id di pendaftaran (FK ke users, 1 akun = 1 calon) ─
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran`
    ADD COLUMN `user_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD UNIQUE KEY `uk_user_id` (`user_id`),
    ADD CONSTRAINT `fk_pendaftaran_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Update tahun ajaran ke TA 2027/2028 (juknis) ─────────
UPDATE `pengaturan` SET `value` = '2027/2028'
  WHERE `key_name` = 'psb_tahun' AND `value` IN ('2025/2026', '2026/2027');

UPDATE `pengaturan` SET `value` = '2027-06-26'
  WHERE `key_name` = 'psb_batas_daftar' AND `value` = '2025-06-30';

-- ── 5. Seed pengaturan rekening + kop surat ─────────────────
INSERT INTO `pengaturan` (`key_name`,`value`,`label`) VALUES
  ('rekening_pondok', 'BCA 123-456-7890 a.n. Pondok Pesantren Ash-Shiddiq', 'Rekening Pembayaran PSB'),
  ('kop_alamat', 'Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat', 'Alamat Kop Surat'),
  ('kop_telepon', '(0265) 123-4567', 'Telepon Kop Surat')
ON DUPLICATE KEY UPDATE label=VALUES(label);
