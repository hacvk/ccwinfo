define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    'use strict';

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'promotion/slot/index',
                    add_url: 'promotion/slot/add',
                    edit_url: 'promotion/slot/edit',
                    del_url: 'promotion/slot/del',
                    multi_url: 'promotion/slot/multi',
                    table: 'promotion_slot'
                }
            });

            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'code', title: '编码'},
                    {field: 'name', title: '名称'},
                    {field: 'type_text', title: '类型', operate: false},
                    {field: 'max_items', title: '最多条数', operate: false},
                    {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
                    {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                var form = $('form[role=form]');
                Controller.api.initConfigFields();
                form.on('submit', function () {
                    var configPayload = Controller.api.collectConfig();
                    if (configPayload === false) {
                        return false;
                    }
                    $('#c-config').val(JSON.stringify(configPayload));
                    return true;
                });
                Form.api.bindevent(form);
            },
            initConfigFields: function () {
                var rawConfig = $.trim($('#c-config').val() || '');
                var config = {};
                if (rawConfig) {
                    try {
                        config = JSON.parse(rawConfig);
                    } catch (e) {
                        config = {};
                    }
                }

                var pricing = config.pricing || {};
                $('#cfg-price-week').val(Controller.api.safeNumber(pricing.week));
                $('#cfg-price-month').val(Controller.api.safeNumber(pricing.month));
                $('#cfg-price-quarter').val(Controller.api.safeNumber(pricing.quarter));

                var fields = config.form_fields || [];
                if (fields.length) {
                    $('#cfg-form-fields').val(JSON.stringify(fields, null, 2));
                } else {
                    $('#cfg-form-fields').val('[\n  {"name":"title","label":"推广标题","type":"text","required":1,"maxlength":80}\n]');
                }
            },
            safeNumber: function (value) {
                var num = parseFloat(value);
                return isNaN(num) || num < 0 ? 0 : num;
            },
            collectConfig: function () {
                var week = Controller.api.safeNumber($('#cfg-price-week').val());
                var month = Controller.api.safeNumber($('#cfg-price-month').val());
                var quarter = Controller.api.safeNumber($('#cfg-price-quarter').val());
                var formFieldsText = $.trim($('#cfg-form-fields').val() || '[]');
                var formFields = [];

                if (formFieldsText !== '') {
                    try {
                        formFields = JSON.parse(formFieldsText);
                    } catch (e) {
                        Layer.alert('动态字段JSON格式错误，请检查后重试。');
                        return false;
                    }
                }

                if (!$.isArray(formFields)) {
                    Layer.alert('动态字段必须是JSON数组。');
                    return false;
                }

                return {
                    pricing: {
                        week: week,
                        month: month,
                        quarter: quarter
                    },
                    require_login: 1,
                    form_fields: formFields
                };
            }
        }
    };

    return Controller;
});
