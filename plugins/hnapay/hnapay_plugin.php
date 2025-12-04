<?php
/***
 * https://www.yuque.com/chenyanfei-sjuaz/uhng8q
 */

class hnapay_plugin
{
	static public $info = [
		'name'        => 'hnapay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '新生支付', //支付插件显示名称
		'author'      => '新生支付', //支付插件作者
		'link'        => 'https://www.hnapay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户ID',
				'type' => 'input',
				'note' => '新生用户ID',
			],
			'appkey' => [
				'name' => '新生公钥(新收款密钥)',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥(新收款密钥)',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '报备编号',
				'type' => 'input',
				'note' => '仅支付宝&微信需要填写',
			],
			'appswitch' => [
                'name' => '接口类型',
                'type' => 'select',
                'options' => [0 => '公众号/生活号支付', 1 => '支付宝H5', 2 => '扫码支付'],
            ],
		],
		'select' => null,
		'note' => '需要使用RSA密钥！<br/>如使用扫码支付，需将<b>收款密钥</b>中的<b>商户私钥</b>上传到/plugins/hnapay/cert/mch.key（或cert/商户ID/mch.key）<br/>如使用付款功能，需将<b>付款密钥</b>中的<b>商户私钥</b>上传到/plugins/hnapay/cert/pay.key（或cert/商户ID/pay.key）', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
            if($channel['appswitch']==0 && checkalipay()){
                return ['type' => 'jump', 'url' => '/pay/alipayjs/' . TRADE_NO . '/?d=1'];
            }elseif($channel['appswitch']==1 && checkalipay()){
                return ['type' => 'jump', 'url' => '/pay/alipayh5/' . TRADE_NO . '/'];
            }else{
                return ['type' => 'jump', 'url' => '/pay/alipay/' . TRADE_NO . '/'];
            }
		}elseif($order['typename']=='wxpay'){
			if($channel['appswitch']==0 && checkwechat()){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($channel['appswitch']==0 && checkmobile()){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
            }else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			if($channel['appswitch']==1){
				return ['type'=>'jump','url'=>'/pay/quickpay/'.TRADE_NO.'/'];
			}
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		if($channel['appswitch']==0 && $method=='jsapi'){
			if($order['typename']=='alipay'){
				return self::alipayjs();
			}elseif($order['typename']=='wxpay'){
				return self::wxjspay();
			}
		}elseif($order['typename']=='alipay'){
			if($channel['appswitch']==0 && $mdevice=='alipay'){
				return ['type' => 'jump', 'url' => '/pay/alipayjs/' . TRADE_NO . '/?d=1'];
			}elseif($channel['appswitch']==1 && $mdevice=='alipay'){
				return ['type' => 'jump', 'url' => '/pay/alipayh5/' . TRADE_NO . '/'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($channel['appswitch']==0 && $mdevice=='wechat'){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($channel['appswitch']==0 && $device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			if($channel['appswitch']==1){
				return self::quickpay();
			}
			return self::bank();
		}
	}

	//二维码下单通用
	static private function qrcode($orgCode){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'merOrderNum' => TRADE_NO,
			'tranAmt' => strval($order['realmoney']*100),
			'submitTime' => substr(TRADE_NO, 0, 14),
			'orgCode' => $orgCode,
			'goodsName' => $ordername,
			'tranIP' => $clientip,
			'notifyUrl' => $conf['localurl'].'pay/notifys/'.TRADE_NO.'/',
			'weChatMchId' => $channel['appmchid'],
		];
		
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 1);
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($pay, $param) {
			$result = $pay->scanPay($param);
			return $result['qrCodeUrl'];
		});
	}

	//JSAPI下单通用
	static private function jsapi($orgCode, $appId, $openId){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'tranAmt' => $order['realmoney'],
			'orgCode' => $orgCode,
			'notifyServerUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'merUserIp' => $clientip,
			'goodsInfo' => $ordername,
			'orderSubject' => $ordername,
			'merchantId' => $channel['appmchid'],
		];
		if($orgCode == 'WECHATPAY'){
			$param += [
				'appId' => $appId,
				'openId' => $openId,
			];
		}elseif($orgCode == 'ALIPAY'){
			$param += [
				'aliAppId' => $appId,
				'buyerId' => $openId,
			];
		}

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($pay, $param) {
			$result = $pay->jsapiPay($param, TRADE_NO);
			return $result['payInfo'];
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $siteurl;
		if($channel['appswitch']==2){
			try{
				$code_url = self::qrcode('ALIPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}elseif($channel['appswitch']==1){
			if(checkmobile()){
				return self::alipayh5();
			}else{
				$code_url = $siteurl.'pay/alipayh5/'.TRADE_NO.'/?d=1';
			}
		}else{
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}
		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
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

		$achannel = \lib\Channel::get($conf['alipay_web_login']);

		try{
			$retData = self::jsapi('ALIPAY', $achannel['appid'], $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$retData['tradeNO']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$retData['tradeNO'], 'redirect_url'=>$redirect_url]];
	}

	//支付宝H5支付
	static public function alipayh5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if($_GET['d']=='1'){
			$front_url=$siteurl.'pay/ok/'.TRADE_NO.'/';
		}else{
			$front_url=$siteurl.'pay/return/'.TRADE_NO.'/';
		}
		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$param = [
			'tranAmt' => $order['realmoney'],
			'payType' => 'HnaZFB',
			'exPayMode'=>'',
			'cardNo'=>'',
			'holderName'=>'',
			'identityCode'=>'',
			'merUserId'=>'',
			'orderExpireTime'=>'10',
			'frontUrl' => $front_url,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'riskExpand'=>'',
			'goodsInfo'=>'',
			'orderSubject' => $ordername,
			'orderDesc'=>'',
			'merchantId' => json_encode(['02'=>$channel['appmchid']]),
			'bizProtocolNo'=>'',
			'payProtocolNo'=>'',
			'merUserIp' => $clientip,
			'payLimit'=>'',
		];
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$html = $pay->h5Pay($param, TRADE_NO);
		return ['type'=>'html','data'=>$html];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;
		if($channel['appswitch']==2){
			try {
				$code_url = self::qrcode('WECHATPAY');
			} catch (Exception $ex) {
				return ['type' => 'error', 'msg' => '微信支付下单失败！' . $ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
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
			$pay_info = self::jsapi('WECHATPAY', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>json_encode($pay_info)];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($pay_info), 'redirect_url'=>$redirect_url]];
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
			$pay_info = self::jsapi('WECHATPAY', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>$pay_info]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}


	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'银联云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//快捷支付
	static public function quickpay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $cdnpublic, $DB;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if(isset($_POST['action'])){
			switch($_POST['action']){
				case 'query_card':
					$cardno = trim($_POST['cardno']);
					if(empty($cardno)) exit(json_encode(['code'=>-1, 'msg'=>'银行卡号不能为空']));
					try{
						$result = getBankCardInfo($cardno);
						exit(json_encode(['code'=>0, 'data'=>$result]));
					}catch(Exception $ex){
						exit(json_encode(['code'=>-1, 'msg'=>$ex->getMessage()]));
					}
					break;
				case 'request':
					$phone = trim($_POST['phone']);
					$cardno = trim($_POST['cardno']);
					$cardtype = trim($_POST['cardtype']);
					$name = trim($_POST['name']);
					$idcard = trim($_POST['idcard']);
					$expiry = str_replace('/', '', trim($_POST['expiry']));
					$cvv = trim($_POST['cvv']);
					if(empty($phone) || empty($cardno) || empty($name) || empty($idcard)){
						exit(json_encode(['code'=>-1, 'msg'=>'参数不能为空']));
					}
					if(!is_idcard($idcard)) exit(json_encode(['code'=>-1, 'msg'=>'身份证号码不正确']));

					$DB->update('order', ['mobile'=>$phone, 'buyer'=>$cardno], ['trade_no'=>TRADE_NO]);
					$black = $DB->find('blacklist', '*', ['type'=>0, 'content'=>$phone], null, 1);
					if($black) exit(json_encode(['code'=>-1, 'msg'=>'系统异常无法完成付款']));
					$black = $DB->find('blacklist', '*', ['type'=>0, 'content'=>$cardno], null, 1);
					if($black) exit(json_encode(['code'=>-1, 'msg'=>'系统异常无法完成付款']));
					
					$param = [
						'tranAmt' => $order['realmoney'],
						'payType' => '2',
						'cardNo' => $cardno,
						'holderName' => $name,
						'cardAvailableDate' => $expiry,
						'cvv2' => $cvv,
						'mobileNo' => $phone,
						'identityType' => '01',
						'identityCode' => $idcard,
						'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
						'merUserId' => $phone,
						'merUserIp' => $clientip,
						'goodsInfo' => $ordername,
					];
					try{
						$result = $pay->quickPayRequest($param, TRADE_NO);
						exit(json_encode(['code'=>0, 'token'=>$result['hnapayOrderId']]));
					}catch(Exception $ex){
						exit(json_encode(['code'=>-1, 'msg'=>'快捷支付下单失败！'.$ex->getMessage()]));
					}
					break;
				case 'confirm':
					$token = trim($_POST['token']);
					$smscode = trim($_POST['smscode']);
					if(empty($token) || empty($smscode)) exit(json_encode(['code'=>-1, 'msg'=>'参数不能为空']));
					$paymentTerminalInfo = (checkmobile() ? '02|' : '01|') . substr(md5($_POST['phone']), 0, 10);
					$param = [
						'hnapayOrderId' => $token,
						'smsCode' => $smscode,
						'merUserIp' => $clientip,
						'paymentTerminalInfo' => $paymentTerminalInfo,
						'receiverTerminalInfo' => '01|00001|CN|110000',
						'deviceInfo' => $clientip.'||||||',
					];
					try{
						$result = $pay->quickPayConfirm($param, TRADE_NO);
						$DB->update('order', ['ext'=>['bizProtocolNo'=>$result['bizProtocolNo'], 'payProtocolNo'=>$result['payProtocolNo']]], ['trade_no'=>TRADE_NO]);
						exit(json_encode(['code'=>0, 'backurl'=>'/pay/return/'.TRADE_NO.'/']));
					}catch(Exception $ex){
						exit(json_encode(['code'=>-1, 'msg'=>'快捷支付下单失败！'.$ex->getMessage()]));
					}
					break;
			}
		}

		include PAY_ROOT.'inc/pay.page.php';
	    exit;
	}

	static public function agreement(){
		global $siteurl, $cdnpublic;
		include PAY_ROOT.'inc/agreement.page.php';
	    exit;
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(!isset($_POST)) return ['type'=>'html','data'=>'no_data'];
		//file_put_contents('logs.txt' , http_build_query($_POST));

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if($_POST['tranCode'] == 'MUP11'){
			$verify_result = $pay->alipayh5Verify($_POST);
		}elseif($_POST['tranCode'] == 'EXP13'){
			$verify_result = $pay->quickpayVerify($_POST);
		}else{
			$verify_result = $pay->jsapiVerify($_POST);
		}
		if($verify_result){
			if($_POST['resultCode'] == '0000'){
				$out_trade_no = $_POST['merOrderId'];
				$trade_no = $_POST['hnapayOrderId'];
				$bill_mch_trade_no = $_POST['bankOrderId'];
				$bill_trade_no = $_POST['realBankOrderId'];
				if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
				$money = $_POST['tranAmt'];
				$buyer = $_POST['userId'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'200'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

	//异步回调(扫码支付)
	static public function notifys(){
		global $channel, $order;

		//file_put_contents('logs.txt' , http_build_query($_POST));

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 1);

		if($pay->scanVerify($_POST)){
			if($_POST['respCode'] == '0000'){
				$out_trade_no = $_POST['merOrderNum'];
				$trade_no = $_POST['hnapayOrderId'];
				$bill_mch_trade_no = $_POST['bankOrderId'];
				$bill_trade_no = $_POST['realBankOrderId'];
				if($order['type'] == 1 && substr($bill_trade_no, 0, 4) != date('Y') && substr($bill_trade_no, 2, 4) == date('Y')) $bill_trade_no = substr($bill_trade_no, 2);
				$money = $_POST['tranAmt'];
				$buyer = $_POST['userId'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
				}
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'200'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
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
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'orgMerOrderId' => $order['trade_no'],
			'orgSubmitTime' => substr($order['trade_no'], 0, 14),
			'orderAmt' => $order['realmoney'],
			'refundOrderAmt' => $order['refundmoney'],
			'notifyUrl' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		];

		try{
			$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
			$result = $pay->refund($param, $order['refund_no']);
			return ['code'=>0, 'trade_no'=>$result['orgMerOrderId'], 'refund_fee'=>$result['refundAmt']];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		if($pay->jsapiVerify($_POST)){
			if($_POST['resultCode'] == '0000'){
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'status_error'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf, $clientip;
		if(empty($channel) || empty($bizParam))exit();

		require(PLUGIN_ROOT.'hnapay/inc/HnaPayApi.class.php');

		$param = [
			'tranAmt' => $bizParam['money'],
			'payType' => '1',
			'auditFlag' => '0',
			'payeeName' => $bizParam['payee_real_name'],
			'payeeAccount' => $bizParam['payee_account'],
			'note' => '',
			'remark' => $bizParam['transfer_desc'],
			'bankCode' => '',
			'payeeType' => '1',
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'paymentTerminalInfo' => '01|A10001',
			'deviceInfo' => $clientip,
		];

		try{
			$client = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
			$result = $client->transfer($param, $bizParam['out_biz_no']);
			return ['code'=>0, 'status'=>0, 'orderid'=>$result['hnapayOrderId'], 'paydate'=>date('Y-m-d H:i:s')];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require(PLUGIN_ROOT.'hnapay/inc/HnaPayApi.class.php');

		try{
			$client = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
			$result = $client->transferQuery($bizParam['out_biz_no']);
			if($result['orderStatus'] == '1'){
				$status = 1;
			}elseif($result['orderStatus'] == '0' || $result['orderStatus'] == '3'){
				$status = 0;
			}else{
				$status = 2;
			}
			if($result['orderFailedCode']){
				$errmsg = '['.$result['orderFailedCode'].']'.$result['orderFailedMsg'];
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>$result['tranAmt'], 'paydate'=>date('Y-m-d H:i:s', strtotime($result['successTime'])), 'errmsg'=>$errmsg];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//电子回单
	static public function transfer_proof($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require(PLUGIN_ROOT.'hnapay/inc/HnaPayApi.class.php');

		try{
			$client = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
			$result = $client->transferProof($bizParam['orderid']);
			file_put_contents(ROOT.'assets/uploads/'.$bizParam['orderid'].'.png', base64_decode($result['payCertificate']));
			$image = '/assets/uploads/'.$bizParam['orderid'].'.png';
			return ['code'=>0, 'msg'=>'电子回单生成成功！', 'download_url'=>$image];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//余额查询
	static public function balance_query($channel, $bizParam){
		if(empty($channel))exit();

		require(PLUGIN_ROOT.'hnapay/inc/HnaPayApi.class.php');

		try{
			$client = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
			$result = $client->queryBalance();
			return ['code'=>0, 'amount'=>$result['avaBalance'], 'msg'=>'当前账户可用余额：'.$result['avaBalance'].' 元，待结转金额：'.$result['pendAmt']];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//付款异步回调
	static public function transfernotify(){
		global $channel;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
		if($pay->transferVerify($_POST)){
			if($_POST['resultCode'] == '0000'){
				$status = 1;
			}else{
				$status = 2;
			}
			if($_POST['errorMsg']){
				$errmsg = '['.$_POST['errorCode'].']'.$_POST['errorMsg'];
			}
			processTransfer($_POST['merOrderId'], $status, $errmsg);
			return ['type'=>'html','data'=>'200'];
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

}