define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'info/field/index' + location.search,
                    add_url: 'info/field/add',
                    edit_url: 'info/field/edit',
                    del_url: 'info/field/del',
                    multi_url: 'info/field/multi',
                    dragsort_url: 'ajax/weigh',
                    table: 'info_field',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'category.name', title: __('Category_id'), operate: false},
                        {field: 'subcategory.name', title: __('Sub_category_id'), operate: false, formatter: function (value) {
                            return value || __('All subcategories');
                        }},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'key', title: __('Key'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: Config.typeList, formatter: function (value) {
                            return Config.typeList[value] || value;
                        }},
                        {field: 'required', title: __('Required'), formatter: Table.api.formatter.toggle},
                        {field: 'is_filter', title: __('Is_filter'), formatter: Table.api.formatter.toggle},
                        {field: 'is_list_show', title: __('Is_list_show'), formatter: Table.api.formatter.toggle},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), searchList: {"normal": __('Normal'), "hidden": __('Hidden')}, formatter: Table.api.formatter.status},
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.categoryChange();
            Controller.api.typeChange();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.categoryChange();
            Controller.api.typeChange();
            // 编辑时根据当前 type 显示/隐藏 options
            Controller.api.toggleOptions();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            // 一级分类联动二级分类
            categoryChange: function () {
                $(document).on('change', '#c-category_id', function () {
                    var pid = $(this).val();
                    var $sub = $('#c-sub_category_id');
                    $sub.html('<option value="0">' + __('All subcategories') + '</option>');
                    if (!pid) {
                        $sub.selectpicker('refresh');
                        return;
                    }
                    $.ajax({
                        url: 'info/field/getSubcategory',
                        type: 'GET',
                        data: {pid: pid},
                        dataType: 'json',
                        success: function (ret) {
                            if (ret.code === 1 && ret.data) {
                                $.each(ret.data, function (i, item) {
                                    $sub.append('<option value="' + item.id + '">' + item.name + '</option>');
                                });
                            }
                            $sub.selectpicker('refresh');
                        }
                    });
                });
            },
            // 字段类型切换时显示/隐藏选项配置
            typeChange: function () {
                $(document).on('change', '#c-type', function () {
                    Controller.api.toggleOptions();
                });
            },
            toggleOptions: function () {
                var type = $('#c-type').val();
                var needOptions = ['select', 'radio', 'checkbox'];
                if ($.inArray(type, needOptions) !== -1) {
                    $('#options-group').show();
                } else {
                    $('#options-group').hide();
                }
            }
        }
    };
    return Controller;
});
