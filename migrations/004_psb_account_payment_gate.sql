SET NAMES utf8mb4;
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='status_pembayaran'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `status_pembayaran` ENUM(''belum'',''menunggu'',''lunas'',''ditolak'') NOT NULL DEFAULT ''belum'' AFTER `nominal_biaya`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `pendaftaran` SET `status_pembayaran`='menunggu'
WHERE `status_pembayaran`='belum' AND EXISTS (
  SELECT 1 FROM `berkas_santri` b WHERE b.`pendaftaran_id`=`pendaftaran`.`id` AND b.`jenis`='bukti-bayar'
);
