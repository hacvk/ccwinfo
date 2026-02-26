<?php

namespace app\admin\controller\promotion;

use app\common\controller\Backend;
use app\common\model\PromotionOrder as PromotionOrderModel;
use app\common\model\PromotionItem;
use app\common\model\PromotionSlot;
use app\common\library\SentryService;
use think\Db;

/**
 * 推广订单管理
 *
 * @icon fa fa-file-text-o
 */
class Order extends Backend
{
    /**
     * @var \app\common\model\PromotionOrder
     */
    protected $model = null;

    protected $relationSearch = true;
    protected $searchFields = 'id,contact_name,contact_mobile,company_name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new PromotionOrderModel;

        $slotList = PromotionSlot::where('status', 'normal')->order('id asc')->column('name', 'id');
        $this->view->assign('slotList', $slotList);
        $this->view->assign('orderStatusList', $this->model->getOrderStatusList());
        $this->view->assign('payStatusList', $this->model->getPayStatusList());
        $this->assignconfig('orderStatusList', $this->model->getOrderStatusList());
        $this->assignconfig('payStatusList', $this->model->getPayStatusList());
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
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with(['item'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $row) {
            $row->getRelation('item');
        }

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 标记线下已支付
     */
    public function markPaid($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if (in_array((string)$row['pay_status'], ['offline_paid', 'paid'])) {
            $this->error('该订单已支付，无需重复标记');
        }

        Db::startTrans();
        try {
            $row->pay_status = 'offline_paid';
            $row->pay_type = 'offline';
            $row->pay_time = time();
            $row->paid_amount = (float)$row->amount;
            $row->save();

            SentryService::info('Order.markPaid 完成', [
                'order_id' => $row['id'],
                'item_id' => $row['item_id'],
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            SentryService::error('Order.markPaid 失败', [
                'order_id' => $row['id'],
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
        }

        $this->success('已标记为线下已支付');
    }
}
