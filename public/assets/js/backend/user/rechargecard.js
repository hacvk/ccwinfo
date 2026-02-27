define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'user/rechargecard/index' + location.search,
                    add_url: 'user/rechargecard/add',
                    edit_url: 'user/rechargecard/edit',
                    del_url: 'user/rechargecard/del',
                    multi_url: 'user/rechargecard/multi',
                    generate_url: 'user/rechargecard/generate',
                    export_url: 'user/rechargecard/export',
                    table: 'recharge_card'
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'card_type', title: __('Card_type'), searchList: Config.cardTypeList, formatter: Table.api.formatter.normal},
                        {field: 'card_no', title: __('Card_no'), operate: 'LIKE'},
                        {field: 'card_secret', title: __('Card_secret'), operate: false},
                        {field: 'face_amount', title: __('Face_amount'), operate: 'BETWEEN'},
                        {field: 'bonus_rate', title: __('Bonus_rate'), operate: 'BETWEEN'},
                        {field: 'min_recharge', title: __('Min_recharge'), operate: 'BETWEEN'},
                        {field: 'max_bonus', title: __('Max_bonus'), operate: 'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: Config.statusList, formatter: Table.api.formatter.status},
                        {field: 'batch_no', title: __('Batch_no'), operate: 'LIKE'},
                        {field: 'owner_user_id', title: __('Owner_user_id'), operate: 'LIKE'},
                        {field: 'reserve_orderid', title: __('Reserve_orderid'), operate: 'LIKE'},
                        {field: 'used_user_id', title: __('Used_user_id'), operate: 'LIKE'},
                        {field: 'used_orderid', title: __('Used_orderid'), operate: 'LIKE'},
                        {field: 'expiretime', title: __('Expiretime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'used_time', title: __('Used_time'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);

            $(document).on('click', '.btn-generate', function () {
                Controller.api.openGenerateDialog();
            });

            $(document).on('click', '.btn-export-card', function () {
                var ids = Table.api.selectedids(table);
                var url = $.fn.bootstrapTable.defaults.extend.export_url;
                if (ids.length) {
                    url += '?ids=' + ids.join(',');
                }
                window.open(url);
            });
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
            },
            openGenerateDialog: function () {
                var html = [
                    '<form id="gen-card-form" class="form-horizontal" style="padding:15px 15px 0;">',
                    '<div class="form-group"><label class="control-label col-xs-4">卡类型</label><div class="col-xs-8"><select class="form-control" name="card_type"><option value="recharge">充值卡</option><option value="discount">优惠卡</option></select></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">生成数量</label><div class="col-xs-8"><input class="form-control" name="count" type="number" min="1" max="5000" value="10"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">面额</label><div class="col-xs-8"><input class="form-control" name="face_amount" type="number" step="0.01" value="100"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">加赠比例</label><div class="col-xs-8"><input class="form-control" name="bonus_rate" type="number" step="0.0001" value="0.1000"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">最低充值</label><div class="col-xs-8"><input class="form-control" name="min_recharge" type="number" step="0.01" value="100"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">封顶加赠</label><div class="col-xs-8"><input class="form-control" name="max_bonus" type="number" step="0.01" value="50"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">过期时间</label><div class="col-xs-8"><input class="form-control" name="expiretime" type="text" placeholder="YYYY-MM-DD HH:mm:ss"></div></div>',
                    '<div class="form-group"><label class="control-label col-xs-4">批次号</label><div class="col-xs-8"><input class="form-control" name="batch_no" type="text" placeholder="留空自动生成"></div></div>',
                    '</form>'
                ].join('');

                Layer.open({
                    type: 1,
                    title: __('Generate cards'),
                    area: ['520px', '560px'],
                    content: html,
                    btn: [__('Submit'), __('Cancel')],
                    yes: function (index) {
                        var data = {};
                        $('#gen-card-form').serializeArray().forEach(function (item) {
                            data[item.name] = item.value;
                        });
                        Backend.api.ajax({
                            url: $.fn.bootstrapTable.defaults.extend.generate_url,
                            data: data
                        }, function () {
                            Layer.close(index);
                            $("#table").bootstrapTable('refresh');
                        });
                    }
                });
            }
        }
    };

    return Controller;
});

