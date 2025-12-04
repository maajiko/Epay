<?php

class huolian_plugin
{
	static public $info = [
		'name'        => 'huolian', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '火脸支付', //支付插件显示名称
		'author'      => '火脸', //支付插件作者
		'link'        => 'https://www.lianok.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '对接商授权编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '对接商MD5加密盐',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户ID',
				'type' => 'input',
				'note' => '',
			],
			'appurl' => [
				'name' => '收银员手机号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '退款密码（管理密码）',
				'type' => 'input',
				'note' => '如不需要退款功能可留空',
			],
		],
		'select' => null,
		'select_wxpay' => [
			'1' => '聚合支付',
			'2' => 'H5预下单',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkmobile() && !checkwechat() && ($channel['appwxa']>0 || in_array('2',$channel['apptype']))){
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

		if($method == 'wxplugin'){
			return self::wxplugin();
		}
		elseif($method == 'app'){
			return self::wxapppay();
		}
		elseif($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($device=='mobile' && $device!='wechat' && ($channel['appwxa']>0 || in_array('2',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//聚合支付
	static private function addOrder($pay_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $mdevice;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');

		$params = [
			'businessOrderNo' => TRADE_NO,
			'payAmount' => $order['realmoney'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'payWay' => $pay_type,
			'clientIp' => $clientip,
			//'callBackUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if(checkwechat() || $mdevice=='wechat')$params['callBackUrl'] = $siteurl.'pay/return/'.TRADE_NO.'/';

		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('api.hl.order.pay.unified', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['orderNo']);
			return $result['payUrl'];
		});
	}

	//原生支付预下单
	static private function prepay($pay_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $mdevice;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');

		$params = [
			'businessOrderNo' => TRADE_NO,
			'payAmount' => $order['realmoney'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'payWay' => $pay_type,
			'clientIp' => $clientip,
		];

		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('api.hl.order.pay.native', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['orderNo']);
			return $result['qrCodeUrl'];
		});
	}
	
	//微信小程序支付
	static private function wechat_applet($appid, $openid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');

		$params = [
			'businessOrderNo' => TRADE_NO,
			'payAmount' => $order['realmoney'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'payWay' => 'wechat',
			'appId' => $appid,
			'openId' => $openid,
			'clientIp' => $clientip,
		];

		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('api.hl.order.pay.applet', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['orderNo']);
			return $result;
		});
	}

	//微信小程序托管支付
	static private function wechat_applet_host(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');

		$params = [
			'businessOrderNo' => TRADE_NO,
			'payAmount' => $order['realmoney'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'payWay' => 'wechat',
			'clientIp' => $clientip,
		];

		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('api.hl.order.pre.pay.applet', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['orderNo']);
			return $result;
		});
	}

	//H5预下单
	static private function h5pay($pay_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');

		$params = [
			'businessOrderNo' => TRADE_NO,
			'payAmount' => $order['realmoney'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'payWay' => $pay_type,
			'pageNotifyUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'clientIp' => $clientip,
		];

		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('api.hl.order.pay.h5', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['orderNo']);
			return $result['payUrl'];
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		try{
			$code_url = self::addOrder('alipay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		try{
			$code_url = self::addOrder('wechat');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(checkwechat() || $mdevice=='wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf;

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
			$result = self::wechat_applet($wxinfo['appid'], $openid);
			$jsApiParameters = $result['jsPayInfo'];
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;
		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::h5pay('wechat');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$code_url];
		}else{
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}
	}

	//微信小程序插件支付
	static public function wxplugin(){
		$appId = 'wxf51d01cf670e28d3';
		try{
			$result = self::wechat_applet($appId);
			$payinfo = ['appId'=>$appId, 'merchantNo'=>$result['merchantNo'], 'orderNo'=>$result['orderNo']];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxplugin','data'=>$payinfo];
	}

	//微信小程序托管支付
	static public function wxapppay(){
		try{
			$result = self::wechat_applet_host();
			$payinfo = ['appId'=>$result['appId'], 'miniProgramId'=>$result['miniProgramId'], 'path'=>$result['payPath']];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>$payinfo];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('cloud');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if(checkunionpay()){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) exit;

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');
		$client = new HuolianClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($arr);

		if($verify_result){
			$data = json_decode($arr['respBody'], true);
			if($data['orderStatus'] == 2){
				$out_trade_no = $data['businessOrderNo'];
				$api_trade_no = $data['orderNo'];
				$money = $data['payAmount'];
				$buyer = $data['userId'];
				$bill_trade_no = $data['topChannelOrderNo'];
				$bill_mch_trade_no = $data['channelOrderNo'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'SUCCESS'];
			}else{
				return ['type'=>'html','data'=>'status='.$data['orderStatus']];
			}
		}else{
			return ['type'=>'html','data'=>'FAIL'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once(PAY_ROOT.'inc/HuolianClient.class.php');
		$client = new HuolianClient($channel['appid'], $channel['appkey']);

		$params = [
			'orderNo' => $order['api_trade_no'],
			'businessRefundNo' => $order['refund_no'],
			'refundAmount' => $order['refundmoney'],
			'refundPassword' => $channel['appsecret'],
			'merchantNo' => $channel['appmchid'],
			'operatorAccount' => $channel['appurl'],
		];

		try{
			$retData = $client->execute('api.hl.order.refund.operation', $params);
			$result = ['code'=>0, 'trade_no'=>$retData['refundNo'], 'refund_fee'=>$retData['refundAmount']];
		}catch(Exception $e){
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//异步回调
	static public function complainnotify(){
		global $channel;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) exit;

		if(substr($channel['appmchid'],0,1)=='['){
			$channel['appmchid'] = $arr['merchantNo'];
		}

		$model = \lib\Complain\CommUtil::getModel($channel);
		$model->refreshNewInfo($arr['huolianComplaintNo'], $arr['operateType']);

		return ['type'=>'html','data'=>'SUCCESS'];
	}

}