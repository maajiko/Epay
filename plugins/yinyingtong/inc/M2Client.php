<?php

/**
 * @see https://ecn6ul7ztz1a.feishu.cn/docx/N236dMmCJommkJxWcxzcFwiRnAg
 */
class M2Client
{
    private static $url_ca = 'https://api.gomepay.com/CoreCaServlet';

    private static $url_upload = 'https://res.gomepay.com/yyt/upload_internet';

    //应用ID
    private $aid;

    //应用KEY
    private $app_key;

    //私钥证书文件
    private $private_key_file;

    //公钥证书文件
    private $public_key_file = PLUGIN_ROOT.'yinyingtong/cert/M2.cer';

    private $debug = false;

    public function __construct($aid, $app_key, $debug = false){
        $this->aid = $aid;
        $this->app_key = $app_key;
        $this->private_key_file = PLUGIN_ROOT.'yinyingtong/cert/'.$aid.'.pfx';
        if(!file_exists($this->public_key_file)){
            throw new \Exception('平台公钥证书文件M2.cer不存在');
        }
        if(!file_exists($this->private_key_file)){
            throw new \Exception('商户私钥证书文件'.$aid.'.pfx不存在');
        }
        $this->debug = $debug;
    }

    /**
     * @param $api_code string 接口编码
     * @param $params array 请求参数
     * @param $random_key string 随机密钥
     * @param $all_encrypt int 加密范围 0 不加密 1 部分加密 2 全报文加密
     * @return null
     */
    public function execute($api_code, $params, $random_key = null, $all_encrypt = 0){
        $timestamp = time().'';
        $nonce = rand(000000,999999).'';
        $data = json_encode($params, JSON_UNESCAPED_SLASHES);
        $sign = $this->generateSign($api_code, $timestamp, $nonce, $data);

        $query = [
            'aid' => $this->aid,
            'api_id' => $api_code,
            'signature' => $sign,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
        ];
        if ($this->debug) {
            $query['debug'] = 'true';
        }
        if (!empty($random_key)) {
            $rc4_key = $this->get_rc4_key();
            $encryptedKey = $this->rc4Encrypt($random_key, $rc4_key);
            $query['random_key'] = $encryptedKey;
        }
        if ($all_encrypt > 0) {
            $query['all_encrypt'] = $all_encrypt;
        }
        $url = self::$url_ca . '?' . http_build_query($query);

        $response = get_curl($url, $data, 0, 0, 0, 0, 0, ['Content-Type: application/json']);
        $result = json_decode($response, true);
        if(isset($result['op_ret_code']) && $result['op_ret_code'] == '000'){
            return $result;
        }elseif(isset($result['op_ret_subcode'])){
            throw new \Exception('['.$result['op_ret_subcode'].']'.$result['op_err_submsg']);
        }elseif(isset($result['op_err_msg'])){
            throw new \Exception($result['op_err_msg']);
        }else{
            throw new \Exception($result['err_msg']?$result['err_msg']:'返回数据解析失败');
        }
    }

    //文件上传接口
    public function upload($file_path, $file_name){
        $params = [
            'aid' => $this->aid,
            $file_name => new \CURLFile($file_path, null, $file_name),
        ];
        $response = get_curl(self::$url_upload, $params);
        $result = json_decode($response, true);
        if(isset($result['err_code']) && $result['err_code'] == '000'){
            return $result['image_url'];
        }else{
            throw new \Exception($result['err_msg']?$result['err_msg']:'返回数据解析失败');
        }
    }

    //异步通知回调验签
    public function verify($dstbdata, $dstbdatasign)
    {
        $sign = md5($dstbdata . $this->app_key);
        return $sign === $dstbdatasign;
    }

    private function generateSign($api_code, $timestamp, $nonce, $data){
        $argList = [$this->aid, $api_code, $timestamp, $nonce];
        sort($argList, SORT_STRING);
        $content = implode('', $argList) . $data;
        return $this->rsaPrivateSign($content);
    }

    //平台公钥
    private function getPublicKey()
    {
        $file = file_get_contents($this->public_key_file);
        $cert = chunk_split($file, 64, "\n");
        $cert = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
        $res = openssl_pkey_get_public($cert);
        if (!$res) {
            throw new \Exception('平台公钥证书解析失败');
        }
        return $res;
    }

    //商户私钥
    private function getPrivateKey()
    {
        $file = file_get_contents($this->private_key_file);
        if (!openssl_pkcs12_read($file, $cert, $this->app_key)) {
            throw new \Exception('商户私钥证书解析失败');
        }
        return openssl_pkey_get_private($cert['pkey']);
    }

    //私钥加签
    private function rsaPrivateSign($data)
    {
        $prikey = $this->getPrivateKey();
        $result = openssl_sign($data, $sign, $prikey);
        if (!$result) throw new \Exception('sign error');
        return base64_encode($sign);
    }

    //公钥验签
    public function rsaPubilcVerify($data, $sign)
    {
        $pubkey = $this->getPublicKey();
        $result = openssl_verify($data, base64_decode($sign), $pubkey);
        return $result === 1;
    }

    private function get_rc4_key(){
        $pubkey = $this->getPublicKey();
        $details = openssl_pkey_get_details($pubkey);
        if ($details && isset($details['key'])) {
            $pem = $details['key'];
            $pem = str_replace("-----BEGIN PUBLIC KEY-----", "", $pem);
            $pem = str_replace("-----END PUBLIC KEY-----", "", $pem);
            $pem = str_replace("\n", "", $pem);
            return base64_decode($pem);
        }
        throw new \Exception('从平台公钥解析公钥失败');
    }

    private function rc4($data, $key)
    {
        if ($data === null || $key === null) {
            return null;
        }
        $keyLength = strlen($key);
        $dataLength = strlen($data);

        $S = range(0, 255);
        $j = 0;

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $S[$i] + ord($key[$i % $keyLength])) % 256;
            $temp = $S[$i];
            $S[$i] = $S[$j];
            $S[$j] = $temp;
        }

        $i = $j = 0;
        $result = '';
        for ($k = 0; $k < $dataLength; $k++) {
            $i = ($i + 1) % 256;
            $j = ($j + $S[$i]) % 256;

            $temp = $S[$i];
            $S[$i] = $S[$j];
            $S[$j] = $temp;

            $K = $S[($S[$i] + $S[$j]) % 256];
            $result .= chr(ord($data[$k]) ^ $K);
        }

        return $result;
    }

    private function rc4Encrypt($data, $key)
    {
        return base64_encode($this->rc4($data, $key));
    }

    private function rc4Decrypt($data, $key)
    {
        return $this->rc4(base64_decode($data), $key);
    }

    //sm4加密
	public function sm4Encrypt($plaintext, $key){
		$sm4 = new Rtgm\sm\RtSm4($key);
		return $sm4->encrypt($plaintext,'sm4-ecb','','base64');
	}

    //sm4解密
	public function sm4Decrypt($data, $key){
		$sm4 = new Rtgm\sm\RtSm4($key);
		return $sm4->decrypt($data,'sm4-ecb','','base64');
	}
}