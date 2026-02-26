## 推广模块改造实施计划（友链独立 + 动态表单 + 周/月/季定价 + 支付闭环）

## 摘要
1. 友情链接申请改为独立页面与独立提交逻辑，不走支付，仅审核。
2. 其他展示位统一走“登录后申请 -> 选择周期 -> 生成订单 -> 支付 -> 审核 -> 上线”。
3. 展示位价格采用统一粒度：每个展示位配置 `week/month/quarter` 三档价格。
4. 非友链展示位表单字段由后台展示位配置驱动，前台动态渲染。
5. 支付通道按 `epay` 实际配置动态读取，支持回调入账与重复回调幂等。
6. 周期计费规则固定：周=7天，月=30天，季=90天；起算点为“审核通过并上线时间”。

## 范围与边界
1. 本期只改 `promotion` 模块，不影响已有信息发布模块。
2. 仅 `slot_code=friend_links` 走友链独立申请页面。
3. 非友链下单必须登录；未登录跳转登录页并带回跳参数。
4. 支付成功不代表上线，必须经过后台审核。
5. 暂不做自动退款流程；审核拒绝仅保留状态与备注。

## 数据结构与迁移
1. 新增 SQL 脚本：`doc/20260226_promotion_pricing_payment.sql`（幂等）。
2. `fa_promotion_item` 增加字段：`form_data`（TEXT，保存动态字段JSON）、`billing_cycle`（enum week/month/quarter）、`duration_days`（int）。
3. `fa_promotion_order` 调整字段：
4. `pay_status` 扩展为 `unpaid/paid/offline_paid/cancelled`。
5. 新增 `order_no`（varchar，唯一）、`pay_type`（wechat/alipay/offline）、`pay_time`、`pay_trade_no`、`paid_amount`。
6. 保留 `amount` 作为订单总额，所有金额以后端计算为准。
7. 索引新增：`uk_order_no(order_no)`、`idx_pay_status(pay_status,order_status)`、`idx_item_id(item_id)`。
8. 模型同步：
9. `application/common/model/PromotionOrder.php` 增加新状态映射。
10. `application/common/model/PromotionItem.php` 增加 `billing_cycle` 文案映射（若需要）。

## 配置结构（展示位 config 规范）
1. `fa_promotion_slot.config` 采用统一 JSON 结构：
2. `pricing.week/month/quarter`：三档价格，单位元。
3. `require_login`：非友链固定为 1，友链忽略该字段。
4. `form_fields`：动态字段数组（name/label/type/required/options/placeholder/maxlength）。
5. 字段类型白名单：`text/url/textarea/number/select/image`。
6. 后端对字段配置和提交数据均做白名单校验，防止注入和越权字段写入。

## 路由与接口变更
1. 路由新增（放在通配前）：
2. `promotion/friendlink/apply$ -> index/promotion/friendlinkApply`
3. `promotion/apply$ -> index/promotion/apply`
4. `promotion/pay$ -> index/promotion/pay`
5. `promotion/epay/type/:type/paytype/:paytype -> index/promotion/epay`
6. `Promotion` 控制器新增/调整方法：
7. `friendlinkApply()`：渲染友链专页。
8. `submitFriendlink()`：提交友链（仅生成 `PromotionItem`，不生成支付订单）。
9. `apply()`：非友链申请页，读取 slot 配置并渲染动态表单。
10. `submit()`：非友链提交，后端按 slot 价格与周期生成订单（未支付）。
11. `pay()`：发起支付，调用 `addons\epay\library\Service::submitOrder`。
12. `epay()`：支付回调与返回页处理，完成订单入账与幂等。
13. `status()`：展示订单支付与审核状态。
14. 接口契约：
15. `POST /index/promotion/submit`：入参 `slot_code,billing_cycle,contact_*,dynamic_fields`；返回 `order_id,order_no,amount,pay_status`。
16. `GET/POST /index/promotion/pay`：入参 `order_id,paytype`；返回 epay response（跳转/二维码/表单）。
17. `GET /index/promotion/epay/type/return/paytype/:paytype`：支付后跳转状态页。
18. `POST /index/promotion/epay/type/notify/paytype/:paytype`：支付异步通知，幂等更新。

## 页面与前端改造
1. 友情链接专页：
2. 新增模板 `application/index/view/promotion/friendlink_apply.html`。
3. 首页友链入口改到 `promotion/friendlink/apply`。
4. 非友链申请页：
5. `application/index/view/promotion/apply.html` 改为动态表单容器页。
6. 新增脚本 `public/assets/frontend/js/promotion-apply.js`：渲染字段、周期选择、价格联动、提交与支付触发。
7. 新增样式 `public/assets/frontend/css/promotion-apply.css`：延续现主题风格。
8. 订单页面：
9. `application/index/view/promotion/success.html` 调整为“订单创建成功，待支付/待审核提示”。
10. `application/index/view/promotion/status.html` 增加 `支付通道/支付时间/审核结果/投放区间` 展示。
11. 用户中心：
12. `application/index/view/user/promotion_orders.html` 增加 `paid/cancelled` 展示、未支付“继续支付”按钮。
13. `application/index/controller/User.php::promotionOrders()` 保持分页，补充新状态映射与筛选参数。

## 管理端改造
1. 展示位管理：
2. `application/admin/view/promotion/slot/add.html` 与 `edit.html` 增加价格配置输入和动态字段配置区。
3. `public/assets/js/backend/promotion/slot.js` 增加配置序列化/反序列化与基础校验。
4. 推广内容审核：
5. `application/admin/controller/promotion/Item.php::review()` 增加支付前置校验（非友链必须已支付）。
6. 审核通过时设置：
7. `start_time=当前时间`。
8. `duration_days` 按周期映射 7/30/90。
9. `end_time=start_time+duration_days*86400`。
10. `status=online`。
11. 审核拒绝保留 `review_remark`，订单状态置 `rejected`（不自动退款）。

## 关键状态机
| 阶段 | item.status | item.review_status | order.pay_status | order.order_status |
|---|---|---|---|---|
| 提交后待支付 | draft | pending | unpaid | pending |
| 支付成功待审核 | pending | pending | paid | pending |
| 审核通过上线 | online | approved | paid | approved |
| 审核拒绝 | offline | rejected | paid | rejected |
| 手动取消/关闭 | offline | pending/rejected | cancelled | cancelled |

## 分步实施（按里程碑）
1. 里程碑1：友链独立页与非友链登录门槛。
2. 里程碑2：展示位价格配置 + 动态表单配置（先打通提交与落库）。
3. 里程碑3：订单与支付链路接入（submit/pay/epay notify+return）。
4. 里程碑4：后台审核联动上线时间与周期结束时间。
5. 里程碑5：用户中心订单体验与状态页完善。
6. 每个里程碑均可单独上线，里程碑2后可先走线下联调，里程碑3切线上支付。

## 测试与验收
1. 友链入口跳转到独立页面，提交后生成待审核内容，不创建支付流程。
2. 非友链未登录访问申请页时被引导登录，登录后可回跳继续申请。
3. 展示位配置不同字段时，前台表单能正确切换并校验必填。
4. 周/月/季切换时前端价格变化正确，但后端仍以配置重新计算金额。
5. 篡改前端金额或周期参数时，后端拒绝或按后端配置重算，不受前端影响。
6. 订单创建后默认 `unpaid`，支付入口仅允许本人访问并支付。
7. 支付成功异步回调可正确更新状态；重复回调不会重复记账（幂等）。
8. 未支付订单在后台审核时被拦截（非友链）。
9. 审核通过后 `start_time/end_time` 按 7/30/90 天规则写入。
10. 状态页能看到支付状态、审核状态、投放时间区间。
11. 用户中心订单页可看到新支付状态并可继续支付未支付订单。
12. 友链流程不受非友链支付改造影响。
13. 移动端申请页字段、周期选择、支付按钮均可用。

## 假设与默认
1. 非友链展示位默认必须登录下单。
2. 支付通道从 `epay` 可用配置动态读取。
3. 周期定义固定天数：7/30/90，不按自然月自然季。
4. 计费起算点为“审核通过并上线时刻”。
5. 友链保持“不计费仅审核”。
6. 本期不做自动退款和发票能力。
