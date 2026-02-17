define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'guide/guide/index' + location.search,
                    add_url: 'guide/guide/add',
                    edit_url: 'guide/guide/edit',
                    del_url: 'guide/guide/del',
                    multi_url: 'guide/guide/multi',
                    table: 'guide'
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
                        {field: 'title', title: __('Title'), operate: 'LIKE', align: 'left'},
                        {field: 'category_name', title: __('Category_id'), operate: false},
                        {field: 'model_name', title: __('Model_id'), operate: false},
                        {field: 'tag_names', title: 'Tags', operate: 'LIKE', visible: false},
                        {
                            field: 'is_external',
                            title: __('Is_external'),
                            searchList: {'0': __('No'), '1': __('Yes')},
                            formatter: function (value) {
                                return parseInt(value, 10) === 1 ? '<span class="label label-info">' + __('Yes') + '</span>' : '<span class="label label-default">' + __('No') + '</span>';
                            }
                        },
                        {field: 'status', title: __('Status'), searchList: Config.statusList, formatter: Table.api.formatter.status},
                        {field: 'publish_time', title: __('Publish_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'views', title: __('Views'), operate: false},
                        {field: 'is_top', title: __('Is_top'), formatter: Table.api.formatter.toggle},
                        {field: 'weigh', title: __('Weigh'), operate: false},
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
            Controller.api.bindModelChange();
            Controller.api.bindExternalLogic();
            Controller.api.loadExtFields();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.bindModelChange();
            Controller.api.bindExternalLogic();
            Controller.api.loadExtFields();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($('form[role=form]'));
            },
            bindModelChange: function () {
                $(document).on('change', '#c-model_id', function () {
                    Controller.api.loadExtFields();
                });
            },
            bindExternalLogic: function () {
                $(document).on('change keyup', '#c-source_url', function () {
                    var hasUrl = $.trim($(this).val()) !== '';
                    if (hasUrl) {
                        $("input[name='row[is_external]'][value='1']").prop('checked', true).trigger('change');
                    }
                });
            },
            getGuideId: function () {
                var form = $('form[role=form]');
                var action = form.attr('action') || '';
                var actionMatch = action.match(/ids\/(\d+)/);
                if (actionMatch) {
                    return parseInt(actionMatch[1], 10) || 0;
                }
                var queryMatch = location.search.match(/[?&]ids=(\d+)/);
                return queryMatch ? (parseInt(queryMatch[1], 10) || 0) : 0;
            },
            loadExtFields: function () {
                var modelId = $('#c-model_id').val();
                if (!modelId) {
                    $('#ext-fields-wrapper').hide();
                    $('#ext-fields-container').empty();
                    return;
                }
                $.ajax({
                    url: 'guide/guide/getFields',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        model_id: modelId,
                        guide_id: Controller.api.getGuideId()
                    },
                    success: function (ret) {
                        if (ret.code === 1) {
                            Controller.api.renderExtFields(ret.data || []);
                        }
                    }
                });
            },
            renderExtFields: function (fields) {
                var container = $('#ext-fields-container');
                container.empty();
                if (!fields || fields.length === 0) {
                    $('#ext-fields-wrapper').hide();
                    return;
                }

                $.each(fields, function (idx, field) {
                    container.append(Controller.api.buildFieldHtml(field));
                });
                $('#ext-fields-wrapper').show();
                Form.events.selectpicker($('form[role=form]'));
                Form.events.datetimepicker($('form[role=form]'));
            },
            buildFieldHtml: function (field) {
                var key = field.key || '';
                var type = field.type || 'text';
                var label = Controller.api.escapeHtml(field.name || key);
                var placeholder = Controller.api.escapeHtml(field.placeholder || '');
                var required = parseInt(field.required, 10) === 1 ? 'data-rule="required"' : '';
                var name = 'ext[' + key + ']';
                var value = field.value;
                var options = $.isArray(field.options) ? field.options : [];

                var html = '<div class="form-group">';
                html += '<label class="control-label col-xs-12 col-sm-2">' + label + (required ? ' <span class="text-danger">*</span>' : '') + ':</label>';
                html += '<div class="col-xs-12 col-sm-8">';

                if (type === 'textarea') {
                    html += '<textarea class="form-control" rows="3" name="' + name + '" placeholder="' + placeholder + '" ' + required + '>' + Controller.api.escapeHtml(value || '') + '</textarea>';
                } else if (type === 'select') {
                    html += '<select class="form-control selectpicker" name="' + name + '" ' + required + '>';
                    html += '<option value="">' + __('Please select') + '</option>';
                    $.each(options, function (i, opt) {
                        var selected = (String(value) === String(opt)) ? ' selected' : '';
                        html += '<option value="' + Controller.api.escapeHtml(opt) + '"' + selected + '>' + Controller.api.escapeHtml(opt) + '</option>';
                    });
                    html += '</select>';
                } else if (type === 'radio') {
                    $.each(options, function (i, opt) {
                        var checked = (String(value) === String(opt)) ? ' checked' : '';
                        html += '<label class="radio-inline"><input type="radio" name="' + name + '" value="' + Controller.api.escapeHtml(opt) + '"' + checked + '> ' + Controller.api.escapeHtml(opt) + '</label>';
                    });
                } else if (type === 'checkbox') {
                    var checkedValues = $.isArray(value) ? value : ((value || '').toString().split(','));
                    $.each(options, function (i, opt) {
                        var checked = $.inArray(String(opt), $.map(checkedValues, function (v) {
                            return String($.trim(v));
                        })) !== -1 ? ' checked' : '';
                        html += '<label class="checkbox-inline"><input type="checkbox" name="' + name + '[]" value="' + Controller.api.escapeHtml(opt) + '"' + checked + '> ' + Controller.api.escapeHtml(opt) + '</label>';
                    });
                } else if (type === 'number') {
                    html += '<input class="form-control" type="number" name="' + name + '" value="' + Controller.api.escapeHtml(value || '') + '" placeholder="' + placeholder + '" ' + required + '>';
                } else if (type === 'date') {
                    html += '<input class="form-control datetimepicker" data-date-format="YYYY-MM-DD" type="text" name="' + name + '" value="' + Controller.api.escapeHtml(value || '') + '" placeholder="' + placeholder + '" ' + required + '>';
                } else {
                    html += '<input class="form-control" type="text" name="' + name + '" value="' + Controller.api.escapeHtml(value || '') + '" placeholder="' + placeholder + '" ' + required + '>';
                }

                html += '</div></div>';
                return html;
            },
            escapeHtml: function (str) {
                if (str === undefined || str === null) {
                    return '';
                }
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
        }
    };

    return Controller;
});
