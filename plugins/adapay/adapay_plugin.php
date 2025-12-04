<?php

class adapay_plugin
{
	static public $info = [
		'name'        => 'adapay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'AdaPay聚合支付', //支付插件显示名称
		'author'      => 'AdaPay', //支付插件作者
		'link'        => 'https://www.adapay.tech/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用App_ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => 'prod模式API_KEY',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户RSA私钥',
				'type' => 'textarea',
				'note' => '',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
			'3' => '托管小程序支付',
		],
		'select_wxpay' => [
			'1' => '自有公众号/小程序支付',
			'2' => '动态二维码支付',
			'3' => '托管小程序支付',
		],
		'select_bank' => [
			'1' => '银联支付',
			'2' => '快捷支付',
			'3' => '网银支付',
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
			if(in_array('1',$channel['apptype']) && checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			if(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/quickpay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/unionpay/'.TRADE_NO.'/'];
			}
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice, $method;

		if($method=='jsapi'){
			if($order['typename']=='alipay'){
				return self::alipayjs();
			}elseif($order['typename']=='wxpay'){
				return self::wxjspay();
			}
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if(in_array('1',$channel['apptype']) && $mdevice=='wechat'){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			if(in_array('3',$channel['apptype'])){
				return self::bank();
			}elseif(in_array('2',$channel['apptype'])){
				return self::quickpay();
			}else{
				return self::unionpay();
			}
		}
	}

	//通用创建订单
	static private function addOrder($pay_channel, $openid = null){
		global $channel, $order, $ordername, $conf, $clientip;

		require PAY_ROOT . 'inc/AdapayClient.php';
		$client = new AdapayClient($channel['appkey'], $channel['appsecret'], $channel['appid']);

		$params = [
			'order_no' => TRADE_NO,
			'pay_channel' => $pay_channel,
			'pay_amt' => $order['realmoney'],
			'goods_title' => $ordername,
			'goods_desc' => $ordername,
			'currency' => 'cny',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];
		if ($pay_channel === 'wx_pub' || $pay_channel === 'wx_lite') {
			$params['expend'] = [
				'openid' => $openid,
			];
		}elseif ($pay_channel === 'alipay_pub' || $pay_channel === 'alipay_lite') {
			$params['expend'] = [
				'buyer_id' => $openid,
			];
		}
		if($order['profits'] > 0){
			$params['pay_mode'] = 'delay';
		}
		/*if($order['profits'] > 0){
			$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
			if($psreceiver){
				$psmoney = round(floor($order['realmoney'] * $psreceiver['rate']) / 100, 2);
				$psmoney2 = round($order['realmoney']-$psmoney, 2);
				$div_members = [];
				$div_members[] = ['member_id'=>$psreceiver['account'], 'amount' => sprintf('%.2f' , $psmoney), 'fee_flag'=>'N'];
				if($psmoney2 > 0){
					$div_members[] = ['member_id'=>'0', 'amount' => sprintf('%.2f' , $psmoney2), 'fee_flag'=>'Y'];
				}else{
					$div_members[0]['fee_flag'] = 'Y';
				}
				$params['div_members'] = $div_members;
			}
		}*/
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->createPayment($params);
			return $result['expend'];
		});
	}

	//跳转支付创建订单
	static private function pagepay($func_code, $pay_channel){
		global $channel, $order, $ordername, $conf, $clientip, $siteurl;

		require PAY_ROOT . 'inc/AdapayClient.php';
		$client = new AdapayClient($channel['appkey'], $channel['appsecret'], $channel['appid']);
		$params = [
			'adapay_func_code' => $func_code,
			'order_no' => TRADE_NO,
			'pay_channel' => $pay_channel,
			'pay_amt' => $order['realmoney'],
			'goods_title' => $ordername,
			'goods_desc' => $ordername,
			'currency' => 'cny',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'callback_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($order['profits'] > 0){
			$params['pay_mode'] = 'delay';
		}

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->queryAdapay($params);
			return $result['expend'];
		});
	}

	//收银台创建订单
	static private function checkout($pay_channel, $member_id = null){
		global $channel, $order, $ordername, $conf, $clientip, $siteurl;

		require PAY_ROOT . 'inc/AdapayClient.php';
		$client = new AdapayClient($channel['appkey'], $channel['appsecret'], $channel['appid']);
		$params = [
			'adapay_func_code' => 'checkout',
			'order_no' => TRADE_NO,
			'pay_channel' => $pay_channel,
			'pay_amt' => $order['realmoney'],
			'goods_title' => $ordername,
			'goods_desc' => $ordername,
			'currency' => 'cny',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'callback_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($member_id){
			$params['member_id'] = $member_id;
		}

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->queryAdapay($params);
			return $result['expend'];
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype']) || empty($channel['apptype'][0])){
			try{
				$result = self::addOrder('alipay_qr');
			}catch (Exception $e) {
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
			}
			$code_url = $result['qrcode_url'];
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}elseif(in_array('3',$channel['apptype'])){
			try{
				$result = self::pagepay('prePay.preOrder', 'alipay_lite');
			}catch (Exception $e) {
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
			}
			$code_url = $result['ali_h5_pay_url'];
			if(checkalipay() || $mdevice=='alipay'){
				return ['type'=>'jump','url'=>$code_url];
			}elseif(checkmobile() || $device=='mobile'){
				return ['type'=>'page','page'=>'alipay_h5','data'=>['code_url'=>$code_url, 'redirect_url'=>'data.backurl']];
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
			$result = self::addOrder('alipay_pub', $user_id);
			$payinfo = json_decode($result['pay_info'], true);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$payinfo['tradeNO']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$payinfo['tradeNO'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $channel, $device, $mdevice;

		if(in_array('2',$channel['apptype'])){
			try{
				$result = self::pagepay('qrPrePay.qrPreOrder', '');
			}catch (Exception $e) {
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
			}
			$code_url = $result['qr_pay_url'];
		}elseif(in_array('3',$channel['apptype']) && !in_array('1',$channel['apptype'])){
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
			$result = self::addOrder('wx_pub', $openid);
		}catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}

		$jsApiParameters = $result['pay_info'];
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$jsApiParameters];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxa']>0 && in_array('1',$channel['apptype'])){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}elseif(in_array('3',$channel['apptype'])){ //托管小程序支付
			try{
				$result = self::pagepay('wxpay.createOrder', 'wx_lite');
			}catch (Exception $e) {
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
			}
			$code_url = $result['scheme_code'];
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			return self::wxpay();
		}
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
			$result = self::addOrder('wx_lite', $openid);
		}catch (Exception $e) {
			exit('{"code":-1,"msg":"微信支付下单失败！'.$e->getMessage().'"}');
		}

		$jsApiParameters = $result['pay_info'];
		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
	}

	//云闪付扫码支付
	static public function unionpay(){
		try{
			$result = self::addOrder('union_qr');
		}catch (Exception $e) {
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$e->getMessage()];
		}

		$code_url = $result['qrcode_url'];

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//快捷支付
	static public function quickpay(){
		if(!empty($_COOKIE['adapay_user_id'])){
			$user_id = $_COOKIE['adapay_user_id'];
		}else{
			$user_id = substr(getSid(), 0, 10);
			setcookie('adapay_user_id', $user_id, time()+3600*24*365, '/');
		}
		try{
			$result = self::checkout('fast_pay', $user_id);
			$code_url = $result['pay_url'];
		}catch (Exception $e) {
			return ['type'=>'error','msg'=>'快捷支付下单失败！'.$e->getMessage()];
		}
		return ['type'=>'jump','url'=>$code_url];
	}

	//网银支付
	static public function bank(){
		try{
			$result = self::checkout('online_pay');
			$code_url = $result['pay_url'];
		}catch (Exception $e) {
			return ['type'=>'error','msg'=>'网银支付下单失败！'.$e->getMessage()];
		}
		return ['type'=>'jump','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		//file_put_contents('logs.txt',http_build_query($_POST));
		if(!isset($_POST['sign']) || !isset($_POST['data'])){
			return ['type'=>'html','data'=>'No'];
		}

		require PAY_ROOT . 'inc/AdapayClient.php';
		$client = new AdapayClient($channel['appkey'], $channel['appsecret'], $channel['appid']);
		if ($client->verifySign($_POST['sign'] , $_POST['data'])) {
			$_data = json_decode($_POST['data'] , true);
			if ($_data['status'] == 'succeeded') {
				$api_trade_no = $_data['id'];
				$trade_no = $_data['order_no'];
				$orderAmount = $_data['pay_amt'];
				$buyer = $_data['expend']['sub_open_id'];
				$bill_trade_no = $_data['out_trans_id'];
				$bill_mch_trade_no = $_data['party_order_id'];
				if ($trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'Ok'];
			} else {
				return ['type'=>'html','data'=>'No'];
			}
		} else {
			return ['type'=>'html','data'=>'No'];
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

		require PAY_ROOT . 'inc/AdapayClient.php';
		$client = new AdapayClient($channel['appkey'], $channel['appsecret'], $channel['appid']);
		$params = [
			'payment_id' => $order['api_trade_no'],
			'refund_order_no' => $order['refund_no'],
			'refund_amt' => $order['refundmoney']
		];
		if($order['profits'] > 0){
			$psorder = \lib\ProfitSharing\CommUtil::getOrder($order['trade_no']);
			if($psorder && ($psorder['status'] == 1 || $psorder['status'] == 2)){
				$div_members = [];
				if($psorder['rdata']){
					$allmoney = 0;
					$leftmoney = (float)$order['refundmoney'];
					foreach($psorder['rdata'] as $receiver){
						$money = $receiver['money'] > $leftmoney ? $leftmoney : $receiver['money'];
						$div_members[] = ['member_id'=>$receiver['account'], 'amount' => sprintf('%.2f' , $money)];
						$allmoney += $receiver['money'];
						$leftmoney = round($leftmoney - $money, 2);
						if($leftmoney <= 0) break;
					}
					if($order_money > $allmoney && $leftmoney > 0){
						$psmoney2 = round($order_money-$allmoney, 2);
						$psmoney2 = $psmoney2 > $leftmoney ? $leftmoney : $psmoney2;
						$div_members[] = ['member_id'=>'0', 'amount' => sprintf('%.2f' , $psmoney2)];
					}
				}else{
					$amount = $psorder['money'] > $order['refundmoney'] ? $order['refundmoney'] : $psorder['money'];
					$div_members[] = [
						'member_id' => $psorder['account'],
						'amount' => sprintf('%.2f' , $amount),
					];
					if($order['refundmoney'] > $psorder['money']){
						$amount = round($order['refundmoney'] - $psorder['money'], 2);
						$div_members[] = [
							'member_id' => '0',
							'amount' => sprintf('%.2f' , $amount),
						];
					}
				}
				$params['payment_id'] = $psorder['settle_no'];
				$params['div_members'] = $div_members;
			}
		}
		try{
			$res = $client->createRefund($params);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		if($res['status']=='succeeded'||$res['status']=='pending'){
			$result = ['code'=>0, 'trade_no'=>$res['id'], 'refund_fee'=>$res['refund_amt']];
		}else{
			$result = ['code'=>-1, 'msg'=>'['.$res["error_code"].']'.$res["error_msg"]];
		}
		return $result;
	}
}