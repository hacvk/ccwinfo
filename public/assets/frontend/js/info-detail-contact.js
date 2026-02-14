(function ($) {
    'use strict';

    window.InfoDetailContact = window.InfoDetailContact || {};

    var state = {
        cfg: {},
        loading: false,
        revealed: false,
        cooldownUntil: 0,
        cooldownTimer: null
    };

    function isSuccess(res) {
        return Number((res || {}).code) === 1;
    }

    function setRegionText(text) {
        var value = $.trim(String(text || ''));
        $('#detail-phone-region').text(value || '未知归属地');
    }

    function setPhoneText(text) {
        var value = $.trim(String(text || ''));
        $('#detail-contact-phone').text(value || '-');
    }

    function stopCooldownTimer() {
        if (state.cooldownTimer) {
            clearInterval(state.cooldownTimer);
            state.cooldownTimer = null;
        }
    }

    function getCooldownRemainSeconds() {
        var remain = Math.ceil((state.cooldownUntil - Date.now()) / 1000);
        return remain > 0 ? remain : 0;
    }

    function updateCooldownButtonText($btn, remainSeconds) {
        $btn.text(remainSeconds > 0 ? (remainSeconds + 's后可再试') : '点击查看');
    }

    function startCooldown($btn, seconds) {
        var waitSeconds = Number(seconds || 0);
        if (waitSeconds <= 0 || state.revealed) {
            return;
        }

        stopCooldownTimer();
        state.cooldownUntil = Date.now() + (waitSeconds * 1000);
        $btn.prop('disabled', true);
        updateCooldownButtonText($btn, getCooldownRemainSeconds());

        state.cooldownTimer = setInterval(function () {
            var remain = getCooldownRemainSeconds();
            if (remain <= 0) {
                stopCooldownTimer();
                state.cooldownUntil = 0;
                if (!state.loading && !state.revealed) {
                    $btn.prop('disabled', false);
                    updateCooldownButtonText($btn, 0);
                }
                return;
            }
            updateCooldownButtonText($btn, remain);
        }, 1000);
    }

    function bindReveal() {
        var $btn = $('#detail-phone-reveal-btn');
        if (!$btn.length) {
            return;
        }

        $btn.on('click', function () {
            if (state.loading || state.revealed) {
                return;
            }
            if (getCooldownRemainSeconds() > 0) {
                return;
            }
            if (!state.cfg.revealApi || Number(state.cfg.infoId || 0) < 1) {
                return Frontend.alert('联系方式加载失败，请刷新重试', 'warning');
            }

            state.loading = true;
            $btn.prop('disabled', true).text('加载中...');

            Frontend.post(state.cfg.revealApi, {id: Number(state.cfg.infoId)}, function (res) {
                state.loading = false;
                if (!isSuccess(res)) {
                    var waitSeconds = Number((res || {}).wait_seconds || 0);
                    if (waitSeconds > 0) {
                        startCooldown($btn, waitSeconds);
                    } else {
                        $btn.prop('disabled', false).text('点击查看');
                    }
                    return Frontend.alert((res || {}).msg || '获取失败，请稍后重试', 'danger');
                }

                var data = (res || {}).data || {};
                setPhoneText(data.phone || data.masked_phone || '-');
                setRegionText(data.region || '未知归属地');
                state.revealed = true;
                stopCooldownTimer();
                $btn.hide();
            });
        });
    }

    window.InfoDetailContact.init = function () {
        state.cfg = window.InfoDetailContactConfig || {};
        bindReveal();
    };
})(jQuery);
