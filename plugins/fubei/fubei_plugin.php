<?php

class fubei_plugin
{
	static public $info = [
		'name'        => 'fubei', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '付呗聚合支付', //支付插件显示名称
		'author'      => '付呗', //支付插件作者
		'link'        => 'https://www.51fubei.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '开放平台ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '接口密钥',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '门店ID',
				'type' => 'input',
				'note' => '',
			],
			'mchid' => [
				'name' => '商户ID',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '生活号支付',
			'2' => 'H5支付',
		],
		'note' => '如果是微信支付，需要<a href="./plugin_page.php?channel=[channel]&func=wxconfig" target="_blank">配置绑定AppId和支付目录</a>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			if(checkmobile() && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipaywap/'.TRADE_NO.'/'];
			}elseif(checkalipay() && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
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
		}
		elseif($order['typename']=='alipay'){
			if($device=='mobile' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipaywap/'.TRADE_NO.'/'];
			}elseif($mdevice=='alipay' && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}else{
				return self::wxpay();
			}
		}
	}

	//下单通用
	static private function addOrder($pay_type, $user_id, $sub_appid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');

		$bizContent = [
			'merchant_id' => $channel['mchid'],
			'merchant_order_sn' => TRADE_NO,
			'pay_type' => $pay_type,
			'total_amount' => $order['realmoney'],
			'store_id' => $channel['appmchid'],
			'user_id' => $user_id,
			'body' => $ordername,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];
		if($sub_appid) $bizContent['sub_appid'] = $sub_appid;

		$client = new FubeiClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $bizContent) {
			$retData = $client->execute('fbpay.order.create', $bizContent);
			return $retData;
		});
	}

	//支付宝H5下单
	//https://www.yuque.com/51fubei/hq1pfy/zzmg63
	static private function alipayH5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');

		$bizContent = [
			'merchant_id' => $channel['mchid'],
			'merchant_order_sn' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'store_id' => $channel['appmchid'],
			'body' => $ordername,
			'user_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];

		$client = new FubeiClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $bizContent) {
			$retData = $client->execute('fbpay.order.wap.create', $bizContent);
			return $retData;
		});
	}

	//获取微信网页授权url
	static private function getWechatAuthUrl(){
		global $siteurl, $channel;

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');

		$url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		$bizContent = [
			'url' => $url,
			'store_id' => $channel['appmchid'],
		];
		$client = new FubeiClient($channel['appid'], $channel['appkey']);
		$retData = $client->execute('openapi.agent.merchant.wechat.payment.auth', $bizContent);
		return $retData['authUrl'];
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipaywap/'.TRADE_NO.'/';
		}else{
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
		
		try{
			$retData = self::addOrder('alipay', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$retData['prepay_id']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$retData['prepay_id'], 'redirect_url'=>$redirect_url]];
	}

	//支付宝H5支付
	static public function alipaywap(){
		try{
			$retData = self::alipayH5();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		$html = $retData['html'];
		if(substr($html, 0, 4) == 'http'){
			return ['type'=>'jump','url'=>$html];
		}
		return ['type'=>'html','data'=>$html];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $device;
		$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';

		if (checkmobile() || $device == 'mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method;

		if($channel['appwxmp'] > 0){
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
				$appid = $wxinfo['appid'];
		
				try{
					$openid = wechat_oauth($wxinfo);
				}catch(Exception $e){
					return ['type'=>'error','msg'=>$e->getMessage()];
				}
			}
		}else{
			if(!isset($_GET['open_id'])){
				try{
					$auth_url = self::getWechatAuthUrl();
					return ['type'=>'jump','url'=>$auth_url];
				}catch(Exception $e){
					return ['type'=>'error','msg'=>'获取微信网页授权url失败！'.$e->getMessage()];
				}
			}
			$openid = $_GET['open_id'];
			$appid = 'wxab36abed3127b34a';
		}
		
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;
		
		try{
			$retData = self::addOrder('wxpay', $openid, $appid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>json_encode($retData['sign_package'])];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}

		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($retData['sign_package']), 'redirect_url'=>$redirect_url]];
	}

	//微信小程序插件支付
	static public function wxplugin(){
		$appId = 'wx21efb7c54d4729d6';
		try{
			$result = self::addOrder('wxpay', null, $appId);
			$payinfo = ['appId'=>$appId, 'orderSn'=>$result['order_sn']];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxplugin','data'=>$payinfo];
	}

	//微信参数配置
	static public function wxconfig(){
		global $siteurl,$channel,$islogin;
		if(!$islogin) exit('Access Denied');

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');
		$client = new FubeiClient($channel['appid'], $channel['appkey']);

		if(isset($_POST['sub_appid']) && isset($_POST['jsapi_path'])){
			$bizContent=[
				'store_id' => $channel['appmchid'],
				'sub_appid' => $_POST['sub_appid'],
				'jsapi_path' => $_POST['jsapi_path']
			];
			try{
				$retData = $client->execute('fbpay.order.wxconfig', $bizContent);
				$msg = '';
				if(!empty($retData['sub_appid_msg'])) $msg .= $retData['sub_appid_msg'].'<br/>';
				if(!empty($retData['jsapi_msg'])) $msg .= $retData['jsapi_msg'];
				showmsg($msg,1);
			}catch(Exception $e){
				showmsg('微信参数配置失败！'.$e->getMessage(),4);
			}
		}

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);

		$bizContent=[
			'store_id' => $channel['appmchid']
		];
		try{
			$retData = $client->execute('fbpay.order.wxconfig.query', $bizContent);
			$appid_list = json_decode($retData['appid_list'], true);
			$jsapi_path_list = json_decode($retData['jsapi_path_list'], true);
			$data = ['appid_config_list' => $appid_list['appid_config_list'], 'jsapi_path_list' => $jsapi_path_list['jsapi_path_list'], 'appid'=>$wxinfo ? $wxinfo['appid'] : ''];
			include PAY_ROOT.'wxconf.page.php';
		}catch(Exception $e){
			showmsg('微信参数配置查询失败！'.$e->getMessage(),4);
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('unionpay', '');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');
		$client = new FubeiClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST);

		if($verify_result){
			$data = json_decode($_POST["data"], true);
			if($data['order_status'] == 'SUCCESS'){
				$out_trade_no = $data['merchant_order_sn'];
				$api_trade_no = $data['order_sn'];
				$money = $data['total_amount'];
				$buyer = $data['user_id'];
				$bill_trade_no = $data['channel_order_sn'];
				$bill_mch_trade_no = $data['ins_order_sn'];

				if ($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'success'];
			}else{
				return ['type'=>'html','data'=>'status='.$data['order_status']];
			}
		}else{
			return ['type'=>'html','data'=>'fail'];
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

		require_once(PAY_ROOT.'inc/FubeiClient.class.php');
		$client = new FubeiClient($channel['appid'], $channel['appkey']);

		$bizContent=[
			'order_sn' => $order['api_trade_no'],
			'merchant_refund_sn' => $order['refund_no'],
			'refund_amount' => $order['refundmoney'],
		];
		try{
			$retData = $client->execute('fbpay.order.refund', $bizContent);
			$result = ['code'=>0, 'trade_no'=>$retData['merchant_order_sn'], 'refund_fee'=>$retData['refund_amount']];
		}catch(Exception $e){
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

}