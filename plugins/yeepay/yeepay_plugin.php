<?php

class yeepay_plugin
{
	static public $info = [
		'name'        => 'yeepay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '易宝支付', //支付插件显示名称
		'author'      => '易宝支付', //支付插件作者
		'link'        => 'https://www.yeepay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appkey' => [
				'name' => '应用标识',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appid' => [
				'name' => '发起方商户编号',
				'type' => 'input',
				'note' => '标准商户则填写标准商户商编；平台商入驻商户，则填写平台商商编',
			],
			'appmchid' => [
				'name' => '收款商户编号',
				'type' => 'input',
				'note' => '留空则与发起方商户编号一致',
			],
			'appswitch' => [
				'name' => '支付场景',
				'type' => 'select',
				'options' => [0=>'线上',1=>'线下'],
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
			'3' => '托管支付',
		],
		'note' => '密钥需要选RSA格式的', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/'];
			}elseif(checkmobile() && (in_array('3',$channel['apptype']) || $channel['appwxa']>0)){
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
			return self::wxapppay();
		}
		elseif($method == 'app'){
			if($order['typename']=='alipay'){
				return self::aliapppay();
			}else{
				return self::wxapppay();
			}
		}
		elseif($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/'];
			}elseif($device=='mobile' && (in_array('3',$channel['apptype']) || $channel['appwxa']>0)){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//聚合支付托管下单
	static private function tutelage_pay($payWay, $payType, $return_type = false){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/YopClient.php');

		$params = [
			'parentMerchantNo' => $channel['appid'],
			'merchantNo' => empty($channel['appmchid'])?$channel['appid']:$channel['appmchid'],
			'orderId' => TRADE_NO,
			'orderAmount' => $order['realmoney'],
			'goodsName' => $ordername,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'payWay' => $payWay,
			'channel' => $payType,
			'scene' => $channel['appswitch'] == 1 ? 'OFFLINE' : 'ONLINE',
			'userIp' => $clientip,
			'redirectUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($order['profits']){
			self::handleProfits($params);
		}

		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params, $return_type) {
			$result = $client->post('/rest/v1.0/aggpay/tutelage/pre-pay', $params);
        	if($result['code'] == '00000'){
				return $return_type ? ['appId'=>$result['appId'],'miniProgramPath'=>$result['miniProgramPath'],'miniProgramOrgId'=>$result['miniProgramOrgId']] : $result['prePayTn'];
			}else{
				throw new Exception('['.$result['code'].']'.$result['message']);
			}
		});
	}

	//聚合支付统一下单
	static private function pre_pay($payWay, $payType, $appId = null, $userId = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/YopClient.php');

		$params = [
			'parentMerchantNo' => $channel['appid'],
			'merchantNo' => empty($channel['appmchid'])?$channel['appid']:$channel['appmchid'],
			'orderId' => TRADE_NO,
			'orderAmount' => $order['realmoney'],
			'goodsName' => $ordername,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'redirectUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'payWay' => $payWay,
			'channel' => $payType,
			'scene' => $channel['appswitch'] == 1 ? 'OFFLINE' : 'ONLINE',
			'userIp' => $clientip,
		];
		if($appId) $params['appId'] = $appId;
		if($userId) $params['userId'] = $userId;
		if($order['profits']){
			self::handleProfits($params);
		}

		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->post('/rest/v1.0/aggpay/pre-pay', $params);
        	if($result['code'] == '00000'){
				return $result['prePayTn'];
			}else{
				throw new Exception('['.$result['code'].']'.$result['message']);
			}
		});
	}

	static private function handleProfits(&$params){
		global $order, $conf;
		$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
		if($psreceiver){
			$divideDetail = [];
			foreach($psreceiver['info'] as $receiver){
				$psmoney = round(floor($order['realmoney'] * $receiver['rate']) / 100, 2);
				$divideDetail[] = [
					'ledgerNo' => $receiver['account'],
					'amount' => $psmoney,
					'ledgerType' => 'MERCHANT2MERCHANT',
				];
			}
			$params['fundProcessType'] = 'REAL_TIME_DIVIDE';
			$params['divideDetail'] = json_encode($divideDetail);
			$params['divideNotifyUrl'] = $conf['localurl'] . 'pay/dividenotify/' . TRADE_NO . '/';
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::pre_pay('USER_SCAN', 'ALIPAY');
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
			$alipay_trade_no = self::pre_pay('ALIPAY_LIFE', 'ALIPAY', null, $user_id);
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

		if(in_array('1',$channel['apptype'])){
			try{
				$code_url = self::pre_pay('USER_SCAN', 'WECHAT');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('3',$channel['apptype']) || !in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			if($channel['appwxmp']>0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}

		if(checkwechat() || $mdevice == 'wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device == 'mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method, $conf, $clientip;

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
			$payinfo = self::pre_pay('WECHAT_OFFIACCOUNT', 'WECHAT', $wxinfo['appid'], $openid);
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
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

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
			$payinfo = self::pre_pay('MINI_PROGRAM', 'WECHAT', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $channel;
		if(in_array('3',$channel['apptype'])){
			try{
				$jump_url = self::tutelage_pay('H5_PAY', 'WECHAT');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			
			if(checkwechat()){
				return ['type'=>'jump','url'=>$jump_url];
			}else{
				return ['type'=>'qrcode','page'=>'wxpay_h5','url'=>$jump_url];
			}
		}elseif($channel['appwxa']>0){
            $wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
            try {
                $code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>$e->getMessage()];
            }
            return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
        }else{
			return self::wxpay();
		}
	}

	//支付宝APP支付
	static public function aliapppay(){
		try{
			$code_url = self::tutelage_pay('SDK_PAY', 'ALIPAY');
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'scheme','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信APP支付
	static public function wxapppay(){
		try{
			$result = self::tutelage_pay('SDK_PAY', 'WECHAT');
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>['appId'=>$result['appId'], 'miniProgramId'=>$result['miniProgramOrgId'], 'path'=>$result['miniProgramPath']]];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::pre_pay('USER_SCAN', 'UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(!$_POST['response']) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT.'inc/YopClient.php');
		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);
		try{
			$data = $client->notifyDecrypt($_POST['response']);
		}catch(Exception $e){
			return ['type'=>'html','data'=>$e->getMessage()];
		}

		if($data) {
			$out_trade_no = $data['orderId'];
			$api_trade_no = $data['uniqueOrderNo'];
			$total_amount = $data['orderAmount'];
			$payerInfo = json_decode($data['payerInfo'], true);
			$buyer = $payerInfo['userID'];
			$bill_trade_no = $data['channelTrxId'];
			$bill_mch_trade_no = $data['bankOrderId'];

			if ($data['status'] == 'SUCCESS') {
				if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
			}
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'FAIL'];
		}
	}

	//支付返回页面
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
		
		require(PAY_ROOT.'inc/YopClient.php');

		$params = [
			'parentMerchantNo' => $channel['appid'],
			'merchantNo' => empty($channel['appmchid'])?$channel['appid']:$channel['appmchid'],
			'orderId' => $order['trade_no'],
			'refundRequestId' => $order['refund_no'] ?? $order['trade_no'],
			'refundAmount' => $order['refundmoney']
		];

		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);
		
		$result = $client->post('/rest/v1.0/trade/refund', $params);

		if($result['code'] == 'OPR00000'){
			return ['code'=>0, 'trade_no'=>$result['uniqueRefundNo'], 'refund_fee'=>$result['refundAmount']];
		}else{
			return ['code'=>-1, 'msg'=>'['.$result['code'].']'.$result['message']];
		}
	}

	//异步回调
	static public function applynotify(){
		global $channel;

		if(!$_POST['response']) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT.'inc/YopClient.php');
		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);
		try{
			$data = $client->notifyDecrypt($_POST['response']);
		}catch(Exception $e){
			return ['type'=>'html','data'=>$e->getMessage()];
		}

		if($data) {
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($data);
			
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'FAIL'];
		}
	}

	//投诉通知
	static public function complainnotify(){
		global $channel;

		if(!$_POST['response']) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT.'inc/YopClient.php');
		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);
		try{
			$data = $client->notifyDecrypt($_POST['response']);
		}catch(Exception $e){
			return ['type'=>'html','data'=>$e->getMessage()];
		}

		if($data) {
			$model = \lib\Complain\CommUtil::getModel($channel);
			if($model) $model->refreshNewInfo($data['complaintNo'], $data['actionType']);
			
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'FAIL'];
		}
	}

	//分账回调
	static public function dividenotify(){
		global $channel;

		if(!$_POST['response']) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT.'inc/YopClient.php');
		$client = new \Yeepay\YopClient($channel['appkey'], $channel['appsecret']);
		try{
			$data = $client->notifyDecrypt($_POST['response']);
		}catch(Exception $e){
			return ['type'=>'html','data'=>$e->getMessage()];
		}

		if($data) {
			$divide_trade_no = $data['divideRequestId'];
			$out_trade_no = $data['orderId'];
			$status = $data['divideStatus'];
			if($status == 'SUCCESS'){
				processProfitSharing($out_trade_no, 2, null, $divide_trade_no);
			}elseif($status == 'FAIL'){
				processProfitSharing($out_trade_no, 3, $data['failReason']);
			}
			
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'FAIL'];
		}
	}
}