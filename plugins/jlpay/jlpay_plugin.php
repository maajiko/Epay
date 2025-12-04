<?php

class jlpay_plugin
{
	static public $info = [
		'name'        => 'jlpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '嘉联支付', //支付插件显示名称
		'author'      => '嘉联支付', //支付插件作者
		'link'        => 'https://www.jlpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用appid',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => 'SM2-Hex格式',
			],
			'appkey' => [
				'name' => '嘉联公钥',
				'type' => 'textarea',
				'note' => 'SM2-Hex格式',
			],
			'mch_id' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'term_no' => [
				'name' => '终端号',
				'type' => 'input',
				'note' => '',
			],
			'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '聚合扫码支付',
			'2' => '公众号/小程序支付',
		],
		'select_bank' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
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
			if(checkwechat() && in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
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
		}elseif($method == 'app' || $method == 'applet'){
			return self::wxapppay();
		}elseif($method=='scan'){
			return self::scanpay();
		}
		elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return self::wxjspay();
			}elseif($device=='mobile' && in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//扫码支付
	static private function qrcode($pay_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'pay_type' => $pay_type,
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'attach' => $order['name'],
			'total_fee' => strval(round($order['realmoney']*100)),
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'mch_create_ip' => $clientip,
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('/open/trans/qrcodepay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['transaction_id']);
			return $result['code_url'];
		});
	}

	//微信公众号/小程序支付
	static private function officialpay($openid, $appid){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'pay_type' => 'wxpay',
			'open_id' => $openid,
			'sub_appid' => $appid,
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'attach' => $order['name'],
			'total_fee' => strval(round($order['realmoney']*100)),
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'mch_create_ip' => $clientip,
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('/open/trans/officialpay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['transaction_id']);
			return $result['pay_info'];
		});
	}

	//支付宝服务窗/小程序支付
	static private function waph5pay($buyer_id){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'pay_type' => 'alipay',
			'buyer_id' => $buyer_id,
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'attach' => $order['name'],
			'total_fee' => strval(round($order['realmoney']*100)),
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'mch_create_ip' => $clientip,
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('/open/trans/waph5pay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['transaction_id']);
			return $result['pay_info'];
		});
	}

	//银联行业码支付
	static private function unionjspay($user_id){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'pay_type' => 'unionpay',
			'app_up_identifier' => get_unionpay_ua(),
			'user_auth_code' => $_SESSION['unionpay_auth_code'],
			'user_id' => $user_id,
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'attach' => $order['name'],
			'total_fee' => strval(round($order['realmoney']*100)),
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'mch_create_ip' => $clientip,
			'qr_code' => $siteurl,
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('/open/trans/unionjspay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['transaction_id']);
			return $result['pay_info'];
		});
	}

	//收银托管
	static private function cashierpay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'merch_no' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'out_trade_no' => TRADE_NO,
			'description' => $ordername,
			'attach' => $order['name'],
			'product_name' => $order['name'],
			'total_amount' => strval(round($order['realmoney']*100)),
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('/open/cashier/trans/trade/pre-order', $params);
			return $result;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::qrcode('alipay');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
			}
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	static public function alipayjs(){
		global $method, $order;
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
			$result = self::waph5pay($user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		$payinfo = json_decode($result, true);
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$payinfo['tradoNo']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$payinfo['tradoNo'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			if($channel['appwxmp']>0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}else{
			try{
				$code_url = self::qrcode('wxpay');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}

		if(checkwechat() || $mdevice=='wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
		try {
			$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
	}

	//微信公众号支付
	static public function wxjspay(){
		global $method, $channel, $order;

		//①、获取用户openid
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

		//②、统一下单
		try{
			$payinfo = self::officialpay($openid, $wxinfo['appid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$payinfo];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$payinfo, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		try{
			$openid = wechat_applet_oauth($code, $wxinfo);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');
		
		//②、统一下单
		try{
			$payinfo = self::officialpay($openid, $wxinfo['appid']);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//微信APP支付
	static public function wxapppay(){
		try{
			$result = self::cashierpay();
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>['appId'=>$result['jl_pay_appid'], 'miniProgramId'=>$result['gh_id'], 'path'=>$result['cashier_url']]];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('unionpay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if(checkunionpay()){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//云闪付JS支付
	static public function bankjs(){
		global $method, $order;
		try{
			$code_url = self::unionjspay($order['sub_openid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PLUGIN_ROOT.'jlpay/inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'pay_type' => 'unionpay',
			'auth_code' => $userAuthCode,
			'app_up_identifier' => get_unionpay_ua(),
		];

		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);
		try{
			$result = $client->execute('/open/trans/getopenid', $params);
			$_SESSION['unionpay_auth_code'] = $userAuthCode;
			return ['code'=>0,'data'=>$result['user_id']];
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//付款码支付
	static public function scanpay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/JlpayClient.php');

		$params = [
			'mch_id' => $channel['mch_id'],
			'term_no' => $channel['term_no'],
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'attach' => $order['name'],
			'total_fee' => strval(round($order['realmoney']*100)),
			'auth_code' => $order['auth_code'],
			'mch_create_ip' => $clientip,
		];

		try{
			$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);
			$result = $client->execute('/open/trans/micropay', $params);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'被扫下单失败！'.$e->getMessage()];
		}

		if($result['status'] == '2'){
			$bill_trade_no = $result['chn_transaction_id'];
			if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
			processNotify($order, $result['transaction_id'], $result['sub_openid'], $bill_trade_no);
			return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['out_trade_no'], 'api_trade_no'=>$result['transaction_id'], 'buyer'=>$result['sub_openid'], 'money'=>strval(round($result['total_fee']/100, 2))]];
		}else{
			$transaction_id = $result['transaction_id'];
			$retry = 0;
			$success = false;
			while($retry < 6){
				sleep(3);
				try{
					$result = self::orderQuery($client, $transaction_id);
				}catch(Exception $e){
					return ['type'=>'error','msg'=>'订单查询失败:'.$e->getMessage()];
				}
				if($result['status'] == '2'){
					$success = true;
					break;
				}elseif($result['tranSts'] != '1'){
					return ['type'=>'error','msg'=>'订单超时或用户取消支付'];
				}
				$retry++;
			}
			if($success){
				$bill_trade_no = $result['chn_transaction_id'];
				if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
				processNotify($order, $result['transaction_id'], $result['sub_openid'], $bill_trade_no);
				return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['out_trade_no'], 'api_trade_no'=>$result['transaction_id'], 'buyer'=>$result['sub_openid'], 'money'=>strval(round($result['total_fee']/100, 2))]];
			}else{
				try{
					self::orderClose($client, $transaction_id);
				}catch(Exception $e){
				}
				return ['type'=>'error','msg'=>'被扫下单失败！订单已超时'];
			}
		}
	}

	static private function orderQuery($client, $transaction_id){
		global $channel;
		$params = [
			'mch_id' => $channel['mch_id'],
			'transaction_id' => $transaction_id,
		];
		$result = $client->execute('/open/trans/chnquery', $params);
		return $result;
	}

	static private function orderClose($client, $transaction_id){
		global $channel, $clientip;
		$params = [
			'mch_id' => $channel['mch_id'],
			'out_trade_no' => date('YmdHis').rand(1000,9999),
			'ori_transaction_id' => $transaction_id,
			'mch_create_ip' => $clientip,
		];
		$result = $client->execute('/open/trans/cancel', $params);
		return $result;
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) return ['type'=>'json','data'=>['ret_code'=>'00002', 'ret_msg'=>'no data']];

		require(PAY_ROOT."inc/JlpayClient.php");
		
		$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);
		$verify_result = $client->verifyNotify($json);

		if($verify_result) {//验证成功

			if ($arr['status'] == '2') {
				$out_trade_no = $arr['out_trade_no'];
				$api_trade_no = $arr['transaction_id'];
				$money = $arr['total_fee'];
				$buyer = $arr['sub_openid'];
				$bill_trade_no = $arr['chn_transaction_id'];
				if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'json','data'=>['ret_code'=>'00000']];
		}
		else {
			return ['type'=>'json','data'=>['ret_code'=>'00001', 'ret_msg'=>'sign fail']];
		}
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//退款
	static public function refund($order){
		global $channel, $clientip;
		if(empty($order))exit();

		require(PAY_ROOT."inc/JlpayClient.php");

		$params = [
			'mch_id' => $channel['mch_id'],
			'out_trade_no' => $order['refund_no'],
			'ori_transaction_id' => $order['api_trade_no'],
			'total_fee' => strval(round($order['refundmoney']*100)),
			'mch_create_ip' => $clientip,
		];

		try{
			$client = new JlpayClient($channel['appid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']);
			$result = $client->execute('/open/trans/refund', $params);
			return ['code'=>0, 'trade_no'=>$result['transaction_id'], 'refund_fee'=>$result['total_fee']/100];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}
}