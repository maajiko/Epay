<?php
class hlpay_plugin
{
	static public $info = [
		'name'        => 'hlpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '汇联支付', //支付插件显示名称
		'author'      => '汇联', //支付插件作者
		'link'        => 'https://www.huilianlink.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['alipay','wxpay'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用APPID',
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
			'channelcode' => [
				'name' => '通道编码',
				'type' => 'input',
				'note' => '可留空，留空为随机路由',
			],
			'appmchid' => [
				'name' => '子商户编码',
				'type' => 'input',
				'note' => '仅服务商可传，普通商户请勿填写',
			],
			'appswitch' => [
				'name' => '场景类型',
				'type' => 'select',
				'options' => [1=>'线下',2=>'线上'],
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
			'3' => 'PC支付',
			'4' => 'H5支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
		],
		'note' => null, //支付密钥填写说明
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
			if($mdevice=='wechat' && in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一下单
	static private function addOrder($pay_type, $pay_sub_type, $sub_appid=null, $sub_openid=null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/HlpayClient.php";

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);

		if($_GET['r'] == 1){
			$returnUrl = $siteurl.'pay/ok/'.TRADE_NO.'/';
		}else{
			$returnUrl = $siteurl.'pay/return/'.TRADE_NO.'/';
		}
		$param = [
			'payType' => $pay_type,
			'paySubType' => $pay_sub_type,
			'sceneType' => $channel['appswitch'],
			'mchOrderNo' => TRADE_NO,
			'amount' => $order['realmoney'],
			'clientIp'  => $clientip,
			'subject'  => $ordername,
			'notifyUrl'  => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'redirectUrl' => $returnUrl,
		];
		if(!empty($channel['channelcode'])){
			$param['channelCode'] = $channel['channelcode'];
		}
		$extra = [];
		if($sub_appid && $sub_openid){
			$extra['subAppid'] = $sub_appid;
			$extra['userId'] = $sub_openid;
		}elseif($sub_openid){
			$extra['userId'] = $sub_openid;
		}
		if($pay_type == 'WECHAT' && ($pay_sub_type == 'H5' || $pay_sub_type == 'APP')){
			$extra['originalType'] = 0;
			$extra['appName'] = $conf['sitename'];
		}
		if(!empty($extra)){
			$param['extra'] = $extra;
		}
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->execute('/openapi/pay/create', $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['payOrderNo']);
			return $result;
		});
	}

	//支付宝支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('4',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$pay_sub_type = 'H5';
		}elseif(in_array('3',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$pay_sub_type = 'PC';
		}elseif(in_array('1',$channel['apptype'])){
			$pay_sub_type = 'NATIVE';
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}elseif(in_array('4',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/alipay/'.TRADE_NO.'/?r=1';
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$qrcode_url];
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		try{
			$result = self::addOrder('ALIPAY', $pay_sub_type);
			$code_url = $result['payInfo'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		if(checkalipay() || $mdevice=='alipay' || $pay_sub_type=='H5' || $pay_sub_type=='PC'){
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
			$result = self::addOrder('ALIPAY', 'JSAPI', null, $user_id);
			$trade_no = $result['payInfo'];
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
				$result = self::addOrder('WECHAT', 'NATIVE');
				$code_url = $result['payInfo'];
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
			$result = self::addOrder('WECHAT', 'JSAPI', $wxinfo['appid'], $openid);
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
			$result = self::addOrder('WECHAT', 'MINI_APP', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($result['payInfo'], true)]));
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
		global $channel;
		try{
			$code_url = self::addOrder('UNION_PAY', 'NATIVE');
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
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		require_once PAY_ROOT."inc/HlpayClient.php";

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);
		$verify_result = $client->verifySign($arr);

		if($verify_result){
			$data = $arr['data'];
			if ($data['state'] == '3') {
				$out_trade_no = $data['mchOrderNo'];
				$trade_no = $data['payOrderNo'];
				$bill_trade_no = $data['channelOrderNo'];
				$bill_mch_trade_no = $data['instOrderNo'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, null, $bill_trade_no, $bill_mch_trade_no);
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

		require_once PAY_ROOT."inc/HlpayClient.php";

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);
		
		$param = [
			'payOrderNo' => $order['api_trade_no'],
			'mchRefundOrderNo' => $order['refund_no'],
			'amount' => $order['refundmoney'],
		];

		try{
			$result = $client->execute('/openapi/pay/refund', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		return ['code'=>0, 'trade_no'=>$result['instOrderNo'], 'refund_fee'=>$result['refundAmount']];
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $clientip, $conf;
		if(empty($channel) || empty($bizParam))exit();
		if($bizParam['type'] == 'alipay') $entry_type = '1';
		else if($bizParam['type'] == 'wxpay') $entry_type = '2';
		else if($bizParam['type'] == 'bank') $entry_type = '3';

		require_once PLUGIN_ROOT.'hlpay/inc/HlpayClient.php';

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);

		$param = [
			'mchChannelCode' => $channel['channelcode'],
			'entryType' => $entry_type,
			'mchOrderNo' => $bizParam['out_biz_no'],
			'amount' => $bizParam['money'],
			'clientIp' => $clientip,
			'remark' => $bizParam['desc'],
			'name' => $bizParam['payee_real_name'],
			'cardNo' => $bizParam['payee_account'],
		];
		if($bizParam['type'] == 'bank'){
			$param['payeeType'] = '1';
		}
		if($bizParam['type'] == 'alipay'){
			if(is_numeric($bizParam['payee_account']) && substr($bizParam['payee_account'],0,4)=='2088')$is_userid = 1;
			elseif(strpos($bizParam['payee_account'], '@')!==false || is_numeric($bizParam['payee_account']))$is_userid = 2;
			else $is_userid = 3;
			$param['extra'] = ['accountType'=>$is_userid];
		}

		try{
			$result = $client->execute('/openapi/payment/create', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		if($result['status'] == 3){
			$status = 1;
		}elseif($result['status'] == 4 || $result['status'] == 6){
			$status = 2;
		}else{
			$status = 0;
		}
		return ['code'=>0, 'status'=>$status, 'orderid'=>$result['payOrderNo'], 'paydate'=>date('Y-m-d H:i:s')];
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require_once PLUGIN_ROOT.'hlpay/inc/HlpayClient.php';

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);

		$param = [
			'mchOrderNo' => $bizParam['out_biz_no'],
		];

		try{
			$result = $client->execute('/openapi/payment/query', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		if($result['status'] == 3){
			$status = 1;
		}elseif($result['status'] == 4 || $result['status'] == 6){
			$status = 2;
			$errmsg = '转账失败';
		}else{
			$status = 0;
		}

		return ['code'=>0, 'status'=>$status, 'amount'=>$result['amount'], 'paydate'=>$result['successTime'], 'errmsg'=>$errmsg];
	}

	//余额查询
	static public function balance_query($channel, $bizParam){
		if(empty($channel))exit();

		require_once PLUGIN_ROOT.'hlpay/inc/HlpayClient.php';

		$client = new HlpayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appmchid']);

		$param = [
			'mchChannelCode' => $channel['channelcode'],
		];

		try{
			$result = $client->execute('/openapi/payment/account', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		$data = array_filter($result, function($value){
			return $value['acctType'] == '3';
		});
		if(empty($data)) return ['code'=>-1, 'msg'=>'未查询到代付账户'];
		return ['code'=>0, 'amount'=>$data[array_key_first($data)]['balance']];
	}
}