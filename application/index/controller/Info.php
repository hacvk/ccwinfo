<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\Info as InfoModel;
use app\common\model\InfoCategory;
use app\common\model\InfoField;
use app\common\model\InfoFieldValue;
use think\Db;
use think\Session;

/**
 * 分类信息前台控制器
 */
class Info extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = 'default';

    /**
     * 游客发布凭证 Session 键名
     */
    private $guestPublishSessionKey = 'frontend_guest_publish_ticket';

    /**
     * 游客发布凭证有效期（秒）
     */
    private $guestPublishTtl = 600;

    /**
     * 游客匿名编辑凭证 Session 键名
     */
    private $guestEditSessionKey = 'frontend_guest_edit_ticket';

    /**
     * 游客匿名编辑凭证有效期（秒）
     */
    private $guestEditTtl = 1800;

    /**
     * 详情页手机号查看频控 Session 键名
     */
    private $contactRevealSessionKey = 'frontend_contact_reveal_limit';

    /**
     * 详情页手机号查看频控秒数
     */
    private $contactRevealCooldown = 30;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 信息列表
     */
    public function lists()
    {
        $keyword = trim($this->request->get('keyword', ''));
        $categoryId = (int)$this->request->get('category_id', 0);
        $subCategoryId = (int)$this->request->get('sub_category_id', 0);

        $query = InfoModel::where('status', 'published');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->where('title', 'like', "%{$keyword}%")
                    ->whereOr('content', 'like', "%{$keyword}%");
            });
        }
        $this->applyListCategoryFilter($query, $categoryId, $subCategoryId);

        $list = $query->order('is_top desc, createtime desc')
            ->paginate(12, false, ['query' => $this->request->get()]);

        $items = [];
        foreach ($list->items() as $row) {
            $item = is_array($row) ? $row : $row->toArray();
            $item['images_arr'] = $this->parseImages($item['images'] ?? '');
            $item['thumb'] = !empty($item['images_arr'][0]) ? $item['images_arr'][0] : '';
            $item['summary'] = mb_substr(strip_tags((string)($item['content'] ?? '')), 0, 60);
            $item['created_display'] = $this->formatCreatedDisplay($item['createtime'] ?? 0);
            $item['price_display'] = $this->formatPriceDisplay($item['price'] ?? null, (string)($item['price_unit'] ?? ''));
            $item['validity_remaining_display'] = $this->buildRemainingText(
                (int)($item['valid_days'] ?? 0),
                (int)($item['createtime'] ?? 0)
            );
            $items[] = $item;
        }

        // 置顶信息
        $topQuery = InfoModel::where('status', 'published')
            ->where('is_top', '>', 0);
        $this->applyListCategoryFilter($topQuery, $categoryId, $subCategoryId);
        $topInfos = $topQuery->order('is_top desc, createtime desc')
            ->field('id,title,createtime,price,price_unit')
            ->limit(10)
            ->select();
        $topInfos = is_object($topInfos) ? $topInfos->toArray() : (array)$topInfos;
        foreach ($topInfos as &$ti) {
            $ti['created_display'] = $this->formatCreatedDisplay($ti['createtime'] ?? 0);
            $ti['price_display'] = $this->formatPriceDisplay($ti['price'] ?? null, (string)($ti['price_unit'] ?? ''));
        }
        unset($ti);

        $this->assign('list', $items);
        $this->assign('topInfos', $topInfos);
        $this->assign('pager', $list->render());
        $this->assign('keyword', $keyword);
        $this->assign('categoryId', $categoryId);
        $this->assign('subCategoryId', $subCategoryId);
        $this->assign('categoryList', $this->getMainCategories());
        $this->assign('subCategoryList', $categoryId > 0 ? $this->getSubCategories($categoryId) : []);
        $this->assign('page_css', ['__CDN__/assets/frontend/css/info-list.css']);

        return $this->view->fetch('info/list');
    }

    /**
     * 详情页
     */
    public function detail()
    {
        $id = (int)$this->request->get('id', 0);
        $info = InfoModel::get($id);

        if (!$info) {
            $this->error('信息不存在');
        }

        $user = $this->auth->getUser();
        $isOwner = $user && (int)$info->user_id > 0 && (int)$info->user_id === (int)$user->id;
        if ((string)$info->status !== 'published' && !$isOwner) {
            if ((string)$info->status === 'expired') {
                return $this->renderExpiredPage((int)$info->id);
            }
            return $this->renderReviewPendingPage((int)$info->id, 'review');
        }

        // 增加浏览量
        InfoModel::where('id', $id)->setInc('view_count', 1);

        $infoData = $info->getData();
        $infoData['images'] = $this->parseImages($infoData['images'] ?? '');
        $rawContactPhone = trim((string)($infoData['contact_phone'] ?? ''));
        $rawContactWechat = trim((string)($infoData['contact_wechat'] ?? ''));
        $infoData['contact_phone_masked'] = $this->maskPhone($rawContactPhone);
        $infoData['contact_phone_has_value'] = $rawContactPhone !== '';
        $infoData['contact_wechat_plain'] = $rawContactWechat;
        $infoData['phone_region_text'] = '';
        $infoData['contact_phone'] = '';
        $infoData['created_display'] = $this->formatCreatedDisplay($infoData['createtime'] ?? 0);

        // 相关推荐
        $related = InfoModel::where('status', 'published')
            ->where('category_id', (int)$info->category_id)
            ->where('id', '<>', (int)$info->id)
            ->order('is_top desc, createtime desc')
            ->limit(6)
            ->select();
        $related = is_object($related) ? $related->toArray() : (array)$related;

        // 自定义字段
        $detailExtFields = Db::name('info_field_value')
            ->alias('fv')
            ->join('info_field fd', 'fd.id = fv.field_id')
            ->where('fv.info_id', (int)$info->id)
            ->where('fd.status', 'normal')
            ->where('fd.is_detail_show', 1)
            ->order('fd.weigh desc, fd.id asc')
            ->field('fd.name,fd.type,fv.value')
            ->select();
        $detailExtFields = is_object($detailExtFields) ? $detailExtFields->toArray() : (array)$detailExtFields;

        $detailExtFields = array_values(array_filter(array_map(function (array $item) {
            $name = trim((string)($item['name'] ?? ''));
            $type = trim((string)($item['type'] ?? 'text'));
            $value = trim((string)($item['value'] ?? ''));
            if ($type === 'checkbox' && $value !== '') {
                $parts = array_filter(array_map('trim', explode(',', $value)), function ($p) {
                    return $p !== '';
                });
                $value = implode('、', $parts);
            }
            return ['name' => $name, 'value' => $value];
        }, $detailExtFields), function (array $item) {
            return $item['name'] !== '' && $item['value'] !== '';
        }));

        // 编辑入口判断
        $editEntryMode = 'none';
        if ((int)$info->user_id > 0) {
            if ($isOwner) {
                $editEntryMode = 'direct';
            } elseif ($user === null || $user === false) {
                $editEntryMode = 'login';
            }
        } else {
            $verifyPhone = trim((string)$info->verify_phone);
            if ($this->isValidPhone($verifyPhone)) {
                $isMatchedLoginPhone = $user && trim((string)$user->mobile) === $verifyPhone;
                $editEntryMode = $isMatchedLoginPhone ? 'direct' : 'verify';
            }
        }

        $this->assign('info', $infoData);
        $this->assign('related', $related);
        $this->assign('detailExtFields', $detailExtFields);
        $this->assign('editEntryMode', $editEntryMode);
        $this->assign('editUrl', url('info/edit', ['id' => (int)$info->id]));
        $this->assign('editLoginUrl', url('user/login'));
        $this->assign('editAuthInfoId', (int)$info->id);
        $this->assign('editVerifyPhonePrefill', $user ? (string)$user->mobile : '');
        $this->assign('page_css', ['__CDN__/assets/frontend/css/info-detail.css']);

        return $this->view->fetch('info/detail');
    }

    /**
     * 详情页查看完整联系电话
     */
    public function revealContactPhone()
    {
        $id = (int)$this->request->post('id', 0);
        if ($id < 1) {
            return json(['code' => 0, 'msg' => '缺少信息ID']);
        }
        $info = InfoModel::get($id);
        if (!$info) {
            return json(['code' => 0, 'msg' => '信息不存在']);
        }
        if ((string)$info->status !== 'published') {
            return json(['code' => 0, 'msg' => '信息不可访问']);
        }

        $phone = trim((string)$info->getData('contact_phone'));
        if ($phone === '') {
            return json(['code' => 0, 'msg' => '该信息未填写联系电话']);
        }

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'phone' => $phone,
                'masked_phone' => $this->maskPhone($phone),
                'region' => '未知归属地',
            ],
        ]);
    }

    /**
     * 发布栏目中间页
     */
    public function publish_select()
    {
        $user = $this->auth->getUser();
        $categoryGroups = $this->getPublishCategoryGroups();
        list($leftGroups, $rightGroups) = $this->splitCategoryGroups($categoryGroups);

        $guestPhone = $user ? '' : $this->getGuestPublishPhone();

        $this->assign('leftCategoryGroups', $leftGroups);
        $this->assign('rightCategoryGroups', $rightGroups);
        $this->assign('guestPhone', $guestPhone);
        $this->assign('guestHasAccess', $guestPhone !== '');
        $this->assign('page_css', ['__CDN__/assets/frontend/css/info-publish-select.css']);

        return $this->view->fetch('info/publish_select');
    }

    /**
     * 发布页
     */
    public function publish()
    {
        $user = $this->auth->getUser();
        $categoryId = (int)$this->request->get('category_id', 0);
        $subCategoryId = (int)$this->request->get('sub_category_id', 0);

        $guestPhone = '';
        if (!$user) {
            $guestPhone = $this->getGuestPublishPhone();
        }

        $this->assign('categoryList', $this->getMainCategories());
        $this->assign('subCategoryList', $categoryId > 0 ? $this->getSubCategories($categoryId) : []);
        $this->assign('selectedCategoryId', $categoryId);
        $this->assign('selectedSubCategoryId', $subCategoryId);
        $this->assign('guestPublishPhone', $guestPhone);
        $this->assign('guestHasAccess', $guestPhone !== '');
        $this->assign('guestPublishMinutes', (int)ceil($this->guestPublishTtl / 60));
        $this->assign('passwordMinLength', 8);
        $this->assign('imageMaxCount', 9);
        $this->assign('priceUnitOptions', ['元/月', '元/次', '元/年', '元/天', '元/件']);
        $this->assign('validDaysOptions', [7, 15, 30, 60, 90, 0]);
        $this->assign('defaultValidDays', 30);
        $this->assign('page_css', ['__CDN__/assets/css/user-center.css']);

        return $this->view->fetch('info/publish');
    }

    /**
     * 提交发布
     */
    public function doPublish()
    {
        $categoryId = (int)$this->request->post('category_id', 0);
        $subCategoryId = (int)$this->request->post('sub_category_id', 0);
        $title = trim($this->request->post('title', ''));
        $content = trim($this->request->post('content', ''));

        if ($categoryId < 1) {
            return json(['code' => 0, 'msg' => '请选择主分类']);
        }
        if ($title === '') {
            return json(['code' => 0, 'msg' => '请填写标题']);
        }
        if (mb_strlen($title) > 64) {
            return json(['code' => 0, 'msg' => '标题不能超过64个字']);
        }
        if ($content === '' || mb_strlen($content) < 10) {
            return json(['code' => 0, 'msg' => '详细描述至少10个字']);
        }

        if (!$this->isValidCategory($categoryId, $subCategoryId)) {
            return json(['code' => 0, 'msg' => '分类参数无效']);
        }

        // 自定义字段校验
        $ext = $this->request->post('ext/a', []);
        if (!is_array($ext)) {
            $ext = [];
        }
        $extResult = $this->normalizeAndValidateExtFields($categoryId, $subCategoryId, $ext);
        if (!$extResult['ok']) {
            return json(['code' => 0, 'msg' => $extResult['msg']]);
        }

        $user = $this->auth->getUser();
        $userId = $user ? (int)$user->id : 0;
        $verifyPhone = '';
        $managePasswordHash = '';

        if ($userId > 0) {
            $verifyPhone = (string)$user->mobile;
        } else {
            $managePassword = (string)$this->request->post('manage_password', '');
            $managePasswordConfirm = (string)$this->request->post('manage_password_confirm', '');
            if (mb_strlen($managePassword) < 8) {
                return json(['code' => 0, 'msg' => '预留密码至少8位']);
            }
            if ($managePassword !== $managePasswordConfirm) {
                return json(['code' => 0, 'msg' => '两次输入的预留密码不一致']);
            }

            $ticketPhone = $this->getGuestPublishPhone();
            if ($ticketPhone !== '') {
                $verifyPhone = $ticketPhone;
            } else {
                $vp = trim((string)$this->request->post('verify_phone', ''));
                $vc = trim((string)$this->request->post('verify_code', ''));
                if (!$this->isValidPhone($vp) || $vc === '') {
                    return json(['code' => 0, 'msg' => '匿名发布必须完成手机号验证码验证']);
                }
                // 简化验证：此处需对接短信验证服务
                $verifyPhone = $vp;
            }

            $managePasswordHash = password_hash($managePassword, PASSWORD_DEFAULT);
        }

        $images = $this->parseImagesFromPost((string)$this->request->post('images', ''));
        $price = trim((string)$this->request->post('price', ''));
        $priceValue = is_numeric($price) ? (float)$price : null;
        $validDays = (int)$this->request->post('valid_days', 30);
        $now = time();

        $status = 'pending'; // 默认待审核

        try {
            Db::startTrans();
            $infoId = Db::name('info')->insertGetId([
                'user_id'        => $userId,
                'category_id'    => $categoryId,
                'sub_category_id' => $subCategoryId,
                'title'          => $title,
                'content'        => $content,
                'images'         => json_encode($images, JSON_UNESCAPED_UNICODE),
                'price'          => $priceValue,
                'price_unit'     => trim((string)$this->request->post('price_unit', '')),
                'valid_days'     => $validDays,
                'contact_name'   => trim((string)$this->request->post('contact_name', '')),
                'contact_phone'  => trim((string)$this->request->post('contact_phone', '')),
                'contact_wechat' => trim((string)$this->request->post('contact_wechat', '')),
                'verify_phone'   => $verifyPhone ?: null,
                'manage_password' => $managePasswordHash,
                'status'         => $status,
                'view_count'     => 0,
                'is_top'         => 0,
                'createtime'     => $now,
                'updatetime'     => $now,
            ]);

            $this->saveExtFieldValues($infoId, $extResult['rows']);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '发布失败，请稍后重试']);
        }

        if ($userId === 0) {
            $this->clearGuestPublishTicket();
        }

        return json([
            'code' => 1,
            'msg' => '发布成功，等待审核',
            'id' => $infoId,
            'redirect' => (string)url('info/review_pending', ['id' => $infoId, 'scene' => 'publish']),
        ]);
    }

    /**
     * 编辑页
     */
    public function edit()
    {
        $id = (int)$this->request->get('id', 0);
        $info = InfoModel::get($id);

        if (!$info) {
            $this->error('信息不存在');
        }

        $user = $this->auth->getUser();
        $isOwner = $user && (int)$info->user_id > 0 && (int)$info->user_id === (int)$user->id;

        if ((int)$info->user_id > 0 && !$isOwner) {
            if (!$user) {
                $this->redirect(url('user/login'));
            }
            $this->error('无权编辑该信息');
        }

        $infoData = $info->getData();
        $infoData['images'] = $this->parseImages($infoData['images'] ?? '');

        $this->assign('info', $infoData);
        $this->assign('imagesJson', json_encode($infoData['images'] ?? [], JSON_UNESCAPED_UNICODE));
        $this->assign('isOwner', $isOwner);
        $this->assign('requireOtp', false);
        $this->assign('categoryList', $this->getMainCategories());
        $this->assign('subCategoryList', $this->getSubCategories((int)$info->category_id));
        $this->assign('imageMaxCount', 9);
        $this->assign('priceUnitOptions', ['元/月', '元/次', '元/年', '元/天', '元/件']);
        $this->assign('validDaysOptions', [7, 15, 30, 60, 90, 0]);
        $this->assign('defaultValidDays', 30);
        $this->assign('page_css', ['__CDN__/assets/css/user-center.css']);

        return $this->view->fetch('info/edit');
    }

    /**
     * 提交编辑
     */
    public function update()
    {
        $id = (int)$this->request->post('id', 0);
        $info = InfoModel::get($id);
        if (!$info) {
            return json(['code' => 0, 'msg' => '信息不存在']);
        }

        $user = $this->auth->getUser();
        $isOwner = $user && (int)$info->user_id > 0 && (int)$info->user_id === (int)$user->id;

        if ((int)$info->user_id > 0 && !$isOwner) {
            return json(['code' => 0, 'msg' => '无权编辑']);
        }

        $categoryId = (int)$this->request->post('category_id', 0);
        $subCategoryId = (int)$this->request->post('sub_category_id', 0);
        $title = trim((string)$this->request->post('title', ''));
        $content = trim((string)$this->request->post('content', ''));

        if ($categoryId < 1 || $title === '' || mb_strlen($title) > 64 || mb_strlen($content) < 10) {
            return json(['code' => 0, 'msg' => '请填写完整且正确的分类、标题、内容']);
        }

        if (!$this->isValidCategory($categoryId, $subCategoryId)) {
            return json(['code' => 0, 'msg' => '分类参数无效']);
        }

        $ext = $this->request->post('ext/a', []);
        if (!is_array($ext)) {
            $ext = [];
        }
        $extResult = $this->normalizeAndValidateExtFields($categoryId, $subCategoryId, $ext);
        if (!$extResult['ok']) {
            return json(['code' => 0, 'msg' => $extResult['msg']]);
        }

        $images = $this->parseImagesFromPost((string)$this->request->post('images', ''));
        $price = trim((string)$this->request->post('price', ''));
        $priceValue = is_numeric($price) ? (float)$price : null;
        $validDays = (int)$this->request->post('valid_days', 30);

        try {
            Db::startTrans();
            Db::name('info')->where('id', $id)->update([
                'category_id'     => $categoryId,
                'sub_category_id' => $subCategoryId,
                'title'           => $title,
                'content'         => $content,
                'images'          => json_encode($images, JSON_UNESCAPED_UNICODE),
                'price'           => $priceValue,
                'price_unit'      => trim((string)$this->request->post('price_unit', '')),
                'valid_days'      => $validDays,
                'contact_name'    => trim((string)$this->request->post('contact_name', '')),
                'contact_phone'   => trim((string)$this->request->post('contact_phone', '')),
                'contact_wechat'  => trim((string)$this->request->post('contact_wechat', '')),
                'status'          => 'pending',
                'updatetime'      => time(),
            ]);

            $this->saveExtFieldValues($id, $extResult['rows']);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '保存失败']);
        }

        return json([
            'code' => 1,
            'msg' => '保存成功，等待审核',
            'redirect' => (string)url('info/review_pending', ['id' => $id, 'scene' => 'edit']),
        ]);
    }

    /**
     * 前台按分类获取自定义字段 (AJAX)
     */
    public function fields()
    {
        $categoryId = (int)$this->request->get('category_id', 0);
        $subCategoryId = (int)$this->request->get('sub_category_id', 0);
        $infoId = (int)$this->request->get('info_id', 0);

        if ($categoryId < 1) {
            return json(['code' => 0, 'msg' => '缺少分类参数']);
        }

        $definitions = $this->getFieldDefinitionsForCategory($categoryId, $subCategoryId);
        $currentValues = $infoId > 0 ? $this->loadInfoExtValues($infoId) : [];

        $data = [];
        foreach ($definitions as $def) {
            $key = (string)($def['field_key'] ?? '');
            $type = (string)($def['type'] ?? 'text');
            $value = $currentValues[$key] ?? '';

            $options = $def['options'] ?? '';
            if (!is_array($options)) {
                $options = $this->decodeFieldOptions($options);
            }

            $data[] = [
                'id'          => (int)($def['id'] ?? 0),
                'key'         => $key,
                'name'        => (string)($def['name'] ?? ''),
                'type'        => $type,
                'required'    => (int)($def['is_required'] ?? 0),
                'placeholder' => (string)($def['placeholder'] ?? ''),
                'options'     => $options,
                'value'       => $value,
            ];
        }

        return json(['code' => 1, 'data' => $data]);
    }

    /**
     * 图片上传
     */
    public function uploadImage()
    {
        $file = $this->request->file('image');
        if (!$file) {
            return json(['code' => 0, 'msg' => '请选择图片']);
        }

        $info = $file->validate(['ext' => 'jpg,jpeg,png,gif,bmp', 'size' => 10 * 1024 * 1024])
            ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'info');

        if ($info) {
            $url = '/uploads/info/' . str_replace('\\', '/', $info->getSaveName());
            return json(['code' => 1, 'msg' => '上传成功', 'data' => ['url' => $url]]);
        }
        return json(['code' => 0, 'msg' => $file->getError()]);
    }

    /**
     * 发送验证码 (stub)
     */
    public function sendCode()
    {
        $phone = trim((string)$this->request->post('phone', ''));
        if (!$this->isValidPhone($phone)) {
            return json(['code' => 0, 'msg' => '手机号格式不正确']);
        }
        // TODO: 对接实际短信服务
        return json(['code' => 1, 'msg' => '验证码已发送（开发环境）', 'debug_code' => '123456']);
    }

    /**
     * 匿名编辑入口验证
     */
    public function verifyEditAccess()
    {
        $infoId = (int)$this->request->post('info_id', 0);
        $phone = trim((string)$this->request->post('phone', ''));
        $authMode = trim((string)$this->request->post('auth_mode', 'code'));
        $password = (string)$this->request->post('password', '');

        if ($infoId < 1) {
            return json(['code' => 0, 'msg' => '缺少信息ID']);
        }

        $info = InfoModel::get($infoId);
        if (!$info) {
            return json(['code' => 0, 'msg' => '信息不存在']);
        }

        if ((int)$info->user_id > 0) {
            return json(['code' => 0, 'msg' => '该信息请登录发布账号后编辑']);
        }

        $verifyPhone = trim((string)$info->verify_phone);
        if (!$this->isValidPhone($verifyPhone) || $phone !== $verifyPhone) {
            return json(['code' => 0, 'msg' => '手机号与发布信息不一致']);
        }

        if ($authMode === 'password') {
            $managePasswordHash = trim((string)$info->manage_password);
            if ($managePasswordHash === '' || !password_verify($password, $managePasswordHash)) {
                return json(['code' => 0, 'msg' => '预留密码错误']);
            }
        }
        // code mode: 简化处理

        $this->setGuestEditTicket($infoId, $verifyPhone);

        return json([
            'code' => 1,
            'msg' => '验证成功',
            'redirect' => (string)url('info/edit', ['id' => $infoId]),
        ]);
    }

    /**
     * 游客发布入口验证码校验
     */
    public function verifyGuestAccess()
    {
        $phone = trim((string)$this->request->post('phone', ''));
        if (!$this->isValidPhone($phone)) {
            return json(['code' => 0, 'msg' => '请输入正确的手机号']);
        }
        // 简化处理
        $this->setGuestPublishTicket($phone);

        return json([
            'code' => 1,
            'msg' => '验证成功，可直接发布',
            'data' => [
                'phone' => $phone,
                'expires_in' => $this->guestPublishTtl,
            ],
        ]);
    }

    /**
     * 待审核提示页
     */
    public function review_pending()
    {
        $infoId = (int)$this->request->get('id', 0);
        $scene = trim((string)$this->request->get('scene', 'publish'));
        return $this->renderReviewPendingPage($infoId, $scene);
    }

    // ======================== 私有辅助方法 ========================

    private function getMainCategories()
    {
        $data = InfoCategory::where('pid', 0)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        return is_object($data) ? $data->toArray() : (array)$data;
    }

    private function getSubCategories($parentId)
    {
        if ($parentId < 1) {
            return [];
        }
        $data = InfoCategory::where('pid', $parentId)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        return is_object($data) ? $data->toArray() : (array)$data;
    }

    private function applyListCategoryFilter($query, $categoryId, $subCategoryId)
    {
        if ($categoryId < 1) {
            return;
        }
        if ($subCategoryId > 0) {
            $query->where(function ($sub) use ($subCategoryId) {
                $sub->where('sub_category_id', $subCategoryId)
                    ->whereOr('category_id', $subCategoryId);
            });
            return;
        }
        // 包含主分类及所有子分类
        $subIds = InfoCategory::where('pid', $categoryId)->column('id');
        if (empty($subIds)) {
            $query->where('category_id', $categoryId);
            return;
        }
        $query->where(function ($sub) use ($categoryId, $subIds) {
            $sub->where('category_id', $categoryId)
                ->whereOr('category_id', 'in', $subIds)
                ->whereOr('sub_category_id', 'in', $subIds);
        });
    }

    private function isValidCategory($categoryId, $subCategoryId)
    {
        $parent = InfoCategory::where('id', $categoryId)
            ->where('pid', 0)
            ->where('status', 'normal')
            ->find();
        if (!$parent) {
            return false;
        }
        if ($subCategoryId > 0) {
            $child = InfoCategory::where('id', $subCategoryId)
                ->where('pid', $categoryId)
                ->where('status', 'normal')
                ->find();
            return $child ? true : false;
        }
        return true;
    }

    private function getFieldDefinitionsForCategory($categoryId, $subCategoryId)
    {
        if ($categoryId < 1) {
            return [];
        }
        $query = InfoField::where('status', 'normal')
            ->where('category_id', $categoryId);
        if ($subCategoryId > 0) {
            $query->whereIn('sub_category_id', [0, $subCategoryId]);
        } else {
            $query->where('sub_category_id', 0);
        }
        $list = $query->order('weigh desc, id asc')->select();
        $list = is_object($list) ? $list->toArray() : (array)$list;
        foreach ($list as &$item) {
            $item['options'] = $this->decodeFieldOptions($item['options'] ?? null);
        }
        unset($item);
        return $list;
    }

    private function normalizeAndValidateExtFields($categoryId, $subCategoryId, array $ext)
    {
        $definitions = $this->getFieldDefinitionsForCategory($categoryId, $subCategoryId);
        if (empty($definitions)) {
            return ['ok' => true, 'msg' => 'ok', 'rows' => []];
        }

        $rows = [];
        foreach ($definitions as $def) {
            $fieldId = (int)($def['id'] ?? 0);
            $fieldKey = trim((string)($def['field_key'] ?? ''));
            $fieldName = trim((string)($def['name'] ?? ''));
            $fieldType = trim((string)($def['type'] ?? 'text'));
            $required = (int)($def['is_required'] ?? 0) === 1;
            $options = is_array($def['options']) ? $def['options'] : [];

            if ($fieldId < 1 || $fieldKey === '') {
                continue;
            }

            $raw = $ext[$fieldKey] ?? null;

            if ($fieldType === 'checkbox') {
                $items = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []);
                $normalizedList = array_values(array_unique(array_filter(array_map(function ($v) {
                    return is_scalar($v) ? trim((string)$v) : '';
                }, $items), function ($v) { return $v !== ''; })));

                if ($required && empty($normalizedList)) {
                    return ['ok' => false, 'msg' => "请填写{$fieldName}", 'rows' => []];
                }
                if (!empty($normalizedList)) {
                    $rows[] = ['field_id' => $fieldId, 'key' => $fieldKey, 'value' => implode(',', $normalizedList)];
                }
                continue;
            }

            $value = is_scalar($raw) ? trim((string)$raw) : '';
            if ($required && $value === '') {
                return ['ok' => false, 'msg' => "请填写{$fieldName}", 'rows' => []];
            }
            if ($value !== '') {
                $rows[] = ['field_id' => $fieldId, 'key' => $fieldKey, 'value' => $value];
            }
        }

        return ['ok' => true, 'msg' => 'ok', 'rows' => $rows];
    }

    private function saveExtFieldValues($infoId, array $rows)
    {
        Db::name('info_field_value')->where('info_id', $infoId)->delete();
        if (!empty($rows)) {
            $insert = [];
            foreach ($rows as $row) {
                $insert[] = [
                    'info_id'  => $infoId,
                    'field_id' => (int)($row['field_id'] ?? 0),
                    'field_key' => (string)($row['key'] ?? ''),
                    'value'    => (string)($row['value'] ?? ''),
                ];
            }
            Db::name('info_field_value')->insertAll($insert);
        }
    }

    private function loadInfoExtValues($infoId)
    {
        if ($infoId < 1) {
            return [];
        }
        $rows = Db::name('info_field_value')->where('info_id', $infoId)->field('field_key,value')->select();
        $values = [];
        foreach ($rows as $item) {
            $key = trim((string)($item['field_key'] ?? ''));
            if ($key !== '') {
                $values[$key] = (string)($item['value'] ?? '');
            }
        }
        return $values;
    }

    private function decodeFieldOptions($options)
    {
        if (is_array($options)) {
            return $options;
        }
        $text = trim((string)$options);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $rawList = preg_split('/\r\n|\r|\n|,/', $text);
        return array_values(array_unique(array_filter(array_map('trim', $rawList), function ($v) {
            return $v !== '';
        })));
    }

    private function parseImages($images)
    {
        if (empty($images)) {
            return [];
        }
        if (is_array($images)) {
            return $images;
        }
        $decoded = json_decode($images, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_filter(array_map('trim', explode(',', $images)), function ($v) {
            return $v !== '';
        });
    }

    private function parseImagesFromPost($images)
    {
        if ($images === '') {
            return [];
        }
        $decoded = json_decode($images, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), function ($v) {
                return $v !== '';
            }));
        }
        return [];
    }

    private function formatCreatedDisplay($timestamp)
    {
        $timestamp = (int)$timestamp;
        if ($timestamp < 1) {
            return '-';
        }
        $diff = time() - $timestamp;
        if ($diff >= 0 && $diff < 86400) {
            return sprintf('%d小时前', max(1, (int)ceil($diff / 3600)));
        }
        return date('Y-m-d', $timestamp);
    }

    private function formatPriceDisplay($price, $priceUnit = '')
    {
        if (!is_numeric($price)) {
            return '面议';
        }
        $value = rtrim(rtrim(number_format((float)$price, 2, '.', ''), '0'), '.');
        $text = '￥' . ($value ?: '0');
        $unit = trim($priceUnit);
        if ($unit !== '') {
            $text .= ' ' . $unit;
        }
        return $text;
    }

    private function buildRemainingText($validDays, $createtime)
    {
        if ($validDays <= 0) {
            return '长期';
        }
        $createtime = (int)$createtime;
        if ($createtime < 1) {
            return '长期';
        }
        $expireTime = $createtime + $validDays * 86400;
        $remaining = $expireTime - time();
        if ($remaining <= 0) {
            return '已过期';
        }
        $days = (int)ceil($remaining / 86400);
        return "剩余{$days}天";
    }

    private function maskPhone($phone)
    {
        $phone = trim((string)$phone);
        if (strlen($phone) >= 11) {
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        }
        if (strlen($phone) >= 7) {
            return substr($phone, 0, 3) . '****';
        }
        return $phone ?: '-';
    }

    private function isValidPhone($phone)
    {
        return preg_match('/^1[3-9]\d{9}$/', trim((string)$phone)) === 1;
    }

    private function getPublishCategoryGroups()
    {
        $parents = $this->getMainCategories();
        if (empty($parents)) {
            return [];
        }
        $parentIds = array_column($parents, 'id');
        $children = InfoCategory::whereIn('pid', $parentIds)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $children = is_object($children) ? $children->toArray() : (array)$children;

        $childrenMap = [];
        foreach ($children as $item) {
            $pid = (int)($item['pid'] ?? 0);
            $childrenMap[$pid][] = ['id' => (int)$item['id'], 'name' => (string)$item['name']];
        }

        $groups = [];
        foreach ($parents as $item) {
            $id = (int)$item['id'];
            $groups[] = [
                'id'   => $id,
                'name' => (string)$item['name'],
                'icon' => $this->resolveCategoryIcon((string)($item['name'] ?? '')),
                'subs' => $childrenMap[$id] ?? [],
            ];
        }
        return $groups;
    }

    private function splitCategoryGroups(array $groups)
    {
        $left = [];
        $right = [];
        foreach ($groups as $i => $g) {
            if ($i % 2 === 0) {
                $left[] = $g;
            } else {
                $right[] = $g;
            }
        }
        return [$left, $right];
    }

    private function resolveCategoryIcon($name)
    {
        if (mb_strpos($name, '房') !== false) return 'home';
        if (mb_strpos($name, '人') !== false) return 'user';
        if (mb_strpos($name, '车') !== false) return 'road';
        if (mb_strpos($name, '服务') !== false) return 'wrench';
        if (mb_strpos($name, '供') !== false) return 'shopping-cart';
        if (mb_strpos($name, '兑') !== false) return 'briefcase';
        return 'th-large';
    }

    private function getGuestPublishPhone()
    {
        $ticket = Session::get($this->guestPublishSessionKey);
        if (!is_array($ticket)) {
            return '';
        }
        $phone = trim((string)($ticket['phone'] ?? ''));
        $expiresAt = (int)($ticket['expires_at'] ?? 0);
        if ($phone === '' || $expiresAt <= time()) {
            Session::delete($this->guestPublishSessionKey);
            return '';
        }
        return $phone;
    }

    private function setGuestPublishTicket($phone)
    {
        Session::set($this->guestPublishSessionKey, [
            'phone' => $phone,
            'expires_at' => time() + $this->guestPublishTtl,
        ]);
    }

    private function clearGuestPublishTicket()
    {
        Session::delete($this->guestPublishSessionKey);
    }

    private function setGuestEditTicket($infoId, $phone)
    {
        $all = Session::get($this->guestEditSessionKey);
        if (!is_array($all)) {
            $all = [];
        }
        $all[(string)$infoId] = [
            'phone' => $phone,
            'expires_at' => time() + $this->guestEditTtl,
        ];
        Session::set($this->guestEditSessionKey, $all);
    }

    private function renderReviewPendingPage($infoId = 0, $scene = 'publish')
    {
        $title = '发布成功，等待审核';
        $desc = '你发布的信息正在审核中，审核通过后会自动展示在信息列表中。';
        if ($scene === 'edit') {
            $title = '修改已提交，等待审核';
            $desc = '你提交的修改内容正在审核中，审核通过后会自动更新到前台展示。';
        } elseif ($scene === 'review') {
            $title = '信息正在审核中';
            $desc = '当前信息暂未通过审核，审核通过后会自动展示在信息列表中。';
        }

        $this->assign('noticeTitle', $title);
        $this->assign('noticeDesc', $desc);
        $this->assign('infoId', $infoId);
        $this->assign('scene', $scene);

        return $this->view->fetch('info/review_pending');
    }

    private function renderExpiredPage($infoId = 0)
    {
        $this->assign('noticeTitle', '信息已过期下架');
        $this->assign('noticeDesc', '该信息已超过有效期，暂不对外展示。');
        $this->assign('infoId', $infoId);

        return $this->view->fetch('info/expired');
    }
}
