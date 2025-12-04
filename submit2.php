<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';
$submit2=true;

@header('Content-Type: text/html; charset=UTF-8');

$typeid=intval($_GET['typeid']);
$trade_no=daddslashes($_GET['trade_no']);
$order=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' LIMIT 1");
if(!$order)sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($order['status']>0){
	sysmsg('该订单('.$order['out_trade_no'].')已完成支付，请勿重复发起支付');
}
$firstGetChannel = true;
if($order['type'] > 0 && $order['channel'] > 0 && $order['realmoney'] > 0 && $order['getmoney'] > 0){
	$firstGetChannel = false;
	if($typeid != $order['type']){
		sysmsg('该订单已选择支付方式，如需更换其他支付方式请返回网站重新下单');
	}
	$typeid = $order['type'];
}

// 获取订单支付方式ID、支付插件、支付通道、支付费率
$userrow = $DB->getRow("SELECT `uid`,`gid`,`money`,`mode`,`channelinfo`,`ordername` FROM `pre_user` WHERE `uid`='{$order['uid']}' LIMIT 1");
$groupconfig = getGroupConfig($userrow['gid']);
$conf = array_merge($conf, $groupconfig);

if($firstGetChannel){
	$submitData = \lib\Channel::submit2($typeid, $userrow['uid'], $userrow['gid'], $order['money']);
	if(!$submitData){
		sysmsg('<center>当前支付方式无法使用</center>', '跳转提示');
	}

	if($userrow['mode']==1 && $order['tid']!=4 || $order['tid']==2){ //订单加费模式（排除购买用户组）或余额充值
		$realmoney = round($order['money']*(100+100-$submitData['rate'])/100,2);
		$getmoney = $order['money'];
		if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
			$feemoney = round($order['money']*(100-$submitData['rate'])/100,2);
			if($feemoney < round($conf['payfee_lessthan'], 2)){
				$realmoney = round($order['money'] + $conf['payfee_mincost'], 2);
			}
		}
	}else{
		$realmoney = $order['money'];
		$getmoney = round($order['money']*$submitData['rate']/100,2);
		if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
			$feemoney = round($order['money']*(100-$submitData['rate'])/100,2);
			if($feemoney < round($conf['payfee_lessthan'], 2)){
				$getmoney = round($order['money'] - $conf['payfee_mincost'], 2);
				if($getmoney < 0) $getmoney = 0;
			}
		}
	}
}else{
	$submitData = \lib\Channel::info($order['channel']);
	$submitData['typename'] = $DB->getColumn("SELECT name FROM pre_type WHERE id='{$typeid}' LIMIT 1");
	$submitData['subchannel'] = $order['subchannel'];
	$realmoney = $order['realmoney'];
	$getmoney = $order['getmoney'];
}

// 判断通道单笔支付限额
if(!empty($submitData['paymin']) && $submitData['paymin']>0 && $order['money']<$submitData['paymin']){
	sysmsg('<center>当前支付方式单笔最小限额为'.$submitData['paymin'].'元，请选择其他支付方式！</center>', '跳转提示');
}
if(!empty($submitData['paymax']) && $submitData['paymax']>0 && $order['money']>$submitData['paymax']){
	sysmsg('<center>当前支付方式单笔最大限额为'.$submitData['paymax'].'元，请选择其他支付方式！</center>', '跳转提示');
}
// 商户直清模式判断商户余额
if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
	sysmsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
}

if($firstGetChannel){
	// 随机增减金额
	if(empty($order['realmoney'])&&!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = round($realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2)), 2);

	$resCount = $DB->update('order', ['type'=>$submitData['typeid'], 'channel'=>$submitData['channel'], 'subchannel'=>$submitData['subchannel'], 'realmoney'=>$realmoney, 'getmoney'=>$getmoney], ['trade_no'=>$trade_no, 'channel'=>0]);
	if($resCount == 0) sysmsg('更新订单失败，请返回重试！');
}


$order['realmoney'] = $realmoney;
$order['type'] = $submitData['typeid'];
$order['channel'] = $submitData['channel'];
$order['subchannel'] = $submitData['subchannel'];
$order['typename'] = $submitData['typename'];
$order['plugin'] = $submitData['plugin'];
$order['profits'] = \lib\Payment::updateOrderProfits($order, $submitData['plugin']);

try{
	$result = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no);
	$result['submit'] = true;
	\lib\Payment::echoDefault($result);
}catch(Exception $e){
	sysmsg($e->getMessage());
}
