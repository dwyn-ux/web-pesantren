SET NAMES utf8mb4;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alumni' AND COLUMN_NAME='orientasi'),
  'SELECT 1',
  'ALTER TABLE `alumni` ADD COLUMN `orientasi` ENUM(''portrait'',''landscape'') NOT NULL DEFAULT ''landscape'' AFTER `foto`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
