<?php

class ysepay_plugin
{
	static public $info = [
		'name'        => 'ysepay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '银盛支付', //支付插件显示名称
		'author'      => '银盛支付', //支付插件作者
		'link'        => 'https://www.ysepay.com/', //支付插件作者链接
		'types'       => ['alipay','qqpay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '服务商商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '私钥证书密码',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '收款商户号',
				'type' => 'input',
				'note' => '不填写则和服务商商户号相同',
			],
			'appurl' => [
				'name' => '业务代码',
				'type' => 'input',
				'note' => '',
			],
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'H5支付',
			'3' => '生活号支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
		],
		'select_bank' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select' => null,
		'note' => '只能使用RSA证书！需要将商户私钥证书client.pfx（或商户号.pfx）上传到 /plugins/ysepay/cert 文件夹内', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && ($channel['appwxa']>0)){
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
			if($order['typename']=='alipay'){
				return self::alipayjs();
			}elseif($order['typename']=='wxpay'){
				return self::wxjspay();
			}elseif($order['typename']=='bank'){
				return self::bankjs();
			}
		}elseif($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && ($channel['appwxa']>0)){
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

	//扫码支付
	static private function qrcode($bank_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$seller_id = $channel['appmchid']?$channel['appmchid']:$channel['appid'];
		$method = 'ysepay.online.qrcodepay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $seller_id,
			'timeout_express' => '2h',
			'business_code' => $channel['appurl'],
			'bank_type' => $bank_type,
			'submer_ip' => $clientip,
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';
		$result = $client->execute($method, $params);
		return $result['source_qr_code_url'];
	}

	//微信公众号小程序支付
	static private function weixinpay($appid, $openid, $isminipg = '2'){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$seller_id = $channel['appmchid']?$channel['appmchid']:$channel['appid'];
		$method = 'ysepay.online.weixin.pay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $seller_id,
			'timeout_express' => '2h',
			'business_code' => $channel['appurl'],
			'appid' => $appid,
			'sub_openid' => $openid,
			'is_minipg' => $isminipg,
			'payer_ip' => $clientip,
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';

		$result = $client->execute($method, $params);
		return $result['jsapi_pay_info'];
	}

	//支付宝生活号支付
	static private function alijsapipay($buyer_id){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$seller_id = $channel['appmchid']?$channel['appmchid']:$channel['appid'];
		$method = 'ysepay.online.alijsapi.pay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $seller_id,
			'timeout_express' => '2h',
			'business_code' => $channel['appurl'],
			'buyer_id' => $buyer_id,
			'payer_ip' => $clientip,
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';

		$result = $client->execute($method, $params);
		return $result['jsapi_pay_info'];
	}

	//银联行业码支付
	static private function cupmulapppay($buyer_id){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$seller_id = $channel['appmchid']?$channel['appmchid']:$channel['appid'];
		$method = 'ysepay.online.cupmulapp.qrcodepay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $seller_id,
			'timeout_express' => '2h',
			'business_code' => $channel['appurl'],
			'spbill_create_ip' => $clientip,
			'bank_type' => '9001002',
			'userId' => $buyer_id,
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';

		$result = $client->execute($method, $params);
		return $result['web_url'];
	}

	//WAP支付
	static private function wappay($bank_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$seller_id = $channel['appmchid']?$channel['appmchid']:$channel['appid'];
		$method = 'ysepay.online.wap.directpay.createbyuser';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'seller_id' => $seller_id,
			'timeout_express' => '7d',
			'business_code' =>$channel['appurl'],
			'pay_mode' => 'native',
			'bank_type' => $bank_type,
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';
		$client->returnUrl = $siteurl . 'pay/return/' . TRADE_NO . '/';

		$html = $client->pageExecute($method, $params);
		return $html;
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $siteurl, $mdevice;
		if(in_array('2',$channel['apptype']) && (checkmobile() || $device=='mobile')){
			try{
				$html = self::wappay('1903000');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'html','data'=>$html];
		}elseif(in_array('1',$channel['apptype'])){
			try{
				$code_url = self::qrcode('1903000');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('3',$channel['apptype'])){
			$code_url = $siteurl . 'pay/alipayjs/' . TRADE_NO . '/';
		}else{
			$code_url = $siteurl . 'pay/alipay/' . TRADE_NO . '/';
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
			$result = self::alijsapipay($user_id);
			$trade_no = json_decode($result, true)['tradeNO'];
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
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			if($channel['appwxmp']>0 && $channel['appwxa']==0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}else{
			try{
				$code_url = self::qrcode('1902000');
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

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::qrcode('1904000');
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
			$jsApiParameters = self::weixinpay($wxinfo['appid'], $openid, '2');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
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
			$jsApiParameters = self::weixinpay($wxinfo['appid'], $openid, '1');
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
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
			$code_url = self::qrcode('9001002');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if(checkunionpay()){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}
	
	static public function bankjs(){
		global $method, $order;
		try{
			$code_url = self::cupmulapppay($order['sub_openid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PAY_ROOT."inc/YsepayClient.php");

		$params = [
			'authCode' => $userAuthCode,
			'appUpIdentifier' => get_unionpay_ua(),
		];

		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		try{
			$result = $client->execute('ysepay.online.cupgetmulapp.userid', $params);
			return ['code'=>0,'data'=>$result['userId']];
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		//计算得出通知验证结果
		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST);

		if($verify_result) {//验证成功
			$out_trade_no = $_POST['out_trade_no'];
			$trade_no = $_POST['trade_no'];
			$buyer_id = $_POST['buyer_user_id'];
			$total_amount = $_POST['total_amount'];
			$bill_trade_no = $_POST['channel_recv_sn'];
			$bill_mch_trade_no = $_POST['channel_send_sn'];

			if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
					processNotify($order, $trade_no, $buyer_id, $bill_trade_no, $bill_mch_trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//同步回调
	static public function return(){
		global $channel, $order;

		require_once(PAY_ROOT."inc/YsepayClient.php");

		//计算得出通知验证结果
		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_GET);

		if($verify_result) {//验证成功
			$out_trade_no = $_GET['out_trade_no'];
			$trade_no = $_GET['trade_no'];
			$total_amount = $_GET['total_amount'];

			if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
				if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
					processReturn($order, $trade_no);
				}else{
					return ['type'=>'error','msg'=>'订单信息校验失败'];
				}
			}else{
				return ['type'=>'error','msg'=>'trade_status='.$_GET['trade_status']];
			}
		}
		else {
			//验证失败
			return ['type'=>'error','msg'=>'返回验证失败'];
		}
	}
	
	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once(PAY_ROOT."inc/YsepayClient.php");

		$method = 'ysepay.online.trade.refund';
		$params = [
			'out_trade_no' => $order['trade_no'],
			'shopdate' => date("Ymd"),
			'trade_no' => $order['api_trade_no'],
			'refund_amount' => $order['refundmoney'],
			'refund_reason' => '申请退款',
			'out_request_no' => $order['refund_no'],
		];

		try{
			$client = new YsepayClient($channel['appid'], $channel['appkey']);
			$result = $client->execute($method, $params);
			return ['code'=>0, 'trade_no'=>$result['trade_no'], 'refund_fee'=>$result['refund_amount']];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//进件通知
	static public function applynotify(){
		global $channel;

		$json = file_get_contents("php://input");
		$data = json_decode($json,true);
		if(!$data) {
			return ['type'=>'html','data'=>'fail'];
		}

		require_once(PAY_ROOT."inc/YsepayClient.php");

		//计算得出通知验证结果
		$client = new YsepayClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify2($data);

		if($verify_result) {//验证成功
			$arr = json_decode($data['bizContent'], true);

			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($arr);
			
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}
}