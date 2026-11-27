SET NAMES utf8mb4;

-- ============================================================
-- Migration 031: Konversi nomor_daftar lama ke format 9 digit NIPD
-- Format: YY (psb_tahun) + 14 (Baru) + 07/10 (SMP/SMA) + NNN (>= 339)
-- Aman dijalankan ulang (idempotent): hanya baris non-9-digit yang diubah.
-- ============================================================

SET @yy := COALESCE(
  (SELECT CASE WHEN TRIM(`value`) REGEXP '^20[0-9]{2}' THEN SUBSTRING(TRIM(`value`), 3, 2) ELSE NULL END
   FROM `pengaturan` WHERE `key_name` = 'psb_tahun' LIMIT 1),
  DATE_FORMAT(CURDATE(), '%y')
);

SET @urut := GREATEST(
  338,
  COALESCE(
    (SELECT MAX(CAST(SUBSTRING(`nomor_daftar`, 7) AS UNSIGNED))
     FROM `pendaftaran`
     WHERE `nomor_daftar` REGEXP '^[0-9]{9,}$'),
    0
  )
);

UPDATE `pendaftaran`
SET `nomor_daftar` = CONCAT(
  @yy,
  '14',
  CASE LOWER(`jenjang`) WHEN 'sd' THEN '01' WHEN 'smp' THEN '07' WHEN 'sma' THEN '10' ELSE '07' END,
  LPAD((@urut := @urut + 1), 3, '0')
)
WHERE `nomor_daftar` NOT REGEXP '^[0-9]{9,}$'
ORDER BY `id` ASC;
