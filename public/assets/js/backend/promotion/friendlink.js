define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'promotion/friendlink/index' + location.search,
                    add_url: 'promotion/friendlink/add',
                    edit_url: 'promotion/friendlink/edit',
                    del_url: 'promotion/friendlink/del',
                    multi_url: 'promotion/friendlink/multi',
                    table: 'promotion_item',
                }
            });

            var table = $('#table');

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'sort',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: '网站名称', operate: 'LIKE'},
                        {field: 'link_url', title: '链接URL', operate: 'LIKE'},
                        {field: 'sort', title: '排序', operate: false},
                        {field: 'status', title: '状态', searchList: {online: '启用', offline: '停用'}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
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
