-- add revoked status and phone/status index for fa_info (idempotent)
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_info'
       AND COLUMN_NAME = 'status'
       AND COLUMN_TYPE LIKE '%''revoked''%') = 0,
    'ALTER TABLE `fa_info` MODIFY COLUMN `status` enum(''draft'',''pending'',''published'',''rejected'',''expired'',''revoked'') NOT NULL DEFAULT ''draft'' COMMENT ''状态:draft=草稿,pending=待审核,published=已发布,rejected=已拒绝,expired=已过期,revoked=已撤销''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(1)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'fa_info'
       AND INDEX_NAME = 'idx_verify_phone_status') = 0,
    'ALTER TABLE `fa_info` ADD INDEX `idx_verify_phone_status` (`verify_phone`, `status`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
