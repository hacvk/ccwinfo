-- =============================================
-- 步骤 2.2：本地攻略系统（guide）后台菜单与权限 SQL
-- 目标库：fd88（表前缀 fa_）
-- =============================================

SET NAMES utf8mb4;

-- 一级菜单：本地攻略系统
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', 0, 'guide', '本地攻略系统', 'fa fa-map-signs', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @guide_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide' LIMIT 1);

-- 二级菜单：分类管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/category', '分类管理', 'fa fa-list', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 'normal');

SET @guide_category_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/category' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_category_pid, 'guide/category/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_category_pid, 'guide/category/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_category_pid, 'guide/category/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_category_pid, 'guide/category/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_category_pid, 'guide/category/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_category_pid, 'guide/category/selectpage', 'Selectpage', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 二级菜单：模型管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/model', '模型管理', 'fa fa-cubes', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 9, 'normal');

SET @guide_model_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/model' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_model_pid, 'guide/model/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_model_pid, 'guide/model/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_model_pid, 'guide/model/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_model_pid, 'guide/model/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_model_pid, 'guide/model/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 二级菜单：字段管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/field', '字段管理', 'fa fa-puzzle-piece', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 8, 'normal');

SET @guide_field_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/field' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_field_pid, 'guide/field/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_field_pid, 'guide/field/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_field_pid, 'guide/field/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_field_pid, 'guide/field/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_field_pid, 'guide/field/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 二级菜单：标签管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/tag', '标签管理', 'fa fa-tags', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 7, 'normal');

SET @guide_tag_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/tag' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_tag_pid, 'guide/tag/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_tag_pid, 'guide/tag/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_tag_pid, 'guide/tag/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_tag_pid, 'guide/tag/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_tag_pid, 'guide/tag/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_tag_pid, 'guide/tag/selectpage', 'Selectpage', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 二级菜单：攻略管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/guide', '攻略管理', 'fa fa-map-signs', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 6, 'normal');

SET @guide_guide_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/guide' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_guide_pid, 'guide/guide/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_guide_pid, 'guide/guide/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_guide_pid, 'guide/guide/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_guide_pid, 'guide/guide/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_guide_pid, 'guide/guide/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_guide_pid, 'guide/guide/getFields', '动态字段接口', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 二级菜单：专题管理
INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_pid, 'guide/topic', '专题管理', 'fa fa-th-large', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 5, 'normal');

SET @guide_topic_pid = (SELECT id FROM `fa_auth_rule` WHERE `name` = 'guide/topic' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @guide_topic_pid, 'guide/topic/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_topic_pid, 'guide/topic/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_topic_pid, 'guide/topic/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_topic_pid, 'guide/topic/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @guide_topic_pid, 'guide/topic/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SELECT 'guide 菜单导入完成' AS message;

