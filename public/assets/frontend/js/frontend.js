(function ($) {
    'use strict';

    window.Frontend = window.Frontend || {};

    var token = $('meta[name="csrf-token"]').attr('content') || '';
    if (token) {
        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': token}
        });
    }

    Frontend.alert = function (message, type, duration) {
        var kind = type || 'info';
        var timeout = duration || 3000;
        var html = [
            '<div class="alert alert-' + kind + ' alert-dismissible fade in" role="alert">',
            '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>',
            message,
            '</div>'
        ].join('');

        var $el = $(html);
        $('body').append($el);
        setTimeout(function () {
            $el.fadeOut(200, function () {
                $el.remove();
            });
        }, timeout);
    };

    Frontend.post = function (url, data, callback) {
        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (typeof callback === 'function') callback(res || {});
            },
            error: function () {
                Frontend.alert('请求失败，请稍后重试', 'danger');
            }
        });
    };

    Frontend.upload = function (url, file, fieldName, callback) {
        var fd = new FormData();
        fd.append(fieldName || 'image', file);

        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (typeof callback === 'function') callback(res || {});
            },
            error: function () {
                Frontend.alert('上传失败，请稍后重试', 'danger');
            }
        });
    };
})(jQuery);
