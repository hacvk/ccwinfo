# 前台URL SEO优化规划方案

## 一、当前URL结构分析

### 1.1 现有URL格式
```
首页: / 或 /index/index/index
信息列表: /index/info/lists 或 /info/lists.html
信息详情: /index/info/detail/id/1 或 /info/detail/id/1.html
分类列表: /index/info/lists/cate_id/1
用户中心: /index/user/index
```

### 1.2 存在的问题
1. **层级过深**: `/index/info/lists` 包含多余的模块层级
2. **参数不友好**: `/id/1` 格式对搜索引擎不友好
3. **缺少语义化**: URL中没有包含关键词信息
4. **分类ID暴露**: 直接使用数字ID，不利于SEO
5. **缺少层级结构**: 分类没有体现父子关系

---

## 二、SEO优化目标URL设计

### 2.1 首页
```
当前: /index/index/index
优化: /
```

### 2.2 分类列表页
```
当前: /info/lists/cate_id/1.html
优化: /分类别名/
      /jobs/          (招聘)
      /houses/        (房产)
      /cars/          (车辆)
      /jobs/fulltime/ (全职招聘-子分类)
```

### 2.3 信息详情页
```
当前: /info/detail/id/1.html
优化: /ID拼音字头.html
      /1234-phpkf.html      (PHP开发工程师 - ID:1234)
      /5678-lsyct.html      (两室一厅出租 - ID:5678)
      /9999-ercsbc.html     (二手叉车出售 - ID:9999)
```

**拼音字头规则**：取标题每个汉字的首字母，如"PHP开发工程师" → "phpkf"

### 2.4 用户中心
```
当前: /user/index
优化: /member/           (用户中心首页)
      /member/profile/   (个人资料)
      /member/posts/     (我的发布)
```

### 2.5 搜索页
```
当前: /info/lists/keyword/关键词.html
优化: /search/关键词/
      /search/关键词/cate/分类别名/
```

### 2.6 其他功能页
```
当前: /info/publish
优化: /publish/          (发布信息)
      /publish/分类别名/  (发布指定分类)

当前: /signin/index
优化: /signin/           (签到)

当前: /user/login
优化: /login/            (登录)

当前: /user/register  
优化: /register/         (注册)
```

---

## 三、路由规则实现方案

### 3.1 需要修改的文件
1. `application/route.php` - 添加路由规则
2. `application/index/controller/Info.php` - 支持别名参数
3. 数据库分类表 - 添加别名字段（如不存在）

### 3.2 路由规则示例
```php
// 首页
Route::get('/', 'index/index');

// 分类列表（支持多级）
Route::get(':cate_alias', 'info/lists');
Route::get(':parent_alias/:cate_alias', 'info/lists');

// 信息详情（ID+拼音字头.html）
Route::get(':id-:pinyin', 'info/detail')->pattern(['id' => '\d+', 'pinyin' => '[a-z]+']);

// 用户相关
Route::get('member', 'user/index');
Route::get('member/:action', 'user/:action');
Route::get('login', 'user/login');
Route::get('register', 'user/register');

// 功能页
Route::get('publish', 'info/publish');
Route::get('publish/:cate_alias', 'info/publish');
Route::get('search/:keyword', 'info/search');

// 签到
Route::get('signin', 'signin/index');
```

---

## 四、实施步骤

### 第一阶段：准备工作
1. 检查分类表结构，确认是否有别名字段
2. 为分类添加SEO友好的别名
3. 备份现有路由配置

### 第二阶段：路由改造
1. 修改 `route.php` 添加新路由规则
2. 修改控制器支持新参数
3. 更新URL生成方法

### 第三阶段：模板更新
1. 更新所有模板中的链接
2. 确保新旧URL兼容

### 第四阶段：301重定向
1. 添加旧URL到新URL的301重定向规则
2. 更新robots.txt和sitemap

---

## 五、URL命名规范

### 5.1 分类别名规则
- 使用小写英文字母
- 多个单词用连字符(-)连接
- 避免使用特殊字符
- 示例: `full-time-jobs`, `house-rent`, `second-hand-cars`

### 5.2 详情页拼音字头规则
- 取标题每个汉字的首字母（拼音首字母）
- 保留英文单词/数字原样
- 全部小写
- 限制长度（建议4-10个字母）
- 示例:
  - "PHP开发工程师" → `phpkf`
  - "两室一厅出租" → `lsyct`
  - "二手叉车出售" → `ercsbc`
  - "3室2厅精装修" → `3s2tjzx`

---

## 六、预期效果

1. **URL更短更简洁**: 从 `/info/detail/id/1.html` 变为 `/1234-phpkf.html`
2. **包含关键词**: URL中包含标题拼音字头，利于SEO
3. **层级清晰**: 分类层级体现在URL路径中
4. **用户友好**: 用户可通过URL理解页面内容
5. **搜索引擎友好**: 提高页面权重和收录效率

---

## 七、注意事项

1. 保持新旧URL兼容，避免404错误
2. 设置301重定向传递权重
3. 更新站内链接避免死链
4. 更新sitemap.xml
5. 提交新URL给搜索引擎

---

**请确认此方案是否符合您的需求，或需要调整哪些部分？**
