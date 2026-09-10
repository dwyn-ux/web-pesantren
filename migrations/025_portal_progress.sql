SET NAMES utf8mb4;

-- ============================================================
-- Migration 025: Progres wizard portal santri
--   - pendaftaran: + akademik_at, jalur_at (kapan step disimpan)
--   - backfill data lama agar progres langsung akurat
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- ── 1. Kolom timestamp step ──────────────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='akademik_at'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `akademik_at` TIMESTAMP NULL DEFAULT NULL AFTER `berat_badan`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='jalur_at'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `jalur_at` TIMESTAMP NULL DEFAULT NULL AFTER `akademik_at`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Backfill pendaftaran lama ─────────────────────────────
-- Akademik dianggap pernah diisi kalau tahun_lulus sudah ter-set.
UPDATE `pendaftaran` SET `akademik_at` = NOW()
  WHERE `akademik_at` IS NULL AND `tahun_lulus` IS NOT NULL;

-- Jalur dianggap pernah dipilih kalau bukan reguler default,
-- punya detail jalur, atau status jalur bukan 'none'.
UPDATE `pendaftaran` SET `jalur_at` = NOW()
  WHERE `jalur_at` IS NULL
    AND (`jalur` <> 'reguler' OR `jalur_detail` IS NOT NULL OR `jalur_status` <> 'none');