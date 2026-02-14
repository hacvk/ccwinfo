<?php

namespace app\index\controller;

use addons\signin\library\Service;
use addons\signin\library\SigninException;
use app\common\controller\Frontend;
use fast\Date;
use think\Db;
use think\Exception;
use think\exception\PDOException;

class Signin extends Frontend
{
    protected $layout = 'default';
    protected $noNeedRight = ["*"];

    /**
     * 签到首页
     * @return string
     */
    public function index()
    {
        $config = get_addon_config('signin');
        $signdata = $config['signinscore'];
        $date = $this->request->request('date', date("Y-m-d"), "trim");
        $time = strtotime($date);

        $lastdata = \addons\signin\model\Signin::where('user_id', $this->auth->id)->order('createtime', 'desc')->find();
        $successions = $lastdata && $lastdata['createtime'] > Date::unixtime('day', -1) ? $lastdata['successions'] : 0;
        $signin = \addons\signin\model\Signin::where('user_id', $this->auth->id)->whereTime('createtime', 'today')->find();

        $calendar = new \addons\signin\library\Calendar();
        $list = \addons\signin\model\Signin::where('user_id', $this->auth->id)
            ->field('id,createtime')
            ->whereTime('createtime', 'between', [date("Y-m-1", $time), date("Y-m-1", strtotime("+1 month", $time))])
            ->select();
        foreach ($list as $index => $item) {
            $calendar->addEvent(date("Y-m-d", $item->createtime), date("Y-m-d", $item->createtime), "", false, "signed");
        }
        $this->assignconfig('fillupscore', $config['fillupscore']);
        $this->assignconfig('isfillup', $config['isfillup']);
        $this->view->assign('calendar', $calendar);
        $this->view->assign('date', $date);
        $this->view->assign('successions', $successions);
        $successions++;
        $score = $signdata['s' . $successions] ?? $signdata['sn'];
        $this->view->assign('signin', $signin);
        $this->view->assign('score', $score);
        $this->view->assign('signinscore', $config['signinscore']);
        $this->view->assign('title', "每日签到");
        return $this->view->fetch();
    }

    /**
     * 立即签到
     */
    public function dosign()
    {
        if (!$this->request->isPost()) {
            $this->error("请求错误");
        }

        $result = Service::dosign($this->auth->id);
        if (isset($result['errmsg'])) {
            $this->error($result['errmsg']);
        } else {
            $this->success('签到成功!连续签到' . $result['successions'] . '天!获得' . $result['score'] . '积分');
        }
    }

    /**
     * 签到补签
     */
    public function fillup()
    {
        if (!$this->request->isPost()) {
            $this->error("请求错误");
        }
        $date = $this->request->request('date');
        $result = Service::fillup($date, $this->auth->id);
        if (isset($result['errmsg'])) {
            $this->error($result['errmsg']);
        } else {
            $this->success('补签成功');
        }
    }

    /**
     * 排行榜
     */
    public function rank()
    {
        list($ranklist, $ranking, $successions) = Service::getRankInfo();
        $this->success("", "", ['ranklist' => collection($ranklist)->toArray(), 'ranking' => $ranking, 'successions' => $successions]);
    }

}
