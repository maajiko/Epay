<?php

namespace lib\ProfitSharing;

use Exception;

require_once PLUGIN_ROOT.'yinyingtong/inc/GcoinClient.php';

class Yinyingtong implements IProfitSharing
{

    static $paytype = 'yinyingtong';

    private $channel;
    private $service;
    private $up_ent_no;

    function __construct($channel){
        global $DB;
		$this->channel = $channel;
        $applychannel = $DB->find('applychannel', '*', ['type'=>'YinyingtongR', 'status'=>1]);
        if(!$applychannel) throw new Exception('未找到银盈通分账接收方进件渠道');
        $config = json_decode($applychannel['config'], true);
        if(empty($config['client_id']) || empty($config['client_secret'])){
            throw new Exception('客户端ID和密钥不能为空');
        }
        $this->up_ent_no = $config['up_ent_no'];
        $this->service = new \GcoinClient($config['client_id'], $config['client_secret']);
	}

    //请求分账
    public function submit($trade_no, $api_trade_no, $order_money, $info){
        $params = [
            'yyt_order_pay_id' => $api_trade_no,
        ];
        $biz_params = [
            'deal_mer_no' => $this->channel['appmchid'],
        ];
        try{
            $result = $this->service->execute('/gcoin/cls/queryCanDividedAmount', $params, $biz_params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'查询可分账金额失败，'.$e->getMessage()];
        }
        $left_money = $result['uncomplet_account_amount'] ?? 0;

        $receiver_list = [];
        $rdata = [];
        $allmoney = 0;
        foreach($info as $receiver){
            $money = $receiver['rate'] == 100 ? $left_money : round(floor($order_money * $receiver['rate']) / 100, 2);
            $receiver_list[] = ['settlement_ent_no'=>$receiver['account'], 'amount'=>$money];
            $rdata[] = ['account'=>$receiver['account'], 'money'=>$money];
            $allmoney += $money;
        }

        $params = [
            'settlement_type' => '1',
            'command_no' => $trade_no,
            'yyt_order_pay_id' => $api_trade_no,
            'total_amount' => $allmoney,
            'finish' => '0',
        ];
        $biz_params = [
            'platform_ent_no' => $this->up_ent_no,
            'platform_mer_no' => $this->up_ent_no,
            'divided_receiver_list' => $receiver_list,
        ];

        try{
            $result = $this->service->execute('/gcoin/cls/platformDividedCommand', $params, $biz_params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'分账请求成功', 'settle_no'=>$result['command_no'], 'money'=>$result['total_amount'], 'rdata'=>$rdata];
    }

    //查询分账结果
    public function query($trade_no, $api_trade_no, $settle_no){
        $params = [
            'command_no' => $settle_no
        ];

        try{
            $result = $this->service->execute('/gcoin/cls/queryPlatformDividedCommandResult', $params);
            if($result['status'] == '7'){
                return ['code'=>0, 'status'=>1];
            } elseif($result['resultcode'] == '8') {
                return ['code'=>0, 'status'=>2, 'reason'=>$result['result_desc']];
            } else {
                return ['code'=>0, 'status'=>0];
            }
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //解冻剩余资金
    public function unfreeeze($trade_no, $api_trade_no){
        return ['code'=>-1,'msg'=>'不支持当前操作'];
    }

    //分账回退
    public function return($trade_no, $api_trade_no, $rdata){
        $receiver_list = [];
        $allmoney = 0;
        foreach($rdata as $row){
            $receiver_list[] = ['settlement_ent_no'=>$row['account'], 'amount'=>$row['money']];
            $allmoney += $row['money'];
        }
        $params = [
            'command_no' => date('YmdHis').rand(11111,99999),
            'origin_command_no' => $trade_no,
            'yyt_order_pay_id' => $api_trade_no,
            'total_amount' => $allmoney,
        ];
        $biz_params = [
            'platform_ent_no' => $this->up_ent_no,
            'platform_mer_no' => $this->up_ent_no,
            'divided_receiver_list' => $receiver_list,
        ];

        try{
            $result = $this->service->execute('/gcoin/cls/cancelPlatformDividedCommand', $params, $biz_params);
            return ['code'=>0, 'msg'=>'分账撤销成功'];
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //添加分账接收方
    public function addReceiver($account, $name = null){
        global $DB;
        if($account == $this->up_ent_no || $DB->find("applymerchant", '*', ['mchid'=>$account])){
            return ['code'=>0, 'msg'=>'添加分账接收方成功'];
        }
        return ['code'=>-1, 'msg'=>'请先在进件商户管理添加分账接收方'.$account];
    }

    //删除分账接收方
    public function deleteReceiver($account){
        return ['code'=>0, 'msg'=>'删除分账接收方成功'];
    }
}