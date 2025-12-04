<?php

/**
 * https://ecn6ul7ztz1a.feishu.cn/docx/NdZndQqRVou9XRxmK8Vcn07UnSf
 * https://ecn6ul7ztz1a.feishu.cn/docx/XqI7dQ7jioFdrRxxjXdcA9QDnMg
 * https://ecn6ul7ztz1a.feishu.cn/docx/CaC9dkAN3oqbOuxVJYXcApsgnqd
 */
class PayClient
{
    //支付网关地址
    private $gateway_url = 'https://gc-gw.gomepay.com/gpayCashApi';

    //应用APPID
    private $app_id;

    //应用密钥
    private $app_secret;

    
    public function __construct($app_id, $app_secret){
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
    }

    //发起请求
    public function execute($method, $bizData)
    {
        if(checkalipay()){
            $browser_brand = '01';
        }elseif(checkwechat()){
            $browser_brand = '02';
        }elseif(checkmobile()){
            $browser_brand = '04';
        }else{
            $browser_brand = '99';
        }
        $params = [
            'app_id' => $this->app_id,
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'UTF-8',
            'sign_type' => 'MD5',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'client_ip' => real_ip(),
            'data' => json_encode($bizData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'req_no' => getSid(),
            'terminal_type' => checkmobile() ? '4' : '3',
            'browser_brand' => $browser_brand,
        ];
        $params['sign'] = $this->get_sign($params);

        $data = get_curl($this->gateway_url, json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 0, 0, 0, 0, ['method: cash-api@'.$method, 'Content-Type: application/json; charset=utf-8']);

        $result = json_decode($data, true);

        if(isset($result['code']) && ($result['code']=='000000' || $result['code']=='900888' || $result['code']=='900889' || $result['code']=='900001')){
            return json_decode($result['data'], true);
        }elseif(isset($result['sub_msg'])){
            throw new Exception('['.$result['sub_code'].']'.$result['sub_msg']);
        }elseif(isset($result['data']) && strpos($result['data'], 'op_ret_code')!==false){
            $result = json_decode($result['data'], true);
            if(isset($result['op_ret_code']) && ($result['op_ret_code']=='000' || $result['op_ret_code']=='701')){
                return $result;
            }elseif(isset($result['op_ret_subcode'])){
                throw new Exception('['.$result['op_ret_subcode'].']'.$result['op_err_submsg']);
            }else{
                throw new Exception('['.$result['op_ret_code'].']'.$result['op_ret_msg']);
            }
        }else{
            throw new Exception($result['msg']?$result['msg']:'返回数据解析失败');
        }
    }

    public function verify($params){
        if(!isset($params['sign'])) return false;

        $sign = $this->get_verify_sign($params);

        return $sign === $params['sign'];
    }

    private function get_verify_sign($params){
        ksort($params);

        $signstr = '';
        foreach($params as $k => $v){
            if($k != "sign" && $v!==''){
                $signstr .= $k.'='.$v.'&';
            }
        }
        $signstr .= 'key='.$this->app_secret;

        return strtoupper(md5($signstr));
	}

    private function get_sign($params){
        $sign_keys = ['req_no','app_id','sign_type','charset','format','version','data','timestamp','method'];
		ksort($params);

        $signstr = '';
        foreach($params as $k => $v){
            if(in_array($k, $sign_keys) && $v!==''){
                $signstr .= $k.'='.$v.'&';
            }
        }
        $signstr .= 'key='.$this->app_secret;

        return strtoupper(md5($signstr));
	}

    public function notify($data, $key){
        $data = trim(substr($data, 107));
        $len = trim(substr($data, 0, 4));
        $cipher = trim(substr($data, 4));
        $dec_data = self::desDecrypt($cipher, $key);
        if(!$dec_data) return false;
        $json = explode('', $dec_data)[1];
        return json_decode(trim($json), true);
    }

    //DES 加密
    public static function desEncrypt($str, $key)
    {
        $key = substr($key, 0, 8);
        $encrypted = openssl_encrypt($str, 'des-ede3-ecb', $key, OPENSSL_RAW_DATA);
        $encryptedHex = strtoupper(bin2hex($encrypted));
        return $encryptedHex;
    }

    //DES 解密
    public static function desDecrypt($str, $key)
    {
        $decrypted = openssl_decrypt(hex2bin($str), 'des-ede3-ecb' ,$key, OPENSSL_RAW_DATA);
        return rtrim($decrypted, "\0");
    }
}