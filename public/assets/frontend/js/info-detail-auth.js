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
            if (window.Frontend && window.Frontend.alert) {
                window.Frontend.alert('请输入正确手机号', 'warning');
            } else {
                window.alert('请输入正确手机号');
            }
            return;
        }
        if (state.wait > 0) {
            return;
        }

        var sendCodeApi = state.cfg.sendCodeApi;
        var postData = {
            phone: phone,
            type: 'edit',
            info_id: Number(state.cfg.infoId || 0)
        };

        if (window.Frontend && window.Frontend.post) {
            window.Frontend.post(sendCodeApi, postData, function (res) {
                handleSendCodeResponse(res, $btn);
            });
        } else {
            $.ajax({
                url: sendCodeApi,
                method: 'POST',
                data: postData,
                dataType: 'json',
                success: function (res) {
                    handleSendCodeResponse(res, $btn);
                },
                error: function () {
                    if (window.Frontend && window.Frontend.alert) {
                        window.Frontend.alert('请求失败，请稍后重试', 'danger');
                    } else {
                        window.alert('请求失败，请稍后重试');
                    }
                }
            });
        }
    }

    function handleSendCodeResponse(res, $btn) {
        if (Number((res || {}).code) !== 1) {
            if (window.Frontend && window.Frontend.alert) {
                window.Frontend.alert((res || {}).msg || '发送失败', 'danger');
            } else {
                window.alert((res || {}).msg || '发送失败');
            }
            return;
        }

        if (window.Frontend && window.Frontend.alert) {
            window.Frontend.alert(res.msg || '验证码已发送', 'success');
        } else {
            window.alert(res.msg || '验证码已发送');
        }
        
        if (res.debug_code) {
            setTimeout(function() {
                if (window.Frontend && window.Frontend.alert) {
                    window.Frontend.alert('调试验证码：' + res.debug_code, 'info');
                } else {
                    window.alert('调试验证码：' + res.debug_code);
                }
            }, 100);
        }

        state.wait = 60;
        startCountDown($btn);
    }

    function submitVerify(authMode) {
        var phone = getPhone();
        if (!isPhone(phone)) {
            if (window.Frontend && window.Frontend.alert) {
                window.Frontend.alert('请输入正确手机号', 'warning');
            } else {
                window.alert('请输入正确手机号');
            }
            return;
        }

        var payload = {
            info_id: Number(state.cfg.infoId || 0),
            phone: phone,
            auth_mode: authMode
        };

        if (authMode === 'code') {
            payload.code = $.trim($('#edit-auth-code').val());
            if (payload.code === '') {
                if (window.Frontend && window.Frontend.alert) {
                    window.Frontend.alert('请输入验证码', 'warning');
                } else {
                    window.alert('请输入验证码');
                }
                return;
            }
        } else {
            payload.password = $('#edit-auth-password').val() || '';
            if ($.trim(payload.password) === '') {
                if (window.Frontend && window.Frontend.alert) {
                    window.Frontend.alert('请输入预留密码', 'warning');
                } else {
                    window.alert('请输入预留密码');
                }
                return;
            }
        }

        var verifyApi = state.cfg.verifyApi;
        
        if (window.Frontend && window.Frontend.post) {
            window.Frontend.post(verifyApi, payload, function (res) {
                handleVerifyResponse(res);
            });
        } else {
            $.ajax({
                url: verifyApi,
                method: 'POST',
                data: payload,
                dataType: 'json',
                success: function (res) {
                    handleVerifyResponse(res);
                },
                error: function () {
                    if (window.Frontend && window.Frontend.alert) {
                        window.Frontend.alert('请求失败，请稍后重试', 'danger');
                    } else {
                        window.alert('请求失败，请稍后重试');
                    }
                }
            });
        }
    }

    function handleVerifyResponse(res) {
        if (Number((res || {}).code) !== 1) {
            if (window.Frontend && window.Frontend.alert) {
                window.Frontend.alert((res || {}).msg || '验证失败', 'danger');
            } else {
                window.alert((res || {}).msg || '验证失败');
            }
            return;
        }

        var redirectUrl = (res && res.redirect) || state.cfg.editUrl || '/';
        
        if (window.Frontend && window.Frontend.alert) {
            window.Frontend.alert(res.msg || '验证成功', 'success');
        } else {
            window.alert(res.msg || '验证成功');
        }
        
        setTimeout(function () {
            window.location.href = redirectUrl;
        }, 600);
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
