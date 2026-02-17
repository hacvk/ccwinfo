define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'guide/category/index',
                    add_url: 'guide/category/add',
                    edit_url: 'guide/category/edit',
                    del_url: 'guide/category/del',
                    multi_url: 'guide/category/multi',
                    dragsort_url: 'ajax/weigh',
                    table: 'guide_category'
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                pagination: false,
                commonSearch: false,
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'name', title: __('Name'), align: 'left', formatter: function (value) {
                                return value.toString().replace(/(&|&amp;)nbsp;/g, '&nbsp;');
                            }
                        },
                        {field: 'slug', title: __('Slug')},
                        {field: 'icon', title: __('Icon')},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
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

