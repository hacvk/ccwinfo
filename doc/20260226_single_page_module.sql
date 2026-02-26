-- 单页模块：数据表 + 后台菜单
-- 目标库：fd88（前缀 fa_）

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `fa_single_page` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `parent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上级页面ID',
  `is_group` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否分组页:1是0否',
  `title` varchar(120) NOT NULL DEFAULT '' COMMENT '页面标题',
  `slug` varchar(64) DEFAULT NULL COMMENT 'URL标识',
  `slug_locked` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'URL是否锁定',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `content` longtext COMMENT '页面内容',
  `external_url` varchar(255) NOT NULL DEFAULT '' COMMENT '外链地址',
  `show_in_footer` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否页脚展示',
  `status` enum('draft','published','offline') NOT NULL DEFAULT 'draft' COMMENT '状态',
  `weigh` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `views` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
  `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_parent` (`parent_id`,`weigh`,`id`),
  KEY `idx_status` (`status`,`weigh`,`id`),
  KEY `idx_footer` (`show_in_footer`,`status`,`parent_id`,`is_group`,`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='单页内容表';

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', 0, 'page', '单页管理', 'fa fa-file-text-o', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @page_pid = (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'page' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @page_pid, 'page/page', '页面管理', 'fa fa-file-text', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @page_page_pid = (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'page/page' LIMIT 1);

INSERT IGNORE INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
('file', @page_page_pid, 'page/page/index', '查看', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @page_page_pid, 'page/page/add', '添加', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @page_page_pid, 'page/page/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @page_page_pid, 'page/page/del', '删除', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @page_page_pid, 'page/page/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', @page_page_pid, 'page/page/selectpage', 'Selectpage', 'fa fa-circle-o', '', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SELECT 'single_page module sql imported' AS message;
