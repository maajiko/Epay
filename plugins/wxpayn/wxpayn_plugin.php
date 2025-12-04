<?php

class wxpayn_plugin
{
	static public $info = [
		'name'        => 'wxpayn', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '微信官方支付V3', //支付插件显示名称
		'author'      => '微信', //支付插件作者
		'link'        => 'https://pay.weixin.qq.com/', //支付插件作者链接
		'types'       => ['wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['wxpay'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '服务号/小程序/开放平台AppID',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户APIv3密钥',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户API证书序列号',
				'type' => 'input',
				'note' => '',
			],
			'publickeyid' => [
				'name' => '微信支付公钥ID',
				'type' => 'input',
				'note' => '平台证书模式需要留空',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => 'Native支付',
			'2' => 'JSAPI支付',
			'3' => 'H5支付',
			'5' => 'APP支付',
		],
		'note' => '<p>请将商户API私钥“apiclient_key.pem”、微信支付平台公钥“pub_key.pem”放到 /plugins/wxpayn/cert/ 文件夹内（或 /plugins/wxpayn/cert/商户号/ 文件夹内）。</p><p>上方AppID填写已认证的服务号/小程序/开放平台应用皆可，需要在微信支付后台关联对应的AppID账号才能使用。</p>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $submit2, $conf;

		$urlpre = '/';
        if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
        }
		
		if(checkwechat()){
			if(in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}elseif(in_array('1',$channel['apptype']) && $conf['wework_payopen'] == 1){
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}else{
				if(!$submit2){
					return ['type'=>'jump','url'=>'/pay/submit/'.TRADE_NO.'/'];
				}
				return ['type'=>'page','page'=>'wxopen'];
			}
		}elseif(checkmobile()){
			if(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('5',$channel['apptype']) && strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone OS')!==false){
				return ['type'=>'jump','url'=>$urlpre.'pay/apppay/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) && ($channel['appwxmp']>0 || $channel['appwxa']>0)){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}
		}else{
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		$urlpre = $siteurl;
		if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
		}

		if($method == 'applet' && in_array('2',$channel['apptype'])){
			return self::applet();
		}elseif($method=='app'){
			return self::apppay();
		}elseif($method=='jsapi'){
			return self::jspay();
		}elseif($method=='scan'){
			return ['type'=>'error','msg'=>'当前支付通道不支持付款码支付'];
		}
		elseif($mdevice=='wechat'){
			if(in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('2',$channel['apptype']) && $channel['appwxa']>0){
				return self::wap();
			}else{
				return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
			}
		}elseif($device=='mobile'){
			if(in_array('5',$channel['apptype']) && $mdevice == 'app'){
				return self::apppay();
			}elseif(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('5',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/submit/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) && ($channel['appwxmp']>0 || $channel['appwxa']>0)){
				return self::wap();
			}else{
				return self::qrcode();
			}
		}else{
			return self::qrcode();
		}
	}

	//扫码支付
	static public function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(in_array('1',$channel['apptype'])){

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->nativePay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys, $sub_orders);
				$result = $client->combineNativePay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}
			$code_url = $result['code_url'];
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}

		}elseif(in_array('2',$channel['apptype']) && $channel['appwxmp']>0){
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
		}elseif(in_array('2',$channel['apptype']) && $channel['appwxa']>0){
			$code_url = $siteurl.'pay/wap/'.TRADE_NO.'/';
		}elseif(in_array('3',$channel['apptype'])){
			$code_url = $siteurl.'pay/h5/'.TRADE_NO.'/';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
	}

	//JS支付
	static public function jspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $method;

		if(!empty($order['sub_openid'])){
			if(!empty($order['sub_appid'])){
				$channel['appid'] = $order['sub_appid'];
			}else{
				$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
				if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
				$channel['appid'] = $wxinfo['appid'];
			}
			$openid = $order['sub_openid'];
		}else{
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			$channel['appid'] = $wxinfo['appid'];

			//①、获取用户openid
			try{
				$openid = wechat_oauth($wxinfo);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
		}

		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		//②、统一下单
		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->jsapiPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys, $sub_orders);
				$result = $client->combineJsapiPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}
			$jsApiParameters = json_encode($result);
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
		if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$jsApiParameters];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//手机支付
	static public function wap(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		
		if($channel['appwxa']>0 && !isset($_GET['qrcode'])){
			try{
				$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
				if(!$wxinfo)return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//H5支付
	static public function h5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(checkwechat()){
			return ['type'=>'page','page'=>'wxopen'];
		}

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip,
				'h5_info' => [
					'type' => 'Wap',
					'app_name' => $conf['sitename'],
					'app_url' => $siteurl,
				],
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->h5Pay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys, $sub_orders);
				$result = $client->combineH5Pay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}
			$redirect_url=$siteurl.'pay/return/'.TRADE_NO.'/';
			$url=$result['h5_url'].'&redirect_url='.urlencode($redirect_url);
			return ['type'=>'jump','url'=>$url];
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
	}

	//小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		$channel['appid'] = $wxinfo['appid'];

		//①、获取用户openid
		try{
			$openid = wechat_applet_oauth($code, $wxinfo);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$jsApiParameters = $client->jsapiPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys, $sub_orders);
				$jsApiParameters = $client->combineJsapiPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}
			exit(json_encode(['code'=>0, 'data'=>$jsApiParameters]));
		} catch (Exception $e) {
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败！'.$e->getMessage()]));
		}
	}

	//APP支付
	static public function apppay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $method;

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->appPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys, $sub_orders);
				$result = $client->combineAppPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO, $sub_orders);
			}
			if($method == 'app'){
				return ['type'=>'app','data'=>json_encode($result)];
			}
			$params = [
				'nonceStr' => $result['noncestr'],
				'package' => $result['package'],
				'partnerId' => $result['partnerid'],
				'prepayId' => $result['prepayid'],
				'timeStamp' => $result['timestamp'],
				'sign' => $result['sign'],
			];
			$code_url = 'weixin://app/'.$result['appid'].'/pay/?'.http_build_query($params);
			return ['type'=>'qrcode','page'=>'wxpay_h5','url'=>$code_url];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
	}

	//小程序跳转支付
	static public function applet(){
		global $channel;
		try{
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo)return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			$path = wxminipay_jump_path(TRADE_NO);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'wxapp','data'=>['appId'=>$wxinfo['appid'], 'miniProgramId'=>'', 'path'=>$path]];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			$client->replyNotify(false, $e->getMessage());
			exit;
		}

		if(isset($data['combine_out_trade_no'])){ //合单支付
			if($data['combine_out_trade_no'] == TRADE_NO){
				$sub_orders = [];
				foreach($data['sub_orders'] as $detail){
					$sub_orders[] = ['sub_trade_no'=>$detail['out_trade_no'], 'api_trade_no'=>$detail['transaction_id'], 'money'=>round($detail['amount']['total_amount']/100,2)];
				}
				\lib\Payment::processSubOrders(TRADE_NO, $sub_orders);

				processNotify($order, $data['combine_out_trade_no'], $data['combine_payer_info']['openid']);
			}
		}else{
			if ($data['trade_state'] == 'SUCCESS') {
				if($data['out_trade_no'] == TRADE_NO){
					processNotify($order, $data['transaction_id'], $data['payer']['openid']);
				}
			}
		}
		$client->replyNotify(true);
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$param = [
			'transaction_id' => $order['api_trade_no'],
			'out_refund_no' => $order['refund_no'],
			'amount' => [
				'refund' => intval(round($order['refundmoney']*100)),
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			]
		];

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$result = $client->refund($param);
			$result = ['code'=>0, 'trade_no'=>$result['out_trade_no'], 'refund_fee'=>$result['amount']['refund']];
		} catch (Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//合单退款
	static public function refund_combine($order){
		global $channel;
		if(empty($order))exit();

		$sub_orders = \lib\Payment::getSubOrders($order['trade_no']);
		if(empty($sub_orders)) return ['code'=>-1, 'msg'=>'子订单数据不存在'];
	
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

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
			$param = [
				'transaction_id' => $sub_order['api_trade_no'],
				'out_refund_no' => $refund_no,
				'amount' => [
					'refund' => intval(round($money*100)),
					'total' => intval(round($sub_order['money']*100)),
					'currency' => 'CNY'
				]
			];
	
			try{
				$client->refund($param);
			} catch (Exception $e) {
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

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$client->closeOrder($order['trade_no']);
			$result = ['code'=>0];
		} catch (Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//合单支付关闭订单
	static public function close_combine($order){
		global $channel;
		if(empty($order))exit();

		$sub_orders = \lib\Payment::getSubOrders($order['trade_no']);
		if(empty($sub_orders)) return ['code'=>-1, 'msg'=>'子订单数据不存在'];
	
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		foreach($sub_orders as $sub_order){
			try{
				$client->closeOrder($sub_order['trade_no']);
			} catch (Exception $e) {
				return ['code'=>-1, 'msg'=>$e->getMessage()];
			}
		}

		return ['code'=>0];
	}

	//处理合单支付参数
	static private function combineOrderParams($param, $submoneys, &$sub_orders_data){
		global $channel, $order, $conf;
		$sub_orders = [];
		$sub_orders_data = [];
        $i = 1;
        foreach($submoneys as $money){
            $sub_order = [
                'attach' => 'combine',
                'amount' => [
                    'total_amount' => $money,
                    'currency' => $param['amount']['currency'],
                ],
                'out_trade_no' => $param['out_trade_no'].$i,
                'description' => $param['description'],
            ];
			if($order['profits']>0 || $conf['direct_settle_time'] == 1 && $channel['appswitch'] == 1){
				$sub_order['settle_info'] = ['profit_sharing' => true];
			}
            $sub_orders[] = $sub_order;
			$sub_orders_data[] = ['sub_trade_no'=>$sub_order['out_trade_no'], 'money'=>round($money/100,2)];
            $i++;
        }
		if(!empty($param['scene_info']['h5_info'])) $param['scene_info']['device_id'] = '10001';
        $newparam = [
            'combine_out_trade_no' => $param['out_trade_no'],
            'scene_info' => $param['scene_info'],
            'sub_orders' => $sub_orders,
            'notify_url' => $param['notify_url'],
        ];
		if(isset($param['payer'])){
			$newparam['combine_payer_info'] = $param['payer'];
		}
		return $newparam;
	}

	static private $fail_reason_desc = ['ACCOUNT_FROZEN'=>'该用户账户被冻结', 'REAL_NAME_CHECK_FAIL'=>'收款人未实名认证', 'NAME_NOT_CORRECT'=>'收款人姓名校验不通过', 'OPENID_INVALID'=>'Openid校验失败', 'TRANSFER_QUOTA_EXCEED'=>'超过用户单笔收款额度', 'DAY_RECEIVED_QUOTA_EXCEED'=>'超过用户单日收款额度', 'MONTH_RECEIVED_QUOTA_EXCEED'=>'超过用户单月收款额度', 'DAY_RECEIVED_COUNT_EXCEED'=>'超过用户单日收款次数', 'PRODUCT_AUTH_CHECK_FAIL'=>'未开通该权限或权限被冻结', 'OVERDUE_CLOSE'=>'超过系统重试期，系统自动关闭', 'ID_CARD_NOT_CORRECT'=>'收款人身份证校验不通过', 'ACCOUNT_NOT_EXIST'=>'该用户账户不存在', 'TRANSFER_RISK'=>'该笔转账可能存在风险，已被微信拦截', 'OTHER_FAIL_REASON_TYPE'=>'其它失败原因', 'REALNAME_ACCOUNT_RECEIVED_QUOTA_EXCEED'=>'用户账户收款受限，请引导用户在微信支付查看详情', 'RECEIVE_ACCOUNT_NOT_PERMMIT'=>'未配置该用户为转账收款人', 'PAYER_ACCOUNT_ABNORMAL'=>'商户账户付款受限，可前往商户平台获取解除功能限制指引', 'PAYEE_ACCOUNT_ABNORMAL'=>'用户账户收款异常，请引导用户完善身份信息', 'TRANSFER_REMARK_SET_FAIL'=>'转账备注设置失败，请调整后重新再试','TRANSFER_SCENE_UNAVAILABLE'=>'该转账场景暂不可用，请确认转账场景ID是否正确','TRANSFER_SCENE_INVALID'=>'你尚未获取该转账场景，请确认转账场景ID是否正确','RECEIVE_ACCOUNT_NOT_CONFIGURE'=>'请前往商户平台-商家转账到零钱-前往功能-转账场景中添加','BLOCK_B2C_USERLIMITAMOUNT_MONTH'=>'用户账户存在风险收款受限，本月不支持继续向该用户付款','MERCHANT_REJECT'=>'转账验密人已驳回转账','MERCHANT_NOT_CONFIRM'=>'转账验密人超时未验密'];

	//转账
	static public function transfer($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		if($conf['transfer_wxpay_type'] == 1){
			return self::transfer_n($channel, $bizParam);
		}

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		$out_batch_no = $bizParam['out_biz_no'];
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		$transfer_detail = [
			'out_detail_no' => $bizParam['out_biz_no'],
			'transfer_amount' => intval(round($bizParam['money']*100)),
			'transfer_remark' => $bizParam['transfer_desc'],
			'openid' => $bizParam['payee_account'],
		];
		if(!empty($bizParam['payee_real_name'])){
			$transfer_detail['user_name'] = $client->rsaEncrypt($bizParam['payee_real_name']);
		}
		$param = [
			'out_batch_no' => $out_batch_no,
			'batch_name' => '转账给'.$bizParam['payee_real_name'],
			'batch_remark' => date("YmdHis"),
			'total_amount' => intval(round($bizParam['money']*100)),
			'total_num' => 1,
			'transfer_detail_list' => [
				$transfer_detail
			],
		];

		try{
			$result = $client->transfer($param);
		} catch (Exception $e) {
			$errorMsg = $e->getMessage();
			if(!strpos($errorMsg, '对应的订单已经存在')){
				return ['code'=>-1, 'msg'=>$errorMsg];
			}
		}
		$batch_id = $result['batch_id'];

		return ['code'=>0, 'status'=>0, 'orderid'=>$bizParam['out_biz_no'], 'paydate'=>date('Y-m-d H:i:s')];

		usleep(500000);

		try{
			$result = $client->transferoutdetail($out_batch_no, $bizParam['out_biz_no']);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		if($result['detail_status'] == 'PROCESSING'){
			return ['code'=>0, 'status'=>0, 'orderid'=>$result['detail_id'], 'paydate'=>$result['update_time']];
		}elseif($result['detail_status'] == 'FAIL'){
			return ['code'=>-1, 'errcode'=>$result['fail_reason'], 'msg'=>'['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']]];
		}elseif($result['detail_status'] == 'SUCCESS'){
			return ['code'=>0, 'status'=>1, 'orderid'=>$result['detail_id'], 'paydate'=>$result['update_time']];
		}else{
			return ['code'=>-1, 'msg'=>'转账状态未知'];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		if($conf['transfer_wxpay_type'] == 1){
			return self::transfer_query_n($channel, $bizParam);
		}

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			$result = $client->transferoutdetail($bizParam['out_biz_no'], $bizParam['out_biz_no']);
			if($result['detail_status'] == 'SUCCESS'){
				$status = 1;
			}elseif($result['detail_status'] == 'FAIL'){
				$status = 2;
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>round($result['transfer_amount']/100, 2), 'paydate'=>$result['update_time'], 'errmsg'=>'['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']]];
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//电子回单
	static public function transfer_proof($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		if($conf['transfer_wxpay_type'] == 1){
			return self::transfer_proof_n($channel, $bizParam);
		}

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			if(!isset($_SESSION['ereceipt_'.$bizParam['out_biz_no']])){
				$result = $client->transferDetailReceiptApply($bizParam['out_biz_no'], $bizParam['out_biz_no']);
				$_SESSION['ereceipt_'.$bizParam['out_biz_no']] = $result['signature_no'];
			}
			if($result['signature_status'] == 'FINISHED'){
				return ['code'=>0, 'msg'=>'电子回单生成成功！', 'download_url'=>$result['download_url']];
			}

			usleep(300000);
			$result = $client->transferDetailReceiptQuery($bizParam['out_biz_no'], $bizParam['out_biz_no']);
			if($result['signature_status'] == 'FINISHED'){
				$file_content = $client->download($result['download_url']);
				$file_md5 = md5($file_content);
				file_put_contents(ROOT.'assets/uploads/'.$file_md5.'.pdf', $file_content);
				$download_url = $siteurl.'assets/uploads/'.$file_md5.'.pdf';
				return ['code'=>0, 'msg'=>'电子回单生成成功！', 'download_url'=>$download_url];
			}else{
				return ['code'=>0, 'msg'=>'电子回单正在生成中，请稍后再试！'];
			}
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//新商家转账
	static private function transfer_n($channel, $bizParam){
		global $conf;

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		if(empty($conf['transfer_wxpay_scene_id'])) return ['code'=>-1, 'msg'=>'未配置转账场景ID'];
		if(empty($conf['transfer_wxpay_info_type']) || empty($conf['transfer_wxpay_info_content'])) return ['code'=>-1, 'msg'=>'未配置转账报备信息'];
		$report_infos = [];
		$info_types = explode('|',$conf['transfer_wxpay_info_type']);
		$info_contents = explode('|',$conf['transfer_wxpay_info_content']);
		foreach($info_types as $i => $info_type){
			$report_infos[] = [
				'info_type' => $info_type,
				'info_content' => $info_contents[$i] ?? $info_contents[0],
			];
		}

		$param = [
			'out_bill_no' => $bizParam['out_biz_no'],
			'transfer_scene_id' => $conf['transfer_wxpay_scene_id'],
			'openid' => $bizParam['payee_account'],
			'transfer_amount' => intval(round($bizParam['money']*100)),
			'transfer_remark' => $bizParam['transfer_desc'],
			'notify_url' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'transfer_scene_report_infos' => $report_infos,
		];
		if($param['transfer_amount'] >= 30 && !empty($bizParam['payee_real_name'])){
			$param['user_name'] = $client->rsaEncrypt($bizParam['payee_real_name']);
		}

		try{
			$result = $client->mchTransfer($param);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		if($result['state'] == 'SUCCESS'){
			return ['code'=>0, 'status'=>1, 'orderid'=>$result['transfer_bill_no'], 'paydate'=>date('Y-m-d H:i:s'), 'wxpackage'=>$result['package_info']];
		}elseif($result['state'] == 'WAIT_USER_CONFIRM' || $result['state'] == 'TRANSFERING' || $result['state'] == 'ACCEPTED' || $result['state'] == 'PROCESSING'){
			return ['code'=>0, 'status'=>0, 'orderid'=>$result['transfer_bill_no'], 'paydate'=>date('Y-m-d H:i:s'), 'wxpackage'=>$result['package_info']];
		}elseif($result['state'] == 'FAIL'){
			return ['code'=>-1, 'errcode'=>$result['fail_reason'], 'msg'=>'['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']], 'wxpackage'=>$result['package_info']];
		}else{
			return ['code'=>-1, 'msg'=>'转账状态未知('.$result['state'].')'];
		}
	}

	//新转账查询
	static private function transfer_query_n($channel, $bizParam){
		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			$result = $client->queryTransferByOutNo($bizParam['out_biz_no']);
			$errmsg = null;
			if($result['state'] == 'SUCCESS'){
				$status = 1;
			}elseif($result['state'] == 'FAIL'){
				$status = 2;
				$errmsg = '['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']];
			}elseif($result['state'] == 'CANCELLED'){
				$status = 2;
				$errmsg = '转账已撤销';
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>round($result['transfer_amount']/100, 2), 'paydate'=>$result['update_time'], 'errmsg'=>$errmsg];
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//撤销转账
	static public function transfer_cancel($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			$result = $client->cancelTransfer($bizParam['out_biz_no']);
			return ['code'=>0];
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//新电子回单
	static private function transfer_proof_n($channel, $bizParam){
		global $conf, $siteurl;

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			if(!isset($_SESSION['ereceipt_'.$bizParam['out_biz_no']])){
				$result = $client->transferReceiptApply($bizParam['out_biz_no']);
				$_SESSION['ereceipt_'.$bizParam['out_biz_no']] = '1';
			}
			if($result['state'] == 'GENERATING'){
				usleep(300000);
			}
			$result = $client->transferReceiptQuery($bizParam['out_biz_no']);
			if($result['state'] == 'FINISHED'){
				$file_content = $client->download($result['download_url']);
				$file_md5 = md5($file_content);
				file_put_contents(ROOT.'assets/uploads/'.$file_md5.'.pdf', $file_content);
				$download_url = $siteurl.'assets/uploads/'.$file_md5.'.pdf';
				return ['code'=>0, 'msg'=>'电子回单生成成功！', 'download_url'=>$download_url];
			}elseif($result['state'] == 'FAILED'){
				return ['code'=>0, 'msg'=>'电子回单生成失败：'.$result['fail_reason']];
			}else{
				return ['code'=>0, 'msg'=>'电子回单正在生成中，请稍后再试！'];
			}
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//新转账回调
	static public function transfernotify(){
		global $channel;

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\BaseService($wechatpay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			$client->replyNotify(false, $e->getMessage());
			exit;
		}

		$errmsg = null;
		if($data['state'] == 'SUCCESS'){
			$status = 1;
		}elseif($data['state'] == 'FAIL'){
			$status = 2;
			$errmsg = '['.$data['fail_reason'].']'.self::$fail_reason_desc[$data['fail_reason']];
		}elseif($data['state'] == 'CANCELLED'){
			$status = 2;
			$errmsg = '转账已撤销';
		}
		if(isset($status)){
			processTransfer($data['out_bill_no'], $status, $errmsg);
		}
		
		$client->replyNotify(true);
	}

	//投诉通知回调
	static public function complainnotify(){
		global $channel;

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\BaseService($wechatpay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			$client->replyNotify(false, $e->getMessage());
			exit;
		}

		$model = \lib\Complain\CommUtil::getModel($channel);
		$model->refreshNewInfo($data['complaint_id'], $data['action_type']);

		$client->replyNotify(true);
	}
}