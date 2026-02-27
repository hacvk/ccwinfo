<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\ExchangeGoods as ExchangeGoodsModel;

/**
 * 积分商品管理
 *
 * @icon fa fa-gift
 */
class Exchangegoods extends Backend
{
    /**
     * @var ExchangeGoodsModel
     */
    protected $model = null;
    protected $searchFields = 'id,title';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new ExchangeGoodsModel();
        $this->view->assign('goodsTypeList', $this->model->getGoodsTypeList());
        $this->view->assign('virtualModeList', $this->model->getVirtualModeList());
        $this->view->assign('virtualSourceList', $this->model->getVirtualSourceList());
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->assignconfig('goodsTypeList', $this->model->getGoodsTypeList());
        $this->assignconfig('virtualModeList', $this->model->getVirtualModeList());
        $this->assignconfig('virtualSourceList', $this->model->getVirtualSourceList());
        $this->assignconfig('statusList', $this->model->getStatusList());
    }
}

