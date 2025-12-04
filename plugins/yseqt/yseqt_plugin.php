<?php

class yseqt_plugin
{
	static public $info = [
		'name'        => 'yseqt', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '银盛e企通', //支付插件显示名称
		'author'      => '银盛', //支付插件作者
		'link'        => 'https://www.ysepay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
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
				'note' => '',
			],
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '公众号支付',
			'2' => '小程序H5支付',
			'3' => 'JS支付',
		],
		'select_bank' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'select' => null,
		'note' => '只能使用RSA证书！需要将商户私钥证书client.pfx（或商户号.pfx）上传到 /plugins/yseqt/cert 文件夹内', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
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
			if(checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}elseif(checkmobile() && (in_array('1',$channel['apptype']) || in_array('2',$channel['apptype']))){
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
			}elseif($order['typename']=='bank'){
				return self::bankjs();
			}
		}elseif($method == 'applet'){
			return self::wxapppay();
		}elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
				return self::wxpay();
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//正扫支付
	static private function scanPay($bank_type){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		$params = [
			'requestNo' => TRADE_NO,
			'payeeMerchantNo' => $channel['appmchid'],
			'orderDesc' => $ordername,
			'amount' => $order['realmoney'],
			'bankType' => $bank_type,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($order['profits'] > 0){
			$params['isDivision'] = 'Y';
		}

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		return \lib\Payment::lockPayData(TRADE_NO, function () use ($client, $params) {
            $result = $client->execute('scanPay', $params);
			if($result['subCode'] == 'COM000'){
				return $result['qrCode'];
			}else{
				throw new Exception($result['subMsg']);
			}
        });
	}

	//聚合收银台支付
	static private function cashierPay($pay_mode){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		$params = [
			'requestNo' => TRADE_NO,
			'payeeMerchantNo' => $channel['appmchid'],
			'orderDesc' => $ordername,
			'amount' => $order['realmoney'],
			'payMode' => $pay_mode,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'isFastPay' => 'Y'
		];
		if($order['profits'] > 0){
			$params['isDivision'] = 'Y';
		}

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		return \lib\Payment::lockPayData(TRADE_NO, function () use ($client, $params) {
            $result = $client->execute('cashierPay', $params);
			if($result['subCode'] == 'COM000'){
				return $result['payData'];
			}else{
				throw new Exception($result['subMsg']);
			}
        });
	}

	//聚合JS支付
	static private function jsPay($bankType, $payMode, $openid = null, $appid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		$params = [
			'requestNo' => TRADE_NO,
			'payeeMerchantNo' => $channel['appmchid'],
			'orderDesc' => $ordername,
			'amount' => $order['realmoney'],
			'bankType' => $bankType,
			'payMode' => $payMode,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($payMode == '28' || $payMode == '29'){
			$params['wxAppId'] = $appid;
			$params['wxOpenId'] = $openid;
		}elseif($payMode == '26'){
			$params['alipayId'] = $openid;
		}elseif($payMode == '30'){
			$params['unionUserId'] = $openid;
		}
		if($order['profits'] > 0){
			$params['isDivision'] = 'Y';
		}

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		return \lib\Payment::lockPayData(TRADE_NO, function () use ($client, $params) {
            $result = $client->execute('jsPay', $params);
			if($result['subCode'] == 'COM000'||$result['subCode'] == 'COM004'){
				return $result['payData'];
			}else{
				throw new Exception($result['subMsg']);
			}
        });
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $siteurl, $mdevice;
		if(in_array('1',$channel['apptype'])){
			try{
				$code_url = self::scanPay('1903000');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
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
			$paydata = self::jsPay('1903000', '26', $user_id);
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
		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::cashierPay('29h5');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
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
				$code_url = self::cashierPay('29UrlScheme');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}
		elseif(in_array('1',$channel['apptype'])){
			try{
				$code_url = self::cashierPay('28');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			if (checkwechat() || $mdevice=='wechat') {
				return ['type'=>'jump','url'=>$code_url];
			} else {
				return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
			}
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
			$paydata = self::jsPay('1902000', '28', $openid, $wxinfo['appid']);
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
			$paydata = self::jsPay('1902000', '29', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}
		exit(json_encode(['code'=>0, 'data'=>json_decode($paydata, true)]));
	}

	//微信APP支付
	static public function wxapppay(){
		try{
			$paydata = self::cashierPay('29');
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>['appId'=>'wx05e71f6f41f6b9b4', 'miniProgramId'=>'gh_d27d42772cd8', 'path'=>'pages/index/index', 'extraData'=>json_decode($paydata, true)]];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::scanPay('9001002');
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
			$paydata = self::jsPay('9001002', '30', $order['sub_openid']);
			$code_url = json_decode($paydata, true)['redirectUrl'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'jump','url'=>$code_url];
	}

	static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PLUGIN_ROOT."yseqt/inc/YseqtClient.php");

		$params = [
			'userAuthCode' => $userAuthCode,
			'appUpIdentifier' => get_unionpay_ua(),
		];

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		try{
			$result = $client->execute('getUpUserId', $params);
			if($result['subCode'] == 'COM000'){
				return ['code'=>0, 'data'=>$result['unionUserId']];
			}else{
				return ['code'=>-1, 'msg'=>$result['subMsg']];
			}
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		//计算得出通知验证结果
		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST);

		if($verify_result) {//验证成功
			$arr = json_decode($_POST['bizResponseJson'], true);
			$out_trade_no = $arr['requestNo'];
			$trade_no = $arr['tradeSn'];
			$buyer_id = !empty($arr['openId']) ? $arr['openId'] : $arr['userId'];
			$total_amount = $arr['amount'];
			$bill_trade_no = $arr['channelRecvSn'];
			$bill_mch_trade_no = $arr['channelSendSn'];
			if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);

			if ($arr['state'] == 'SUCCESS') {
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

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $DB;
		if(empty($order))exit();

		require_once(PAY_ROOT."inc/YseqtClient.php");

		$params = [
			'requestNo' => $order['refund_no'],
			'origRequestNo' => $order['trade_no'],
			'origTradeSn' => $order['api_trade_no'],
			'amount' => $order['refundmoney'],
			'reason' => '申请退款',
			'isDivision' => 'N'
		];
		if($order['profits'] > 0){
			$psorder = \lib\ProfitSharing\CommUtil::getOrder($order['trade_no']);
			if($psorder && ($psorder['status'] == 1 || $psorder['status'] == 2)){
				$refundSplitInfo = [];
				if($psorder['rdata']){
					$allmoney = 0;
					$leftmoney = (float)$order['refundmoney'];
					foreach($psorder['rdata'] as $receiver){
						$money = $receiver['money'] > $leftmoney ? $leftmoney : $receiver['money'];
						$refundSplitInfo[] = [
							'refundMercId' => $receiver['account'],
							'refundAmount' => $money,
						];
						$allmoney += $receiver['money'];
						$leftmoney = round($leftmoney - $money, 2);
						if($leftmoney <= 0) break;
					}
					if($order_money > $allmoney && $leftmoney > 0){
						$psmoney2 = round($order_money-$allmoney, 2);
						$psmoney2 = $psmoney2 > $leftmoney ? $leftmoney : $psmoney2;
						$refundSplitInfo[] = [
							'refundMercId' => $channel['appmchid'],
							'refundAmount' => $psmoney2,
						];
					}
				}else{
					$refundSplitInfo[] = [
						'refundMercId' => $psorder['account'],
						'refundAmount' => $psorder['money'] > $order['refundmoney'] ? $order['refundmoney'] : $psorder['money'],
					];
					if($order['refundmoney'] > $psorder['money']){
						$refundSplitInfo[] = [
							'refundMercId' => $channel['appmchid'],
							'refundAmount' => round($order['refundmoney'] - $psorder['money'], 2),
						];
					}
				}
				$params['isDivision'] = 'Y';
				$params['refundSplitInfo'] = $refundSplitInfo;
			}
		}

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		try{
			$result = $client->execute('refund', $params);
			if($result['subCode'] == 'COM000' || $result['subCode'] == 'COM004'){
				if($psorder && ($psorder['status'] == 1 || $psorder['status'] == 2)){
					$DB->update('psorder', ['status'=>4], ['id'=>$psorder['id']]);
				}
				return ['code'=>0, 'trade_no'=>$result['refundSn'], 'refund_fee'=>$result['amount']];
			}else{
				$params['requestNo'] = $order['refund_no'].'1';
				$params['refundSource'] = '01';
				$result = $client->execute('refund', $params);
				if($result['subCode'] == 'COM000' || $result['subCode'] == 'COM004'){
					if($psorder && ($psorder['status'] == 1 || $psorder['status'] == 2)){
						$DB->update('psorder', ['status'=>4], ['id'=>$psorder['id']]);
					}
					return ['code'=>0, 'trade_no'=>$result['refundSn'], 'refund_fee'=>$result['amount']];
				}else{
					return ['code'=>-1, 'msg'=>$result['subMsg']];
				}
			}
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//进件通知
	static public function applynotify(){
		global $channel;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		//计算得出通知验证结果
		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST);

		if($verify_result) {//验证成功
			$arr = json_decode($_POST['bizResponseJson'], true);

			if($_POST['serviceNo'] == 'registerNotify'){
				$model = \lib\Applyments\CommUtil::getModel2($channel);
				if($model) $model->notify($arr);
			}elseif($_POST['serviceNo'] == 'contractSignNotify'){
				$model = \lib\Applyments\CommUtil::getModel2($channel);
				if($model) $model->signNotify($arr);
			}
			
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		require_once(PLUGIN_ROOT.'yseqt/inc/YseqtClient.php');

		$params = [
			'requestNo' => $bizParam['out_biz_no'],
			'merchantNo' => $channel['appmchid'],
			'amount' => $bizParam['money'],
			'orderNote' => $bizParam['transfer_desc'],
			'bankAccountNo' => $bizParam['payee_account'],
			'bankAccountName' => $bizParam['payee_real_name'],
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
		];

		try{
			$client = new YseqtClient($channel['appid'], $channel['appkey']);
			$result = $client->execute('paymentRequest', $params);
			if($result['subCode'] == 'COM000'){
				return ['code'=>0, 'status'=>0, 'orderid'=>$result['tradeSn'], 'paydate'=>date('Y-m-d H:i:s')];
			}else{
				return ['code'=>-1, 'msg'=>$result['subMsg']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require_once(PLUGIN_ROOT.'yseqt/inc/YseqtClient.php');

		$params = [
			'requestNo' => $bizParam['out_biz_no'],
			'tradeDate' => substr($bizParam['paydate'], 0, 8),
		];

		try{
			$client = new YseqtClient($channel['appid'], $channel['appkey']);
			$result = $client->execute('paymentQuery', $params);
			if($result['subCode'] == 'COM000'){
				if($result['state'] == 'SUCCESS'){
					$status = 1;
				}elseif($result['state'] == 'PROCESSING' || $result['orderStatus'] == 'WAIT_PAY'){
					$status = 0;
				}else{
					$status = 2;
					if($result['msg']){
						$errmsg = $result['msg'];
					}
				}
				return ['code'=>0, 'status'=>$status, 'amount'=>$result['amount'], 'paydate'=>date('Y-m-d H:i:s', strtotime($result['tradeDate'])), 'errmsg'=>$errmsg];
			}else{
				return ['code'=>-1, 'msg'=>$result['subMsg']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//余额查询
	static public function balance_query($channel, $bizParam){
		if(empty($channel))exit();

		require_once(PLUGIN_ROOT.'yseqt/inc/YseqtClient.php');

		$params = [
			'merchantNo' => $channel['appmchid'],
		];

		try{
			$client = new YseqtClient($channel['appid'], $channel['appkey']);
			$result = $client->execute('paymentQuery', $params);
			
			$desc = '账户总金额：'.$result['totalAmount'].'元';
			$account01 = array_filter($result['accountList'], function($item){
				return $item['accountType'] == '01';
			});
			$account01 = $account01[array_key_first($account01)];
			$account02 = array_filter($result['accountList'], function($item){
				return $item['accountType'] == '02';
			});
			$account02 = $account02[array_key_first($account02)];
			if(!empty($account01['cashAmount'])){
				$desc .= '，可提现金额：'.$account01['cashAmount'].'元';
			}
			if(!empty($account01['uncashAmount']) && $account01['uncashAmount'] > 0){
				$desc .= '，不可提现金额：'.$account01['uncashAmount'].'元';
			}
			elseif(!empty($account02['uncashAmount'])){
				$desc .= '，不可提现金额：'.$account02['uncashAmount'].'元';
			}
			if(!empty($result['settledUnpaidAmount'])){
				$desc .= '，待结算金额：'.$result['settledUnpaidAmount'].'元';
			}

			return ['code'=>0, 'amount'=>$account01['cashAmount'], 'msg'=>$desc];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//付款异步回调
	static public function transfernotify(){
		global $channel;

		require_once(PAY_ROOT."inc/YseqtClient.php");

		$client = new YseqtClient($channel['appid'], $channel['appkey']);
		$verify_result = $client->verify($_POST);

		if($verify_result) {//验证成功
			$arr = json_decode($_POST['bizResponseJson'], true);

			if($arr['state'] == 'SUCCESS'){
				$status = 1;
			}else{
				$status = 2;
				if($arr['msg']){
					$errmsg = $arr['msg'];
				}
			}
			processTransfer($arr['requestNo'], $status, $errmsg);
			
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}
}