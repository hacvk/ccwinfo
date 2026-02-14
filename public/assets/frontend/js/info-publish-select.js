(function ($) {
    'use strict';

    window.PublishSelect = window.PublishSelect || {};

    var state = {
        targetUrl: '',
        loggedIn: false,
        guestAccess: false,
        guestPhone: '',
        cfg: {},
        wait: {
            guest: 0,
            login: 0,
            pwdLogin: 0
        }
    };

    function isSuccess(res) {
        return Number((res || {}).code) === 1;
    }

    function toAbsoluteUrl(url) {
        var target = $.trim(url || '');
        if (!target) return '/';
        if (/^https?:\/\//i.test(target)) return target;
        if (target.charAt(0) !== '/') return '/' + target;
        return target;
    }

    function isPhone(phone) {
        return /^1[3-9]\d{9}$/.test(phone);
    }

    function buildPublishUrl(categoryId, subCategoryId) {
        var base = $.trim(state.cfg.publishUrl || '/index/info/publish');
        var params = ['category_id=' + encodeURIComponent(Number(categoryId || 0))];
        if (Number(subCategoryId || 0) > 0) {
            params.push('sub_category_id=' + encodeURIComponent(Number(subCategoryId)));
        }
        return base + (base.indexOf('?') > -1 ? '&' : '?') + params.join('&');
    }

    function goTarget() {
        window.location.href = toAbsoluteUrl(state.targetUrl || state.cfg.publishUrl || '/');
    }

    function startCountDown($btn, key) {
        var wait = Number(state.wait[key] || 0);
        if (wait <= 0) {
            $btn.prop('disabled', false).text('获取验证码');
            return;
        }

        $btn.prop('disabled', true).text(wait + 's 后重试');
        state.wait[key] = wait - 1;
        setTimeout(function () {
            startCountDown($btn, key);
        }, 1000);
    }

    function sendAuthCode($btn, phone, scene, key) {
        if (!isPhone(phone)) return Frontend.alert('请输入正确手机号', 'warning');
        if (Number(state.wait[key] || 0) > 0) return;

        Frontend.post(state.cfg.sendLoginCodeApi, {phone: phone, scene: scene}, function (res) {
            if (!isSuccess(res)) return Frontend.alert(res.msg || '发送失败', 'danger');
            Frontend.alert(res.msg || '验证码已发送', 'success');
            if (res.debug_code) {
                Frontend.alert('调试验证码：' + res.debug_code, 'info', 8000);
            }
            state.wait[key] = 60;
            startCountDown($btn, key);
        });
    }

    function onLoginSuccess(res) {
        state.loggedIn = true;
        $('#publish-choice-modal').modal('hide');
        $('#guest-verify-modal').modal('hide');
        $('#publish-login-modal').modal('hide');
        Frontend.alert(res.msg || '登录成功', 'success');
        setTimeout(goTarget, 120);
    }

    function bindCategoryClick() {
        $('.publish-category-link').on('click', function (e) {
            e.preventDefault();
            var categoryId = Number($(this).data('category-id') || 0);
            var subCategoryId = Number($(this).data('sub-category-id') || 0);
            state.targetUrl = buildPublishUrl(categoryId, subCategoryId);

            if (state.loggedIn) {
                goTarget();
                return;
            }
            $('#publish-choice-modal').modal('show');
        });
    }

    function bindChoiceModal() {
        $('#choose-login-btn').on('click', function () {
            $('#publish-choice-modal').modal('hide');
            $('#publish-login-modal').modal('show');
        });

        $('#choose-guest-btn').on('click', function () {
            $('#publish-choice-modal').modal('hide');
            if (state.guestAccess) {
                goTarget();
                return;
            }

            $('#guest-phone').val(state.guestPhone || '');
            $('#guest-verify-modal').modal('show');
        });
    }

    function bindGuestVerify() {
        $('#send-guest-code-btn').on('click', function () {
            var $btn = $(this);
            var phone = $.trim($('#guest-phone').val());
            if (!isPhone(phone)) return Frontend.alert('请输入正确手机号', 'warning');
            if (Number(state.wait.guest || 0) > 0) return;

            Frontend.post(state.cfg.sendGuestCodeApi, {phone: phone, type: 'publish'}, function (res) {
                if (!isSuccess(res)) return Frontend.alert(res.msg || '发送失败', 'danger');
                Frontend.alert(res.msg || '验证码已发送', 'success');
                if (res.debug_code) {
                    Frontend.alert('调试验证码：' + res.debug_code, 'info', 8000);
                }
                state.wait.guest = 60;
                startCountDown($btn, 'guest');
            });
        });

        $('#guest-verify-form').on('submit', function (e) {
            e.preventDefault();
            var phone = $.trim($('#guest-phone').val());
            var code = $.trim($('#guest-code').val());

            if (!isPhone(phone) || code === '') {
                return Frontend.alert('请输入正确的手机号和验证码', 'warning');
            }

            Frontend.post(state.cfg.verifyGuestApi, {phone: phone, code: code}, function (res) {
                if (!isSuccess(res)) return Frontend.alert(res.msg || '验证失败', 'danger');

                state.guestAccess = true;
                state.guestPhone = phone;
                $('#guest-verify-modal').modal('hide');
                Frontend.alert(res.msg || '验证成功', 'success');
                setTimeout(goTarget, 120);
            });
        });
    }

    function bindLoginModal() {
        $('#send-login-code-modal').on('click', function () {
            var phone = $.trim($('#publish-login-phone').val());
            sendAuthCode($(this), phone, 'login', 'login');
        });

        $('#publish-login-sms-form').on('submit', function (e) {
            e.preventDefault();
            Frontend.post(state.cfg.smsLoginApi, $(this).serialize(), function (res) {
                if (!isSuccess(res)) return Frontend.alert(res.msg || '登录失败', 'danger');
                onLoginSuccess(res);
            });
        });

        $('#publish-login-password-form').on('submit', function (e) {
            e.preventDefault();
            var phone = $.trim($('#publish-pwd-phone').val());
            Frontend.post(state.cfg.passwordLoginApi, $(this).serialize(), function (res) {
                if (isSuccess(res)) {
                    onLoginSuccess(res);
                    return;
                }

                if (res.need_sms_verify) {
                    $('#publish-pwd-verify-phone').val(phone);
                    $('#publish-password-sms-form').show();
                    Frontend.alert(res.msg || '请先完成短信验证', 'warning');
                    return;
                }

                Frontend.alert(res.msg || '登录失败', 'danger');
            });
        });

        $('#send-password-login-code-modal').on('click', function () {
            var phone = $.trim($('#publish-pwd-verify-phone').val() || $('#publish-pwd-phone').val());
            sendAuthCode($(this), phone, 'password_login', 'pwdLogin');
        });

        $('#publish-password-sms-form').on('submit', function (e) {
            e.preventDefault();
            Frontend.post(state.cfg.passwordSmsVerifyApi, $(this).serialize(), function (res) {
                if (!isSuccess(res)) return Frontend.alert(res.msg || '验证失败', 'danger');
                onLoginSuccess(res);
            });
        });

        $('#publish-login-modal').on('hidden.bs.modal', function () {
            $('#publish-password-sms-form').hide();
            $('#publish-pwd-verify-code').val('');
        });
    }

    window.PublishSelect.init = function () {
        state.cfg = window.PublishSelectConfig || {};
        state.loggedIn = !!state.cfg.isLogin;
        state.guestAccess = !!state.cfg.guestAccess;
        state.guestPhone = $.trim(state.cfg.guestPhone || '');

        bindCategoryClick();
        bindChoiceModal();
        bindGuestVerify();
        bindLoginModal();
    };
})(jQuery);
