-- 2026-02-27
-- Member management menus for points exchange + recharge cards
-- Idempotent inserts

SET NAMES utf8mb4;
SET @now = UNIX_TIMESTAMP();

SELECT id INTO @user_pid
FROM fa_auth_rule
WHERE name = 'user'
LIMIT 1;

-- user/exchangegoods
INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @user_pid, 'user/exchangegoods', '积分商品管理', 'fa fa-gift', '', '', '', 1, @now, @now, 9, 'normal'
FROM DUAL
WHERE @user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods');

SELECT id INTO @exchangegoods_pid
FROM fa_auth_rule
WHERE name = 'user/exchangegoods'
LIMIT 1;

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangegoods_pid, 'user/exchangegoods/index', '查看', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangegoods_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods/index');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangegoods_pid, 'user/exchangegoods/add', '添加', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangegoods_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods/add');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangegoods_pid, 'user/exchangegoods/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangegoods_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods/edit');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangegoods_pid, 'user/exchangegoods/del', '删除', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangegoods_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods/del');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangegoods_pid, 'user/exchangegoods/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangegoods_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangegoods/multi');

-- user/exchangeorder
INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @user_pid, 'user/exchangeorder', '积分兑换订单', 'fa fa-list-alt', '', '', '', 1, @now, @now, 8, 'normal'
FROM DUAL
WHERE @user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder');

SELECT id INTO @exchangeorder_pid
FROM fa_auth_rule
WHERE name = 'user/exchangeorder'
LIMIT 1;

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/index', '查看', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/index');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/add', '添加', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/add');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/edit');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/del', '删除', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/del');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/multi');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/deliver', '交付发码', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/deliver');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/ship', '实物发货', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/ship');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @exchangeorder_pid, 'user/exchangeorder/reject', '取消驳回', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @exchangeorder_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/exchangeorder/reject');

-- user/rechargecard
INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @user_pid, 'user/rechargecard', '充值卡管理', 'fa fa-ticket', '', '', '', 1, @now, @now, 7, 'normal'
FROM DUAL
WHERE @user_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard');

SELECT id INTO @rechargecard_pid
FROM fa_auth_rule
WHERE name = 'user/rechargecard'
LIMIT 1;

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/index', '查看', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/index');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/add', '添加', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/add');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/edit');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/del', '删除', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/del');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/multi', '批量更新', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/multi');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/generate', '批量生成', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/generate');

INSERT INTO fa_auth_rule
(`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file', @rechargecard_pid, 'user/rechargecard/export', '导出', 'fa fa-circle-o', '', '', '', 0, @now, @now, 0, 'normal'
FROM DUAL
WHERE @rechargecard_pid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fa_auth_rule WHERE name = 'user/rechargecard/export');
