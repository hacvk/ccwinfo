<?php

namespace app\admin\controller\promotion;

use app\common\controller\Backend;
use app\common\library\SentryService;
use app\common\model\PromotionItem;
use app\common\model\PromotionSlot;
use think\Db;

/**
 * 友情链接管理（基于推广内容）
 *
 * @icon fa fa-link
 */
class Friendlink extends Backend
{
    /**
     * @var \app\common\model\PromotionItem
     */
    protected $model = null;

    protected $searchFields = 'id,title,link_url,contact_name,company_name';

    /**
     * 友情链接专用展示位编码
     */
    protected $slotCode = 'friend_links';

    /**
     * 友链展示位ID
     */
    protected $slotId = 0;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new PromotionItem;

        // 获取友链展示位 - 加调试日志
        SentryService::debug('Friendlink._initialize 开始', [
            'slotCode' => $this->slotCode,
        ]);

        // 用 Db 直接查询，避免模型问题
        $slot = Db::name('promotion_slot')->where('code', $this->slotCode)->find();
        
        SentryService::debug('Friendlink._initialize 查询结果', [
            'slot' => $slot,
            'slot_type' => gettype($slot),
        ]);

        $this->slotId = $slot ? (int)$slot['id'] : 0;
        
        SentryService::debug('Friendlink._initialize 完成', [
            'slotId' => $this->slotId,
        ]);
        
        $this->view->assign('slotId', $this->slotId);
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }

        $query = $this->model->where('slot_id', $this->slotId);

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        if ($where) {
            $query->where($where);
        }

        $list = $query->order($sort ?: 'id', $order ?: 'desc')->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 添加
     */
    public function add()
    {
        SentryService::debug('Friendlink.add 开始', [
            'slotId' => $this->slotId,
        ]);
        
        if ($this->slotId <= 0) {
            SentryService::error('Friendlink.add 展示位未找到', [
                'slotId' => $this->slotId,
                'slotCode' => $this->slotCode,
            ]);
            $this->error('请先在"展示位管理"中创建编码为 friend_links 的展示位');
        }
    
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            $params['slot_id'] = $this->slotId;
            $params['status'] = $params['status'] ?? 'online';
            $params['page_scope'] = 'all';
            $params['review_status'] = 'approved';
            $params['review_time'] = time();
            $params['review_admin_id'] = session('admin.id');

            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->allowField(true)->save($params);
                Db::commit();
            } catch (\think\exception\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result === false) {
                $this->error(__('No rows were inserted'));
            }
            $this->success();
        }

        return $this->view->fetch();
    }

    /**
     * 审核
     */
    public function review($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        $adminId = session('admin.id');
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            
            $params = $this->preExcludeFields($params);
            
            // 审核状态验证
            if (!in_array($params['review_status'], ['approved', 'rejected'])) {
                $this->error('无效的审核状态');
            }
            
            // 如果审核通过，同时设置为在线状态
            if ($params['review_status'] === 'approved') {
                $params['status'] = 'online';
                $params['review_time'] = time();
                $params['review_admin_id'] = $adminId;
            }
            
            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                Db::commit();
            } catch (\think\exception\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result === false) {
                $this->error(__('Update failed'));
            }
            
            // 记录审核日志
            $reviewAction = $params['review_status'] === 'approved' ? '通过' : '拒绝';
            $message = "友情链接审核{$reviewAction}：" . $row['title'];
            SentryService::info('Friendlink.review', [
                'id' => $row['id'],
                'title' => $row['title'],
                'review_status' => $params['review_status'],
                'admin_id' => $adminId,
                'message' => $message
            ]);
            
            $this->success();
        }
        
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 批量审核
     */
    public function multi($ids = null)
    {
        $params = $this->request->post("params");
        if (!is_numeric($ids) && !is_array($ids)) {
            $this->error(__("Parameter %s can not be empty", "ids"));
        }
        
        $ids = array_unique(is_array($ids) ? $ids : [$ids]);
        $ids = array_filter($ids);
        
        if (!count($ids)) {
            $this->error(__('Selected item not found'));
        }
        
        if ($params && is_array($params)) {
            $values = [];
            foreach ($params as $k => $v) {
                if (strpos($k, "row[") === 0) {
                    $values[substr($k, 4, -1)] = $v;
                }
            }
            if (count($values) && method_exists($this->model, 'allowField')) {
                $this->model->allowField(true)->save($values, ["id" => ['in', $ids]]);
                $this->success();
            } else {
                $this->error(__('Parameter error'));
            }
        } else {
            $this->error(__('Parameter error'));
        }
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            
            // 管理员编辑时，如果当前审核状态不是已通过，设置为已通过
            if ($row['review_status'] !== 'approved') {
                $params['review_status'] = 'approved';
                $params['review_time'] = time();
                $params['review_admin_id'] = session('admin.id');
            }

            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                Db::commit();
            } catch (\think\exception\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result === false) {
                $this->error(__('No rows were updated'));
            }
            $this->success();
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }
}
