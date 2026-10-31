SET NAMES utf8mb4;

SET @activity_type = (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'alumni'
    AND COLUMN_NAME = 'aktivitas'
  LIMIT 1
);
SET @ddl = IF(
  LOCATE('kuliah_dan_bekerja', COALESCE(@activity_type, '')) > 0,
  'SELECT 1',
  'ALTER TABLE `alumni` MODIFY `aktivitas` ENUM(''kuliah'',''bekerja'',''kuliah_dan_bekerja'') NOT NULL'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
