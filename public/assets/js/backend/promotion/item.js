define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'promotion/item/index' + location.search,
                    add_url: 'promotion/item/add',
                    edit_url: 'promotion/item/edit',
                    del_url: 'promotion/item/del',
                    multi_url: 'promotion/item/multi',
                    review_url: 'promotion/item/review',
                    table: 'promotion_item',
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
                        {field: 'id', title: __('Id')},
                        {field: 'slot.name', title: '展示位', operate: false},
                        {field: 'title', title: '标题/链接文字', operate: 'LIKE', align: 'left'},
                        {field: 'page_scope', title: '范围', searchList: {all: '全站', home: '首页', category: '栏目页', channel: '频道页'}, formatter: function (value) {
                            var map = {all: '全站', home: '首页', category: '栏目页', channel: '频道页'};
                            return map[value] || value;
                        }},
                        {field: 'status', title: __('Status'), searchList: Config.statusList, formatter: Table.api.formatter.status},
                        {field: 'review_status_text', title: '审核状态', operate: false},
                        {field: 'start_time', title: '开始时间', operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'end_time', title: '结束时间', operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'sort', title: '排序', operate: false},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'review',
                                    title: '审核',
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-check-circle',
                                    url: $.fn.bootstrapTable.defaults.extend.review_url,
                                    visible: function (row) {
                                        return row.review_status === 'pending';
                                    }
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
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
