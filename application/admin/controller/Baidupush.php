<?php

namespace app\admin\controller;

use addons\baidupush\library\Push;
use app\common\controller\Backend;

/**
 * 百度推送管理
 *
 * @icon fa fa-circle-o
 */
class Baidupush extends Backend
{

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        $config = get_addon_config('baidupush');
        $config['status'] = explode(',', $config['status']);
        $this->view->assign('addonConfig', $config);
        return $this->view->fetch();
    }

    /**
     * 快速收录提交
     */
    public function daily()
    {
        $action = $this->request->post("action");
        $urls = $this->request->post("urls");
        $urls = explode("\n", str_replace("\r", "", $urls));
        $urls = array_unique(array_filter($urls));
        if (!$urls) {
            $this->error("URL列表不能为空");
        }
        $result = false;
        if ($action == 'urls') {
            $result = Push::init(['type' => 'daily'])->realtime($urls);
        } elseif ($action == 'del') {
            $result = Push::init(['type' => 'daily'])->delete($urls);
        }

        if ($result) {
            $data = Push::init()->getData();
            $this->success("推送成功", null, $data);
        } else {
            $this->error("推送失败：" . Push::init()->getError());
        }
    }

    /**
     * 普通收录
     */
    public function normal()
    {
        $action = $this->request->post("action");
        $urls = $this->request->post("urls");
        $urls = explode("\n", str_replace("\r", "", $urls));
        $urls = array_unique(array_filter($urls));
        if (!$urls) {
            $this->error("URL列表不能为空");
        }
        $result = false;
        if ($action == 'urls') {
            $result = Push::init(['type' => 'normal'])->realtime($urls);
        } elseif ($action == 'del') {
            $result = Push::init(['type' => 'normal'])->delete($urls);
        }
        if ($result) {
            $data = Push::init()->getData();
            $this->success("推送成功", null, $data);
        } else {
            $this->error("推送失败：" . Push::init()->getError());
        }
    }
}
