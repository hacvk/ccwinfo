-- =============================================
-- 步骤 1.2：分类信息-分类管理 菜单权限 SQL
-- 请手动在 fd88 数据库执行
-- =============================================

-- 先查看当前最大的 fa_auth_rule id，确保不冲突
-- SELECT MAX(id) FROM fa_auth_rule;

-- 一级菜单：分类信息系统
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', 0, 'info', '分类信息系统', 'fa fa-newspaper-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @info_pid = LAST_INSERT_ID();

-- 二级菜单：分类管理
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/category', '分类管理', 'fa fa-list', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @cat_pid = LAST_INSERT_ID();

-- 三级权限节点：分类管理下的操作
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @cat_pid, 'info/category/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/selectpage', 'Selectpage', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
