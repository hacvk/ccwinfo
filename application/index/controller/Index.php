<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\Info;
use app\common\model\InfoCategory;
use think\Db;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = 'default';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        // 查询一级分类
        $categories = InfoCategory::where('pid', 0)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $categories = is_object($categories) ? $categories->toArray() : (array)$categories;

        // 查询最新已发布信息
        $latest = Info::where('status', 'published')
            ->order('is_top desc, createtime desc')
            ->limit(36)
            ->select();
        $latest = is_object($latest) ? $latest->toArray() : (array)$latest;

        // 处理摘要和图片
        foreach ($latest as &$item) {
            $item['summary'] = mb_substr(strip_tags((string)($item['content'] ?? '')), 0, 80);
            $item['images_arr'] = $this->parseImages($item['images'] ?? '');
            $item['created_display'] = $this->formatCreatedDisplay($item['createtime'] ?? 0);
        }
        unset($item);

        // 置顶信息
        $topInfos = Info::where('status', 'published')
            ->where('is_top', '>', 0)
            ->order('is_top desc, createtime desc')
            ->limit(12)
            ->select();
        $topInfos = is_object($topInfos) ? $topInfos->toArray() : (array)$topInfos;

        if (empty($topInfos)) {
            $topInfos = array_slice($latest, 0, 10);
        }

        foreach ($topInfos as &$item) {
            $item['created_display'] = $this->formatCreatedDisplay($item['createtime'] ?? 0);
        }
        unset($item);

        // 构建栏目导航数据
        $categoryGroups = $this->buildCategoryGroups($categories);

        $this->assign('categories', $categories);
        $this->assign('latestInfos', $latest);
        $this->assign('topInfos', array_slice($topInfos, 0, 12));
        $this->assign('normalInfos', array_slice($latest, 0, 24));
        $this->assign('sideInfos', array_slice($latest, 0, 12));
        $this->assign('categoryGroups', $categoryGroups);
        // 页面级CSS
        $cdnUrl = \think\Config::get('site.cdnurl') ?: '';
        $this->assign('page_css', [
            $cdnUrl . '/assets/frontend/frontend-theme-v1/css/index.css',
        ]);

        return $this->view->fetch();
    }

    /**
     * 获取子分类 (AJAX API)
     */
    public function subCategories()
    {
        $parentId = (int)$this->request->param('parent_id', 0);
        if ($parentId < 1) {
            return json(['code' => 1, 'data' => []]);
        }

        $data = InfoCategory::where('pid', $parentId)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $data = is_object($data) ? $data->toArray() : (array)$data;

        return json(['code' => 1, 'data' => $data]);
    }

    /**
     * 构建栏目导航数据
     */
    private function buildCategoryGroups(array $categories)
    {
        if (empty($categories)) {
            return [];
        }

        $parentIds = array_column($categories, 'id');
        if (empty($parentIds)) {
            return [];
        }

        $subRows = InfoCategory::where('status', 'normal')
            ->whereIn('pid', $parentIds)
            ->order('weigh desc, id asc')
            ->select();
        $subRows = is_object($subRows) ? $subRows->toArray() : (array)$subRows;

        $subs = [];
        foreach ($subRows as $row) {
            $pid = (int)($row['pid'] ?? 0);
            $subs[$pid][] = [
                'id'   => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'slug' => (string)($row['slug'] ?? ''),
                'pid'  => $pid,
            ];
        }

        $groups = [];
        foreach ($categories as $category) {
            $cid = (int)($category['id'] ?? 0);
            $groups[] = [
                'id'   => $cid,
                'name' => (string)($category['name'] ?? ''),
                'slug' => (string)($category['slug'] ?? ''),
                'subs' => array_slice($subs[$cid] ?? [], 0, 8),
            ];
        }

        return $groups;
    }

    /**
     * 解析images字段为数组
     */
    private function parseImages($images)
    {
        if (empty($images)) {
            return [];
        }
        // Info 模型的 getImagesAttr 返回逗号分隔字符串，需兼容
        if (is_array($images)) {
            return $images;
        }
        $decoded = json_decode($images, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // 逗号分隔
        return array_filter(array_map('trim', explode(',', $images)), function ($v) {
            return $v !== '';
        });
    }

    /**
     * 格式化发布时间（24小时内显示X小时前，否则显示日期）
     */
    private function formatCreatedDisplay($timestamp)
    {
        $timestamp = (int)$timestamp;
        if ($timestamp < 1) {
            return '-';
        }
        $now = time();
        $diff = $now - $timestamp;
        if ($diff >= 0 && $diff < 86400) {
            $hours = max(1, (int)ceil($diff / 3600));
            return sprintf('%d小时前', $hours);
        }
        return date('Y-m-d', $timestamp);
    }
}
