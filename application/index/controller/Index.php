<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\Info;
use app\common\model\InfoCategory;
use app\common\model\PromotionItem;
use app\common\model\PromotionSlot;
use app\common\model\Guide as GuideEntity;
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
        // 查询最新攻略
        $now = time();
        $latestGuideRows = GuideEntity::where('status', 'published')
            ->where('publish_time', '<=', $now)
            ->where('title', '<>', '')
            ->order('is_top desc, publish_time desc, id desc')
            ->limit(18)
            ->select();
        $latestGuideRows = $this->queryResultToArray($latestGuideRows);
        $latestGuides = $this->normalizeGuideRows($latestGuideRows);
        $latestGuides = $this->filterHomepageGuideRows($latestGuides, true);

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
        $this->assign('latestGuides', $latestGuides);

        // home_header_text: homepage scrolling text ads
        $headerTextAds = [];
        try {
            $headerSlot = PromotionSlot::where('code', 'home_header_text')
                ->where('status', 'normal')
                ->find();
            if ($headerSlot) {
                $slotLimit = (int)($headerSlot['max_items'] ?? 0);
                $slotLimit = $slotLimit > 0 ? $slotLimit : 16;
                $now = time();
                $query = PromotionItem::where('slot_id', (int)$headerSlot['id'])
                    ->where('status', 'online')
                    ->where('review_status', 'approved');

                $query->where(function ($q) use ($now) {
                    $q->whereNull('start_time')
                      ->whereOr('start_time', 0)
                      ->whereOr('start_time', '<=', $now);
                });
                $query->where(function ($q) use ($now) {
                    $q->whereNull('end_time')
                      ->whereOr('end_time', 0)
                      ->whereOr('end_time', '>=', $now);
                });

                $headerList = $query->order('sort desc, id desc')->limit($slotLimit)->select();
                $headerTextAds = is_object($headerList) ? $headerList->toArray() : (array)$headerList;
            }
        } catch (\Throwable $e) {
            $headerTextAds = [];
        }
        $this->assign('headerTextAds', $headerTextAds);

        // home_middle_promo: middle image promo slot
        $middlePromos = [];
        try {
            $middleSlot = PromotionSlot::where('code', 'home_middle_promo')
                ->where('status', 'normal')
                ->find();
            if ($middleSlot) {
                $slotLimit = (int)($middleSlot['max_items'] ?? 0);
                $slotLimit = $slotLimit > 0 ? $slotLimit : 3;
                $now = time();
                $query = PromotionItem::where('slot_id', (int)$middleSlot['id'])
                    ->where('status', 'online')
                    ->where('review_status', 'approved');

                $query->where(function ($q) use ($now) {
                    $q->whereNull('start_time')
                      ->whereOr('start_time', 0)
                      ->whereOr('start_time', '<=', $now);
                });
                $query->where(function ($q) use ($now) {
                    $q->whereNull('end_time')
                      ->whereOr('end_time', 0)
                      ->whereOr('end_time', '>=', $now);
                });

                $middleList = $query->order('sort desc, id desc')->limit($slotLimit)->select();
                $middlePromos = is_object($middleList) ? $middleList->toArray() : (array)$middleList;
            }
        } catch (\Throwable $e) {
            $middlePromos = [];
        }
        $this->assign('middlePromos', $middlePromos);

        // 友情链接
        $friendLinks = [];
        try {
            $slot = PromotionSlot::where('code', 'friend_links')
                ->where('status', 'normal')
                ->find();
            if ($slot) {
                $now = time();
                $query = PromotionItem::where('slot_id', (int)$slot['id'])
                    ->where('status', 'online')
                    ->where('review_status', 'approved');

                $query->where(function ($q) use ($now) {
                    $q->whereNull('start_time')
                      ->whereOr('start_time', 0)
                      ->whereOr('start_time', '<=', $now);
                });
                $query->where(function ($q) use ($now) {
                    $q->whereNull('end_time')
                      ->whereOr('end_time', 0)
                      ->whereOr('end_time', '>=', $now);
                });

                $list = $query->order('sort desc, id desc')->limit(20)->select();
                $friendLinks = is_object($list) ? $list->toArray() : (array)$list;
            }
        } catch (\Throwable $e) {
            $friendLinks = [];
        }
        $this->assign('friendLinks', $friendLinks);

        // 页面级CSS
        $cdnUrl = \think\Config::get('site.cdnurl') ?: '';
        $this->assign('page_css', [
            $cdnUrl . '/assets/frontend/frontend-theme-v1/css/index.css',
        ]);
        
        $this->assign('title', '首页');

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

    /**
     * 规范化攻略数据行数组
     */
    /**
     * 将查询结果转换为数组
     */
    protected function queryResultToArray($result)
    {
        if (empty($result)) {
            return [];
        }

        // 如果是单个对象
        if (is_object($result)) {
            return method_exists($result, 'toArray') ? $result->toArray() : (array)$result;
        }

        // 如果是集合
        $arrayResult = [];
        foreach ($result as $item) {
            $arrayResult[] = $this->queryResultToArray($item);
        }
        return $arrayResult;
    }

    protected function normalizeGuideRows(array $rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->normalizeGuideRow($row);
        }
        return $result;
    }

    /**
     * 规范化单个攻略数据行
     */
    protected function normalizeGuideRow(array $row)
    {
        $images = $this->parseMediaField($row['images'] ?? '');
        $attachments = $this->parseMediaField($row['attachments'] ?? '');
        $cover = trim((string)($row['cover'] ?? ''));
        if ($cover === '' && !empty($images[0])) {
            $cover = $images[0];
        }

        $sourceUrl = trim((string)($row['source_url'] ?? ''));
        $isExternal = (int)($row['is_external'] ?? 0) === 1 && $sourceUrl !== '';
        $detailUrl = function_exists('guide_url') ? guide_url($row) : $this->buildGuideDetailUrl($row);
        $jumpUrl = $isExternal ? $sourceUrl : $detailUrl;

        $summary = trim((string)($row['summary'] ?? ''));
        if ($summary === '') {
            $summary = mb_substr(strip_tags((string)($row['content'] ?? '')), 0, 120);
        }
        $publishTime = (int)($row['publish_time'] ?? 0);
        if ($publishTime < 1) {
            $publishTime = (int)($row['createtime'] ?? 0);
        }
        if ($publishTime < 1) {
            $publishTime = (int)($row['updatetime'] ?? 0);
        }

        return [
            'id'                => (int)($row['id'] ?? 0),
            'category_id'       => (int)($row['category_id'] ?? 0),
            'model_id'          => (int)($row['model_id'] ?? 0),
            'title'             => trim((string)($row['title'] ?? '')),
            'summary'           => $summary,
            'content'           => (string)($row['content'] ?? ''),
            'cover'             => $cover,
            'images'            => $images,
            'attachments'       => $attachments,
            'is_external'       => $isExternal ? 1 : 0,
            'source_url'        => $sourceUrl,
            'detail_url'        => $detailUrl,
            'jump_url'          => $jumpUrl,
            'status'            => (string)($row['status'] ?? ''),
            'publish_time'      => $publishTime,
            'publish_time_text' => $publishTime > 0 ? date('Y-m-d H:i', $publishTime) : '-',
            'views'             => (int)($row['views'] ?? 0),
            'is_top'            => (int)($row['is_top'] ?? 0),
        ];
    }

    /**
     * 解析媒体字段（图片或附件）
     */
    protected function parseMediaField($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), function ($v) {
                return $v !== '';
            }));
        }
        $text = trim((string)$value);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), function ($v) {
                return $v !== '';
            }));
        }
        return array_values(array_filter(array_map('trim', explode(',', $text)), function ($v) {
            return $v !== '';
        }));
    }

    /**
     * 构建攻略详情页URL
     */
    protected function buildGuideDetailUrl(array $row)
    {
        $id = (int)($row['id'] ?? 0);
        if ($id < 1) {
            return '';
        }

        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug === '') {
            $title = strtolower(trim((string)($row['title'] ?? '')));
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $title);
            $slug = trim((string)$slug, '-');
        }

        if ($slug === '') {
            $slug = 'guide';
        }

        return "/guide/{$id}-{$slug}";
    }

    /**
     * 过滤首页攻略数据
     */
    protected function filterHomepageGuideRows(array $rows, $requireDate = false)
    {
        $result = [];
        foreach ($rows as $row) {
            $title = trim((string)($row['title'] ?? ''));
            $jumpUrl = trim((string)($row['jump_url'] ?? ''));
            $publishTimeText = trim((string)($row['publish_time_text'] ?? ''));
            if ($title === '') {
                continue;
            }
            if (!$this->isValidGuideJumpUrl($jumpUrl)) {
                continue;
            }
            if ($requireDate && ($publishTimeText === '' || $publishTimeText === '-')) {
                continue;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * 验证攻略跳转URL的有效性
     */
    protected function isValidGuideJumpUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return false;
        }
        return preg_match('/^https?:\/\//i', $url) || strpos($url, '/') === 0;
    }
}
