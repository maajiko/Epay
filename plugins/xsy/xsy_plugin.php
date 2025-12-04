<?php

class xsy_plugin
{
    static $info = [
        'name' => 'xsy',       // 插件标识
        'showname' => '新生易',          // 插件显示名称
        'author' => '新生易',                 // 作者信息
        'link' => 'https://www.hnapay.com/',                 // 支付官网
        'types' => ['wxpay', 'alipay', 'bank'], // 可支持的支付类型
        'inputs' => [ // 配置信息表单
            'appid' => [
                'name' => '机构代码',
                'type' => 'input',
            ],
            'appkey' => [
                'name' => '平台公钥',
                'type' => 'textarea',
            ],
            'appsecret' => [
                'name' => '商户私钥',
                'type' => 'textarea',
            ],
            'appmchid' => [
                'name' => '商户编号',
                'type' => 'input',
            ],
            'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
        ],
        'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
        'select_bank' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
        'bindwxmp' => true, // 绑定微信公众号
        'bindwxa' => true, // 绑定微信小程序
        'note' => '',
    ];

    public static function submit()
    {
        global $siteurl, $channel, $order;

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

    public static function mapi()
    {
        global $siteurl, $channel, $order, $conf, $device, $mdevice, $method;

		if($method=='jsapi'){
            if($order['typename']=='alipay'){
                return self::alipayjs();
            }elseif($order['typename']=='wxpay'){
                return self::wxjspay();
            }elseif($order['typename']=='bank'){
                return self::bankjs();
            }
		}elseif($method=='scan'){
			return self::scanpay();
		}
		elseif($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
    }

    //扫码支付
    private static function qrcode($pay_type)
    {
        global $siteurl, $channel, $order, $ordername, $conf, $clientip;
        require_once(PAY_ROOT . 'lib/PayClient.php');

        $param = [
            'merchantNo' => $channel['appmchid'],
            'orderNo' => TRADE_NO,
            'amt' => intval(round($order['realmoney']*100)),
            'payType' => $pay_type,
            'subject' => $order['name'],
            'trmIp' => $clientip,
            'customerIp' => $clientip,
            'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/'
        ];

        $client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);
        return \lib\Payment::lockPayData(TRADE_NO, function () use ($client, $param) {
            $result = $client->request('/trade/activeScan', $param);
            if(strpos($result['payUrl'], 'qrContent=')){
                $result['payUrl'] = getSubstr($result['payUrl'], 'qrContent=', '&sign=');
            }
            return $result['payUrl'];
        });
    }

    //公众号小程序支付
    private static function jsapi($pay_type, $pay_way, $userid, $appid = null)
    {
        global $siteurl, $channel, $order, $ordername, $conf, $clientip;
        require_once(PAY_ROOT . 'lib/PayClient.php');

        $param = [
            'merchantNo' => $channel['appmchid'],
            'orderNo' => TRADE_NO,
            'amt' => intval(round($order['realmoney']*100)),
            'payType' => $pay_type,
            'payWay' => $pay_way,
            'subAppId' => $appid,
            'userId' => $userid,
            'subject' => $order['name'],
            'trmIp' => $clientip,
            'customerIp' => $clientip,
            'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/'
        ];

        $client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);
        return \lib\Payment::lockPayData(TRADE_NO, function () use ($client, $param) {
            $result = $client->request('/trade/jsapiScan', $param);
            return $result;
        });
    }

    //支付宝扫码支付
    public static function alipay()
    {
        global $channel, $siteurl, $mdevice;
        if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
            $code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
        }else{
            try {
                $code_url = self::qrcode('ALIPAY');
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>'支付宝下单失败！'.$e->getMessage()];
            }
        }
        if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
    }

    //支付宝生活号支付
    public static function alipayjs()
    {
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
			$retData = self::jsapi('ALIPAY', '02', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
        if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>$retData['source']];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$retData['source'], 'redirect_url'=>$redirect_url]];
    }

    //微信扫码支付
    public static function wxpay()
    {
        global $siteurl, $device, $channel;
        if($channel['appwxa']>0 && $channel['appwxmp']==0){
            $code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
        }else{
            $code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
        }
        if (checkmobile() || $device=='mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
    }

    //微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $method, $conf, $clientip;

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
			$result = self::jsapi('WECHAT', '02', $openid, $wxinfo['appid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

        $payinfo = ['appId'=>$result['payAppId'], 'timeStamp'=>$result['payTimeStamp'], 'nonceStr'=>$result['paynonceStr'], 'package'=>$result['payPackage'], 'signType'=>$result['paySignType'], 'paySign'=>$result['paySign']];

        if($method == 'jsapi'){
			return ['type'=>'jsapi','data'=>json_encode($payinfo)];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($payinfo), 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

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
			$result = self::jsapi('WECHAT', '03', $openid, $wxinfo['appid']);
		}catch(Exception $e){
            exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}

        $payinfo = ['appId'=>$result['payAppId'], 'timeStamp'=>$result['payTimeStamp'], 'nonceStr'=>$result['paynonceStr'], 'package'=>$result['payPackage'], 'signType'=>$result['paySignType'], 'paySign'=>$result['paySign']];

		exit(json_encode(['code'=>0, 'data'=>$payinfo]));
	}

	//微信手机支付
	static public function wxwappay(){
        global $channel;
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
    public static function bank()
    {
        try {
            $url = self::qrcode('UNIONPAY');
        } catch (Exception $e) {
            return ['type'=>'error','msg'=>'云闪付下单失败！'.$e->getMessage()];
        }
        return ['type' => 'qrcode', 'page' => 'bank_qrcode', 'url' => $url];
    }

    //云闪付JS支付
    public static function bankjs()
    {
        global $method, $order;
        try {
            $result = self::jsapi('UNIONPAY', '02', $order['sub_openid']);
            $url = $result['redirectUrl'];
        } catch (Exception $e) {
            return ['type'=>'error','msg'=>'云闪付下单失败！'.$e->getMessage()];
        }
        return ['type'=>'jump','url'=>$url];
    }

    static public function get_unionpay_userid($channel, $userAuthCode){
		require_once(PLUGIN_ROOT . 'xsy/lib/PayClient.php');

		$params = [
			'merchantNo' => $channel['appmchid'],
			'userAuthCode' => $userAuthCode,
			'appIdentifier' => get_unionpay_ua(),
		];

        $client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);
		try{
			$result = $client->request('/trade/getUnionInfo', $params);
			return ['code'=>0,'data'=>$result['userId']];
		}catch(Exception $e){
			return ['code'=>-1,'msg'=>$e->getMessage()];
		}
	}

    //被扫支付
	static public function scanpay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

        if($order['typename']=='alipay'){
            $pay_type = 'ALIPAY';
        }elseif($order['typename']=='wxpay'){
            $pay_type = 'WECHAT';
        }elseif($order['typename']=='bank'){
            $pay_type = 'UNIONPAY';
        }

		require_once(PAY_ROOT . 'lib/PayClient.php');

		$params = [
            'merchantNo' => $channel['appmchid'],
            'orderNo' => TRADE_NO,
            'authCode' => $order['auth_code'],
            'amt' => intval(round($order['realmoney']*100)),
            'payType' => $pay_type,
            'subject' => $order['name'],
            'trmIp' => $clientip,
            'notifyUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];

		$client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);

		try{
			$result = $client->request('/trade/reverseScan', $params);
			if($client->res_code == '0000'){
				processNotify($order, $result['outOrderNo'], $result['buyerId'], $result['transactionId']);
				return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['orderNo'], 'api_trade_no'=>$result['outOrderNo'], 'buyer'=>$result['buyerId'], 'money'=>$order['realmoney']]];
			}else{
				$retry = 0;
				$success = false;
				while($retry < 6){
					sleep(3);
					try{
						$result = self::orderQuery($client, TRADE_NO);
					}catch(Exception $e){
						return ['type'=>'error','msg'=>'订单查询失败:'.$e->getMessage()];
					}
					if($result['tranSts'] == 'SUCCESS'){
						$success = true;
						break;
					}elseif($result['tranSts'] != 'NEEDPAY' && $result['tranSts'] != 'PAYING'){
						return ['type'=>'error','msg'=>'订单超时或用户取消支付'];
					}
					$retry++;
				}
				if($success){
					processNotify($order, $result['outOrderNo'], $result['buyerId'], $result['transactionId']);
					return ['type'=>'scan','data'=>['type'=>$order['typename'], 'trade_no'=>$result['orderNo'], 'api_trade_no'=>$result['outOrderNo'], 'buyer'=>$result['buyerId'], 'money'=>strval(round($result['amt']/100, 2))]];
				}else{
					try{
						self::orderRevoked($client, TRADE_NO, $pay_type);
					}catch(Exception $e){
					}
					return ['type'=>'error','msg'=>'被扫下单失败！订单已超时'];
				}
			}
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'被扫下单失败！'.$e->getMessage()];
		}
	}

    static private function orderQuery($client, $out_trade_no){
		global $channel;
		$params = [
			'merchantNo' => $channel['appmchid'],
			'orderNo' => $out_trade_no,
		];
		$result = $client->request('/trade/tradeQuery', $params);
		return $result;
	}

	static private function orderRevoked($client, $out_trade_no, $pay_type){
		global $channel, $clientip;
		$params = [
			'merchantNo' => $channel['appmchid'],
			'orderNo' => $out_trade_no,
			'payType' => $pay_type,
		];
		$result = $client->request('/trade/cancel', $params);
		return $result;
	}

    //回调
    public static function notify()
    {
        global $channel, $order;
        $data = file_get_contents('php://input');
        $arr = json_decode($data, true);
        if (!$arr) return ['type' => 'html', 'data' => '{"code":"nodata"}'];

        require_once(PAY_ROOT . 'lib/PayClient.php');
        $client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);

        try {
            if (!$client->verifySign($arr, $data)){
                return ['type' => 'html', 'data' => '{"code":"fail"}'];
            }
            $out_trade_no = $arr['respData']['orderNo'];
            $api_trade_no = $arr['respData']['outOrderNo'];
            $buyer = $arr['respData']['buyerId'];
            $bill_trade_no = $arr['respData']['transactionId'];
            $bill_mch_trade_no = $arr['respData']['thirdPartyUuid'];
            if($out_trade_no == TRADE_NO){
                processNotify($order, $api_trade_no, $buyer, $bill_trade_no, $bill_mch_trade_no);
            }
            return ['type' => 'html', 'data' => '{"code":"success"}'];
        } catch (Exception $e) {
            return ['type' => 'html', 'data' => '{"code":"'.$e->getMessage().'"}'];
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
    public static function refund($order)
    {
        global $channel, $order;

        require_once(PAY_ROOT . 'lib/PayClient.php');

        $param = [
            'merchantNo' => $channel['appmchid'],
            'orderNo' => $order['refund_no'],
            'origOrderNo' => $order['trade_no'],
            'amt' => intval(round($order['refundmoney']*100))
        ];

        try {
            $client = new \xsy\PayClient($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);
            $result = $client->request('/trade/refund', $param);
            return ['code' => 0, 'trade_no'=>$result['orderNo'], 'refund_fee'=>$result['amt']/100];
        } catch (Exception $e) {
            return ['code' => -1, 'msg' => $e->getMessage()];
        }
    }
}