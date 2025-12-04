<?php
$nosession = true;
require './includes/common.php';

@header('Content-Type: application/json; charset=UTF-8');

function voicemsg($msg){
    exit(json_encode(['cmd'=>'voice', 'msg'=>$msg], JSON_UNESCAPED_UNICODE));
}

$json = file_get_contents('php://input');
$arr = json_decode($json,true);
if(!$arr || !isset($arr['cmd'])) exit;
//file_put_contents('gateway.txt', $json);

if($arr['cmd'] == 'heartbeat'){
    $result = ['cmd'=>'heartbeat'];
    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}elseif($arr['cmd'] == 'qrcode'){
    $sn = $arr['sn'];
    if(!$sn) voicemsg('设备号不能为空');
    $userrow=$DB->getRow("SELECT `uid`,`gid`,`key`,`money`,`mode`,`pay`,`cert`,`status`,`channelinfo`,`qq`,`ordername`,`keytype`,`publickey` FROM `pre_user` WHERE `voice_devid`=:voice_devid LIMIT 1", [':voice_devid'=>$sn]);
    if(!$userrow) voicemsg('设备关联的商户不存在');
    if($userrow['status']==0 || $userrow['pay']==0) voicemsg('当前商户已被封禁');
    $uid = $userrow['uid'];

    $auth_code = base64_decode($arr['data']);
    if(!$auth_code) voicemsg('二维码解析失败');

    $money = $arr['money'];
    if($money <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) voicemsg('金额错误');
    if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney']) voicemsg('最大支付金额是'.$conf['pay_maxmoney'].'元');
	if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney']) voicemsg('最小支付金额是'.$conf['pay_minmoney'].'元');

    $trade_no = date("YmdHis").rand(11111,99999);
    $out_trade_no = $arr['msgid'];
    if(!preg_match('/^[a-zA-Z0-9.\_\-|]+$/',$out_trade_no))voicemsg('订单号格式不正确');
    $method = 'scan';
    $name = '付款码收款';
    $type = getScanPayType($auth_code);
    if($type == 'unknown') voicemsg('未知的付款码类型');
    $typeshowname = $DB->findColumn('type', 'showname', ['name'=>$type]);

    $return_url=$siteurl;
    $domain=getdomain($return_url);
    if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`) VALUES (:trade_no, :out_trade_no, :uid, 3, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$out_trade_no, ':uid'=>$uid, ':name'=>$name, ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip]))voicemsg('创建订单失败');

    $submitData = \lib\Channel::submit($type, $userrow['uid'], $userrow['gid'], $money);
    if(!$submitData) voicemsg('没有可用的支付通道');
    $realmoney = sprintf("%.2f", $money);
    $getmoney = round($money*$submitData['rate']/100,2);

    if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
        voicemsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
    }

    $DB->update('order', ['type'=>$submitData['typeid'], 'channel'=>$submitData['channel'], 'subchannel'=>$submitData['subchannel'], 'realmoney'=>$realmoney, 'getmoney'=>$getmoney], ['trade_no'=>$trade_no]);
    
    $order['trade_no'] = $trade_no;
    $order['out_trade_no'] = $out_trade_no;
    $order['uid'] = $uid;
    $order['tid'] = 3;
    $order['addtime'] = date('Y-m-d H:i:s');
    $order['name'] = $name;
    $order['realmoney'] = $realmoney;
    $order['getmoney'] = $getmoney;
    $order['money'] = $money;
    $order['type'] = $submitData['typeid'];
    $order['channel'] = $submitData['channel'];
    $order['subchannel'] = $submitData['subchannel'];
    $order['typename'] = $submitData['typename'];
    $order['profits'] = \lib\Payment::updateOrderProfits($order, $submitData['plugin']);
    $order['auth_code'] = $auth_code;

    try{
        $res = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no, true);
    }catch(Exception $e){
        voicemsg($e->getMessage());
    }
    if($res['type'] == 'error') voicemsg(preg_replace('/\[[^\]]*\]/', '', $res['msg']));
    if($res['type'] != 'scan') voicemsg('当前支付插件不支持付款码支付');
    $result = ['cmd'=>'voice', 'msg'=>$typeshowname.'收款'.$money.'元', 'money'=>$money];
    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}