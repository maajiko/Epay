<?php

class AdapayClient
{
    const SDK_VERSION = 'v1.0.0';
	private $gateWayUrl = 'https://api.adapay.tech'; //网关地址
	private $rsaPublicKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCwN6xgd6Ad8v2hIIsQVnbt8a3JituR8o4Tc3B5WlcFR55bz4OMqrG/356Ur3cPbc2Fe8ArNd/0gZbC9q56Eb16JTkVNA/fye4SXznWxdyBPR7+guuJZHc/VW2fKH2lfZ2P3Tt0QkKZZoawYOGSMdIvO+WqK44updyax0ikK6JlNQIDAQAB";
    private $api_key;
    private $rsaPrivateKey;
    private $app_id;

    public function __construct($api_key_live, $rsa_private_key, $app_id)
	{
        $this->api_key = $api_key_live;
        $this->rsaPrivateKey = $rsa_private_key;
        $this->app_id = $app_id;
    }

    public function request($method, $endpoint, $params = null)
	{
		$req_url = $this->gateWayUrl . $endpoint;
        $headers = [];
        $postData = '';
		if($method == 'GET'){
			if($params){
				ksort($params);
				$req_url .= '?' . http_build_query($params);
			}
		}else{
            $postData = json_encode($params);
            $headers[] = 'Content-Type: application/json';
		}
        $headers[] = 'Authorization: ' . $this->api_key;
        $headers[] = 'Signature: ' . $this->generateSignature($req_url , $postData);
        $headers[] = 'sdk_version: ' . self::SDK_VERSION;

        $response = get_curl($req_url, $postData, 0, 0, 0, 0, 0, $headers);
		
		if (!$response || !($result = json_decode($response , true))) {
			throw new Exception('返回内容为空或解析失败');
		}
		if(!isset($result['data']) && isset($result['message'])){
			throw new Exception($result['message']);
		}
		$data = json_decode($result['data'], true);

		if ($data['status'] !== 'succeeded' && $data['status'] !== 'pending' && empty($data['expend'])) {
			throw new Exception('['.$data['error_code'].']'.$data['error_msg']);
		}
		return $data;
	}


    //创建支付对象
	public function createPayment($params)
	{
		$endpoint = '/v1/payments';
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($params, $public_params);
		return $this->request('POST', $endpoint, $params);
	}

	//通用请求
	public function queryAdapay($params)
	{
		self::$gateWayUrl = "https://page.adapay.tech";
		$adapayFuncCode = $params["adapay_func_code"];
		$endpoint = '/v1/'.str_replace(".", "/",$adapayFuncCode);
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($public_params, $params);
		return $this->request('POST', $endpoint, $params);
	}

	//通用请求
	public function requestAdapay($params)
	{
		$adapayFuncCode = $params["adapay_func_code"];
		$endpoint = '/v1/'.str_replace(".", "/",$adapayFuncCode);
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($public_params, $params);
		return $this->request('POST', $endpoint, $params);
	}

	//查询支付对象
	public function queryPayment($id)
	{
		$endpoint = '/v1/payments/'.$id;
		return $this->request('GET', $endpoint, null);
	}

	//创建退款对象
	public function createRefund($params){
		$charge_id = isset($params['payment_id']) ? $params['payment_id'] : '';
		$endpoint = '/v1/payments/'.$charge_id.'/refunds';
		return $this->request('POST', $endpoint, $params);
	}

	//查询退款对象
	public function queryRefund($params){
		$endpoint = '/v1/payments/refunds';
		return $this->request('GET', $endpoint, $params);
	}

	//创建用户对象
	public function createMember($member_id){
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id,
		];
		$endpoint = '/v1/members';
		return $this->request('POST', $endpoint, $params);
	}

	//创建结算账户对象
	public function createSettleAccount($member_id, $account_info){
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id,
			'channel' => 'bank_account',
			'account_info' => $account_info
		];
		$endpoint = '/v1/settle_accounts';
		return $this->request('POST', $endpoint, $params);
	}

	//查询结算账户对象
	public function querySettleAccount($member_id, $settle_account_id){
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id,
			'settle_account_id' => $settle_account_id
		];
		$endpoint = '/v1/settle_accounts/'.$settle_account_id;
		return $this->request('GET', $endpoint, $params);
	}

	//删除结算账户对象
	public function deleteSettleAccount($member_id, $settle_account_id){
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id,
			'settle_account_id' => $settle_account_id
		];
		$endpoint = '/v1/settle_accounts/delete';
		return $this->request('POST', $endpoint, $params);
	}

	//创建支付确认对象
	public function createPaymentConfirm($params){
		$endpoint = '/v1/payments/confirm';
		return $this->request('POST', $endpoint, $params);
	}

	//查询支付确认对象
	public function queryPaymentConfirm($payment_confirm_id){
		$params = [
			'payment_confirm_id' => $payment_confirm_id
		];
		$endpoint = '/v1/payments/confirm/'.$payment_confirm_id;
		return $this->request('GET', $endpoint, $params);
	}

	//创建支付撤销对象
	public function createPaymentReverse($params){
		$endpoint = '/v1/payments/reverse';
		return $this->request('POST', $endpoint, $params);
	}

	//查询支付确认对象
	public function queryPaymentReverse($reverse_id){
		$params = [
			'reverse_id' => $reverse_id
		];
		$endpoint = '/v1/payments/reverse/'.$reverse_id;
		return $this->request('GET', $endpoint, $params);
	}

	//创建取现对象
	public function createDrawCash($params){
		$endpoint = '/v1/cashs';
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($params, $public_params);
		return $this->request('POST', $endpoint, $params);
	}

	//查询取现对象
	public function queryDrawCash($order_no){
		$endpoint = '/v1/cashs/stat';
		$params = [
			'order_no' => $order_no,
		];
		return $this->request('GET', $endpoint, $params);
	}

	//查询账户余额
	public function queryBalance($member_id, $settle_account_id = null){
		$endpoint = '/v1/settle_accounts/balance';
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id
		];
		if($settle_account_id){
			$params['settle_account_id'] = $settle_account_id;
		}
		return $this->request('GET', $endpoint, $params);
	}

	//钱包登录
	public function walletLogin($member_id, $ip){
		$endpoint = '/v1/walletLogin';
		$params = [
			'app_id' => $this->app_id,
			'member_id' => $member_id,
			'ip' => $ip
		];
		return $this->request('GET', $endpoint, $params);
	}

	//账户转账
	public function createTransfer($params){
		$endpoint = '/v1/settle_accounts/transfer';
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($params, $public_params);
		return $this->request('POST', $endpoint, $params);
	}

	//账户转账查询
	public function queryTransfer($params){
		$endpoint = '/v1/settle_accounts/transfer/list';
		$public_params = [
			'app_id' => $this->app_id,
		];
		$params = array_merge($public_params, $params);
		return $this->request('GET', $endpoint, $params);
	}

    private function generateSignature($url , $postData):string
	{
		$data = $url . $postData;
		$sign = $this->SHA1withRSA($data);
		return $sign;
	}
	
	private function SHA1withRSA($data)
	{
		$privKey = trim($this->rsaPrivateKey);
		$key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($privKey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
		$keyid = openssl_pkey_get_private($key);
		if(!$keyid){
			throw new \Exception('签名失败，商户私钥不正确');
		}
		openssl_sign($data , $signature , $keyid , OPENSSL_ALGO_SHA1);
		return base64_encode($signature);
	}
	
	public function verifySign($signature , $data)
	{
		$pubKey = trim($this->rsaPublicKey);
		$key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($pubKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
		$keyid = openssl_pkey_get_public($key);
		if(!$keyid){
			throw new \Exception('验签失败，AdaPay公钥不正确');
		}
		$result = openssl_verify($data , base64_decode($signature) , $keyid , OPENSSL_ALGO_SHA1);
		return $result === 1;
	}
}