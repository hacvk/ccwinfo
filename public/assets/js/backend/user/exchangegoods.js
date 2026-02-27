define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'user/exchangegoods/index' + location.search,
                    add_url: 'user/exchangegoods/add',
                    edit_url: 'user/exchangegoods/edit',
                    del_url: 'user/exchangegoods/del',
                    multi_url: 'user/exchangegoods/multi',
                    table: 'exchange_goods'
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'goods_type', title: __('Goods_type'), searchList: Config.goodsTypeList, formatter: Table.api.formatter.normal},
                        {field: 'virtual_mode', title: __('Virtual_mode'), searchList: Config.virtualModeList, formatter: Table.api.formatter.normal},
                        {field: 'virtual_source', title: __('Virtual_source'), searchList: Config.virtualSourceList, formatter: Table.api.formatter.normal},
                        {field: 'points_cost', title: __('Points_cost'), operate: 'BETWEEN'},
                        {field: 'stock', title: __('Stock'), operate: 'BETWEEN'},
                        {field: 'locked_stock', title: __('Locked_stock'), operate: false},
                        {field: 'per_user_limit', title: __('Per_user_limit'), operate: false},
                        {
                            field: 'need_shipping',
                            title: __('Need_shipping'),
                            searchList: {'0': __('No'), '1': __('Yes')},
                            formatter: function (value) {
                                return Number(value) === 1 ? __('Yes') : __('No');
                            }
                        },
                        {field: 'status', title: __('Status'), searchList: Config.statusList, formatter: Table.api.formatter.status},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'start_time', title: __('Start_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'end_time', title: __('End_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime, visible: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
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
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };

    return Controller;
});
