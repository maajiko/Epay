<?php

/**
 * https://www.yuque.com/leshua-jhzf/qrcode_pay
 */
class leshua_plugin
{
	static public $info = [
		'name'        => 'leshua', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '乐刷聚合支付', //支付插件显示名称
		'author'      => '乐刷', //支付插件作者
		'link'        => 'http://www.leshuazf.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '交易密钥',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '异步通知密钥',
				'type' => 'input',
				'note' => '',
			],
		],
		'select_alipay' => [
			'1' => 'Native支付',
			'2' => 'JSAPI支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	const API_URL = 'https://paygate.leshuazf.com/cgi-bin/lepos_pay_gateway.cgi';

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
				return self::wxjspay();
			}elseif($device=='mobile' && $channel['appwxa']>0){
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
			if($k != "sign" && $k != "error_code"){
				if(is_array($v)) $v = '';
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr .= 'key='.$key;
		$sign = strtoupper(md5($signstr));
		return $sign;
	}

    static private function xml2array($xml)
    {
        if (!$xml) {
            return false;
        }
		LIBXML_VERSION < 20900 && libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
    }

	static private function addOrder($jspay_flag, $pay_way = null, $openid = null, $appid = null){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;

		$params = [
			'service' => 'get_tdcode',
			'jspay_flag' => $jspay_flag,
			'pay_way' => $pay_way,
			'merchant_id' => $channel['appid'],
			'third_order_id' => TRADE_NO,
			'amount' => strval($order['realmoney']*100),
			'body' => $ordername,
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'client_ip' => $clientip,
			'nonce_str' => getSid(),
		];
		//if($pay_way) $params['pay_way'] = $pay_way;
		if($openid) $params['sub_openid'] = $openid;
		if($appid) $params['appid'] = $appid;
		$params['sign'] = self::make_sign($params, $channel['appkey']);
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($params) {
			$response = get_curl(self::API_URL, http_build_query($params));
			$result = self::xml2array($response);
			if(isset($result["resp_code"]) && $result["resp_code"]=='0'){
				if(isset($result['result_code']) && $result['result_code'] == '0'){
					\lib\Payment::updateOrder(TRADE_NO, $result['leshua_order_id']);
					return $result;
				}else{
					throw new Exception($result["error_msg"]?$result["error_msg"]:'下单失败');
				}
			}else{
				throw new Exception($result["resp_msg"]?$result["resp_msg"]:'返回数据解析失败');
			}
		});
	}

	static private function scanpay(){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;

		$params = [
			'service' => 'upload_authcode',
			'auth_code' => $order['auth_code'],
			'merchant_id' => $channel['appid'],
			'third_order_id' => TRADE_NO,
			'amount' => strval($order['realmoney']*100),
			'body' => $ordername,
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'client_ip' => $clientip,
			'nonce_str' => getSid(),
		];
		$params['sign'] = self::make_sign($params, $channel['appkey']);
		
		$response = get_curl(self::API_URL, http_build_query($params));
		$result = self::xml2array($response);
		if(isset($result["resp_code"]) && $result["resp_code"]=='0'){
			if(isset($result['result_code']) && $result['result_code'] == '0'){
				\lib\Payment::updateOrder(TRADE_NO, $result['leshua_order_id']);
				return $result;
			}else{
				throw new Exception($result["error_msg"]?$result["error_msg"]:'下单失败');
			}
		}else{
			throw new Exception($result["resp_msg"]?$result["resp_msg"]:'返回数据解析失败');
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$result = self::addOrder('0', 'ZFBZF');
				$code_url = $result['td_code'];
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
			$result = self::addOrder('1', 'ZFBZF', $user_id);
			$pay_info = json_decode($result['jspay_info'], true);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$pay_info['tradeNO']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$pay_info['tradeNO'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}else{
			try{
				$result = self::addOrder('2', 'WXZF');
				$code_url = $result['jspay_url'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}

		if($mdevice == 'wechat' || checkwechat()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif ($device == 'mobile' || checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method, $conf, $clientip;

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
			$result = self::addOrder('1', 'WXZF', $openid, $wxinfo['appid']);
			$pay_info = $result['jspay_info'];
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
			$result = self::addOrder('3', 'WXZF', $openid, $wxinfo['appid']);
			$pay_info = $result['jspay_info'];
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
			$result = self::addOrder('0', 'UPSMZF');
			$code_url = $result['td_code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = self::xml2array($json);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		$sign = strtolower(self::make_sign($arr, $channel['appsecret']));

		if($sign === $arr["sign"]){
			if($arr['status'] == '2'){
				$out_trade_no = $arr['third_order_id'];
				$api_trade_no = $arr['leshua_order_id'];
				$money = $arr['account'];
				$buyer = $arr['sub_openid'];
				$bill_trade_no = $arr['out_transaction_id'];
	
				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'html','data'=>'000000'];
		}else{
			return ['type'=>'html','data'=>'fail'];
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

		$params = [
			'service' => 'unified_refund',
			'merchant_id' => $channel['appid'],
			'leshua_order_id' => $order['api_trade_no'],
			'merchant_refund_id' => $order['refund_no'],
			'refund_amount' => strval($order['refundmoney']*100),
			'nonce_str' => getSid(),
		];
		$params['sign'] = self::make_sign($params, $channel['appkey']);

		$response = get_curl(self::API_URL, http_build_query($params));
		$result = self::xml2array($response);
		if(isset($result["resp_code"]) && $result["resp_code"]=='0'){
			if(isset($result['result_code']) && $result['result_code'] == '0'){
				return ['code'=>0, 'trade_no'=>$result['leshua_refund_id'], 'refund_fee'=>$result['refund_amount']/100];
			}else{
				return ['code'=>-1, 'msg'=>$result["error_msg"]];
			}
		}else{
			return ['code'=>-1, 'msg'=>$result["resp_msg"]?$result["resp_msg"]:'返回数据解析失败'];
		}
	}

}