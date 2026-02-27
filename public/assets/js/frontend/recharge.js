define(['jquery', 'bootstrap', 'frontend'], function ($, undefined, Frontend) {
    var Controller = {
        recharge: function () {
            var togglePayExtras = function () {
                var paytype = $.trim($("input[name=paytype]").val() || '');
                var isCard = paytype === 'card';
                var isOnline = paytype === 'wechat' || paytype === 'alipay';

                $("#recharge-card-box").toggle(isCard);
                $("#discount-card-box").toggle(isOnline);

                $("input[name=recharge_card_no], input[name=recharge_card_secret]").prop('disabled', !isCard);
                $("input[name=discount_card_no], input[name=discount_card_secret]").prop('disabled', !isOnline);
            };

            $(document).on("click", ".row-money label[data-type]", function () {
                $(".row-money label[data-type]").removeClass("active");
                $(this).addClass("active");
                $("#col-custom").toggleClass("hidden", $(this).data("type") === "fixed");
                $("input[name=money]").val($(this).data("value"));
                if ($(this).data("type") === 'custom') {
                    $("input[name=custommoney]").trigger("focus").trigger("change");
                }
            });

            $(document).on("click", ".row-paytype label", function () {
                $(".row-paytype label").removeClass("active");
                $(this).addClass("active");
                $("input[name=paytype]").val($(this).data("value"));
                togglePayExtras();
            });

            $(document).on("keyup change", ".custommoney", function () {
                $("input[name=money]").val($(this).val());
            });

            $("#recharge-form").on("submit", function () {
                var price = parseFloat($("input[name=money]").val());
                var paytype = $.trim($("input[name=paytype]").val() || '');
                var rechargeCardNo = $.trim($("input[name=recharge_card_no]").val() || '');
                var rechargeCardSecret = $.trim($("input[name=recharge_card_secret]").val() || '');
                var discountCardNo = $.trim($("input[name=discount_card_no]").val() || '');
                var discountCardSecret = $.trim($("input[name=discount_card_secret]").val() || '');

                if (isNaN(price) || price <= 0) {
                    Layer.msg("充值金额不正确");
                    return false;
                }
                if (!paytype) {
                    Layer.msg("请选择支付方式");
                    return false;
                }

                if (paytype === 'card') {
                    if (!rechargeCardNo || !rechargeCardSecret) {
                        Layer.msg("请选择充值卡支付时必须填写卡号和卡密");
                        return false;
                    }
                    if (discountCardNo || discountCardSecret) {
                        Layer.msg("充值卡与优惠卡不可叠加使用");
                        return false;
                    }
                } else {
                    if ((discountCardNo && !discountCardSecret) || (!discountCardNo && discountCardSecret)) {
                        Layer.msg("优惠卡号和卡密需要同时填写");
                        return false;
                    }
                }
            });

            togglePayExtras();
        }
    };
    return Controller;
});

