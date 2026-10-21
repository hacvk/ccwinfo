# 东营查查网（ccw88）

基于 [FastAdmin](https://www.fastadmin.net) 的本地分类信息与生活服务平台，面向东营地区提供分类信息发布、本地攻略、积分商城、推广合作等能力。

本项目在 FastAdmin 基础上二次开发，原 ThinkAdmin 版本的部分模块已迁移至此（迁移思路见 `doc/前言.md`）。

## 技术栈

| 项 | 说明 |
| --- | --- |
| 后端 | PHP >= 7.4（需 `json` `curl` `pdo` `bcmath` 扩展） |
| 框架 | ThinkPHP 5.0.28 + FastAdmin 1.6.1 |
| 数据库 | MySQL 5.7+，表前缀 `fa_` |
| 前端 | Bootstrap 3 + jQuery + RequireJS，Less 源码经 Grunt 压缩打包 |
| 依赖管理 | Composer（后端）/ npm + Grunt（前端） |

## 功能模块

**前台（`application/index`）**

- **分类信息发布**：多分类、自定义字段、发布前手机号首发与月度复验、信息撤销、审核状态流转
- **本地攻略 Guide**：分类 / 标签 / 专题 / 模型多维度浏览，URL 走 SEO 友好伪静态
- **用户中心**：资料、密码、我的发布、收藏、邀请、签到
- **积分体系**：签到与发布获取积分，积分兑换商城、兑换订单
- **充值与卡券**：在线充值、充值卡 / 优惠卡
- **推广中心**：推广位申请与在线付费（易支付：微信 / 支付宝）、友情链接申请
- **单页 CMS**：单页模块前台只读展示
- **通知中心**：站内信 + 邮件（SMTP）+ 短信（阿里云 / 腾讯云）多通道通知
- **第三方登录**：QQ / 微信 / 微博

**后台（`application/admin`）**

- 分类与自定义字段管理、信息审核中心、用户与会员组管理
- 通知中心配置（邮件 / 短信 / OTP）、单页模块、攻略管理、推广位与订单管理
- 易支付配置、签到配置、第三方登录配置、百度主动推送
- 基于 `Auth` 的权限管理、一键 CRUD 生成、插件市场在线安装升级
- Sentry 错误上报（可选，经 `.env` 开关，见 `doc/sentry_usage_guide.md`）

**API（`application/api`）**：与前台共用账号体系，提供 Token 鉴权、短信 / 邮件验证等接口。

## 目录结构

```
ccw88/
├── application/            # 应用代码
│   ├── admin/              #   后台模块
│   ├── index/              #   前台模块
│   ├── api/                #   API 模块
│   ├── common/             #   公共模型、类库（Auth、Sms、Email、AliyunSms 等）
│   └── extra/              #   站点、插件、上传等扩展配置
├── thinkphp/               # ThinkPHP 框架
├── public/                 # Web 根目录（index.php 及后台入口）
├── addons/                 # 插件目录（gitignore，composer 装入）
├── doc/                    # 数据库脚本、实施计划、安全审查与运维文档
├── extend/                 # 第三方扩展类库
├── .env.sample             # 环境变量样例
└── composer.json / package.json / Gruntfile.js
```

## 环境要求

- PHP >= 7.4，开启 `pdo_mysql`、`curl`、`bcmath`、`json`、`mbstring`
- MySQL 5.7+（utf8mb4）
- Composer 2.x；构建前端需 Node.js + Grunt CLI
- Web 服务器（Nginx / Apache），站点根目录指向 `public/`

## 安装步骤

1. **拉取代码并安装依赖**

   ```bash
   git clone https://github.com/hacvk/ccwinfo.git
   cd ccwinfo
   composer install
   ```

2. **配置环境变量**

   ```bash
   cp .env.sample .env
   ```

   编辑 `.env`，填入数据库连接等信息：

   ```ini
   [app]
   debug = false

   [database]
   hostname = 127.0.0.1
   database = ccw88
   username = root
   password = 你的密码
   hostport = 3306
   prefix = fa_
   ```

   站点名称、备案号、邮件 SMTP、短信密钥等业务配置在后台「系统配置 / 通知中心」中维护，不要写进代码。

3. **初始化数据库**

   命令行安装（会随机生成后台 `admin` 密码，注意保存输出）：

   ```bash
   php think install --hostname=127.0.0.1 --database=ccw88 --username=root --password=你的密码
   ```

   > 仓库中的 `install.lock` 表示源站已安装过。若要在全新环境重复安装，先删除 `application/admin/command/Install/install.lock`，或加 `--force=true`。

   随后按文件名顺序执行 `doc/` 下的增量脚本（`step1.x` → `step2.x` → 带日期的 `2026xxxx_*.sql`），用于建立分类信息、攻略、通知中心、积分兑换等业务表：

   ```
   doc/step1.1_create_tables.sql ... step1.6_all_menus.sql
   doc/step2.1_create_guide_tables.sql, step2.2_guide_menu.sql
   doc/20260225_*.sql ... doc/20260227_*.sql
   ```

   如需演示数据，可另执行 `doc/seed_info_data.sql`（30 条测试信息）。

4. **配置 Web 服务器**

   站点根目录指向 `public/`，并开启伪静态（ThinkPHP 路由）。Nginx 示例：

   ```nginx
   server {
       listen 80;
       server_name www.dyccw.com;
       root /path/to/ccw88/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fast_pass unix:/run/php/php7.4-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }

       # 禁止直接访问敏感目录
       location ~ ^/(application|thinkphp|extend|doc|vendor)/ { return 404; }
   }
   ```

   后台入口是 `public/` 下绑定 admin 模块的随机命名 PHP 文件（出于安全考虑不建议改名为 `admin.php`），其路径只在部署时向管理员告知。

5. **（可选）构建前端资源**

   仅在修改了 `public/assets/js`、Less 源码或插件前端代码时才需要：

   ```bash
   npm install
   npm run build    # 即 grunt，压缩打包 JS/CSS 并做 CDN 部署
   ```

6. **权限**

   `runtime/` 需对 Web 进程可写；`addons/` 需可写以便后台在线安装插件。

## 常见问题

- **安装后白屏 / 500**：检查 `runtime/` 是否可写、`.env` 数据库配置是否正确、`app.debug` 是否已设为 `true` 以查看报错。
- **验证码 / 图片上传失败**：确认 `gd` 扩展与 `runtime/` 写权限。
- **邮件发送失败**：后台「通知中心」核对 SMTP 主机、端口、授权码（授权码不要提交到仓库）。
- **伪静态 404**：确认站点根目录指向 `public/` 且已配置 `try_files` 回退到 `index.php`。

## 安全须知

- `.env` 已在 `.gitignore` 中，**切勿**提交真实数据库密码、SMTP 授权码、短信 AccessKey、支付密钥。
- 后台登录开启验证码与失败重试限制；生产环境保持 `app.debug = false`。
- 面向公网部署时建议限制 SSH 端口来源 IP，并定期同步上游 FastAdmin 安全补丁。
- 数据库脚本与运维说明见 `doc/`，其中 `ccw88-安全审查.md` 记录了既往安全检查结论。

## 相关文档

- `doc/前言.md` —— 从 ThinkAdmin 迁移到 FastAdmin 的背景与要求
- `doc/sentry_usage_guide.md` —— Sentry 错误上报接入
- `doc/*.sql` —— 业务表结构与菜单初始化脚本
- 官方文档：<https://doc.fastadmin.net>

## 版权信息

本项目基于 FastAdmin（Apache-2.0 协议）二次开发，ThinkPHP、AdminLTE、Bootstrap 等第三方组件版权另行标注，详见 [LICENSE](LICENSE) 与各依赖仓库。
