# 后台单页 CMS 模块实施计划（前台只读详情）

## 摘要

1. 新增一个**独立后台模块**（一级菜单），用于管理“单页内容”。
2. 前台仅开放详情访问，路由固定为 `page/:slug`，不提供公开列表页。
3. 页面支持树形父子结构，但你已确认“父页面仅分组”，因此仅叶子页可被前台访问。
4. 页面支持完整后台 CRUD + 发布状态（`draft/published/offline`），发布/下线即时生效。
5. URL 使用单段 slug，固定前缀 `page/slug`，避免与现有 `:cate` 路由冲突。
6. 链接字段采用“外链优先模式”：若配置外链，访问详情时直接跳转外链。
7. 前台页脚仅显示“顶级 + 勾选展示 + 已发布 + 非分组”的页面入口。
8. 已发布页面禁止改 slug（URL 锁定）；详情页需要浏览量统计。

## 对外接口与类型变更（Public APIs / Interfaces / Types）

1. 新增前台路由：`GET /page/:slug` -> `index/page/detail`。
2. 新增后台模块路由（FastAdmin 标准）：`/admin/page/page/index|add|edit|del|multi|selectpage`。
3. 新增数据模型：`SinglePage`（建议表 `fa_single_page`）。
4. 路由规则要求：`slug` 正则 `^[a-z0-9][a-z0-9-]{1,62}$`（单段、英文数字中划线）。
5. 详情页访问行为：
6. 命中 `published + is_group=0 + slug`。
7. 若 `external_url` 非空，则 302 跳转外链。
8. 否则渲染站内详情并 `views + 1`。

## 数据库设计（新表）

1. 表名：`fa_single_page`。
2. 字段：
3. `id` int unsigned PK
4. `parent_id` int unsigned default 0（上级页面）
5. `is_group` tinyint unsigned default 0（1=分组节点，0=内容页）
6. `title` varchar(120) not null
7. `slug` varchar(64) null（仅内容页必填）
8. `slug_locked` tinyint unsigned default 0（发布后锁 URL）
9. `cover` varchar(255) default ''
10. `content` longtext null（富文本）
11. `external_url` varchar(255) default ''
12. `show_in_footer` tinyint unsigned default 0
13. `status` enum('draft','published','offline') default 'draft'
14. `weigh` int default 0
15. `views` int unsigned default 0
16. `createtime` int unsigned
17. `updatetime` int unsigned
18. 索引：
19. `uk_slug(slug)` 唯一索引（NULL 允许）
20. `idx_parent(parent_id,weigh,id)`
21. `idx_status(status,weigh,id)`
22. `idx_footer(show_in_footer,status,parent_id,is_group,weigh,id)`

## 后台功能规格（管理端）

1. 新增一级菜单：`单页管理`，子菜单：`页面管理`。
2. 列表页字段：`ID/标题/上级页面/类型(分组或内容)/slug/状态/页脚展示/浏览量/排序/更新时间`。
3. 支持筛选：`状态/类型/上级页面/标题关键词`。
4. 表单字段：`上级页面、是否分组、标题、slug、封面、富文本内容、外链、状态、页脚展示、排序`。
5. 业务校验：
6. `is_group=1` 时，不允许前台访问，不允许勾选页脚展示，`slug/content/external_url` 置空或忽略。
7. `is_group=0` 时，`slug` 必填且唯一；`content` 与 `external_url` 允许二选一或都填（外链优先）。
8. 已发布（或 `slug_locked=1`）记录禁止修改 `slug`。
9. `parent_id` 不可为自己，不可形成环；子页面父节点必须存在且为分组节点。
10. 删除分组节点前必须先清空子节点（阻止误删树结构）。

## 前台功能规格（只读）

1. 新增控制器 `index/page/detail` 与模板 `index/view/page/detail.html`。
2. 访问逻辑：
3. 非 `published`、分组节点、slug 不存在 -> 404。
4. 命中外链 -> 302 跳转。
5. 普通内容页 -> 渲染标题、封面、富文本并累加浏览量。
6. 父子路径可用于面包屑展示，但父节点不提供详情访问链接。
7. 不新增前台列表页，不影响现有首页与分类页主流程。

## 页脚集成

1. 在 `Frontend` 公共初始化中加载页脚单页数据：
2. 条件：`parent_id=0 AND is_group=0 AND show_in_footer=1 AND status='published'`
3. 排序：`weigh desc, id asc`
4. 在前台默认布局页脚增加“单页入口”区域，展示上述链接列表。

## 实施步骤

1. 编写 SQL 迁移脚本（建议 `doc/20260226_single_page_module.sql`，幂等）。
2. 新增模型与后台控制器/视图/JS（FastAdmin 标准 CRUD + 自定义校验）。
3. 新增前台控制器、详情模板、路由规则（置于 `:cate` 兜底规则前）。
4. 在公共前台基类与默认布局接入页脚链接数据与展示。
5. 新增后台菜单与权限规则 SQL（`fa_auth_rule`）。
6. 联调：后台发布 -> 前台访问 -> 页脚可见性 -> 浏览量增长 -> slug 锁定。
7. 回归现有模块：`info/guide/promotion` 路由不被新规则影响。

## 测试用例与验收场景

1. 新建内容页：slug 合法、发布后前台 `/page/{slug}` 可访问。
2. slug 冲突：保存失败并提示唯一性错误。
3. 发布后改 slug：被拒绝，URL 保持不变。
4. 分组节点访问：`/page/{slug}` 返回 404（或无路由命中）。
5. 外链优先：配置 `external_url` 后访问详情即跳转。
6. 页脚展示规则：仅顶级+勾选+已发布+非分组显示。
7. 父子关系校验：禁止自引用、禁止循环、父节点类型错误时拒绝保存。
8. 删除分组节点：存在子页时阻止删除。
9. 浏览量统计：详情访问后 `views` 递增。
10. 路由兼容：原有 `:cate`、`guide/*`、`promotion/*` 不回归。

## 明确假设与默认值

1. 模块中文名默认使用“单页管理”（可在菜单 SQL 时调整）。
2. 暂不做历史 slug 301 映射（你已选择发布后不允许改 slug）。
3. 暂不做定时发布与发布计划任务（状态即时生效）。
4. 暂不新增 SEO 专用字段（如后续需要可追加 `seo_title/seo_keywords/seo_description`）。
5. 暂不做软删除字段（默认硬删 + 删除前约束保护）。
