-- 为 fa_info 增加发布IP与归属地字段（幂等）
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_info' AND COLUMN_NAME='publish_ip') = 0,
    'ALTER TABLE `fa_info` ADD COLUMN `publish_ip` varchar(45) NOT NULL DEFAULT '''' COMMENT ''发布IP(原始)'' AFTER `verify_phone`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fa_info' AND COLUMN_NAME='publish_ip_region') = 0,
    'ALTER TABLE `fa_info` ADD COLUMN `publish_ip_region` varchar(128) NOT NULL DEFAULT '''' COMMENT ''发布IP归属地'' AFTER `publish_ip`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

