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
                    review_url: 'promotion/friendlink/review',
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
                        {field: 'image', title: 'LOGO', operate: false, formatter: Table.api.formatter.image},
                        {field: 'sort', title: '排序', operate: false},
                        {field: 'status', title: '状态', searchList: {online: '启用', offline: '停用'}, formatter: Table.api.formatter.status},
                        {field: 'review_status_text', title: '审核状态', searchList: {'pending': '待审核', 'approved': '已通过', 'rejected': '已拒绝'}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'review',
                                    text: function (row) {
                                        if (row.review_status === 'pending') {
                                            return '审核';
                                        } else if (row.review_status === 'rejected') {
                                            return '重新审核';
                                        } else {
                                            return '查看详情';
                                        }
                                    },
                                    classname: function (row) {
                                        if (row.review_status === 'pending') {
                                            return 'btn btn-xs btn-info btn-dialog';
                                        } else if (row.review_status === 'rejected') {
                                            return 'btn btn-xs btn-warning btn-dialog';
                                        } else {
                                            return 'btn btn-xs btn-default btn-dialog';
                                        }
                                    },
                                    icon: 'fa fa-check-circle',
                                    url: 'promotion/friendlink/review',
                                    visible: function (row) {
                                        return true; // 总是显示审核按钮
                                    }
                                },
                                {
                                    name: 'edit',
                                    text: __('Edit'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-pencil',
                                    url: 'promotion/friendlink/edit'
                                },
                                {
                                    name: 'del',
                                    text: __('Del'),
                                    classname: 'btn btn-xs btn-danger btn-ajax',
                                    icon: 'fa fa-trash',
                                    url: 'promotion/friendlink/del',
                                    confirm: __('Are you sure you want to delete this item?')
                                }
                            ],
                            events: $.extend({}, Table.api.events.operate, {
                                'click .btn-review': function (e, value, row, index) {
                                    e.stopPropagation();
                                    Fast.api.open($.fn.bootstrapTable.defaults.extend.review_url + '/ids/' + row[Table.pk], __('Review'), $(this).data() || {});
                                }
                            }),
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
