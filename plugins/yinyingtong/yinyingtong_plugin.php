<?php

class yinyingtong_plugin
{
	static public $info = [
		'name'        => 'yinyingtong', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '银盈通支付', //支付插件显示名称
		'author'      => '银盈通', //支付插件作者
		'link'        => 'http://www.yinyingtong.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '应用KEY',
				'type' => 'input',
				'note' => '同时是私钥证书密码',
			],
			'productkey' => [
				'name' => '产品密钥',
				'type' => 'input',
				'note' => '用于支付回调数据解密，填错将无法回调',
			],
			'appmchid' => [
                'name' => '交易商户企业号',
                'type' => 'input',
                'note' => '',
			],
			'trade_platform_no' => [
                'name' => '平台商企业号(参考号)',
                'type' => 'input',
                'note' => '',
			],
			'channel_merch_no' => [
                'name' => '渠道商户号',
                'type' => 'input',
                'note' => '可留空，多个渠道商户号可用,分隔',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '银盈通公众号',
			'2' => '银盈通小程序',
			'3' => '自有公众号/小程序',
		],
		'note' => '如使用进件，需要将商户私钥证书 <font color="red">应用ID.pfx</font> 上传到 /plugins/yinyingtong/cert 文件夹内', //支付密钥填写说明
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
			if(in_array('3',$channel['apptype']) && checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif((in_array('2',$channel['apptype']) || in_array('3',$channel['apptype']) && $channel['appwxa']>0) && checkmobile()){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/quickpay/'.TRADE_NO.'/'];
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
		}elseif($method == 'app' || $method == 'applet'){
			return self::wxapppay();
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if(in_array('3',$channel['apptype']) && $mdevice=='wechat'){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif((in_array('2',$channel['apptype']) || in_array('3',$channel['apptype']) && $channel['appwxa']>0) && $device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::quickpay();
		}
	}

	//支付预下单
	static private function prepay($pay_type, $bank_service_type = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/PayClient.php');

		$client = new PayClient($channel['appid'], $channel['appkey']);

		$params = [
			'merchant_number' => $channel['appmchid'],
			'order_number' => TRADE_NO,
			'amount' => $order['realmoney'],
			'pay_type' => $pay_type,
			'currency' => 'CNY',
			'order_title' => $ordername,
			'channel_code' => $pay_type,
			'async_notification_addr' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'notify_key_mode' => '03',
			'ref_no' => $channel['trade_platform_no'],
		];
		if($bank_service_type) $params['bank_service_type'] = $bank_service_type;
		if($order['profits']>0){
			$params['profit_sharing'] = '1';
		}
		if(!empty($channel['channel_merch_no'])){
			if(strpos($channel['channel_merch_no'], ',')){
				$merch_nos = explode(',', $channel['channel_merch_no']);
				$channel['channel_merch_no'] = $merch_nos[array_rand($merch_nos)];
			}
			$params['bank_mch_id'] = $channel['channel_merch_no'];
		}

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('gepos.pre.pay', $params);
			if($result['op_ret_code'] == '000'){
				$order_id = $result['order_id'];
				\lib\Payment::updateOrder(TRADE_NO, $order_id);
				return $result;
			}else{
				throw new Exception('['.$result['op_ret_subcode'].']'.$result['op_err_msg']);
			}
		});
	}

	//公众号小程序支付
	static private function jsapipay($pay_type, $sub_openid = null, $sub_appid = null, $is_mini = false){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/PayClient.php');

		$client = new PayClient($channel['appid'], $channel['appkey']);

		$params = [
			'merchant_number' => $channel['appmchid'],
			'order_number' => TRADE_NO,
			'amount' => $order['realmoney'],
			'pay_type' => $pay_type,
			'currency' => 'CNY',
			'order_title' => $ordername,
			'channel_code' => $pay_type,
			'async_notification_addr' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'notify_key_mode' => '03',
			'ref_no' => $channel['trade_platform_no'],
		];
		if($sub_openid) $params['open_id'] = $sub_openid;
		if($sub_appid) $params['sub_appid'] = $sub_appid;
		if($order['profits']>0){
			$params['profit_sharing'] = '1';
		}
		if(!empty($channel['channel_merch_no'])){
			if(strpos($channel['channel_merch_no'], ',')){
				$merch_nos = explode(',', $channel['channel_merch_no']);
				$channel['channel_merch_no'] = $merch_nos[array_rand($merch_nos)];
			}
			$params['bank_mch_id'] = $channel['channel_merch_no'];
		}
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params, $is_mini) {
			$result = $client->execute($is_mini ? 'gepos.mini.program.pay' : 'gepos.public.number.order', $params);
			if($result['op_ret_code'] == '000'){
				\lib\Payment::updateOrder(TRADE_NO, $result['order_id']);
				return $result['trans_data'];
			}else{
				throw new Exception('['.$result['op_ret_subcode'].']'.$result['op_err_msg']);
			}
		});
	}

	//预下单
	static private function precreate($pay_type, $user_id){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT.'inc/PayClient.php');

		$params = [
			'merchant_number' => $channel['appmchid'],
			'order_number' => TRADE_NO,
			'scene' => '14',
			'good_desc' => $order['name'],
			'total_amount' => $order['realmoney'],
			'currency' => 'cny',
			'user_id' => $user_id,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];

		$client = new PayClient($channel['appid'], $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->execute('gcash.trade.precreate', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['order_id']);
			return $result;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $siteurl, $mdevice;

		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$result = self::prepay('01');
				$bank_order_id = $result['order_id'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
			$code_url = 'https://h5.gomepay.com/cashier-h5/index.html#/pages/preOrder/orderPay?orderId='.$bank_order_id.'&showPayButton=0';
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}
	
	//支付宝生活号支付
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
			$paydata = self::jsapipay('01', $user_id);
			$trade_no = json_decode($paydata, true)['tradeNO'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$trade_no];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$trade_no, 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;
		if(in_array('1',$channel['apptype'])){
			try{
				$result = self::prepay('02', '16');
				$bank_order_id = $result['order_id'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			$code_url = 'https://h5.gomepay.com/cashier-h5/index.html#/pages/preOrder/wxPublicOrder?orderId='.$bank_order_id.'&showPayButton=0';
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			if($channel['appwxmp']>0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}

		if (checkwechat() || $mdevice=='wechat') {
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device == 'mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}
	
	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $mdevice;
		if(in_array('2',$channel['apptype'])){
			try{
				$result = self::prepay('02', '22');
				$bank_order_id = $result['order_id'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			$query = 'orderId='.$bank_order_id.'&showPayButton=0';
			$code_url = 'weixin://dl/business/?appid=wx135edf7e3c7a1e7d&path=pages/wechat/preOrder/orderpay&query='.urlencode($query).'&env_version=release';
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}
		else{
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

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method, $conf;

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
			$paydata = self::jsapipay('02', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$paydata];
		}
		
		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$paydata, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl,$channel, $mdevice;
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
			$paydata = self::jsapipay('02', $openid, $wxinfo['appid'], true);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}
		exit(json_encode(['code'=>0, 'data'=>json_decode($paydata, true)]));
	}

	//微信APP支付
	static public function wxapppay(){
		global $method;
		try{
			$result = self::prepay('02', '22');
			$bank_order_id = $result['order_id'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		$env = $method == 'applet' ? 'miniprogram' : 'app';
		return ['type'=>'wxapp','data'=>['appId'=>'wx135edf7e3c7a1e7d', 'miniProgramId'=>'gh_d27d42772cd8', 'path'=>'pages/wechat/preOrder/orderpay?orderId='.$bank_order_id.'&env='.$env]];
	}

	//快捷支付
	static public function quickpay(){
		global $channel, $order;
		if(!empty($_COOKIE['yyt_user_id'])){
			$user_id = $_COOKIE['yyt_user_id'];
		}else{
			$user_id = substr(getSid(), 0, 16);
			setcookie('yyt_user_id', $user_id, time()+3600*24*365, '/');
		}
		try{
			$result = self::precreate('10', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'快捷支付下单失败！'.$ex->getMessage()];
		}
		$params = [
			'merchant_number' => $channel['appmchid'],
			'user_id' => $user_id,
			'order_number' => TRADE_NO,
			'type' => 'wbsh',
		];
		$url = 'https://h5.gomepay.com/cashier-h5/index.html#/pages/paymentB/cashRegister?'.http_build_query($params);

		return ['type'=>'jump','url'=>$url];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(!isset($_POST['dstbdata']) || !isset($_POST['dstbdatasign'])) return ['type'=>'html','data'=>'no data'];

		require_once(PAY_ROOT.'inc/PayClient.php');
		$client = new PayClient($channel['appid'], $channel['appkey']);
		$data = $client->notify($_POST['dstbdatasign'], $channel['productkey']);

		if($data){
			if($data['orderstatus'] == '00'){
				$out_trade_no = $data['dsorderid'];
				$api_trade_no = $data['orderid'];
				$money = $data['amount'];
				$bill_trade_no = $data['seq_no'];

				$ext = unserialize($order['ext']);
				$ext['transcode'] = $data['transcode'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $null, $bill_trade_no);
					\lib\Payment::updateOrderExt(TRADE_NO, $ext);
				}
			}
			return ['type'=>'html','data'=>'00'];
		}else{
			return ['type'=>'html','data'=>'01'];
		}
	}

	//异步回调
	static public function notify_old(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) return ['type'=>'html','data'=>'no data'];

		require_once(PAY_ROOT.'inc/PayClient.php');
		$client = new PayClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($arr);

		if($verify_result){
			$data = json_decode($arr['data'], true);
			if($data['status'] == '00'){
				$out_trade_no = $data['order_number'];
				$api_trade_no = $data['order_id'];
				$money = $data['total_amount'];
				$bill_mch_trade_no = $data['biz_content']['data'][0]['bank_order_id'];
				$buyer = $data['biz_content']['data'][0]['bank_user_id'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, null, $bill_mch_trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}else{
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require_once(PAY_ROOT.'inc/PayClient.php');
		$client = new PayClient($channel['appid'], $channel['appkey']);

		$ext = unserialize($order['ext']);

		$params = [
			'scene' => $ext['transcode'] == 'T61' ? '0615' : '0606',
			'merchant_number' => $channel['appmchid'],
			'order_number' => $order['refund_no'],
			'old_order_number' => $order['trade_no'],
			'old_order_id' => $order['api_trade_no'],
			'amount' => $order['refundmoney'],
			'currency' => 'CNY',
			'async_notification_addr' => $conf['localurl'] . 'pay/refundnotify/' . TRADE_NO . '/',
			'memo' => '订单退款',
		];

		try{
			$retData = $client->execute('gepos.refund', $params);
			$result = ['code'=>0, 'trade_no'=>$retData['order_id'], 'refund_fee'=>$params['amount']];
		}catch(Exception $e){
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//退款回调
	static public function refundnotify(){
		global $channel, $order;

		if(!isset($_POST['dstbdata']) || !isset($_POST['dstbdatasign'])) return ['type'=>'html','data'=>'no data'];

		require_once(PAY_ROOT.'inc/PayClient.php');
		$client = new PayClient($channel['appid'], $channel['appkey']);
		$data = $client->notify($_POST['dstbdatasign'], $channel['productkey']);

		if($data){
			if($data['orderstatus'] == '00'){
				$out_trade_no = $data['dsorderid'];
				$api_trade_no = $data['orderid'];
				$money = $data['amount'];
			}
			return ['type'=>'html','data'=>'00'];
		}else{
			return ['type'=>'html','data'=>'01'];
		}
	}

	//进件通知
	static public function applynotify(){
		global $channel;

		require_once(PAY_ROOT."inc/M2Client.php");

		//计算得出通知验证结果
		$client = new M2Client($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST['dstbdata'], $_POST['dstbdatasign']);

		if($verify_result) {//验证成功
			$data = json_decode($_POST['dstbdata'], true);

			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($data);
			
			return ['type'=>'html','data'=>'00'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'01'];
		}
	}

	//投诉通知
	static public function complainnotify(){
		global $channel;

		require_once(PAY_ROOT."inc/M2Client.php");

		//计算得出通知验证结果
		$client = new M2Client($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST['dstbdata'], $_POST['dstbdatasign']);

		if($verify_result) {//验证成功
			$data = json_decode($_POST['dstbdata'], true);

			$model = \lib\Complain\CommUtil::getModel($channel);
			$model->refreshNewInfo($data['complaint_id'].'|'.$data['sub_mchid'], $data['action_type']);
			
			return ['type'=>'html','data'=>'00'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'01'];
		}
	}
}