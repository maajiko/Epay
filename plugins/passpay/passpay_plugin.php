<?php
class passpay_plugin
{
	static public $info = [
		'name'        => 'passpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '精秀支付', //支付插件显示名称
		'author'      => '精秀', //支付插件作者
		'link'        => 'https://www.jxpays.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => 'API接口地址',
				'type' => 'input',
				'note' => '以http://或https://开头，以/结尾',
			],
			'appid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '平台公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '通道ID',
				'type' => 'input',
				'note' => '不填写将进行子商户号轮训',
			],
		],
		'select_alipay' => [
			'1' => '支付宝当面付',
			'2' => '支付宝电脑',
			'3' => '支付宝H5',
			'4' => '支付宝生活号',
		],
		'select_wxpay' => [
			'1' => '微信扫码',
			'2' => '微信公众号',
			'3' => '微信H5',
			'4' => '微信小程序H5',
		],
		'select' => null,
		'note' => null, //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='qqpay'){
			return ['type'=>'jump','url'=>'/pay/qqpay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		if($method=='jsapi'){
			if($order['typename']=='wxpay'){
				return self::wxjspay();
			}
		}elseif($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='qqpay'){
			return self::qqpay();
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一下单
	static private function addOrder($trade_type, $sub_appid=null, $sub_openid=null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/PasspayClient.php";

		$client = new PasspayClient($channel['appurl'], $channel['appid'], $channel['appkey'], $channel['appsecret']);

		if($_GET['d'] == 1){
			$return_url = $siteurl.'pay/return/'.TRADE_NO.'/';
		}else{
			$return_url = $siteurl.'pay/ok/'.TRADE_NO.'/';
		}
		$param = [
			'trade_type'  => $trade_type,
			'pay_channel_id' => $channel['appmchid'],
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			"subject"  => $ordername,
			'notify_url'  => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $return_url,
			'client_ip'  => $clientip,
		];
		if($sub_appid && $sub_openid){
			$param += [
				'sub_appid' => $sub_appid,
				'user_id' => $sub_openid,
				'channe_expend' => json_encode(['is_raw' => 1])
			];
		}
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->execute('pay.order/create', $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
			return $result;
		});
	}

	//支付宝支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('3',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$trade_type = 'alipayWap';
		}elseif(in_array('2',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$trade_type = 'alipayPc';
		}elseif(in_array('4',$channel['apptype']) && !in_array('3',$channel['apptype'])){
			$trade_type = 'alipayPub';
		}else{
			$trade_type = 'alipayQr';
		}
		try{
			$result = self::addOrder($trade_type);
			$code_url = $result['payurl'];
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
		try{
			$result = self::addOrder('wechatQr');
			$code_url = $result['payurl'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(checkwechat()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method, $conf;

		if($channel['appwxmp'] > 0){
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
				$result = self::addOrder('wechatPub', $wxinfo['appid'], $openid);
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			if($method == 'jsapi'){
				return ['type'=>'jsapi','data'=>$result['payInfo']];
			}

			if($_GET['d']==1){
				$redirect_url='data.backurl';
			}else{
				$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
			}
			return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$result['payInfo'], 'redirect_url'=>$redirect_url]];
		}else{
			try{
				$result = self::addOrder('wechatPub');
				$code_url = $result['payurl'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$code_url];
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
			$result = self::addOrder('wechatLite', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($result['payInfo'], true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('4',$channel['apptype']) && $channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			if(in_array('3',$channel['apptype'])){
				$trade_type = 'wechatWap';
			}else{
				$trade_type = 'wechatLiteH5';
			}
			try{
				$result = self::addOrder($trade_type);
				$code_url = $result['payurl'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'qrcode','page'=>'wxpay_h5','url'=>$code_url];
		}
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::addOrder('qqQr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$ex->getMessage()];
		}

		if(checkmobbileqq()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif(checkmobile() && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('unionQr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once PAY_ROOT."inc/PasspayClient.php";

		$client = new PasspayClient($channel['appurl'], $channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($_POST);

		if($verify_result){
			if ($_POST['order_status'] == 'SUCCESS') {
				$out_trade_no = $_POST['out_trade_no'];
				$trade_no = $_POST['trade_no'];
				$bill_trade_no = $_POST['channel_order_sn'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, null, $bill_trade_no);
				}
				return ['type'=>'html','data'=>'success'];
			}
			return ['type'=>'html','data'=>'status fail'];
		}
		else {
			return ['type'=>'html','data'=>'sign fail'];
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

		require_once PAY_ROOT."inc/PasspayClient.php";

		$client = new PasspayClient($channel['appurl'], $channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		$param = [
			'refund_amount' => $order['refundmoney'],
			'refund_reason' => '订单退款',
			'out_refund_no' => $order['refund_no'],
			'trade_no' => $order['api_trade_no'],
		];

		try{
			$result = $client->execute('pay.order/refund', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		return ['code'=>0, 'trade_no'=>$result['trade_no'], 'refund_fee'=>$result['refund_amount']];
	}

}