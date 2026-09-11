SET NAMES utf8mb4;

-- ============================================================
-- Migration 027: Seed pengaturan timeline PSB TA 2027/2028
-- (dipakai oleh home.php untuk section "Daftar Period")
-- Aman dijalankan ulang (INSERT IGNORE / ON DUPLICATE KEY UPDATE).
-- ============================================================

INSERT INTO `pengaturan` (`key_name`, `value`, `label`) VALUES
  ('psb_tgl_mulai',        '2027-05-01', 'Mulai Pendaftaran PSB'),
  ('psb_tgl_seleksi',      '2027-06-01', 'Mulai Seleksi & Tes'),
  ('psb_tgl_seleksi_selesai', '2027-06-20', 'Selesai Seleksi & Tes'),
  ('psb_tgl_hasil',        '2027-07-01', 'Pengumuman Hasil')
ON DUPLICATE KEY UPDATE label=VALUES(label);