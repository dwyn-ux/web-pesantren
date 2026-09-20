SET NAMES utf8mb4;

-- ============================================================
-- Migration 029: Perbaiki mapping gelombang tarif yang hilang
-- Penyebab: admin/pengaturan.php versi lama menyimpan ulang tarif
-- TANPA kolom gelombang_id, sehingga semua baris menjadi NULL
-- (= berlaku semua tahap) dan semua varian Indent/G1/G2/G3 ikut
-- masuk tagihan pendaftar.
-- Perbaikan: petakan ulang baris NULL ke gelombang via penanda
-- nama "(Indent)/(G1)/(G2)/(G3)" dari seed migration 014.
-- Aman dijalankan ulang (idempotent): hanya baris yang masih NULL
-- yang diproses; baris yang sudah termapping tidak disentuh.
-- Catatan: baris NULL yang namanya sudah diubah admin (tanpa
-- penanda) tetap NULL/global — perbaiki manual via Pengaturan.
-- ============================================================

UPDATE `pembiayaan_tarif` t
JOIN `pendaftaran_gelombang` g ON (
  (g.slug = 'indent'      AND t.nama LIKE '%(Indent)%') OR
  (g.slug = 'gelombang-1' AND t.nama LIKE '%(G1)%') OR
  (g.slug = 'gelombang-2' AND t.nama LIKE '%(G2)%') OR
  (g.slug = 'gelombang-3' AND t.nama LIKE '%(G3)%')
)
SET t.gelombang_id = g.id
WHERE t.gelombang_id IS NULL
  AND t.jenis IN ('pendaftaran', 'administrasi', 'wakaf');
