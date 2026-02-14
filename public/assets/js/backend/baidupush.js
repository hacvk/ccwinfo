define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Form.api.bindevent($("#form-daily"), function (data, ret) {
                var html = "状态：成功\n";
                html += JSON.stringify(data);
                $("#form-daily textarea[name=result]").val(html);
            }, function (data, ret) {
                var html = "状态：失败\n";
                html = html + "失败原因：" + ret.msg;
                $("#form-daily textarea[name=result]").val(html);
            });
            Form.api.bindevent($("#form-normal"), function (data, ret) {
                var html = "状态：成功\n";
                html += JSON.stringify(data);
                $("#form-normal textarea[name=result]").val(html);
            }, function (data, ret) {
                var html = "状态：失败\n";
                html = html + "失败原因：" + ret.msg;
                $("#form-normal textarea[name=result]").val(html);
            });
        },
    };
    return Controller;
});
