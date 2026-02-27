<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\ExchangeOrder as ExchangeOrderModel;
use app\common\service\ExchangeService;
use think\Exception;

/**
 * 积分兑换订单
 *
 * @icon fa fa-list-alt
 */
class Exchangeorder extends Backend
{
    /**
     * @var ExchangeOrderModel
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $searchFields = 'id,order_no,user_id,receiver_name,receiver_mobile';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new ExchangeOrderModel();
        $this->view->assign('orderStatusList', $this->model->getOrderStatusList());
        $this->view->assign('pointsStatusList', $this->model->getPointsStatusList());
        $this->assignconfig('orderStatusList', $this->model->getOrderStatusList());
        $this->assignconfig('pointsStatusList', $this->model->getPointsStatusList());
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with(['goods'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        foreach ($list as $row) {
            $row->getRelation('goods');
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    public function deliver()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->parseIds();
        $deliveryContent = trim((string)$this->request->post('delivery_content', ''));
        $adminRemark = trim((string)$this->request->post('admin_remark', ''));
        if (empty($ids) || $deliveryContent === '') {
            $this->error(__('Invalid parameters'));
        }
        [$ok, $fail] = $this->batchAction($ids, function ($id) use ($deliveryContent, $adminRemark) {
            ExchangeService::adminDeliver($id, $deliveryContent, $adminRemark);
        });
        if ($ok < 1) {
            $this->error(__('Deliver failed') . ': ' . implode('; ', $fail));
        }
        $msg = __('Deliver success') . " {$ok}";
        if (!empty($fail)) {
            $msg .= '，' . __('Failed') . ' ' . count($fail) . ': ' . implode('; ', $fail);
        }
        $this->success($msg);
    }

    public function ship()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->parseIds();
        $deliveryContent = trim((string)$this->request->post('delivery_content', ''));
        $adminRemark = trim((string)$this->request->post('admin_remark', ''));
        if (empty($ids) || $deliveryContent === '') {
            $this->error(__('Invalid parameters'));
        }
        [$ok, $fail] = $this->batchAction($ids, function ($id) use ($deliveryContent, $adminRemark) {
            ExchangeService::adminShip($id, $deliveryContent, $adminRemark);
        });
        if ($ok < 1) {
            $this->error(__('Ship failed') . ': ' . implode('; ', $fail));
        }
        $msg = __('Ship success') . " {$ok}";
        if (!empty($fail)) {
            $msg .= '，' . __('Failed') . ' ' . count($fail) . ': ' . implode('; ', $fail);
        }
        $this->success($msg);
    }

    public function reject()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->parseIds();
        $reason = trim((string)$this->request->post('reason', ''));
        $adminRemark = trim((string)$this->request->post('admin_remark', ''));
        if (empty($ids) || $reason === '') {
            $this->error(__('Invalid parameters'));
        }
        [$ok, $fail] = $this->batchAction($ids, function ($id) use ($reason, $adminRemark) {
            ExchangeService::adminReject($id, $reason, $adminRemark);
        });
        if ($ok < 1) {
            $this->error(__('Reject failed') . ': ' . implode('; ', $fail));
        }
        $msg = __('Reject success') . " {$ok}";
        if (!empty($fail)) {
            $msg .= '，' . __('Failed') . ' ' . count($fail) . ': ' . implode('; ', $fail);
        }
        $this->success($msg);
    }

    protected function parseIds()
    {
        $ids = $this->request->post('ids/a', []);
        if (empty($ids)) {
            $idsText = trim((string)$this->request->post('ids', ''));
            if ($idsText !== '') {
                $ids = explode(',', $idsText);
            }
        }
        $ids = array_filter(array_map('intval', (array)$ids));
        return array_values(array_unique($ids));
    }

    protected function batchAction(array $ids, callable $callback)
    {
        $ok = 0;
        $fail = [];
        foreach ($ids as $id) {
            try {
                call_user_func($callback, $id);
                $ok++;
            } catch (Exception $e) {
                $fail[] = '#' . $id . ':' . $e->getMessage();
            } catch (\Throwable $e) {
                $fail[] = '#' . $id . ':' . __('Operation failed');
            }
        }
        return [$ok, $fail];
    }
}

