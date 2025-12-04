<?php

namespace lib\ProfitSharing;

use Exception;

class CommUtil
{
    public static $plugins = ['alipay','alipaysl','alipayd','wxpayn','wxpaynp','yeepay','yseqt','chinaums','dinpay','adapay','duolabao','allinpay','huifu','haipay','heepay','kunpeng','kuaiqian','kuaiqianbank','yinyingtong','entpay','shengpay'];
    public static $no_order_plugins = ['chinaums','dinpay','duolabao','allinpay','huifu','haipay'];

    /**
     * @param array $channel
     * @return IProfitSharing|false
     */
    public static function getModel($channel){
        if($channel['plugin'] == 'alipay' || $channel['plugin'] == 'alipaysl' || $channel['plugin'] == 'alipayd'){
            return new Alipay($channel);
        }elseif($channel['plugin'] == 'wxpayn' || $channel['plugin'] == 'wxpaynp'){
            return new Wxpay($channel);
        }elseif($channel['plugin'] == 'kuaiqian' || $channel['plugin'] == 'kuaiqianbank'){
            return new Kuaiqian($channel);
        }elseif(in_array($channel['plugin'], self::$plugins)){
            $classname = '\\lib\\ProfitSharing\\'.ucfirst($channel['plugin']);
            if(class_exists($classname)){
                return new $classname($channel);
            }else{
                return new Common($channel);
            }
        }
        return false;
    }

    public static function getReceiver($id){
        global $DB;
        $result = $DB->find('psreceiver', '*', ['id'=>$id]);
        if(!$result) return $result;
        $result['info'] = !empty($result['info']) ? json_decode($result['info'], true) : [['account'=>$result['account'], 'name'=>$result['name'], 'rate'=>$result['rate']]];
        return $result;
    }

    public static function getOrder($trade_no){
        global $DB;
        $result = $DB->getRow("SELECT A.*,B.channel,B.account,B.name,B.rate FROM pre_psorder A LEFT JOIN pre_psreceiver B ON A.rid=B.id WHERE A.trade_no=:trade_no", [':trade_no'=>$trade_no]);
        if(!$result) return $result;
        $result['rdata'] = json_decode($result['rdata'], true);
        return $result;
    }

    //订单分账定时任务
    public static function task(){
        global $DB, $conf;
        $limit = 10; //每次查询分账的订单数量
        $list = $DB->getAll("SELECT A.*,B.channel,B.account,B.name,B.rate,B.info,B.uid psuid,C.uid,C.subchannel,C.realmoney ordermoney FROM pre_psorder A INNER JOIN pre_psreceiver B ON B.id=A.rid LEFT JOIN pre_order C ON C.trade_no=A.trade_no WHERE A.status=1 ORDER BY A.id ASC LIMIT {$limit}");
        foreach($list as $srow){
            self::process_item($srow);
        }
    
        $limit = 10; //每次提交分账的订单数量
        $list = $DB->getAll("SELECT A.*,B.channel,B.account,B.name,B.rate,B.info,B.uid psuid,C.uid,C.subchannel,C.realmoney ordermoney FROM pre_psorder A INNER JOIN pre_psreceiver B ON B.id=A.rid LEFT JOIN pre_order C ON C.trade_no=A.trade_no WHERE A.status=0 AND (A.addtime<=DATE_SUB(NOW(), INTERVAL 60 SECOND) AND A.delay=0 OR A.addtime<=DATE_SUB(NOW(), INTERVAL 24 HOUR) AND A.delay=1) ORDER BY A.id ASC LIMIT {$limit}");
        foreach($list as $srow){
            self::process_item($srow);
        }

        if($conf['profits_failretry'] == 1){
            $limit = 10; //每次提交分账的订单数量
            $list = $DB->getAll("SELECT A.*,B.channel,B.account,B.name,B.rate,B.info,B.uid psuid,C.uid,C.subchannel,C.realmoney ordermoney FROM pre_psorder A INNER JOIN pre_psreceiver B ON B.id=A.rid LEFT JOIN pre_order C ON C.trade_no=A.trade_no WHERE A.status=3 AND (A.addtime<=DATE_SUB(NOW(), INTERVAL 24 HOUR) AND A.delay=0 OR A.addtime<=DATE_SUB(NOW(), INTERVAL 48 HOUR) AND A.delay=1) AND A.retry=0 AND A.addtime>DATE_SUB(NOW(), INTERVAL 3 DAY) ORDER BY A.id ASC LIMIT {$limit}");
            foreach($list as $srow){
                self::process_item($srow);
            }
        }
    }

    //处理一个订单分账任务
    public static function process_item($row){
        global $DB;
        $id = $row['id'];
        $channel = $row['subchannel'] > 0 ? \lib\Channel::getSub($row['subchannel']) : \lib\Channel::get($row['channel'], $row['uid']?$DB->findColumn('user', 'channelinfo', ['uid'=>$row['uid']]):null);
        if(!$channel) return;
        $model = self::getModel($channel);
        // status:0-待分账,1-已提交,2-成功,3-失败
        if($row['status']==0 || $row['status']==3){
            if($row['money'] == 0){
                $DB->update('psorder', ['status'=>3,'result'=>'分账金额为0'], ['id'=>$id]);
                echo $row['trade_no'].' 分账金额为0<br/>';
                return;
            }
            $row['info'] = !empty($row['info']) ? json_decode($row['info'], true) : [['account'=>$row['account'], 'name'=>$row['name'], 'rate'=>$row['rate']]];
            if(!empty($row['sub_trade_no'])){
                $row['ordermoney'] = $DB->findColumn('suborder', 'money', ['sub_trade_no'=>$row['sub_trade_no']]);
                $row['trade_no'] = $row['sub_trade_no'];
            }
            $result = $model->submit($row['trade_no'], $row['api_trade_no'], $row['ordermoney'], $row['info']);
            if($result['code'] == 0){
                $DB->update('psorder', ['status'=>1,'settle_no'=>$result['settle_no']], ['id'=>$id]);
            }elseif($result['code'] == 1){
                $DB->update('psorder', ['status'=>2,'settle_no'=>$result['settle_no']], ['id'=>$id]);
                if(!empty($row['psuid']) && $channel['mode']==0){
                    changeUserMoney($row['psuid'], $row['money'], false, '订单分账', $row['trade_no']);
                }
            }elseif($result['code'] == -1){
                $DB->update('psorder', ['status'=>3,'result'=>$result['msg']], ['id'=>$id]);
                if($row['status']==3) $DB->update('psorder', ['retry'=>1], ['id'=>$id]);
            }
            if(isset($result['rdata'])){
                $DB->update('psorder', ['money'=>$result['money'], 'rdata'=>json_encode($result['rdata'])], ['id'=>$id]);
            }
            echo $row['trade_no'].' '.$result['msg'].'<br/>';
        }elseif($row['status']==1){
            if(!empty($row['sub_trade_no'])) $row['trade_no'] = $row['sub_trade_no'];
            $result = $model->query($row['trade_no'], $row['api_trade_no'], $row['settle_no']);
            if($result['code']==0){
                if($result['status']==1){
                    $DB->update('psorder', ['status'=>2], ['id'=>$id]);
                    if(!empty($row['psuid']) && $channel['mode']==0){
                        changeUserMoney($row['psuid'], $row['money'], false, '订单分账', $row['trade_no']);
                    }
                    $result = '分账成功';
                }elseif($result['status']==2){
                    $DB->update('psorder', ['status'=>3,'result'=>$result['reason']], ['id'=>$id]);
                    $result = '分账失败:'.$result['reason'];
                }else{
                    $result = '正在分账';
                }
                echo $row['trade_no'].' '.$result.'<br/>';
            }else{
                echo $row['trade_no'].' 查询失败:'.$result['msg'].'<br/>';
            }
        }
    }

    //分账结果异步回调
    public static function processNotify($trade_no, $status, $errmsg = null, $settle_no = null){
        global $DB;
        $psorder = $DB->find('psorder', '*', ['trade_no'=>$trade_no]);
        if($psorder){
            if($psorder['status'] != $status){
                $data = ['status'=>$status];
                if($errmsg) $data['result'] = $errmsg;
                if($settle_no) $data['settle_no'] = $settle_no;
                $DB->update('psorder', $data, ['id'=>$psorder['id']]);
            }
        }
    }
}