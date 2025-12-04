<?php

namespace lib\ProfitSharing;

use Exception;

require_once PLUGIN_ROOT.'shengpay/inc/ShengPayClient.php';

class Shengpay implements IProfitSharing
{

    static $paytype = 'shengpay';

    private $channel;
    private $service;

    function __construct($channel){
		$this->channel = $channel;
        $this->service = new \ShengPayClient($channel['appid'],$channel['appkey'],$channel['appsecret']);
	}

    //请求分账
    public function submit($trade_no, $api_trade_no, $order_money, $info){
        global $conf;
        $receivers = [];
        $rdata = [];
        $allmoney = 0;
        foreach($info as $receiver){
            $money = round(floor($order_money * $receiver['rate']) / 100, 2);
            $receivers[] = ['receiverType'=>'B', 'receiverId'=>$receiver['account'], 'amount'=>intval(round($money * 100)), 'description'=>'分账'];
            $rdata[] = ['account'=>$receiver['account'], 'money'=>$money];
            $allmoney += $money;
        }

        $params = [
            'mchSharingNo' => $trade_no,
            'transactionId' => $api_trade_no,
            'totalAmount' => intval(round($allmoney * 100)),
            'notifyUrl' => $conf['localurl'].'pay/sharingnotify/'.$this->channel['id'].'/',
            'receivers' => json_encode($receivers),
        ];

        try{
            $result = $this->service->execute('/sharing/applySharing', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'分账请求成功', 'settle_no'=>$result['sharingNo'], 'money'=>$allmoney, 'rdata'=>$rdata];
    }

    //查询分账结果
    public function query($trade_no, $api_trade_no, $settle_no){
        $params = [
            'mchSharingNo' => $trade_no,
        ];

        try{
            $result = $this->service->execute('/sharing/querySharing', $params);
            if($result['status'] == 'FINISHED'){
                $receivers = json_decode($result['receivers'], true);
                if(empty($receivers)){
                    return ['code'=>-1, 'msg'=>'未查询到分账结果'];
                }
                $info = $receivers[0];
                if($info['sharingStatus'] == 'SUCCESS'){
                    return ['code'=>0, 'status'=>1];
                } elseif($info['sharingStatus'] == 'FAIL') {
                    return ['code'=>0, 'status'=>2, 'reason'=>$info['failReason']];
                }
            }
            return ['code'=>0, 'status'=>0];
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
        $success = 0;
        $errmsg = null;
        foreach($rdata as $receiver){
            $params = [
                'mchReturnNo' => date('YmdHis').rand(11111,99999),
                'mchSharingNo' => $trade_no,
                'returnReceiverType' => 'B',
                'returnReceiverId' => $receiver['account'],
                'returnAmount' => intval(round($receiver['money']*100)),
                'returnDescription' => '分账回退'
            ];
            try{
                $this->service->execute('/return/applyReturn', $params);
                $success++;
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
        }
        if($success > 0 || $errmsg == null){
            return ['code'=>0, 'msg'=>'分账回退成功'];
        }else{
            return ['code'=>-1, 'msg'=>$errmsg];
        }
    }

    //添加分账接收方
    public function addReceiver($account, $name = null){
        $params = [
            'receiver' => json_encode(['receiverType'=>'B', 'receiverId'=>$account])
        ];

        try{
            $result = $this->service->execute('/sharing/receiver/add', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'添加分账接收方成功'];
    }

    //删除分账接收方
    public function deleteReceiver($account){
        $params = [
            'receiver' => json_encode(['receiverType'=>'B', 'receiverId'=>$account])
        ];

        try{
            $result = $this->service->execute('/sharing/receiver/remove', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'删除分账接收方成功'];
    }
}