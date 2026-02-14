-- =============================================
-- 分类信息测试数据 - 30条
-- 执行前请确保已执行 step1.1_create_tables.sql
-- =============================================

SET NAMES utf8mb4;

-- 清空现有数据（可选，谨慎使用）
-- TRUNCATE TABLE `fa_info`;
-- TRUNCATE TABLE `fa_info_field_value`;

BEGIN;

-- 房产信息 (category_id=1, sub_category_id=7~13)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 1, 7, '阳光花园精装两室出租', '阳光花园小区，6楼，精装修，两室一厅，家电齐全，拎包入住，月租押一付三。周边配套完善，交通便利，步行5分钟到地铁站。', '[]', 1800.00, '元/月', 30, '王女士', '13812345678', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 1, 8, '市中心单身公寓招租', '市中心黄金地段，精装单身公寓，独立厨卫，空调热水器齐全，适合单身白领居住。月租1200元，押一付一。', '[]', 1200.00, '元/月', 60, '李经理', '13923456789', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 1, 9, '急售学区房三室两厅', '急售！重点学区房，三室两厅两卫，面积128平，南北通透，采光好，满五唯一，价格可议。', '[]', 850000.00, '万元', 90, '张先生', '15034567890', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 1, 10, '中介代理优质房源推荐', '本中介公司代理多套优质房源，涵盖各价位段，欢迎来电咨询看房，专业服务，诚信经营。', '[]', NULL, '', 30, '刘经理', '13645678901', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 1, 11, '地下车位转让', '小区地下停车位转让，位置好，靠近电梯口，产权清晰，价格面议。', '[]', 80000.00, '元', 60, '赵先生', '13756789012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 1, 12, '繁华地段商铺出租', '步行街黄金地段商铺出租，面积80平米，适合餐饮、服装等行业，人流量大，租金面议。', '[]', 15000.00, '元/月', 30, '孙女士', '13867890123', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 人才信息 (category_id=2, sub_category_id=14~28)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 2, 14, '大型超市招聘导购员10名', '本超市因业务扩展，现招聘导购员10名，要求女性，18-35岁，形象好气质佳，有经验者优先，薪资3000-5000元。', '[]', NULL, '', 30, '人事部', '13978901234', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 15, '美容院招聘美容师', '高端美容院招聘美容师3名，要求有相关工作经验，手法熟练，薪资底薪+提成，月入过万不是梦。', '[]', NULL, '', 30, '陈店长', '15089012345', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 16, '连锁餐厅招聘厨师服务员', '连锁餐厅招聘厨师2名、服务员5名，包吃住，薪资面议，有经验者优先，工作地点市中心。', '[]', NULL, '', 30, '周经理', '13690123456', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 17, '家政公司招聘保姆月嫂', '正规家政公司招聘保姆、月嫂若干名，要求有健康证，有经验者优先，薪资4000-8000元。', '[]', NULL, '', 30, '家政部', '13701234567', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 18, '物流公司招聘司机保安', '物流公司招聘货车司机5名，保安3名，司机需B2驾照，包吃住，薪资5000-8000元。', '[]', NULL, '', 30, '吴主管', '13812345670', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 19, '快递公司招聘配送员', '快递公司急聘配送员20名，自带电动车优先，多劳多得，月入6000-10000元，区域就近分配。', '[]', NULL, '', 30, '招聘热线', '13923456701', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 20, '工厂招聘普工技工', '电子工厂招聘普工50名，技工10名，包吃住，五险一金，综合薪资4500-7000元。', '[]', NULL, '', 30, '人事科', '15034567801', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 21, '保险公司招聘业务精英', '知名保险公司招聘业务精英，底薪+提成+奖金，培训上岗，月入过万，欢迎有志之士加入。', '[]', NULL, '', 30, '郑经理', '13645678901', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 22, '贸易公司招聘文员助理', '贸易公司招聘文员助理2名，要求熟练办公软件，工作细心，薪资3500-4500元，双休。', '[]', NULL, '', 30, '黄主管', '13756789001', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 2, 28, '求职会计工作', '本人女，32岁，会计专业，有会计证，5年工作经验，求职会计相关工作，薪资面议。', '[]', NULL, '', 30, '林女士', '13867890101', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 供求信息 (category_id=3, sub_category_id=29~38)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 3, 29, '转让全新iPhone手机', '转让全新iPhone 15 Pro Max 256G，未拆封，正品行货，因中奖所得，价格优惠，欲购从速。', '[]', 7999.00, '元', 15, '马先生', '13978901201', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 30, '转让购物卡加油卡', '转让各大商场购物卡、中石化加油卡，面值100-1000元不等，9折优惠，量大从优。', '[]', NULL, '', 30, '卡务中心', '15089012301', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 31, '品牌服装清仓处理', '品牌女装清仓处理，款式多样，尺码齐全，原价300-800元，现价50-150元，欢迎选购。', '[]', NULL, '', 30, '服装店', '13690123012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 32, '二手家具低价处理', '搬家处理二手家具，沙发、床、衣柜、餐桌等，成色新，价格低，自提可送货。', '[]', NULL, '', 15, '何女士', '13701234501', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 34, '出售木工机械设备', '出售木工机械设备一套，包括推台锯、压刨机、打孔机等，八成新，价格面议。', '[]', 25000.00, '元', 60, '徐老板', '13812345012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 35, '农家自产土鸡蛋出售', '农家自产土鸡蛋，纯粮喂养，营养丰富，每斤15元，满10斤送货上门，欢迎订购。', '[]', 15.00, '元/斤', 30, '农户老李', '13923456012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 36, '纯种金毛犬出售', '自家繁殖纯种金毛犬，3个月大，已打疫苗，活泼可爱，售价2000元，喜欢联系。', '[]', 2000.00, '元', 30, '宠物店', '15034567012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 3, 38, '寻物启事：丢失钱包', '本人于昨天在步行街丢失棕色钱包一个，内有身份证、银行卡及现金若干，望好心人归还，必有重谢！', '[]', NULL, '', 7, '失主', '13645678012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 车辆信息 (category_id=4, sub_category_id=39~43)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 4, 39, '转让二手大众帕萨特', '转让二手大众帕萨特，2019年上牌，行驶4万公里，车况良好，无事故，价格8.5万。', '[]', 85000.00, '元', 30, '车主', '13756789012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 4, 40, '出售二手挖掘机', '出售小松PC200-8挖掘机，2018年出厂，工作小时数5000+，性能良好，价格面议。', '[]', 280000.00, '元', 60, '工程部', '13867890123', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 4, 41, '转让雅迪电动车', '转让雅迪电动车一辆，72V20A，续航80公里，九成新，带防盗器，售价1800元。', '[]', 1800.00, '元', 15, '杨先生', '13978901234', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 4, 42, '专业汽车保养维修', '专业汽车保养维修店，提供换机油、轮胎、刹车片等服务，价格实惠，技术专业。', '[]', NULL, '', 90, '维修店', '15089012345', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 4, 43, '婚车租赁服务', '提供婚车租赁服务，奔驰、宝马、奥迪等车型齐全，价格优惠，专业司机，欢迎预订。', '[]', NULL, '', 90, '婚庆公司', '13690123456', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 出兑信息 (category_id=5, sub_category_id=44~45)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 5, 44, '旺铺转让：奶茶店', '繁华地段奶茶店转让，位置好，客源稳定，设备齐全，接手即可经营，转让费面议。', '[]', NULL, '', 30, '店主', '13701234567', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 5, 45, '餐饮项目招商合作', '特色餐饮项目招商，技术培训+原料供应+开店指导，低门槛创业，欢迎考察合作。', '[]', NULL, '', 60, '招商部', '13812345678', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 服务信息 (category_id=6, sub_category_id=46~52)
INSERT INTO `fa_info` (`user_id`, `category_id`, `sub_category_id`, `title`, `content`, `images`, `price`, `price_unit`, `valid_days`, `contact_name`, `contact_phone`, `status`, `entry_admin_id`, `createtime`, `updatetime`) VALUES
(0, 6, 46, '专业开锁换锁服务', '公安备案专业开锁换锁，上门服务，价格透明，技术开锁不伤门，24小时服务。', '[]', NULL, '', 90, '开锁师傅', '13923456789', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 6, 47, '专业装修队承接工程', '专业装修队承接家庭装修、店面装修、水电改造等工程，价格合理，质量保证。', '[]', NULL, '', 90, '装修队', '15034567890', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 6, 48, '家电维修上门服务', '专业维修空调、冰箱、洗衣机、电视等家电，上门服务，价格公道，修不好不收费。', '[]', NULL, '', 90, '维修师傅', '13645678901', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 6, 49, '搬家公司优惠接单', '正规搬家公司，专业团队，价格优惠，承接居民搬家、公司搬迁、钢琴搬运等业务。', '[]', NULL, '', 90, '搬家热线', '13756789012', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 6, 50, '广告设计印刷服务', '专业广告设计印刷，名片、传单、横幅、灯箱、展架等，设计精美，价格实惠。', '[]', NULL, '', 90, '广告公司', '13867890123', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 6, 52, '代驾服务24小时', '专业代驾服务，24小时随叫随到，价格透明，安全可靠，酒后请找代驾。', '[]', NULL, '', 90, '代驾小哥', '13978901234', 'published', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

COMMIT;
