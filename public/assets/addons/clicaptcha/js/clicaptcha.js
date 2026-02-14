/**
 * Clicaptcha
 * https://gitee.com/hooray/clicaptcha
 */
(function ($) {
    $.fn.extend({
        'clicaptcha': function (options) {
            var opts = $.extend({}, defaluts, options);
            var $this = this;
            var decodeURIComponentSafe = function (uri) {
                try {
                    uri = decodeURIComponent(uri);
                } catch (e) {
                    var uriArr = uri.split(/;/);
                    var resultArr = [];
                    for (var i = 0; i < uriArr.length; i++) {
                        try {
                            resultArr[i] = decodeURIComponent(uriArr[i]);
                        } catch (err) {
                            resultArr[i] = uriArr[i];
                        }
                    }
                    uri = resultArr.join(';');
                }
                return uri;
            };
            var getCookie = function (name) {
                var value = "; " + decodeURIComponentSafe(document.cookie);
                var parts = value.split("; " + name + "=");
                if (parts.length === 2) return parts.pop().split(";").shift();
                return '';
            };
            if (!$('#clicaptcha-box').length) {
                $('body').append('<div id="clicaptcha-box">' +
                    '<img class="clicaptcha-img" src="" alt="验证码加载失败，请点击刷新按钮">' +
                    '<div class="clicaptcha-cover"></div>' +
                    '<div class="clicaptcha-title"></div>' +
                    '<div class="clicaptcha-refresh-box">' +
                    '<div class="clicaptcha-refresh-line clicaptcha-refresh-line-left"></div>' +
                    '<a href="javascript:;" class="clicaptcha-refresh-btn" title="刷新"></a>' +
                    '<div class="clicaptcha-refresh-line clicaptcha-refresh-line-right"></div>' +
                    '</div>' +
                    '</div>');
                $('body').append('<div id="clicaptcha-mask"></div>');
                $('#clicaptcha-mask').click(function () {
                    $('#clicaptcha-box').hide();
                    $(this).hide();
                });
                $('#clicaptcha-box .clicaptcha-refresh-btn').click(function () {
                    $("#clicaptcha-box .clicaptcha-tag").remove();
                    $this.clicaptcha(opts);
                });

            }
            var clicaptchaBox = $('#clicaptcha-box');
            var clicaptchaTitle = $('.clicaptcha-title', clicaptchaBox);
            var clicaptchaCover = $(".clicaptcha-cover", clicaptchaBox);

            $('.clicaptcha-tag', clicaptchaBox).remove();
            $('#clicaptcha-box, #clicaptcha-mask').show();
            $('.clicaptcha-img', clicaptchaBox).attr('src', opts.src + '?r=' + new Date().getTime()).on("load", function () {
                var imgWidth = $(this).width();
                var imgHeight = $(this).height();
                clicaptchaCover.css({
                    width: imgWidth + 'px',
                    height: imgHeight + 'px'
                });
                var text = getCookie('clicaptcha_text' + (opts.id || '')).split(',');
                var title = '请依次点击 ';
                var t = [];
                for (var i = 0; i < text.length; i++) {
                    t.push('<span>' + text[i] + '</span>');
                }
                title += t.join('、');
                clicaptchaTitle.html(title);
                var xyArr = [];
                clicaptchaCover.off('mousedown').on('mousedown', function (e) {
                    e.preventDefault();
                    clicaptchaCover.off('mouseup').on('mouseup', function (e) {
                        $('span:eq(' + xyArr.length + ')', clicaptchaTitle).addClass('clicaptcha-clicked');
                        var x = ($(document).scrollLeft() + e.clientX - $(this).offset().left);
                        var y = ($(document).scrollTop() + e.clientY - $(this).offset().top);
                        xyArr.push(x + ',' + y);
                        $("<div />").addClass("clicaptcha-tag").text(xyArr.length).attr("data-index", xyArr.length).css({left: (x + 3) + "px", top: (y + 3) + "px"}).appendTo("#clicaptcha-box");
                        if (xyArr.length === text.length) {
                            var captchainfo = [xyArr.join('-'), imgWidth, imgHeight].join(';');
                            $.ajax({
                                type: 'POST',
                                url: opts.src,
                                xhrFields: {
                                    withCredentials: true
                                },
                                data: {
                                    do: 'check',
                                    id: opts.id || '',
                                    info: captchainfo
                                }
                            }).done(function (cb) {
                                if (cb == 1) {
                                    $this.val(captchainfo).data('ischeck', true);
                                    clicaptchaTitle.html(opts.success_tip);
                                    setTimeout(function () {
                                        $('#clicaptcha-box, #clicaptcha-mask').hide();
                                        opts.callback(captchainfo);
                                    }, 1000);
                                } else {
                                    clicaptchaTitle.html(opts.error_tip);
                                    setTimeout(function () {
                                        $this.clicaptcha(opts);
                                    }, 1000);
                                }
                            });
                        }
                    });
                });
                clicaptchaBox.off("click", ".clicaptcha-tag").on("click", ".clicaptcha-tag", function () {
                    var index = parseInt($(this).data("index"));
                    var nums = $(".clicaptcha-tag", clicaptchaBox).length;
                    if (nums > index) {
                        $(".clicaptcha-tag[data-index='" + (index + 1) + "']", clicaptchaBox).trigger("click");
                    }
                    $(this).remove();
                    xyArr.pop();
                    $(".clicaptcha-clicked:last", clicaptchaBox).removeClass("clicaptcha-clicked");
                });
            });
            return this;
        },
        'clicaptchaCheck': function () {
            var ischeck = false;
            if (this.data('ischeck') == true) {
                ischeck = true;
            }
            return ischeck;
        },
        'clicaptchaReset': function () {
            this.val('').removeData('ischeck');
            $("#clicaptcha-box .clicaptcha-tag").remove();
            return this;
        }
    });
    //默认参数
    var defaluts = {
        src: 'clicaptcha/clicaptcha.php',
        id: '',
        success_tip: '验证成功！',
        error_tip: '未点中正确区域，请重试！',
        callback: function () {
        }
    };
})(jQuery);
