-- =============================================
-- 步骤 1.3：分类信息-自定义字段管理 菜单权限 SQL
-- 请手动在 fd88 数据库执行
-- =============================================
-- 注意：需要先获取"分类信息系统"一级菜单的 id
-- 如果步骤 1.2 的菜单SQL已执行，可用以下语句查询：
-- SELECT id FROM fa_auth_rule WHERE name = 'info' AND pid = 0;

-- 在"分类信息系统"下插入"自定义字段"二级菜单
SET @info_pid = (SELECT id FROM fa_auth_rule WHERE name = 'info' AND pid = 0 LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES ('file', @info_pid, 'info/field', '自定义字段', 'fa fa-puzzle-piece', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @field_pid = LAST_INSERT_ID();

-- 三级权限节点
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', @field_pid, 'info/field/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @field_pid, 'info/field/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
