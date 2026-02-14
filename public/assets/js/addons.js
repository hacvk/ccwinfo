define([], function () {
    require.config({
    paths: {
        'clicaptcha': '../addons/clicaptcha/js/clicaptcha'
    },
    shim: {
        'clicaptcha': {
            deps: [
                'jquery',
                'css!../addons/clicaptcha/css/clicaptcha.css'
            ],
            exports: '$.fn.clicaptcha'
        }
    }
});

require(['form'], function (Form) {
    window.clicaptcha = function (captcha) {
        require(['clicaptcha'], function (undefined) {
            captcha = captcha ? captcha : $("input[name=captcha]");
            if (captcha.length > 0) {
                var form = captcha.closest("form");
                var parentDom = captcha.parent();
                // 非文本验证码
                if ($("a[data-event][data-url]", parentDom).length > 0) {
                    return;
                }
                if (captcha.parentsUntil(form, "div.form-group").length > 0) {
                    captcha.parentsUntil(form, "div.form-group").addClass("hidden");
                } else if (parentDom.is("div.input-group")) {
                    parentDom.addClass("hidden");
                }
                captcha.attr("data-rule", "required");
                // 验证失败时进行操作
                captcha.on('invalid.field', function (e, result, me) {
                    //必须删除errors对象中的数据，否则会出现Layer的Tip
                    delete me.errors['captcha'];
                    let id = captcha.attr("data-id") || '';
                    captcha.clicaptcha({
                        src: '/addons/clicaptcha/index/start/id/' + id,
                        id: id,
                        success_tip: '验证成功！',
                        error_tip: '未点中正确区域，请重试！',
                        callback: function (captchainfo) {
                            form.trigger("submit");
                            return false;
                        }
                    });
                });
                // 监听表单错误事件
                form.on("error.form", function (e, data) {
                    captcha.val('');
                });
            }
        });
    };
    // clicaptcha($("input[name=captcha]"));

    if (typeof Frontend !== 'undefined') {
        Frontend.api.preparecaptcha = function (btn, type, data) {
            require(['form'], function (Form) {
                $("#clicaptchacontainer").remove();
                $("<div />").attr("id", "clicaptchacontainer").addClass("hidden").html(Template("captchatpl", {})).appendTo("body");
                var form = $("#clicaptchacontainer form");
                form.data("validator-options", {
                    valid: function (ret) {
                        data.captcha = $("input[name=captcha]", form).val();
                        Frontend.api.sendcaptcha(btn, type, data, function (data, ret) {
                            console.log("ok");
                        });
                        return true;
                    }
                })
                Form.api.bindevent(form);
            });
        };
    }

    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        var captchaObj = $("input[name=captcha]", form);
        if (captchaObj.length > 0) {
            captchaObj.closest("form").find("button[type=submit]").removeAttr("disabled");
            clicaptcha(captchaObj);
            if ($(form).attr("name") === 'captcha-form') {
                setTimeout(function () {
                    captchaObj.trigger("invalid.field", [{key: 'captcha'}, {errors: {}}]);
                }, 100);
            }
        }
    }
});

require.config({
    paths: {
        'nkeditor': '../addons/nkeditor/js/customplugin',
        'nkeditor-core': '../addons/nkeditor/nkeditor',
        'nkeditor-lang': '../addons/nkeditor/lang/zh-CN',
        'dompurify': '../addons/nkeditor/js/dompurify',
    },
    shim: {
        'nkeditor': {
            deps: [
                'nkeditor-core',
                'nkeditor-lang'
            ]
        },
        'nkeditor-core': {
            deps: [
                'css!../addons/nkeditor/themes/black/editor.min.css',
                'css!../addons/nkeditor/css/common.css'
            ],
            exports: 'window.KindEditor'
        },
        'nkeditor-lang': {
            deps: [
                'nkeditor-core'
            ]
        }
    }
});
require(['form'], function (Form) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        if ($(Config.nkeditor.classname || '.editor', form).length > 0) {
            require(['nkeditor', 'upload', 'dompurify'], function (Nkeditor, Upload, DOMPurify) {
                var getFileFromBase64, uploadFiles;
                uploadFiles = async function (files, options) {
                    var self = this;
                    for (var i = 0; i < files.length; i++) {
                        try {
                            await new Promise((resolve) => {
                                var url, html, file;
                                file = files[i];
                                Upload.api.send(file, function (data) {
                                    url = Config.nkeditor.fullmode ? Fast.api.cdnurl(data.url, true) : Fast.api.cdnurl(data.url);
                                    if (file.type.indexOf("image") !== -1) {
                                        self.exec("insertimage", url);
                                    } else {
                                        html = '<a class="ke-insertfile" href="' + url + '" data-ke-src="' + url + '" target="_blank">' + (file.name || url) + '</a>';
                                        self.exec("inserthtml", html);
                                    }
                                    resolve();
                                }, function () {
                                    resolve();
                                }, undefined, options || {});
                            });
                        } catch (e) {

                        }
                    }
                };
                getFileFromBase64 = function (data, url) {
                    var arr = data.split(','), mime = arr[0].match(/:(.*?);/)[1],
                        bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
                    while (n--) {
                        u8arr[n] = bstr.charCodeAt(n);
                    }
                    var filename, suffix;
                    if (typeof url != 'undefined') {
                        var urlArr = url.split('.');
                        filename = url.substr(url.lastIndexOf('/') + 1);
                        suffix = urlArr.pop();
                    } else {
                        filename = Math.random().toString(36).substring(5, 15);
                    }
                    if (!suffix) {
                        suffix = data.substring("data:image/".length, data.indexOf(";base64"));
                    }

                    var exp = new RegExp("\\." + suffix + "$", "i");
                    filename = exp.test(filename) ? filename : filename + "." + suffix;
                    var file = new File([u8arr], filename, {type: mime});
                    return file;
                };

                // 添加 hook 过滤 iframe 来源
                DOMPurify.addHook('uponSanitizeElement', function (node, data, config) {
                    if (data.tagName === 'iframe') {
                        var allowedIframePrefixes = Config.nkeditor.allowiframeprefixs || [];
                        var src = node.getAttribute('src');

                        // 判断是否匹配允许的前缀
                        var isAllowed = false;
                        for (var i = 0; i < allowedIframePrefixes.length; i++) {
                            if (src && src.indexOf(allowedIframePrefixes[i]) === 0) {
                                isAllowed = true;
                                break;
                            }
                        }

                        if (!isAllowed) {
                            // 不符合要求则移除该节点
                            return node.parentNode.removeChild(node);
                        }

                        // 添加安全属性
                        node.setAttribute('allowfullscreen', '');
                        node.setAttribute('allow', 'fullscreen');
                    }
                });

                if (Config.nkeditor.isdompurify) {
                    var purifyOptions = {
                        ADD_TAGS: ['iframe'],
                        FORCE_REJECT_IFRAME: false
                    };
                    Nkeditor.plugin('dompurify', function (K) {
                        this.handler('beforeGetHtml', function (html) {
                            return DOMPurify.sanitize(html, purifyOptions);
                        });
                        this.handler('beforeSetHtml', function (html) {
                            return DOMPurify.sanitize(html, purifyOptions);
                        });
                    });
                }
                $(Config.nkeditor.classname || '.editor', form).each(function () {
                    var that = this;
                    var options = $(this).data("nkeditor-options");
                    var editor = Nkeditor.create(that, $.extend({}, {
                        width: '100%',
                        filterMode: false,
                        wellFormatMode: false,
                        allowMediaUpload: true, //是否允许媒体上传
                        allowFileManager: true,
                        allowImageUpload: true,
                        baiduMapKey: Config.nkeditor.baidumapkey || '',
                        baiduMapCenter: Config.nkeditor.baidumapcenter || '',
                        fontSizeTable: ['9px', '10px', '12px', '14px', '16px', '18px', '21px', '24px', '32px'],
                        formulaPreviewUrl: typeof Config.nkeditor != 'undefined' && Config.nkeditor.formulapreviewurl ? Config.nkeditor.formulapreviewurl : "", //数学公式的预览地址
                        cssPath: Config.site.cdnurl + '/assets/addons/nkeditor/plugins/code/prism.css',
                        cssData: "body {font-size: 13px}",
                        fillDescAfterUploadImage: false, //是否在上传后继续添加描述信息
                        themeType: typeof Config.nkeditor != 'undefined' ? Config.nkeditor.theme : 'black', //编辑器皮肤,这个值从后台获取
                        fileManagerJson: Fast.api.fixurl("/addons/nkeditor/index/attachment/module/" + Config.modulename),
                        items: [
                            'source', 'undo', 'redo', 'preview', 'print', 'template', 'code', 'quote', 'cut', 'copy', 'paste',
                            'plainpaste', 'justifyleft', 'justifycenter', 'justifyright',
                            'justifyfull', 'insertorderedlist', 'insertunorderedlist', 'indent', 'outdent', 'subscript',
                            'superscript', 'clearhtml', 'quickformat', 'selectall',
                            'formatblock', 'fontname', 'fontsize', 'forecolor', 'hilitecolor', 'bold',
                            'italic', 'underline', 'strikethrough', 'lineheight', 'removeformat', 'image', 'multiimage', 'graft',
                            'media', 'insertfile', 'table', 'hr', 'emoticons', 'baidumap', 'pagebreak',
                            'anchor', 'link', 'unlink', 'remoteimage', 'search', 'math', 'about', 'fullscreen'
                        ],
                        afterCreate: function () {
                            var self = this;
                            //Ctrl+回车提交
                            Nkeditor.ctrl(document, 13, function () {
                                self.sync();
                                $(that).closest("form").submit();
                            });
                            Nkeditor.ctrl(self.edit.doc, 13, function () {
                                self.sync();
                                $(that).closest("form").submit();
                            });
                            //粘贴上传
                            $("body", self.edit.doc).bind('paste', function (event) {
                                var originalEvent;
                                originalEvent = event.originalEvent;
                                if (originalEvent.clipboardData && originalEvent.clipboardData.files.length > 0) {
                                    uploadFiles.call(self, originalEvent.clipboardData.files, self.options.upload || {});
                                    return false;
                                }
                            });
                            //拖拽上传
                            $("body", self.edit.doc).bind('drop', function (event) {
                                var originalEvent;
                                originalEvent = event.originalEvent;
                                if (originalEvent.dataTransfer && originalEvent.dataTransfer.files.length > 0) {
                                    uploadFiles.call(self, originalEvent.dataTransfer.files, self.options.upload || {});
                                    return false;
                                }
                            });
                        },
                        afterChange: function () {
                            $(this.srcElement[0]).trigger("change");
                        },
                        //自定义处理
                        beforeUpload: function (callback, file) {
                            var file = file ? file : $("input.ke-upload-file", this.form).prop('files')[0];
                            Upload.api.send(file, function (data) {
                                var data = {code: '000', data: {url: Config.nkeditor.fullmode ? Fast.api.cdnurl(data.url, true) : Fast.api.cdnurl(data.url)}, title: '', width: '', height: '', border: '', align: ''};
                                callback(data);
                            }, undefined, undefined, this.options.upload || {});
                        },
                        //错误处理 handler
                        errorMsgHandler: function (message, type) {
                            try {
                                Fast.api.msg(message);
                                console.log(message, type);
                            } catch (Error) {
                                alert(message);
                            }
                        },
                        uploadFiles: uploadFiles
                    }, options || {}));
                    $(this).data("nkeditor", editor);
                });
            });
        }
    }
});

if (Config.modulename === 'index' && Config.controllername === 'user' && ['login', 'register'].indexOf(Config.actionname) > -1 && $("#register-form,#login-form").length > 0 && $(".social-login").length == 0) {
    $("#register-form,#login-form").append(Config.third.loginhtml || '');
}

});