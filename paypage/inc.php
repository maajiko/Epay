<?php
include("../includes/common.php");

function showerror($msg){
	global $cdnpublic;
	include ROOT.'paypage/error.php';
	exit;
}

function showerrorjson($msg){
	$result = ['code'=>-1, 'msg'=>$msg];
	exit(json_encode($result));
}

function check_paytype(){
	$type=null;
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger/')!==false){
		$type='wxpay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient/')!==false){
		$type='alipay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
		$type='qqpay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'UnionPay/')!==false){
		$type='bank';
	}
	return $type;
}

function alipayOpenId($channel){
	$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
	try{
		[$user_type, $user_id] = alipay_oauth($alipay_config);
	}catch(Exception $e){
		showerror($e->getMessage());
	}
	return $user_id;
}

function weixinOpenId($channel){
	$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
	if(!$wxinfo)showerror('支付通道绑定的微信公众号不存在');

	try{
		$openId = wechat_oauth($wxinfo);
	}catch(Exception $e){
		showerror($e->getMessage());
	}
	return $openId;
}

function unionpayOpenId($channel){
	if(isset($_GET['respCode'])){
		if($_GET['respCode'] == '00' && isset($_GET['userAuthCode'])){
			$result = \lib\Plugin::call('get_unionpay_userid', $channel, $_GET['userAuthCode']);
			if($result['code'] == 0){
				return $result['data'];
			}else{
				showerror('银联用户标识获取失败：'.$result['msg']);
			}
		}elseif($_GET['respCode'] == '34'){
			return '';
		}else{
			showerror('银联用户标识获取失败，respCode='.$_GET['respCode']);
		}
	}else{
		$redirect_uri = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$url = 'https://qr.95516.com/qrcGtwWeb-web/api/userAuth?version=1.0.0&redirectUrl='.urlencode($redirect_uri);
		header("Location: $url");
		exit;
	}
}
