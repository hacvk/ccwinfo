## 用户中心前台样式统一优化方案（以 `user/index.html` 为锚点）

### 摘要

目标是把以下页面统一到用户中心欢迎页的视觉体系（双栏布局、卡片容器、标题层级、按钮与表格风格）并修复侧栏 active 的白字显示问题，同时扩展侧栏入口让邀请与充值页面也有明确 active 态。  
本次只做前端模板/CSS 重构，不改业务逻辑与后端接口。

### 变更范围（文件级）

1. [application/index/view/common/sidenav.html](D:/phpEnv/www/ccw88.com/application/index/view/common/sidenav.html)
2. [public/assets/css/user-center.css](D:/phpEnv/www/ccw88.com/public/assets/css/user-center.css)
3. [application/index/view/user/posts.html](D:/phpEnv/www/ccw88.com/application/index/view/user/posts.html)
4. [application/index/view/invite/index.html](D:/phpEnv/www/ccw88.com/application/index/view/invite/index.html)
5. [application/index/view/recharge/recharge.html](D:/phpEnv/www/ccw88.com/application/index/view/recharge/recharge.html)
6. [application/index/view/recharge/moneylog.html](D:/phpEnv/www/ccw88.com/application/index/view/recharge/moneylog.html)
7. [application/index/view/third/prepare.html](D:/phpEnv/www/ccw88.com/application/index/view/third/prepare.html)

### 关键实现方案（决策已锁定）

1. 侧栏扩展与 active 修复  
   在 `sidenav.html` 增加“邀请好友 / 账户充值 / 余额明细”菜单项，并绑定 `check_nav_active('invite/index')`、`check_nav_active('recharge/recharge')`、`check_nav_active('recharge/moneylog')`。  
   在 `user-center.css` 将 active 规则改为高优先级选择器（覆盖 `frontend.css` 的 `.sidenav .list-group .list-group-item.active > a`），确保 active 文本与图标恒为白色，hover/focus 仍保持白色。

2. 统一页面壳层（invite/recharge/moneylog/third）  
   把 `#content-container.container` 旧壳替换为与欢迎页一致的结构：`user-center-page` + `row` + `col-md-3` 侧栏 + `col-md-9 col-xs-12` 主区 + `panel panel-default modern-card user-main-card`。  
   `third/prepare.html` 按你的选择改为双栏并复用 `common/sidenav`。

3. `user/posts.html` 美化（桌面表格 + 移动卡片）  
   保留现有筛选逻辑与撤销逻辑，改为：桌面端 `table-responsive` 表格展示（标题/状态/发布时间/浏览/操作），移动端使用卡片列表展示同字段。  
   保留 `js-user-revoke` 按钮与现有脚本流程，不改请求地址与返回处理；仅扩展样式类和布局容器。

4. `invite/index.html` 协调样式  
   重构为用户中心卡片页：顶部说明区 + 链接输入复制区 + 桌面表格/移动卡片两套展示。  
   保留原数据变量与分页输出，不改邀请链接生成与复制按钮数据属性。

5. `recharge/recharge.html` 协调样式  
   保留 `#recharge-form`、`row-money`、`row-paytype`、`custommoney` 等 JS 依赖点；只重做视觉层（金额选项卡片化、支付方式选中态、提交按钮样式、提示块样式）。  
   把当前内联样式迁移/并入 `user-center.css` 的“充值模块”分区，降低模板内样式耦合。

6. `recharge/moneylog.html` 协调样式  
   改为用户中心卡片主体，日志项做成统一列表/时间线视觉；正负金额使用统一状态色。  
   保留“去充值”按钮与分页，保持字段与渲染逻辑不变。

7. `third/prepare.html` 协调样式  
   在双栏框架下，将绑定表单纳入 `user-main-card` 与 `user-form` 体系，按钮/输入框与用户中心一致。  
   保留 `#bind-form`、`btn-captcha`、`require.callback` 和 `Form.api.bindevent` 逻辑，确保绑定流程不受影响。

### 公共接口 / 类型 / 兼容性影响

1. 后端 API、控制器、模型、SQL、路由不变。  
2. 前端“接口约束”保持：`#recharge-form`、`#bind-form`、`.js-user-revoke`、`.row-money`、`.row-paytype`、`.custommoney`、`.btn-captcha` 等选择器不改名。  
3. 侧栏导航项增加会带来 UI 入口变化，但不改变现有页面 URL。

### 测试与验收场景

1. 侧栏 active 验收：访问 `user/index`、`user/posts`、`invite/index`、`recharge/recharge`、`recharge/moneylog` 时对应菜单项高亮，文字/图标为白色。  
2. `posts` 功能回归：桌面表格与移动卡片中的“编辑/撤销”可用，撤销后响应处理与刷新行为不变。  
3. 充值交互回归：金额切换、支付方式切换、自定义金额输入、提交金额校验均正常。  
4. 邀请页回归：邀请链接展示与复制按钮可用，分页正常。  
5. 余额日志回归：金额正负颜色正确，分页正常，去充值按钮可跳转。  
6. 第三方绑定回归：验证码发送、表单校验、提交后跳转逻辑正常。  
7. 响应式验收：`>=992px` 双栏稳定；`<992px` 侧栏抽屉与 `sidebar-toggle` 行为正常；`<768px` 移动卡片可读性正常。

### 实施顺序

1. 先改 `sidenav.html` 与 `user-center.css`（active 修复 + 统一基础样式）。  
2. 再改 `user/posts.html`（桌面表格+移动卡片）。  
3. 然后依次改 `invite/index.html`、`recharge/recharge.html`、`recharge/moneylog.html`。  
4. 最后改 `third/prepare.html` 双栏化并做全链路回归验收。

### 假设与默认值

1. 默认主色继续沿用当前用户中心蓝色体系（`#008cd7` 系列）。  
2. 默认不新增 JS 文件，优先复用现有脚本与事件绑定。  
3. 默认不新增数据库变更、不改控制器逻辑。  
4. 默认不新增侧栏“第三方绑定”固定入口（该页为流程页，保留通过流程进入）。
