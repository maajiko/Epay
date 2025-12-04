<?php

class easypay_plugin
{
	static public $info = [
		'name'        => 'easypay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '易生易企通', //支付插件显示名称
		'author'      => '易生', //支付插件作者
		'link'        => 'https://www.easypay.com.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'reqtype' => [
				'name' => '接入模式',
				'type' => 'select',
				'options' => [2=>'机构模式',1=>'商户模式'],
			],
			'appid' => [
				'name' => '机构号/商户号',
				'type' => 'input',
				'note' => 'reqId',
			],
			'appmchid' => [
				'name' => '子商户号',
				'type' => 'input',
				'note' => '机构模式下填写子商户号，非机构模式请勿填写',
			],
			'appkey' => [
				'name' => '易生公钥',
				'type' => 'textarea',
				'note' => '不能有换行和标签',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '不能有换行和标签',
			],
			'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '主扫支付',
			'2' => 'JSAPI支付',
		],
		'select_bank' => [
			'1' => '主扫支付',
			'2' => 'JSAPI支付',
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
			}elseif(checkmobile() && $channel['appwxa']>0){
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
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//扫码支付接口
	static private function qrcode($pay_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/EasypayClient.php");

		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => TRADE_NO,
				'transAmount' => intval(round($order['realmoney']*100)),
				'orderSub' => $ordername,
				'backUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			],
			'payInfo' => [
				'payType' => $pay_type,
				'transDate' => date('Ymd'),
			],
			'settleParamInfo' => [
				'delaySettleFlag' => '0',
				'patnerSettleFlag' => '0',
				'splitSettleFlag' => '0',
			],
			'riskData' => [
				'customerIp' => $clientip,
			],
		];
		if(strpos($pay_type, 'UnionPay') === 0){
			$params['qrBizParam'] = [
				'transType' => '10',
				'areaInfo' => '1561000',
			];
		}

		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);
		$result = \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			return $client->execute('/trade/native', $params);
		});
		
		if($channel['appswitch']==1){
			echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
		}
		if($result['respStateInfo']['respCode'] == '000000'){
			if($result['respStateInfo']['transState'] == 'X'){
				if(isset($result['respStateInfo']['appendRetCode'])){
					throw new Exception('['.$result['respStateInfo']['appendRetCode'].']'.$result['respStateInfo']['appendRetMsg']);
				}else{
					throw new Exception($result['respStateInfo']['transStatusDesc']);
				}
			}
			\lib\Payment::updateOrder(TRADE_NO, $result['respOrderInfo']['outTrace']);
			return $result['respOrderInfo']['qrCode'];
		}else{
			throw new Exception($result['respStateInfo']['respDesc']);
		}
	}

	//JSAPI支付接口
	static private function jsapi($pay_type, $openid, $appid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/EasypayClient.php");

		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => TRADE_NO,
				'transAmount' => intval(round($order['realmoney']*100)),
				'orderSub' => $ordername,
				'backUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			],
			'payInfo' => [
				'payType' => $pay_type,
				'transDate' => date('Ymd'),
			],
			'settleParamInfo' => [
				'delaySettleFlag' => '0',
				'patnerSettleFlag' => '0',
				'splitSettleFlag' => '0',
			],
			'riskData' => [
				'customerIp' => $clientip,
			],
		];
		if(strpos($pay_type, 'AliPay') === 0){
			$params['aliBizParam'] = [
				'buyerId' => $openid,
			];
		}elseif(strpos($pay_type, 'WeChat') === 0){
			$params['wxBizParam'] = [
				'subAppid' => $appid,
				'subOpenId' => $openid,
			];
		}elseif(strpos($pay_type, 'UnionPay') === 0){
			$params['qrBizParam'] = [
				'userAuthCode' => $_SESSION['unionpay_auth_code'],
				'userId' => $openid,
				'qrCode' => $siteurl,
				'paymentValidTime' => 1800,
				'transType' => '10',
				'areaInfo' => '1561000',
			];
		}

		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);
		$result = \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			return $client->execute('/trade/jsapi', $params);
		});
		
		if($result['respStateInfo']['respCode'] == '000000'){
			if($result['respStateInfo']['transState'] == 'X'){
				if(isset($result['respStateInfo']['appendRetCode'])){
					throw new Exception('['.$result['respStateInfo']['appendRetCode'].']'.$result['respStateInfo']['appendRetMsg']);
				}else{
					throw new Exception($result['respStateInfo']['transStatusDesc']);
				}
			}
			\lib\Payment::updateOrder(TRADE_NO, $result['respOrderInfo']['outTrace']);
			if(strpos($pay_type, 'AliPay') === 0){
				return $result['aliRespParamInfo']['tradeNo'];
			}elseif(strpos($pay_type, 'WeChat') === 0){
				return $result['wxRespParamInfo']['wcPayData'];
			}elseif(strpos($pay_type, 'UnionPay') === 0){
				return $result['qrRespParamInfo']['qrRedirectUrl'];
			}
		}else{
			throw new Exception($result['respStateInfo']['respDesc']);
		}
	}

	static public function query(){
		global $siteurl, $channel, $order;
		require_once(PAY_ROOT."inc/EasypayClient.php");
		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => date('YmdHis').rand(100000,999999),
				'oriOrgTrace' => TRADE_NO,
				'oriTransDate' => substr(TRADE_NO, 0, 8),
			],
			'payInfo' => [
				'transDate' => date('Ymd'),
			],
		];

		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);
		$result = $client->execute('/trade/tradeQuery', $params);
		if($channel['appswitch']==1){
			echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
		}
		print_r($result);
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::qrcode('AliPayNative');
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
			$alipay_trade_no = self::jsapi('AliPayJsapi', $user_id);
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
		global $channel, $siteurl, $device, $mdevice;

		if($channel['appwxa']>0 && $channel['appwxmp']==0){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}

		if(checkwechat() || $mdevice == 'wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device == 'mobile') {
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
		global $siteurl, $channel, $order, $method;

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
			$payinfo = self::jsapi('WeChatJsapi', $openid, $wxinfo['appid']);
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
			$payinfo = self::jsapi('WeChatMiniApp', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('UnionPayNative');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//云闪付JS支付
	static public function bankjs(){
		global $method, $order;
		try{
			$code_url = self::jsapi('UnionPayJsapi', $order['sub_openid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PAY_ROOT."inc/EasypayClient.php");
		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => date('YmdHis').rand(100000,999999),
				'authCode' => $userAuthCode,
				'appUpIdentifier' => get_unionpay_ua(),
			],
		];

		try{
			$result = $client->execute('/trade/user/getQrUserId', $params);
			if($result['respStateInfo']['respCode'] == '000000'){
				$_SESSION['unionpay_auth_code'] = $userAuthCode;
				return ['code'=>0,'data'=>$result['respOrderInfo']['userId']];
			}else{
				return ['code'=>-1, 'msg'=>$result['respStateInfo']['respDesc']];
			}
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) return ['type'=>'json','data'=>['code'=>'400001','msg'=>'no data']];

		require_once(PAY_ROOT."inc/EasypayClient.php");
		
		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);
		$verify_result = $client->verifySign($arr['reqHeader'], $arr['reqBody'], $arr['reqSign']);

		if($verify_result) {//验证成功
			$data = $arr['reqBody'];
			if ($data['respStateInfo']['transState'] == '0' || $data['respStateInfo']['transState'] == '1') {
				$out_trade_no = $data['respOrderInfo']['orgTrace'];
				$api_trade_no = $data['respOrderInfo']['outTrace'];
				$money = $data['respOrderInfo']['transAmount'];
				$buyer = $data['respOrderInfo']['userId'];
				$bill_trade_no = $data['respOrderInfo']['pcTrace'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'json','data'=>['code'=>'000000','msg'=>'Success']];
		}
		else {
			return ['type'=>'json','data'=>['code'=>'100001','msg'=>'sign error']];
		}
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once(PAY_ROOT."inc/EasypayClient.php");
		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => $order['refund_no'],
				'oriOutTrace' => $order['api_trade_no'],
				'oriTransDate' => substr($order['trade_no'], 0, 8),
				'refundAmount' => intval(round($order['refundmoney']*100)),
			],
			'payInfo' => [
				'transDate' => date('Ymd'),
			],
		];
		
		try{
			$result = $client->execute('/trade/refund/apply', $params);
			if($result['respStateInfo']['respCode'] == '000000'){
				if($result['respStateInfo']['transState'] == 'X'){
					if(isset($result['respStateInfo']['appendRetCode'])){
						return ['code'=>-1, 'msg'=>'['.$result['respStateInfo']['appendRetCode'].']'.$result['respStateInfo']['appendRetMsg']];
					}else{
						return ['code'=>-1, 'msg'=>$result['respStateInfo']['transStatusDesc']];
					}
				}
				return ['code'=>0, 'trade_no'=>$result['outTrace'], 'refund_fee'=>$result['transAmt']/100];
			}else{
				return ['code'=>-1, 'msg'=>$result['respStateInfo']['respDesc']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//退款查询
	static public function refundquery(){
		global $channel, $order;

		require_once(PAY_ROOT."inc/EasypayClient.php");
		$client = new EasypayClient($channel['appid'],$channel['reqtype'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		$refund_no = '2025052715162033617';
		$params = [
			'reqInfo' => [
				'mchtCode' => $channel['reqtype'] == 2 ? $channel['appmchid'] : $channel['appid'],
			],
			'reqOrderInfo' => [
				'orgTrace' => date('YmdHis').rand(100000,999999),
				'oriOrgTrace' => $refund_no,
				'oriTransDate' => substr($refund_no, 0, 8),
			],
			'payInfo' => [
				'transDate' => date('Ymd'),
			],
		];
		
		$result = $client->execute('/trade/refund/query', $params);
		if($channel['appswitch']==1){
			echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
		}
		print_r($result);
	}
}