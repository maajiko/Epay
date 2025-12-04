<?php

class dinpay_plugin
{
	static public $info = [
		'name'        => 'dinpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '智付', //支付插件显示名称
		'author'      => '智付', //支付插件作者
		'link'        => 'https://www.dinpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => 'SM2-Hex格式',
			],
			'appkey' => [
				'name' => '平台公钥',
				'type' => 'textarea',
				'note' => 'SM2-Hex格式',
			],
			'appmchid' => [
				'name' => '子商户号',
				'type' => 'input',
				'note' => '可留空',
			],
			'reportid' => [
				'name' => '渠道商户报备ID',
				'type' => 'input',
				'note' => '可留空，多个报备ID可用,隔开',
			],
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'H5支付',
			'3' => 'JS支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => 'H5支付',
			'3' => 'JS支付',
		],
		'select' => null,
		'note' => '<a href="http://qqapi.cccyun.cc/dinpay.php" target="_blank" rel="noreferrer">智付SM2公私钥提取</a>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			if(checkalipay() && in_array('3',$channel['apptype']) && !in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && ($channel['appwxa']>0 || in_array('2',$channel['apptype']))){
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
			if($mdevice=='alipay' && in_array('3',$channel['apptype']) && !in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && ($channel['appwxa']>0 || in_array('2',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	static private function getReportId($reportid){
		global $order;
		if(!empty($order['param']) && is_numeric($order['param'])){
			return $order['param'];
		}
		if(strpos($reportid, ',')){
            $reportids = explode(',', $reportid);
            $reportid = $reportids[array_rand($reportids)];
        }
		global $DB;
		$DB->update('order', ['param'=>$reportid], ['trade_no'=>TRADE_NO]);
		return $reportid;
	}

	//扫码支付
	static private function qrcode($paytype){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/DinpayClient.php");

		$params = [
			'interfaceName' => 'AppPay',
			'paymentType' => $paytype,
			'paymentMethods' => 'SCAN',
			'paymentCode' => '1',
			'payAmount' => $order['realmoney'],
			'currency' => 'CNY',
			'orderNo' => TRADE_NO,
			'orderIp' => $clientip,
			'goodsName' => $ordername,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($order['profits']>0){
			self::handleProfits($params);
		}
		if(!empty($channel['reportid'])) $params['reportId'] = self::getReportId($channel['reportid']);

		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$result = $client->execute('/api/appPay/pay', $params);
		return $result['qrcode'];
	}

	//公众号支付
	static private function publicpay($paytype, $openid, $appid){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/DinpayClient.php");

		$params = [
			'interfaceName' => 'AppPayPublic',
			'paymentType' => $paytype,
			'paymentMethods' => 'PUBLIC',
			'appid' => $appid,
			'openid' => $openid,
			'payAmount' => $order['realmoney'],
			'currency' => 'CNY',
			'orderNo' => TRADE_NO,
			'orderIp' => $clientip,
			'goodsName' => $ordername,
			'isNative' => '1',
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'successToUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($order['profits']>0){
			self::handleProfits($params);
		}
		if(!empty($channel['reportid'])) $params['reportId'] = self::getReportId($channel['reportid']);

		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$result = $client->execute('/api/appPay/payPublic', $params);
		return $result['payInfo'];
	}

	//小程序支付
	static private function appletpay($paytype, $openid, $appid){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/DinpayClient.php");

		$params = [
			'interfaceName' => 'AppPayApplet',
			'paymentType' => $paytype,
			'paymentMethods' => 'APPLET',
			'appid' => $appid,
			'openid' => $openid,
			'payAmount' => $order['realmoney'],
			'currency' => 'CNY',
			'orderNo' => TRADE_NO,
			'orderIp' => $clientip,
			'goodsName' => $ordername,
			'isNative' => '1',
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			//'successToUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($order['profits']>0){
			self::handleProfits($params);
		}
		if(!empty($channel['reportid'])) $params['reportId'] = self::getReportId($channel['reportid']);

		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$result = $client->execute('/api/appPay/payApplet', $params);
		return $result['payInfo'];
	}

	//H5支付
	static private function h5pay($paytype){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/DinpayClient.php");

		$params = [
			'interfaceName' => 'AppPayH5WFT',
			'paymentType' => $paytype,
			'paymentMethods' => 'WAP',
			'payAmount' => $order['realmoney'],
			'currency' => 'CNY',
			'orderNo' => TRADE_NO,
			'orderIp' => $clientip,
			'applyName' => $conf['sitename'],
			'applyType' => 'AND_WAP',
			'applyId' => $siteurl,
			'isNative' => '0',
			'goodsName' => $ordername,
			'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'successToUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];
		if($order['profits']>0){
			self::handleProfits($params);
		}
		if(!empty($channel['reportid'])) $params['reportId'] = self::getReportId($channel['reportid']);

		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$result = $client->execute('/api/appPay/payH5', $params);
		return $result['payInfo'];
	}

	static private function handleProfits(&$params){
		global $order;
		$psreceiver = \lib\ProfitSharing\CommUtil::getReceiver($order['profits']);
		if($psreceiver){
			$splitRules = [];
			$i = 1;
			foreach($psreceiver['info'] as $receiver){
				$psmoney = round(floor($order['realmoney'] * $receiver['rate']) / 100, 2);
				$splitRules[] = [
					'splitBillMerchantNo' => $receiver['account'],
					'splitBillAmount' => $psmoney,
					'splitBillRequestNo' => TRADE_NO.$i++,
				];
			}
			$params['splitType'] = 'FIXED_AMOUNT';
			$params['splitRules'] = json_encode($splitRules);
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype']) || empty($channel['apptype'][0])){
			try{
				$code_url = self::qrcode('ALIPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('2',$channel['apptype'])){
			if(checkalipay() || $mdevice=='alipay'){
				try{
					$code_url = self::h5pay('ALIPAY');
				}catch(Exception $ex){
					return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
				}
			}else{
				$code_url = $siteurl.'pay/alipay/'.TRADE_NO.'/';
			}
		}elseif(in_array('3',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	static public function alipayjs(){
		global $method, $order;
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
			$payinfo = self::publicpay('ALIPAY', $user_id, '1');
			$result = json_decode($payinfo, true);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$result['tradeNO']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['tradeNO'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype'])){
			try{
				$code_url = self::qrcode('WXPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}

		if(checkwechat() || $mdevice=='wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::h5pay('WXPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$code_url];
		}elseif ($channel['appwxa']>0) {
            $wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
            try {
                $code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>$e->getMessage()];
            }
            return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
        }else{
			return self::wxpay();
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order;

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
			$payinfo = self::publicpay('WXPAY', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
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
			$payinfo = self::appletpay('WXPAY', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('UNIONPAY');
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

		require(PAY_ROOT."inc/DinpayClient.php");
		
		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->sm2DoVerifySign($_POST['data'], $_POST['sign']);

		if($verify_result) {//验证成功

			$data = json_decode($_POST['data'], true);
			if ($data['orderStatus'] == 'SUCCESS') {
				$out_trade_no = $data['orderNo'];
				$api_trade_no = $data['channelNumber'];
				$money = $data['payAmount'];
				$buyer = $data['subOpenId'];
				$bill_trade_no = $data['outTransactionOrderId'];
				if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
				if($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)){
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
		global $channel, $DB;
		if(empty($order))exit();

		require(PAY_ROOT."inc/DinpayClient.php");

		$params = [
			'interfaceName' => 'AppPayRefund',
			'payOrderNo' => $order['trade_no'],
			'refundOrderNo' => $order['refund_no'],
			'refundAmount' => $order['refundmoney'],
			'notifyUrl' => $conf['localurl'] . 'pay/refundnotify/' . $order['trade_no'] . '/',
		];
		if($order['profits'] > 0){
			$psorder = \lib\ProfitSharing\CommUtil::getOrder($order['trade_no']);
			if($psorder && $psorder['rdata']){
				$leftmoney = (float)$order['refundmoney'];
				$rules = [];
				$i = 1;
				foreach($psorder['rdata'] as $receiver){
					$money = $receiver['money'] > $leftmoney ? $leftmoney : $receiver['money'];
					$rules[] = [
						'merchantNo' => $receiver['account'],
						'refundAmount' => $money,
						'splitBillRequestNo' => $order['trade_no'].$i++,
					];
					$leftmoney -= $money;
					if($leftmoney <= 0) break;
				}
				$params['refundSplitRules'] = json_encode($rules);
			}
		}
		
		try{
			if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
			$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
			$result = $client->execute('/api/appPay/payRefund', $params);

			return ['code'=>0, 'trade_no'=>$result['refundChannelNumber'], 'refund_fee'=>$result['refundAmount']];

		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		require(PAY_ROOT."inc/DinpayClient.php");
		
		if(!empty($channel['appmchid']) && substr($channel['appmchid'], 0, 1)!='[') $channel['appid'] = $channel['appmchid'];
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->sm2DoVerifySign($_POST['data'], $_POST['sign']);

		if($verify_result) {//验证成功

			$data = json_decode($_POST['data'], true);
			if ($data['refundStatus'] == 'ALL_REFUND') {
				$out_trade_no = $data['orderNo'];
				$api_trade_no = $data['channelNumber'];
			}
			return ['type'=>'html','data'=>'sccuess'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//转账异步回调
	static public function transfernotify(){
		global $channel, $order, $DB;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT."inc/DinpayClient.php");
		
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->sm2DoVerifySign($arr['data'], $arr['sign']);

		if($verify_result) {//验证成功

			$data = json_decode($arr['data'], true);
			$orderid = $data['orderNo'];
			$trade_row = $DB->find('dinpay_trade', '*', ['orderid'=>$orderid]);
			if($trade_row){
				if($trade_row['status'] != $data['orderStatus']){
					$DB->update('dinpay_trade', ['status'=>$data['orderStatus'], 'reason'=>$data['statusIllustrate'], 'endtime'=>date('Y-m-d H:i:s')], ['id'=>$trade_row['id']]);
				}
			}
			return ['type'=>'html','data'=>'sccuess'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//投诉通知
	static public function complainnotify(){
		global $channel;

		$json = file_get_contents('php://input');
		$arr = json_decode($json, true);
		if(!$arr) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT."inc/DinpayClient.php");
		
		$client = new DinpayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->sm2DoVerifySign($arr['data'], $arr['sign']);

		if($verify_result) {//验证成功

			$data = json_decode($arr['data'], true);
			$thirdid = $data['complaintId'];
			if(substr($channel['appmchid'],0,1)=='['){
				$channel['appmchid'] = $data['merchantId'];
			}
			if($data['appPayType'] == 'ALIPAY'){
				$channel['type'] = 1;
			}else{
				$channel['type'] = 2;
			}
			$model = \lib\Complain\CommUtil::getModel($channel);
			$model->refreshNewInfo($thirdid);
			return ['type'=>'html','data'=>'sccuess'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
		}
	}
}