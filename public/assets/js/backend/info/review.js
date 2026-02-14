define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'info/review/index' + location.search,
                    approve_url: 'info/review/approve',
                    reject_url: 'info/review/reject',
                    table: 'info',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'category.name', title: __('Category_id'), operate: false},
                        {field: 'subcategory.name', title: __('Sub_category_id'), operate: false},
                        {field: 'title', title: __('Title'), operate: 'LIKE', align: 'left', formatter: function (value, row) {
                            return '<a href="javascript:;" class="btn-preview" data-id="' + row.id + '">' + value + '</a>';
                        }},
                        {field: 'contact_name', title: __('Contact_name')},
                        {field: 'contact_phone', title: __('Contact_phone')},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), width: '180px',
                            formatter: function (value, row, index) {
                                var html = [];
                                html.push('<a href="javascript:;" class="btn btn-xs btn-success btn-approve-one" data-id="' + row.id + '"><i class="fa fa-check"></i> ' + __('Approve') + '</a>');
                                html.push('<a href="javascript:;" class="btn btn-xs btn-danger btn-reject-one" data-id="' + row.id + '"><i class="fa fa-times"></i> ' + __('Reject') + '</a>');
                                return html.join(' ');
                            }
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 刷新按钮
            $(document).on('click', '.btn-refresh', function () {
                table.bootstrapTable('refresh');
            });

            // 批量通过
            $(document).on('click', '.btn-approve', function () {
                var ids = Table.api.selectedids(table);
                if (ids.length === 0) {
                    Layer.msg(__('Please select at least one'));
                    return;
                }
                Controller.api.doApprove(ids);
            });

            // 批量拒绝
            $(document).on('click', '.btn-reject', function () {
                var ids = Table.api.selectedids(table);
                if (ids.length === 0) {
                    Layer.msg(__('Please select at least one'));
                    return;
                }
                Controller.api.doReject(ids);
            });

            // 单个通过
            $(document).on('click', '.btn-approve-one', function () {
                var id = $(this).data('id');
                Controller.api.doApprove([id]);
            });

            // 单个拒绝
            $(document).on('click', '.btn-reject-one', function () {
                var id = $(this).data('id');
                Controller.api.doReject([id]);
            });

            // 选中行变化时启用/禁用批量按钮
            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function () {
                var ids = Table.api.selectedids(table);
                if (ids.length > 0) {
                    $('.btn-approve, .btn-reject').removeClass('disabled btn-disabled');
                } else {
                    $('.btn-approve, .btn-reject').addClass('disabled btn-disabled');
                }
            });

            // 预览信息（可选功能）
            $(document).on('click', '.btn-preview', function () {
                var id = $(this).data('id');
                Layer.alert('预览功能待实现，信息ID：' + id);
            });
        },
        api: {
            // 执行通过审核
            doApprove: function (ids) {
                Layer.confirm(__('Are you sure you want to approve?'), function (index) {
                    Backend.api.ajax({
                        url: $.fn.bootstrapTable.defaults.extend.approve_url,
                        data: {ids: ids}
                    }, function (ret) {
                        Layer.close(index);
                        Layer.msg(ret.msg);
                        $("#table").bootstrapTable('refresh');
                    });
                });
            },
            // 执行拒绝审核
            doReject: function (ids) {
                Layer.prompt({
                    title: __('Reject reason'),
                    formType: 2,
                    maxlength: 200
                }, function (value, index) {
                    if (!value || value.length < 2) {
                        Layer.msg('拒绝原因至少 2 个字符');
                        return false;
                    }
                    Backend.api.ajax({
                        url: $.fn.bootstrapTable.defaults.extend.reject_url,
                        data: {ids: ids, reason: value}
                    }, function (ret) {
                        Layer.close(index);
                        Layer.msg(ret.msg);
                        $("#table").bootstrapTable('refresh');
                    });
                });
            }
        }
    };
    return Controller;
});
