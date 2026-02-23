(function ($) {
    'use strict';

    $(function () {
        var $main = $('#guide-detail-main-image');
        if (!$main.length) {
            return;
        }
        $('.guide-detail-page .detail-thumb').on('click', function () {
            var $thumb = $(this);
            var src = $thumb.data('src');
            if (!src) {
                return;
            }
            $main.attr('src', src);
            $('.guide-detail-page .detail-thumb').removeClass('active');
            $thumb.addClass('active');
        });
    });
})(jQuery);
