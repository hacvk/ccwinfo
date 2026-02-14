-- =============================================
-- 步骤 1.5：分类信息-审核中心 菜单权限 SQL
-- 请手动在 fd88 数据库执行
-- =============================================

-- 获取"分类信息系统"一级菜单 id
SET @info_pid = (SELECT id FROM fa_auth_rule WHERE name = 'info' AND pid = 0 LIMIT 1);

-- 二级菜单：审核中心
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/review', '审核中心', 'fa fa-check-square-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @review_pid = LAST_INSERT_ID();

-- 三级权限节点
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @review_pid, 'info/review/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @review_pid, 'info/review/approve', '通过审核', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @review_pid, 'info/review/reject', '拒绝审核', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
