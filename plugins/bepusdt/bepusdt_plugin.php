<?php

class bepusdt_plugin
{
    public static $info = [
        'name'     => 'bepusdt',
        'showname' => 'BEpusdt USDT/USDC 个人收款',
        'author'   => 'V03413',
        'link'     => 'https://github.com/v03413/BEpusdt',
        'types'    => [
            // 此列表可能存在变动，以此为准 https://github.com/v03413/BEpusdt/blob/main/docs/trade-type.md
            'tron.trx',
            'usdt.trc20',
            'usdc.trc20',
            'usdt.polygon',
            'usdc.polygon',
            'usdt.arbitrum',
            'usdc.arbitrum',
            'usdt.erc20',
            'usdc.erc20',
            'usdt.bep20',
            'usdc.bep20',
            'usdt.xlayer',
            'usdc.xlayer',
            'usdc.base',
            'usdt.solana',
            'usdc.solana',
            'usdt.aptos',
            'usdc.aptos',
        ],
        'inputs'   => [
            'appurl'  => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '必须以http://或https://开头，以/结尾',
            ],
            'appkey'  => [
                'name' => '认证Token',
                'type' => 'input',
                'note' => '搭建BEpusdt时填写的 auth_token 参数',
            ],
            'address' => [
                'name' => '收款地址',
                'type' => 'input',
                'note' => '可以留空 留空则由BEpusdt自动分配，切勿乱填 注意空格',
            ],
            'timeout' => [
                'name' => '订单超时',
                'type' => 'input',
                'note' => '可以留空 填写整数(单位秒)、推荐 1200',
            ],
            'rate'    => [
                'name' => '订单汇率',
                'type' => 'input',
                'note' => '可以留空 例如：7.4 ~1.02 ~0.98（不明白切勿乱填）',
            ],
        ],
        'select'   => null,
        'note'     => '', //支付密钥填写说明
    ];

    public static function submit(): array
    {
        global $siteurl, $channel, $order, $conf;

        $parameter = [
            'address'      => trim($channel['address']),
            'trade_type'   => $order['typename'],
            'order_id'     => TRADE_NO,
            'name'         => $order['name'],
            'timeout'      => intval($channel['timeout']),
            'rate'         => strval($channel['rate']),
            'amount'       => $order['realmoney'],
            'notify_url'   => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
            'redirect_url' => $siteurl . 'pay/return/' . TRADE_NO . '/',
        ];

        $parameter['signature'] = self::_toSign($parameter, $channel['appkey']);

        $url  = trim($channel['appurl']) . 'api/v1/order/create-transaction';
        $data = self::_post($url, $parameter);
        if (!is_array($data)) {

            return ['type' => 'error', 'msg' => '请求失败，请检查服务器是否能正常请求 BEpusdt 网关！'];
        }

        if ($data['status_code'] != 200) {

            return ['type' => 'error', 'msg' => '请求失败，错误信息：' . $data['message']];
        }

        return ['type' => 'jump', 'url' => $data['data']['payment_url']];
    }

    public static function notify()
    {
        global $channel, $order;

        ob_clean();
        header('Content-Type: plain/text; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $sign = $data['signature'] ?? '';
        if ($sign != self::_toSign($data, $channel['appkey'])) {
            // 签名验证失败

            exit('fail - sign error');
        }

        $out_trade_no = $data['order_id'];    // 商户订单号
        $trade_no     = $data['trade_id'];    // BEpusdt 交易ID
        $buyer        = mb_substr($data['buyer'], -28);
        if ($data['status'] === 2 && $out_trade_no == TRADE_NO) {
            processNotify($order, $trade_no, $buyer);

            exit('ok');
        }

        exit('fail - status error');
    }

    public static function return(): array
    {
        return ['type' => 'page', 'page' => 'return'];
    }

    private static function _toSign(array $parameter, string $token): string
    {
        ksort($parameter);

        $sign = '';

        foreach ($parameter as $key => $val) {
            if ($val == '') continue;
            if ($key != 'signature') {
                if ($sign != '') {
                    $sign .= "&";

                }

                $sign .= "$key=$val";
            }
        }

        return md5($sign . $token);
    }

    private static function _post(string $url, array $json)
    {

        $header[] = 'Accept: */*';
        $header[] = 'Accept-Language: zh-CN,zh;q=0.8';
        $header[] = 'Connection: close';
        $header[] = 'Content-Type: application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $resp = curl_exec($ch);
        curl_close($ch);

        return json_decode($resp, true);
    }
}