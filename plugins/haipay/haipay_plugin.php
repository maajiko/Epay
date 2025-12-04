<?php

//http://39.106.84.215:8181/docs/saas/saas-1fdsisa23tp3f
class haipay_plugin
{
	static public $info = [
		'name'        => 'haipay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '海科聚合支付', //支付插件显示名称
		'author'      => '海科融通', //支付插件作者
		'link'        => 'https://www.hkrt.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'accessid' => [
				'name' => 'accessid',
				'type' => 'input',
				'note' => '',
			],
			'accesskey' => [
				'name' => '接入秘钥',
				'type' => 'input',
				'note' => '',
			],
			'agent_no' => [
				'name' => '服务商编号',
				'type' => 'input',
				'note' => '',
			],
			'merch_no' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'pn' => [
				'name' => '终端号',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'note' => '需要先加服务器IP白名单，否则无法调用支付', //支付密钥填写说明
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
			}
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//预下单
	static private function prepay($pay_type, $pay_mode, $sub_openid = null, $sub_appid = null){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'agent_no' => $channel['agent_no'],
			'merch_no' => $channel['merch_no'],
			'pay_type' => $pay_type,
			'pay_mode' => $pay_mode,
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'pn' => $channel['pn'],
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($sub_openid) $params['openid'] = $sub_openid;
		if($sub_appid) $params['appid'] = $sub_appid;
		if($pay_type == 'WX'){
			$params['extend_params'] = ['body'=>$ordername];
		}elseif($pay_type == 'ALI'){
			$params['extend_params'] = ['subject'=>$ordername];
		}
		if($order['profits'] > 0){
			self::handleProfits($params);
		}

		$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->payRequest('/api/v2/pay/pre-pay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
			return $result;
		});
	}

	static private function handleProfits(&$params){
		global $order;
		$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
		if($psreceiver){
			$relation = [];
			foreach($psreceiver['info'] as $receiver){
				$psmoney = round(floor($order['realmoney'] * $receiver['rate']) / 100, 2);
				$relation[] = [
					'receive_no' => $receiver['account'],
					'amt' => sprintf('%.2f' , $psmoney),
				];
			}
			$params['ledger_biz'] = [
				'ledger_type' => 'REALTIME_SETTLE',
				'ledger_relation' => $relation,
			];
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype']) || empty($channel['apptype'][0])){
			try{
				$result = self::prepay('ALI', 'NATIVE');
				$code_url = $result['ali_qr_code'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	//支付宝JS支付
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
			$result = self::prepay('ALI', 'JSAPI', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$result['ali_trade_no']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['ali_trade_no'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $device, $mdevice, $channel;
		/*try{
			$result = self::prepay('WX', 'NATIVE');
			$code_url = $result['code_url'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}*/
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
			$result = self::prepay('WX','JSAPI',$openid,$wxinfo['appid']);
			$pay_info = $result['wc_pay_data'];
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
			$result = self::prepay('WX','JSAPI',$openid,$wxinfo['appid']);
			$pay_info = $result['wc_pay_data'];
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

	//云闪付扫码支付
	static public function bank(){
		try{
			$result = self::prepay('UNIONQR', 'NATIVE');
			$code_url = $result['uniqr_qr_code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//付款码支付
	static public function scanpay(){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'accessid' => $channel['accessid'],
			'merch_no' => $channel['merch_no'],
			'auth_code' => $order['auth_code'],
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'pn' => $channel['pn'],
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($order['typename'] == 'wxpay'){
			$params['extend_params'] = ['body'=>$ordername];
		}elseif($order['typename'] == 'alipay'){
			$params['extend_params'] = ['subject'=>$ordername];
		}
		$params['terminal_info'] = ['device_ip' => $clientip];
		if($order['profits'] > 0){
			self::handleProfits($params);
		}
		
		try{
			$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);
			$result = $client->payRequest('/api/v2/pay/passive-pay', $params);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'被扫下单失败！'.$e->getMessage()];
		}

		$api_trade_no = $result['trade_no'];
		if($result['trade_status'] == '1'){
			$result = self::orderQuery($client, $api_trade_no);
			processNotify($order, $api_trade_no, $result['openid'], $result['bank_trade_no']);
			return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['out_trade_no'], 'api_trade_no'=>$api_trade_no, 'buyer'=>$result['openid'], 'money'=>$result['order_amount']]];
		}else{
			$retry = 0;
			$success = false;
			while($retry < 6){
				sleep(3);
				try{
					$result = self::orderQuery($client, $api_trade_no);
				}catch(Exception $e){
					return ['type'=>'error','msg'=>'订单查询失败:'.$e->getMessage()];
				}
				if($result['trade_status'] == '1'){
					$success = true;
					break;
				}elseif($result['tranSts'] != '3'){
					return ['type'=>'error','msg'=>'订单超时或用户取消支付'];
				}
				$retry++;
			}
			if($success){
				processNotify($order, $api_trade_no, $result['openid'], $result['bank_trade_no']);
				return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['out_trade_no'], 'api_trade_no'=>$api_trade_no, 'buyer'=>$result['openid'], 'money'=>$result['order_amount']]];
			}else{
				try{
					self::orderClose($client, $api_trade_no);
				}catch(Exception $e){
				}
				return ['type'=>'error','msg'=>'被扫下单失败！订单已超时'];
			}
		}
	}

	static private function orderQuery($client, $api_trade_no){
		global $channel;
		$params = [
			'merch_no' => $channel['merch_no'],
			'trade_no' => $api_trade_no,
		];
		$result = $client->payRequest('/api/v2/pay/order-query', $params);
		return $result;
	}

	static private function orderClose($client, $api_trade_no){
		global $channel, $clientip;
		$params = [
			'merch_no' => $channel['merch_no'],
			'trade_no' => $api_trade_no,
		];
		$result = $client->payRequest('/api/v2/pay/close-order', $params);
		return $result;
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);

		if($client->verify($arr)){
			if($arr['trade_status'] == '1'){
				$out_trade_no = $arr['out_trade_no'];
				$api_trade_no = $arr['trade_no'];
				$bill_trade_no = $arr['bank_trade_no'];
				$money = $arr['order_amount'];
				$buyer = $arr['openid'];
	
				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'json','data'=>['return_code'=>'SUCCESS']];
		}else{
			return ['type'=>'json','data'=>['return_code'=>'FAIL', 'return_msg'=>'SIGN ERROR']];
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

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'agent_no' => $channel['agent_no'],
			'merch_no' => $channel['merch_no'],
			'trade_no' => $order['api_trade_no'],
			'out_refund_no' => $order['refund_no'],
			'refund_amount' => $order['refundmoney'],
			'pn' => $channel['pn'],
		];
		
		try{
			$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);
			$result = $client->payRequest('/api/v2/pay/refund', $params);
			return ['code'=>0, 'trade_no'=>$result['refund_no'], 'refund_fee'=>$result['refund_amount']];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//进件回调
	static public function applynotify(){
		global $channel;

		$json = file_get_contents('php://input');
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);
		
		if($client->verify($arr)){
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($arr);
			return ['type'=>'json','data'=>['return_code'=>'SUCCESS']];
		}else{
			return ['type'=>'json','data'=>['return_code'=>'FAIL', 'return_msg'=>'SIGN ERROR']];
		}
	}

	//投诉回调
	static public function complainnotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		require_once PAY_ROOT.'inc/HaiPayClient.php';
		
		$client = new HaiPayClient($channel['accessid'], $channel['accesskey']);

        if($client->verify($arr)){
			$thirdid = $arr['complaintId'];
			if(isset($arr['gmtComplain'])){
				$channel['type'] = 1;
			}else{
				$channel['type'] = 2;
			}
			$model = \lib\Complain\CommUtil::getModel($channel);
			$model->refreshNewInfo($thirdid);
			return ['type'=>'json','data'=>['return_code'=>'SUCCESS']];
        }else{
			return ['type'=>'json','data'=>['return_code'=>'FAIL', 'return_msg'=>'SIGN ERROR']];
		}
	}
}