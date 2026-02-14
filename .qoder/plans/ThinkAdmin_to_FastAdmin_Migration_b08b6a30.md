# ThinkAdmin 到 FastAdmin 分步迁移计划

## 背景说明

- **源项目**: `D:\phpEnv\www\ccw99.com` (ThinkAdmin, DB: ccw99)
- **目标项目**: `D:\phpEnv\www\ccw88.com` (FastAdmin 1.6.1, DB: fd88, 表前缀 `fa_`)
- **核心原则**: 不完全 copy 源代码，按 FastAdmin CRUD 最佳实践重写

## 关键差异（ThinkAdmin vs FastAdmin）

| 项目 | ThinkAdmin (ccw99) | FastAdmin (ccw88) |
|---|---|---|
| 表前缀 | 无前缀 | `fa_` |
| 控制器基类 | `think\admin\Controller` | `app\common\controller\Backend` |
| 模型基类 | `think\admin\Model` | `think\common\model\Base` 或 ThinkPHP `Model` |
| CRUD方式 | `mQuery/mForm/mSave/mDelete` | `$this->model->...` + traits\Backend |
| 视图 | ThinkAdmin layTable 模板 | Bootstrap Table + RequireJS 模块化 JS |
| 表单提交 | 直接 POST | `row[field]` 方式提交 |
| 时间戳字段 | `created_at/updated_at` | `createtime/updatetime`（FastAdmin 默认）|

---

## 第一大模块：分类信息系统（后台管理）

### 步骤 1.1：数据库表创建

在 `fd88` 数据库中创建以下表（表名加 `fa_` 前缀），我会生成 SQL 语句供你手动执行：

1. **`fa_info_category`** - 分类信息的分类表（避开 FastAdmin 自带的 `fa_category`）
   - 基于源表 `category` 结构，增加 FastAdmin 常用字段 `createtime/updatetime`
   - 字段：id, name, pid(原parent_id), slug, icon, weigh(原sort_order), status(原is_active), createtime, updatetime

2. **`fa_info_field`** - 自定义字段定义表
   - 基于源表 `field_definition` 结构
   - 字段：id, category_id, sub_category_id, name, key, type, required, options, placeholder, weigh, is_filter, is_list_show, is_detail_show, status, createtime, updatetime

3. **`fa_info_field_value`** - 自定义字段值表
   - 基于源表 `field_value` 结构
   - 字段：id, info_id, field_id, key, value, value_number, value_date

4. **`fa_info`** - 分类信息主表
   - 基于源表 `info` 结构
   - 字段适配 FastAdmin 规范：status 改为枚举整型或保留字符串、时间字段用 int 时间戳

5. **`fa_info_review_log`** - 审核日志表
   - 基于源表 `info_review_log`

### 步骤 1.2：分类管理 CRUD

按 FastAdmin 最佳实践创建，文件清单：

- **模型**: `application/common/model/InfoCategory.php`
- **控制器**: `application/admin/controller/info/Category.php`（放在 info 子目录避开已有 Category）
- **视图**: `application/admin/view/info/category/index.html`, `add.html`, `edit.html`
- **JS**: `public/assets/js/backend/info/category.js`
- **语言包**: `application/admin/lang/zh-cn/info/category.php`

功能：树形分类管理（parent_id），支持搜索、启用/禁用、排序

### 步骤 1.3：自定义字段管理 CRUD

- **模型**: `application/common/model/InfoField.php`
- **控制器**: `application/admin/controller/info/Field.php`
- **视图**: `application/admin/view/info/field/index.html`, `add.html`, `edit.html`
- **JS**: `public/assets/js/backend/info/field.js`
- **语言包**: `application/admin/lang/zh-cn/info/field.php`

功能：字段定义管理，关联分类联动选择，支持多种字段类型配置

### 步骤 1.4：分类信息管理（信息录入/管理）CRUD

- **模型**: `application/common/model/Info.php`, `application/common/model/InfoFieldValue.php`
- **控制器**: `application/admin/controller/info/Info.php`
- **视图**: `application/admin/view/info/info/index.html`, `add.html`, `edit.html`
- **JS**: `public/assets/js/backend/info/info.js`
- **语言包**: `application/admin/lang/zh-cn/info/info.php`

功能：信息录入和管理，支持动态自定义字段，分类联动，图片上传，有效期管理

### 步骤 1.5：审核中心

- **控制器**: `application/admin/controller/info/Review.php`
- **视图**: `application/admin/view/info/review/index.html`
- **JS**: `public/assets/js/backend/info/review.js`
- **语言包**: `application/admin/lang/zh-cn/info/review.php`

功能：待审核信息列表、通过/拒绝操作、审核日志记录

### 步骤 1.6：菜单注册和权限配置

生成 SQL 插入 `fa_auth_rule` 表的菜单记录，供你手动执行。

---

## 第二大模块：前台用户中心

### 步骤 2.1：获取前端样式

从 `D:\phpEnv\www\ccw99.com\app\index\view\user` 获取视图样式，适配到 FastAdmin 前台 `application/index/view/user/` 目录。

### 步骤 2.2：功能对接

保留 FastAdmin 用户中心原有功能（登录、注册、个人资料、密码修改等），仅替换前端模板样式。

### 步骤 2.3：静态文件统一

确保调用的 CSS/JS/图片等静态资源与前台前端保持一致。

---

## 第三大模块：前台前端

### 步骤 3.1：首页模板

从 `D:\phpEnv\www\ccw99.com\app\index\view\index` 获取首页模板，适配到 FastAdmin 的 `application/index/view/index/` 目录。

### 步骤 3.2：分类信息前台页面

从 `D:\phpEnv\www\ccw99.com\app\index\view\info` 获取信息列表/详情模板，创建对应的前台控制器和视图。

### 步骤 3.3：静态资源迁移

将相关的 CSS、JS、图片等静态文件迁移到 `public/assets/` 对应目录。

---

## 执行顺序

建议严格按以下顺序执行：

1. 先完成 **第一大模块步骤 1.1**（建表），你手动执行 SQL
2. 再逐步完成 **步骤 1.2 ~ 1.6**（后台管理功能），每步完成后验证
3. 然后处理 **第二大模块**（前台用户中心）
4. 最后处理 **第三大模块**（前台前端）

每个步骤完成后都会暂停确认，确保功能正常后再进入下一步。

## 首先从步骤 1.1 开始

确认此计划后，我将先生成建表 SQL（`fa_info_category` 等表），提供给你手动执行。