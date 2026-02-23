(function ($) {
    'use strict';

    $(function () {
        var $subnav = $('.guide-subnav-wrap .guide-subnav-inner');
        var $active = $subnav.find('.guide-subnav-item.active');
        if ($subnav.length && $active.length) {
            var offset = $active.position().left - 12;
            if (offset > 0) {
                $subnav.scrollLeft(offset);
            }
        }
    });
})(jQuery);
