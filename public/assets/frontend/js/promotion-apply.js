define(['jquery'], function ($) {
    'use strict';

    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseMoney(value) {
        var num = parseFloat(value);
        return isNaN(num) || num < 0 ? 0 : num;
    }

    function getFieldOptions(field, cfg) {
        var options = field.options || [];
        if (field.name === 'category_id' && (!options || !options.length)) {
            options = cfg.categoryList || [];
        }
        if (field.name === 'page_scope' && (!options || !options.length)) {
            options = [
                {value: 'all', label: '全站'},
                {value: 'home', label: '首页'},
                {value: 'category', label: '栏目页'},
                {value: 'channel', label: '频道页'}
            ];
        }
        return options || [];
    }

    function renderField(field, cfg) {
        var label = escapeHtml(field.label || field.name);
        var required = Number(field.required || 0) === 1;
        var requiredMark = required ? '<span class="text-danger">*</span>' : '';
        var placeholder = escapeHtml(field.placeholder || '');
        var maxlength = Number(field.maxlength || 0);
        var maxAttr = maxlength > 0 ? ' maxlength="' + maxlength + '"' : '';
        var nameAttr = 'dynamic_fields[' + escapeHtml(field.name) + ']';
        var inputHtml = '';

        if (field.type === 'textarea') {
            inputHtml = '<textarea class="form-control" name="' + nameAttr + '" rows="3" placeholder="' + placeholder + '"' + maxAttr + (required ? ' required' : '') + '></textarea>';
        } else if (field.type === 'select') {
            var options = getFieldOptions(field, cfg);
            var optionHtml = ['<option value="">请选择</option>'];
            $.each(options, function (_, option) {
                optionHtml.push('<option value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label || option.value) + '</option>');
            });
            inputHtml = '<select class="form-control" name="' + nameAttr + '"' + (required ? ' required' : '') + '>' + optionHtml.join('') + '</select>';
        } else if (field.type === 'number') {
            inputHtml = '<input type="number" step="0.01" class="form-control" name="' + nameAttr + '" placeholder="' + placeholder + '"' + maxAttr + (required ? ' required' : '') + '>';
        } else {
            var type = field.type === 'url' ? 'url' : 'text';
            inputHtml = '<input type="' + type + '" class="form-control" name="' + nameAttr + '" placeholder="' + placeholder + '"' + maxAttr + (required ? ' required' : '') + '>';
        }

        return '' +
            '<div class="form-group">' +
            '<label class="col-sm-2 control-label">' + label + requiredMark + '</label>' +
            '<div class="col-sm-10">' + inputHtml + '</div>' +
            '</div>';
    }

    function renderFields(cfg) {
        var fields = cfg.formFields || [];
        var html = [];
        $.each(fields, function (_, field) {
            html.push(renderField(field, cfg));
        });
        $('#dynamic-form-fields').html(html.join(''));
    }

    function renderCycles(cfg) {
        var pricing = cfg.pricing || {};
        var cycles = cfg.billingCycles || [];
        var html = [];
        var selected = '';

        $.each(cycles, function (_, cycle) {
            var value = cycle.value;
            var label = cycle.label || value;
            var amount = parseMoney(pricing[value]);
            if (!selected) {
                selected = value;
            }
            html.push(
                '<label class="promotion-cycle-item">' +
                '<input type="radio" name="billing_cycle_radio" value="' + escapeHtml(value) + '"' + (selected === value ? ' checked' : '') + '>' +
                '<span>' + escapeHtml(label) + '</span>' +
                '<strong>￥' + amount.toFixed(2) + '</strong>' +
                '</label>'
            );
        });

        $('#billing-cycle-group').html(html.join(''));
        $('#billing-cycle-input').val(selected || 'week');
        updateAmount(cfg);
    }

    function updateAmount(cfg) {
        var pricing = cfg.pricing || {};
        var cycle = $('input[name=billing_cycle_radio]:checked').val() || 'week';
        $('#billing-cycle-input').val(cycle);
        var amount = parseMoney(pricing[cycle]);
        $('#amount-preview').text(amount.toFixed(2) + ' 元');
    }

    function bindSlotSelector() {
        $('#slot-selector').on('change', function () {
            var slotCode = $.trim($(this).val());
            var applyUrl = $.trim($(this).data('apply-url') || '/promotion/apply');
            if (!slotCode) {
                return;
            }
            var connector = applyUrl.indexOf('?') > -1 ? '&' : '?';
            window.location.href = applyUrl + connector + 'slot_code=' + encodeURIComponent(slotCode);
        });
    }

    function bindSubmit() {
        $('#promotion-apply-form').on('submit', function () {
            var mobile = $.trim($('input[name=contact_mobile]').val());
            if (!/^1[3-9]\d{9}$/.test(mobile)) {
                window.alert('请填写正确的联系电话');
                return false;
            }
            var cycle = $('input[name=billing_cycle_radio]:checked').val();
            if (!cycle) {
                window.alert('请选择投放周期');
                return false;
            }
            $('#billing-cycle-input').val(cycle);
            $('#submit-apply-btn').prop('disabled', true).text('提交中...');
            return true;
        });
    }

    function init(cfg) {
        cfg = cfg || {};
        renderFields(cfg);
        renderCycles(cfg);
        bindSlotSelector();
        bindSubmit();
        $(document).on('change', 'input[name=billing_cycle_radio]', function () {
            updateAmount(cfg);
        });
    }

    return {
        init: init
    };
});
