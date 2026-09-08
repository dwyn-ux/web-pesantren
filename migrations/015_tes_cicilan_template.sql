SET NAMES utf8mb4;

-- ============================================================
-- Migration 015: Tes pemetaan + tes hafalan + cicilan
-- + Status enum diperluas + tabel template_dokumen
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- ── 1. Tes Pemetaan (semua jalur) ──────────────────────────
CREATE TABLE IF NOT EXISTS `tes_pemetaan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` INT UNSIGNED NOT NULL,
  `tanggal_tes` DATE NOT NULL,
  `nilai_total` DECIMAL(5,2) DEFAULT NULL,
  `status_lulus` ENUM('lulus','tidak-lulus','mengulang') NOT NULL DEFAULT 'mengulang',
  `catatan_panitia` TEXT DEFAULT NULL,
  `diinput_oleh` INT UNSIGNED DEFAULT NULL COMMENT 'admin user id',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tes_pendaftaran` (`pendaftaran_id`),
  CONSTRAINT `fk_tes_pemetaan_pendaftaran`
    FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Tes Hafalan (khusus jalur tahfidz) ──────────────────
CREATE TABLE IF NOT EXISTS `tes_hafalan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` INT UNSIGNED NOT NULL,
  `tanggal_tes` DATE NOT NULL,
  `juz_dinilai` TINYINT UNSIGNED NOT NULL COMMENT '1-30',
  `status_lulus` ENUM('lulus','tidak-lulus','mengulang') NOT NULL DEFAULT 'mengulang',
  `penguji` VARCHAR(100) DEFAULT NULL,
  `catatan` TEXT DEFAULT NULL,
  `diinput_oleh` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_hafalan_pendaftaran` (`pendaftaran_id`),
  CONSTRAINT `fk_tes_hafalan_pendaftaran`
    FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Cicilan / angsuran ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `pembiayaan_cicilan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pembiayaan_id` INT UNSIGNED NOT NULL,
  `angsuran_ke` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `nominal` DECIMAL(12,2) NOT NULL,
  `tanggal_bayar` DATE NOT NULL,
  `metode` ENUM('tunai','transfer','virtual-account') NOT NULL DEFAULT 'transfer',
  `bukti_file` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `catatan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cicilan_pembiayaan` (`pembiayaan_id`, `status`),
  CONSTRAINT `fk_cicilan_pembiayaan`
    FOREIGN KEY (`pembiayaan_id`) REFERENCES `pembiayaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Status enum diperluas (5 fase) ─────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'
      AND COLUMN_NAME='status' AND COLUMN_TYPE LIKE '%menunggu-verifikasi%'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` MODIFY COLUMN `status`
    ENUM(''pending'',''menunggu-verifikasi'',''tes-selesai'',''diterima'',''ditolak'',''daftar-ulang'')
    NOT NULL DEFAULT ''pending'''
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. Tabel template_dokumen ──────────────────────────────
CREATE TABLE IF NOT EXISTS `template_dokumen` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `judul` VARCHAR(200) NOT NULL,
  `isi_html` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed template dengan placeholder (idempotent via IGNORE)
INSERT IGNORE INTO `template_dokumen` (`slug`,`judul`,`isi_html`) VALUES
('rekomendasi-kaderisasi','Surat Rekomendasi Tokoh Agama (Kaderisasi)','<div style=\"font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;\"><div style=\"text-align:center;border-bottom:3px double #0d7a4a;padding-bottom:15px;margin-bottom:25px;\"><h2 style=\"margin:0;color:#0d7a4a;\">SURAT REKOMENDASI TOKOH AGAMA</h2><h3 style=\"margin:5px 0;\">Jalur Kaderisasi Pondok Pesantren Ash-Shiddiq</h3></div><p>Yang bertanda tangan di bawah ini:</p><p>Nama: ____________________________<br>Jabatan: ____________________________<br>Instansi/Lembaga: ____________________________</p><p>Dengan ini merekomendasikan calon atas nama ____________________________ untuk mengikuti seleksi Penerimaan Santri Baru (PSB) Pondok Pesantren Ash-Shiddiq melalui <strong>Jalur Kaderisasi</strong> TA 2027/2028.</p><p>Demikian surat rekomendasi ini dibuat dengan sebenarnya.</p><p style=\"text-align:right;margin-top:40px;\">__________________, ____/____/2026<br>Tokoh Agama,<br><br><br>( ____________________________ )</p></div>'),
('mou-kaderisasi','MOU Jalur Kaderisasi','<div style=\"font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;\"><div style=\"text-align:center;border-bottom:3px double #0d7a4a;padding-bottom:15px;margin-bottom:25px;\"><h2 style=\"margin:0;color:#0d7a4a;\">PERJANJIAN KERJASAMA (MOU)</h2><h3 style=\"margin:5px 0;\">Jalur Kaderisasi TA 2027/2028</h3></div><p>Antara Pondok Pesantren Ash-Shiddiq (Pihak Pertama), Calon Santri (Pihak Kedua), dan Orang Tua/Wali (Pihak Ketiga).</p><h4>Pasal 1 — Pendidikan</h4><p>Pendidikan berlangsung 6 tahun berturut-turut.</p><h4>Pasal 2 — Biaya</h4><p>ADM Awal: Rp 5.000.000. SPP Putra: Rp 650.000/bulan. SPP Putri: Rp 750.000/bulan (sudah termasuk laundry + infak).</p><h4>Pasal 3 — Pengabdian</h4><p>Wajib mengabdi 1 tahun setelah lulus.</p><h4>Pasal 4 — Konsekuensi</h4><p>Apabila mengundurkan diri, wajib mengganti selisih biaya yang telah disubsidi.</p><p style=\"margin-top:40px;\">Materai Rp 10.000<br><br><br><br><strong>Pihak Pertama</strong> &nbsp;&nbsp;&nbsp; <strong>Pihak Kedua</strong> &nbsp;&nbsp;&nbsp; <strong>Pihak Ketiga</strong></p></div>'),
('rekomendasi-alumni','Surat Rekomendasi Alumni SDMUA','<div style=\"font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;\"><div style=\"text-align:center;border-bottom:3px double #0d7a4a;padding-bottom:15px;margin-bottom:25px;\"><h2 style=\"margin:0;color:#0d7a4a;\">SURAT REKOMENDASI ALUMNI</h2><h3 style=\"margin:5px 0;\">SD Muhammadiyah Unggulan Ashidiq</h3></div><p>Yang bertanda tangan di bawah ini Kepala SD Muhammadiyah Unggulan Ashidiq, menerangkan bahwa:</p><p>Nama Siswa: ____________________________<br>Tahun Lulus: ____________________________<br>No. Induk: ____________________________</p><p>Adalah benar siswa alumni SD Muhammadiyah Unggulan Ashidiq yang akan melanjutkan ke jenjang berikutnya di Pondok Pesantren Ash-Shiddiq melalui Jalur Alumni.</p><p style=\"text-align:right;margin-top:40px;\">__________________, ____/____/2026<br>Kepala Sekolah,<br><br><br>( ____________________________ )</p></div>'),
('rekomendasi-dhuafa','Surat Rekomendasi PCM/PDM (Dhuafa)','<div style=\"font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;\"><div style=\"text-align:center;border-bottom:3px double #0d7a4a;padding-bottom:15px;margin-bottom:25px;\"><h2 style=\"margin:0;color:#0d7a4a;\">SURAT REKOMENDASI</h2><h3 style=\"margin:5px 0;\">PCM / PDM (Untuk Jalur Dhuafa)</h3></div><p>Yang bertanda tangan di bawah ini:</p><p>Nama: ____________________________<br>Jabatan: ____________________________<br>Instansi: PCM / PDM _________________</p><p>Merekomendasikan calon berikut untuk mengikuti Jalur Dhuafa pada PSB Pondok Pesantren Ash-Shiddiq TA 2027/2028:</p><p>Nama: ____________________________<br>Alamat: ____________________________</p><p>Berdasarkan data yang ada, keluarga yang bersangkutan termasuk dalam kategori keluarga pra-sejahtera dan layak dibantu.</p><p style=\"text-align:right;margin-top:40px;\">__________________, ____/____/2026<br>Ketua PCM/PDM,<br><br><br>( ____________________________ )</p></div>'),
('pernyataan-dhuafa','Surat Pernyataan (Dhuafa)','<div style=\"font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;\"><div style=\"text-align:center;border-bottom:3px double #0d7a4a;padding-bottom:15px;margin-bottom:25px;\"><h2 style=\"margin:0;color:#0d7a4a;\">SURAT PERNYATAAN</h2><h3 style=\"margin:5px 0;\">Jalur Dhuafa</h3></div><p>Saya yang bertanda tangan di bawah ini:</p><p>Nama: ____________________________<br>NIK: ____________________________<br>Alamat: ____________________________</p><p>Selaku orang tua/wali dari calon:</p><p>Nama: ____________________________<br>TTL: ____________________________</p><p>Menyatakan dengan sesungguhnya bahwa:</p><ol><li>Data yang saya berikan adalah benar dan dapat dipertanggungjawabkan.</li><li>Keluarga saya termasuk kategori keluarga pra-sejahtera sesuai SKTM yang dilampirkan.</li><li>Saya bersedia memberikan keterangan tambahan yang diminta Panitia.</li><li>Apabila terbukti tidak benar, saya bersedia menerima konsekuensi termasuk pembatalan status penerimaan.</li></ol><p style=\"text-align:right;margin-top:40px;\">__________________, ____/____/2026<br>Yang membuat pernyataan,<br>Materai Rp 10.000<br><br><br>( ____________________________ )</p></div>');
