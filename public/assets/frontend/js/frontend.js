define(['jquery', 'frontend'], function ($, FA) {
    'use strict';

    // FA 是 FastAdmin 内置的 Frontend 对象，此时 window.Frontend 已被设置
    var Frontend = window.Frontend || FA || {};

    var token = $('meta[name="csrf-token"]').attr('content') || '';
    if (token) {
        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': token}
        });
    }

    if (typeof Frontend.alert !== 'function') {
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
    }

    if (typeof Frontend.post !== 'function') {
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
    }

    if (typeof Frontend.upload !== 'function') {
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
    }

    return Frontend;
});
