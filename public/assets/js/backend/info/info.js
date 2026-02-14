define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'info/info/index' + location.search,
                    add_url: 'info/info/add',
                    edit_url: 'info/info/edit',
                    del_url: 'info/info/del',
                    multi_url: 'info/info/multi',
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
                        {field: 'title', title: __('Title'), operate: 'LIKE', align: 'left'},
                        {field: 'contact_name', title: __('Contact_name'), operate: 'LIKE'},
                        {field: 'contact_phone', title: __('Contact_phone'), operate: 'LIKE'},
                        {field: 'is_top', title: __('Is_top'), formatter: Table.api.formatter.toggle},
                        {field: 'views', title: __('Views')},
                        {field: 'status', title: __('Status'), searchList: Config.statusList, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
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
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.categoryChange();
            // 编辑时加载已有字段
            Controller.api.loadExtFields();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            // 一级分类联动二级分类 + 加载自定义字段
            categoryChange: function () {
                // 一级分类变化
                $(document).on('change', '#c-category_id', function () {
                    var pid = $(this).val();
                    var $sub = $('#c-sub_category_id');
                    $sub.html('<option value="">' + __('Please select') + '</option>');
                    $('#ext-fields-wrapper').hide();
                    $('#ext-fields-container').empty();

                    if (!pid) {
                        $sub.selectpicker('refresh');
                        return;
                    }
                    $.ajax({
                        url: 'info/info/getSubcategory',
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

                // 二级分类变化 -> 加载动态字段
                $(document).on('change', '#c-sub_category_id', function () {
                    Controller.api.loadExtFields();
                });
            },
            // 加载自定义字段
            loadExtFields: function () {
                var categoryId = $('#c-category_id').val();
                var subCategoryId = $('#c-sub_category_id').val();
                if (!categoryId || !subCategoryId) {
                    $('#ext-fields-wrapper').hide();
                    $('#ext-fields-container').empty();
                    return;
                }

                // 获取信息ID（编辑模式）
                var infoId = 0;
                var $form = $('form[role=form]');
                var action = $form.attr('action') || '';
                var match = action.match(/ids\/(\d+)/);
                if (match) {
                    infoId = parseInt(match[1]);
                }

                $.ajax({
                    url: 'info/info/getFields',
                    type: 'GET',
                    data: {category_id: categoryId, sub_category_id: subCategoryId, info_id: infoId},
                    dataType: 'json',
                    success: function (ret) {
                        if (ret.code === 1) {
                            Controller.api.renderExtFields(ret.data || []);
                        }
                    }
                });
            },
            // 渲染动态字段
            renderExtFields: function (fields) {
                var $container = $('#ext-fields-container');
                $container.empty();
                if (!fields || fields.length === 0) {
                    $('#ext-fields-wrapper').hide();
                    return;
                }
                $('#ext-fields-wrapper').show();

                $.each(fields, function (i, field) {
                    var html = Controller.api.buildFieldHtml(field);
                    $container.append(html);
                });
            },
            // 构建单个字段的 HTML
            buildFieldHtml: function (field) {
                var key = field.key;
                var name = 'ext[' + key + ']';
                var label = field.name + (field.required == 1 ? ' <span class="text-danger">*</span>' : '');
                var value = field.value || '';
                var placeholder = field.placeholder || '';
                var required = field.required == 1 ? 'data-rule="required"' : '';
                var html = '';

                html += '<div class="form-group">';
                html += '<label class="control-label col-xs-12 col-sm-2">' + label + ':</label>';
                html += '<div class="col-xs-12 col-sm-8">';

                switch (field.type) {
                    case 'textarea':
                        html += '<textarea class="form-control" name="' + name + '" rows="3" placeholder="' + placeholder + '" ' + required + '>' + Controller.api.escapeHtml(value) + '</textarea>';
                        break;
                    case 'select':
                        html += '<select class="form-control selectpicker" name="' + name + '" ' + required + '>';
                        html += '<option value="">请选择</option>';
                        if (field.options) {
                            $.each(field.options, function (j, opt) {
                                var selected = (value == opt) ? ' selected' : '';
                                html += '<option value="' + Controller.api.escapeHtml(opt) + '"' + selected + '>' + Controller.api.escapeHtml(opt) + '</option>';
                            });
                        }
                        html += '</select>';
                        break;
                    case 'radio':
                        if (field.options) {
                            $.each(field.options, function (j, opt) {
                                var checked = (value == opt) ? ' checked' : '';
                                html += '<label class="radio-inline"><input type="radio" name="' + name + '" value="' + Controller.api.escapeHtml(opt) + '"' + checked + '> ' + Controller.api.escapeHtml(opt) + '</label>';
                            });
                        }
                        break;
                    case 'checkbox':
                        var checkedValues = Array.isArray(value) ? value : (value ? value.split(',') : []);
                        if (field.options) {
                            $.each(field.options, function (j, opt) {
                                var checked = ($.inArray(opt, checkedValues) !== -1) ? ' checked' : '';
                                html += '<label class="checkbox-inline"><input type="checkbox" name="' + name + '[]" value="' + Controller.api.escapeHtml(opt) + '"' + checked + '> ' + Controller.api.escapeHtml(opt) + '</label>';
                            });
                        }
                        break;
                    case 'number':
                        html += '<input class="form-control" type="number" name="' + name + '" value="' + Controller.api.escapeHtml(value) + '" placeholder="' + placeholder + '" ' + required + '>';
                        break;
                    case 'date':
                        html += '<input class="form-control datetimepicker" data-date-format="YYYY-MM-DD" type="text" name="' + name + '" value="' + Controller.api.escapeHtml(value) + '" placeholder="' + placeholder + '" ' + required + '>';
                        break;
                    default: // text
                        html += '<input class="form-control" type="text" name="' + name + '" value="' + Controller.api.escapeHtml(value) + '" placeholder="' + placeholder + '" ' + required + '>';
                        break;
                }

                html += '</div>';
                html += '</div>';
                return html;
            },
            escapeHtml: function (str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
        }
    };
    return Controller;
});
