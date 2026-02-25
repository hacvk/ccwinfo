define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            var tableResponseHandler = function (res) {
                if (res && typeof res.total !== 'undefined' && typeof res.rows !== 'undefined') {
                    return res;
                }
                if (res && Number(res.code) === 1 && res.data && typeof res.data.total !== 'undefined' && typeof res.data.rows !== 'undefined') {
                    return res.data;
                }
                if (res && typeof res.code !== 'undefined' && Number(res.code) === 0) {
                    Toastr.error(res.msg || '数据加载失败');
                }
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('notice table invalid response', res);
                }
                return {total: 0, rows: []};
            };

            var buildUrl = function (path) {
                return Backend.api.fixurl(path);
            };
            var tableLogs = $("#table-logs");
            var tableOtp = $("#table-otp");
            var logsInited = false;
            var otpInited = false;

            var initLogsTable = function () {
                if (logsInited) {
                    return;
                }
                tableLogs.bootstrapTable({
                    url: buildUrl('notice/logs'),
                    pk: 'id',
                    sortName: 'id',
                    sortOrder: 'desc',
                    search: true,
                    searchFormVisible: false,
                    responseHandler: tableResponseHandler,
                    columns: [
                        [
                            {checkbox: true},
                            {field: 'id', title: 'ID', sortable: true},
                            {field: 'phone', title: '手机号', operate: 'LIKE'},
                            {field: 'template_code', title: '模板Code', operate: 'LIKE'},
                            {field: 'scene', title: '业务场景', operate: 'LIKE'},
                            {field: 'channel', title: '渠道', searchList: {"qcloud":"腾讯云","aliyun":"阿里云"}},
                            {field: 'success', title: '发送结果', searchList: {"0":"失败","1":"成功"}, formatter: Table.api.formatter.status},
                            {field: 'provider_biz_id', title: '回执ID', operate: false},
                            {field: 'payload_text', title: '请求参数', operate: false},
                            {field: 'error', title: '错误信息', operate: 'LIKE'},
                            {field: 'createtime', title: '创建时间', sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime}
                        ]
                    ]
                });
                Table.api.bindevent(tableLogs);
                logsInited = true;
            };

            var initOtpTable = function () {
                if (otpInited) {
                    return;
                }
                tableOtp.bootstrapTable({
                    url: buildUrl('notice/otp'),
                    pk: 'id',
                    sortName: 'id',
                    sortOrder: 'desc',
                    search: true,
                    searchFormVisible: false,
                    responseHandler: tableResponseHandler,
                    columns: [
                        [
                            {checkbox: true},
                            {field: 'id', title: 'ID', sortable: true},
                            {field: 'mobile', title: '手机号', operate: 'LIKE'},
                            {field: 'event', title: '事件', operate: 'LIKE'},
                            {field: 'type', title: '类型', operate: 'LIKE'},
                            {field: 'info_id', title: '信息ID', operate: '='},
                            {field: 'code', title: '验证码', operate: 'LIKE'},
                            {field: 'otp_status', title: '状态', searchList: {"unused":"待使用","used":"已使用","expired":"已过期"}, formatter: function(value) {
                                if (value === '待使用') return '<span class="text-success">待使用</span>';
                                if (value === '已使用') return '<span class="text-info">已使用</span>';
                                return '<span class="text-danger">已过期</span>';
                            }},
                            {field: 'ip', title: 'IP地址', operate: 'LIKE'},
                            {field: 'createtime', title: '创建时间', sortable: true, operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                            {field: 'expire_at', title: '过期时间', sortable: true, formatter: Table.api.formatter.datetime},
                            {field: 'used_at', title: '使用时间', sortable: true, formatter: Table.api.formatter.datetime}
                        ]
                    ]
                });
                Table.api.bindevent(tableOtp);
                otpInited = true;
            };

            var ensureTabLoaded = function (tab) {
                var value = tab || 'logs';
                if (value === 'logs') {
                    initLogsTable();
                    tableLogs.bootstrapTable('resetView');
                    return;
                }
                if (value === 'otp') {
                    initOtpTable();
                    tableOtp.bootstrapTable('refresh', {silent: true});
                    tableOtp.bootstrapTable('resetView');
                }
            };

            var updateTabQuery = function (tab) {
                if (!window.history || typeof window.history.replaceState !== 'function') {
                    return;
                }
                var current = window.location.href;
                if (current.indexOf('tab=') > -1) {
                    current = current.replace(/([?&])tab=[^&#]*/g, '$1tab=' + tab);
                } else {
                    current += (current.indexOf('?') > -1 ? '&' : '?') + 'tab=' + tab;
                }
                window.history.replaceState(null, '', current);
            };

            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                var value = $(e.target).data('value');
                if (!value) {
                    return;
                }
                ensureTabLoaded(value);
                updateTabQuery(value);
            });

            ensureTabLoaded($('ul.nav-tabs li.active a[data-toggle="tab"]').data('value') || 'logs');

            $(document).on('click', '.btn-cleanup', function() {
                Layer.confirm('按当前保留策略清理历史数据？', {icon: 3, title: '确认清理'}, function(index) {
                    Layer.close(index);
                    $.ajax({
                        url: buildUrl('notice/cleanup'),
                        type: 'POST',
                        dataType: 'json',
                        success: function(ret) {
                            if (ret.code === 1) {
                                Toastr.success(ret.msg);
                                if (logsInited) {
                                    tableLogs.bootstrapTable('refresh');
                                }
                                if (otpInited) {
                                    tableOtp.bootstrapTable('refresh');
                                }
                            } else {
                                Toastr.error(ret.msg);
                            }
                        }
                    });
                });
            });

            Form.api.bindevent($('#aliyun-form'), function(data, ret) {
                if (ret.code === 1) {
                    Toastr.success(ret.msg);
                }
            });

            Form.api.bindevent($('#mail-form'), function(data, ret) {
                if (ret.code === 1) {
                    Toastr.success(ret.msg);
                }
            });

            $('#test-sms-btn').on('click', function() {
                var data = {
                    aliyun_sms_access_key_id: $('#c-aliyun_sms_access_key_id').val(),
                    aliyun_sms_access_key_secret: $('#c-aliyun_sms_access_key_secret').val(),
                    aliyun_sms_sign_name: $('#c-aliyun_sms_sign_name').val(),
                    aliyun_sms_region_id: $('#c-aliyun_sms_region_id').val() || 'cn-hangzhou',
                    aliyun_sms_endpoint: $('#c-aliyun_sms_endpoint').val() || 'dysmsapi.aliyuncs.com'
                };

                if (!data.aliyun_sms_access_key_id || !data.aliyun_sms_access_key_secret) {
                    Toastr.error('请先填写 AccessKey ID 和 Secret！');
                    return;
                }
                if (!data.aliyun_sms_sign_name) {
                    Toastr.error('请先填写短信签名！');
                    return;
                }

                Layer.prompt({
                    formType: 0,
                    value: '',
                    title: '请输入测试手机号',
                    area: ['300px', '40px']
                }, function(phone, index) {
                    Layer.close(index);

                    if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
                        Toastr.error('请输入正确的手机号！');
                        return;
                    }

                    data.phone = phone;
                    $.ajax({
                        url: buildUrl('notice/testAliyunSms'),
                        type: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(ret) {
                            if (ret.code === 1) {
                                Toastr.success(ret.msg);
                            } else {
                                Toastr.error(ret.msg);
                            }
                        },
                        error: function(xhr) {
                            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.msg) ? xhr.responseJSON.msg : '测试短信请求失败，请检查网络或后端日志';
                            Toastr.error(msg);
                        }
                    });
                });
            });

            $('#test-mail-btn').on('click', function() {
                var data = {
                    mail_type: $('#c-mail_type').val(),
                    mail_smtp_host: $('#c-mail_smtp_host').val(),
                    mail_smtp_port: $('#c-mail_smtp_port').val(),
                    mail_smtp_user: $('#c-mail_smtp_user').val(),
                    mail_smtp_pass: $('#c-mail_smtp_pass').val(),
                    mail_verify_type: $('#c-mail_verify_type').val(),
                    mail_from: $('#c-mail_from').val()
                };

                if (!data.mail_smtp_host || !data.mail_smtp_port || !data.mail_from) {
                    Toastr.error('请先填写 SMTP 主机、端口和发件人邮箱！');
                    return;
                }

                Layer.prompt({
                    formType: 0,
                    value: '',
                    title: '请输入测试收件邮箱',
                    area: ['360px', '40px']
                }, function(receiver, index) {
                    Layer.close(index);

                    if (!receiver || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(receiver)) {
                        Toastr.error('请输入正确的邮箱地址！');
                        return;
                    }

                    $.ajax({
                        url: buildUrl('notice/testMail'),
                        type: 'POST',
                        data: {
                            receiver: receiver,
                            row: data
                        },
                        dataType: 'json',
                        success: function(ret) {
                            if (ret.code === 1) {
                                Toastr.success(ret.msg);
                            } else {
                                Toastr.error(ret.msg);
                            }
                        },
                        error: function(xhr) {
                            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.msg) ? xhr.responseJSON.msg : '测试邮件请求失败，请检查网络或后端日志';
                            Toastr.error(msg);
                        }
                    });
                });
            });

        },
        logs: function () {
            Controller.index();
        },
        otp: function () {
            Controller.index();
        },
        aliyun: function () {
            Controller.index();
        },
        mail: function () {
            Controller.index();
        }
    };
    return Controller;
});
