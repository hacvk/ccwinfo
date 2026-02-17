define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'guide/field/index' + location.search,
                    add_url: 'guide/field/add',
                    edit_url: 'guide/field/edit',
                    del_url: 'guide/field/del',
                    multi_url: 'guide/field/multi',
                    dragsort_url: 'ajax/weigh',
                    table: 'guide_field'
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'model.name', title: __('Model_id'), operate: false},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'key', title: __('Key'), operate: 'LIKE'},
                        {
                            field: 'type', title: __('Type'), searchList: Config.typeList, formatter: function (value) {
                                return Config.typeList[value] || value;
                            }
                        },
                        {field: 'required', title: __('Required'), formatter: Table.api.formatter.toggle},
                        {field: 'is_list_show', title: __('Is_list_show'), formatter: Table.api.formatter.toggle},
                        {field: 'is_detail_show', title: __('Is_detail_show'), formatter: Table.api.formatter.toggle},
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

            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.typeChange();
            Controller.api.toggleOptions();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.typeChange();
            Controller.api.toggleOptions();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
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

