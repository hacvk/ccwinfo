<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\SinglePage as SinglePageModel;

/**
 * 前台单页
 */
class Page extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = 'default';

    public function _initialize()
    {
        parent::_initialize();
        // 避免加载不存在的 frontend/page.js
        $this->assignconfig('jsname', '');
    }

    /**
     * 单页详情
     */
    public function detail($slug = '')
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            $slug = trim((string)$this->request->param('slug', ''));
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug)) {
            $this->error('页面不存在');
        }

        $page = SinglePageModel::where('slug', $slug)
            ->where('status', 'published')
            ->where('is_group', 0)
            ->find();
        if (!$page) {
            $this->error('页面不存在');
        }

        $externalUrl = trim((string)$page['external_url']);
        if ($externalUrl !== '' && filter_var($externalUrl, FILTER_VALIDATE_URL) !== false) {
            return $this->redirect($externalUrl, 302);
        }

        SinglePageModel::where('id', (int)$page['id'])->setInc('views');
        $row = is_object($page) ? $page->toArray() : (array)$page;
        $row['views'] = (int)($row['views'] ?? 0) + 1;
        $row['title'] = trim((string)($row['title'] ?? ''));
        $row['cover'] = trim((string)($row['cover'] ?? ''));
        $row['content'] = (string)($row['content'] ?? '');
        $row['breadcrumb'] = $this->buildParentChain((int)($row['parent_id'] ?? 0));

        $this->assign('pageInfo', $row);
        $this->assign('page_css', [
            $this->getCdnUrl() . '/assets/frontend/css/page-detail.css',
        ]);
        $this->assign('title', $row['title'] !== '' ? $row['title'] : '页面详情');

        return $this->view->fetch('page/detail');
    }

    /**
     * 构建父级路径（仅分组页）
     *
     * @param int $parentId
     * @return array
     */
    protected function buildParentChain($parentId)
    {
        $result = [];
        $visited = [];
        $current = (int)$parentId;
        while ($current > 0 && !isset($visited[$current])) {
            $visited[$current] = true;
            $parent = SinglePageModel::where('id', $current)
                ->where('is_group', 1)
                ->field('id,parent_id,title')
                ->find();
            if (!$parent) {
                break;
            }
            $result[] = [
                'id' => (int)$parent['id'],
                'title' => (string)$parent['title'],
            ];
            $current = (int)$parent['parent_id'];
        }
        return array_reverse($result);
    }

    protected function getCdnUrl()
    {
        return \think\Config::get('site.cdnurl') ?: '';
    }
}
