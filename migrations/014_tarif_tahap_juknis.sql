SET NAMES utf8mb4;

-- ============================================================
-- Migration 014: Tarif per gelombang sesuai juknis TA 2027/2028
-- + Hapus seed generic (250rb/1.5jt/1jt) yang tidak match
-- + Update syahriyah 750rb → 800rb (juknis VI)
-- Aman dijalankan ulang (idempotent).
-- Catatan: jalankan SETELAH migration 013.
-- ============================================================

-- ── 1. Tambah gelombang_id di pembiayaan_tarif ───────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pembiayaan_tarif' AND COLUMN_NAME='gelombang_id'),
  'SELECT 1',
  'ALTER TABLE `pembiayaan_tarif`
    ADD COLUMN `gelombang_id` INT UNSIGNED DEFAULT NULL COMMENT ''NULL = berlaku semua tahap'' AFTER `jenis`,
    ADD INDEX `idx_tarif_gelombang` (`gelombang_id`, `jenis`, `is_active`),
    ADD CONSTRAINT `fk_tarif_gelombang`
      FOREIGN KEY (`gelombang_id`) REFERENCES `pendaftaran_gelombang` (`id`) ON DELETE CASCADE'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Hapus seed generic (hanya yang NULL gelombang_id + 3 jenis ini) ──
DELETE FROM `pembiayaan_tarif`
  WHERE `gelombang_id` IS NULL
    AND `jenis` IN ('pendaftaran','administrasi','wakaf');

-- ── 3. Lookup id gelombang (idempotent, abaikan jika tabel kosong) ──
SET @g_indent = (SELECT id FROM pendaftaran_gelombang WHERE slug='indent');
SET @g_g1     = (SELECT id FROM pendaftaran_gelombang WHERE slug='gelombang-1');
SET @g_g2     = (SELECT id FROM pendaftaran_gelombang WHERE slug='gelombang-2');
SET @g_g3     = (SELECT id FROM pendaftaran_gelombang WHERE slug='gelombang-3');

-- Skip seed jika gelombang belum ada
SET @skip = (@g_indent IS NULL OR @g_g1 IS NULL OR @g_g2 IS NULL OR @g_g3 IS NULL);

-- ── 4. Pendaftaran per tahap: Indent GRATIS, G1 200rb, G2 300rb, G3 300rb ──
INSERT INTO `pembiayaan_tarif` (`gelombang_id`,`jenis`,`nama`,`harga_asli`,`gratis`,`gender`,`urutan`)
SELECT * FROM (
  SELECT @g_indent AS gelombang_id, 'pendaftaran' AS jenis, 'Biaya Pendaftaran (Indent)' AS nama, 0 AS harga_asli, 1 AS gratis, 'all' AS gender, 1 AS urutan
  UNION ALL SELECT @g_g1,     'pendaftaran', 'Biaya Pendaftaran (G1)',     200000, 0, 'all', 1
  UNION ALL SELECT @g_g2,     'pendaftaran', 'Biaya Pendaftaran (G2)',     300000, 0, 'all', 1
  UNION ALL SELECT @g_g3,     'pendaftaran', 'Biaya Pendaftaran (G3)',     300000, 0, 'all', 1
) AS seed
WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='pendaftaran' AND `gelombang_id` IS NOT NULL);

-- ── 5. ADM Awal: 7.5jt di semua tahap (Indent otomatis dipotong 500rb) ───
INSERT INTO `pembiayaan_tarif` (`gelombang_id`,`jenis`,`nama`,`harga_asli`,`harga_diskon`,`gender`,`urutan`)
SELECT * FROM (
  SELECT @g_indent AS gelombang_id, 'administrasi' AS jenis, 'ADM Awal (Indent)' AS nama, 7500000 AS harga_asli, 7000000 AS harga_diskon, 'all' AS gender, 1 AS urutan
  UNION ALL SELECT @g_g1, 'administrasi', 'ADM Awal (G1)', 7500000, NULL, 'all', 1
  UNION ALL SELECT @g_g2, 'administrasi', 'ADM Awal (G2)', 7500000, NULL, 'all', 1
  UNION ALL SELECT @g_g3, 'administrasi', 'ADM Awal (G3)', 7500000, NULL, 'all', 1
) AS seed
WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='administrasi' AND `gelombang_id` IS NOT NULL);

-- ── 6. Wakaf: 1.5jt / 2jt / 3jt / 3.5jt ─────────────────────
INSERT INTO `pembiayaan_tarif` (`gelombang_id`,`jenis`,`nama`,`harga_asli`,`gender`,`urutan`)
SELECT * FROM (
  SELECT @g_indent AS gelombang_id, 'wakaf' AS jenis, 'Wakaf Pembangunan (Indent)' AS nama, 1500000 AS harga_asli, 'all' AS gender, 1 AS urutan
  UNION ALL SELECT @g_g1, 'wakaf', 'Wakaf Pembangunan (G1)', 2000000, 'all', 1
  UNION ALL SELECT @g_g2, 'wakaf', 'Wakaf Pembangunan (G2)', 3000000, 'all', 1
  UNION ALL SELECT @g_g3, 'wakaf', 'Wakaf Pembangunan (G3)', 3500000, 'all', 1
) AS seed
WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM `pembiayaan_tarif` WHERE `jenis`='wakaf' AND `gelombang_id` IS NOT NULL);

-- ── 7. Update syahriyah 750rb → 800rb (juknis VI) ─────────
UPDATE `pembiayaan_tarif` SET `harga_asli` = 800000
  WHERE `jenis`='syahriyah' AND `gelombang_id` IS NULL AND `harga_asli` = 750000;
