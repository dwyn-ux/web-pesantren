SET NAMES utf8mb4;

-- Tabel testimoni (dikelola admin, ditampilkan di halaman depan)

CREATE TABLE IF NOT EXISTS `testimoni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `role` VARCHAR(150) NOT NULL DEFAULT '',
  `isi` TEXT NOT NULL,
  `foto` VARCHAR(255) NOT NULL DEFAULT '',
  `urutan` INT NOT NULL DEFAULT 0,
  `is_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3 dummy testimoni (hanya jika tabel masih kosong; satu statement atomik,
-- aman dijalankan ulang dan tidak menghidupkan kembali dummy yang sudah dihapus admin)
INSERT INTO `testimoni` (`nama`, `role`, `isi`, `urutan`, `is_aktif`)
SELECT * FROM (
  SELECT 'Ibu Siti Nurjanah' AS `nama`, 'Wali Santri — Angkatan 2023' AS `role`,
         'Alhamdulillah, sejak anak saya mondok di Ash-Shiddiq hafalannya bertambah pesat dan akhlaknya jauh lebih baik. Pembinaannya sangat intensif dan penuh kasih sayang.' AS `isi`,
         1 AS `urutan`, 1 AS `is_aktif`
  UNION ALL SELECT 'Ahmad Fauzan', 'Alumni — Angkatan 2024',
         'Dulu saya kesulitan menghafal Al-Qur\'an, kini alhamdulillah hafal 12 juz berkat metode dan pendampingan ustadz di Ash-Shiddiq. Terima kasih, Pondok Ash-Shiddiq.',
         2, 1
  UNION ALL SELECT 'Hj. Dedeh Kurniasih', 'Wali Santri — Angkatan 2022',
         'Lingkungannya aman, asramanya bersih, dan para ustadz serta ustadzah sangat perhatian. Anak saya betah, bahkan tidak ingin pulang saat libur.',
         3, 1
) AS dummy
WHERE NOT EXISTS (SELECT 1 FROM `testimoni`);
