SET NAMES utf8mb4;

-- Tabel pengurus (dikelola admin, ditampilkan di halaman profil)

CREATE TABLE IF NOT EXISTS `pengurus` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(150) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `level` ENUM('mudir','wakil','sekretariat','unit') NOT NULL DEFAULT 'unit',
  `foto` VARCHAR(255) NOT NULL DEFAULT '',
  `urutan` INT NOT NULL DEFAULT 0,
  `is_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_level` (`level`),
  INDEX `idx_urutan` (`urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9 pengurus awal (hanya jika tabel masih kosong; satu statement atomik,
-- aman dijalankan ulang dan tidak menghidupkan kembali data yang sudah dihapus admin)
INSERT INTO `pengurus` (`nama`, `jabatan`, `level`, `urutan`, `is_aktif`)
SELECT * FROM (
  SELECT 'KH. Suroto Abu Nizam, M.Pd.' AS `nama`, 'Mudir / Pimpinan Pesantren' AS `jabatan`,
         'mudir' AS `level`, 1 AS `urutan`, 1 AS `is_aktif`
  UNION ALL SELECT 'Nur Wahyudi, S.Pd.', 'Wakil Mudir',
         'wakil', 2, 1
  UNION ALL SELECT 'Fahmi Dwi Payana, S.H.', 'Sekretaris',
         'sekretariat', 3, 1
  UNION ALL SELECT 'Nurwidi Sasongko, S.Pd.', 'Bendahara',
         'sekretariat', 4, 1
  UNION ALL SELECT 'Rahmat Yulianto, S.H.', 'Kesantrian',
         'unit', 5, 1
  UNION ALL SELECT 'Rohmad Sigid Affandi, S.H.', 'Sarpras',
         'unit', 6, 1
  UNION ALL SELECT 'Ina Rusiana, S.Pd., Gr.', 'Kepala SMP',
         'unit', 7, 1
  UNION ALL SELECT 'Ahmad Nurdin Kholilis, S.Th.I., M.Pd.', 'Kepala SMA',
         'unit', 8, 1
  UNION ALL SELECT 'Muhammad Abdullah', 'Humas',
         'unit', 9, 1
) AS dummy
WHERE NOT EXISTS (SELECT 1 FROM `pengurus`);
