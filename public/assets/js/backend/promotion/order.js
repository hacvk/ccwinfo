define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'promotion/order/index' + location.search,
                    del_url: 'promotion/order/del',
                    table: 'promotion_order',
                }
            });

            var table = $('#table');

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: '订单ID'},
                        {field: 'item.title', title: '推广标题', operate: false},
                        {field: 'slot_id', title: '展示位ID', operate: false},
                        {field: 'amount', title: '金额', operate: false},
                        {field: 'pay_status_text', title: '支付状态', searchList: Config.payStatusList, formatter: Table.api.formatter.status},
                        {field: 'order_status_text', title: '订单状态', searchList: Config.orderStatusList, formatter: Table.api.formatter.status},
                        {field: 'contact_name', title: '联系人', operate: 'LIKE'},
                        {field: 'contact_mobile', title: '联系电话', operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'markpaid',
                                    text: '标记线下已支付',
                                    classname: 'btn btn-xs btn-success btn-markpaid',
                                    icon: 'fa fa-check',
                                    url: 'promotion/order/markPaid',
                                }
                            ],
                            events: $.extend({}, Table.api.events.operate, {
                                'click .btn-markpaid': function (e, value, row, index) {
                                    var url = 'promotion/order/markPaid/ids/' + row.id;
                                    Layer.confirm('确认将该订单标记为线下已支付？', {icon: 3, title: '确认'}, function (index) {
                                        Layer.close(index);
                                        Fast.api.ajax({url: url, type: 'POST'}, function () {
                                            table.bootstrapTable('refresh');
                                            return false;
                                        });
                                    });
                                    e.stopPropagation();
                                }
                            }),
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
