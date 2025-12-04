<?php

class kuaiqian_plugin
{
	static public $info = [
		'name'        => 'kuaiqian', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '快钱支付', //支付插件显示名称
		'author'      => '快钱', //支付插件作者
		'link'        => 'https://www.99bill.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '快钱账户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户证书密码',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => 'SSL客户端证书密码',
				'type' => 'input',
				'note' => '',
			],
			'merchant_id' => [
				'name' => '当面付-商户号',
				'type' => 'input',
				'note' => '仅当面付需要填写',
			],
			'terminal_id' => [
				'name' => '当面付-终端号',
				'type' => 'input',
				'note' => '仅当面付需要填写',
			],
			'appmchid' => [
				'name' => '服务商-快钱子账户号',
				'type' => 'input',
				'note' => '仅服务商需要填写',
			],
			'own_channel' => [
				'name' => '是否自有渠道',
				'type' => 'select',
				'options' => [0=>'否',1=>'是'],
			],
			/*'custom_mch_id' => [
				'name' => '自定义渠道-商户号',
				'type' => 'input',
				'note' => '仅自定义授权渠道需要填写',
			],
			'custom_sub_mch_id' => [
				'name' => '自定义渠道-子商户号',
				'type' => 'input',
				'note' => '仅自定义授权渠道需要填写',
			],
			'custom_channel_id' => [
				'name' => '自定义渠道-渠道商商户号',
				'type' => 'input',
				'note' => '仅自定义授权渠道需要填写',
			],*/
		],
		'select_alipay' => [
			'1' => 'H5支付',
			'2' => '当面付',
		],
		'select_wxpay' => [
			'1' => 'H5支付',
			'2' => '当面付',
		],
		'select_bank' => [
			'1' => '网银支付',
			'2' => '快捷支付',
			'3' => '云闪付扫码',
		],
		'note' => '将商户证书key.pfx，快钱公钥cert.cer，SSL双向证书ssl.pfx 放到/plugins/kuaiqian/cert/文件夹下。<br/>证书类型均为RSA，需要在商户证书配置页面，添加人民币网关功能，并选中你正在使用的商户证书。', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename, $submit2;
		
		if($order['typename']=='alipay'){
			if(checkalipay()){
				return ['type'=>'jump','url'=>'/pay/alipaywap/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && in_array('1',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkwechat() && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($order['typename']=='alipay'){
			if($mdevice=='alipay'){
				return self::alipaywap();
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('1',$channel['apptype']) && $channel['appwxmp']>0){
				return self::wxjspay();
			}elseif($mdevice=='wechat' && in_array('1',$channel['apptype'])){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}
	
	//网银支付
	static private function bankpay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(in_array('1',$channel['apptype'])){
			$payType = '10';
		}else{
			$payType = '21';
		}

		require(PAY_ROOT."inc/PayApp.class.php");

		$apiurl = 'https://www.99bill.com/gateway/recvMerchantInfoAction.htm';
		//$apiurl = 'https://sandbox.99bill.com/gateway/recvMerchantInfoAction.htm';

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'inputCharset' => '1',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'v2.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'payType' => $payType
		];
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];

		$html_text = '<form action="'.$apiurl.'" method="post" id="dopay">';
		foreach($params as $k => $v) {
			$html_text .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\" />\n";
		}
		$html_text .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("dopay").submit();</script>';

		return ['type'=>'html','data'=>$html_text];
	}

	//H5支付
	static private function mobilepay($payType, $aggregatePay = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$apiurl = 'https://www.99bill.com/mobilegateway/recvMerchantInfoAction.htm';
		//$apiurl = 'https://sandbox.99bill.com/mobilegateway/recvMerchantInfoAction.htm';

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'inputCharset' => '1',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'payType' => $payType
		];
		if($aggregatePay) $params['aggregatePay'] = $aggregatePay;
		if($channel['own_channel'] == 1 || !empty($channel['custom_mch_id']) && !empty($channel['custom_sub_mch_id']) && !empty($channel['custom_channel_id'])){
			$params['extDataType'] = 'NB2';
			$customAuthNetInfo = [];
			if($channel['own_channel'] == 1){
				$customAuthNetInfo['own_channel'] = '1';
			}
			if(!empty($channel['custom_mch_id']) && !empty($channel['custom_sub_mch_id']) && !empty($channel['custom_channel_id'])){
				$customAuthNetInfo['mch_id'] = $channel['custom_mch_id'];
				$customAuthNetInfo['sub_mch_id'] = $channel['custom_sub_mch_id'];
				$customAuthNetInfo['channel_id'] = $channel['custom_channel_id'];
			}
			$params['extDataContent'] = '<NB2>'.json_encode(['customAuthNetInfo'=>$customAuthNetInfo]).'</NB2>';
		}
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];

		$html_text = '<form action="'.$apiurl.'" method="post" id="dopay">';
		foreach($params as $k => $v) {
			$v = htmlentities($v, ENT_QUOTES | ENT_HTML5);
			$html_text .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\" />\n";
		}
		$html_text .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("dopay").submit();</script>';

		return ['type'=>'html','data'=>$html_text];
	}

	//获取H5支付链接
	static private function mobilepayurl($payType, $aggregatePay = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$apiurl = 'https://www.99bill.com/mobilegateway/recvMerchantInfoAction.htm';
		//$apiurl = 'https://sandbox.99bill.com/mobilegateway/recvMerchantInfoAction.htm';

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'inputCharset' => '1',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'payType' => $payType
		];
		if($aggregatePay) $params['aggregatePay'] = $aggregatePay;
		if($channel['own_channel'] == 1 || !empty($channel['custom_mch_id']) && !empty($channel['custom_sub_mch_id']) && !empty($channel['custom_channel_id'])){
			$params['extDataType'] = 'NB2';
			$customAuthNetInfo = [];
			if($channel['own_channel'] == 1){
				$customAuthNetInfo['own_channel'] = '1';
			}
			if(!empty($channel['custom_mch_id']) && !empty($channel['custom_sub_mch_id']) && !empty($channel['custom_channel_id'])){
				$customAuthNetInfo['mch_id'] = $channel['custom_mch_id'];
				$customAuthNetInfo['sub_mch_id'] = $channel['custom_sub_mch_id'];
				$customAuthNetInfo['channel_id'] = $channel['custom_channel_id'];
			}
			$params['extDataContent'] = '<NB2>'.json_encode(['customAuthNetInfo'=>$customAuthNetInfo]).'</NB2>';
		}
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];

		$res = $client->curl($apiurl, http_build_query($params));
		if(strpos($res[1], '确认支付') !== false){
			$cookie = '';
			preg_match_all('/Set-Cookie: (.*?);/i', $res[0], $match);
			foreach($match[1] as $v){
				$cookie .= $v.'; ';
			}
			if(substr($payType, 0, 2) == '26'){
				$url = 'https://www.99bill.com/mobilegateway/weixinWapPrePay.htm';
				$res = $client->curl($url, '', $cookie);
				$arr = json_decode($res[1], true);
				if(isset($arr['openlink'])){
					return $arr['openlink'];
				}else{
					echo $res[1];exit;
				}
			}elseif(substr($payType, 0, 2) == '27'){
				$url = 'https://www.99bill.com/mobilegateway/alicsbPay.htm';
				$res = $client->curl($url, '', $cookie);
				$arr = json_decode($res[1], true);
				if(isset($arr['qrcode'])){
					return $arr['qrcode'];
				}elseif($res[1]){
					echo $res[1];exit;
				}else{
					throw new Exception('支付异常');
				}
			}
		}else{
			echo $res[1];exit;
		}
	}

	//当面付
	static private function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$head = [
			'version' => '1.0.0',
			'messageType' => 'A7007',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => TRADE_NO,
		];
		if(!empty($channel['appmchid'])){
			$head['memberCode'] = $channel['appmchid'];
			$head['vendorMemberCode'] =  $channel['appid'];
		}
		$body = [
			'merchantId' => $channel['merchant_id'],
			'terminalId' => $channel['terminal_id'],
			'cur' => 'CNY',
			'amount' => strval($order['realmoney'] * 100),
			'tr3Url' => $conf['localurl'] . 'pay/notifys/' . TRADE_NO . '/',
			'qrType' => '00',
			'terminalIp' => $clientip,
		];

		$result = $client->execute($head, $body);
		if($result['bizResponseCode'] == '0000'){
			\lib\Payment::updateOrderCombine(TRADE_NO);
			return $result['qrCode'];
		}else{
			throw new Exception('['.$result['bizResponseCode'].']'.$result['bizResponseMessage']);
		}
	}

	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;

		if(in_array('1',$channel['apptype']) && (checkmobile() || $device=='mobile')){
			if(checkwechat()){
				return ['type'=>'page','page'=>'wxopen'];
			}
			return self::mobilepay('27-3');
		}elseif(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/alipaywap/'.TRADE_NO.'/';
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	static public function alipaywap(){
		try{
			$jump_url = self::mobilepayurl('27-3');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
		return ['type'=>'jump','url'=>$jump_url];
	}

	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;

		if(in_array('1',$channel['apptype']) && (checkmobile() || $device=='mobile')){
			if(checkalipay()){
				return ['type'=>'page','page'=>'wxopen'];
			}
			return self::mobilepay('26-2');
		}elseif(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif($channel['appwxmp']>0){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}else{
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}

		if($mdevice == 'wechat' || checkwechat()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif ($device == 'mobile' || checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	static public function wxwappay(){
		try{
			$jump_url = self::mobilepayurl('26-2');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$jump_url];
	}

	//微信公众号
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];

		try{
			$openid = wechat_oauth($wxinfo);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		$aggregatePay = 'appId='.$wxinfo['appid'].',openId='.$openid.',limitPay=0';
		return self::mobilepay('26-1', $aggregatePay);
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
		require(PAY_ROOT."inc/PayApp.class.php");
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		$apiurl = 'https://www.99bill.com/mobilegateway/miniProgramPay.htm';
		$params = [
			'inputCharset' => '1',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'aggregatePay' => 'appId='.$wxinfo['appid'].',openId='.$openid.',limitPay=0',
			'payType' => '26-3'
		];
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];

		$response = get_curl($apiurl, http_build_query($params));
		$result = json_decode($response, true);
		if(isset($result['responseCode']) && $result['responseCode']=='00'){
			exit(json_encode(['code'=>0, 'data'=>$result['payInfo']]));
		}elseif(isset($result['ResponseMsg'])){
			exit('{"code":-1,"msg":"'.$result['ResponseMsg'].'"}');
		}else{
			exit('{"code":-1,"msg":"返回内容解析失败"}');
		}
	}

	static public function bank(){
		global $channel, $device, $siteurl;

		if((checkmobile() || $device=='mobile') && (in_array('1',$channel['apptype']) || in_array('2',$channel['apptype']))){
			if(in_array('1',$channel['apptype'])){
				$payType = '00';
			}else{
				$payType = '21';
			}
			return self::mobilepay($payType);
		}elseif(!checkmobile() && $device!='mobile' && in_array('1',$channel['apptype'])){
			return self::bankpay();
		}else{
			$code_url = $siteurl.'pay/bank/'.TRADE_NO.'/';
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifyNotify($_GET);

		if($verify_result) {//验证成功
			if ($_GET['payResult'] == '10') {
				if($_GET['orderId'] == TRADE_NO){
					processNotify($order, $_GET['dealId']);
				}
			}
			$redirecturl = $siteurl.'pay/return/'.TRADE_NO.'/';
			return ['type'=>'html','data'=>'<result>1</result><redirecturl>'.$redirecturl.'</redirecturl>'];
		}
		else {
			return ['type'=>'html','data'=>'<result>0</result>'];
		}
	}

	//当面付异步回调
	static public function notifys(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		try{
			$response = $client->notifyProcess($result);
		}catch(Exception $ex){
			return ['type'=>'html','data'=>$ex->getMessage()];
		}

		if($result['body']['orderStatus'] == 'S'){
			if($result['head']['externalRefNumber'] == TRADE_NO){
				processNotify($order, $result['body']['idOrderCtrl'], $result['body']['thirdPartyBuyerId']);
			}
		}

		return ['type'=>'html','data'=>$response];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//查单
	static public function query(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if($order['combine'] == 1){ //当面付
			$head = [
				'version' => '1.0.0',
				'messageType' => 'A7006',
				'memberCode' => $channel['appid'],
				'externalRefNumber' => 'QUE'.$order['trade_no'],
			];
			if(!empty($channel['appmchid'])){
				$head['memberCode'] = $channel['appmchid'];
				$head['vendorMemberCode'] =  $channel['appid'];
			}
			$body = [
				'merchantId' => $channel['merchant_id'],
				'terminalId' => $channel['terminal_id'],
				'idOrderCtrl' => $order['api_trade_no'],
			];
		}else{
			$head = [
				'version' => '1.0.0',
				'messageType' => 'F0003',
				'memberCode' => $channel['appid'],
				'externalRefNumber' => 'QUE'.$order['trade_no'],
			];
			if(!empty($channel['appmchid'])){
				$head['memberCode'] = $channel['appmchid'];
				$head['vendorMemberCode'] =  $channel['appid'];
			}
			$body = [
				'merchantAcctId' => $channel['appid'] . '01',
				'queryType' => '0',
				'queryMode' => '1',
				'orderId' => $order['trade_no'],
			];
		}

		try{
			$result = $client->execute($head, $body);
			print_r($result);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$head = [
			'version' => '1.0.0',
			'messageType' => 'F0001',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => $order['refund_no'],
		];
		$body = [
			'merchantAcctId' => $channel['appid'],
			'txnType' => 'bill_drawback_api_1',
			'amount' => strval($order['refundmoney'] * 100),
			'entryTime' => substr($order['trade_no'], 0, 14),
			'orgOrderId' => $order['trade_no'],
		];

		try{
			$result = $client->execute($head, $body);
			if($result['bizResponseCode'] == '0000'){
				return ['code'=>0];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['bizResponseCode'].']'.$result['bizResponseMessage']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//当面付退款
	static public function refund_combine($order){
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$head = [
			'version' => '1.0.0',
			'messageType' => 'A7003',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => $order['refund_no'],
		];
		if(!empty($channel['appmchid'])){
			$head['memberCode'] = $channel['appmchid'];
			$head['vendorMemberCode'] =  $channel['appid'];
		}
		$body = [
			'merchantId' => $channel['merchant_id'],
			'terminalId' => $channel['terminal_id'],
			'amount' => strval($order['refundmoney'] * 100),
			'origOrderCtrl' => $order['api_trade_no'],
			'origRefNumber' => $order['trade_no'],
			'tr3Url' => $conf['localurl'] . 'pay/notifys/' . TRADE_NO . '/',
		];

		try{
			$result = $client->execute($head, $body);
			if($result['bizResponseCode'] == '0000'){
				return ['code'=>0];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['bizResponseCode'].']'.$result['bizResponseMessage']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		try{
			$bank_info = getBankCardInfo($bizParam['payee_account']);
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
		
		require(PLUGIN_ROOT."kuaiqian/inc/PayApp.class.php");
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$head = [
			'version' => '1.0.0',
			'messageType' => 'C1017',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => $bizParam['out_biz_no'],
		];
		$body = [
			'amount' => strval($bizParam['money'] * 100),
			'cardHolderName' => $bizParam['payee_real_name'],
			'bankName' => $bank_info['bank_name'],
			'pan' => $bizParam['payee_account'],
			'reMark' => $bizParam['transfer_desc'],
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
		];

		try{
			$result = $client->execute($head, $body);
			if($result['bizResponseCode'] == '0000'){
				return ['code'=>0, 'status'=>0, 'orderid'=>$bizParam['out_biz_no'], 'paydate'=>date('Y-m-d H:i:s')];
			}else{
				return ['code'=>-1, 'errcode'=>$result['bizResponseCode'], 'msg'=>'['.$result['bizResponseCode'].']'.$result['bizResponseMessage']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require(PLUGIN_ROOT."kuaiqian/inc/PayApp.class.php");
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$head = [
			'version' => '1.0.0',
			'messageType' => 'C1018',
			'memberCode' => $channel['appid'],
		];
		$body = [
			'pageNo' => 1,
			'pageSize' => 1,
			'externalRefNumber' => $bizParam['out_biz_no'],
		];

		try{
			$result = $client->execute($head, $body);
			if(!empty($result['detailedList'])){
				$detail = $result['detailedList'][0];
				$paydate = $detail['endDate'];
				if($detail['txnStatus'] == 'S'){
					$status = 1;
				}elseif($detail['txnStatus'] == 'F' || $detail['txnStatus'] == 'R'){
					$status = 2;
					$errmsg = $detail['failMessage'];
				}else{
					$status = 0;
				}
				return ['code'=>0, 'status'=>$status, 'amount'=>$detail['amount'], 'errmsg'=>$errmsg, 'paydate'=>$paydate];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['bizResponseCode'].']'.$result['bizResponseMessage']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账异步回调
	static public function transfernotify(){
		global $channel;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		try{
			$response = $client->notifyProcessTransfer($result);
		}catch(Exception $ex){
			return ['type'=>'html','data'=>$ex->getMessage()];
		}

		$out_biz_no = $result['head']['externalRefNumber'];
		if($result['body']['txnStatus'] == 'S'){
			$status = 1;
			$errmsg = '';
		}elseif($result['body']['txnStatus'] == 'F' || $result['body']['txnStatus'] == 'R'){
			$status = 2;
			$errmsg = $result['body']['bizResponseMessage'];
		}
		processTransfer($out_biz_no, $status, $errmsg);

		return ['type'=>'html','data'=>$response];
	}

	//投诉通知回调
	static public function complainnotify(){
		global $channel;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		try{
			$response = $client->notifyProcessComplain($result);
		}catch(Exception $ex){
			return ['type'=>'html','data'=>$ex->getMessage()];
		}

		if($result['body']['complaintSource'] == 'ALIPAY_BILL'){
			return ['type'=>'html','data'=>$response];
		}
		$model = \lib\Complain\CommUtil::getModel($channel);
		$model->refreshNewInfo($result['body']['complaintNo'], $result['body']['actionType']);

		return ['type'=>'html','data'=>$response];
	}

}