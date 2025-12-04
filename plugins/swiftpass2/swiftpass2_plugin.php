<?php

class swiftpass2_plugin
{
	static public $info = [
		'name'        => 'swiftpass2', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '威富通MD5', //支付插件显示名称
		'author'      => '威富通', //支付插件作者
		'link'        => 'https://www.swiftpass.cn/', //支付插件作者链接
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
				'note' => '可不填,默认是https://pay.swiftpass.cn/pay/gateway',
			],
			'appswitch' => [
				'name' => '微信是否支持H5',
				'type' => 'select',
				'options' => [0=>'否',1=>'是'],
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()){
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
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
                if ($channel['appwxmp']>0) {
					return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
                }else{
					return self::wxjspay();
				}
			}elseif($device=='mobile'){
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
	static private function nativepay($service){
		global $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/SwiftpassClient.class.php');
		$pay_config = require(PAY_ROOT.'inc/SwiftpassConfig.php');
		
		$params = [
			'service' => $service,
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
	static private function weixinjspay($sub_appid, $sub_openid, $is_minipg = 0){
		global $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/SwiftpassClient.class.php');
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

	//微信H5支付
	static private function weixinh5pay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/SwiftpassClient.class.php');
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
		try{
			$code_url = self::nativepay('pay.alipay.native');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$code_url = self::nativepay('pay.weixin.native');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::nativepay('pay.tenpay.native');
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
			$code_url = self::nativepay('pay.unionpay.native');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//京东扫码支付
	static public function jdpay(){
		try{
			$code_url = self::nativepay('pay.jdpay.native');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'京东支付下单失败 '.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'jdpay_qrcode','url'=>$code_url];
	}


	//微信公众号支付
	static public function wxjspay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxmp']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];

			try{
				$openid = wechat_oauth($wxinfo);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			$blocks = checkBlockUser($openid, TRADE_NO);
			if($blocks) return $blocks;

			try{
				$pay_info = self::weixinjspay($wxinfo['appid'], $openid);
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
			}

			if($_GET['d']=='1'){
				$redirect_url='data.backurl';
			}else{
				$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
			}
			return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$pay_info, 'redirect_url'=>$redirect_url]];
		}else{
			$code_url = self::nativepay('unified.trade.native');
			return ['type'=>'jump','url'=>$code_url];
		}
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

		if($channel['appswitch']==1){
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

		require(PAY_ROOT.'inc/SwiftpassClient.class.php');
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

		require(PAY_ROOT.'inc/SwiftpassClient.class.php');
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