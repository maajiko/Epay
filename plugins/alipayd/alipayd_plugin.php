<?php

class alipayd_plugin
{
	static public $info = [
		'name'        => 'alipayd', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '支付宝官方支付直付通版', //支付插件显示名称
		'author'      => '支付宝', //支付插件作者
		'link'        => 'https://b.alipay.com/signing/productSetV2.htm', //支付插件作者链接
		'types'       => ['alipay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用APPID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '支付宝公钥',
				'type' => 'textarea',
				'note' => '填错也可以支付成功但会无法回调，如果用公钥证书模式此处留空',
			],
			'appsecret' => [
				'name' => '应用私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '子商户SMID',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => '电脑网站支付',
			'2' => '手机网站支付',
			'3' => '当面付扫码',
			'4' => '当面付JS',
			'5' => '预授权支付',
			'6' => 'APP支付',
			'7' => 'JSAPI支付',
			'8' => '订单码支付',
		],
		'note' => '<p>需要先申请互联网平台直付通才能使用！</p><p>如果使用公钥证书模式，需将<font color="red">应用公钥证书、支付宝公钥证书、支付宝根证书</font>3个crt文件放置于<font color="red">/plugins/alipayd/cert/</font>文件夹（或<font color="red">/plugins/alipayd/cert/应用APPID/</font>文件夹）</p>', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $submit2, $conf, $clientip;

		$isMobile = checkmobile();
		$isAlipay = checkalipay();
		if($isAlipay && in_array('4',$channel['apptype']) && !in_array('2',$channel['apptype'])){
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				return ['type'=>'jump','url'=>$conf['localurl_alipay'].'pay/jspay/'.TRADE_NO.'/?d=1'];
			}
			return ['type'=>'jump','url'=>'/pay/jspay/'.TRADE_NO.'/?d=1'];
		}
		elseif($isMobile && (in_array('3',$channel['apptype'])||in_array('4',$channel['apptype'])||in_array('8',$channel['apptype'])) && !in_array('2',$channel['apptype']) || !$isMobile && !in_array('1',$channel['apptype'])){
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
		}
		else{
		
		if(checkwechat()){
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/?wap=1'];
		}
		
		if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
			return ['type'=>'jump','url'=>$conf['localurl_alipay'].'pay/submit/'.TRADE_NO.'/'];
		}
		
		if($isMobile && in_array('2',$channel['apptype'])){
			if($conf['alipay_wappaylogin']==1){
				if($isAlipay){
					return ['type'=>'jump','url'=>'/pay/submitwap/'.TRADE_NO.'/?d=1'];
				}else{
					return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
				}
			}
			$_GET['d']='1';
			return self::submitwap();
		}elseif(in_array('1',$channel['apptype'])){
			if($conf['alipay_paymode'] == 1 || $isMobile){
				return ['type'=>'jump','url'=>'/pay/qrcodepc/'.TRADE_NO.'/'];
			}
			$alipay_config = require(PAY_ROOT.'inc/config.php');
			$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
			$alipay_config['return_url'] = $siteurl.'pay/return/'.TRADE_NO.'/';
			$bizContent = [
				'out_trade_no' => TRADE_NO,
				'total_amount' => $order['realmoney'],
				'subject' => $ordername,
			];
			$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
			self::handleExtUser($bizContent);
			try{
				$aop = new \Alipay\AlipayTradeService($alipay_config);
				$aop->directPayParams($bizContent);
				$html = $aop->pagePay($bizContent);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
			}

			return ['type'=>'html','data'=>$html];
		}elseif(in_array('6',$channel['apptype'])){
			if($conf['alipay_wappaylogin']==1 && !$isAlipay || $isMobile && !$isAlipay){
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}
			return ['type'=>'jump','url'=>'/pay/apppay/'.TRADE_NO.'/?d=1'];
		}elseif(in_array('7',$channel['apptype'])){
			return ['type'=>'jump','url'=>'/pay/minipay/'.TRADE_NO.'/?d=1'];
		}elseif(in_array('5',$channel['apptype'])){
			if($conf['alipay_wappaylogin']==1 && !$isAlipay){
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}
			return ['type'=>'jump','url'=>'/pay/preauth/'.TRADE_NO.'/?d=1'];
		}
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		if($method=='app'){
			return self::apppay();
		}
		elseif($method=='jsapi'){
			if(in_array('7',$channel['apptype'])){
				return self::jsapipay();
			}else{
				return self::jspay();
			}
		}
		elseif($mdevice=='alipay' && in_array('4',$channel['apptype']) && !in_array('2',$channel['apptype'])){
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				return ['type'=>'jump','url'=>$conf['localurl_alipay'].'pay/jspay/'.TRADE_NO.'/?d=1'];
			}
			return ['type'=>'jump','url'=>$siteurl.'pay/jspay/'.TRADE_NO.'/?d=1'];
		}
		elseif($device=='mobile' && (in_array('3',$channel['apptype'])||in_array('4',$channel['apptype'])||in_array('8',$channel['apptype'])) && !in_array('2',$channel['apptype']) || $device=='pc' && !in_array('1',$channel['apptype'])){
			return self::qrcode();
		}else{
		
		if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
			return ['type'=>'jump','url'=>$conf['localurl_alipay'].'pay/submit/'.TRADE_NO.'/'];
		}else{
			return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
		}
		}
	}

	static private function handleExtUser(&$bizContent){
		global $order;
		if(!empty($order['cert_no']) || !empty($order['cert_name']) || !empty($order['min_age'])){
			$ext_user_info = ['need_check_info'=>'T'];
			if(!empty($order['cert_no'])){
				$ext_user_info['cert_type'] = 'IDENTITY_CARD';
				$ext_user_info['cert_no'] = $order['cert_no'];
			}
			if(!empty($order['cert_name'])){
				$ext_user_info['name'] = $order['cert_name'];
			}
			if(!empty($order['min_age'])){
				$ext_user_info['min_age'] = $order['min_age'];
			}
			$bizContent['ext_user_info'] = $ext_user_info;
		}
	}

	//电脑网站支付扫码
	static public function qrcodepc(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(checkmobile()){
			$alipay_config = require(PAY_ROOT.'inc/config.php');
			$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
			$alipay_config['return_url'] = $siteurl.'pay/return/'.TRADE_NO.'/';
			$alipay_config['pageMethod'] = '2';
			$bizContent = [
				'out_trade_no' => TRADE_NO,
				'total_amount' => $order['realmoney'],
				'subject' => $ordername,
				'qr_pay_mode' => '4'
			];
			$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
			self::handleExtUser($bizContent);
			try{
				$aop = new \Alipay\AlipayTradeService($alipay_config);
				$aop->directPayParams($bizContent);
				$url = $aop->pagePay($bizContent);
				$html = get_curl($url, 0, 0, 0, 0, 0, 0, 0, 1);
				$html = mb_convert_encoding($html, 'utf-8', 'gbk');
			}catch(Exception $e){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
			}

			if(preg_match('!<input name="qrCode" type="hidden" value="(.*?)"!i', $html, $match)){
				$code_url = $match[1];
				return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
			}else{
				return ['type'=>'error','msg'=>'支付宝下单失败！获取二维码失败'];
			}
	
		}else{
			$code_url = '/pay/submitpc/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'alipay_qrcodepc','url'=>$code_url];
		}
	}

	//电脑网站支付扫码跳转
	static public function submitpc(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$alipay_config['return_url'] = $siteurl.'pay/return/'.TRADE_NO.'/';
		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername,
			'qr_pay_mode' => '4',
			'qrcode_width' => '230'
		];
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		self::handleExtUser($bizContent);
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			$aop->directPayParams($bizContent);
			$html = $aop->pagePay($bizContent);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}

		$html = '<!DOCTYPE html><html><body><style>body{margin:0;padding:0}.waiting{position:absolute;width:100%;height:100%;background:#fff url(/assets/img/load.gif) no-repeat fixed center/80px;}</style><div class="waiting"></div>'.$html.'</body></html>';
		return ['type'=>'html','data'=>$html];
	}

	//手机网站支付扫码跳转
	static public function submitwap(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$alipay_config = require(PAY_ROOT.'inc/config.php');

		if($conf['alipay_wappaylogin']==1 && checkalipay()){
			[$user_type, $user_id] = alipay_oauth($alipay_config);
			$blocks = checkBlockUser($user_id, TRADE_NO);
			if($blocks) return $blocks;
		}

		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		if($_GET['d']=='1'){
			$alipay_config['return_url'] = $siteurl.'pay/return/'.TRADE_NO.'/';
		}else{
			$alipay_config['return_url'] = $siteurl.'pay/ok/'.TRADE_NO.'/';
		}
		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername,
		];
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		self::handleExtUser($bizContent);
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			if(self::isCombinePay($order['realmoney'])){
				$bizContent['product_code'] = 'QUICK_WAP_WAY';
				$bizContent = self::combineOrderParams($bizContent, $sub_orders);
				$result = $aop->mergePrecreatePay($bizContent);
				$bizContent = [
					'pre_order_no' => $result['pre_order_no']
				];
				$html = $aop->wapMergePay($bizContent);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}else{
				$aop->directPayParams($bizContent);
				$html = $aop->wapPay($bizContent);
			}
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}

		return ['type'=>'html','data'=>$html];
	}

	//扫码支付
	static public function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $mdevice;
		if(!in_array('3',$channel['apptype']) && in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/submitwap/'.TRADE_NO.'/';
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				$code_url = $conf['localurl_alipay'].'pay/submitwap/'.TRADE_NO.'/';
			}
			if(isset($_GET['wap']) && !$conf['alipay_wappaylogin'] && checkmobile() && !checkwechat()){
				return self::submitwap();
			}
		}elseif(!in_array('3',$channel['apptype']) && in_array('4',$channel['apptype'])){
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				$code_url = $conf['localurl_alipay'].'pay/jspay/'.TRADE_NO.'/';
			}
		}elseif(!in_array('3',$channel['apptype']) && in_array('6',$channel['apptype'])){
			$code_url = $siteurl.'pay/apppay/'.TRADE_NO.'/';
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				$code_url = $conf['localurl_alipay'].'pay/apppay/'.TRADE_NO.'/';
			}
		}elseif(!in_array('3',$channel['apptype']) && in_array('7',$channel['apptype'])){
			$code_url = $siteurl.'pay/minipay/'.TRADE_NO.'/';
		}elseif(!in_array('3',$channel['apptype']) && in_array('5',$channel['apptype'])){
			$code_url = $siteurl.'pay/preauth/'.TRADE_NO.'/';
			if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
				$code_url = $conf['localurl_alipay'].'pay/preauth/'.TRADE_NO.'/';
			}
		}else{

			if($conf['alipay_qrpaylogin'] == 1){
				if(checkalipay() || $mdevice=='alipay'){
					[$user_type, $user_id] = alipay_oauth(require(PAY_ROOT.'inc/config.php'));
					$blocks = checkBlockUser($user_id, TRADE_NO);
					if($blocks) return $blocks;
				}else{
					$code_url = $siteurl.'pay/qrcode/'.TRADE_NO.'/';
					return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
				}
			}

			$alipay_config = require(PAY_ROOT.'inc/config.php');
			$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
			$bizContent = [
				'out_trade_no' => TRADE_NO,
				'total_amount' => $order['realmoney'],
				'subject' => $ordername
			];
			if(!in_array('3',$channel['apptype']) && in_array('8',$channel['apptype'])){
				$bizContent['product_code'] = 'QR_CODE_OFFLINE';
			}
			$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
			try{
				$aop = new \Alipay\AlipayTradeService($alipay_config);
				$result = $aop->qrPay($bizContent);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
			}
			$code_url = $result['qr_code'];

		}
		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	//APP支付
	static public function apppay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $method;

		$alipay_config = require(PAY_ROOT.'inc/config.php');

		if($conf['alipay_wappaylogin']==1 && checkalipay()){
			[$user_type, $user_id] = alipay_oauth($alipay_config);
			$blocks = checkBlockUser($user_id, TRADE_NO);
			if($blocks) return $blocks;
		}

		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername
		];
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		self::handleExtUser($bizContent);
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			if(self::isCombinePay($order['realmoney'])){
				$bizContent['product_code'] = 'QUICK_MSECURITY_PAY';
				$bizContent = self::combineOrderParams($bizContent, $sub_orders);
				$result = $aop->mergePrecreatePay($bizContent);
				$bizContent = [
					'pre_order_no' => $result['pre_order_no']
				];
				$result = $aop->appMergePay($bizContent);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}else{
				$aop->directPayParams($bizContent);
				$result = $aop->appPay($bizContent);
			}
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}
		if($method == 'app'){
			return ['type'=>'app','data'=>$result];
		}
		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		$code_url = 'alipays://platformapi/startApp?appId=20000125&orderSuffix='.urlencode($result).'#Intent;scheme=alipays;package=com.eg.android.AlipayGphone;end';
		return ['type'=>'page','page'=>'alipay_h5','data'=>['code_url'=>$code_url, 'redirect_url'=>$redirect_url]];
	}

	//预授权支付
	static public function preauth(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$alipay_config = require(PAY_ROOT.'inc/config.php');

		if($conf['alipay_wappaylogin']==1 && checkalipay()){
			[$user_type, $user_id] = alipay_oauth($alipay_config);
			$blocks = checkBlockUser($user_id, TRADE_NO);
			if($blocks) return $blocks;
		}

		$alipay_config['notify_url'] = $conf['localurl'].'pay/preauthnotify/'.TRADE_NO.'/';
		$bizContent = [
			'out_order_no' => TRADE_NO,
			'out_request_no' => TRADE_NO,
			'order_title' => $ordername,
			'amount' => $order['realmoney'],
			'product_code' => 'PREAUTH_PAY'
		];
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			$aop->directPayParams($bizContent);
			$result = $aop->preAuthFreeze($bizContent);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}
		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		$code_url = 'alipays://platformapi/startApp?appId=20000125&orderSuffix='.urlencode($result).'#Intent;scheme=alipays;package=com.eg.android.AlipayGphone;end';
		return ['type'=>'page','page'=>'alipay_h5','data'=>['code_url'=>$code_url, 'redirect_url'=>$redirect_url]];
	}

	//当面付JS支付
	static public function jspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $method;
		
		$alipay_config = require(PAY_ROOT.'inc/config.php');

		if(!empty($order['sub_openid'])){
			$user_id = $order['sub_openid'];
			$user_type = is_numeric($user_id) && substr($user_id, 0, 4) == '2088' ? 'userid' : 'openid';
		}else{
			[$user_type, $user_id] = alipay_oauth($alipay_config);
		}
		
		$blocks = checkBlockUser($user_id, TRADE_NO);
		if($blocks) return $blocks;

		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername
		];
		if($user_type == 'userid'){
			$bizContent['buyer_id'] = $user_id;
		}else{
			$bizContent['buyer_open_id'] = $user_id;
		}
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			$aop->directPayParams($bizContent);
			$result = $aop->jsPay($bizContent);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}
		$alipay_trade_no = $result['trade_no'];
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

	//JSAPI支付
	static public function jsapipay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $method;

		$user_id = $order['sub_openid'];
		$user_type = is_numeric($user_id) && substr($user_id, 0, 4) == '2088' ? 'userid' : 'openid';
		
		$blocks = checkBlockUser($user_id, TRADE_NO);
		if($blocks) return $blocks;

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername,
			'product_code' => 'JSAPI_PAY',
			'op_app_id' => $order['sub_appid'] ? $order['sub_appid'] : $alipay_config['app_id']
		];
		if($user_type == 'openid'){
			$bizContent['buyer_open_id'] = $user_id;
		}else{
			$bizContent['buyer_id'] = $user_id;
		}
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			if(self::isCombinePay($order['realmoney'])){
				$bizContent['product_code'] = 'QUICK_MSECURITY_PAY';
				$bizContent = self::combineOrderParams($bizContent, $sub_orders);
				$result = $aop->mergeCreate($bizContent);
				$alipay_trade_no = $result['merge_no'];
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}else{
				$aop->directPayParams($bizContent);
				$result = $aop->jsPay($bizContent);
				$alipay_trade_no = $result['trade_no'];
			}
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
		}
		$alipay_trade_no = $result['trade_no'];
		return ['type'=>'jsapi','data'=>$alipay_trade_no];
	}

	//支付宝小程序支付
	static public function alipaymini(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		
		$auth_code = isset($_GET['auth_code'])?trim($_GET['auth_code']):exit('{"code":-1,"msg":"auth_code不能为空"}');

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';

		try{
			[$app_id, $user_type, $user_id] = alipay_mini_oauth($auth_code, $alipay_config);
		}catch(Exception $e){
			exit(json_encode(['code'=>-1, 'msg'=>$e->getMessage()]));
		}
	
		$blocks = checkBlockUser($user_id, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		$bizContent = [
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername,
			'product_code' => 'JSAPI_PAY',
			'op_app_id' => $app_id
		];
		if($user_type == 'openid'){
			$bizContent['buyer_open_id'] = $user_id;
		}else{
			$bizContent['buyer_id'] = $user_id;
		}
		$bizContent['business_params'] = ['mc_create_trade_ip' => $clientip];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			if(self::isCombinePay($order['realmoney'])){
				$bizContent['product_code'] = 'QUICK_MSECURITY_PAY';
				$bizContent = self::combineOrderParams($bizContent, $sub_orders);
				$result = $aop->mergeCreate($bizContent);
				$alipay_trade_no = $result['merge_no'];
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}else{
				$aop->directPayParams($bizContent);
				$result = $aop->jsPay($bizContent);
				$alipay_trade_no = $result['trade_no'];
			}
		}catch(Exception $e){
			exit(json_encode(['code'=>-1, 'msg'=>'支付宝下单失败！'.$e->getMessage()]));
		}
		exit(json_encode(['code'=>0, 'data'=>$alipay_trade_no]));
	}

	//H5跳转小程序支付
	static public function minipay(){
		global $siteurl, $channel, $conf;

		$code_url = alipaymini_jump_scheme(TRADE_NO, $channel['appid']);

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_h5','data'=>['code_url'=>$code_url, 'redirect_url'=>$redirect_url]];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order, $conf;

		if($order['combine']){
			return self::combine_notify();
		}

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayTradeService($alipay_config);

		$verify_result = $aop->check($_POST);

		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = $_POST['out_trade_no'];

			//支付宝交易号
			$trade_no = $_POST['trade_no'];

			//买家支付宝
			$buyer_id = $_POST['buyer_id'];
			if(empty($buyer_id))$buyer_id = $_POST['buyer_open_id'];

			//交易金额
			$total_amount = $_POST['total_amount'];

			if($_POST['trade_status'] == 'TRADE_FINISHED') {
				//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
			}
			else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
					if($conf['direct_settle_time'] > 0 && $order['profits'] == 0){
						$order['settle'] = 1;
					}else{
						usleep(300000);
						try{
							$freeze = $conf['direct_settle_time'] > 0 && $order['profits'] > 0;
							$aop->settle_confirm($trade_no, $order['realmoney'], $freeze);
							$order['settle'] = 2;
						}catch(Exception $e){
							$errmsg = $e->getMessage();
							if(strpos($errmsg, 'ALREADY_CONFIRM_SETTLE')){
								$order['settle'] = 2;
							}else{
								$aop->writeLog('settle_confirm:'.$errmsg);
								$order['settle'] = 3;
								\lib\Payment::alipayd_settle_fail($channel, $order, $errmsg);
							}
						}
					}
					processNotify($order, $trade_no, $buyer_id);
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

		if($order['combine']){
			return self::combine_return();
		}

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayTradeService($alipay_config);

		$verify_result = $aop->check($_GET);

		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = $_GET['out_trade_no'];

			//支付宝交易号
			$trade_no = $_GET['trade_no'];

			//交易金额
			$total_amount = $_GET['total_amount'];

			if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
				processReturn($order, $trade_no);
			}else{
				return ['type'=>'error','msg'=>'订单信息校验失败'];
			}
		}
		else {
			//验证失败
			return ['type'=>'error','msg'=>'支付宝返回验证失败'];
		}
	}

	//预授权支付回调
	static public function preauthnotify(){
		global $channel, $order, $conf, $ordername, $clientip;

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$alipay_config['notify_url'] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$aop = new \Alipay\AlipayService($alipay_config);

		$verify_result = $aop->check($_POST);

		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = $_POST['out_order_no'];

			//资金授权订单号
			$auth_no = $_POST['auth_no'];

			$buyer_id = $result['payer_user_id'];
			
			if($out_trade_no == TRADE_NO){
				$bizContent = [
					'out_trade_no' => TRADE_NO,
					'total_amount' => $order['realmoney'],
					'subject' => $ordername,
					'product_code' => 'PREAUTH_PAY',
					'auth_no' => $auth_no,
					'auth_confirm_mode' => 'COMPLETE'
				];
				try{
					$aop = new \Alipay\AlipayTradeService($alipay_config);
					$result = $aop->scanPay($bizContent);
				}catch(Exception $e){
					\lib\Payment::updateOrder(TRADE_NO, $auth_no, $buyer_id, 4);
					return ['type'=>'html','data'=>'success'];
					//return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
				}
				$trade_no = $result['trade_no'];
				$buyer_id = $result['buyer_user_id'];
				$total_amount = $result['total_amount'];

				processNotify($order, $trade_no, $buyer_id);
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$bizContent = [
			'trade_no' => $order['api_trade_no'],
			'refund_amount' => $order['refundmoney'],
			'out_request_no' => $order['refund_no']
		];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			$result = $aop->refund($bizContent);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return  ['code'=>0, 'trade_no'=>$result['trade_no'], 'refund_fee'=>$result['refund_fee'], 'refund_time'=>$result['gmt_refund_pay'], 'buyer'=>$result['buyer_user_id']];
	}

	//合单支付退款
	static public function refund_combine($order){
		global $channel;
		if(empty($order))exit();

		$sub_orders = \lib\Payment::getSubOrders($order['trade_no']);
		if(empty($sub_orders)) return ['code'=>-1, 'msg'=>'子订单数据不存在'];
	
		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayTradeService($alipay_config);

		//循环退款
		$refundmoney = $order['refundmoney'];
		foreach($sub_orders as $sub_order){
			if($sub_order['status']==2 && (empty($sub_order['refundmoney']) || $sub_order['refundmoney']>=$sub_order['money'])) continue;
			$money = $sub_order['money'];
			if($sub_order['status']==2) $money = round($sub_order['money'] - $sub_order['refundmoney'], 2);
			if($money > $refundmoney){
				$money = $refundmoney;
			}
			$refund_no = date("YmdHis").rand(11111,99999);
			$bizContent = [
				'trade_no' => $sub_order['api_trade_no'],
				'refund_amount' => $money,
				'out_request_no' => $refund_no
			];
			try{
				$aop->refund($bizContent);
			}catch(Exception $e){
				return ['code'=>-1, 'msg'=>$e->getMessage()];
			}
			$sub_refundmoney = $sub_order['refundmoney'] ? round($sub_order['refundmoney'] + $money, 2) : $money;
			\lib\Payment::refundSubOrder($sub_order['sub_trade_no'], $sub_refundmoney);
			$refundmoney = round($refundmoney - $money, 2);
			if($refundmoney<=0)break;
		}
		return ['code'=>0];
	}

	//关闭订单
	static public function close($order){
		global $channel;
		if(empty($order))exit();

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$bizContent = [
			'out_trade_no' => $order['trade_no'],
		];
		try{
			$aop = new \Alipay\AlipayTradeService($alipay_config);
			$aop->close($bizContent);
			return  ['code'=>0];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//合单支付关闭订单
	static public function close_combine($order){
		global $channel;
		if(empty($order))exit();

		$sub_orders = \lib\Payment::getSubOrders($order['trade_no']);
		if(empty($sub_orders)) return ['code'=>-1, 'msg'=>'子订单数据不存在'];

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayTradeService($alipay_config);
		foreach($sub_orders as $sub_order){
			$bizContent = [
				'out_trade_no' => $sub_order['trade_no'],
			];
			try{
				$aop->close($bizContent);
			}catch(Exception $e){
				return ['code'=>-1, 'msg'=>$e->getMessage()];
			}
		}
		return  ['code'=>0];
	}

	//支付宝应用网关
	static public function appgw(){
		global $channel,$DB;
		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayService($alipay_config);
		$verify_result = $aop->check($_POST);
		if($verify_result){
			if($_POST['msg_method'] == 'alipay.merchant.tradecomplain.changed'){
				$bizContent = json_decode($_POST['biz_content'], true);
				if($bizContent && isset($bizContent['complain_event_id'])){
					$model = \lib\Complain\CommUtil::getModel($channel);
					$model->refreshNewInfo($bizContent['complain_event_id']);
				}
			}elseif($_POST['msg_method'] == 'ant.merchant.expand.indirect.zft.passed'){
				$bizContent = json_decode($_POST['biz_content'], true);
				if($bizContent && isset($bizContent['order_id'])){
					$bizContent['type'] = 'passed';
					$model = \lib\Applyments\CommUtil::getModel2($channel);
					$model->callback($bizContent);
				}
			}elseif($_POST['msg_method'] == 'ant.merchant.expand.indirect.zft.rejected'){
				$bizContent = json_decode($_POST['biz_content'], true);
				if($bizContent && isset($bizContent['order_id'])){
					$bizContent['type'] = 'rejected';
					$model = \lib\Applyments\CommUtil::getModel2($channel);
					$model->callback($bizContent);
				}
			}
			/*if($_POST['service']=='alipay.adatabus.risk.end.push' || $_POST['service']=='alipay.riskgo.risk.push'){
				if($_POST['charset'] == 'GBK'){
					$_POST['risktype'] = mb_convert_encoding($_POST['risktype'], "UTF-8", "GBK");
					$_POST['risklevel'] = mb_convert_encoding($_POST['risklevel'], "UTF-8", "GBK");
					$_POST['riskDesc'] = mb_convert_encoding($_POST['riskDesc'], "UTF-8", "GBK");
					$_POST['complainText'] = mb_convert_encoding($_POST['complainText'], "UTF-8", "GBK");
				}
				$DB->exec("INSERT INTO `pre_alipayrisk` (`channel`,`pid`,`smid`,`tradeNos`,`risktype`,`risklevel`,`riskDesc`,`complainTime`,`complainText`,`date`,`status`) VALUES (:channel, :pid, :smid, :tradeNos, :risktype, :risklevel, :riskDesc, :complainTime, :complainText, NOW(), 0)", [':channel'=>$channelid, ':pid'=>$_POST['pid'], ':smid'=>$_POST['smid']?$_POST['smid']:$_POST['merchantId'], ':tradeNos'=>$_POST['tradeNos'], ':risktype'=>$_POST['risktype'], ':risklevel'=>$_POST['risklevel'], ':riskDesc'=>$_POST['riskDesc'], ':complainTime'=>$_POST['complainTime'], ':complainText'=>$_POST['complainText']]);
			}*/
			return ['type'=>'html','data'=>'success'];
		}else{
			return ['type'=>'html','data'=>'check sign fail'];
		}
	}

	//判断是否支持合单支付
	static private function isCombinePay($money){
		global $conf;
		if(!$conf['alicombine_open'] || !$conf['wxcombine_minmoney']) return false;
		if($money < $conf['wxcombine_minmoney']) return false;
		return true;
	}

	//合单支付参数
	static private function combineOrderParams($bizContent, &$sub_orders){
		global $channel, $conf;
		
		$sub_mchid = $channel['appmchid'];
		if(strpos($channel['appmchid'], ',')){
			$sub_mchids = explode(',', $channel['appmchid']);
			shuffle($sub_mchids);
		}

		$money = intval(round($bizContent['total_amount']*100));
		$subnum = isset($sub_mchids)?count($sub_mchids):2;
		if($subnum > 6) $subnum = 6;
		$submoney = intval($money/$subnum);
		if($subnum < 6 && $conf['wxcombine_submoney']){
			while($submoney > intval($conf['wxcombine_submoney']*100)){
				$subnum++;
				$submoney = intval($money/$subnum);
				if($subnum==6)break;
			}
		}
		$submoneys = [];
		for($i=0;$i<$subnum;$i++){
			$submoneys[] = $submoney;
		}
		$mod = $money%$subnum;
		if($mod > 0){
			for($i=0;$i<$mod;$i++){
				$submoneys[$i] += 1;
			}
		}

		$order_details = [];
		$sub_orders = [];
		$i = 1;
        foreach($submoneys as $money){
			$order_detail = [
				'app_id' => $channel['appid'],
				'out_trade_no' => $bizContent['out_trade_no'].$i,
				'product_code' => $bizContent['product_code'],
				'total_amount' => strval(round($money/100, 2)),
				'subject' => $bizContent['subject'],
				'business_params' => $bizContent['business_params'],
				'sub_merchant' => ['merchant_id' => isset($sub_mchids)?$sub_mchids[($i-1)%count($sub_mchids)]:$sub_mchid],
				'settle_info' => [
					'settle_period_time' => '1d',
					'settle_detail_infos' => [
						[
							'trans_in_type' => 'defaultSettle',
							'amount' => strval(round($money/100, 2))
						]
					]
				]
			];
			if(isset($bizContent['op_app_id'])) $order_detail['op_app_id'] = $bizContent['op_app_id'];
			if(isset($bizContent['buyer_id'])) $order_detail['buyer_id'] = $bizContent['buyer_id'];
			if(isset($bizContent['buyer_open_id'])) $order_detail['buyer_open_id'] = $bizContent['buyer_open_id'];
            $order_details[] = $order_detail;
			$sub_orders[] = ['sub_trade_no'=>$order_detail['out_trade_no'], 'money'=>$order_detail['total_amount']];
            $i++;
        }
		$bizContent = [
			'out_merge_no' => $bizContent['out_trade_no'],
			'order_details' => $order_details
		];
		return $bizContent;
	}

	static private function combine_notify(){
		global $channel, $order, $conf;

		$alipay_config = require(PAY_ROOT.'inc/config.php');
		$aop = new \Alipay\AlipayService($alipay_config);

		$verify_result = $aop->check($_POST);

		if($verify_result) {
			$out_trade_no = $_POST['out_merge_no'];
			$buyer_id = $_POST['buyer_id'];
			$order_detail_results = json_decode($_POST['order_detail_results'], true);

			if($_POST['merge_pay_status'] == 'FINISHED' && $out_trade_no == TRADE_NO){

				$sub_orders = [];
				foreach($order_detail_results as $detail){
					$sub_orders[] = ['sub_trade_no'=>$detail['out_trade_no'], 'api_trade_no'=>$detail['trade_no'], 'money'=>$detail['total_amount']];
				}
				\lib\Payment::processSubOrders(TRADE_NO, $sub_orders);

				if($conf['direct_settle_time'] > 0 && $order['profits'] == 0){
					$order['settle'] = 1;
				}else{
					usleep(300000);
					$freeze = $conf['direct_settle_time'] > 0 && $order['profits'] > 0;
					$failnum = 0;
					$aop = new \Alipay\AlipayTradeService($alipay_config);
					foreach($order_detail_results as $detail){
						$settle = 0;
						try{
							$aop->settle_confirm($detail['trade_no'], $detail['total_amount'], $freeze);
							$settle = 1;
						}catch(Exception $e){
							$errmsg = $e->getMessage();
							if(strpos($errmsg, 'ALREADY_CONFIRM_SETTLE')!==false){
								$settle = 1;
							}else{
								$failnum++;
								$aop->writeLog('settle_confirm:'.$errmsg);
							}
						}
						if($settle == 1) \lib\Payment::updateSubOrderSettle($detail['out_trade_no'], 1);
					}
					$order['settle'] = $failnum > 0 ? 3 : 2;
					if($failnum > 0){
						\lib\Payment::alipayd_settle_fail($channel, $order, $errmsg);
					}
				}
				
				processNotify($order, $out_trade_no, $buyer_id);
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}
	
	static private function combine_return(){
		return ['type'=>'page','page'=>'return'];
	}
}