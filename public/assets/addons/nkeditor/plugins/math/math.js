KindEditor.plugin('math', function (K) {
    var self = this, name = 'math', lang = self.lang(name + '.');
    self.clickToolbar(name, function () {
        var img = self.plugin.getSelectedImage();
        var latex = $(img).data("latex") || '';
        var html = [
            '<div class="ke-dialog-content-inner">',
            '<div class="tabs"></div>',
            '<div class="ke-formula" style="width:510px;height:380px;"></div>',
            '</div>'
        ].join('');
        var iframe = K('<iframe class="" frameborder="0" src="' + self.pluginsPath + 'math/formula.html?latex=' + encodeURIComponent(latex) + '&previewUrl=' + encodeURIComponent(self.options.formulaPreviewUrl) + '" style="width:100%;height:300px;"></iframe>');

        function svgToPng(svgString, width, height, callback) {
            // 创建SVG图像
            var svgBlob = new Blob([svgString], {type: 'image/svg+xml'});
            var svgUrl = URL.createObjectURL(svgBlob);

            // 创建Image对象
            var img = new Image();
            img.onload = function () {
                // 创建canvas
                var canvas = document.createElement('canvas');
                canvas.width = width || img.width;
                canvas.height = height || img.height;

                // 在canvas上绘制SVG
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // 释放URL对象
                URL.revokeObjectURL(svgUrl);

                // 将canvas转换为PNG Blob
                if (canvas.toBlob) {
                    canvas.toBlob(function (pngBlob) {
                        if (pngBlob) {
                            // 创建文件对象
                            var pngFile = new File([pngBlob], 'latex.png', {type: 'image/png'});
                            callback(null, pngFile);
                        } else {
                            callback(new Error('转换PNG失败'));
                        }
                    }, 'image/png');
                } else {
                    // 兼容不支持toBlob的浏览器
                    try {
                        var dataURL = canvas.toDataURL('image/png');
                        var binary = atob(dataURL.split(',')[1]);
                        var array = [];
                        for (var i = 0; i < binary.length; i++) {
                            array.push(binary.charCodeAt(i));
                        }
                        var pngBlob = new Blob([new Uint8Array(array)], {type: 'image/png'});
                        var pngFile = new File([pngBlob], 'latex.png', {type: 'image/png'});
                        callback(null, pngFile);
                    } catch (e) {
                        callback(e);
                    }
                }
            };

            img.onerror = function () {
                URL.revokeObjectURL(svgUrl);
                callback(new Error('SVG加载失败'));
            };

            img.src = svgUrl;
        }

        var dialog = self.createDialog({
                name: name,
                width: Math.min(document.body.clientWidth, 500),
                height: 380,
                title: "插入公式",
                body: html,
                yesBtn: {
                    name: '插入',
                    click: function (e) {
                        var win = iframe[0].contentWindow;

                        var currentSVG = win.$("#preview-body")[0].querySelector("svg");
                        if (!currentSVG) {
                            alert("请输入正确的公式");
                        }

                        svgToPng(currentSVG.outerHTML, currentSVG.clientWidth * 2, currentSVG.clientHeight * 2, function (err, file) {
                            require(['upload'], function (Upload) {
                                Upload.api.send(file, function (data, ret) {
                                    var url = Fast.api.cdnurl(ret.data.url, true);
                                    var latex = win.$("#latex-source").val();
                                    if (latex === '') {
                                        Layer.msg("请选择或输入公式");
                                        return false;
                                    }
                                    self.insertHtml("<img src='" + url + "' data-latex='" + latex + "' width='" + currentSVG.clientWidth + "' />");
                                    self.hideDialog().focus();
                                });
                            });
                        });
                        return;
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

        K('.ke-formula', div).replaceWith(iframe);
        return;

    });
});
