-- =============================================
-- 步骤 1.4：分类信息-信息管理 菜单权限 SQL
-- 请手动在 fd88 数据库执行
-- =============================================

-- 获取"分类信息系统"一级菜单 id
SET @info_pid = (SELECT id FROM fa_auth_rule WHERE name = 'info' AND pid = 0 LIMIT 1);

-- 二级菜单：信息管理
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/info', '信息管理', 'fa fa-file-text-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @info_entry_pid = LAST_INSERT_ID();

-- 三级权限节点
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @info_entry_pid, 'info/info/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
