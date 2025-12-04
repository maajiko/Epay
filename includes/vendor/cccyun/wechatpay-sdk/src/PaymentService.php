<?php

namespace WeChatPay;

use Exception;

/**
 * 基础支付服务类
 * @see https://pay.weixin.qq.com/wiki/doc/api/index.html
 */
class PaymentService extends BaseService
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->publicParams = [
            'appid'      => $this->appId,
            'mch_id'     => $this->mchId,
            'nonce_str'  => $this->getNonceStr(),
            'sign_type'  => 'MD5',
        ];
        if (!empty($this->subMchId)) {
            $this->publicParams['sub_mch_id'] = $this->subMchId;
        }
        if (!empty($this->subAppId)) {
            $this->publicParams['sub_appid'] = $this->subAppId;
        }
    }

	/**
	 * 统一下单
	 * @param array $params 下单参数
	 * @return mixed
	 * @throws Exception
	 */
    public function unifiedOrder(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        if (empty($params['out_trade_no'])) {
            throw new \InvalidArgumentException('缺少统一支付接口必填参数out_trade_no');
        }
        if (empty($params['body'])) {
            throw new \InvalidArgumentException('缺少统一支付接口必填参数body');
        }
        if (empty($params['total_fee'])) {
            throw new \InvalidArgumentException('缺少统一支付接口必填参数total_fee');
        }
        if (empty($params['trade_type'])) {
            throw new \InvalidArgumentException('缺少统一支付接口必填参数trade_type');
        }
        return $this->execute($url, $params);
    }

	/**
	 * NATIVE支付
	 * @param array $params 下单参数
	 * @return mixed {"code_url":"二维码链接"}
	 * @throws Exception
	 */
    public function nativePay(array $params)
    {
        if (empty($params['product_id'])) {
            throw new \InvalidArgumentException('缺少NATIVE支付必填参数product_id');
        }
        $params['trade_type'] = 'NATIVE';
        return $this->unifiedOrder($params);
    }

	/**
	 * JSAPI支付
	 * @param array $params 下单参数
	 * @return array Jsapi支付json数据
	 * @throws Exception
	 */
    public function jsapiPay(array $params)
    {
        if (empty($params['openid'])) {
            throw new \InvalidArgumentException('缺少JSAPI支付必填参数openid');
        }
        $params['trade_type'] = 'JSAPI';
        $result = $this->unifiedOrder($params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

    /**
     * 获取JSAPI支付的参数
     * @param string $prepay_id 预支付交易会话标识
     * @return array json数据
     */
    private function getJsApiParameters(string $prepay_id): array
    {
        $appId = !empty($this->subAppId) ? $this->subAppId : $this->appId;
        $params = [
            'appId' => $appId,
            'timeStamp' => time() . '',
            'nonceStr' => $this->getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
            'signType' => 'MD5',
        ];
        $params['paySign'] = $this->makeSign($params);
        return $params;
    }

	/**
	 * APP支付
	 * @param array $params 下单参数
	 * @return array APP支付json数据
	 * @throws Exception
	 */
    public function appPay(array $params): array
    {
        $params['trade_type'] = 'APP';
        $result = $this->unifiedOrder($params);
        return $this->getAppParameters($result['prepay_id']);
    }

    /**
     * 获取APP支付的参数
     * @param string $prepay_id 预支付交易会话标识
     * @return array
     */
    private function getAppParameters(string $prepay_id): array
    {
        $params = [
            'appid' => $this->appId,
            'partnerid' => $this->mchId,
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $this->getNonceStr(),
            'timestamp' => time().'',
        ];
        $params['sign'] = $this->makeSign($params);
        return $params;
    }

	/**
	 * H5支付
	 * @param array $params 下单参数
	 * @return mixed {"mweb_url":"支付跳转链接"}
	 * @throws Exception
	 */
    public function h5Pay(array $params)
    {
        $params['trade_type'] = 'MWEB';
        return $this->unifiedOrder($params);
    }

	/**
	 * 付款码支付
	 * @param array $params 下单参数
	 * @return mixed {"openid":"用户标识","is_subscribe":"N","total_fee":888,"cash_fee":888,"transaction_id":"微信支付订单号","out_trade_no":"商户订单号","time_end":"支付完成时间"}
	 * @throws Exception
	 */
    public function microPay(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/micropay';
        if (empty($params['out_trade_no'])) {
            throw new \InvalidArgumentException('缺少付款码支付接口必填参数out_trade_no');
        }
        if (empty($params['body'])) {
            throw new \InvalidArgumentException('缺少付款码支付接口必填参数body');
        }
        if (empty($params['total_fee'])) {
            throw new \InvalidArgumentException('缺少付款码支付接口必填参数total_fee');
        }
        if (empty($params['auth_code'])) {
            throw new \InvalidArgumentException('缺少付款码支付接口必填参数auth_code');
        }
        return $this->execute($url, $params);
    }

	/**
	 * 撤销订单
	 * @param string|null $transaction_id 微信订单号
	 * @param string|null $out_trade_no 商户订单号
	 * @return mixed
	 * @throws Exception
	 */
    public function reverse(string $transaction_id = null, string $out_trade_no = null)
    {
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/reverse';
        $params = [];
        if ($transaction_id) {
            $params['transaction_id'] = $transaction_id;
        } elseif ($out_trade_no) {
            $params['out_trade_no'] = $out_trade_no;
        }
        return $this->execute($url, $params, true);
    }

	/**
	 * 查询订单，微信订单号、商户订单号至少填一个
	 * @param string|null $transaction_id 微信订单号
	 * @param string|null $out_trade_no 商户订单号
	 * @return mixed
	 * @throws Exception
	 */
    public function orderQuery(string $transaction_id = null, string $out_trade_no = null)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $params = [];
        if ($transaction_id) {
            $params['transaction_id'] = $transaction_id;
        } elseif ($out_trade_no) {
            $params['out_trade_no'] = $out_trade_no;
        }
        return $this->execute($url, $params);
    }

    /**
     * 判断订单是否已完成
     * @param string $transaction_id 微信订单号
     * @return bool
     */
    public function orderQueryResult(string $transaction_id): bool
    {
        try {
            $data = $this->orderQuery($transaction_id);
            return $data['trade_state'] == 'SUCCESS' || $data['trade_state'] == 'REFUND';
        } catch (Exception $e) {
            return false;
        }
    }

	/**
	 * 关闭订单
	 * @param string $out_trade_no 商户订单号
	 * @return mixed
	 * @throws Exception
	 */
    public function closeOrder(string $out_trade_no)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $params = [
            'out_trade_no' => $out_trade_no
        ];
        return $this->execute($url, $params);
    }

	/**
	 * 申请退款
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function refund(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        if (empty($params['transaction_id']) && empty($params['out_trade_no'])) {
            throw new \InvalidArgumentException('out_trade_no、transaction_id至少填一个');
        }
        if (empty($params['out_refund_no'])) {
            throw new \InvalidArgumentException('out_refund_no参数不能为空');
        }
        if (empty($params['total_fee'])) {
            throw new \InvalidArgumentException('total_fee参数不能为空');
        }
        if (empty($params['refund_fee'])) {
            throw new \InvalidArgumentException('refund_fee参数不能为空');
        }
        return $this->execute($url, $params, true);
    }

	/**
	 * 查询退款
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function refundQuery(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/refundquery';
        if (empty($params['transaction_id']) && empty($params['out_trade_no']) && empty($params['out_refund_no']) && empty($params['refund_id'])) {
            throw new \InvalidArgumentException('退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个');
        }
        return $this->execute($url, $params);
    }

	/**
	 * 下载对账单
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function downloadBill(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/downloadbill';
        if (empty($params['bill_date'])) {
            throw new \InvalidArgumentException('bill_date参数不能为空');
        }
        return $this->execute($url, $params);
    }

	/**
	 * 下载资金账单
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function downloadFundFlow(array $params)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/downloadfundflow';
        if (empty($params['bill_date'])) {
            throw new \InvalidArgumentException('bill_date参数不能为空');
        }
        if (empty($params['account_type'])) {
            throw new \InvalidArgumentException('account_type参数不能为空');
        }
        return $this->execute($url, $params);
    }

	/**
	 * 支付结果通知
	 * @return bool|mixed
	 * @throws Exception
	 */
    public function notify()
    {
        $xml = file_get_contents("php://input");
        if (empty($xml)) {
            throw new Exception('NO_DATA');
        }
        $result = $this->xml2array($xml);
        if (!$result) {
            throw new Exception('XML_ERROR');
        }
        if ($result['return_code'] != 'SUCCESS') {
            throw new Exception($result['return_msg']);
        }
        if (!$this->checkSign($result)) {
            throw new Exception('签名校验失败');
        }
        if (!isset($result['transaction_id'])) {
            throw new Exception('缺少订单号参数');
        }
        if (!$this->orderQueryResult($result['transaction_id'])) {
            throw new Exception('订单未完成');
        }
        return $result;
    }

    /**
     * 退款结果通知
     * @param array &$errmsg 错误信息
     * @return bool|string
     */
    public function refundNotify(array &$errmsg): bool
    {
        $xml = file_get_contents("php://input");
        if (empty($xml)) {
            $errmsg = 'NO_DATA';
            return false;
        }
        $result = $this->xml2array($xml);
        if (!$result) {
            $errmsg = 'XML_ERROR';
            return false;
        }
        if ($result['return_code'] != 'SUCCESS') {
            $errmsg = $result['return_msg'];
            return false;
        }
        $req_info = base64_decode($result['req_info']);
        $md5_key = md5($this->apiKey);
	    return openssl_decrypt($req_info, 'aes-256-ecb', $md5_key);
    }

    /**
     * 回复通知
     * @param bool $isSuccess 是否成功
     * @param string|null $msg 失败原因
     */
    public function replyNotify(bool $isSuccess = true, ?string $msg = '')
    {
        $data = [];
        if ($isSuccess) {
            $data['return_code'] = 'SUCCESS';
            $data['return_msg'] = 'OK';
        } else {
            $data['return_code'] = 'FAIL';
            $data['return_msg'] = $msg;
        }
        $xml = $this->array2Xml($data);
        echo $xml;
    }
}
