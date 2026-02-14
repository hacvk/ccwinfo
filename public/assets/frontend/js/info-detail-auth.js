(function ($) {
    'use strict';

    window.InfoDetailAuth = window.InfoDetailAuth || {};

    var state = {
        cfg: {},
        wait: 0
    };

    function isPhone(phone) {
        return /^1[3-9]\d{9}$/.test(phone);
    }

    function startCountDown($btn) {
        if (state.wait <= 0) {
            $btn.prop('disabled', false).text('获取验证码');
            return;
        }

        $btn.prop('disabled', true).text(state.wait + 's 后重试');
        state.wait -= 1;
        setTimeout(function () {
            startCountDown($btn);
        }, 1000);
    }

    function getPhone() {
        return $.trim($('#edit-auth-phone').val());
    }

    function sendCode() {
        var phone = getPhone();
        var $btn = $('#edit-auth-send-code');

        if (!isPhone(phone)) {
            return Frontend.alert('请输入正确手机号', 'warning');
        }
        if (state.wait > 0) {
            return;
        }

        Frontend.post(state.cfg.sendCodeApi, {
            phone: phone,
            type: 'edit',
            info_id: Number(state.cfg.infoId || 0)
        }, function (res) {
            if (Number((res || {}).code) !== 1) {
                return Frontend.alert((res || {}).msg || '发送失败', 'danger');
            }

            Frontend.alert(res.msg || '验证码已发送', 'success');
            if (res.debug_code) {
                Frontend.alert('调试验证码：' + res.debug_code, 'info', 8000);
            }

            state.wait = 60;
            startCountDown($btn);
        });
    }

    function submitVerify(authMode) {
        var phone = getPhone();
        if (!isPhone(phone)) {
            return Frontend.alert('请输入正确手机号', 'warning');
        }

        var payload = {
            info_id: Number(state.cfg.infoId || 0),
            phone: phone,
            auth_mode: authMode
        };

        if (authMode === 'code') {
            payload.code = $.trim($('#edit-auth-code').val());
            if (payload.code === '') {
                return Frontend.alert('请输入验证码', 'warning');
            }
        } else {
            payload.password = $('#edit-auth-password').val() || '';
            if ($.trim(payload.password) === '') {
                return Frontend.alert('请输入预留密码', 'warning');
            }
        }

        Frontend.post(state.cfg.verifyApi, payload, function (res) {
            if (Number((res || {}).code) !== 1) {
                return Frontend.alert((res || {}).msg || '验证失败', 'danger');
            }

            Frontend.alert(res.msg || '验证成功', 'success');
            setTimeout(function () {
                window.location.href = (res && res.redirect) || state.cfg.editUrl || '/';
            }, 120);
        });
    }

    function bindEvents() {
        $('.js-open-edit-auth').on('click', function () {
            $('#edit-auth-modal').modal('show');
        });

        $('#edit-auth-send-code').on('click', function () {
            sendCode();
        });

        $('#edit-auth-code-form').on('submit', function (e) {
            e.preventDefault();
            submitVerify('code');
        });

        $('#edit-auth-password-form').on('submit', function (e) {
            e.preventDefault();
            submitVerify('password');
        });

        $('#edit-auth-modal').on('hidden.bs.modal', function () {
            $('#edit-auth-code').val('');
            $('#edit-auth-password').val('');
        });
    }

    window.InfoDetailAuth.init = function () {
        state.cfg = window.InfoDetailAuthConfig || {};
        bindEvents();
    };
})(jQuery);
