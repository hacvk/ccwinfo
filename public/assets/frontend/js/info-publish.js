(function ($) {
    'use strict';

    window.InfoPublish = window.InfoPublish || {};

    function _alert(msg, type, duration) {
        if (window.Frontend && typeof window.Frontend.alert === 'function') {
            window.Frontend.alert(msg, type, duration);
        } else {
            window.alert(msg);
        }
    }

    function _post(url, data, callback) {
        if (window.Frontend && typeof window.Frontend.post === 'function') {
            window.Frontend.post(url, data, callback);
        } else {
            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function (res) {
                    if (typeof callback === 'function') callback(res || {});
                },
                error: function () {
                    _alert('请求失败，请稍后重试', 'danger');
                }
            });
        }
    }

    function _upload(url, file, fieldName, callback) {
        if (window.Frontend && typeof window.Frontend.upload === 'function') {
            window.Frontend.upload(url, file, fieldName, callback);
        } else {
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
                    _alert('上传失败，请稍后重试', 'danger');
                }
            });
        }
    }

    var state = {
        page: 'publish',
        images: [],
        wait: 0,
        cfg: {},
        extFields: []
    };

    function parseExistingImages() {
        var raw = $('#images-json').val();
        if (!raw) return [];
        try {
            var list = JSON.parse(raw);
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttrValue(value) {
        return String(value || '')
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"');
    }

    function parseCheckboxValue(value) {
        var list = [];
        if (Array.isArray(value)) {
            list = value;
        } else if (typeof value === 'string' && value !== '') {
            list = value.split(',');
        }

        return list.map(function (item) {
            return $.trim(String(item || ''));
        }).filter(function (item) {
            return item !== '';
        });
    }

    function parseOptions(value) {
        if (!Array.isArray(value)) {
            return [];
        }
        return value.map(function (item) {
            return $.trim(String(item || ''));
        }).filter(function (item) {
            return item !== '';
        });
    }

    function renderImages() {
        var html = '';
        state.images.forEach(function (url, idx) {
            html += '<div class="preview-item">';
            html += '<img src="' + url + '" alt="image">';
            html += '<button type="button" data-idx="' + idx + '">x</button>';
            html += '</div>';
        });

        $('#image-preview').html(html);
        $('#images-json').val(JSON.stringify(state.images));
    }

    function startCountDown($btn) {
        if (state.wait <= 0) {
            $btn.prop('disabled', false).text('获取验证码');
            return;
        }
        $btn.prop('disabled', true).text(state.wait + 's 后重试');
        state.wait -= 1;
        setTimeout(function () { startCountDown($btn); }, 1000);
    }

    function clearExtFields(message) {
        state.extFields = [];
        $('#ext-fields-container').empty();
        $('#ext-fields-empty-tip').text(message || '当前分类暂无自定义字段').show();
    }

    function renderExtFieldItem(field) {
        var key = String(field.key || '');
        var name = String(field.name || '自定义字段');
        var type = String(field.type || 'text');
        var required = Number(field.required || 0) === 1;
        var placeholder = String(field.placeholder || name);
        var options = parseOptions(field.options);
        var value = field.value;
        var inputName = 'ext[' + key + ']';
        var inputNameEscaped = escapeAttrValue(inputName);

        var html = '<div class="form-group ext-field-item" data-key="' + escapeHtml(key) + '">';
        html += '<label>' + escapeHtml(name);
        if (required) {
            html += ' <span class="text-danger">*</span>';
        }
        html += '</label>';

        if (type === 'number') {
            html += '<input type="number" class="form-control" name="' + inputNameEscaped + '" value="' + escapeHtml(value || '') + '" placeholder="' + escapeHtml(placeholder) + '">';
        } else if (type === 'select') {
            html += '<select class="form-control" name="' + inputNameEscaped + '">';
            html += '<option value="">' + escapeHtml(placeholder || '请选择') + '</option>';
            options.forEach(function (opt) {
                var selected = String(value || '') === opt ? ' selected' : '';
                html += '<option value="' + escapeHtml(opt) + '"' + selected + '>' + escapeHtml(opt) + '</option>';
            });
            html += '</select>';
        } else if (type === 'radio') {
            if (!options.length) {
                html += '<p class="help-block text-warning">该字段缺少可选项配置</p>';
            } else {
                var checkedValue = String(value || '');
                options.forEach(function (opt) {
                    var checked = checkedValue !== '' && checkedValue === opt ? ' checked' : '';
                    html += '<label class="radio-inline">';
                    html += '<input type="radio" name="' + inputNameEscaped + '" value="' + escapeHtml(opt) + '"' + checked + '> ' + escapeHtml(opt);
                    html += '</label>';
                });
            }
        } else if (type === 'checkbox') {
            if (!options.length) {
                html += '<p class="help-block text-warning">该字段缺少可选项配置</p>';
            } else {
                var checkedValues = parseCheckboxValue(value);
                options.forEach(function (opt) {
                    var checked = checkedValues.indexOf(opt) > -1 ? ' checked' : '';
                    html += '<label class="checkbox-inline">';
                    html += '<input type="checkbox" name="' + inputNameEscaped + '[]" value="' + escapeHtml(opt) + '"' + checked + '> ' + escapeHtml(opt);
                    html += '</label>';
                });
            }
        } else if (type === 'date') {
            html += '<input type="text" class="form-control" name="' + inputNameEscaped + '" value="' + escapeHtml(value || '') + '" placeholder="YYYY-MM-DD">';
        } else {
            html += '<input type="text" class="form-control" name="' + inputNameEscaped + '" value="' + escapeHtml(value || '') + '" placeholder="' + escapeHtml(placeholder) + '">';
        }

        html += '</div>';
        return html;
    }

    function renderExtFields(fields) {
        if (!Array.isArray(fields) || !fields.length) {
            clearExtFields('当前分类暂无自定义字段');
            return;
        }

        state.extFields = fields;
        var html = '';
        fields.forEach(function (field) {
            html += renderExtFieldItem(field);
        });
        $('#ext-fields-container').html(html);
        $('#ext-fields-empty-tip').hide();
    }

    function loadExtFields(categoryId, subCategoryId, infoId) {
        if (!state.cfg.fieldsApi) return;
        if (!categoryId || Number(categoryId) < 1) {
            clearExtFields('请选择分类后自动加载自定义字段');
            return;
        }

        $('#ext-fields-container').html('<p class="text-muted">自定义字段加载中...</p>');
        $('#ext-fields-empty-tip').hide();

        var params = {
            category_id: categoryId,
            sub_category_id: subCategoryId || 0
        };
        if (infoId && Number(infoId) > 0) {
            params.info_id = infoId;
        }

        $.getJSON(state.cfg.fieldsApi, params, function (res) {
            if (!res || Number(res.code) !== 1) {
                clearExtFields('当前分类暂无自定义字段');
                return;
            }
            renderExtFields(Array.isArray(res.data) ? res.data : []);
        }).fail(function () {
            clearExtFields('自定义字段加载失败，请稍后重试');
        });
    }

    function loadSubCategories(categoryId, selectedId, callback) {
        $('#sub_category_id').html('<option value="">加载中...</option>');
        $.getJSON(state.cfg.subCategoryApi, {parent_id: categoryId}, function (res) {
            if (res.code !== 1 || !Array.isArray(res.data)) {
                $('#sub_category_id').html('<option value="">请选择子分类</option>');
                if (typeof callback === 'function') callback();
                return;
            }
            var html = '<option value="">请选择子分类</option>';
            res.data.forEach(function (item) {
                var selected = selectedId && Number(selectedId) === Number(item.id) ? ' selected' : '';
                html += '<option value="' + item.id + '"' + selected + '>' + item.name + '</option>';
            });
            $('#sub_category_id').html(html);
            if (typeof callback === 'function') callback();
        }).fail(function () {
            $('#sub_category_id').html('<option value="">请选择子分类</option>');
            if (typeof callback === 'function') callback();
        });
    }

    function isValidDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        var parts = value.split('-');
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.getFullYear() === Number(parts[0]) &&
            date.getMonth() === Number(parts[1]) - 1 &&
            date.getDate() === Number(parts[2]);
    }

    function validateExtFields() {
        if (!Array.isArray(state.extFields) || !state.extFields.length) {
            return true;
        }

        var $form = state.page === 'edit' ? $('#edit-form') : $('#publish-form');
        for (var i = 0; i < state.extFields.length; i += 1) {
            var field = state.extFields[i] || {};
            var key = String(field.key || '');
            var name = String(field.name || '自定义字段');
            var type = String(field.type || 'text');
            var required = Number(field.required || 0) === 1;
            var options = parseOptions(field.options);

            if (!key) continue;

            var selector = '[name="ext[' + escapeAttrValue(key) + ']"]';
            var value = '';
            var values = [];

            if (type === 'checkbox') {
                var checkboxSelector = '[name="ext[' + escapeAttrValue(key) + '][]"]';
                $form.find(checkboxSelector + ':checked').each(function () {
                    values.push($.trim(String($(this).val() || '')));
                });
                values = values.filter(function (item) {
                    return item !== '';
                });
                if (required && !values.length) {
                    _alert('请填写' + name, 'warning');
                    return false;
                }
                if (values.length) {
                    if (!options.length) {
                        _alert(name + ' 选项配置无效', 'warning');
                        return false;
                    }
                    for (var c = 0; c < values.length; c += 1) {
                        if (options.indexOf(values[c]) < 0) {
                            _alert(name + ' 选项值无效', 'warning');
                            return false;
                        }
                    }
                }
                continue;
            }

            if (type === 'radio') {
                value = $.trim(String($form.find(selector + ':checked').val() || ''));
            } else {
                value = $.trim(String($form.find(selector).val() || ''));
            }

            if (required && value === '') {
                _alert('请填写' + name, 'warning');
                return false;
            }
            if (value === '') {
                continue;
            }

            if (type === 'number' && !/^-?\d+(\.\d+)?$/.test(value)) {
                _alert(name + ' 必须为数字', 'warning');
                return false;
            }
            if (type === 'date' && !isValidDate(value)) {
                _alert(name + ' 日期格式应为 YYYY-MM-DD', 'warning');
                return false;
            }
            if (type === 'select' || type === 'radio') {
                if (!options.length || options.indexOf(value) < 0) {
                    _alert(name + ' 选项值无效', 'warning');
                    return false;
                }
            }
        }

        return true;
    }

    function sendCode(type) {
        var phone = $.trim($('#verify_phone').val());
        var btn = type === 'edit' ? $('#send-edit-code') : $('#send-publish-code');
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            return _alert('请输入正确手机号', 'warning');
        }
        if (state.wait > 0) return;

        var payload = {phone: phone, type: type};
        if (type === 'edit' && state.cfg.infoId) payload.info_id = state.cfg.infoId;
        if (type === 'publish' && state.page === 'publish' && Number(state.cfg.loginNeedMobileVerify || 0) === 1) {
            payload.scene = String(state.cfg.loginVerifyScene || 'publish_user_verify');
        }

        _post(state.cfg.sendCodeApi, payload, function (res) {
            if (res.code !== 1) return _alert(res.msg || '发送失败', 'danger');
            _alert(res.msg || '验证码已发送', 'success');
            state.wait = 60;
            startCountDown(btn);
        });
    }

    function submitForm() {
        var categoryId;
        var title;
        var content;
        var minLen;
        var pwd;
        var pwdConfirm;

        var formId = state.page === 'edit' ? '#edit-form' : '#publish-form';
        categoryId = Number($('#category_id').val() || 0);
        title = $.trim(String($(formId + ' [name="title"]').val() || ''));
        content = $.trim(String($(formId + ' [name="content"]').val() || ''));

        if (categoryId < 1) {
            _alert('请选择主分类', 'warning');
            return;
        }
        if (title === '') {
            _alert('请填写标题', 'warning');
            return;
        }
        if (title.length > 64) {
            _alert('标题不能超过64个字', 'warning');
            return;
        }
        if (content === '') {
            _alert('请填写详细描述', 'warning');
            return;
        }
        if (content.length < 10) {
            _alert('详细描述至少10个字', 'warning');
            return;
        }

        var validDaysText = $.trim(String($(formId + ' [name="valid_days"]').val() || ''));
        if (!/^\d+$/.test(validDaysText)) {
            _alert('有效期必须为0或正整数', 'warning');
            return;
        }

        if (state.page === 'publish' && $('#manage_password').length > 0) {
            minLen = Number(state.cfg.passwordMinLength || 8);
            minLen = minLen > 8 ? minLen : 8;
            pwd = String($('#manage_password').val() || '');
            pwdConfirm = String($('#manage_password_confirm').val() || '');
            if (pwd.length < minLen) {
                _alert('预留密码至少 ' + minLen + ' 位', 'warning');
                return;
            }
            if (pwd !== pwdConfirm) {
                _alert('两次输入的预留密码不一致', 'warning');
                return;
            }
        }

        if (state.page === 'publish' && Number(state.cfg.loginNeedMobileVerify || 0) === 1) {
            var verifyPhone = $.trim(String($('#verify_phone').val() || ''));
            var verifyCode = $.trim(String($('#verify_code').val() || ''));
            var loginHasMobile = Number(state.cfg.loginHasMobile || 0) === 1;

            if (!/^1[3-9]\d{9}$/.test(verifyPhone)) {
                _alert('请填写正确的验证手机号', 'warning');
                return;
            }
            if (verifyCode === '') {
                _alert('请输入短信验证码', 'warning');
                return;
            }
            if (loginHasMobile) {
                var accountMobile = $.trim(String($('#verify_phone').data('account-mobile') || ''));
                if (accountMobile !== '' && verifyPhone !== accountMobile) {
                    _alert('请使用账号绑定手机号完成验证', 'warning');
                    return;
                }
            } else {
                var bindAction = $.trim(String($(formId + ' [name="bind_mobile_action"]:checked').val() || ''));
                if (bindAction !== 'bind' && bindAction !== 'skip') {
                    _alert('请选择验证后是否绑定手机号', 'warning');
                    return;
                }
            }
        }

        if (!validateExtFields()) {
            return;
        }

        _post(state.cfg.submitApi, $(formId).serialize(), function (res) {
            if (res.code !== 1) return _alert(res.msg || '提交失败', 'danger');
            _alert(res.msg || '操作成功', 'success');
            setTimeout(function () {
                window.location.href = res.redirect || '/';
            }, 600);
        });
    }

    window.InfoPublish.init = function (page) {
        state.page = page || 'publish';
        state.cfg = window.InfoPublishConfig || {};
        state.images = parseExistingImages();
        renderImages();

        var initialCategory = Number($('#category_id').val() || 0);
        var initialSub = Number($('#sub_category_id').val() || 0);
        var initialInfoId = state.page === 'edit' ? Number(state.cfg.infoId || 0) : 0;
        
        console.log('[InfoPublish] init - initialCategory:', initialCategory, 'initialSub:', initialSub);
        
        if (initialCategory > 0) {
            var subCategoryOptions = $('#sub_category_id option').length;
            var hasSelectedSub = initialSub > 0;
            console.log('[InfoPublish] subCategoryOptions:', subCategoryOptions, 'hasSelectedSub:', hasSelectedSub);
            
            if (subCategoryOptions > 1 && hasSelectedSub) {
                console.log('[InfoPublish] 子分类已由后端渲染，直接加载自定义字段');
                loadExtFields(initialCategory, initialSub, initialInfoId);
            } else if (subCategoryOptions > 1 && !hasSelectedSub) {
                console.log('[InfoPublish] 子分类列表已渲染但未选中，直接加载自定义字段');
                loadExtFields(initialCategory, 0, initialInfoId);
            } else {
                console.log('[InfoPublish] 子分类列表为空，需要重新加载');
                loadSubCategories(initialCategory, initialSub, function () {
                    var selectedSub = Number($('#sub_category_id').val() || 0);
                    loadExtFields(initialCategory, selectedSub, initialInfoId);
                });
            }
        } else {
            clearExtFields('请选择分类后自动加载自定义字段');
        }

        $('#category_id').on('change', function () {
            var categoryId = Number($(this).val() || 0);
            if (categoryId > 0) {
                loadSubCategories(categoryId, 0, function () {
                    loadExtFields(categoryId, 0, 0);
                });
            } else {
                $('#sub_category_id').html('<option value="">请选择子分类</option>');
                clearExtFields('请选择分类后自动加载自定义字段');
            }
        });

        $('#sub_category_id').on('change', function () {
            var categoryId = Number($('#category_id').val() || 0);
            var subCategoryId = Number($(this).val() || 0);
            loadExtFields(categoryId, subCategoryId, 0);
        });

        $('#image-input').on('change', function () {
            var files = this.files || [];
            if (!files.length) return;

            Array.prototype.forEach.call(files, function (file) {
                if (state.images.length >= (state.cfg.maxImages || 9)) {
                    _alert('图片数量达到上限', 'warning');
                    return;
                }
                _upload(state.cfg.uploadApi, file, 'image', function (res) {
                    if (res.code !== 1) return _alert(res.msg || '上传失败', 'danger');
                    state.images.push(res.url);
                    renderImages();
                });
            });

            $(this).val('');
        });

        $('#image-preview').on('click', 'button', function () {
            var idx = Number($(this).data('idx'));
            state.images.splice(idx, 1);
            renderImages();
        });

        $('#send-publish-code').on('click', function () { sendCode('publish'); });
        $('#send-edit-code').on('click', function () { sendCode('edit'); });

        $('#publish-form, #edit-form').on('submit', function (e) {
            e.preventDefault();
            submitForm();
        });
    };
})(jQuery);
