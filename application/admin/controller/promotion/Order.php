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

        SentryService::info('Order.markPaid 开始', [
            'order_id' => $row['id'],
            'current_pay_status' => $row['pay_status'],
            'current_order_status' => $row['order_status'],
            'admin_id' => session('admin.id'),
            'admin_username' => session('admin.username')
        ]);

        if ($row['pay_status'] === 'offline_paid') {
            SentryService::warning('Order.markPaid 订单已标记为线下已支付', [
                'order_id' => $row['id']
            ]);
            $this->error('该订单已标记为线下已支付');
        }

        Db::startTrans();
        try {
            $row->pay_status = 'offline_paid';
            $row->order_status = $row->order_status === 'pending' ? 'approved' : $row->order_status;
            $row->save();

            SentryService::debug('Order.markPaid 订单状态已更新', [
                'order_id' => $row['id'],
                'new_pay_status' => $row['pay_status'],
                'new_order_status' => $row['order_status']
            ]);

            // 同步更新推广内容状态为待上线（由运营在内容管理中审核并上线）
            if ($row['item_id']) {
                $item = PromotionItem::get($row['item_id']);
                if ($item) {
                    if ($item['status'] === 'draft') {
                        $item->status = 'pending';
                    }
                    $item->save();
                    SentryService::debug('Order.markPaid 推广内容状态已更新', [
                        'item_id' => $item['id'],
                        'new_status' => $item['status']
                    ]);
                }
            }

            Db::commit();
            SentryService::info('Order.markPaid 完成', [
                'order_id' => $row['id'],
                'contact_name' => $row['contact_name'],
                'amount' => $row['amount']
            ]);
        } catch (\think\exception\PDOException $e) {
            Db::rollback();
            SentryService::error('Order.markPaid 数据库异常', [
                'order_id' => $row['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            Db::rollback();
            SentryService::error('Order.markPaid 系统异常', [
                'order_id' => $row['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error($e->getMessage());
        }

        $this->success('已标记为线下已支付');
    }
}
