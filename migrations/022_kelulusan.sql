SET NAMES utf8mb4;

-- ============================================================
-- Migration 022: Kelulusan santri — surat, TTD, berkas susulan
--   - pendaftaran: + agama, kelas, ttd_santri, ttd_wali
--   - berkas_santri.jenis: + kip, skl (opsional, beda slot dgn ijazah)
--   - pendaftaran.jenjang: hapus tahfidz-intensif (sisa smp/sma)
--   - tabel notif_log (jejak kirim WA opsi B)
--   - pengaturan: nama_direktur, ttd_direktur
--   - seed 4 template: kesanggupan-biaya-smp/sma, pernyataan-santri/wali
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- ── 0. Petakan baris lama tahfidz-intensif → smp ─────────────
UPDATE `pendaftaran` SET `jenjang` = 'smp'
  WHERE `jenjang` = 'tahfidz-intensif';

-- ── 1. Kolom baru pendaftaran ─────────────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='agama'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `agama` VARCHAR(30) DEFAULT NULL AFTER `jenis_kelamin`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='kelas'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `kelas` VARCHAR(5) DEFAULT NULL AFTER `jenjang`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='ttd_santri'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `ttd_santri` VARCHAR(255) DEFAULT NULL AFTER `kesanggupan_sign`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='ttd_wali'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `ttd_wali` VARCHAR(255) DEFAULT NULL AFTER `ttd_santri`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Jenjang: smp/sma saja ──────────────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'
      AND COLUMN_NAME='jenjang' AND COLUMN_TYPE LIKE '%tahfidz-intensif%'),
  'ALTER TABLE `pendaftaran` MODIFY COLUMN `jenjang` ENUM(''smp'',''sma'') NOT NULL',
  'SELECT 1'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. berkas_santri.jenis: + kip, skl ───────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='berkas_santri'
      AND COLUMN_NAME='jenis'
      AND COLUMN_TYPE LIKE '%kip%'
      AND COLUMN_TYPE LIKE '%skl%'),
  'SELECT 1',
  'ALTER TABLE `berkas_santri` MODIFY COLUMN `jenis` ENUM(
    ''kartu-keluarga'',''akta-lahir'',''ijazah'',''foto'',''bukti-bayar'',''lainnya'',
    ''ktp-ortu'',''sertifikat-tka'',''sertifikat-tahfidz'',
    ''surat-rekomendasi'',''sktm'',''surat-pernyataan'',''mou-kaderisasi'',
    ''kip'',''skl''
  ) NOT NULL'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Tabel notif_log (jejak tombol Kirim WA) ────────────────
CREATE TABLE IF NOT EXISTS `notif_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` INT UNSIGNED NOT NULL,
  `kanal` ENUM('wa') NOT NULL DEFAULT 'wa',
  `nomor` VARCHAR(20) NOT NULL,
  `isi` TEXT NOT NULL,
  `status` ENUM('klik-kirim','terkirim','gagal') NOT NULL DEFAULT 'klik-kirim',
  `oleh` INT UNSIGNED DEFAULT NULL COMMENT 'admin user id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notif_pendaftar` (`pendaftaran_id`),
  CONSTRAINT `fk_notif_pendaftaran` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Pengaturan direktur ────────────────────────────────────
INSERT INTO `pengaturan` (`key_name`,`value`,`label`)
SELECT * FROM (SELECT 'nama_direktur' AS key_name, 'Suroto Abu Nizam, M.Pd' AS `value`, 'Nama Direktur (tanda tangan surat)' AS label) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pengaturan` WHERE `key_name`='nama_direktur');

INSERT INTO `pengaturan` (`key_name`,`value`,`label`)
SELECT * FROM (SELECT 'ttd_direktur' AS key_name, '' AS `value`, 'File stempel/TTD direktur di assets/img/kop-surat/' AS label) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `pengaturan` WHERE `key_name`='ttd_direktur');

-- ── 6. Seed 4 template surat kelulusan ────────────────────────
INSERT IGNORE INTO `template_dokumen` (`slug`,`judul`,`isi_html`) VALUES
('kesanggupan-biaya-smp','Komitmen Pembiayaan — SMP','<div style=''font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;''><div style=''text-align:center;''><h2 style=''margin:0;''>PONDOK PESANTREN ASH-SHIDDIQ</h2><p style=''margin:2px 0;''>LEMBAGA SOSIAL, PENDIDIKAN DAN DAKWAH</p><p style=''margin:2px 0;font-size:13px;''>Sekretariat : Purworejo, Jurangjero, Ngawen, Gunungkidul, D.I. Yogyakarta<br>KodePos : 55853. Telp/WA : +6285642010902, +62 812-7757-0669</p></div><hr><div style=''text-align:center;''><h3 style=''margin:6px 0;''>SURAT PERNYATAAN<br>KOMITMEN PEMBIAYAAN PENDIDIKAN<br>PONDOK PESANTREN ASH-SHIDDIQ TAHUN AJARAN __TAHUN_AJARAN__</h3></div><p>Segala pujian hanya milik Allah, sholawat dan salam semoga terus tercurah kepada Nabi Muhammad Saw. Dengan semata mengharap ridha Allah SWT. Kami:</p><table><tr><td>Nama</td><td>: __NAMA_WALI__</td></tr><tr><td>Alamat</td><td>: __ALAMAT__</td></tr><tr><td>No. Telp/HP</td><td>: __HP_ORTU__</td></tr><tr><td>Wali Dari</td><td>: __NAMA__</td></tr><tr><td>Kelas</td><td>: __KELAS__</td></tr><tr><td>No. Pendaftaran</td><td>: __NOMOR_DAFTAR__</td></tr></table><p>Menyadari akan pentingnya amal jariyah dalam pendidikan, maka dengan penuh keikhlasan dan kerelaan hati, kami menyatakan kesediaan menyalurkan harta kami untuk penyelenggaraan pendidikan anak kami di SMP Muhammadiyah Unggulan Ashidiq, dengan rincian sebagai berikut:</p>__RINCIAN_BIAYA__<p>Demikian pernyataan ini dibuat, semoga Allah swt. berkenan mencatat usaha kecil ini sebagai amal jariyah kami. Apabila dikemudian hari, kami tidak bisa melaksanakan komitmen ini, kami siap menerima konsekuensi dari sekolah. Jazakumullahu khairan.</p><p style=''text-align:right;''>Ngawen, __TANGGAL_CETAK__<br>Orang tua / Wali<br><br>__TTD_WALI__<br>Materai Rp 10.000<br><br>(__NAMA_WALI__)</p></div>'),
('kesanggupan-biaya-sma','Komitmen Pembiayaan — SMA','<div style=''font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;''><div style=''text-align:center;''><h2 style=''margin:0;''>PONDOK PESANTREN ASH-SHIDDIQ</h2><p style=''margin:2px 0;''>LEMBAGA SOSIAL, PENDIDIKAN DAN DAKWAH</p><p style=''margin:2px 0;font-size:13px;''>Sekretariat : Purworejo, Jurangjero, Ngawen, Gunungkidul, D.I. Yogyakarta<br>KodePos : 55853. Telp/WA : +6285642010902, +62 812-7757-0669</p></div><hr><div style=''text-align:center;''><h3 style=''margin:6px 0;''>SURAT PERNYATAAN<br>KOMITMEN PEMBIAYAAN PENDIDIKAN<br>PONDOK PESANTREN ASH-SHIDDIQ TAHUN AJARAN __TAHUN_AJARAN__</h3></div><p>Segala pujian hanya milik Allah, sholawat dan salam semoga terus tercurah kepada Nabi Muhammad Saw. Dengan semata mengharap ridha Allah SWT. Kami:</p><table><tr><td>Nama</td><td>: __NAMA_WALI__</td></tr><tr><td>Alamat</td><td>: __ALAMAT__</td></tr><tr><td>No. Telp/HP</td><td>: __HP_ORTU__</td></tr><tr><td>Wali Dari</td><td>: __NAMA__</td></tr><tr><td>Kelas</td><td>: __KELAS__</td></tr><tr><td>No. Pendaftaran</td><td>: __NOMOR_DAFTAR__</td></tr></table><p>Menyadari akan pentingnya amal jariyah dalam pendidikan, maka dengan penuh keikhlasan dan kerelaan hati, kami menyatakan kesediaan menyalurkan harta kami untuk penyelenggaraan pendidikan anak kami di SMA Pondok Pesantren Ash-Shiddiq, dengan rincian sebagai berikut:</p>__RINCIAN_BIAYA__<p>Demikian pernyataan ini dibuat, semoga Allah swt. berkenan mencatat usaha kecil ini sebagai amal jariyah kami. Apabila dikemudian hari, kami tidak bisa melaksanakan komitmen ini, kami siap menerima konsekuensi dari sekolah. Jazakumullahu khairan.</p><p style=''text-align:right;''>Ngawen, __TANGGAL_CETAK__<br>Orang tua / Wali<br><br>__TTD_WALI__<br>Materai Rp 10.000<br><br>(__NAMA_WALI__)</p></div>'),
('pernyataan-santri','Surat Pernyataan Santri','<div style=''font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;''><div style=''text-align:center;''><h2 style=''margin:0;''>PONDOK PESANTREN ASH-SHIDDIQ</h2><p style=''margin:2px 0;''>LEMBAGA SOSIAL, PENDIDIKAN DAN DAKWAH</p><p style=''margin:2px 0;font-size:13px;''>Sekretariat : Purworejo, Jurangjero, Ngawen, Gunungkidul, D.I. Yogyakarta<br>KodePos : 55853. Telp/WA : +6285642010902, +62 812-7757-0669</p></div><hr><div style=''text-align:center;''><h3 style=''margin:6px 0;''>SURAT PERNYATAAN SANTRI PONDOK PESANTREN ASH-SHIDDIQ<br>TAHUN PELAJARAN __TAHUN_AJARAN__</h3></div><p>Yang bertanda tangan di bawah ini:</p><table><tr><td>Nama</td><td>: __NAMA__</td></tr><tr><td>Tempat, tanggal lahir</td><td>: __TTL__</td></tr><tr><td>Jenis Kelamin</td><td>: __JK__</td></tr><tr><td>Agama</td><td>: __AGAMA__</td></tr><tr><td>Nama Orang tua</td><td>: __ORTU__</td></tr><tr><td>Pekerjaan Orang tua</td><td>: __PEKERJAAN_ORTU__</td></tr></table><p>Dengan sungguh-sungguh dan penuh kesadaran : <strong>MENYATAKAN</strong> bahwa selama menjadi santri, saya :</p><ol><li>Siap taat dan patuh pada orang tua, ustadz/ustadzah, pembina dan peraturan, serta tata tertib yang berlaku di Pondok Pesantren Ash-Shiddiq.</li><li>Siap menuntut ilmu dengan sungguh-sungguh.</li><li>Siap dikembalikan kepada orang tua/wali jika sudah tidak bisa dididik atau dibimbing oleh guru, pembina dan pengurus Pondok Pesantren Ash-Shiddiq.</li></ol><p>Demikian pernyataan ini saya buat dengan sebenarnya dan penuh rasa tanggung jawab serta diketahui / disetujui orang tua / wali.</p><p style=''text-align:right;''>Hari/Tgl : __TANGGAL_CETAK__</p><table style=''width:100%;text-align:center;''><tr><td>Orang Tua / Wali<br><br>__TTD_WALI__<br><br>(__ORTU__)</td><td>Yang Membuat Pernyataan<br><br>__TTD_SANTRI__<br><br>(__NAMA__)</td></tr></table><p style=''text-align:center;''>Mengetahui,<br>Direktur Pondok Pesantren Ash-Shiddiq<br><br><br><br><strong><u>__NAMA_DIREKTUR__</u></strong></p></div>'),
('pernyataan-wali','Surat Pernyataan Wali Santri','<div style=''font-family:Times New Roman,serif;max-width:700px;margin:auto;padding:40px;line-height:1.7;''><div style=''text-align:center;''><h2 style=''margin:0;''>PONDOK PESANTREN ASH-SHIDDIQ</h2><p style=''margin:2px 0;''>LEMBAGA SOSIAL, PENDIDIKAN DAN DAKWAH</p><p style=''margin:2px 0;font-size:13px;''>Sekretariat : Purworejo, Jurangjero, Ngawen, Gunungkidul, D.I. Yogyakarta<br>KodePos : 55853. Telp/WA : +6285642010902, +62 812-7757-0669</p></div><hr><div style=''text-align:center;''><h3 style=''margin:6px 0;''>SURAT PERNYATAAN WALI SANTRI<br>TAHUN PELAJARAN __TAHUN_AJARAN__</h3></div><p>Dengan bertawakkal kepada Allah SWT, kami yang bertanda tangan di bawah ini:</p><table><tr><td>Nama</td><td>: __NAMA_WALI__</td></tr><tr><td>No. Hp</td><td>: __HP_ORTU__</td></tr><tr><td>Wali Santri dari</td><td>: __NAMA__</td></tr><tr><td>Tempat Tanggal Lahir</td><td>: __TTL__</td></tr><tr><td>Alamat</td><td>: __ALAMAT__</td></tr></table><p>Menyatakan bahwa kami :</p><ol><li>Menyerahkan anak kami sepenuhnya kepada Pengasuh, Pembina dan Pengurus Pondok Pesantren Ash-Shiddiq untuk mendidik dan mengawasi menurut ajaran Agama Islam Ahlus Sunnah wal Jamaah dan Hukum Negara Kesatuan Republik Indonesia.</li><li>Tidak akan mencampuri sistem Pendidikan dan Pengajaran maupun urusan manajemen dan administrasi yang telah ditetapkan oleh Pimpinan Pondok Pesantren Ash-Shiddiq.</li><li>Ridha atas segala sanksi yang diberikan oleh pengasuh, pembina, kepala, pengurus, atau lembaga kepada anak kami, jika terbukti melakukan pelanggaran terhadap peraturan Pondok Pesantren Ash-Shiddiq.</li><li>Menerima dengan tulus ikhlas, atas pengembalian anak kami, jika sudah tidak sanggup untuk mentaati peraturan atau melampaui poin pelanggaran yang telah ditetapkan oleh Pondok Pesantren Ash-Shiddiq.</li><li>Siap mengajukan permohonan pengunduran diri anak kami kepada pihak Pondok Pesantren (Satuan Pendidikan), jika anak kami melakukan pelanggaran berat dan dikeluarkan dari Pondok Pesantren Ash-Shiddiq.</li><li>Tidak membawa pulang fasilitas kasur yang disediakan Pondok apabila mengundurkan diri sebelum dinyatakan Lulus dari pihak Pondok Pesantren Ash-Shiddiq.</li><li>Bertanggung jawab sepenuhnya atas biaya yang timbul selama anak kami tercatat sebagai santri Pondok Pesantren Ash-Shiddiq.</li><li>Jika anak kami telah resmi tercatat sebagai santri mulai saat pendaftaran dan telah mengikuti kegiatan pembelajaran, maka kami tidak akan meminta segala biaya yang telah dibayarkan kepada pihak Pondok Pesantren Ash-Shiddiq.</li><li>Menyelesaikan segala permasalahan secara kekeluargaan yang terjadi dengan keluarga besar Pondok Pesantren Ash-Shiddiq.</li><li>Sanggup menjaga nama baik almamater Pondok Pesantren Ash-Shiddiq.</li><li>Memintakan izin anak kami kepada pengasuh, Pembina dan pengurus Pondok Pesantren Ash-Shiddiq pada saat meninggalkan asrama, atau tidak mengikuti kegiatan Pembelajaran.</li><li>Ikut serta mendidik, mengawasi dan membina anak kami, baik ketika berada di rumah maupun di Pondok Pesantren Ash-Shiddiq.</li></ol><p>Demikian surat pernyataan ini kami buat dengan sebenarnya.</p><p>Wabillahi Taufiq Was Saadah.</p><p style=''text-align:right;''>Hari/Tgl : __TANGGAL_CETAK__</p><table style=''width:100%;text-align:center;''><tr><td>Direktur Pondok Pesantren Ash-Shiddiq<br><br><br><br><strong><u>__NAMA_DIREKTUR__</u></strong></td><td>Orangtua / Wali<br><br>__TTD_WALI__<br><br>(__NAMA_WALI__)</td></tr></table></div>');
