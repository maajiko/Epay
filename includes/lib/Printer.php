<?php
namespace lib;

/**
 * https://iot-doc.excelsecu.com/
 */
class Printer
{
    private $gateway_url = 'https://saas.excelsecu.com';
    private $appid;
    private $appsecret;

    public function __construct($appid, $appsecret)
    {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    public function print($sn, $params, $count = 1, $voice = false, $log = false){
        global $CACHE;
        if(!$count) $count = 1;
        $content = '<printer><br/><h3-s justify="center">'.$params['codename'].'</h3-s><br line="2"/><divider symbol="-"></divider><br/><h4>订单金额: '.$params['money'].'元</h4><br/><h4>支付金额: '.$params['money'].'元</h4><br/><h4>优惠金额: 0.00元</h4><br/><h4>支付类型: '.$params['type'].'</h4><br/><h4>订单状态: 成功</h4><br/><h4>订单号: '.$params['trade_no'].'</h4><br/><h4>支付时间: '.$params['time'].'</h4>'.($params['remark']?'<br/><h4>订单备注: '.$params['remark'].'</h4>':'').'<br/><divider symbol="-"></divider><br line="2"/><cut/></printer>';
        $biz = [
            'sn' => $sn,
            'content' => base64_encode($content),
            'count' => intval($count),
        ];
        if($voice){
            $biz['tts'] = $params['type'].'收款'.$params['money'].'元';
        }
        try{
            $this->request('/openapi/v1/iot/printer/print', 3, $biz);
            return true;
        }catch(\Exception $e){
            if($log){
                $errmsg = '打印失败，'.$e->getMessage();
                $CACHE->save('printerrmsg', ['errmsg'=>$errmsg, 'time'=>date('Y-m-d H:i:s')], 86400);
            }else{
                throw $e;
            }
            return false;
        }
    }

    private function request($path, $biztype, $biz){
        $url = $this->gateway_url . $path;
        $timestamp = (int)getMillisecond();
        $reqid = getSid();
        $biz = json_encode($biz, JSON_UNESCAPED_UNICODE);
        $signstr = $this->appid . $timestamp . $biz . $biztype . $reqid;
        $params = [
            'appid' => $this->appid,
            'reqid' => $reqid,
            'timestamp' => $timestamp,
            'sign' => base64_encode(hash_hmac('sha256', $signstr, $this->appsecret, true)),
            'biztype' => $biztype,
            'biz' => $biz,
        ];
        $data = get_curl($url, json_encode($params, JSON_UNESCAPED_UNICODE), 0, 0, 0, 0, 0, ['Content-Type: application/json']);
        $result = json_decode($data, true);
        if(isset($result['code']) && $result['code'] == 0){
            return $result['data'];
        }else{
            throw new \Exception($result['message'] ?? 'Unknown error');
        }
    }
}