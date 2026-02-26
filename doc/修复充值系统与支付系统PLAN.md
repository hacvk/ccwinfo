# 充值系统修复计划（支付方式按配置展示 + 余额入账兜底）

## 摘要
本次改造分两条主线：
1. 充值页支付方式改为“已配置可用 + 后台勾选”的交集展示，避免未配置通道仍显示。
2. 充值成功后增加“返回页补单”兜底，解决扫码页显示成功但异步通知未落账导致余额仍为 0 的问题。

## 实施内容
1. 改造充值页支付方式来源  
- 修改 [Recharge.php](D:/phpEnv/www/ccw88.com/application/index/controller/Recharge.php) 的 `recharge()`。  
- 新增可用支付方式计算方法（沿用 Promotion 模块同口径）：  
  - `isWechatPayEnabled()`：校验微信关键配置是否完整。  
  - `isAlipayPayEnabled()`：校验支付宝关键配置是否完整。  
  - `getAvailablePaytypes()`：返回 `['wechat','alipay']` 的可用子集。  
- 最终展示列表规则：`epay可用通道 ∩ recharge.paytypelist`。  
- 默认支付方式规则：优先 `recharge.defaultpaytype`，若不在最终列表内则回退到第一个可用通道。  

2. 改造充值提交校验  
- 修改 [Recharge.php](D:/phpEnv/www/ccw88.com/application/index/controller/Recharge.php) 的 `submit()`。  
- 新增服务端强校验：  
  - 无可用支付通道时直接报错。  
  - `paytype` 非最终可用列表时拒绝提交。  
  - `paytype` 为空时使用回退后的默认通道。  

3. 改造充值页模板显示行为  
- 修改 [recharge.html](D:/phpEnv/www/ccw88.com/application/index/view/recharge/recharge.html)。  
- 隐藏域 `paytype` 改为使用控制器下发的“实际默认支付方式”（不是原始配置值）。  
- 当无可用支付通道时显示明确提示，并禁用“立即充值”按钮。  

4. 为返回页补单准备订单号  
- 修改 [Order.php](D:/phpEnv/www/ccw88.com/addons/recharge/library/Order.php) 的 `submit()`。  
- 将 `returnurl` 从  
  `/index/recharge/epay/type/return/paytype/{paytype}`  
  调整为  
  `/index/recharge/epay/type/return/paytype/{paytype}/out_trade_no/{orderid}`。  
- 这样扫码页前端跳转 `returnurl` 时，后端一定能拿到订单号做补单。  

5. 增加返回页主动补单兜底  
- 修改 [Recharge.php](D:/phpEnv/www/ccw88.com/application/index/controller/Recharge.php) 的 `epay()`。  
- `notify` 分支继续主处理（异步通知优先），并补充更稳健异常捕获（`Throwable`）。  
- `return` 分支新增逻辑：  
  - 读取 `out_trade_no` + `paytype`。  
  - 若订单未支付，则向支付网关主动查单（支付宝/微信分别处理，兼容 v2/v3）。  
  - 查到已支付后调用 `\addons\recharge\library\Order::settle()` 补入账。  
  - `settle()` 本身幂等（已支付不会重复加钱）。  

6. 强化结算金额比较容错  
- 修改 [Order.php](D:/phpEnv/www/ccw88.com/addons/recharge/library/Order.php) 的 `settle()`。  
- 将严格不等比较改为“金额差值阈值比较（如 `> 0.01`）”，避免浮点精度导致误判未入账。  
- 保留并增强异常日志，便于后续排障。  

## 公共接口/行为变更
- 充值返回 URL 新增携带 `out_trade_no`（路径参数），属于后端回调路径行为增强，不影响前台用户操作。  
- 充值页支付方式展示不再仅由 `recharge.paytypelist` 决定，而是改为与 `epay` 配置联动。  
- 提交接口会拒绝“前端篡改/不可用 paytype”。  

## 测试用例与验收标准
1. 仅配置支付宝  
- 充值页只显示支付宝。  
- 提交成功后创建支付宝订单。  

2. 仅配置微信  
- 充值页只显示微信。  
- 提交成功后创建微信订单。  

3. 两者都配置  
- 充值页显示微信+支付宝，默认值符合配置或回退规则。  

4. 都未配置  
- 页面提示未配置可用支付通道，提交按钮不可用。  

5. 提交参数篡改  
- 手动提交不可用 `paytype`，接口返回错误，不创建有效支付请求。  

6. 正常通知入账  
- `notify` 到达后订单变 `paid`，用户 `money` 增加，日志写入。  

7. 通知丢失但扫码页显示成功  
- 跳转 `return` 后触发补单查单，订单最终 `paid`，余额更新。  

8. 幂等验证  
- `notify` 与 `return` 先后都触发时，余额只增加一次。  

9. 金额安全  
- 实付金额与订单金额差异超阈值时，不入账并记录日志。  

## 假设与默认
- 已确定采用“交集过滤”支付方式展示规则。  
- 已确定采用“返回页补单”兜底策略。  
- 不改动 Promotion 模块，仅改 Recharge 相关链路。  
- 本地无法直接连通数据库验证（环境返回 `Access denied`），以代码级改造和日志验证为准。  
