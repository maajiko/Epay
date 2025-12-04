<?php

class duolabao_plugin
{
	static public $info = [
		'name'        => 'duolabao', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '哆啦宝支付', //支付插件显示名称
		'author'      => '哆啦宝', //支付插件作者
		'link'        => 'http://www.duolabao.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank','jdpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'agentNum' => [
				'name' => '代理商编号',
				'type' => 'input',
				'note' => '非代理商不需要填写',
			],
			'customerNum' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'shopNum' => [
				'name' => '店铺编号',
				'type' => 'input',
				'note' => '此项可留空',
			],
			'accessKey' => [
				'name' => '公钥',
				'type' => 'input',
				'note' => '',
			],
			'secretKey' => [
				'name' => '私钥',
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
			if(checkwechat() && in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}else{
			return ['type'=>'jump','url'=>'/pay/'.$order['typename'].'/'.TRADE_NO.'/'];
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
			if($mdevice=='wechat' && in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}else{
			$typename = $order['typename'];
			return self::$typename();
		}
	}

	//通用创建订单
	static public function qrcode(){
		global $channel, $order, $ordername, $conf, $clientip, $siteurl;

		require PAY_ROOT.'inc/PayApp.class.php';
		$client = new PayApp($channel['accessKey'], $channel['secretKey']);
		
		$param = [
			'version' => 'V4.0',
			'agentNum' => $channel['agentNum'],
			'customerNum' => $channel['customerNum'],
			'shopNum' => $channel['shopNum'],
			'requestNum' => TRADE_NO,
			'orderAmount' => $order['realmoney'],
			'subOrderType' => 'NORMAL',
			'orderType' => 'SALES',
			'timeExpire' => date('Y-m-d H:i:s', time() + 7200),
			'businessType' => 'QRCODE_TRAD',
			'payModel' => 'ONCE',
			'source' => 'API',
			'callbackUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'completeUrl' => $siteurl . 'pay/return/' . TRADE_NO . '/',
			'clientIp' => $clientip,
		];
		if($order['profits']>0){
			self::handleProfits($param);
		}

		$result = $client->submitNew('/api/generateQRCodeUrl', $param);
		return $result['url'];
	}

	//通用创建订单
	static public function jspay($bankType, $authCode, $appId = null){
		global $channel, $order, $ordername, $conf, $clientip;

		require PAY_ROOT.'inc/PayApp.class.php';
		$client = new PayApp($channel['accessKey'], $channel['secretKey']);
		
		$param = [
			'version' => 'V4.0',
			'agentNum' => $channel['agentNum'],
			'customerNum' => $channel['customerNum'],
			'shopNum' => $channel['shopNum'],
			'bankType' => $bankType,
			'paySource' => $bankType,
			'authCode' => $authCode,
			'requestNum' => TRADE_NO,
			'orderAmount' => $order['realmoney'],
			'subOrderType' => 'NORMAL',
			'orderType' => 'SALES',
			'payType' => 'ACTIVE',
			'businessType' => 'QRCODE_TRAD',
			'payModel' => 'ONCE',
			'source' => 'API',
			'timeExpire' => date('Y-m-d H:i:s', time() + 7200),
			'callbackUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'clientIp' => $clientip,
		];
		if($appId) {
			$param['appId'] = $appId;
			$param['subAppId'] = $appId;
		}
		if($order['profits']>0){
			self::handleProfits($param);
		}

		$result = $client->submitNew('/api/createPayWithCheck', $param);
		return $result['bankRequest'];
	}

	static private function handleProfits(&$params){
		global $order;
		$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
		if($psreceiver){
			$list = [];
			$allmoney = 0;
			foreach($psreceiver['info'] as $receiver){
				$psmoney = round(floor($order['realmoney'] * $receiver['rate']) / 100, 2);
				$list[] = [
					'customerNum' => $receiver['account'],
					'amount' => sprintf('%.2f' , $psmoney),
				];
				$allmoney += $psmoney;
			}
			$psmoney2 = round($order['realmoney']-$allmoney, 2);
			if($psmoney2 > 0){
				$list[] = [
					'customerNum' => $params['customerNum'],
					'amount' => sprintf('%.2f' , $psmoney2),
				];
			}
			$params['LedgerRequest'] = [
				'ledgerType' => 'FIXED',
				'ledgerFeeAssume' => 'FIXED',
				'list' => $list,
			];
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::qrcode();
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
			$result = self::jspay('ALIPAY', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$result['TRADENO']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['TRADENO'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $device, $mdevice, $channel;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			if($channel['appwxmp']>0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}else{
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
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
		global $siteurl, $channel, $order, $ordername, $conf, $method;

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
			$result = self::jspay('WX', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}
		$payinfo = ['appId'=>$result['APPID'], 'timeStamp'=>$result['TIMESTAMP'], 'nonceStr'=>$result['NONCESTR'], 'package'=>$result['PACKAGE'], 'signType'=>$result['SIBGTYPE'], 'paySign'=>$result['PAYSIGN']];
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>json_encode($payinfo)];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($payinfo), 'redirect_url'=>$redirect_url]];
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
			$result = self::jspay('WX_XCX', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		$payinfo = ['appId'=>$result['APPID'], 'timeStamp'=>$result['TIMESTAMP'], 'nonceStr'=>$result['NONCESTR'], 'package'=>$result['PACKAGE'], 'signType'=>$result['SIGNTYPE'], 'paySign'=>$result['PAYSIGN']];

		exit(json_encode(['code'=>0, 'data'=>$payinfo]));
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

	//QQ扫码支付
	static public function qqpay(){
		global $siteurl, $device, $mdevice;
		try{
			$code_url = self::qrcode();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$ex->getMessage()];
		}

		if(checkmobbileqq() || $mdevice == 'qq'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif((checkmobile() || $device == 'mobile') && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()) {
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//京东支付
	static public function jdpay(){
		try{
			$code_url = self::qrcode();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'京东支付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()) {
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'jdpay_qrcode','url'=>$code_url];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'no data'];

		require PAY_ROOT.'inc/PayApp.class.php';
		$client = new PayApp($channel['accessKey'], $channel['secretKey']);
		
		if ($client->verifyNotify($json)) {
			$trade_no = $arr['requestNum'];
			$api_trade_no = $arr['orderNum'];
			$orderAmount = $arr['orderAmount'];
			$bill_trade_no = $arr['bankOutTradeNum'];
			$bill_mch_trade_no = $arr['bankRequestNum'];
			$buyer = $arr['subOpenId'];
			if ($arr['status'] == 'SUCCESS') {
				if ($trade_no == TRADE_NO && round($order['realmoney'],2) == round($orderAmount,2)) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'success'];
			}else{
				return ['type'=>'html','data'=>'error'];
			}
		} else {
			return ['type'=>'html','data'=>'error'];
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
		global $channel;
		if(empty($order))exit();

		require PAY_ROOT.'inc/PayApp.class.php';
		$client = new PayApp($channel['accessKey'], $channel['secretKey']);

		$param = [
			'requestVersion' => 'V4.0',
			'agentNum' => $channel['agentNum'],
			'customerNum' => $channel['customerNum'],
			'shopNum' => $channel['shopNum'],
			'requestNum' => $order['trade_no'],
			'refundPartAmount' => $order['refundmoney'],
			'refundRequestNum' => $order['refund_no'],
			'extMap' => ['refund_status_type'=>'1'],
		];
		if($order['profits'] > 0){
			$psorder = \lib\ProfitSharing\CommUtil::getOrder($order['trade_no']);
			if($psorder && ($psorder['status'] == 1 || $psorder['status'] == 2)){
				if($psorder['rdata']){
					$leftmoney = (float)$order['refundmoney'];
					$div_members = [];
					foreach($psorder['rdata'] as $receiver){
						$money = $receiver['money'] > $leftmoney ? $leftmoney : $receiver['money'];
						$div_members[] = [
							'customerNum' => $receiver['account'],
							'amount' => sprintf('%.2f' , $money),
						];
						$leftmoney -= $money;
						if($leftmoney <= 0) break;
					}
					$param['list'] = $div_members;
				}else{
					$amount = $psorder['money'] > $order['refundmoney'] ? $order['refundmoney'] : $psorder['money'];
					$div_members[] = [
						'customerNum' => $psorder['account'],
						'amount' => sprintf('%.2f' , $amount),
					];
					if($order['refundmoney'] > $psorder['money']){
						$amount = round($order['refundmoney'] - $psorder['money'], 2);
						$div_members[] = [
							'customerNum' => $param['customerNum'],
							'amount' => sprintf('%.2f' , $amount),
						];
					}
					$param['list'] = $div_members;
				}
			}
		}
		try{
			$result = $client->submitNew('/api/refundByRequestNum', $param);
			return ['code'=>0, 'trade_no'=>$result['orderNum'], 'refund_fee'=>$result['refundAmount']];
		}catch(Exception $ex){
			return ['code'=>-1,'msg'=>$ex->getMessage()];
		}
	}

	//进件回调
	static public function applynotify(){
		global $channel, $order, $DB;

		require PAY_ROOT.'inc/PayApp.class.php';
		$client = new PayApp($channel['accessKey'], $channel['secretKey']);
		
		if ($client->verifyNotify()) {
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($_GET);
			return ['type'=>'html','data'=>'success'];
		} else {
			return ['type'=>'html','data'=>'error'];
		}
	}
}