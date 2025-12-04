<?php

namespace WeChatPay\V3;

use Exception;

/**
 * 服务商基础支付服务类
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3_partner/index.shtml
 */
class PartnerPaymentService extends BaseService
{
    public function __construct(array $config)
    {
        if(strpos($config['sub_mchid'], ',')){
            $sub_mchids = explode(',', $config['sub_mchid']);
            $config['sub_mchid'] = $sub_mchids[array_rand($sub_mchids)];
        }
        parent::__construct($config);
    }


	/**
	 * NATIVE支付
	 * @param array $params 下单参数
	 * @return mixed {"code_url":"二维码链接"}
	 * @throws Exception
	 */
    public function nativePay(array $params){
        $path = '/v3/pay/partner/transactions/native';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * JSAPI支付
	 * @param array $params 下单参数
	 * @return array Jsapi支付json数据
	 * @throws Exception
	 */
    public function jsapiPay(array $params){
        $path = '/v3/pay/partner/transactions/jsapi';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
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
            'timeStamp' => time().'',
            'nonceStr' => $this->getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
        ];
        $params['paySign'] = $this->makeSign([$params['appId'], $params['timeStamp'], $params['nonceStr'], $params['package']]);
        $params['signType'] = 'RSA';
        return $params;
    }

	/**
	 * H5支付
	 * @param array $params 下单参数
	 * @return mixed {"h5_url":"支付跳转链接"}
	 * @throws Exception
	 */
    public function h5Pay(array $params){
        $path = '/v3/pay/partner/transactions/h5';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * APP支付
	 * @param array $params 下单参数
	 * @return array {"prepay_id":"预支付交易会话标识"}
	 * @throws Exception
	 */
    public function appPay(array $params){
        $path = '/v3/pay/partner/transactions/app';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
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
        $params['sign'] = $this->makeSign([$params['appid'], $params['timestamp'], $params['noncestr'], $params['prepayid']]);
        return $params;
    }

	/**
	 * 查询订单，微信订单号、商户订单号至少填一个
	 * @param string|null $transaction_id 微信订单号
	 * @param string|null $out_trade_no 商户订单号
	 * @return mixed
	 * @throws Exception
	 */
    public function orderQuery(string $transaction_id = null, string $out_trade_no = null){
        if(!empty($transaction_id)){
            $path = '/v3/pay/partner/transactions/id/'.$transaction_id;
        }elseif(!empty($out_trade_no)){
            $path = '/v3/pay/partner/transactions/out-trade-no/'.$out_trade_no;
        }else{
            throw new Exception('微信支付订单号和商户订单号不能同时为空');
        }
        
        $params = [
            'sp_mchid' => $this->mchId,
            'sub_mchid' => $this->subMchId,
        ];
        return $this->execute('GET', $path, $params);
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
    public function closeOrder(string $out_trade_no){
        $path = '/v3/pay/partner/transactions/out-trade-no/'.$out_trade_no.'/close';
        $params = [
            'sp_mchid' => $this->mchId,
            'sub_mchid' => $this->subMchId,
        ];
        return $this->execute('POST', $path, $params);
    }

	/**
	 * 申请退款
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function refund(array $params){
        $path = '/v3/refund/domestic/refunds';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * 查询退款
	 * @param string $out_refund_no
	 * @return mixed
	 * @throws Exception
	 */
    public function refundQuery(string $out_refund_no){
        $path = '/v3/refund/domestic/refunds/'.$out_refund_no;
        $params = [
            'sub_mchid' => $this->subMchId,
        ];
        return $this->execute('GET', $path, $params);
    }

	/**
	 * 申请交易账单
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function tradeBill(array $params){
        $path = '/v3/bill/tradebill';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('GET', $path, $params);
    }

	/**
	 * 申请资金账单
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function fundflowBill(array $params){
        $path = '/v3/bill/fundflowbill';
        return $this->execute('GET', $path, $params);
    }

	/**
	 * 申请单个子商户资金账单
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
    public function subMerchantFundflowBill(array $params){
        $path = '/v3/bill/sub-merchant-fundflowbill';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('GET', $path, $params);
    }

	/**
	 * 支付通知处理
	 * @return array 支付成功通知参数
	 * @throws Exception
	 */
    public function notify(): array
    {
        $data = parent::notify();
        if (!$data || !isset($data['transaction_id']) && !isset($data['combine_out_trade_no'])) {
            throw new Exception('缺少订单号参数');
        }
        if (!isset($data['combine_out_trade_no']) && !$this->orderQueryResult($data['transaction_id'])) {
            throw new Exception('订单未完成');
        }
        return $data;
    }


	/**
	 * 合单Native支付
	 * @param array $params 下单参数
	 * @return mixed {"code_url":"二维码链接"}
	 * @throws Exception
	 */
    public function combineNativePay(array $params){
        $path = '/v3/combine-transactions/native';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
            if(!isset($order['sub_mchid'])) $order['sub_mchid'] = $this->subMchId;
            if(!isset($order['sub_appid'])) $order['sub_appid'] = $this->subAppId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * 合单JSAPI支付
	 * @param array $params 下单参数
	 * @return array Jsapi支付json数据
	 * @throws Exception
	 */
    public function combineJsapiPay(array $params): array
    {
        $path = '/v3/combine-transactions/jsapi';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
            if(!isset($order['sub_mchid'])) $order['sub_mchid'] = $this->subMchId;
            if(!isset($order['sub_appid'])) $order['sub_appid'] = $this->subAppId;
        }
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

	/**
	 * 合单H5支付
	 * @param array $params 下单参数
	 * @return mixed {"h5_url":"支付跳转链接"}
	 * @throws Exception
	 */
    public function combineH5Pay(array $params){
        $path = '/v3/combine-transactions/h5';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
            if(!isset($order['sub_mchid'])) $order['sub_mchid'] = $this->subMchId;
            if(!isset($order['sub_appid'])) $order['sub_appid'] = $this->subAppId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * 合单APP支付
	 * @param array $params 下单参数
	 * @return mixed {"prepay_id":"预支付交易会话标识"}
	 * @throws Exception
	 */
    public function combineAppPay(array $params){
        $path = '/v3/combine-transactions/app';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
            if(!isset($order['sub_mchid'])) $order['sub_mchid'] = $this->subMchId;
            if(!isset($order['sub_appid'])) $order['sub_appid'] = $this->subAppId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

	/**
	 * 合单查询订单
	 * @param string $combine_out_trade_no 合单商户订单号
	 * @return mixed
	 * @throws Exception
	 */
    public function combineQueryOrder(string $combine_out_trade_no){
        $path = '/v3/combine-transactions/out-trade-no/'.$combine_out_trade_no;
        
        return $this->execute('GET', $path, []);
    }

	/**
	 * 合单关闭订单
	 * @param string $combine_out_trade_no 合单商户订单号
	 * @param array $out_trade_no_list 子单订单号列表
	 * @return mixed
	 * @throws Exception
	 */
    public function combineCloseOrder(string $combine_out_trade_no, array $out_trade_no_list){
        $path = '/v3/combine-transactions/out-trade-no/'.$combine_out_trade_no.'/close';
        $sub_orders = [];
        foreach($out_trade_no_list as $out_trade_no){
            $sub_orders[] = [
                'mchid' => $this->mchId,
                'out_trade_no' => $out_trade_no,
                'sub_appid' => $this->subAppId,
                'sub_mchid' => $this->subMchId,
            ];
        }
        $params = [
            'combine_appid' => $this->appId,
            'sub_orders' => $sub_orders
        ];
        return $this->execute('POST', $path, $params);
    }
}