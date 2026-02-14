<?php

namespace app\admin\controller\info;

use app\common\controller\Backend;
use app\common\model\Info;
use app\common\model\InfoReviewLog;
use think\Db;

/**
 * 分类信息-审核中心
 *
 * @icon fa fa-check-square-o
 */
class Review extends Backend
{
    /**
     * @var \app\common\model\Info
     */
    protected $model = null;
    protected $searchFields = 'id,title,contact_name,contact_phone';
    protected $relationSearch = true;
    protected $noNeedRight = ['approve', 'reject'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new Info;
    }

    /**
     * 审核中心列表（仅显示待审核）
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        
        // 强制筛选待审核状态
        $where[] = ['status', '=', 'pending'];
        
        // 排除管理员代录的（entry_admin_id=0 表示前台用户提交）
        $where[] = ['entry_admin_id', '=', 0];

        $list = $this->model
            ->with(['category', 'subcategory'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $row) {
            $row->getRelation('category');
            $row->getRelation('subcategory');
        }

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 通过审核（批量）
     */
    public function approve()
    {
        $ids = $this->request->post('ids');
        if (empty($ids)) {
            $this->error('请选择待审核的信息');
        }

        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('未获取到有效信息ID');
        }

        $adminId = (int)$this->auth->id;
        $adminName = (string)$this->auth->username;
        $batchNo = count($ids) > 1 ? sprintf('approve-%d-%s', $adminId, date('YmdHis')) : '';

        $result = Db::transaction(function () use ($ids, $adminId, $adminName, $batchNo) {
            // 锁表查询待审核记录
            $rows = $this->model
                ->where('id', 'in', $ids)
                ->where('status', 'pending')
                ->where('entry_admin_id', 0)
                ->lock(true)
                ->column('id');

            if (empty($rows)) {
                return ['affected' => 0, 'skipped' => count($ids)];
            }

            $processIds = array_values($rows);

            // 更新状态
            $this->model
                ->where('id', 'in', $processIds)
                ->update(['status' => 'published']);

            // 写入审核日志
            $logs = [];
            foreach ($processIds as $id) {
                $logs[] = [
                    'info_id'     => $id,
                    'action'      => 'approve',
                    'from_status' => 'pending',
                    'to_status'   => 'published',
                    'reason'      => '',
                    'admin_id'    => $adminId,
                    'admin_name'  => $adminName,
                    'batch_no'    => $batchNo,
                ];
            }
            if (!empty($logs)) {
                (new InfoReviewLog)->insertAll($logs);
            }

            return [
                'affected' => count($processIds),
                'skipped'  => count($ids) - count($processIds),
            ];
        });

        if ($result['affected'] < 1) {
            $this->error('没有可处理的待审核信息，可能已被其他审核员处理');
        }

        $this->success(sprintf(
            '审核完成：通过 %d 条，跳过 %d 条',
            $result['affected'],
            $result['skipped']
        ));
    }

    /**
     * 拒绝审核（需填写原因）
     */
    public function reject()
    {
        $ids = $this->request->post('ids');
        $reason = trim((string)$this->request->post('reason', ''));

        if (empty($ids)) {
            $this->error('请选择待审核的信息');
        }
        if (mb_strlen($reason) < 2 || mb_strlen($reason) > 200) {
            $this->error('拒绝原因需为 2-200 个字符');
        }

        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->error('未获取到有效信息ID');
        }

        $adminId = (int)$this->auth->id;
        $adminName = (string)$this->auth->username;
        $batchNo = count($ids) > 1 ? sprintf('reject-%d-%s', $adminId, date('YmdHis')) : '';

        $result = Db::transaction(function () use ($ids, $reason, $adminId, $adminName, $batchNo) {
            // 锁表查询待审核记录
            $rows = $this->model
                ->where('id', 'in', $ids)
                ->where('status', 'pending')
                ->where('entry_admin_id', 0)
                ->lock(true)
                ->column('id');

            if (empty($rows)) {
                return ['affected' => 0, 'skipped' => count($ids)];
            }

            $processIds = array_values($rows);

            // 更新状态
            $this->model
                ->where('id', 'in', $processIds)
                ->update(['status' => 'rejected']);

            // 写入审核日志
            $logs = [];
            foreach ($processIds as $id) {
                $logs[] = [
                    'info_id'     => $id,
                    'action'      => 'reject',
                    'from_status' => 'pending',
                    'to_status'   => 'rejected',
                    'reason'      => $reason,
                    'admin_id'    => $adminId,
                    'admin_name'  => $adminName,
                    'batch_no'    => $batchNo,
                ];
            }
            if (!empty($logs)) {
                (new InfoReviewLog)->insertAll($logs);
            }

            return [
                'affected' => count($processIds),
                'skipped'  => count($ids) - count($processIds),
            ];
        });

        if ($result['affected'] < 1) {
            $this->error('没有可处理的待审核信息，可能已被其他审核员处理');
        }

        $this->success(sprintf(
            '审核完成：拒绝 %d 条，跳过 %d 条',
            $result['affected'],
            $result['skipped']
        ));
    }
}
