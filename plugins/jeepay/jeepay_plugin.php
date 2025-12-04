<?php

class jeepay_plugin
{
	static public $info = [
		'name'        => 'jeepay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'Jeepay聚合支付', //支付插件显示名称
		'author'      => 'Jeepay', //支付插件作者
		'link'        => 'https://www.jeequan.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['alipay','wxpay','bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => '接口地址',
				'type' => 'input',
				'note' => '必须以http://或https://开头，以/结尾',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appid' => [
				'name' => '应用AppId',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '私钥AppSecret',
				'type' => 'textarea',
				'note' => '',
			],
		],
		'select_alipay' => [
			'1' => '支付宝扫码',
			'2' => '支付宝PC网站',
			'3' => '支付宝WAP',
			'7' => '支付宝APP',
			'4' => '支付宝生活号',
			'5' => '聚合扫码',
			'6' => 'WEB收银台',
		],
		'select_wxpay' => [
			'1' => '微信扫码',
			'2' => '微信H5',
			'7' => '微信APP',
			'3' => '微信公众号',
			'4' => '微信小程序',
			'5' => '聚合扫码',
			'6' => 'WEB收银台',
		],
		'select_bank' => [
			'1' => '云闪付扫码',
			'5' => '聚合扫码',
			'6' => 'WEB收银台',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	static private function make_sign($param, $key){
		ksort($param);
		$signstr = '';
	
		foreach($param as $k => $v){
			if($k != "sign" && $v!=''){
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr .= 'key=' . $key;
		$sign = strtoupper(md5($signstr));
		return $sign;
	}

	static private function getMillisecond()
	{
		list($s1, $s2) = explode(' ', microtime());
		return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
	}

	//下单通用
	static private function addOrder($wayCode, $channelExtra = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if($_GET['r'] == 1){
			$returnUrl = $siteurl.'pay/ok/'.TRADE_NO.'/';
		}else{
			$returnUrl = $siteurl.'pay/return/'.TRADE_NO.'/';
		}
		$apiurl = $channel['appurl'].'api/pay/unifiedOrder';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'mchOrderNo' => TRADE_NO,
			'wayCode' => $wayCode,
			'amount' => round($order['realmoney']*100),
			'currency' => 'cny',
			'clientIp' => $clientip,
			'subject' => $ordername,
			'body' => $ordername,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'returnUrl' => $returnUrl,
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];
		if($channelExtra) $param['channelExtra'] = $channelExtra;

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiurl, $param) {
			$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);
			$result = json_decode($data, true);

			if(isset($result['code']) && $result['code']==0){
				if($result['data']['errMsg']){
					throw new Exception('['.$result['data']['errCode'].']'.$result['data']['errMsg']);
				}elseif($result['data']['error']){
					throw new Exception($result['data']['error']);
				}
				return [strtolower($result['data']['payDataType']), $result['data']['payData']];
			}else{
				throw new Exception($result['msg']?$result['msg']:'返回数据解析失败');
			}
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('3',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$wayCode = 'ALI_WAP';
		}elseif(in_array('2',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$wayCode = 'ALI_PC';
		}elseif(in_array('1',$channel['apptype'])){
			$wayCode = 'ALI_QR';
		}elseif(in_array('7',$channel['apptype'])){
			$wayCode = 'ALI_APP';
		}elseif(in_array('4',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('5',$channel['apptype'])){
			$wayCode = 'QR_CASHIER';
		}elseif(in_array('6',$channel['apptype'])){
			$wayCode = 'WEB_CASHIER';
		}elseif(in_array('3',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/alipay/'.TRADE_NO.'/?r=1';
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$qrcode_url];
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		try{
			list($type, $payData) = self::addOrder($wayCode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		if($wayCode == 'QR_CASHIER' && strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')===false && $mdevice!='alipay'){
			$type = 'codeurl';
		}

		if($type == 'payurl'){
			if(checkwechat()){
				return ['type'=>'page','page'=>'wxopen'];
			}
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$payData];
		}
	}

	//支付宝生活号支付
	static public function alipayjs(){
		global $channel;

		if (!isset($_GET['channelUserId'])) {
			$apiurl = $channel['appurl'].'api/channelUserId/jump';
			$redirect_url = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$param = [
				'mchNo' => $channel['appmchid'],
				'appId' => $channel['appid'],
				'ifCode' => 'AUTO',
				'redirectUrl' => $redirect_url,
				'reqTime' => self::getMillisecond(),
				'version' => '1.0',
				'signType' => 'MD5',
			];
			$param['sign'] = self::make_sign($param, $channel['appkey']);
			$jump_url = $apiurl.'?'.http_build_query($param);
			return ['type'=>'jump','url'=>$jump_url];
		}else{
			$openId = $_GET['channelUserId'];
		}

		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks) return $blocks;

		try{
			$extra = json_encode(['buyerUserId' => $openId]);
			list($type, $payData) = self::addOrder('ALI_JSAPI', $extra);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
			if($_GET['d']=='1'){
				$redirect_url='data.backurl';
			}else{
				$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
			}
			$arr = json_decode($payData, true);
			return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$arr['alipayTradeNo'], 'redirect_url'=>$redirect_url]];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype'])){
			$wayCode = 'WX_NATIVE';
		}elseif(in_array('3',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('4',$channel['apptype']) || in_array('7',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('5',$channel['apptype'])){
			$wayCode = 'QR_CASHIER';
		}elseif(in_array('6',$channel['apptype'])){
			$wayCode = 'WEB_CASHIER';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		try{
			list($type, $payData) = self::addOrder($wayCode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if($wayCode == 'QR_CASHIER' && !checkwechat() && $mdevice!='wechat'){
			$type = 'codeurl';
		}
		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}elseif (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$payData];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$payData];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		global $channel;
		if(in_array('1',$channel['apptype'])){
			$wayCode = 'YSF_NATIVE';
		}elseif(in_array('5',$channel['apptype'])){
			$wayCode = 'QR_CASHIER';
		}elseif(in_array('6',$channel['apptype'])){
			$wayCode = 'WEB_CASHIER';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		try{
			list($type, $payData) = self::addOrder($wayCode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$payData];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		//①、获取用户openid
		if($channel['appwxmp']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			try{
				$openid = wechat_oauth($wxinfo);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
		}else{
			if (!isset($_GET['channelUserId'])) {
				$apiurl = $channel['appurl'].'api/channelUserId/jump';
				$redirect_url = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				$param = [
					'mchNo' => $channel['appmchid'],
					'appId' => $channel['appid'],
					'ifCode' => 'AUTO',
					'redirectUrl' => $redirect_url,
					'reqTime' => self::getMillisecond(),
					'version' => '1.0',
					'signType' => 'MD5',
				];
				$param['sign'] = self::make_sign($param, $channel['appkey']);
				$jump_url = $apiurl.'?'.http_build_query($param);
				return ['type'=>'jump','url'=>$jump_url];
			}else{
				$openid = $_GET['channelUserId'];
			}
		}

		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;
		
		//②、统一下单
		try{
			$extra = json_encode(['openid' => $openid]);
			list($type, $jsApiParameters) = self::addOrder('WX_JSAPI', $extra);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
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
			$extra = json_encode(['openid' => $openid]);
			list($type, $jsApiParameters) = self::addOrder('WX_LITE', $extra);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('2',$channel['apptype'])){ //H5支付
			try{
				list($type,$jump_url) = self::addOrder('WX_H5');
				return ['type'=>'jump','url'=>$jump_url];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信H5支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('7',$channel['apptype'])){ //APP支付
			try{
				list($type,$code_url) = self::addOrder('WX_APP');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信H5支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'qrcode','page'=>'wxpay_h5','url'=>$code_url];
		}elseif(in_array('4',$channel['apptype']) && $channel['appwxa']>0){ //小程序支付
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}elseif(in_array('3',$channel['apptype'])){ //公众号支付
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(strpos($_SERVER['CONTENT_TYPE'], 'application/json')!==false){
			$data = file_get_contents('php://input');
			$arr = json_decode($data, true);
		}elseif(isset($_POST['sign'])){
			$arr = $_POST;
		}elseif(isset($_GET['sign'])){
			$arr = $_GET;
		}else{
			return ['type'=>'html','data'=>'no data'];
		}

		$sign = self::make_sign($arr,$channel['appkey']);

		if($sign===$arr["sign"]){
			if($arr['state'] == '2'){
				$out_trade_no = $arr['mchOrderNo'];
				$api_trade_no = $arr['payOrderId'];
				$money = $arr['amount'];
				$bill_trade_no = $arr['channelOrderNo'];

				if ($out_trade_no == TRADE_NO && $money==strval($order['realmoney']*100)) {
					processNotify($order, $api_trade_no, null, $bill_trade_no);
				}
				return ['type'=>'html','data'=>'success'];
			}else{
				return ['type'=>'html','data'=>'state='.$arr['state']];
			}
		}else{
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付返回页面
	static public function return(){
		global $channel, $order;
		
		$sign = self::make_sign($_GET,$channel['appkey']);

		if($sign===$_GET["sign"]){
			if($_GET['state'] == '2'){
				$out_trade_no = daddslashes($_GET['mchOrderNo']);
				$api_trade_no = daddslashes($_GET['payOrderId']);
				$money = $_GET['amount'];

				if ($out_trade_no == TRADE_NO && $money==strval($order['realmoney']*100)) {
					processReturn($order, $api_trade_no);
				}else{
					return ['type'=>'error','msg'=>'订单信息校验失败'];
				}
			}else{
				return ['type'=>'page','page'=>'return'];
			}
		}else{
			return ['type'=>'error','msg'=>'签名验证失败'];
		}
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$apiurl = $channel['appurl'].'api/refund/refundOrder';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'payOrderId' => $order['api_trade_no'],
			'mchRefundNo' => $order['refund_no'],
			'refundAmount' => round($order['refundmoney']*100),
			'currency' => 'cny',
			'refundReason' => '申请退款',
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		$result = json_decode($data, true);

		if (isset($result['code']) && $result['code'] == 0) {
			if($result['data']['errMsg']){
				return ['code'=>-1, 'msg'=>'['.$result['data']['errCode'].']'.$result['data']['errMsg']];
			}elseif($result['data']['error']){
				return ['code'=>-1, 'msg'=>$result['data']['error']];
			}
			return ['code'=>0, 'trade_no'=>$result['data']['refundOrderId'], 'refund_fee'=>$order['refundmoney']];
		} else {
			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $clientip, $conf;
		if(empty($channel) || empty($bizParam))exit();
		$type = $bizParam['type'];
		if($type == 'alipay'){
			$entryType = 'ALIPAY_CASH';
		}elseif($type == 'wxpay'){
			$entryType = 'WX_CASH';
		}elseif($type == 'bank'){
			$entryType = 'BANK_CARD';
		}

		$apiurl = $channel['appurl'].'api/transferOrder';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'mchOrderNo' => $bizParam['out_biz_no'],
			'ifCode' => $type,
			'entryType' => $entryType,
			'amount' => round($bizParam['money']*100),
			'currency' => 'cny',
			'accountNo' => $bizParam['payee_account'],
			'accountName' => $bizParam['payee_real_name'],
			'clientIp' => $clientip,
			'transferDesc' => $bizParam['transfer_desc'],
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		$result = json_decode($data, true);

		if (isset($result['code']) && $result['code'] == 0) {
			if($result['data']['errMsg']){
				return ['code'=>-1, 'errcode'=>$result['data']['errCode'], 'msg'=>'['.$result['data']['errCode'].']'.$result['data']['errMsg']];
			}elseif($result['data']['error']){
				return ['code'=>-1, 'msg'=>$result['data']['error']];
			}
			if($result['data']['state'] == 2){
				$status = 1;
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'orderid'=>$result['data']['transferId'], 'paydate'=>date('Y-m-d H:i:s')];
		} else {
			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$apiurl = $channel['appurl'].'api/transfer/query';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'transferId' => $bizParam['orderid'],
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		$result = json_decode($data, true);

		if (isset($result['code']) && $result['code'] == 0) {
			if($result['data']['state'] == 2){
				$status = 1;
			}elseif($result['data']['state'] == 1){
				$status = 0;
			}else{
				$status = 2;
			}
			$paydate = date('Y-m-d H:i:s', intval($result['data']['successTime']/1000));
			if($result['data']['errCode'] && $result['data']['errMsg']){
				$errmsg = '['.$result['data']['errCode'].']'.$result['data']['errMsg'];
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>$result['data']['amount'], 'paydate'=>$paydate, 'errmsg'=>$errmsg];
		} else {
			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
		}
	}

	static public function transfernotify(){
		global $channel;

		if(isset($_POST['sign'])){
			$arr = $_POST;
		}elseif(isset($_GET['sign'])){
			$arr = $_GET;
		}else{
			return ['type'=>'html','data'=>'no data'];
		}

		$sign = self::make_sign($arr,$channel['appkey']);

		if($sign===$arr["sign"]){
			if($arr['state'] == 2){
				$status = 1;
			}elseif($arr['state'] == 1){
				$status = 0;
			}else{
				$status = 2;
			}
			if($arr['errCode'] && $arr['errMsg']){
				$errmsg = '['.$arr['errCode'].']'.$arr['errMsg'];
			}
			processTransfer($arr['mchOrderNo'], $status, $errmsg);
			return ['type'=>'html','data'=>'success'];
		}else{
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

}