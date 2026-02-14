-- =============================================
-- 步骤 1.6：分类信息系统-完整菜单权限 SQL（汇总版）
-- 请手动在 fd88 数据库执行
-- =============================================
-- 包含：1.2 分类管理、1.3 自定义字段、1.4 信息管理、1.5 审核中心
-- =============================================

SET NAMES utf8mb4;

-- ----------------------------
-- 一级菜单：分类信息系统
-- ----------------------------
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', 0, 'info', '分类信息系统', 'fa fa-newspaper-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @info_pid = LAST_INSERT_ID();

-- ----------------------------
-- 二级菜单：分类管理
-- ----------------------------
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/category', '分类管理', 'fa fa-list', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 'normal');

SET @cat_pid = LAST_INSERT_ID();

INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @cat_pid, 'info/category/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @cat_pid, 'info/category/selectpage', 'Selectpage', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- ----------------------------
-- 二级菜单：自定义字段
-- ----------------------------
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/field', '自定义字段', 'fa fa-puzzle-piece', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 9, 'normal');

SET @field_pid = LAST_INSERT_ID();

INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @field_pid, 'info/field/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- ----------------------------
-- 二级菜单：信息管理
-- ----------------------------
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/info', '信息管理', 'fa fa-file-text-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 8, 'normal');

SET @info_entry_pid = LAST_INSERT_ID();

INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @info_entry_pid, 'info/info/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @info_entry_pid, 'info/info/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- ----------------------------
-- 二级菜单：审核中心
-- ----------------------------
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/review', '审核中心', 'fa fa-check-square-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 7, 'normal');

SET @review_pid = LAST_INSERT_ID();

INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @review_pid, 'info/review/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @review_pid, 'info/review/approve', '通过审核', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @review_pid, 'info/review/reject', '拒绝审核', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 执行完成提示
SELECT '菜单权限导入完成，请到后台【权限管理】→【菜单规则】查看' AS message;
