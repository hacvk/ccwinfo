define(['jquery'], function ($) {
    'use strict';

    window.InfoManage = window.InfoManage || {};

    var state = {
        cfg: {},
        currentPhone: '',
        items: {}
    };

    function isPhone(phone) {
        return /^1[3-9]\d{9}$/.test(phone);
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showAlert(msg, type) {
        if (window.Frontend && window.Frontend.alert) {
            window.Frontend.alert(msg, type || 'info');
        } else {
            window.alert(msg);
        }
    }

    function post(url, data, callback) {
        if (window.Frontend && window.Frontend.post) {
            window.Frontend.post(url, data, callback);
            return;
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json',
            success: callback,
            error: function () {
                showAlert('请求失败，请稍后重试', 'danger');
            }
        });
    }

    function getPhone() {
        return $.trim($('#manage-phone').val());
    }

    function setEmpty(text) {
        $('#manage-result-list').empty();
        $('#manage-result-empty').show().find('p').text(text);
    }

    function renderItems(items) {
        var html = [];
        state.items = {};

        $.each(items || [], function (_, item) {
            var id = Number(item.id || 0);
            if (!id) {
                return true;
            }
            state.items[id] = item;

            var badgeClass = item.publisher_type === 'guest' ? 'label-info' : 'label-warning';
            var badgeText = item.publisher_type === 'guest' ? '游客发布' : '账号发布';
            var actions = [];

            if (Number(item.can_password_manage || 0) === 1) {
                actions.push('<button type="button" class="btn btn-default btn-xs js-password-edit" data-id="' + id + '">预留密码修改</button>');
                actions.push('<button type="button" class="btn btn-danger btn-xs js-password-revoke" data-id="' + id + '">预留密码撤销</button>');
            } else if (item.publisher_type === 'guest') {
                actions.push('<button type="button" class="btn btn-default btn-xs" disabled>未设置预留密码</button>');
            } else if (Number(item.can_direct_edit || 0) === 1 || Number(item.can_direct_revoke || 0) === 1) {
                if (Number(item.can_direct_edit || 0) === 1) {
                    actions.push('<a href="' + escapeHtml(item.edit_url || '#') + '" class="btn btn-default btn-xs">直接修改</a>');
                }
                if (Number(item.can_direct_revoke || 0) === 1) {
                    actions.push('<button type="button" class="btn btn-danger btn-xs js-direct-revoke" data-id="' + id + '">直接撤销</button>');
                }
            } else {
                actions.push('<button type="button" class="btn btn-warning btn-xs js-login-manage" data-id="' + id + '">登录后管理</button>');
            }

            html.push(
                '<div class="manage-item">' +
                    '<div class="manage-item-main">' +
                        '<div class="manage-item-title"><a href="' + escapeHtml(item.detail_url || '#') + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(item.title || '-') + '</a></div>' +
                        '<div class="manage-item-meta">' +
                            '<span class="label ' + badgeClass + '">' + badgeText + '</span>' +
                            '<span class="manage-item-date">' + escapeHtml(item.created_display || '-') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="manage-item-actions">' + actions.join('') + '</div>' +
                '</div>'
            );
        });

        if (!html.length) {
            setEmpty('未找到该手机号对应的已发布信息');
            return;
        }

        $('#manage-result-empty').hide();
        $('#manage-result-list').html(html.join(''));
    }

    function search() {
        var phone = getPhone();
        if (!isPhone(phone)) {
            showAlert('请输入正确的手机号', 'warning');
            return;
        }

        state.currentPhone = phone;
        $('#manage-search-btn').prop('disabled', true);
        post(state.cfg.searchApi, { phone: phone }, function (res) {
            $('#manage-search-btn').prop('disabled', false);
            if (Number((res || {}).code) !== 1) {
                setEmpty('检索失败，请稍后重试');
                showAlert((res || {}).msg || '检索失败', 'danger');
                return;
            }
            var items = (((res || {}).data || {}).items || []);
            renderItems(items);
        });
    }

    function goLogin(item) {
        var loginUrl = (item && item.login_url) || state.cfg.loginUrl || '/user/login';
        showAlert('该信息需登录发布账号后管理，正在跳转登录页', 'warning');
        setTimeout(function () {
            window.location.href = loginUrl;
        }, 500);
    }

    function openPasswordModal(id, action) {
        $('#manage-info-id').val(id);
        $('#manage-action-type').val(action);
        $('#manage-action-phone').val(state.currentPhone || '');
        $('#manage-password').val('');
        var text = action === 'edit' ? '输入预留密码后进入修改' : '输入预留密码后撤销信息';
        $('#managePasswordTitle').text(text);
        $('#manage-password-modal').modal('show');
    }

    function doDirectRevoke(id) {
        if (!window.confirm('确认撤销该信息吗？撤销后不可恢复。')) {
            return;
        }
        post(state.cfg.revokeApi, { info_id: id }, function (res) {
            if (Number((res || {}).need_login || 0) === 1) {
                goLogin({ login_url: (res || {}).redirect || state.cfg.loginUrl });
                return;
            }
            if (Number((res || {}).code) !== 1) {
                showAlert((res || {}).msg || '撤销失败', 'danger');
                return;
            }
            showAlert((res || {}).msg || '撤销成功', 'success');
            search();
        });
    }

    function submitPasswordAction() {
        var id = Number($('#manage-info-id').val() || 0);
        var action = $('#manage-action-type').val();
        var phone = $.trim($('#manage-action-phone').val());
        var password = $('#manage-password').val() || '';
        var item = state.items[id] || {};

        if (!id || !action) return;
        if ($.trim(password) === '') {
            showAlert('请输入预留密码', 'warning');
            return;
        }

        $('#manage-password-submit').prop('disabled', true);
        if (action === 'edit') {
            post(state.cfg.verifyApi, {
                info_id: id,
                phone: phone,
                auth_mode: 'password',
                password: password
            }, function (res) {
                $('#manage-password-submit').prop('disabled', false);
                if (Number((res || {}).code) !== 1) {
                    showAlert((res || {}).msg || '验证失败', 'danger');
                    return;
                }
                var redirect = (res || {}).redirect || item.edit_url || '/';
                window.location.href = redirect;
            });
            return;
        }

        post(state.cfg.revokeApi, {
            info_id: id,
            phone: phone,
            password: password
        }, function (res) {
            $('#manage-password-submit').prop('disabled', false);
            if (Number((res || {}).code) !== 1) {
                showAlert((res || {}).msg || '撤销失败', 'danger');
                return;
            }
            $('#manage-password-modal').modal('hide');
            showAlert((res || {}).msg || '撤销成功', 'success');
            search();
        });
    }

    function bindEvents() {
        $('#info-manage-search-form').on('submit', function (e) {
            e.preventDefault();
            search();
        });

        $('#manage-result-list').on('click', '.js-password-edit', function () {
            openPasswordModal(Number($(this).data('id') || 0), 'edit');
        });

        $('#manage-result-list').on('click', '.js-password-revoke', function () {
            openPasswordModal(Number($(this).data('id') || 0), 'revoke');
        });

        $('#manage-result-list').on('click', '.js-direct-revoke', function () {
            doDirectRevoke(Number($(this).data('id') || 0));
        });

        $('#manage-result-list').on('click', '.js-login-manage', function () {
            var id = Number($(this).data('id') || 0);
            goLogin(state.items[id] || {});
        });

        $('#manage-password-form').on('submit', function (e) {
            e.preventDefault();
            submitPasswordAction();
        });

        $('#manage-password-modal').on('hidden.bs.modal', function () {
            $('#manage-password-submit').prop('disabled', false);
            $('#manage-password').val('');
        });
    }

    window.InfoManage.init = function () {
        state.cfg = window.InfoManageConfig || {};
        if (!state.cfg.searchApi || !state.cfg.verifyApi || !state.cfg.revokeApi) {
            setEmpty('配置缺失，请联系管理员');
            return;
        }
        $('#manage-phone').val($.trim(state.cfg.phonePrefill || ''));
        bindEvents();
    };

    return window.InfoManage;
});
