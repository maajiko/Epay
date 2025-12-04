<?php

namespace lib\ProfitSharing;

use Exception;

require_once PLUGIN_ROOT.'kunpeng/inc/KunpengClient.php';

class Kunpeng implements IProfitSharing
{

    static $paytype = 'kunpeng';

    private $channel;
    private $service;

    function __construct($channel){
		$this->channel = $channel;
        $this->service = new \KunpengClient($channel['appid']);
	}

    //请求分账
    public function submit($trade_no, $api_trade_no, $order_money, $info){
        global $conf;
        $sharingInfos = [];
        $rdata = [];
        $allmoney = 0;
        $i = 1;
        foreach($info as $receiver){
            $money = round(floor($order_money * $receiver['rate']) / 100, 2);
            $sharingInfos[] = ['seqNo'=>$trade_no.$i++, 'subCustNo'=>$receiver['account'], 'amount'=>strval($money * 100)];
            $rdata[] = ['account'=>$receiver['account'], 'money'=>$money];
            $allmoney += $money;
        }

        $params = [
            'instOrderNo' => $api_trade_no,
            'payCustomerOrderNo' => $trade_no,
            'sharingOrderDate' => substr($trade_no, 0, 8),
            'sharingCustOrderNo' => $trade_no,
            'sharingNotifyUrl' => $conf['localurl'].'pay/sharingnotify/'.$this->channel['id'].'/',
            'sharingInfos' => json_encode($sharingInfos),
        ];

        try{
            $result = $this->service->execute3('/mas/sharing/sharingOrderSync.do', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'分账请求成功', 'settle_no'=>$result['sharingInstOrderNo'], 'money'=>$allmoney, 'rdata'=>$rdata];
    }

    //查询分账结果
    public function query($trade_no, $api_trade_no, $settle_no){
        $params = [
            'sharingOrderDate' => substr($trade_no, 0, 8),
            'sharingCustOrderNo' => $trade_no,
            'sharingInstOrderNo' => $settle_no
        ];

        try{
            $result = $this->service->execute3('/mas/sharing/orderQuerySync.do', $params);
            $sharingInfos = json_decode($result['sharingInfos'], true);
            if(empty($sharingInfos)){
                return ['code'=>-1, 'msg'=>'未查询到分账结果'];
            }
            $info = $sharingInfos[0];
            if($info['resultcode'] == '0000'){
                return ['code'=>0, 'status'=>1];
            } elseif($info['resultcode'] == '9997') {
                global $DB;
                $addtime = $DB->findColumn('psorder', 'addtime', ['trade_no'=>$trade_no]);
                if($addtime && strtotime($addtime) < time() - 86400){
                    return ['code'=>0, 'status'=>2, 'reason'=>'分账超时未处理'];
                }
                return ['code'=>0, 'status'=>0];
            } else {
                return ['code'=>0, 'status'=>2, 'reason'=>$info['resultmsg'] ?? '分账结果查询失败'];
            }
        }catch(Exception $e){
            $errmsg = $e->getMessage();
            if($errmsg == '交易处理中'){
                global $DB;
                $addtime = $DB->findColumn('psorder', 'addtime', ['trade_no'=>$trade_no]);
                if($addtime && strtotime($addtime) < time() - 86400){
                    return ['code'=>0, 'status'=>2, 'reason'=>'分账超时未处理'];
                }
            }
            return ['code'=>-1, 'msg'=>$errmsg];
        }
    }

    //解冻剩余资金
    public function unfreeeze($trade_no, $api_trade_no){
        return ['code'=>-1,'msg'=>'不支持当前操作'];
    }

    //分账回退
    public function return($trade_no, $api_trade_no, $rdata){
        global $DB;
        $settle_no = $DB->findColumn('psorder', 'settle_no', ['trade_no'=>$trade_no]);
        $sharingInfos = [];
        $i = 1;
        foreach($rdata as $row){
            $sharingInfos[] = ['seqNo'=>$trade_no.$i++, 'subCustNo'=>$row['account'], 'amount'=>strval($row['money'] * 100)];
        }
        $params = [
            'reqNo' => date('YmdHis').rand(11111,99999),
            'sharingCustOrderNo' => $trade_no,
            'sharingInstOrderNo' => $settle_no,
            'sharingInfos' => json_encode($sharingInfos),
        ];

        try{
            $result = $this->service->execute3('/mas/sharing/reverse.do', $params);
            return ['code'=>0, 'msg'=>'分账撤销成功'];
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //添加分账接收方
    public function addReceiver($account, $name = null){
        return ['code'=>0, 'msg'=>'添加分账接收方成功'];
    }

    //删除分账接收方
    public function deleteReceiver($account){
        return ['code'=>0, 'msg'=>'删除分账接收方成功'];
    }
}