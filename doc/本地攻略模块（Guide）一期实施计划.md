# 本地攻略模块（Guide）一期实施计划

## 摘要

在现有 FastAdmin/ThinkPHP 项目中新增与“分类信息系统（info）”同级的“本地攻略（guide）”模块，采用“固定主字段 + 扩展字段”模型方案，后台仅管理员可录入，前台提供攻略列表/详情、专题列表/详情、标签与模型筛选。  
已锁定决策：独立攻略分类、`Tag表+关系表`、发布流`草稿/发布/下线`、URL前缀`/guide`、专题`手工+规则混合`且支持三类模块、外链“新窗口打开”。

## 1. 核心架构决策（已定）

1. 模型系统采用“固定主字段 + 扩展字段”。
2. 攻略分类独立于 `info_category`，新建 `guide_category`。
3. Tag 采用 `guide_tag` + `guide_tag_rel` 关系化存储，不用逗号字符串做主存储。
4. 外链攻略支持“录入后点击即外站”，并以新窗口打开。
5. 专题聚合采用“手工+规则混合”，专题页支持三类可排序模块：手选攻略、规则聚合、外链卡片。

## 2. 数据库设计（新增表）

1. `fa_guide_category`：`id,pid,name,slug,icon,weigh,status,createtime,updatetime`，用于攻略分类树。
2. `fa_guide_model`：`id,name,key,description,weigh,status,createtime,updatetime`，用于攻略模型（如景点/美食/办事）。
3. `fa_guide_field`：`id,model_id,name,key,type,required,options,placeholder,weigh,is_list_show,is_detail_show,status,createtime,updatetime`，扩展字段定义。
4. `fa_guide`：`id,category_id,model_id,title,keywords,summary,content,cover,images,attachments,source_url,is_external,status,publish_time,views,is_top,weigh,admin_id,createtime,updatetime`，攻略主表。
5. `fa_guide_ext_value`：`id,guide_id,field_id,key,value,value_number,value_date`，扩展字段值。
6. `fa_guide_tag`：`id,name,slug,use_count,status,createtime,updatetime`，标签主表。
7. `fa_guide_tag_rel`：`id,guide_id,tag_id,createtime`，唯一键`(guide_id,tag_id)`。
8. `fa_guide_topic`：`id,title,slug,summary,cover,banner,keywords,description,status,publish_time,weigh,createtime,updatetime`，专题主表。
9. `fa_guide_topic_block`：`id,topic_id,type,title,rule_json,weigh,status,createtime,updatetime`，专题模块定义，`type`取值：`manual_list/rule_list/external_cards`。
10. `fa_guide_topic_block_item`：`id,block_id,item_type,guide_id,title,url,image,summary,weigh,status,createtime,updatetime`，模块内容项；`item_type`取值：`guide/external`。

## 3. 后台模块规划（FastAdmin）

1. 新增后台命名空间：`application/admin/controller/guide`，包含 `Category/Model/Field/Tag/Guide/Topic`。
2. 新增后台视图目录：`application/admin/view/guide/*` 与 JS：`public/assets/js/backend/guide/*`。
3. 一级菜单新增“本地攻略系统”（与 `info` 同级），二级菜单为：分类管理、模型管理、字段管理、标签管理、攻略管理、专题管理。
4. 攻略录入表单字段固定包含：标题、关键字、标签、内容、图片、附件、他站链接、封面、摘要、分类、模型、状态、发布时间。
5. 富媒体编辑器使用当前已安装 `nkeditor`，`content` 字段采用 `.editor`。
6. 图片与附件使用 `faupload/fachoose` 组件，`images` 支持多图，`attachments` 支持多附件。
7. 动态扩展字段按 `model_id` 加载：新增接口 `guide/guide/getFields`，与现有 `info` 动态字段交互风格保持一致。
8. 标签输入采用 `tagsinput`（逗号输入）+ 服务端归一化入库（自动建 tag、同步关系表、回写 use_count）。
9. 攻略状态流：`draft/published/offline`；前台仅显示 `published` 且 `publish_time<=now`。
10. 外链逻辑：`is_external=1` 且 `source_url` 有值时，前台点击走外链逻辑；后台仍保留内容字段用于检索与兜底。

## 4. 前台模块规划

1. 新增前台控制器：`application/index/controller/Guide.php`。
2. 新增前台模板：`application/index/view/guide/list.html`、`detail.html`、`topic_list.html`、`topic_detail.html`。
3. 新增前台样式与脚本：`public/assets/frontend/css/guide-*.css`、`public/assets/frontend/js/guide-*.js`，视觉风格对齐 `info-list.css/info-detail.css`。
4. 导航栏新增“本地攻略”入口（`layout/default.html`），指向 `/guide`。
5. 列表页支持筛选：关键字、分类、模型、标签。
6. 详情页支持：主内容、图集、附件下载、标签、相关推荐（同分类/同模型）。
7. 专题列表页展示已发布专题；专题详情页按模块顺序渲染三类区块。
8. 规则聚合区块 `rule_json` 固定结构：`category_ids,model_ids,tag_ids,order_by,order_dir,limit,include_external`。
9. 外链点击默认 `target="_blank"` + `rel="noopener noreferrer nofollow"`。

## 5. 路由与对外接口变更（Public APIs/Interfaces）

1. 在 `application/route.php` 中于 `:cate` 兜底规则之前新增：
2. `guide` -> `index/guide/index`。
3. `guide/tag/:slug` -> `index/guide/tag`。
4. `guide/model/:key` -> `index/guide/model`。
5. `guide/topic` -> `index/guide/topicIndex`。
6. `guide/topic/:slug` -> `index/guide/topicDetail`。
7. `guide/:slug`（形如`{id}-{pinyin}`）-> `index/guide/detail`。
8. 后台 AJAX 接口新增：`guide/guide/getFields`、`guide/tag/selectpage`（标签联想）等。
9. 约定类型：
10. `Guide.status` = `draft|published|offline`。
11. `TopicBlock.type` = `manual_list|rule_list|external_cards`。
12. `TopicBlockItem.item_type` = `guide|external`。

## 6. 专题聚合实现细节（决策完成）

1. `manual_list`：从 `topic_block_item` 读取 `guide` 项，按 `weigh desc,id asc` 渲染。
2. `rule_list`：按 `rule_json` 实时查询 `fa_guide`，默认过滤 `published + publish_time<=now`。
3. `external_cards`：从 `topic_block_item` 读取 `external` 项，字段为标题/链接/封面/摘要。
4. 同一专题内规则区块默认去重已渲染攻略，避免重复曝光。
5. 专题支持 `draft/published/offline`，前台仅展示已发布。

## 7. 实施顺序

1. 建表 SQL 与菜单 SQL（放入 `doc/step2.*.sql`）并先在本地库执行。
2. 完成 `common/model` 与后台 `guide` 控制器、语言包、视图、JS。
3. 完成前台 `Guide` 控制器、模板、CSS/JS 与路由。
4. 完成标签归一化服务与专题模块渲染服务。
5. 联调并验收后台录入到前台展示全链路。

## 8. 测试用例与验收场景

1. 后台可创建分类、模型、字段、标签、攻略、专题并正常编辑删除。
2. 攻略录入时 `nkeditor`、多图上传、附件上传、标签输入均可保存并回显。
3. 模型切换时扩展字段动态加载正确，字段必填与类型校验生效。
4. 外链攻略在列表/专题/详情入口均新窗口打开远程页面。
5. 非外链攻略正常进入本站详情页。
6. 专题三类模块均可渲染，规则聚合筛选结果符合配置。
7. 未发布或下线的攻略/专题不在前台出现。
8. 路由不与原 `:cate` 规则冲突，`/guide/*` 全部命中预期控制器。
9. 标签页、模型页筛选结果正确，分页与排序正常。
10. 删除攻略后标签关系与 `use_count` 同步更新。

## 9. 明确假设与默认值

1. 本期仅管理员后台录入，不开放前台用户投稿攻略。
2. 不引入攻略评论、点赞、收藏等互动能力。
3. 不做独立“攻略频道首页装修器”，前台范围按已定为列表/详情/专题链路。
4. 继续使用现有上传与权限体系，不引入新中间件。
5. SEO 默认使用：`title + keywords + summary/description`，缺省时自动回退摘要。
