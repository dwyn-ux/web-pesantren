SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════
-- 010: PENGGATURAN POTONGAN JALUR PSB (ADMIN)
-- Tabel jalur_potongan_admin menyimpan nilai potongan per jalur
-- yang bisa diset oleh admin (mengganti/mengendalikan juknis).
--
-- Secara default seed dengan nilai juknis TA 2027/2028, jadi
-- sistem tetap bekerja meski admin belum mengubah apa-apa.
-- ─────────────────────────────────────────────────────────────────
-- kolom:
--   jalur              ENUM(jalur yang dikenali)
--   potongan_persen    persen potongan (jalur prestasi/tahfidz/alumni/dhuafa)
--   adm_khusus         tarif ADM khusus (kaderisasi / kaderisasi-like)
--   spp_l_khusus       tarif SPP putra khusus (kaderisasi)
--   spp_p_khusus       tarif SPP putri khusus (kaderisasi)
--   admin_dhuafa_bebas 1 => ADMIN AWAL DHUAFA DIBEBA SKAN 100%
-- ─────────────────────────────────────────────────────────────────

-- tabel
CREATE TABLE IF NOT EXISTS `jalur_potongan_admin` (
  `jalur` VARCHAR(50) NOT NULL COMMENT 'kunci: reguler|prestasi|tahfidz|kaderisasi|alumni-sdmua|dhuafa',
  `potongan_persen` DECIMAL(5,2) DEFAULT NULL COMMENT 'persen potongan umum (prestasi/tahfidz/alumni/dhuafa)',
  `adm_khusus` DECIMAL(12,0) DEFAULT NULL COMMENT 'tarif ADM khusus (kaderisasi)',
  `spp_l_khusus` DECIMAL(12,0) DEFAULT NULL COMMENT 'tarif SPP putra khusus (kaderisasi)',
  `spp_p_khusus` DECIMAL(12,0) DEFAULT NULL COMMENT 'tarif SPP putri khusus (kaderisasi)',
  `admin_dhuafa_bebas` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'jika 1, ADM awal DHUAFA dibebaskan 100%',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`jalur`),
  UNIQUE KEY `u_idx_jalur` (`jalur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Pengaturan potongan jalur PSB oleh admin (fallback juknis)';

-- seed: isi nilai juknis (idempotent)
INSERT INTO `jalur_potongan_admin` (`jalur`,`potongan_persen`,`adm_khusus`,`spp_l_khusus`,`spp_p_khusus`,`admin_dhuafa_bebas`)
SELECT * FROM (
  SELECT 'reguler' AS jalur, NULL AS potongan_persen, NULL AS adm_khusus, NULL AS spp_l_khusus, NULL AS spp_p_khusus, 0 AS admin_dhuafa_bebas
  UNION ALL
  SELECT 'prestasi', 0, NULL, NULL, NULL, 0
  UNION ALL
  SELECT 'tahfidz', 0, NULL, NULL, NULL, 0
  UNION ALL
  SELECT 'kaderisasi', NULL, 5000000, 650000, 750000, 0
  UNION ALL
  SELECT 'alumni-sdmua', 0, NULL, NULL, NULL, 0
  UNION ALL
  SELECT 'dhuafa', 0, NULL, NULL, NULL, 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `jalur_potongan_admin`);

-- catatan: nilai di atas hanyalah referensi juknis.
--   - prestasi      : potongan akan diisi oleh admin sesuai tingkat prestasi (1-4)
--   - tahfidz       : potongan akan diisi oleh admin sesuai jumlah juz
--   - kaderisasi    : adm_khusus / spp_l_khusus / spp_p_khusus sesuai Juknis VIII
--   - alumni-sdmua  : agar setara dengan jalur verifikasi (25-50%), admin bisa isi 0..50
--   - dhuafa        : potongan SPP 20-60%, ADM bebas (sudah di-set admin_dhuafa_bebas=1)
--   - reguler       : tidak ada potongan
