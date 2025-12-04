CREATE TABLE IF NOT EXISTS `pre_suborder` (
  `sub_trade_no` varchar(25) NOT NULL,
  `trade_no` char(19) NOT NULL,
  `api_trade_no` varchar(150) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `refundmoney` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `settle` tinyint(1) NOT NULL DEFAULT '0',
 PRIMARY KEY (`sub_trade_no`),
 KEY `trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `pre_order`
ADD COLUMN `bill_mch_trade_no` varchar(150) DEFAULT NULL,
ADD INDEX `bill_mch_trade_no` (`bill_mch_trade_no`);

ALTER TABLE `pre_transfer`
ADD COLUMN `out_biz_no` varchar(150) NOT NULL DEFAULT '',
ADD INDEX `out_biz_no` (`out_biz_no`,`uid`);

ALTER TABLE `pre_user`
ADD COLUMN `voice_devid` varchar(30) DEFAULT NULL,
ADD COLUMN `voice_order` tinyint(1) NOT NULL DEFAULT '0';

ALTER TABLE `pre_psreceiver`
ADD COLUMN `info` varchar(1024) DEFAULT NULL;
ALTER TABLE `pre_psorder`
ADD COLUMN `rdata` text DEFAULT NULL;

ALTER TABLE `pre_psorder`
ADD COLUMN `sub_trade_no` varchar(25) DEFAULT NULL;

ALTER TABLE `pre_user`
MODIFY COLUMN `msgconfig` text DEFAULT NULL;

ALTER TABLE `pre_user`
ADD COLUMN `pay_maxmoney` varchar(10) DEFAULT NULL,
ADD COLUMN `pay_minmoney` varchar(10) DEFAULT NULL;

ALTER TABLE `pre_group`
ADD COLUMN `index` int(11) NOT NULL DEFAULT 0;

ALTER TABLE `pre_channel`
ADD COLUMN `daymaxorder` int(10) DEFAULT 0;

ALTER TABLE `pre_psorder`
ADD COLUMN `retry` tinyint(1) NOT NULL DEFAULT '0';

ALTER TABLE `pre_user`
ADD COLUMN `wxa_uid` varchar(32) DEFAULT NULL;

ALTER TABLE `pre_channel`
ADD COLUMN `timestart` int(11) DEFAULT NULL,
ADD COLUMN `timestop` int(11) DEFAULT NULL;

ALTER TABLE `pre_user`
ADD COLUMN `print_order` tinyint(1) NOT NULL DEFAULT '0',
ADD COLUMN `print_config` varchar(300) DEFAULT NULL;

ALTER TABLE `pre_order`
ADD COLUMN `cert_info` varchar(300) DEFAULT NULL;