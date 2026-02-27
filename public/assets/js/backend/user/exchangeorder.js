define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'user/exchangeorder/index' + location.search,
                    del_url: 'user/exchangeorder/del',
                    multi_url: 'user/exchangeorder/multi',
                    deliver_url: 'user/exchangeorder/deliver',
                    ship_url: 'user/exchangeorder/ship',
                    reject_url: 'user/exchangeorder/reject',
                    table: 'exchange_order'
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id'), operate: 'LIKE'},
                        {field: 'goods.title', title: __('Goods_title'), operate: false},
                        {field: 'points_cost', title: __('Points_cost'), operate: 'BETWEEN'},
                        {field: 'order_status', title: __('Order_status'), searchList: Config.orderStatusList, formatter: Table.api.formatter.status},
                        {field: 'points_status', title: __('Points_status'), searchList: Config.pointsStatusList, formatter: Table.api.formatter.status},
                        {field: 'receiver_name', title: __('Receiver_name'), operate: 'LIKE'},
                        {field: 'receiver_mobile', title: __('Receiver_mobile'), operate: 'LIKE'},
                        {field: 'deliver_time', title: __('Deliver_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'cancel_time', title: __('Cancel_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            buttons: [
                                {
                                    name: 'deliver',
                                    text: __('Deliver'),
                                    classname: 'btn btn-xs btn-success btn-row-deliver',
                                    icon: 'fa fa-gift'
                                },
                                {
                                    name: 'ship',
                                    text: __('Ship'),
                                    classname: 'btn btn-xs btn-info btn-row-ship',
                                    icon: 'fa fa-truck'
                                },
                                {
                                    name: 'reject',
                                    text: __('Reject'),
                                    classname: 'btn btn-xs btn-danger btn-row-reject',
                                    icon: 'fa fa-ban'
                                }
                            ],
                            events: {
                                'click .btn-row-deliver': function (e, value, row) {
                                    Controller.api.promptAction('deliver', [row.id]);
                                    e.stopPropagation();
                                },
                                'click .btn-row-ship': function (e, value, row) {
                                    Controller.api.promptAction('ship', [row.id]);
                                    e.stopPropagation();
                                },
                                'click .btn-row-reject': function (e, value, row) {
                                    Controller.api.promptAction('reject', [row.id]);
                                    e.stopPropagation();
                                }
                            },
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            Table.api.bindevent(table);

            $(document).on('click', '.btn-batch-deliver', function () {
                var ids = Table.api.selectedids(table);
                if (!ids.length) {
                    Layer.msg(__('Please select at least one'));
                    return;
                }
                Controller.api.promptAction('deliver', ids);
            });
            $(document).on('click', '.btn-batch-ship', function () {
                var ids = Table.api.selectedids(table);
                if (!ids.length) {
                    Layer.msg(__('Please select at least one'));
                    return;
                }
                Controller.api.promptAction('ship', ids);
            });
            $(document).on('click', '.btn-batch-reject', function () {
                var ids = Table.api.selectedids(table);
                if (!ids.length) {
                    Layer.msg(__('Please select at least one'));
                    return;
                }
                Controller.api.promptAction('reject', ids);
            });
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            promptAction: function (action, ids) {
                ids = ids || [];
                var url = $.fn.bootstrapTable.defaults.extend[action + '_url'];
                if (!url) {
                    return;
                }

                if (action === 'reject') {
                    Layer.prompt({
                        title: __('Reject reason'),
                        formType: 2
                    }, function (value, index) {
                        if (!value) {
                            Layer.msg(__('Reject reason required'));
                            return false;
                        }
                        Backend.api.ajax({url: url, data: {ids: ids, reason: value}}, function () {
                            Layer.close(index);
                            $("#table").bootstrapTable('refresh');
                        });
                    });
                    return;
                }

                Layer.prompt({
                    title: action === 'deliver' ? __('Delivery content') : __('Shipping content'),
                    formType: 2
                }, function (value, index) {
                    if (!value) {
                        Layer.msg(__('Delivery content required'));
                        return false;
                    }
                    Backend.api.ajax({url: url, data: {ids: ids, delivery_content: value}}, function () {
                        Layer.close(index);
                        $("#table").bootstrapTable('refresh');
                    });
                });
            }
        }
    };

    return Controller;
});
