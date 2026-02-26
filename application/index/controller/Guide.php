<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\GuideTopicService;
use app\common\model\Guide as GuideEntity;
use app\common\model\GuideCategory;
use app\common\model\GuideExtValue;
use app\common\model\GuideField;
use app\common\model\GuideModel as GuideModelEntity;
use app\common\model\GuideTag;
use app\common\model\GuideTagRel;
use app\common\model\GuideTopic;
use think\Db;

class Guide extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = 'default';

    protected $now = 0;
    protected $guideCategoryContext = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->now = time();
        $this->assignconfig('jsname', '');
        $this->assignGuideSubnavData(0, false);
    }

    public function index()
    {
        $focusRows = $this->baseGuideQuery()
            ->where('is_top', 1)
            ->order('publish_time desc,id desc')
            ->limit(8)
            ->select();
        $focusRows = is_object($focusRows) ? $focusRows->toArray() : (array)$focusRows;

        $latestRows = $this->baseGuideQuery()
            ->where('title', '<>', '')
            ->order('is_top desc,publish_time desc,id desc')
            ->limit(60)
            ->select();
        $latestRows = is_object($latestRows) ? $latestRows->toArray() : (array)$latestRows;
        $latestGuideCount = (int)$this->baseGuideQuery()->count();
        $focusGuides = $this->filterHomepageGuideRows($this->normalizeGuideRows($focusRows), false);
        $latestGuides = $this->filterHomepageGuideRows($this->normalizeGuideRows($latestRows), true);
        if (count($latestGuides) > 18) {
            $latestGuides = array_slice($latestGuides, 0, 18);
        }

        $topicRows = GuideTopic::where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->order('publish_time desc,weigh desc,id desc')
            ->limit(8)
            ->select();
        $topicRows = is_object($topicRows) ? $topicRows->toArray() : (array)$topicRows;
        foreach ($topicRows as &$topic) {
            $topic['summary'] = trim((string)($topic['summary'] ?? ''));
            if ($topic['summary'] === '') {
                $topic['summary'] = mb_substr(strip_tags((string)($topic['description'] ?? '')), 0, 80);
            }
            $topic['cover'] = trim((string)($topic['cover'] ?? ''));
            if ($topic['cover'] === '') {
                $topic['cover'] = trim((string)($topic['banner'] ?? ''));
            }
        }
        unset($topic);

        $hotTags = GuideTag::where('status', 'normal')
            ->order('use_count desc,id desc')
            ->limit(24)
            ->select();
        $hotTags = is_object($hotTags) ? $hotTags->toArray() : (array)$hotTags;

        $hotModels = GuideModelEntity::where('status', 'normal')
            ->order('weigh desc,id asc')
            ->limit(24)
            ->select();
        $hotModels = is_object($hotModels) ? $hotModels->toArray() : (array)$hotModels;

        $this->assign('focusGuides', $focusGuides);
        $this->assign('latestGuides', $latestGuides);
        $this->assign('latestGuideCount', $latestGuideCount);
        $this->assign('hotTopics', $topicRows);
        $this->assign('hotTags', $hotTags);
        $this->assign('hotModels', $hotModels);
        $this->assign('guideNav', 'home');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
        ]);
        $this->assign('title', '本地攻略');

        return $this->view->fetch('guide/index');
    }

    public function lists()
    {
        return $this->renderListPage([
            'guideNav' => 'list',
            'title'    => '攻略列表',
        ]);
    }

    public function detail()
    {
        $id = (int)$this->request->param('id', 0);
        $slug = trim((string)$this->request->param('slug', ''));
        $rawUrl = (string)$this->request->url();
        $legacyHtmlSlug = $rawUrl !== ''
            && preg_match('/\/guide\/\d+-[^\/?#]+\.html(?:\?.*)?$/i', $rawUrl) === 1;
        if ($id < 1 && $slug !== '' && preg_match('/^(\d+)-(.+)$/', $slug, $matches)) {
            $id = (int)$matches[1];
        }
        if ($id < 1) {
            $this->error('攻略不存在');
        }

        $guide = $this->baseGuideQuery()->where('id', $id)->find();
        if (!$guide) {
            $this->error('攻略不存在或未发布');
        }

        $row = is_object($guide) ? $guide->toArray() : (array)$guide;
        $detailUrl = function_exists('guide_url') ? guide_url($row) : $this->buildGuideDetailUrl($row);
        if ($legacyHtmlSlug && $detailUrl !== '') {
            return $this->redirect($detailUrl, 301);
        }

        $sourceUrl = trim((string)($row['source_url'] ?? ''));
        $isExternal = (int)($row['is_external'] ?? 0) === 1 && $sourceUrl !== '';
        if ($isExternal) {
            return $this->redirect($sourceUrl, 302);
        }

        GuideEntity::where('id', $id)->setInc('views');
        $row['views'] = (int)($row['views'] ?? 0) + 1;

        $detail = $this->normalizeGuideRow($row);
        $detail['content'] = (string)($row['content'] ?? '');
        $detail['keywords'] = trim((string)($row['keywords'] ?? ''));
        $detailPublishTime = (int)($row['publish_time'] ?? 0);
        if ($detailPublishTime < 1) {
            $detailPublishTime = (int)($row['createtime'] ?? 0);
        }
        if ($detailPublishTime < 1) {
            $detailPublishTime = (int)($row['updatetime'] ?? 0);
        }
        $detail['publish_time_text'] = $detailPublishTime > 0 ? date('Y-m-d H:i:s', $detailPublishTime) : '-';
        $detailKeywords = [];
        if ($detail['keywords'] !== '') {
            $parts = preg_split('/[\s,，]+/u', $detail['keywords']);
            foreach ((array)$parts as $part) {
                $part = trim((string)$part);
                if ($part === '' || in_array($part, $detailKeywords, true)) {
                    continue;
                }
                $detailKeywords[] = $part;
            }
        }

        $tagRows = GuideTag::alias('t')
            ->join('__GUIDE_TAG_REL__ r', 'r.tag_id=t.id')
            ->where('r.guide_id', $id)
            ->where('t.status', 'normal')
            ->field('t.id,t.name,t.slug,t.use_count')
            ->order('t.use_count desc,t.id desc')
            ->select();
        $tagRows = is_object($tagRows) ? $tagRows->toArray() : (array)$tagRows;

        $extRows = GuideExtValue::alias('v')
            ->join('__GUIDE_FIELD__ f', 'f.id=v.field_id')
            ->where('v.guide_id', $id)
            ->where('f.status', 'normal')
            ->where('f.is_detail_show', 1)
            ->field('f.name,f.type,v.value')
            ->order('f.weigh desc,f.id asc')
            ->select();
        $extRows = is_object($extRows) ? $extRows->toArray() : (array)$extRows;
        $extFields = [];
        foreach ($extRows as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $value = trim((string)($item['value'] ?? ''));
            $type = trim((string)($item['type'] ?? 'text'));
            if ($type === 'checkbox' && $value !== '') {
                $parts = array_filter(array_map('trim', explode(',', $value)), function ($v) {
                    return $v !== '';
                });
                $value = implode('、', $parts);
            }
            if ($name === '' || $value === '') {
                continue;
            }
            $extFields[] = [
                'name'  => $name,
                'value' => $value,
            ];
        }

        $relatedQuery = $this->baseGuideQuery()->where('id', '<>', $id);
        $categoryId = (int)($row['category_id'] ?? 0);
        $modelId = (int)($row['model_id'] ?? 0);
        if ($categoryId > 0 && $modelId > 0) {
            $relatedQuery->where(function ($q) use ($categoryId, $modelId) {
                $q->where('category_id', $categoryId)
                    ->whereOr('model_id', $modelId);
            });
        } elseif ($categoryId > 0) {
            $relatedQuery->where('category_id', $categoryId);
        } elseif ($modelId > 0) {
            $relatedQuery->where('model_id', $modelId);
        }

        $relatedRows = $relatedQuery->order('is_top desc,publish_time desc,id desc')
            ->limit(8)
            ->select();
        $relatedRows = is_object($relatedRows) ? $relatedRows->toArray() : (array)$relatedRows;
        $categoryContext = $this->getGuideCategoryContext();
        $detailCategoryPath = $this->buildGuideCategoryPath($categoryId, $categoryContext);
        $guideCurrentTopCategoryId = $this->resolveGuideTopCategoryId($categoryId, $categoryContext);
        $detailModel = [];
        if ($modelId > 0) {
            $model = GuideModelEntity::where('id', $modelId)->field('id,name,key')->find();
            $detailModel = $model ? (is_array($model) ? $model : $model->toArray()) : [];
        }

        $this->assign('info', $detail);
        $this->assign('tags', $tagRows);
        $this->assign('extFields', $extFields);
        $this->assign('related', $this->normalizeGuideRows($relatedRows));
        $this->assign('detailCategoryPath', $detailCategoryPath);
        $this->assign('detailModel', $detailModel);
        $this->assign('detailKeywords', $detailKeywords);
        $this->assignGuideSubnavData($guideCurrentTopCategoryId, false);
        $this->assign('guideNav', 'list');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-detail.css',
        ]);
        $this->assign('title', (string)($detail['title'] ?? '攻略详情'));

        return $this->view->fetch('guide/detail');
    }

    public function topicIndex()
    {
        $list = GuideTopic::where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->order('publish_time desc,weigh desc,id desc')
            ->paginate(12, false, ['query' => $this->request->get()]);

        $rows = $list ? $list->items() : [];
        foreach ($rows as &$row) {
            $row = is_array($row) ? $row : $row->toArray();
            $row['summary'] = trim((string)($row['summary'] ?? ''));
            if ($row['summary'] === '') {
                $row['summary'] = mb_substr(strip_tags((string)($row['description'] ?? '')), 0, 100);
            }
            $row['cover'] = trim((string)($row['cover'] ?? ''));
            if ($row['cover'] === '') {
                $row['cover'] = trim((string)($row['banner'] ?? ''));
            }
            $row['publish_time_text'] = !empty($row['publish_time']) ? date('Y-m-d', (int)$row['publish_time']) : '-';
        }
        unset($row);

        $this->assign('topics', $rows);
        $this->assign('pager', $list ? $list->render() : '');
        $this->assign('guideNav', 'topic');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-topic.css',
        ]);
        $this->assign('title', '攻略专题');

        return $this->view->fetch('guide/topic_list');
    }

    public function topicDetail($slug = '')
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            $slug = trim((string)$this->request->param('slug', ''));
        }
        if ($slug === '') {
            $this->error('专题不存在');
        }

        $service = new GuideTopicService();
        $topic = $service->getPublishedTopicBySlug($slug);
        if (!$topic) {
            $this->error('专题不存在或未发布');
        }

        $topicData = is_object($topic) ? $topic->toArray() : (array)$topic;
        $topicData['cover'] = trim((string)($topicData['cover'] ?? ''));
        if ($topicData['cover'] === '') {
            $topicData['cover'] = trim((string)($topicData['banner'] ?? ''));
        }
        $topicData['publish_time_text'] = !empty($topicData['publish_time']) ? date('Y-m-d H:i:s', (int)$topicData['publish_time']) : '-';

        $blocks = $service->buildBlocks((int)$topicData['id']);
        foreach ($blocks as &$block) {
            if (empty($block['items']) || !is_array($block['items'])) {
                $block['items'] = [];
                continue;
            }
            foreach ($block['items'] as &$item) {
                $item['cover'] = trim((string)($item['cover'] ?? ''));
                $item['summary'] = trim((string)($item['summary'] ?? ''));
                if ($item['summary'] === '') {
                    $item['summary'] = mb_substr(strip_tags((string)($item['content'] ?? '')), 0, 90);
                }
                $item['jump_url'] = trim((string)($item['jump_url'] ?? ''));
                $item['detail_url'] = trim((string)($item['detail_url'] ?? ''));
                if ($item['jump_url'] === '' && $item['detail_url'] !== '') {
                    $item['jump_url'] = $item['detail_url'];
                }
                $item['is_external'] = !empty($item['is_external']) ? 1 : 0;
                $item['publish_time_text'] = !empty($item['publish_time']) ? date('Y-m-d', (int)$item['publish_time']) : '';
            }
            unset($item);
        }
        unset($block);

        $this->assign('topic', $topicData);
        $this->assign('blocks', $blocks);
        $this->assign('guideNav', 'topic');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-topic.css',
        ]);
        $this->assign('title', (string)($topicData['title'] ?? '专题详情'));

        return $this->view->fetch('guide/topic_detail');
    }

    public function tagIndex()
    {
        $list = GuideTag::where('status', 'normal')
            ->order('use_count desc,id desc')
            ->paginate(60, false, ['query' => $this->request->get()]);
        $rows = $list ? $list->items() : [];
        $rows = is_array($rows) ? array_map(function ($row) {
            return is_array($row) ? $row : $row->toArray();
        }, $rows) : [];

        $this->assign('tags', $rows);
        $this->assign('pager', $list ? $list->render() : '');
        $this->assign('guideNav', 'tag');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-list.css',
        ]);
        $this->assign('title', '攻略标签');

        return $this->view->fetch('guide/tag_index');
    }

    public function tag($slug = '')
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            $slug = trim((string)$this->request->param('slug', ''));
        }
        if ($slug === '') {
            $this->error('标签不存在');
        }

        $tag = GuideTag::where('slug', $slug)->where('status', 'normal')->find();
        if (!$tag) {
            $this->error('标签不存在');
        }
        $tagData = is_object($tag) ? $tag->toArray() : (array)$tag;

        return $this->renderListPage([
            'fixedTagId' => (int)$tagData['id'],
            'guideNav'   => 'tag',
            'title'      => '标签：' . (string)$tagData['name'],
            'currentTag' => $tagData,
        ]);
    }

    public function modelIndex()
    {
        $rows = GuideModelEntity::where('status', 'normal')
            ->order('weigh desc,id asc')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;

        $countRows = Db::name('guide')
            ->where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->field('model_id,COUNT(*) AS cnt')
            ->group('model_id')
            ->select();
        $countMap = [];
        foreach ((array)$countRows as $item) {
            $modelId = (int)($item['model_id'] ?? 0);
            if ($modelId > 0) {
                $countMap[$modelId] = (int)($item['cnt'] ?? 0);
            }
        }
        foreach ($rows as &$row) {
            $row['guide_count'] = (int)($countMap[(int)$row['id']] ?? 0);
        }
        unset($row);

        $this->assign('models', $rows);
        $this->assign('guideNav', 'model');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-list.css',
        ]);
        $this->assign('title', '攻略模型');

        return $this->view->fetch('guide/model_index');
    }

    public function model($key = '')
    {
        $key = trim((string)$key);
        if ($key === '') {
            $key = trim((string)$this->request->param('key', ''));
        }
        if ($key === '') {
            $this->error('模型不存在');
        }

        $model = GuideModelEntity::where('key', $key)->where('status', 'normal')->find();
        if (!$model) {
            $this->error('模型不存在');
        }
        $modelData = is_object($model) ? $model->toArray() : (array)$model;

        return $this->renderListPage([
            'fixedModelId' => (int)$modelData['id'],
            'guideNav'     => 'model',
            'title'        => '模型：' . (string)$modelData['name'],
            'currentModel' => $modelData,
        ]);
    }

    public function categoryIndex()
    {
        $context = $this->getGuideCategoryContext();
        $countRows = Db::name('guide')
            ->where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->field('category_id,COUNT(*) AS cnt')
            ->group('category_id')
            ->select();

        $directCountMap = [];
        foreach ((array)$countRows as $item) {
            $cid = (int)($item['category_id'] ?? 0);
            if ($cid > 0) {
                $directCountMap[$cid] = (int)($item['cnt'] ?? 0);
            }
        }

        $groups = [];
        $rootIds = $context['childrenMap'][0] ?? [];
        if (empty($rootIds)) {
            $rootIds = array_keys($context['byId']);
        }

        foreach ($rootIds as $rootId) {
            $rootId = (int)$rootId;
            if ($rootId < 1 || empty($context['byId'][$rootId])) {
                continue;
            }

            $root = $context['byId'][$rootId];
            $descendantIds = $this->collectCategoryDescendantIds($rootId, $context['childrenMap'], true);
            $totalCount = 0;
            foreach ($descendantIds as $descendantId) {
                $totalCount += (int)($directCountMap[(int)$descendantId] ?? 0);
            }
            $root['guide_count'] = $totalCount;

            $subs = [];
            foreach (($context['childrenMap'][$rootId] ?? []) as $subId) {
                $subId = (int)$subId;
                if ($subId < 1 || empty($context['byId'][$subId])) {
                    continue;
                }
                $sub = $context['byId'][$subId];
                $sub['guide_count'] = (int)($directCountMap[$subId] ?? 0);
                $subs[] = $sub;
            }
            $root['subs'] = $subs;
            $groups[] = $root;
        }

        $this->assign('categoryGroups', $groups);
        $this->assignGuideSubnavData(0, true);
        $this->assign('guideNav', 'category');
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-list.css',
        ]);
        $this->assign('title', '攻略分类');

        return $this->view->fetch('guide/category_index');
    }

    public function category($slug = '')
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            $slug = trim((string)$this->request->param('slug', ''));
        }
        if ($slug === '') {
            $this->error('分类不存在');
        }

        $context = $this->getGuideCategoryContext();
        $slugKey = strtolower($slug);
        $matchedIds = $context['slugToIds'][$slugKey] ?? [];
        if (count($matchedIds) !== 1) {
            $this->error('分类不存在');
        }

        $categoryId = (int)reset($matchedIds);
        $currentCategory = $context['byId'][$categoryId] ?? [];
        if (empty($currentCategory)) {
            $this->error('分类不存在');
        }
        if (empty($currentCategory['slug_usable'])) {
            $this->error('分类链接不可用，请使用ID链接');
        }
        $guideCurrentTopCategoryId = $this->resolveGuideTopCategoryId($categoryId, $context);
        $this->assignGuideSubnavData($guideCurrentTopCategoryId, false);

        return $this->renderListPage([
            'fixedCategoryId' => $categoryId,
            'guideNav'        => 'category',
            'title'           => '分类：' . (string)$currentCategory['name'],
            'currentCategory' => $currentCategory,
        ]);
    }

    public function categoryById($id = 0)
    {
        $id = (int)$id;
        if ($id < 1) {
            $id = (int)$this->request->param('id', 0);
        }
        if ($id < 1) {
            $this->error('分类不存在');
        }

        $context = $this->getGuideCategoryContext();
        $currentCategory = $context['byId'][$id] ?? [];
        if (empty($currentCategory)) {
            $this->error('分类不存在');
        }
        $guideCurrentTopCategoryId = $this->resolveGuideTopCategoryId($id, $context);
        $this->assignGuideSubnavData($guideCurrentTopCategoryId, false);

        return $this->renderListPage([
            'fixedCategoryId' => $id,
            'guideNav'        => 'category',
            'title'           => '分类：' . (string)$currentCategory['name'],
            'currentCategory' => $currentCategory,
        ]);
    }

    protected function renderListPage(array $options = [])
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $categoryId = (int)$this->request->param('category_id', 0);
        $modelId = (int)$this->request->param('model_id', 0);
        $tagId = (int)$this->request->param('tag_id', 0);

        if (isset($options['fixedModelId'])) {
            $modelId = (int)$options['fixedModelId'];
        }
        if (isset($options['fixedTagId'])) {
            $tagId = (int)$options['fixedTagId'];
        }
        if (isset($options['fixedCategoryId'])) {
            $categoryId = (int)$options['fixedCategoryId'];
        }

        $categoryContext = $this->getGuideCategoryContext();
        $categoryFilterIds = [];
        if ($categoryId > 0 && !empty($categoryContext['byId'][$categoryId])) {
            $categoryFilterIds = $this->collectCategoryDescendantIds($categoryId, $categoryContext['childrenMap'], true);
        }

        $query = $this->baseGuideQuery();
        $this->applyGuideFilters($query, $keyword, $categoryId, $modelId, $tagId, $categoryFilterIds);
        $queryParams = $this->request->get();
        $queryParams['keyword'] = $keyword;
        $queryParams['category_id'] = $categoryId;
        $queryParams['model_id'] = $modelId;
        $queryParams['tag_id'] = $tagId;

        $list = $query->order('is_top desc,publish_time desc,id desc')
            ->paginate(15, false, ['query' => $queryParams]);
        $rows = $list ? $list->items() : [];
        $rows = is_array($rows) ? array_map(function ($row) {
            return is_array($row) ? $row : $row->toArray();
        }, $rows) : [];

        $topQuery = $this->baseGuideQuery()->where('is_top', 1);
        $this->applyGuideFilters($topQuery, $keyword, $categoryId, $modelId, $tagId, $categoryFilterIds);
        $topRows = $topQuery->order('publish_time desc,id desc')->limit(10)->select();
        $topRows = is_object($topRows) ? $topRows->toArray() : (array)$topRows;

        $currentTag = [];
        if (!empty($options['currentTag']) && is_array($options['currentTag'])) {
            $currentTag = $options['currentTag'];
        } elseif ($tagId > 0) {
            $tmpTag = GuideTag::where('id', $tagId)->find();
            $currentTag = $tmpTag ? $tmpTag->toArray() : [];
        }

        $currentModel = [];
        if (!empty($options['currentModel']) && is_array($options['currentModel'])) {
            $currentModel = $options['currentModel'];
        } elseif ($modelId > 0) {
            $tmpModel = GuideModelEntity::where('id', $modelId)->find();
            $currentModel = $tmpModel ? $tmpModel->toArray() : [];
        }

        $currentCategory = [];
        if (!empty($options['currentCategory']) && is_array($options['currentCategory'])) {
            $currentCategory = $options['currentCategory'];
        } elseif ($categoryId > 0 && !empty($categoryContext['byId'][$categoryId])) {
            $currentCategory = $categoryContext['byId'][$categoryId];
        }
        $guideCurrentTopCategoryId = $this->resolveGuideTopCategoryId($categoryId, $categoryContext);

        $categoryList = array_values($categoryContext['byId']);

        $modelList = GuideModelEntity::where('status', 'normal')->order('weigh desc,id asc')->select();
        $modelList = is_object($modelList) ? $modelList->toArray() : (array)$modelList;

        $tagList = GuideTag::where('status', 'normal')->order('use_count desc,id desc')->limit(50)->select();
        $tagList = is_object($tagList) ? $tagList->toArray() : (array)$tagList;

        $this->assign('list', $this->normalizeGuideRows($rows));
        $this->assign('topGuides', $this->normalizeGuideRows($topRows));
        $this->assign('pager', $list ? $list->render() : '');
        $this->assign('keyword', $keyword);
        $this->assign('categoryId', $categoryId);
        $this->assign('modelId', $modelId);
        $this->assign('tagId', $tagId);
        $this->assign('categoryList', $categoryList);
        $this->assign('modelList', $modelList);
        $this->assign('tagList', $tagList);
        $this->assign('currentTag', $currentTag);
        $this->assign('currentModel', $currentModel);
        $this->assign('currentCategory', $currentCategory);
        $this->assignGuideSubnavData($guideCurrentTopCategoryId, false);
        $this->assign('guideNav', (string)($options['guideNav'] ?? 'list'));
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/guide-home.css',
            $this->getCdnUrl() . '/assets/frontend/css/guide-list.css',
        ]);
        $this->assign('title', (string)($options['title'] ?? '攻略列表'));

        return $this->view->fetch('guide/list');
    }

    protected function baseGuideQuery()
    {
        return GuideEntity::where('status', 'published')
            ->where('publish_time', '<=', $this->now);
    }

    protected function applyGuideFilters($query, $keyword, $categoryId, $modelId, $tagId, array $categoryFilterIds = [])
    {
        $keyword = trim((string)$keyword);
        $categoryId = (int)$categoryId;
        $modelId = (int)$modelId;
        $tagId = (int)$tagId;
        $categoryFilterIds = array_values(array_unique(array_filter(array_map('intval', $categoryFilterIds), function ($v) {
            return $v > 0;
        })));

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->where('title', 'like', "%{$keyword}%")
                    ->whereOr('summary', 'like', "%{$keyword}%")
                    ->whereOr('content', 'like', "%{$keyword}%");
            });
        }
        if (!empty($categoryFilterIds)) {
            $query->where('category_id', 'in', $categoryFilterIds);
        } elseif ($categoryId > 0) {
            $query->where('id', 0);
        }
        if ($modelId > 0) {
            $query->where('model_id', $modelId);
        }
        if ($tagId > 0) {
            $guideIds = GuideTagRel::where('tag_id', $tagId)->column('guide_id');
            $guideIds = array_values(array_unique(array_filter(array_map('intval', (array)$guideIds), function ($v) {
                return $v > 0;
            })));
            if (empty($guideIds)) {
                $query->where('id', 0);
            } else {
                $query->where('id', 'in', $guideIds);
            }
        }
    }

    protected function getGuideCategoryContext()
    {
        if ($this->guideCategoryContext !== null) {
            return $this->guideCategoryContext;
        }

        $rows = Db::name('guide_category')
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $byId = [];
        $childrenMap = [];
        $slugToIds = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (array)$row;
            $id = (int)($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $pid = (int)($row['pid'] ?? 0);
            $slug = trim((string)($row['slug'] ?? ''));
            $slugKey = strtolower($slug);

            $row['pid'] = $pid;
            $row['slug'] = $slug;
            $row['slug_usable'] = false;
            $byId[$id] = $row;
            $childrenMap[$pid][] = $id;
            if ($slugKey !== '') {
                $slugToIds[$slugKey][] = $id;
            }
        }

        foreach ($byId as $id => &$row) {
            $slugKey = strtolower(trim((string)($row['slug'] ?? '')));
            $row['slug_usable'] = ($slugKey !== '' && isset($slugToIds[$slugKey]) && count($slugToIds[$slugKey]) === 1) ? 1 : 0;
        }
        unset($row);

        $this->guideCategoryContext = [
            'byId'       => $byId,
            'childrenMap'=> $childrenMap,
            'slugToIds'  => $slugToIds,
        ];

        return $this->guideCategoryContext;
    }

    protected function assignGuideSubnavData($currentTopCategoryId = 0, $moreActive = false)
    {
        $context = $this->getGuideCategoryContext();
        $this->assign('guideSubnavCategories', $this->buildGuideTopCategoryNav($context));
        $this->assign('guideCurrentTopCategoryId', (int)$currentTopCategoryId);
        $this->assign('guideSubnavMoreActive', $moreActive ? 1 : 0);
    }

    protected function buildGuideTopCategoryNav(array $context)
    {
        $items = [];
        $byId = (array)($context['byId'] ?? []);
        $rootIds = (array)($context['childrenMap'][0] ?? []);

        if (!empty($rootIds)) {
            foreach ($rootIds as $id) {
                $id = (int)$id;
                if ($id > 0 && !empty($byId[$id])) {
                    $items[] = $byId[$id];
                }
            }
            return $items;
        }

        foreach ($byId as $row) {
            if ((int)($row['pid'] ?? 0) === 0) {
                $items[] = $row;
            }
        }

        return $items;
    }

    protected function resolveGuideTopCategoryId($categoryId, array $context = [])
    {
        $categoryId = (int)$categoryId;
        if ($categoryId < 1) {
            return 0;
        }

        if (empty($context)) {
            $context = $this->getGuideCategoryContext();
        }
        $byId = (array)($context['byId'] ?? []);
        if (empty($byId[$categoryId])) {
            return 0;
        }

        $visited = [];
        $current = $categoryId;
        while ($current > 0 && !isset($visited[$current]) && !empty($byId[$current])) {
            $visited[$current] = true;
            $pid = (int)($byId[$current]['pid'] ?? 0);
            if ($pid < 1 || empty($byId[$pid])) {
                return $current;
            }
            $current = $pid;
        }

        return $categoryId;
    }

    protected function buildGuideCategoryPath($categoryId, array $context = [])
    {
        $categoryId = (int)$categoryId;
        if ($categoryId < 1) {
            return [];
        }

        if (empty($context)) {
            $context = $this->getGuideCategoryContext();
        }
        $byId = (array)($context['byId'] ?? []);
        if (empty($byId[$categoryId])) {
            return [];
        }

        $chain = [];
        $visited = [];
        $current = $categoryId;
        while ($current > 0 && !isset($visited[$current]) && !empty($byId[$current])) {
            $visited[$current] = true;
            $row = (array)$byId[$current];
            $chain[] = [
                'id'   => (int)($row['id'] ?? $current),
                'name' => trim((string)($row['name'] ?? '')),
                'url'  => guide_category_url($row),
            ];
            $current = (int)($row['pid'] ?? 0);
        }

        return array_reverse($chain);
    }

    protected function collectCategoryDescendantIds($categoryId, array $childrenMap, $includeSelf = true)
    {
        $categoryId = (int)$categoryId;
        if ($categoryId < 1) {
            return [];
        }

        $ids = [];
        $stack = [$categoryId];
        while (!empty($stack)) {
            $current = (int)array_pop($stack);
            if ($current < 1 || isset($ids[$current])) {
                continue;
            }
            $ids[$current] = true;
            foreach (($childrenMap[$current] ?? []) as $childId) {
                $childId = (int)$childId;
                if ($childId > 0 && !isset($ids[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        if (!$includeSelf) {
            unset($ids[$categoryId]);
        }

        return array_values(array_map('intval', array_keys($ids)));
    }

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

    protected function isValidGuideJumpUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return false;
        }
        return preg_match('/^https?:\/\//i', $url) || strpos($url, '/') === 0;
    }

    protected function normalizeGuideRows(array $rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (array)$row;
            $result[] = $this->normalizeGuideRow($row);
        }
        return $result;
    }

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

    protected function getCdnUrl()
    {
        return \think\Config::get('site.cdnurl') ?: '';
    }
}
