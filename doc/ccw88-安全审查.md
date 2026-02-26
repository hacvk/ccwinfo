**结论（可直接用于上线评审）**
当前项目可运行，但按“互联网正式运营”标准，存在多项高风险安全问题和可预见性能瓶颈。建议先做一轮 `P0/P1` 整改，再上线。

**P0（上线前必须修）**

1. 明文密钥/凭据落盘且已入版本库。  
   证据: [site.php:34](D:/phpEnv/www/ccw88.com/application/extra/site.php:34), [site.php:35](D:/phpEnv/www/ccw88.com/application/extra/site.php:35), [site.php:37](D:/phpEnv/www/ccw88.com/application/extra/site.php:37), [git ls-files结果显示已跟踪 site.php](D:/phpEnv/www/ccw88.com/application/extra/site.php)  
   风险: 邮件账号被盗用、批量发信、业务被封。  
   建议: 立即改为环境变量/密钥管理，轮换已泄露密码。

2. 用户与后台密码哈希算法过弱（`md5(md5+salt)`）。  
   证据: [common/Auth.php:492](D:/phpEnv/www/ccw88.com/application/common/library/Auth.php:492), [admin/Auth.php:148](D:/phpEnv/www/ccw88.com/application/admin/library/Auth.php:148)  
   风险: 库泄露后易被撞库/离线破解。  
   建议: 迁移 `password_hash/password_verify`（bcrypt/argon2），并做平滑升级。

3. 关键写接口开放且缺少 CSRF 令牌校验。  
   证据: [Info.php:21](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:21), [Info.php:572](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:572), [Info.php:858](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:858), [Info.php:986](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:986), [Info.php:1006](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1006), [publish.html:22](D:/phpEnv/www/ccw88.com/application/index/view/info/publish.html:22), [edit.html:15](D:/phpEnv/www/ccw88.com/application/index/view/info/edit.html:15), [info-publish.js:470](D:/phpEnv/www/ccw88.com/public/assets/frontend/js/info-publish.js:470)  
   风险: 被跨站请求伪造、批量刷发布/刷上传。  
   建议: 所有写接口强制 `POST + CSRF Token + 登录态/风控`。

4. 短信发送风控不足，存在短信轰炸成本风险。  
   证据: [Info.php:1006](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1006), [Info.php:1346](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1346)（仅手机号+事件冷却）；对比 [api/Sms.php:38](D:/phpEnv/www/ccw88.com/application/api/controller/Sms.php:38), [api/Sms.php:39](D:/phpEnv/www/ccw88.com/application/api/controller/Sms.php:39)（有IP限流）  
   风险: 短信费用被打爆、通道被封。  
   建议: 增加 IP/设备/账号三层限流+图形验证码+黑名单。

5. 手机号隐私可被脚本化抓取（会话级冷却太弱）。  
   证据: [Info.php:342](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:342), [Info.php:361](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:361), [Info.php:1706](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1706)  
   风险: 批量采集联系方式，骚扰与数据合规风险。  
   建议: 改成登录后可见/一次性验证票据/设备指纹+IP限频。

**P1（高优先级）**

1. 外链跳转未做白名单校验，存在开放跳转/钓鱼风险。  
   证据: [Guide.php:134](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:134), [Guide.php:137](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:137)

2. 会话与跨域策略偏危险。  
   证据: [Common.php:33](D:/phpEnv/www/ccw88.com/application/api/controller/Common.php:33)（`HTTP_SID` 可设 session id）, [common.php:383](D:/phpEnv/www/ccw88.com/application/common.php:383), [common.php:389](D:/phpEnv/www/ccw88.com/application/common.php:389), [config.php:303](D:/phpEnv/www/ccw88.com/application/config.php:303)  
   风险: 会话固定/跨域凭据滥用面扩大。

3. Cookie 安全属性不足。  
   证据: [config.php:224](D:/phpEnv/www/ccw88.com/application/config.php:224), [config.php:226](D:/phpEnv/www/ccw88.com/application/config.php:226), [User.php:38](D:/phpEnv/www/ccw88.com/application/index/controller/User.php:38)  
   风险: XSS后 token 更容易被窃取。  
   建议: `Secure=true`, `HttpOnly=true`, `SameSite=Lax/Strict`。

4. 日志包含敏感上下文，且会记录后台入口与管理员信息。  
   证据: [SentryService.php:93](D:/phpEnv/www/ccw88.com/application/common/library/SentryService.php:93), [SentryService.php:505](D:/phpEnv/www/ccw88.com/application/common/library/SentryService.php:505), [2026-02-25.log:1](D:/phpEnv/www/ccw88.com/runtime/sentry/2026-02-25.log:1)

5. 依赖治理不稳：框架使用 `dev-master`，且 `composer.lock` 被忽略。  
   证据: [composer.json:19](D:/phpEnv/www/ccw88.com/composer.json:19), [.gitignore:6](D:/phpEnv/www/ccw88.com/.gitignore:6)  
   风险: 每次部署依赖不一致，供应链与回滚风险高。

**性能主要瓶颈**

1. 搜索使用 `%keyword%` 对 `content/summary` 模糊匹配，容易全表扫描。  
   证据: [Info.php:127](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:127), [Guide.php:691](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:691), [Guide.php:693](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:693)

2. 标签过滤先 `column('guide_id')` 拉全量 ID 再 `IN`，数据大时内存和SQL都抖。  
   证据: [Guide.php:705](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:705), [Guide.php:712](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:712)

3. OTP 表查询高频，但初始表只有主键，缺少 `(mobile,event,createtime)` 索引。  
   证据: [fastadmin.sql:369](D:/phpEnv/www/ccw88.com/application/admin/command/Install/fastadmin.sql:369), [fastadmin.sql:377](D:/phpEnv/www/ccw88.com/application/admin/command/Install/fastadmin.sql:377), [Info.php:1350](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1350), [Info.php:1351](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1351), [Info.php:1352](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1352)

4. 多处请求内做元数据查询（`SHOW COLUMNS/SHOW TABLES`），高QPS下是纯额外开销。  
   证据: [Info.php:1243](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1243), [Info.php:1267](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1267), [Info.php:1291](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1291), [Info.php:1770](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:1770)

5. 详情页每次访问都写 `views`，热点内容会造成写放大。  
   证据: [Info.php:233](D:/phpEnv/www/ccw88.com/application/index/controller/Info.php:233), [Guide.php:140](D:/phpEnv/www/ccw88.com/application/index/controller/Guide.php:140)

**总体上线建议**

- 先完成 P0，再灰度。  
- 并行做 P1 与性能改造（索引、限流、日志脱敏、搜索优化）。  
- 我这次是静态审计（读代码+配置+SQL+日志），未做在线渗透和压测；正式上线前建议补一轮黑盒安全测试和压测。
