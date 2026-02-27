define(['jquery', 'bootstrap', 'frontend'], function ($, undefined, Frontend) {
    'use strict';

    var Controller = {
        index: function () {
        },
        mall: function () {
        },
        detail: function () {
            var $form = $('#exchange-submit-form');
            if ($form.length === 0) {
                return;
            }

            var needShipping = parseInt($form.data('need-shipping'), 10) === 1;
            if (!needShipping) {
                return;
            }

            var $shippingBox = $('#shipping-box');
            var $province = $('#province-id');
            var $city = $('#city-id');
            var $district = $('#district-id');
            $shippingBox.show();

            var areaUrl = (window.Fast && Fast.api && Fast.api.fixurl) ? Fast.api.fixurl('ajax/area') : '/index/ajax/area';

            var fillOptions = function ($select, items, placeholder) {
                var html = ['<option value="">' + placeholder + '</option>'];
                $.each(items || [], function (_, row) {
                    html.push('<option value="' + row.value + '">' + row.name + '</option>');
                });
                $select.html(html.join(''));
            };

            var loadArea = function (params, callback) {
                $.getJSON(areaUrl, params || {}, function (ret) {
                    if (ret && Number(ret.code) === 1) {
                        callback(ret.data || []);
                        return;
                    }
                    callback([]);
                }).fail(function () {
                    callback([]);
                });
            };

            loadArea({}, function (rows) {
                fillOptions($province, rows, '请选择省份');
            });

            $province.on('change', function () {
                var provinceId = $(this).val() || '';
                fillOptions($city, [], '请选择城市');
                fillOptions($district, [], '请选择区县');
                $city.prop('disabled', true);
                $district.prop('disabled', true);
                if (!provinceId) {
                    return;
                }
                loadArea({province: provinceId}, function (rows) {
                    fillOptions($city, rows, '请选择城市');
                    $city.prop('disabled', false);
                });
            });

            $city.on('change', function () {
                var cityId = $(this).val() || '';
                fillOptions($district, [], '请选择区县');
                $district.prop('disabled', true);
                if (!cityId) {
                    return;
                }
                loadArea({city: cityId}, function (rows) {
                    fillOptions($district, rows, '请选择区县');
                    $district.prop('disabled', false);
                });
            });

            $form.on('submit', function () {
                var provinceName = $.trim($province.find('option:selected').text() || '');
                var cityName = $.trim($city.find('option:selected').text() || '');
                var districtName = $.trim($district.find('option:selected').text() || '');
                $('#province-name').val(provinceName);
                $('#city-name').val(cityName);
                $('#district-name').val(districtName);

                if (!($province.val() && $city.val() && $district.val())) {
                    Layer.msg('请完整选择省市区');
                    return false;
                }
                if (provinceName.indexOf('山东') !== 0) {
                    Layer.msg('仅支持山东省地址发货');
                    return false;
                }
                if (!$.trim($('input[name=address_detail]').val())) {
                    Layer.msg('请填写详细地址');
                    return false;
                }
                if (!$.trim($('input[name=receiver_name]').val())) {
                    Layer.msg('请填写收件人');
                    return false;
                }
                if (!$.trim($('input[name=receiver_mobile]').val())) {
                    Layer.msg('请填写手机号');
                    return false;
                }
            });
        },
        orders: function () {
            var cancelUrl = (window.Fast && Fast.api && Fast.api.fixurl) ? Fast.api.fixurl('exchange/cancel') : '/index/exchange/cancel';
            $(document).on('click', '.js-exchange-cancel', function () {
                var id = parseInt($(this).data('id'), 10) || 0;
                var token = $(this).data('token') || '';
                if (!id) {
                    return;
                }
                Layer.confirm('确认取消该兑换订单吗？', {icon: 3, title: '提示'}, function (index) {
                    $.ajax({
                        url: cancelUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {id: id, __token__: token},
                        success: function (ret) {
                            if (ret && Number(ret.code) === 1) {
                                Layer.msg(ret.msg || '取消成功');
                                setTimeout(function () {
                                    location.reload();
                                }, 600);
                                return;
                            }
                            Layer.msg((ret && ret.msg) || '取消失败');
                        },
                        error: function () {
                            Layer.msg('请求失败，请稍后重试');
                        }
                    });
                    Layer.close(index);
                });
            });
        }
    };

    return Controller;
});
