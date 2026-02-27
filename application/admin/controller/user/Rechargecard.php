<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\RechargeCard as RechargeCardModel;
use app\common\service\RechargeCardService;
use think\Exception;
use think\Response;

/**
 * 充值卡管理
 *
 * @icon fa fa-ticket
 */
class Rechargecard extends Backend
{
    /**
     * @var RechargeCardModel
     */
    protected $model = null;
    protected $searchFields = 'id,card_no,batch_no,reserve_orderid,used_orderid';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new RechargeCardModel();
        $this->view->assign('cardTypeList', $this->model->getCardTypeList());
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->assignconfig('cardTypeList', $this->model->getCardTypeList());
        $this->assignconfig('statusList', $this->model->getStatusList());
    }

    public function generate()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }

        $cardType = trim((string)$this->request->post('card_type', 'recharge'));
        $count = (int)$this->request->post('count', 0);
        $faceAmount = (float)$this->request->post('face_amount', 0);
        $bonusRate = (float)$this->request->post('bonus_rate', 0);
        $minRecharge = (float)$this->request->post('min_recharge', 0);
        $maxBonus = (float)$this->request->post('max_bonus', 0);
        $expiretimeRaw = trim((string)$this->request->post('expiretime', ''));
        $batchNo = trim((string)$this->request->post('batch_no', ''));

        $options = [
            'face_amount'  => $faceAmount,
            'bonus_rate'   => $bonusRate,
            'min_recharge' => $minRecharge,
            'max_bonus'    => $maxBonus,
            'batch_no'     => $batchNo,
        ];
        if ($expiretimeRaw !== '') {
            $expiretime = is_numeric($expiretimeRaw) ? (int)$expiretimeRaw : strtotime($expiretimeRaw);
            if (!$expiretime) {
                $this->error(__('Invalid expiretime'));
            }
            $options['expiretime'] = $expiretime;
        }

        try {
            $result = RechargeCardService::batchGenerate($cardType, $count, $options);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->error(__('Generate failed'));
        }

        $this->success(__('Generate success') . ' #' . $result['batch_no'] . ' x ' . $result['count']);
    }

    public function export()
    {
        $ids = trim((string)$this->request->request('ids', ''));
        $query = $this->model->order('id desc');
        if ($ids !== '') {
            $query->where('id', 'in', explode(',', $ids));
        }
        $rows = $query->select();

        $filename = 'recharge_cards_' . date('YmdHis') . '.csv';
        $header = [
            'ID',
            'CardType',
            'CardNo',
            'CardSecret',
            'FaceAmount',
            'BonusRate',
            'MinRecharge',
            'MaxBonus',
            'Status',
            'BatchNo',
            'ExpireTime',
            'OwnerUserId',
            'ReserveOrderId',
            'UsedUserId',
            'UsedOrderId',
            'UsedTime',
            'CreateTime',
        ];

        $lines = [implode(',', $header)];
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $this->csvValue($row['id']),
                $this->csvValue($row['card_type']),
                $this->csvValue($row['card_no']),
                $this->csvValue($row['card_secret']),
                $this->csvValue($row['face_amount']),
                $this->csvValue($row['bonus_rate']),
                $this->csvValue($row['min_recharge']),
                $this->csvValue($row['max_bonus']),
                $this->csvValue($row['status']),
                $this->csvValue($row['batch_no']),
                $this->csvValue($row['expiretime'] ? date('Y-m-d H:i:s', (int)$row['expiretime']) : ''),
                $this->csvValue($row['owner_user_id']),
                $this->csvValue($row['reserve_orderid']),
                $this->csvValue($row['used_user_id']),
                $this->csvValue($row['used_orderid']),
                $this->csvValue($row['used_time'] ? date('Y-m-d H:i:s', (int)$row['used_time']) : ''),
                $this->csvValue($row['createtime'] ? date('Y-m-d H:i:s', (int)$row['createtime']) : ''),
            ]);
        }

        $content = "\xEF\xBB\xBF" . implode("\r\n", $lines);
        return Response::create($content, 'csv', 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function csvValue($value)
    {
        $value = str_replace('"', '""', (string)$value);
        return '"' . $value . '"';
    }
}
