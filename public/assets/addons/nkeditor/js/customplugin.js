define(['nkeditor-core'], function (Nkeditor) {

    Nkeditor.lang({
        remoteimage: '下载远程图片',
        search: '查找替换',
        math: '插入公式',
    });

    var getImageFromUrl = function (url, callback, outputFormat) {
        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = new Image;
        img.crossOrigin = 'anonymous';
        img.referrerPolicy = 'no-referrer';
        img.onload = function () {
            var urlArr = url.split('.');
            var suffix = urlArr.pop();
            suffix = suffix.match(/^(jpg|png|gif|bmp|jpeg|webp)$/i) ? suffix : 'png';

            try {
                canvas.height = img.height;
                canvas.width = img.width;
                ctx.drawImage(img, 0, 0);
                var filename = Math.random().toString(36).substring(5, 15) + "." + suffix;
                var dataURL = canvas.toDataURL(outputFormat || 'image/' + suffix);
                var arr = dataURL.split(','), mimetype = arr[0].match(/:(.*?);/)[1],
                    bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
                while (n--) {
                    u8arr[n] = bstr.charCodeAt(n);
                }
                var blob = new Blob([u8arr], {type: mimetype});
                var file = new File([blob], filename, {type: mimetype});
            } catch (e) {
                callback.call(this, null);
                return;
            }

            callback.call(this, file);
            canvas = null;
        };
        img.onerror = function (e) {
            console.log(e)
            callback.call(this, null);
        };
        img.src = url;
    };

    Nkeditor.plugin('multiimage', function (K) {
        var self = this, name = 'multiimage', lang = self.lang(name + '.'),
            allowImages = K.undef(self.allowImages, false);

        var click = function () {

            var html = [
                '<div class="ke-dialog-content-inner">',
                '<div class="ke-dialog-row ke-clearfix">',
                '<div class=""><div class="ke-inline-block ke-upload-button">' +
                '<form class="ke-upload-area ke-form nice-validator n-default" method="post" enctype="multipart/form-data" style="width: 266px;margin:50px auto;">' +
                '<span class="ke-button-common"><input type="button" class="ke-button-common ke-button" value="批量上传图片" style="width:128px;"></span><input type="file" class="ke-upload-file" name="imgFiles" multiple style="width:128px;left:0;right:inherit" tabindex="-1">' +
                '<span class="ke-button-common" style="margin-left:10px;"><input type="button" class="ke-button-common ke-button ke-select-image" style="width:128px;" value="从图片空间选择"></span>' +
                '</form>' +
                '</div></span></div>',
                '</div>',
                '</div>'
            ].join('');
            var dialog = self.createDialog({
                    name: name,
                    width: Math.min(document.body.clientWidth, 450),
                    height: 260,
                    title: self.lang(name),
                    body: html,
                    noBtn: {
                        name: self.lang('no'),
                        click: function (e) {
                            self.hideDialog().focus();
                        }
                    }
                }),
                div = dialog.div;
            $("input[name=imgFiles]", div).change(function () {
                dialog.showLoading();
                var files = $(this).prop('files');
                self.options.uploadFiles.call(self, files).then(function () {
                    self.hideDialog().focus();
                });
                return false;
            });
            $(".ke-select-image", div).click(function () {
                self.loadPlugin('filemanager', function () {
                    self.plugin.filemanagerDialog({
                        dirName: 'image',
                        multiple: true,
                        clickFn: function (urls) {
                            $.each(urls, function (i, url) {
                                self.exec('insertimage', Config.nkeditor.fullmode ? Fast.api.cdnurl(url, true) : Fast.api.cdnurl(url));
                            });
                        }
                    });
                });
                self.hideDialog().focus();
            });
        };
        self.clickToolbar(name, click);
    });

    //远程下载图片
    Nkeditor.plugin('remoteimage', function (K) {
        var editor = this, name = 'remoteimage';
        var Upload = require('upload');
        editor.plugin.remoteimage = {
            download: function (e) {
                var that = this;
                var html = that.html();
                var staging = {}, orgined = {}, index = 0, images = 0, completed = 0, failured = 0;
                var checkrestore = function () {
                    if (completed + failured >= images) {
                        $.each(staging, function (i, j) {
                            that.html(that.html().replace("<code>" + i + "</code>", j));
                        });
                        Toastr.info("成功：" + completed + " 失败：" + failured);
                    }
                };
                html.replace(/<code>([\s\S]*?)<\/code>/g, function (code) {
                        staging[index] = code;
                        return "<code>" + index + "</code>";
                    }
                );
                html = html.replace(/<img([\s\S]*?)\ssrc\s*=\s*('|")((http(s?):)([\s\S]*?))('|")([\s\S]*?)[\/]?>/g, function () {
                    images++;
                    var url = arguments[3];
                    var placeholder = '<img src="' + Config.site.cdnurl + "/assets/addons/nkeditor/img/downloading.png" + '" data-index="' + index + '" />';
                    //如果是云存储的链接或本地的链接,则忽略
                    if ((Config.upload.cdnurl && url.indexOf(Config.upload.cdnurl) > -1) || url.indexOf(location.origin) > -1) {
                        completed++;
                        return arguments[0];
                    } else {
                        orgined[index] = arguments[0];
                    }
                    var attributes = arguments[1] + " " + arguments[8];
                    attributes = attributes.replace(/'/g, '"').replace(/\sdata\-(src|original|actualsrc|ke\-src)\s*=\s*('|")(.*?)('|")/g, '');
                    //下载远程图片
                    (function (index, url, placeholder, attributes) {
                        placeholder = new RegExp('\<img src="((?!\\<).)*?" data-index="' + index + '"(.*?)\>', 'g');
                        getImageFromUrl(url, function (file) {
                            if (!file) {
                                failured++;
                                that.html(that.html().replace(placeholder, orgined[index]));
                                checkrestore();
                            } else {
                                Upload.api.send(file, function (data) {
                                    completed++;
                                    var replaceValue = '<img src="' + (Config.nkeditor.fullmode ? Fast.api.cdnurl(data.url, true) : Fast.api.cdnurl(data.url)) + '" ' + attributes + ' />';
                                    that.html(that.html().replace(placeholder, replaceValue));
                                    checkrestore();
                                }, function (data) {
                                    failured++;
                                    that.html(that.html().replace(placeholder, orgined[index]));
                                    checkrestore();
                                });
                            }
                        });
                    })(index, url, placeholder, attributes);

                    index++;
                    return placeholder;
                });
                if (index > 0) {
                    that.html(html);
                } else {
                    Toastr.info("没有需要下载的远程图片");
                }
            }
        };
        // 点击图标时执行
        editor.clickToolbar(name, editor.plugin.remoteimage.download);
    });

    //查找替换
    Nkeditor.plugin('search', function (K) {
        var self = this, name = 'search', lang = self.lang(name + '.');

        var click = function () {

            var html = [
                '<div class="ke-dialog-content-inner">',
                '<div class="ke-dialog-row ke-clearfix">',
                '<div class=""><div class="ke-inline-block ke-upload-button">' +
                '<form class="ke-upload-area ke-form nice-validator n-default" method="post" style="width: 366px;margin:20px auto;">' +
                '<div style="margin-bottom:20px;color:red;">温馨提示：替换完成后务必核对最终结果是否正确</div>' +
                '<span class="ke-button-common">请输入查找的字符：<input type="text" class="ke-input-text" name="search" value="" placeholder="" style="width:220px;"></span>' +
                '<span class="ke-button-common" style="margin-top:10px;">请输入替换的字符：<input type="text" name="replace" class="ke-input-text" value="" placeholder="" style="width:220px;"></span>' +
                '</form>' +
                '</div></span></div>',
                '</div>',
                '</div>'
            ].join('');
            var dialog = self.createDialog({
                    name: name,
                    width: Math.min(document.body.clientWidth, 450),
                    height: 260,
                    title: self.lang(name),
                    body: html,
                    yesBtn: {
                        name: self.lang('yes'),
                        click: function (e) {
                            var search = $("input[name=search]", self.bodyDiv).val();
                            var replace = $("input[name=replace]", self.bodyDiv).val();
                            if (search === '') {
                                Layer.msg("查找的字符不能为空");
                                return false;
                            }
                            if (search === replace) {
                                Layer.msg("查找的字符不能等于替换的字符");
                                return false;
                            }
                            var html = self.html();
                            if (html === '') {
                                Layer.msg("暂无可替换的文本");
                            }
                            var searchExp = new RegExp("(" + search + ")(?!([^<]+)?>)", "gi");
                            var matches = html.match(searchExp);
                            if (matches && matches.length > 0) {
                                self._firstAddBookmark = true;
                                self.addBookmark(false);
                                self.html(html.replace(searchExp, replace));
                                Toastr.success("共完成" + matches.length + "处文本替换");
                            } else {
                                Layer.msg("暂未找到可替换的文本");
                            }
                            self.hideDialog().focus();
                        }
                    },
                    noBtn: {
                        name: self.lang('no'),
                        click: function (e) {
                            self.hideDialog().focus();
                        }
                    }
                }),
                div = dialog.div;
        };
        self.clickToolbar(name, click);
    });

    return Nkeditor;
});
