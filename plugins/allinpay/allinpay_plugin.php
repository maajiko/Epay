<?php

class allinpay_plugin
{
	static public $info = [
		'name'        => 'allinpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '通联支付', //支付插件显示名称
		'author'      => '通联', //支付插件作者
		'link'        => 'https://www.allinpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appid' => [
				'name' => '应用ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '通联公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'orgid' => [
				'name' => '代理商商户号',
				'type' => 'input',
				'note' => '仅代理商需要填写',
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号/小程序支付',
		],
		'select_bank' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'note' => '', //支付密钥填写说明
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
		}elseif($order['typename']=='qqpay'){
			return self::qqpay();
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一支付接口
	static private function addOrder($paytype, $sub_appid = null, $openid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/PayService.class.php");

		$apiurl = 'https://vsp.allinpay.com/apiweb/unitorder/pay';

		$params = [
			'trxamt' => strval($order['realmoney']*100),
			'reqsn' => TRADE_NO,
			'paytype' => $paytype,
			'body' => $ordername,
			'validtime' => '30',
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'cusip' => $clientip,
		];
		if($sub_appid) $params['sub_appid'] = $sub_appid;
		if($openid){
			$params['acct'] = $openid;
			$params['front_url'] = $siteurl.'pay/return/'.TRADE_NO.'/';
		}
		if($order['profits'] > 0){
			$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
			if($psreceiver){
				$asinfo = '';
				foreach($psreceiver['info'] as $receiver) {
					$psmoney = round(floor($order['realmoney'] * $receiver['rate']));
					$asinfo .= $receiver['account'].':01:'.$psmoney.';';
				}
				$asinfo = rtrim($asinfo, ';');
			}
		}

		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$result = $client->submit($apiurl, $params);
		if($result['trxstatus'] == '0000') {
			return $result['payinfo'];
		}else{
			throw new Exception($result['errmsg']);
		}
	}

	//H5收银台
	static private function cashier(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/PayService.class.php");

		$apiurl = 'https://syb.allinpay.com/apiweb/h5unionpay/unionorder';

		$params = [
			'trxamt' => strval($order['realmoney']*100),
			'reqsn' => TRADE_NO,
			'body' => $ordername,
			'validtime' => '30',
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'returl' => $siteurl . 'pay/return/' . TRADE_NO . '/',
			'charset' => 'UTF-8',
		];
		if($order['profits'] > 0){
			$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
			if($psreceiver){
				$asinfo = '';
				foreach($psreceiver['info'] as $receiver) {
					$psmoney = round(floor($order['realmoney'] * $receiver['rate']));
					$asinfo .= $receiver['account'].':01:'.$psmoney.';';
				}
				$asinfo = rtrim($asinfo, ';');
			}
		}

		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$data = $client->cashier($params);
		
		$html_text = '<form action="'.$apiurl.'" method="post" id="dopay">';
		foreach($data as $k => $v) {
			$html_text .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\" />\n";
		}
		$html_text .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("dopay").submit();</script>';

		return ['type'=>'html','data'=>$html_text];
	}

	//小程序收银台
	static private function applet($paytype){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/PayService.class.php");

		$params = [
			'trxamt' => strval($order['realmoney']*100),
			'reqsn' => TRADE_NO,
			'paytype' => $paytype,
			'body' => $ordername,
			'validtime' => '30',
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($order['profits'] > 0){
			$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
			if($psreceiver){
				$asinfo = '';
				foreach($psreceiver['info'] as $receiver) {
					$psmoney = round(floor($order['realmoney'] * $receiver['rate']));
					$asinfo .= $receiver['account'].':01:'.$psmoney.';';
				}
				$asinfo = rtrim($asinfo, ';');
			}
		}

		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$data = $client->cashier($params);
		
		return $data;
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::addOrder('A01');
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
			$alipay_trade_no = self::addOrder('A02', null, $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$alipay_trade_no];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$alipay_trade_no, 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			if($channel['appwxmp']>0){
				$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			}else{
				$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			}
		}else{
			try{
				$code_url = self::addOrder('W01');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}

		if(checkwechat() || $mdevice == 'wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device == 'mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

        if ($channel['appwxa']>0) {
            $wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
            try {
                $code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>$e->getMessage()];
            }
            return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
        }elseif($channel['appwxmp']>0){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $method, $order;

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
			$payinfo = self::addOrder('W02', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$payinfo];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$payinfo, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel;

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
			$payinfo = self::addOrder('W06', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::addOrder('Q01');
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
			$code_url = self::addOrder('U01');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if(checkunionpay()){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//云闪付JS支付
	static public function bankjs(){
		global $order;
		try{
			$code_url = self::addOrder('U02', null, $order['sub_openid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PLUGIN_ROOT."allinpay/inc/PayService.class.php");

		$apiurl = 'https://vsp.allinpay.com/apiweb/unitorder/authcodetouserid';
		$params = [
			'authcode' => $userAuthCode,
			'authtype' => '02',
			'identify' => get_unionpay_ua(),
		];

		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		try{
			$result = $client->submit($apiurl, $params);
			return ['code'=>0, 'data'=>$result['acct']];
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once(PAY_ROOT."inc/PayService.class.php");
		
		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->verifySign($_POST);

		if($verify_result) {//验证成功

			if ($_POST['trxstatus'] == '0000') {
				$out_trade_no = $_POST['cusorderid'];
				$api_trade_no = $_POST['trxid'];
				$money = $_POST['initamt'];
				$buyer = $_POST['acct'];
				$bill_trade_no = $_POST['chnltrxid'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}
	
	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once(PAY_ROOT."inc/PayService.class.php");

		$apiurl = 'https://vsp.allinpay.com/apiweb/tranx/refund';

		$params = [
			'trxamt' => strval($order['refundmoney']*100),
			'reqsn' => $order['refund_no'],
			'oldtrxid' => $order['api_trade_no'],
		];
		
		try{
			$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
			$result = $client->submit($apiurl, $params);

			return ['code'=>0, 'trade_no'=>$result['trxid'], 'refund_fee'=>$result['fee']];

		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//投诉通知
	static public function complainnotify(){
		global $channel;

		require_once(PAY_ROOT."inc/PayService.class.php");
		
		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->verifySign($_POST);

		if($verify_result) {//验证成功

			$data = json_decode($_POST['resource'], true);
			if($_POST['risktype'] == 'VIOLATION'){
				if(class_exists('\\lib\\WxMchRisk')){
					$model = new \lib\WxMchRisk($channel);
					$model->notify($data);
				}
			}elseif($_POST['risktype'] == 'COMPLAINT'){
				$model = \lib\Complain\CommUtil::getModel($channel);
				$model->refreshNewInfo($data['complaint_id'], $data['action_type']);
			}elseif($_POST['risktype'] == 'COMPLAINTV2'){
				$model = \lib\Complain\CommUtil::getModel($channel);
				$model->refreshNewInfo($data['complaint_id']);
			}
			
			return ['type'=>'html','data'=>'sccuess'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//进件通知
	static public function applynotify(){
		global $channel;

		require_once(PAY_ROOT."inc/PayService.class.php");
		
		$client = new PayService($channel['orgid'],$channel['appmchid'],$channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->verifySign($_POST);

		if($verify_result) {//验证成功

			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($_POST);
			
			return ['type'=>'html','data'=>'sccuess'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}
}