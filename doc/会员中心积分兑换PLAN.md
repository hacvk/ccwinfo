## 会员中心积分兑换 + 充值卡/优惠卡一体化实施计划

### 摘要

本期将新增“前台积分兑换商城 + 后台兑换管理 + 后台充值卡管理”，并把充值页接入“充值卡支付 + 优惠卡加赠”。  
已锁定的关键口径如下：纯积分兑换、实物仅支持山东省地址、实物/站外虚拟下单锁库存、积分在交付时扣减、站内卡类虚拟商品自动发放、充值卡金额必须与所选充值金额一致、优惠卡按“加赠比例+最低充值+封顶”生效、优惠卡与充值卡不叠加、订单超时由后台人工处理（不做自动取消任务）。

### 技能使用

本任务不使用当前可用 skills（`skill-creator`/`skill-installer`），因为需求是业务模块落地规划，不是技能创建或安装。

### 变更范围（按模块）

1. 前台用户中心新增积分兑换商城与兑换记录页，支持提交实物/虚拟兑换订单。  
2. 后台会员管理新增三个菜单模块：积分商品管理、积分订单管理、充值卡管理。  
3. 前台充值页新增“充值卡”支付方式，并新增“优惠卡号+卡密”输入（仅微信/支付宝可用）。  
4. 数据层新增兑换商品、兑换订单、充值卡表，并扩展充值订单表支持优惠卡加赠与卡绑定追踪。  

### 关键文件改动清单

1. 前台充值链路：  
   [Recharge.php](D:/phpEnv/www/ccw88.com/application/index/controller/Recharge.php)  
   [recharge.html](D:/phpEnv/www/ccw88.com/application/index/view/recharge/recharge.html)  
   [recharge.js](D:/phpEnv/www/ccw88.com/public/assets/js/frontend/recharge.js)  
   [recharge.php(lang)](D:/phpEnv/www/ccw88.com/application/index/lang/zh-cn/recharge.php)

2. 前台积分兑换链路（新增）：  
   [Exchange.php](D:/phpEnv/www/ccw88.com/application/index/controller/Exchange.php)  
   [exchange/index.html](D:/phpEnv/www/ccw88.com/application/index/view/exchange/index.html)  
   [exchange/detail.html](D:/phpEnv/www/ccw88.com/application/index/view/exchange/detail.html)  
   [exchange/orders.html](D:/phpEnv/www/ccw88.com/application/index/view/exchange/orders.html)  
   [exchange.js](D:/phpEnv/www/ccw88.com/public/assets/js/frontend/exchange.js)  
   [exchange.php(lang)](D:/phpEnv/www/ccw88.com/application/index/lang/zh-cn/exchange.php)

3. 前台地址联动接口：  
   [Ajax.php](D:/phpEnv/www/ccw88.com/application/index/controller/Ajax.php)（新增 `area()`）

4. 用户中心导航：  
   [sidenav.html](D:/phpEnv/www/ccw88.com/application/index/view/common/sidenav.html)

5. 后台会员管理模块（新增）：  
   [Exchangegoods.php](D:/phpEnv/www/ccw88.com/application/admin/controller/user/Exchangegoods.php)  
   [Exchangeorder.php](D:/phpEnv/www/ccw88.com/application/admin/controller/user/Exchangeorder.php)  
   [Rechargecard.php](D:/phpEnv/www/ccw88.com/application/admin/controller/user/Rechargecard.php)  
   对应 `admin/view/user/*` 与 `public/assets/js/backend/user/*`、`admin/lang/zh-cn/user/*`

6. 公共模型/服务（新增）：  
   [ExchangeGoods.php](D:/phpEnv/www/ccw88.com/application/common/model/ExchangeGoods.php)  
   [ExchangeOrder.php](D:/phpEnv/www/ccw88.com/application/common/model/ExchangeOrder.php)  
   [RechargeCard.php](D:/phpEnv/www/ccw88.com/application/common/model/RechargeCard.php)  
   [ExchangeService.php](D:/phpEnv/www/ccw88.com/application/common/service/ExchangeService.php)  
   [RechargeCardService.php](D:/phpEnv/www/ccw88.com/application/common/service/RechargeCardService.php)

7. 充值插件配置与订单扩展：  
   [config.php](D:/phpEnv/www/ccw88.com/addons/recharge/config.php)（`paytypelist` 增加 `card`）  
   [Order.php](D:/phpEnv/www/ccw88.com/addons/recharge/library/Order.php)（支持 bonus/card 结算扩展）  
   [install.sql](D:/phpEnv/www/ccw88.com/addons/recharge/install.sql)（仅新装参考；增量以业务迁移脚本为准）

8. 路由与菜单迁移脚本：  
   [route.php](D:/phpEnv/www/ccw88.com/application/route.php)（补充会员中心兑换别名）  
   新增 SQL：  
   [D:/phpEnv/www/ccw88.com/doc/20260227_points_exchange_and_cards.sql]  
   [D:/phpEnv/www/ccw88.com/doc/20260227_user_menu_exchange_cards.sql]

### 公共接口/类型变更

1. 新表 `fa_recharge_card`。  
   字段核心：`card_no`、`card_secret`、`card_type(recharge|discount)`、`face_amount`、`bonus_rate`、`min_recharge`、`max_bonus`、`batch_no`、`owner_user_id`、`status(unused|reserved|issued|used|disabled)`、`expiretime`、`reserve_orderid`、`used_user_id`、`used_orderid`、`used_time`、`createtime`、`updatetime`。  

2. 新表 `fa_exchange_goods`。  
   字段核心：`title`、`goods_type(physical|virtual)`、`virtual_mode(internal_recharge_card|internal_discount_card|manual)`、`virtual_source(inside|outside)`、`points_cost`、`stock`、`locked_stock`、`per_user_limit`、`status`、`weigh`、`start_time`、`end_time`、`need_shipping`、`delivery_note`、时间戳。  

3. 新表 `fa_exchange_order`。  
   字段核心：`order_no`、`user_id`、`goods_id`、`goods_snapshot`、`points_cost`、`points_status(undeducted|deducted|refunded)`、`order_status(pending|delivered|shipped|completed|cancelled|rejected)`、`delivery_content`、收货地址字段（省市区ID+名称+详细地址+收件人+手机号）、`cancel_reason`、`admin_remark`、关键时间戳。  

4. 扩展 `fa_recharge_order`。  
   新增：`recharge_card_id`、`discount_card_id`、`bonus_amount`。  
   结算口径：最终入账金额 = `payamount + bonus_amount`。  

5. 新增前台接口（`index/exchange/*`）：  
   `GET mall`、`GET detail`、`POST submit`、`GET orders`、`POST cancel`。  
   `POST submit` 对实物单强制校验山东省。  

6. 新增后台动作接口：  
   积分订单的“发码交付/实物发货/取消”，充值卡“批量生成、启停、导出”。  

### 业务流程（决策完成版）

1. 兑换下单。  
   实物/站外虚拟：创建 `pending` 订单并锁库存（`stock-1, locked_stock+1`），不扣积分。  
   站内充值卡/优惠卡：下单即自动发放卡，订单直接 `delivered`，并立即扣积分（因为交付已发生）。  

2. 交付扣分。  
   `pending` 订单在后台交付动作时扣积分；若用户当前积分不足，则交付失败并保持 `pending`，由后台或用户取消。  

3. 取消规则。  
   用户可取消“未交付（pending）”订单；取消后释放锁库存。  
   若订单已扣积分（理论上仅已交付订单），取消需走后台驳回并返还积分。  

4. 山东地址限制。  
   前端地址选择为省市区+详细地址。  
   提交时前端先拦截，后端二次强校验 `province_name` 以“山东”开头；否则拒单。  

5. 充值卡支付。  
   充值页选择“充值卡”后必须输入卡号+卡密。  
   校验通过后要求“充值金额 == 卡面额”，成功即直接入账并核销充值卡。  

6. 优惠卡加赠。  
   仅在微信/支付宝支付时可输入优惠卡（独立字段，不与充值卡叠加）。  
   规则：`bonus = min(amount * bonus_rate, max_bonus)` 且 `amount >= min_recharge`。  
   优惠卡在发起支付时置 `reserved`，支付成功后转 `used`。  

### 后台菜单与权限

1. 在“会员管理(user)”下新增：  
   `user/exchangegoods`、`user/exchangeorder`、`user/rechargecard`。  
2. 每个菜单补齐 `index/add/edit/del/multi` 及自定义动作节点（交付/发货/批量生成/导出）。  
3. 通过独立 SQL 脚本做 `INSERT IGNORE`，避免重复插入。  

### 测试与验收场景

1. 积分商品。  
   站内充值卡商品可下单后立即收到卡号卡密且积分同步扣减。  
   站外虚拟/实物商品下单后仅锁库存不扣积分。  

2. 积分交付。  
   后台交付 `pending` 订单时扣积分成功并变更状态。  
   若积分不足，交付被拒绝且订单仍可取消。  

3. 取消与库存。  
   用户取消 `pending` 订单后库存回补、锁库存释放。  

4. 山东限制。  
   省份非山东时前端阻止提交；绕过前端直接请求也被后端拒绝。  

5. 充值卡支付。  
   金额与卡面额一致可成功充值；不一致时报错且不核销。  

6. 优惠卡。  
   满足最低充值时加赠正确，超过封顶按封顶；充值成功后优惠卡状态变 `used`。  
   充值卡模式下提交优惠卡字段应被忽略并提示不叠加。  

7. 安全与并发。  
   同一卡并发提交只能成功一次。  
   同一商品并发下单不超卖（锁库存事务生效）。  

### 实施顺序

1. 先落库迁移与菜单 SQL。  
2. 实现充值卡管理后台与卡生成。  
3. 实现积分商品/订单后台。  
4. 实现前台积分商城与下单/取消。  
5. 实现充值页“充值卡+优惠卡”流程。  
6. 联调并完成回归。  

### 明确假设与默认值

1. 积分兑换为纯积分，不引入现金补差。  
2. 不做自动超时取消任务，超时/异常订单由后台处理。  
3. 用户可取消未交付订单。  
4. 充值卡仅做“批量生成”，不做外部导入。  
5. 优惠卡本期即接入充值使用，规则固定为“加赠比例+最低充值+封顶”。  









已按你的方案直接落地实现，核心链路都接上了：

- 前台积分兑换模块已新增：控制器、商城页、详情页、订单页、前端 JS、语言包、用户中心导航入口、会员中心路由别名。  
  [Exchange.php](d:/phpEnv/www/ccw88.com/application/index/controller/Exchange.php)  
  [exchange/index.html](d:/phpEnv/www/ccw88.com/application/index/view/exchange/index.html)  
  [exchange/detail.html](d:/phpEnv/www/ccw88.com/application/index/view/exchange/detail.html)  
  [exchange/orders.html](d:/phpEnv/www/ccw88.com/application/index/view/exchange/orders.html)  
  [exchange.js](d:/phpEnv/www/ccw88.com/public/assets/js/frontend/exchange.js)  
  [exchange.php](d:/phpEnv/www/ccw88.com/application/index/lang/zh-cn/exchange.php)

- 前台地址联动接口已加 `area()`。  
  [Ajax.php](d:/phpEnv/www/ccw88.com/application/index/controller/Ajax.php)

- 兑换业务服务与模型已新增，包含库存锁定/释放、交付扣分、取消回滚、山东地址强校验、站内卡自动发放。  
  [ExchangeGoods.php](d:/phpEnv/www/ccw88.com/application/common/model/ExchangeGoods.php)  
  [ExchangeOrder.php](d:/phpEnv/www/ccw88.com/application/common/model/ExchangeOrder.php)  
  [RechargeCard.php](d:/phpEnv/www/ccw88.com/application/common/model/RechargeCard.php)  
  [ExchangeService.php](d:/phpEnv/www/ccw88.com/application/common/service/ExchangeService.php)  
  [RechargeCardService.php](d:/phpEnv/www/ccw88.com/application/common/service/RechargeCardService.php)

- 后台会员管理三模块已新增：积分商品、积分订单、充值卡（含批量生成/导出、交付/发货/驳回动作）。  
  [Exchangegoods.php](d:/phpEnv/www/ccw88.com/application/admin/controller/user/Exchangegoods.php)  
  [Exchangeorder.php](d:/phpEnv/www/ccw88.com/application/admin/controller/user/Exchangeorder.php)  
  [Rechargecard.php](d:/phpEnv/www/ccw88.com/application/admin/controller/user/Rechargecard.php)  
  [exchangegoods.js](d:/phpEnv/www/ccw88.com/public/assets/js/backend/user/exchangegoods.js)  
  [exchangeorder.js](d:/phpEnv/www/ccw88.com/public/assets/js/backend/user/exchangeorder.js)  
  [rechargecard.js](d:/phpEnv/www/ccw88.com/public/assets/js/backend/user/rechargecard.js)

- 充值页已接入充值卡支付 + 优惠卡输入，且实现“不叠加”、卡面额等值、优惠卡预占与支付后核销、bonus 入账。  
  [Recharge.php](d:/phpEnv/www/ccw88.com/application/index/controller/Recharge.php)  
  [recharge.html](d:/phpEnv/www/ccw88.com/application/index/view/recharge/recharge.html)  
  [recharge.js](d:/phpEnv/www/ccw88.com/public/assets/js/frontend/recharge.js)  
  [recharge.php](d:/phpEnv/www/ccw88.com/application/index/lang/zh-cn/recharge.php)

- 迁移与菜单 SQL 已补齐。  
  [20260227_points_exchange_and_cards.sql](d:/phpEnv/www/ccw88.com/doc/20260227_points_exchange_and_cards.sql)  
  [20260227_user_menu_exchange_cards.sql](d:/phpEnv/www/ccw88.com/doc/20260227_user_menu_exchange_cards.sql)

- 会员中心路由与导航已接入兑换入口。  
  [route.php](d:/phpEnv/www/ccw88.com/application/route.php)  
  [sidenav.html](d:/phpEnv/www/ccw88.com/application/index/view/common/sidenav.html)

已完成校验：

- `php -l`：新增/修改 PHP 文件全部通过
- `node --check`：新增/修改 JS 文件全部通过

补充说明：

- `addons/recharge/*` 我也已按方案改了本地文件（`Order.php/config.php/install.sql`），但该目录被项目 `.gitignore` 忽略，不会出现在 `git status`。
