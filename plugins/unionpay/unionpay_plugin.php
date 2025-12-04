<?php

/**
 * https://up.95516.com/open/openapi?code=unionpay
 */
class unionpay_plugin
{
	static public $info = [
		'name'        => 'unionpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '银联前置', //支付插件显示名称
		'author'      => '银联', //支付插件作者
		'link'        => 'http://www.95516.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank','jdpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '',
			],
			'appurl' => [
				'name' => '自定义网关URL',
				'type' => 'input',
				'note' => '可不填,默认是https://qra.95516.com/pay/gateway',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => '服务窗支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
			'3' => 'H5支付',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			if(checkalipay() && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && ($channel['appwxa']>0 || in_array('3',$channel['apptype']))){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='qqpay'){
			return ['type'=>'jump','url'=>'/pay/qqpay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='jdpay'){
			return ['type'=>'jump','url'=>'/pay/jdpay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		if($method=='jsapi'){
			if($order['typename']=='alipay'){
				return self::alipayjs();
			}elseif($order['typename']=='wxpay'){
				return self::wxjspay();
			}elseif($order['typename']=='bank'){
				return self::bankjs();
			}
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
                return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && ($channel['appwxa']>0 || in_array('3',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='qqpay'){
			return self::qqpay();
		}elseif($order['typename']=='jdpay'){
			return self::jdpay();
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//扫码通用
	static private function nativepay(){
		global $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'unified.trade.native',
			'body' => $ordername,
			'total_fee' => strval($order['realmoney']*100),
			'mch_create_ip' => $clientip,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];

		$client = new SwiftpassClient($pay_config);
		$result = $client->requestApi($params);
		$code_url = $result['code_url'];
		if(strpos($code_url,'myun.tenpay.com')){
			$qrcode=explode('&t=',$code_url);
			$code_url = 'https://qpay.qq.com/qr/'.$qrcode[1];
		}
		return $code_url;
	}

	//微信JS支付
	static private function weixinjspay($sub_appid, $sub_openid, $is_minipg = '0'){
		global $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'pay.weixin.jspay',
			'is_raw' => '1',
			'is_minipg' => strval($is_minipg),
			'body' => $ordername,
			'sub_appid' => $sub_appid,
			'sub_openid' => $sub_openid,
			'total_fee' => strval($order['realmoney']*100),
			'mch_create_ip' => $clientip,
			'out_trade_no' => TRADE_NO,
			'device_info' => 'AND_WAP',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];

		$client = new SwiftpassClient($pay_config);
		$result = $client->requestApi($params);
		$pay_info = $result['pay_info'];
		return $pay_info;
	}

	//支付宝服务窗支付
	static private function alipayjspay($buyer_id){
		global $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'pay.alipay.jspay',
			'body' => $ordername,
			'total_fee' => strval($order['realmoney']*100),
			'mch_create_ip' => $clientip,
			'out_trade_no' => TRADE_NO,
			'buyer_id' => $buyer_id,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];

		$client = new SwiftpassClient($pay_config);
		$result = $client->requestApi($params);
		$pay_info = $result['pay_info'];
		return $pay_info;
	}

	//微信H5支付
	static private function weixinh5pay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'pay.weixin.wappay',
			'body' => $ordername,
			'total_fee' => strval($order['realmoney']*100),
			'mch_create_ip' => $clientip,
			'out_trade_no' => TRADE_NO,
			'device_info' => 'AND_WAP',
			'mch_app_name' => $conf['sitename'],
			'mch_app_id' => $siteurl,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'callback_url' => $siteurl.'pay/return/'.TRADE_NO.'/'
		];

		$client = new SwiftpassClient($pay_config);
		$result = $client->requestApi($params);
		$pay_info = $result['pay_info'];
		return $pay_info;
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::nativepay();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝支付下单失败 '.$ex->getMessage()];
			}
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	static public function alipayjs(){
		global $conf, $method, $order;
		if(!empty($order['sub_openid'])){
			$user_id = $order['sub_openid'];
		}else{
			[$user_type, $user_id] = alipay_oauth();
		}

		$blocks = checkBlockUser($user_id, TRADE_NO);
		if($blocks) return $blocks;
		if($user_type == 'openid'){
			return ['type'=>'error','msg'=>'支付宝快捷登录获取uid失败，需将用户标识切换到uid模式'];
		}

		try{
			$result = self::alipayjspay($user_id);
			$trade_no = json_decode($result, true)['tradeNO'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$trade_no];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$trade_no, 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		try{
			$code_url = self::nativepay();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if(checkwechat() || $mdevice=='wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::nativepay();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败 '.$ex->getMessage()];
		}

		if(checkmobile() && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::nativepay();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败 '.$ex->getMessage()];
		}

		if(checkunionpay()){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//云闪付JS支付
	static public function bankjs(){
		global $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'pay.unionpay.jspay',
			'body' => $ordername,
			'user_id' => $order['sub_openid'],
			'total_fee' => strval($order['realmoney']*100),
			'mch_create_ip' => $clientip,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];

		try{
			$client = new SwiftpassClient($pay_config);
			$result = $client->requestApi($params);
			$code_url = $result['pay_url'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');

		$params = [
			'service' => 'pay.unionpay.userid',
			'user_auth_code' => $userAuthCode,
			'app_up_identifier' => get_unionpay_ua(),
		];

		try{
			$client = new SwiftpassClient($pay_config);
			$result = $client->requestApi($params);
			return ['code'=>0, 'data'=>$result['user_id']];
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//京东扫码支付
	static public function jdpay(){
		try{
			$code_url = self::nativepay();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'京东支付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'jdpay_qrcode','url'=>$code_url];
	}


	//微信公众号支付
	static public function wxjspay(){
		global $siteurl,$channel, $order, $method, $conf, $clientip;

		if(!empty($order['sub_openid'])){
			if(!empty($order['sub_appid'])){
				$wxinfo['appid'] = $order['sub_appid'];
			}else{
				$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
				if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			}
			$openid = $order['sub_openid'];
		}else{
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			try{
				$openid = wechat_oauth($wxinfo);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		try{
			$pay_info = self::weixinjspay($wxinfo['appid'], $openid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$pay_info];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$pay_info, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');

		try{
			$openid = wechat_applet_oauth($code, $wxinfo);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		try{
			$pay_info = self::weixinjspay($wxinfo['appid'], $openid, '1');
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($pay_info, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('3',$channel['apptype'])){
			try{
				$pay_info = self::weixinh5pay();
				return ['type'=>'jump','url'=>$pay_info];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
			}
		}elseif($channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		try{
			$client = new SwiftpassClient($pay_config);
			$result = $client->notify();
			if($result['status'] == '0' && $result['result_code'] == '0'){
				if($result['out_trade_no'] == TRADE_NO && $result['total_fee']==strval($order['realmoney']*100)){
					processNotify($order, $result['transaction_id'], $result['openid']);
				}
				return ['type'=>'html','data'=>'success'];
			}else{
				return ['type'=>'html','data'=>'failure'];
			}
		}catch(Exception $e){
			return ['type'=>'html','data'=>$e->getMessage()];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => 'unified.trade.refund',
			'transaction_id' => $order['api_trade_no'],
			'out_refund_no' => $order['refund_no'],
			'total_fee' => strval($order['realmoney']*100),
			'refund_fee' => strval($order['refundmoney']*100),
			'op_user_id' => $pay_config['mchid'],
		];

		try{
			$client = new SwiftpassClient($pay_config);
			$data = $client->requestApi($params);
			$result = ['code'=>0, 'trade_no'=>$data['refund_id'], 'refund_fee'=>$data['refund_fee']];
		}catch(Exception $e){
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}
}