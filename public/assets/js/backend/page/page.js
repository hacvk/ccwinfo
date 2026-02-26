define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    'use strict';

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'page/page/index' + location.search,
                    add_url: 'page/page/add',
                    edit_url: 'page/page/edit',
                    del_url: 'page/page/del',
                    multi_url: 'page/page/multi',
                    table: 'single_page'
                }
            });

            var table = $('#table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'title', title: '页面标题', operate: 'LIKE', align: 'left'},
                    {field: 'parent_title', title: '上级页面', operate: false},
                    {
                        field: 'is_group',
                        title: '页面类型',
                        searchList: {'0': '内容页', '1': '分组页'},
                        formatter: function (value, row) {
                            return Number(value) === 1
                                ? '<span class="label label-info">分组页</span>'
                                : '<span class="label label-primary">内容页</span>';
                        }
                    },
                    {field: 'slug', title: 'URL 标识', operate: 'LIKE'},
                    {
                        field: 'status',
                        title: __('Status'),
                        searchList: Config.statusList,
                        formatter: function (value) {
                            var text = (Config.statusList && Config.statusList[value]) ? Config.statusList[value] : value;
                            var clazz = 'default';
                            if (value === 'published') {
                                clazz = 'success';
                            } else if (value === 'draft') {
                                clazz = 'warning';
                            }
                            return '<span class="label label-' + clazz + '">' + text + '</span>';
                        }
                    },
                    {
                        field: 'show_in_footer',
                        title: '页脚展示',
                        searchList: {'0': '否', '1': '是'},
                        formatter: Table.api.formatter.toggle
                    },
                    {field: 'views', title: '浏览量', operate: false},
                    {field: 'weigh', title: __('Weigh'), operate: false},
                    {field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {
                        field: 'operate',
                        title: __('Operate'),
                        table: table,
                        events: Table.api.events.operate,
                        formatter: Table.api.formatter.operate
                    }
                ]]
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
                var form = $('form[role=form]');
                Form.api.bindevent(form);

                $(document).on('change', "input[name='row[is_group]']", function () {
                    Controller.api.toggleByType();
                });

                var slugInput = $('#c-slug');
                if (slugInput.length && String(slugInput.data('locked')) === '1') {
                    slugInput.prop('readonly', true);
                }

                Controller.api.toggleByType();
            },
            toggleByType: function () {
                var isGroup = $("input[name='row[is_group]']:checked").val() === '1';
                $('.js-content-only').toggle(!isGroup);

                if (isGroup) {
                    $('#c-slug').val('');
                    $('#c-cover').val('');
                    $('#c-external_url').val('');
                    $("input[name='row[show_in_footer]'][value='0']").prop('checked', true);
                }
            }
        }
    };

    return Controller;
});
