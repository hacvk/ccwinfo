/**
 * 简洁文字轮播脚本
 * 15条信息，每次显示6条，2秒轮换
 */
$(document).ready(function() {
    var $container = $('#textAdContainer');
    var $list = $container.find('.text-ad-list');
    var $items = $list.find('.text-ad-item');
    var totalItems = $items.length; // 15条
    var displayCount = 8; // 每次显示6条
    var interval = 2000; // 2秒切换

    var currentIndex = 0;

    // 计算总组数（向上取整）
    var totalGroups = Math.ceil(totalItems / displayCount);

    // 开始轮播
    function startCarousel() {
        setInterval(function() {
            currentIndex = (currentIndex + 1) % totalGroups;
            updateDisplay();
        }, interval);
    }

    // 更新显示
    function updateDisplay() {
        var startIdx = currentIndex * displayCount;
        $items.each(function(index) {
            if (index >= startIdx && index < startIdx + displayCount) {
                $(this).css('display', 'block');
            } else {
                $(this).css('display', 'none');
            }
        });
    }

    // 初始化显示前6条
    updateDisplay();

    // 启动轮播
    startCarousel();
});
