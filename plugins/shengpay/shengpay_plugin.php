<?php
class shengpay_plugin
{
	static public $info = [
		'name'        => 'shengpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '盛付通', //支付插件显示名称
		'author'      => '盛付通', //支付插件作者
		'link'        => 'https://www.shengpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '盛付通公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appswitch' => [
				'name' => '收单接口类型',
				'type' => 'select',
				'options' => [0=>'线上',1=>'线下'],
			],
			'appmchid' => [
				'name' => '子商户号',
				'type' => 'input',
				'note' => '非代理商户可留空',
			],
			'aeskey' => [
				'name' => 'AES加密密钥',
				'type' => 'input',
				'note' => '用于投诉事件回调解密',
			],
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => '电脑网站支付',
			'3' => '手机网站支付',
			'4' => '服务窗支付',
		],
		'select_wxpay' => [
			'1' => 'JSAPI支付',
			'2' => 'Native支付',
			'3' => 'H5支付',
			'4' => '小程序收银台',
			'5' => '盛付通聚合码',
		],
		'select' => null,
		'note' => '如果是微信支付，需要<a href="./plugin_page.php?channel=[channel]&func=wxconfig" target="_blank">配置绑定AppId和支付目录</a>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			if(checkalipay() && in_array('4',$channel['apptype']) && !in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
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
			if($mdevice=='alipay' && in_array('4',$channel['apptype']) && !in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一下单
	static private function addOrder($tradeType, $extra=null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$path = $channel['appswitch'] == 1 ? '/pay/unifiedorderOffline' : '/pay/unifiedorder';

		$param = [
			'outTradeNo' => TRADE_NO,
			'totalFee' => intval(round($order['realmoney']*100)),
			'currency' => 'CNY',
			'tradeType' => $tradeType,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'extra' => $extra,
			'body'  => $ordername,
			'clientIp'  => $clientip,
		];
		if(!empty($channel['appmchid'])) $param['subMchId'] = $channel['appmchid'];
		if($order['profits'] > 0) $param['isNeedShare'] = 'TRUE';
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $path, $param) {
			$result = $client->execute($path, $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['transactionId']);
			return $result['payInfo'];
		});
	}

	//微信小程序收银台
	static private function wxlite(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$param = [
			'outTradeNo' => TRADE_NO,
			'totalFee' => intval(round($order['realmoney']*100)),
			'currency' => 'CNY',
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'nonceStr' => random(32),
			'body'  => $ordername,
			'clientIp'  => $clientip,
		];
		if(!empty($channel['appmchid'])) $param['subMchId'] = $channel['appmchid'];
		if($order['profits'] > 0) $param['isNeedShare'] = 'TRUE';
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->execute('/pay/preUnifieAppletdorder', $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['transactionId']);
			return $result['payInfo'];
		});
	}

	//支付宝支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('3',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$tradeType = 'alipay_wap';
		}elseif(in_array('2',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$tradeType = 'alipay_pc';
		}elseif(in_array('4',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
			$tradeType = 'alipay_jsapi';
		}else{
			$tradeType = 'alipay_qr';
		}
		if(!isset($code_url)){
			try{
				$code_url = self::addOrder($tradeType);
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
			}
		}

		if($tradeType == 'alipay_qr' || $tradeType == 'alipay_jsapi'){
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}else{
			return ['type'=>'jump','url'=>$code_url];
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
			$pay_info = self::addOrder('alipay_jsapi', json_encode(['openId'=>$user_id]));
			$result = json_decode($pay_info, true);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$result['tradeNo']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['tradeNo'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl;
		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::addOrder('wx_native');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}elseif(in_array('4',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}elseif(in_array('5',$channel['apptype'])){
			try{
				$code_url = self::addOrder('shengpay_aggre');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		if (checkmobile()) {
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
			$pay_info = self::addOrder('wx_jsapi', json_encode(['openId'=>$openid, 'appId'=>$wxinfo['appid']]));
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$pay_info];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$pay_info, 'redirect_url'=>$redirect_url]];
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
			$pay_info = self::addOrder('wx_lite', json_encode(['openId'=>$openid, 'appId'=>$wxinfo['appid']]));
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($pay_info, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('3',$channel['apptype'])){
			try{
				$code_url = self::addOrder('wx_wap');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$code_url];
		}elseif(in_array('4',$channel['apptype'])){
			try{
				$code_url = self::wxlite();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}


	//云闪付扫码支付
	static public function bank(){
		global $channel;
		try{
			$code_url = self::addOrder('upacp_qr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if ($data['status'] == 'PAY_SUCCESS') {
				$out_trade_no = $data['outTradeNo'];
				$trade_no = $data['transactionId'];
				$payerInfo = json_decode($data['payerInfo'], true);
				$buyer = $payerInfo['openid'];
				$bill_trade_no = $payerInfo['officOrderNum'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, $buyer, $bill_trade_no);
				}
				return ['type'=>'html','data'=>'SUCCESS'];
			}
			return ['type'=>'html','data'=>'FAIL'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
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
		global $channel, $conf;
		if(empty($order))exit();

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		$param = [
			'outTradeNo' => $order['trade_no'],
			'outRefundNo' => $order['refund_no'] ? $order['refund_no'] : 'R'.$order['trade_no'],
			'refundFee' => intval(round($order['refundmoney']*100)),
			'notifyUrl' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		];

		try{
			$result = $client->execute('/refund/orderRefund', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		return ['code'=>0, 'trade_no'=>$result['refundId'], 'refund_fee'=>$result['refundFee']/100];
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if ($data['refundStatus'] == 'REFUND_SUCCESS') {
				$out_trade_no = $data['refundOrderNo'];
				$trade_no = $data['refundId'];

				return ['type'=>'html','data'=>'SUCCESS'];
			}
			return ['type'=>'html','data'=>'FAIL'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf, $clientip;
		if(empty($channel) || empty($bizParam))exit();

		require_once(PLUGIN_ROOT.'shengpay/inc/ShengPayClient.php');
		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'mchOrderNo' => $bizParam['out_biz_no'],
			'transAmount' => intval(round($bizParam['money']*100)),
			'payeeAccount' => $bizParam['payee_account'],
			'accountType' => 'C',
			'payeeName' => $bizParam['payee_real_name'],
			'remark' => $bizParam['transfer_desc'],
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
		];

		try{
			$result = $client->execute('/merchant/fund/transfer', $params);
			return ['code'=>0, 'status'=>0, 'orderid'=>$result['transNo'], 'paydate'=>date('Y-m-d H:i:s')];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require_once(PLUGIN_ROOT.'shengpay/inc/ShengPayClient.php');
		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'transNo' => $bizParam['orderid'],
		];
		try{
			$result = $client->execute('/merchant/fund/query', $params);
			if($result['transStatus'] == 'SUCCESS'){
				$status = 1;
			}elseif($result['transStatus'] == 'FAIL'){
				$status = 2;
				$errmsg = $result['transStatusDes'];
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'errmsg'=>$errmsg];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//付款异步回调
	static public function transfernotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if($data['transStatus'] == 'SUCCESS'){
				$status = 1;
			}elseif($data['transStatus'] == 'FAIL'){
				$status = 2;
				$errmsg = $data['transStatusDes'];
			}else{
				$status = 0;
			}
			processTransfer($data['mchOrderNo'], $status, $errmsg);
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//微信参数配置
	static public function wxconfig(){
		global $siteurl,$channel,$islogin;
		if(!$islogin) exit('Access Denied');

		require_once PAY_ROOT."inc/ShengPayClient.php";
		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if(isset($_POST['conf_type']) && isset($_POST['conf_value'])){
			$conf_value = trim($_POST['conf_value']);
			$params = [
				'subMchId' => !empty($channel['appmchid'])?$channel['appmchid']:$channel['appid'],
				'configType' => $_POST['conf_type'],
				'appId' => trim($_POST['appid']),
			];
			if($_POST['conf_type'] == '1') $params['payUrl'] = $conf_value;
            elseif($_POST['conf_type'] == '2') $params['appId'] = $conf_value;
			try{
				$client->execute('/report/appidBind', $params);
				showmsg('微信参数配置成功！',1);
			}catch(Exception $e){
				showmsg('微信参数配置失败！'.$e->getMessage(),4);
			}
		}

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);

		$param = [
			'subMchId' => !empty($channel['appmchid'])?$channel['appmchid']:$channel['appid'],
			'configType' => '5',
		];
		try{
			$result = $client->execute('/report/appidBind', $param);
			$appid_list = json_decode($result['appidConfigArray'], true);
			$payurl_list = json_decode($result['payUrlArray'], true);
			$data = ['appid_list' => $appid_list, 'payurl_list' => $payurl_list, 'appid'=>$wxinfo ? $wxinfo['appid'] : ''];
			include PAY_ROOT.'wxconf.page.php';
		}catch(Exception $e){
			showmsg('微信参数配置查询失败！'.$e->getMessage(),4);
		}
	}

	//进件异步回调
	static public function applynotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($data);
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//提现异步回调
	static public function settlenotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->settlenotify($data);
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//分账异步回调
	static public function sharingnotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if($data['status'] == 'FINISHED'){
				$receivers = json_decode($data['receivers'], true);
				$info = $receivers[0];
				if($info['sharingStatus'] == 'SUCCESS'){
					processProfitSharing($data['mchSharingNo'], 2);
                } elseif($info['sharingStatus'] == 'FAIL') {
					processProfitSharing($data['mchSharingNo'], 3, $info['failReason']);
                }
			}
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//投诉回调
	static public function complainnotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data || !isset($data['resource'])) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$res = $data['resource'];
		$data = $client->decrpytEvent($res['ciphertext'], $res['nonce'], $res['associated_data'], $channel['aeskey']);

		if($data){
			$data = json_decode($data, true);
			$channel['type'] = $data['tradeType'] == '支付宝' ? 1 : 2;
			$model = \lib\Complain\CommUtil::getModel($channel);
			$model->refreshNewInfo($data['complaintId'], $data);
			return ['type'=>'html','data'=>'SUCCESS'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}
}