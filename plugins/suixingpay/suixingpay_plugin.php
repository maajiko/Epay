<?php

class suixingpay_plugin
{
	static public $info = [
		'name'        => 'suixingpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '随行付', //支付插件显示名称
		'author'      => '随行付', //支付插件作者
		'link'        => 'https://www.suixingpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '机构编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '平台公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
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
			if($channel['appwxmp']>0 && checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($channel['appwxa']>0 && checkmobile()){
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
			}
		}elseif($method == 'applet'){
			return self::wxplugin();
			//return self::wxapplet();
		}
		elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($channel['appwxmp']>0 && $mdevice=='wechat'){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($channel['appwxa']>0 && $device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//扫码支付
	static private function qrcode($type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Suixingpay.class.php");

		$params = [
			'mno' => $channel['appmchid'],
			'ordNo' => TRADE_NO,
			'amt' => (string)$order['realmoney'],
			'payType' => $type,
			'subject' => $ordername,
			'trmIp' => $clientip,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];

		$client = new Suixingpay($channel['appid'], $channel['appkey'], $channel['appsecret']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->submit('/order/activeScan', $params);
		
			if($result['bizCode'] == '0000'){
				return $result['payUrl'];
			}else{
				throw new Exception('['.$result['bizCode'].']'.$result['bizMsg']);
			}
		});
	}

	//JS支付
	static private function jsapi($type, $subAppid, $userId, $is_mini = false){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Suixingpay.class.php");

		$payWay = $type=='WECHAT'&&$is_mini?'03':'02';

		$params = [
			'mno' => $channel['appmchid'],
			'ordNo' => TRADE_NO,
			'amt' => (string)$order['realmoney'],
			'payType' => $type,
			'payWay' => $payWay,
			'subject' => $ordername,
			'trmIp' => $clientip,
			'subAppid' => $subAppid,
			'userId' => $userId,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];

		$client = new Suixingpay($channel['appid'], $channel['appkey'], $channel['appsecret']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params, $type) {
			$result = $client->submit('/order/jsapiScan', $params);

			if($result['bizCode'] == '0000'){
				if($type=='WECHAT'){
					return ['appId'=>$result['payAppId'], 'timeStamp'=>$result['payTimeStamp'], 'nonceStr'=>$result['paynonceStr'], 'package'=>$result['payPackage'], 'signType'=>$result['paySignType'], 'paySign'=>$result['paySign']];
				}elseif($type=='ALIPAY'){
					return $result['source'];
				}elseif($type=='UNIONPAY'){
					return $result['redirectUrl'];
				}
			}else{
				throw new Exception('['.$result['bizCode'].']'.$result['bizMsg']);
			}
		});
	}

	//小程序收银台
	static private function appletPay($appletSource){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Suixingpay.class.php");

		$params = [
			'mno' => $channel['appmchid'],
			'ordNo' => TRADE_NO,
			'amt' => (string)$order['realmoney'],
			'appletSource' => $appletSource,
			'subject' => $ordername,
			'trmIp' => $clientip,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];

		$client = new Suixingpay($channel['appid'], $channel['appkey'], $channel['appsecret']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->submit('/order/appletScanPre', $params);
		
			if($result['bizCode'] == '0000'){
				return $result;
			}else{
				throw new Exception('['.$result['bizCode'].']'.$result['bizMsg']);
			}
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::qrcode('ALIPAY');
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
			$alipay_trade_no = self::jsapi('ALIPAY', '', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$alipay_trade_no];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$alipay_trade_no, 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			if($channel['appwxmp']>0 && $channel['appwxa']==0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}else{
			try{
				$code_url = self::qrcode('WECHAT');
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

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

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
			$pay_info = self::jsapi('WECHAT', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($pay_info), 'redirect_url'=>$redirect_url]];
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
			$pay_info = self::jsapi('WECHAT', $wxinfo['appid'], $openid, true);
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($pay_info, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
		try{
			$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
	}

	//微信小程序插件支付
	static public function wxplugin(){
		try{
			$result = self::appletPay('00');
			$payinfo = ['appId'=>'wx78f434e31e956fb8', 'amt'=>$result['amt'], 'key'=>$result['key']];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxplugin','data'=>$payinfo];
	}

	//半屏小程序支付
	static public function wxapplet(){
		try{
			$result = self::appletPay('01');
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>['appId'=>$result['appId'], 'path'=>$result['path']]];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		//file_put_contents('logs.txt', $json);
		$arr = json_decode($json,true);

		require(PAY_ROOT."inc/Suixingpay.class.php");
		
		$client = new Suixingpay($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($arr);

		if($verify_result) {//验证成功

			if ($arr['bizCode'] == '0000') {
				if($arr['ordNo'] == TRADE_NO){
					processNotify($order, $arr['sxfUuid'], $arr['buyerId'], $arr['transactionId']);
				}
				return ['type'=>'html','data'=>'{"code":"success","msg":"成功"}'];
			}else{
				return ['type'=>'html','data'=>'{"code":"fail","msg":"状态错误"}'];
			}
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'{"code":"fail","msg":"签名错误"}'];
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
		global $channel;
		if(empty($order))exit();

		require(PAY_ROOT."inc/Suixingpay.class.php");

		$params = [
			'mno' => $channel['appmchid'],
			'ordNo' => $order['refund_no'],
			'origOrderNo' => $order['trade_no'],
			'amt' => (string)$order['refundmoney'],
		];
		
		$client = new Suixingpay($channel['appid'], $channel['appkey'], $channel['appsecret']);
		try{
			$result = $client->submit('/order/refund', $params);
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}

        if (isset($result['bizCode']) && $result['bizCode'] == '0000') {
			return ['code'=>0, 'trade_no'=>$result['origOrderNo'], 'refund_fee'=>$result['amt']];
        } elseif(isset($result['bizCode'])) {
			return ['code'=>-1, 'msg'=>'['.$result['bizCode'].']'.$result['bizMsg']];
		}else{
			return ['code'=>-1, 'msg'=>'未知错误'];
		}
	}
}