<?php
$is_defend = true;
include("./inc.php");
@header('Content-Type: text/html; charset=UTF-8');

$biz_no = trim($_GET['n']);
$time = trim($_GET['t']);
$sign = trim($_GET['s']);
if(empty($biz_no) || empty($time) || empty($sign)) showerror('参数错误');
if(md5(SYS_KEY.$biz_no.$time.SYS_KEY) != $sign) showerror('签名错误');
if($time < time() - 86400) showerror('红包已过期，请重新获取二维码');

$trans = $DB->find('transfer', '*', ['biz_no' => $biz_no]);
if(!$trans) showerror('红包不存在');

if(isset($_GET['do']) && $_GET['do'] == 'success'){
    $receive_action = $trans['status'] == 1 ? '已存入' : '将稍后存入';
    $receive_name = $trans['type'] == 'alipay' ? '余额' : '零钱';
    include ROOT.'paypage/red_success.php';
    exit;
}

if($trans['status']==1){
    showerror('红包已领取');
}elseif($trans['status']==2){
    showerror($trans['result']);
}elseif($trans['status']!=4){
    showerror('红包状态异常，无法领取');
}
$channel = \lib\Channel::get($trans['channel']);
if(!$channel) showerror('当前支付通道信息不存在');

if($trans['type'] == 'alipay'){
    if(!checkalipay()) showerror('请在支付宝客户端打开链接');
    try{
        [$user_type, $openid] = alipay_oauth();
    }catch(Exception $e){
        showerror($e->getMessage());
    }
}elseif($trans['type'] == 'wxpay'){
    if(!checkwechat()) showerror('请在微信客户端打开链接');
    $wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
    if(!$wxinfo) showerror('支付通道绑定的微信公众号不存在');
    try{
        $openid = wechat_oauth($wxinfo);
    }catch(Exception $e){
        showerror($e->getMessage());
    }
    if($conf['transfer_wxpay_type'] == 1){
        $url = rtrim($siteurl, '/').$_SERVER['REQUEST_URI'];
        try{
            $wechat = new \lib\wechat\WechatAPI($wxinfo['id']);
            $wxconfig = $wechat->getJsapiConfig($wxinfo['appid'], $url, ['requestMerchantTransfer']);
            $wxconfig = json_encode($wxconfig);
        }catch(Exception $e){
            showerror($e->getMessage());
        }
        include ROOT.'paypage/red_confirmwx.php';
        exit;
    }
}else{
    showerror('不支持的红包类型');
}

include ROOT.'paypage/red_confirm.php';
exit;