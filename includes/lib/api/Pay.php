<?php
namespace lib\api;

use Exception;

class Pay
{
    public static function submit()
    {
        global $conf, $DB, $clientip, $order, $userrow;
        @header('Content-Type: text/html; charset=UTF-8');
        if(isset($_GET['pid'])){
            $queryArr=$_GET;
        }elseif(isset($_POST['pid'])){
            $queryArr=$_POST;
        }else{
            exit('你还未配置支付接口商户！');
        }
        
        $pid=intval($queryArr['pid']);
        if(empty($pid))sysmsg('商户ID不能为空');
        $userrow=$DB->getRow("SELECT `uid`,`gid`,`key`,`money`,`mode`,`pay`,`cert`,`status`,`channelinfo`,`qq`,`ordername`,`keytype`,`publickey`,`deposit`,`pay_minmoney`,`pay_maxmoney` FROM `pre_user` WHERE `uid`='{$pid}' LIMIT 1");
        if(!$userrow)sysmsg('商户不存在！');
        if(isset($queryArr['__defend'])){
            $defend_result = $queryArr['__defend'];
            unset($queryArr['__defend']);
        }

        try{
            \lib\ApiHelper::api_verify($userrow, $queryArr);
        }catch(Exception $e){
            sysmsg($e->getMessage());
        }

        if($userrow['status']==0 || $userrow['pay']==0)sysmsg('商户已被封禁，无法支付！');

        if($userrow['pay']==2 && $conf['user_review']==1)sysmsg('商户未通过审核，无法支付！');

        $type=daddslashes($queryArr['type']);
        $out_trade_no=daddslashes($queryArr['out_trade_no']);
        $notify_url=htmlspecialchars(daddslashes($queryArr['notify_url']));
        $return_url=htmlspecialchars(daddslashes($queryArr['return_url']));
        $name=htmlspecialchars(daddslashes($queryArr['name']));
        $money=daddslashes($queryArr['money']);
        $sitename=urlencode(base64_encode(htmlspecialchars($queryArr['sitename'])));
        $param=isset($queryArr['param'])?htmlspecialchars(daddslashes($queryArr['param'])):null;
        $channel_id=isset($queryArr['channel_id'])?intval($queryArr['channel_id']):null;
        $cert_no=isset($queryArr['cert_no'])?htmlspecialchars($queryArr['cert_no']):null;
        $cert_name=isset($queryArr['cert_name'])?htmlspecialchars($queryArr['cert_name']):null;
        $min_age=isset($queryArr['min_age'])?daddslashes($queryArr['min_age']):null;


        if(empty($out_trade_no))sysmsg('订单号(out_trade_no)不能为空');
        if(empty($notify_url))sysmsg('通知地址(notify_url)不能为空');
        if(empty($return_url))sysmsg('回调地址(return_url)不能为空');
        if(empty($name))sysmsg('商品名称(name)不能为空');
        if(empty($money))sysmsg('金额(money)不能为空');
        if(!preg_match('/^[a-zA-Z0-9.\_\-|]+$/',$out_trade_no))sysmsg('订单号(out_trade_no)格式不正确');
        if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))sysmsg('金额不合法');
        if(!empty($cert_no) && !is_idcard($cert_no)) sysmsg('身份证号码格式不正确');
        if(!empty($min_age) && (!is_numeric($min_age) || $min_age < 0)) sysmsg('最低年龄格式不正确');
        $cert_info = null;
        if(!empty($cert_no) || !empty($cert_name) || !empty($min_age)){
            $cert_info = json_encode(['cert_no'=>$cert_no, 'cert_name'=>$cert_name, 'min_age'=>$min_age], JSON_UNESCAPED_UNICODE);
        }

        $groupconfig = getGroupConfig($userrow['gid']);
        $conf = array_merge($conf, $groupconfig);

        if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])sysmsg('最大支付金额是'.$conf['pay_maxmoney'].'元');
        if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])sysmsg('最小支付金额是'.$conf['pay_minmoney'].'元');
        if($userrow['pay_maxmoney']>0 && $money>$userrow['pay_maxmoney'])sysmsg('最大支付金额是'.$userrow['pay_maxmoney'].'元');
        if($userrow['pay_minmoney']>0 && $money<$userrow['pay_minmoney'])sysmsg('最小支付金额是'.$userrow['pay_minmoney'].'元');

        $domain=getdomain($notify_url);

        if($conf['cert_force']==1 && $userrow['cert']==0){
            sysmsg('当前商户未完成实名认证，无法收款');
        }
        if($conf['forceqq']==1 && empty($userrow['qq'])){
            sysmsg('当前商户未填写联系QQ，无法收款');
        }
        if($conf['pay_domain_forbid']==1){
            if(!$DB->getRow("SELECT * FROM pre_domain WHERE uid=:uid AND (domain=:domain OR domain=:domain2) AND status=1 LIMIT 1", [':uid'=>$pid, ':domain'=>get_host($notify_url), ':domain2'=>'*.'.get_main_host($notify_url)])){
                sysmsg('该域名不可发起支付，原因：域名没过白，请前往支付平台授权支付域名');
            }
        }
        if($conf['user_deposit']==1 && $conf['user_deposit_min'] > 0 && $conf['user_deposit_min'] > $userrow['deposit']){
            sysmsg('商户保证金不足，请前往支付平台充值保证金后再发起支付');
        }

        if(!empty($conf['blockname'])){
            $block_name = explode('|',$conf['blockname']);
            foreach($block_name as $rows){
                if(!empty($rows) && strpos($name,$rows)!==false){
                    $DB->exec("INSERT INTO `pre_risk` (`uid`, `url`, `content`, `date`) VALUES (:uid, :domain, :rows, NOW())", [':uid'=>$pid,':domain'=>$domain,':rows'=>$rows]);
                    sysmsg($conf['blockalert']?$conf['blockalert']:'该商品禁止出售');
                }
            }
        }

        $blackip = $DB->find('blacklist', '*', ['type'=>1, 'content'=>$clientip], null, 1);
        if($blackip)sysmsg('系统异常无法完成付款');

        if($conf['pay_daymax'] > 0){
            $daytotal = $DB->getColumn("select sum(money) from pre_order where `uid`=:uid and `date`='".date('Y-m-d')."' and status>0", ['uid'=>$pid]);
            if($daytotal + $money > $conf['pay_daymax']){
                sysmsg('当前商户今日收款已达到限额，无法发起支付');
            }
        }
        if($conf['pay_iplimit'] > 0 && (empty($conf['pay_iplimit_white']) || strpos($conf['pay_iplimit_white'], $clientip)===false)){
            $ipcount = $DB->getColumn("select count(*) from pre_order where `ip`=:ip and `date`='".date('Y-m-d')."' and status>0", ['ip'=>$clientip]);
            if($ipcount >= $conf['pay_iplimit']){
                sysmsg('你今天已无法再发起支付，请明天再试');
            }
        }

        if(checkPayVerifyOpen($pid)){
            $defend_key = getDefendKey($pid, $out_trade_no);
            if(empty($defend_result) || $defend_key!==substr($defend_result,10,32)){
                if($conf['pay_verify_type'] == 3) sysmsg('当前商户已超出订单并发量限制，请稍后再试');
                showPayVerifyPage($defend_key, $queryArr);
            }
        }

        if(strlen($name)>127)$name=mb_strcut($name, 0, 127, 'utf-8');

        $firstGetChannel = true;
        $oldorder = $DB->getRow("SELECT * FROM `pre_order` WHERE `uid`=:uid AND `out_trade_no`=:out_trade_no", [':uid'=>$pid, ':out_trade_no'=>$out_trade_no]);
        if($oldorder && time() - strtotime($oldorder['addtime']) < 864000){
            if($oldorder['status']>0){
                sysmsg('该订单('.$out_trade_no.')已完成支付，请勿重复发起支付');
            }
            if(round($oldorder['money'],2) != round($money,2) || $oldorder['name'] != $name || $oldorder['notify_url'] != $notify_url || $oldorder['return_url'] != $return_url || $oldorder['param'] != $param){
                sysmsg('该订单('.$out_trade_no.')支付参数有变化，请更换订单号重新发起支付');
            }
            $trade_no=$oldorder['trade_no'];
            $typeid = $DB->getColumn("SELECT id FROM pre_type WHERE name=:name LIMIT 1", [':name'=>$type]);
            if($oldorder['type'] > 0 && $oldorder['channel'] > 0 && $oldorder['realmoney'] > 0 && $oldorder['getmoney'] > 0 && $typeid == $oldorder['type']){ //订单已经获取过支付通道信息
                $firstGetChannel = false;
            }
        }else{
            $version = defined('API_INIT') ? 1 : 0;
            $trade_no=date("YmdHis").rand(11111,99999);
            if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`param`,`domain`,`ip`,`status`,`version`,`cert_info`) VALUES (:trade_no, :out_trade_no, :uid, NOW(), :name, :money, :notify_url, :return_url, :param, :domain, :clientip, 0, :version, :cert_info)", [':trade_no'=>$trade_no, ':out_trade_no'=>$out_trade_no, ':uid'=>$pid, ':name'=>$name, ':money'=>$money, ':notify_url'=>$notify_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param, ':version'=>$version, 'cert_info'=>$cert_info]))sysmsg('创建订单失败，请返回重试！');
        }


        if(empty($type)){
            echo "<script>window.location.replace('/cashier.php?trade_no={$trade_no}&sitename={$sitename}');</script>";
            exit;
        }

        // 获取订单支付方式ID、支付插件、支付通道、支付费率
        if($firstGetChannel){
            $submitData = \lib\Channel::submit($type, $userrow['uid'], $userrow['gid'], $money, $channel_id);
            if(!$submitData){
                echo "<script>window.location.replace('/cashier.php?trade_no={$trade_no}&sitename={$sitename}&other=1');</script>";
                exit;
            }
            if($userrow['mode']==1){ //订单加费模式
                $realmoney = round($money*(100+100-$submitData['rate'])/100,2);
                $getmoney = $money;
                if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
                    $feemoney = round($money*(100-$submitData['rate'])/100,2);
                    if($feemoney < round($conf['payfee_lessthan'], 2)){
                        $realmoney = round($money + $conf['payfee_mincost'], 2);
                    }
                }
            }else{
                $realmoney = $money;
                $getmoney = round($money*$submitData['rate']/100,2);
                if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
                    $feemoney = round($money*(100-$submitData['rate'])/100,2);
                    if($feemoney < round($conf['payfee_lessthan'], 2)){
                        $getmoney = round($money - $conf['payfee_mincost'], 2);
                        if($getmoney < 0) $getmoney = 0;
                    }
                }
            }
        }else{
            $submitData = \lib\Channel::info($oldorder['channel']);
            $submitData['typename'] = $type;
            $submitData['subchannel'] = $oldorder['subchannel'];
            $realmoney = $oldorder['realmoney'];
            $getmoney = $oldorder['getmoney'];
        }

        // 判断通道单笔支付限额
        if(!empty($submitData['paymin']) && $submitData['paymin']>0 && $money<$submitData['paymin']){
            sysmsg('<center>当前支付方式单笔最小限额为'.$submitData['paymin'].'元，请选择其他支付方式！</center>', '跳转提示');
        }
        if(!empty($submitData['paymax']) && $submitData['paymax']>0 && $money>$submitData['paymax']){
            sysmsg('<center>当前支付方式单笔最大限额为'.$submitData['paymax'].'元，请选择其他支付方式！</center>', '跳转提示');
        }
        if(!empty($submitData['timestart']) && !empty($submitData['timestop'])){
            $hour = date('H');
            if($submitData['timestart'] < $submitData['timestop']){
                if($hour < $submitData['timestart'] || $hour > $submitData['timestop']) {
                    sysmsg('<center>当前支付方式仅在每日'.$submitData['timestart'].':00-'.$submitData['timestop'].':00开放，请选择其他支付方式！</center>', '跳转提示');
                }
            }else{
                if($hour < $submitData['timestart'] && $hour > $submitData['timestop']) {
                    sysmsg('<center>当前支付方式仅在每日'.$submitData['timestart'].':00-'.$submitData['timestop'].':00开放，请选择其他支付方式！</center>', '跳转提示');
                }
            }
        }
        // 商户直清模式判断商户余额
        if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
            sysmsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
        }

        if($firstGetChannel){
            // 随机增减金额
            if(!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = round($realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2)), 2);

            $resCount = $DB->update('order', ['type'=>$submitData['typeid'], 'channel'=>$submitData['channel'], 'subchannel'=>$submitData['subchannel'], 'realmoney'=>$realmoney, 'getmoney'=>$getmoney], ['trade_no'=>$trade_no, 'channel'=>0]);
            if($resCount == 0) sysmsg('更新订单失败，请返回重试！');
        }


        $order['trade_no'] = $trade_no;
        $order['out_trade_no'] = $out_trade_no;
        $order['uid'] = $pid;
        $order['addtime'] = date('Y-m-d H:i:s');
        $order['name'] = $name;
        $order['realmoney'] = sprintf("%.2f", $realmoney);
        $order['type'] = $submitData['typeid'];
        $order['channel'] = $submitData['channel'];
        $order['subchannel'] = $submitData['subchannel'];
        $order['typename'] = $submitData['typename'];
        $order['plugin'] = $submitData['plugin'];
        $order['profits'] = \lib\Payment::updateOrderProfits($order, $submitData['plugin']);
        $order['cert_no'] = $cert_no;
        $order['cert_name'] = $cert_name;
        $order['min_age'] = $min_age;

        try{
            $result = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no);
            $result['submit'] = true;
            \lib\Payment::echoDefault($result);
        }catch(Exception $e){
            sysmsg($e->getMessage());
        }
    }


    public static function create(){
        global $conf, $DB, $clientip, $order, $userrow, $method, $device, $mdevice, $siteurl;
        if(isset($_POST['pid'])){
            $queryArr=$_POST;
        }else{
            echojsonmsg('未传入任何参数', -4);
        }

        $pid=intval($queryArr['pid']);
        if(empty($pid))echojsonmsg('商户ID不能为空');
        $userrow=$DB->getRow("SELECT `uid`,`gid`,`key`,`money`,`mode`,`pay`,`cert`,`status`,`channelinfo`,`qq`,`ordername`,`keytype`,`publickey`,`deposit`,`pay_minmoney`,`pay_maxmoney` FROM `pre_user` WHERE `uid`='{$pid}' LIMIT 1");
        if(!$userrow)echojsonmsg('商户不存在！');
        
        try{
            \lib\ApiHelper::api_verify($userrow, $queryArr);
        }catch(Exception $e){
            echojsonmsg($e->getMessage(), -3);
        }

        if($userrow['status']==0 || $userrow['pay']==0)echojsonmsg('商户已被封禁，无法支付！');

        if($userrow['pay']==2 && $conf['user_review']==1)echojsonmsg('商户未通过审核，无法支付！');

        $type=daddslashes($queryArr['type']);
        $out_trade_no=daddslashes($queryArr['out_trade_no']);
        $notify_url=htmlspecialchars(daddslashes($queryArr['notify_url']));
        $return_url=htmlspecialchars(daddslashes($queryArr['return_url']));
        $name=htmlspecialchars(daddslashes($queryArr['name']));
        $money=daddslashes($queryArr['money']);
        $clientip=daddslashes($queryArr['clientip']);
        $device=$queryArr['device'];
        if(empty($device))$device = 'pc';
        $sub_openid=$queryArr['sub_openid'];
        $sub_appid=$queryArr['sub_appid'];
        $auth_code=$queryArr['auth_code'];
        $sitename=urlencode(base64_encode(htmlspecialchars($queryArr['sitename'])));
        $param=isset($queryArr['param'])?htmlspecialchars(daddslashes($queryArr['param'])):null;
        $channel_id=isset($queryArr['channel_id'])?intval($queryArr['channel_id']):null;
        $method=$queryArr['method']; //web/jump/jsapi/scan
        if($device == 'jump')$method = 'jump';
        $mdevice='';
        if ($device=='qq'||$device=='wechat'||$device=='alipay'||$device=='app') {
            $mdevice=$device;
            $device='mobile';
        }
        $cert_no=isset($queryArr['cert_no'])?htmlspecialchars($queryArr['cert_no']):null;
        $cert_name=isset($queryArr['cert_name'])?htmlspecialchars($queryArr['cert_name']):null;
        $min_age=isset($queryArr['min_age'])?daddslashes($queryArr['min_age']):null;

        if(empty($out_trade_no))echojsonmsg('订单号(out_trade_no)不能为空');
        if(empty($notify_url))echojsonmsg('通知地址(notify_url)不能为空');
        if(empty($name))echojsonmsg('商品名称(name)不能为空');
        if(empty($money))echojsonmsg('金额(money)不能为空');
        if(empty($type) && $method != 'scan')echojsonmsg('支付方式(type)不能为空');
        if(empty($clientip))echojsonmsg('用户IP地址(clientip)不能为空');
        if(!preg_match('/^[a-zA-Z0-9.\_\-|]+$/',$out_trade_no))echojsonmsg('订单号(out_trade_no)格式不正确');
        if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))echojsonmsg('金额不合法');
        if(!filter_var($clientip, FILTER_VALIDATE_IP))echojsonmsg('用户IP地址不合法');
        if($method == 'jsapi' && empty($sub_openid))echojsonmsg('jsapi支付时参数(sub_openid)不能为空');
        //if($method == 'jsapi' && $type=='wxpay' && empty($sub_appid))echojsonmsg('jsapi支付时参数(sub_appid)不能为空');
        if($method == 'scan' && empty($auth_code))echojsonmsg('付款码支付时授权码(auth_code)不能为空');
        if($method == 'scan' && empty($type)){
            $type = getScanPayType($auth_code);
            if($type == 'unknown') echojsonmsg('未知的付款码类型');
        }
        if(!empty($cert_no) && !is_idcard($cert_no)) echojsonmsg('身份证号码格式不正确');
        if(!empty($min_age) && (!is_numeric($min_age) || $min_age < 0)) echojsonmsg('最低年龄格式不正确');
        $cert_info = null;
        if(!empty($cert_no) || !empty($cert_name) || !empty($min_age)){
            $cert_info = json_encode(['cert_no'=>$cert_no, 'cert_name'=>$cert_name, 'min_age'=>$min_age], JSON_UNESCAPED_UNICODE);
        }

        $groupconfig = getGroupConfig($userrow['gid']);
        $conf = array_merge($conf, $groupconfig);

        if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])echojsonmsg('最大支付金额是'.$conf['pay_maxmoney'].'元');
        if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])echojsonmsg('最小支付金额是'.$conf['pay_minmoney'].'元');
        if($userrow['pay_maxmoney']>0 && $money>$userrow['pay_maxmoney'])echojsonmsg('最大支付金额是'.$userrow['pay_maxmoney'].'元');
        if($userrow['pay_minmoney']>0 && $money<$userrow['pay_minmoney'])echojsonmsg('最小支付金额是'.$userrow['pay_minmoney'].'元');

        $domain=getdomain($notify_url);

        if($conf['cert_force']==1 && $userrow['cert']==0){
            echojsonmsg('当前商户未完成实名认证，无法收款');
        }
        if($conf['forceqq']==1 && empty($userrow['qq'])){
            echojsonmsg('当前商户未填写联系QQ，无法收款');
        }
        if($conf['pay_domain_forbid']==1){
            if(!$DB->getRow("SELECT * FROM pre_domain WHERE uid=:uid AND (domain=:domain OR domain=:domain2) AND status=1 LIMIT 1", [':uid'=>$pid, ':domain'=>get_host($notify_url), ':domain2'=>'*.'.get_main_host($notify_url)])){
                echojsonmsg('该域名不可发起支付，原因：域名没过白，请前往支付平台授权支付域名');
            }
        }
        if($conf['user_deposit']==1 && $conf['user_deposit_min'] > 0 && $conf['user_deposit_min'] > $userrow['deposit']){
            echojsonmsg('商户保证金不足，请前往支付平台充值保证金后再发起支付');
        }

        if(!empty($conf['blockname'])){
            $block_name = explode('|',$conf['blockname']);
            foreach($block_name as $rows){
                if(!empty($rows) && strpos($name,$rows)!==false){
                    $DB->exec("INSERT INTO `pre_risk` (`uid`, `url`, `content`, `date`) VALUES (:uid, :domain, :rows, NOW())", [':uid'=>$pid,':domain'=>$domain,':rows'=>$rows]);
                    echojsonmsg($conf['blockalert']?$conf['blockalert']:'该商品禁止出售');
                }
            }
        }

        $blackip = $DB->find('blacklist', '*', ['type'=>1, 'content'=>$clientip], null, 1);
        if($blackip)echojsonmsg('系统异常无法完成付款');

        if($conf['pay_daymax'] > 0){
            $daytotal = $DB->getColumn("select sum(money) from pre_order where `uid`=:uid and `date`='".date('Y-m-d')."' and status>0", ['uid'=>$pid]);
            if($daytotal + $money > $conf['pay_daymax']){
                echojsonmsg('当前商户今日收款已达到限额，无法发起支付');
            }
        }
        if($conf['pay_iplimit'] > 0 && (empty($conf['pay_iplimit_white']) || strpos($conf['pay_iplimit_white'], $clientip)===false)){
            $ipcount = $DB->getColumn("select count(*) from pre_order where `ip`=:ip and `date`='".date('Y-m-d')."' and status>0", ['ip'=>$clientip]);
            if($ipcount >= $conf['pay_iplimit']){
                echojsonmsg('你今天已无法再发起支付，请明天再试');
            }
        }

        if(checkPayVerifyOpen($pid)){
            if($conf['pay_verify_type'] == 3) sysmsg('当前商户已超出订单并发量限制，请稍后再试');
            echojsonmsg('本次支付需要安全验证，请使用跳转支付接口发起支付');
        }

        if(strlen($name)>127)$name=mb_strcut($name, 0, 127, 'utf-8');

        $firstGetChannel = true;
        $oldorder = $DB->getRow("SELECT * FROM `pre_order` WHERE `uid`=:uid AND `out_trade_no`=:out_trade_no", [':uid'=>$pid, ':out_trade_no'=>$out_trade_no]);
        if($oldorder && time() - strtotime($oldorder['addtime']) < 864000){
            if($oldorder['status']>0){
                echojsonmsg('该订单('.$out_trade_no.')已完成支付，请勿重复发起支付');
            }
            if(round($oldorder['money'],2) != round($money,2) || $oldorder['name'] != $name || $oldorder['notify_url'] != $notify_url || $oldorder['return_url'] != $return_url || $oldorder['param'] != $param){
                echojsonmsg('该订单('.$out_trade_no.')支付参数有变化，请更换订单号重新发起支付');
            }
            $trade_no=$oldorder['trade_no'];
            $typeid = $DB->getColumn("SELECT id FROM pre_type WHERE name=:name LIMIT 1", [':name'=>$type]);
            if($oldorder['type'] > 0 && $oldorder['channel'] > 0 && $oldorder['realmoney'] > 0 && $oldorder['getmoney'] > 0 && $typeid == $oldorder['type']){ //订单已经获取过支付通道信息
                $firstGetChannel = false;
            }
        }else{
            $version = defined('API_INIT') ? 1 : 0;
            $trade_no=date("YmdHis").rand(11111,99999);
            if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`param`,`domain`,`ip`,`buyer`,`status`,`version`,`cert_info`) VALUES (:trade_no, :out_trade_no, :uid, NOW(), :name, :money, :notify_url, :return_url, :param, :domain, :clientip, :buyer, 0, :version, :cert_info)", [':trade_no'=>$trade_no, ':out_trade_no'=>$out_trade_no, ':uid'=>$pid, ':name'=>$name, ':money'=>$money, ':notify_url'=>$notify_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':buyer'=>$sub_openid, ':param'=>$param, ':version'=>$version, ':cert_info'=>$cert_info]))echojsonmsg('创建订单失败，请返回重试！');
        }

        if(empty($type)){
            define("TRADE_NO", $trade_no);
            \lib\Payment::echoJson(['type'=>'jump','url'=>$siteurl.'cashier.php?trade_no='.$trade_no.'&sitename='.$sitename]);
        }

        // 获取订单支付方式ID、支付插件、支付通道、支付费率
        if($firstGetChannel){
            $submitData = \lib\Channel::submit($type, $userrow['uid'], $userrow['gid'], $money, $channel_id);
            if(!$submitData){
                define("TRADE_NO", $trade_no);
                \lib\Payment::echoJson(['type'=>'jump','url'=>$siteurl.'cashier.php?trade_no='.$trade_no.'&sitename='.$sitename.'&other=1']);
            }
            if($userrow['mode']==1){ //订单加费模式
                $realmoney = round($money*(100+100-$submitData['rate'])/100,2);
                $getmoney = $money;
                if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
                    $feemoney = round($money*(100-$submitData['rate'])/100,2);
                    if($feemoney < round($conf['payfee_lessthan'], 2)){
                        $realmoney = round($money + $conf['payfee_mincost'], 2);
                    }
                }
            }else{
                $realmoney = $money;
                $getmoney = round($money*$submitData['rate']/100,2);
                if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
                    $feemoney = round($money*(100-$submitData['rate'])/100,2);
                    if($feemoney < round($conf['payfee_lessthan'], 2)){
                        $getmoney = round($money - $conf['payfee_mincost'], 2);
                        if($getmoney < 0) $getmoney = 0;
                    }
                }
            }
        }else{
            $submitData = \lib\Channel::info($oldorder['channel']);
            $submitData['typename'] = $type;
            $submitData['subchannel'] = $oldorder['subchannel'];
            $realmoney = $oldorder['realmoney'];
            $getmoney = $oldorder['getmoney'];
        }

        // 判断通道单笔支付限额
        if(!empty($submitData['paymin']) && $submitData['paymin']>0 && $money<$submitData['paymin']){
            echojsonmsg('当前支付方式单笔最小限额为'.$submitData['paymin'].'元，请选择其他支付方式！');
        }
        if(!empty($submitData['paymax']) && $submitData['paymax']>0 && $money>$submitData['paymax']){
            echojsonmsg('当前支付方式单笔最大限额为'.$submitData['paymax'].'元，请选择其他支付方式！');
        }
        if(!empty($submitData['timestart']) && !empty($submitData['timestop'])){
            $hour = date('H');
            if($submitData['timestart'] < $submitData['timestop']){
                if($hour < $submitData['timestart'] || $hour > $submitData['timestop']) {
                    echojsonmsg('当前支付方式仅在每日'.$submitData['timestart'].':00-'.$submitData['timestop'].':00开放，请选择其他支付方式！');
                }
            }else{
                if($hour < $submitData['timestart'] && $hour > $submitData['timestop']) {
                    echojsonmsg('当前支付方式仅在每日'.$submitData['timestart'].':00-'.$submitData['timestop'].':00开放，请选择其他支付方式！');
                }
            }
        }
        // 商户直清模式判断商户余额
        if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
            echojsonmsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
        }

        if($firstGetChannel){
            // 随机增减金额
            if(!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = $realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2));

            $resCount = $DB->update('order', ['type'=>$submitData['typeid'], 'channel'=>$submitData['channel'], 'subchannel'=>$submitData['subchannel'], 'realmoney'=>$realmoney, 'getmoney'=>$getmoney], ['trade_no'=>$trade_no, 'channel'=>0]);
            if($resCount == 0) echojsonmsg('更新订单失败，请返回重试！');
        }

        $order['trade_no'] = $trade_no;
        $order['out_trade_no'] = $out_trade_no;
        $order['uid'] = $pid;
        $order['addtime'] = date('Y-m-d H:i:s');
        $order['name'] = $name;
        $order['realmoney'] = sprintf("%.2f", $realmoney);
        $order['type'] = $submitData['typeid'];
        $order['channel'] = $submitData['channel'];
        $order['subchannel'] = $submitData['subchannel'];
        $order['typename'] = $submitData['typename'];
        $order['plugin'] = $submitData['plugin'];
        $order['profits'] = \lib\Payment::updateOrderProfits($order, $submitData['plugin']);
        $order['sub_openid'] = $sub_openid;
        $order['sub_appid'] = $sub_appid;
        $order['auth_code'] = $auth_code;
        $order['cert_no'] = $cert_no;
        $order['cert_name'] = $cert_name;
        $order['min_age'] = $min_age;

        if($method == 'jump'){
            define("TRADE_NO", $trade_no);
            \lib\Payment::echoJson(['type'=>'jump','url'=>$siteurl.'pay/submit/'.$trade_no.'/']);
        }

        try{
            $result = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no, true);
            \lib\Payment::echoJson($result);
        }catch(Exception $e){
            echojsonmsg($e->getMessage());
        }

    }

    public static function query(){
        global $conf, $DB, $queryArr;

        $pid=intval($queryArr['pid']);
        
        if(!empty($queryArr['trade_no'])){
            $trade_no=daddslashes($queryArr['trade_no']);
            $order=$DB->getRow("SELECT * FROM pre_order WHERE uid='{$pid}' and trade_no='{$trade_no}' limit 1");
        }elseif(!empty($queryArr['out_trade_no'])){
            $out_trade_no=daddslashes($queryArr['out_trade_no']);
            $order=$DB->getRow("SELECT * FROM pre_order WHERE uid='{$pid}' and out_trade_no='{$out_trade_no}' limit 1");
        }else{
            throw new Exception('订单号不能为空');
        }
        if($order){
            $type=$DB->getColumn("SELECT name FROM pre_type WHERE id='{$order['type']}' LIMIT 1");
            $result = ['code'=>0, 'trade_no'=>$order['trade_no'],'out_trade_no'=>$order['out_trade_no'],'api_trade_no'=>$order['api_trade_no'],'bill_trade_no'=>$order['bill_trade_no'],'type'=>$type,'pid'=>$order['uid'],'addtime'=>$order['addtime'],'endtime'=>$order['endtime'],'name'=>$order['name'],'money'=>$order['money'],'param'=>$order['param'],'buyer'=>$order['buyer'],'clientip'=>$order['ip'],'status'=>$order['status'],'refundmoney'=>$order['refundmoney']];
            $result = array_filter($result, function($a){return !isEmpty($a);});
            return $result;
        }else{
            throw new Exception('订单号不存在');
        }
    }

    public static function refund(){
        global $conf, $DB, $userrow, $queryArr;

        $pid=intval($queryArr['pid']);
        if(!$conf['user_refund']) throw new Exception('管理员未开启商户后台自助退款');

        $money = trim($queryArr['money']);
	    if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))throw new Exception('金额输入错误');
        
        if(!empty($queryArr['trade_no'])){
			$trade_no=daddslashes($queryArr['trade_no']);
		}elseif(!empty($queryArr['out_trade_no'])){
			$out_trade_no=daddslashes($queryArr['out_trade_no']);
            $trade_no = $DB->findColumn('order', 'trade_no', ['out_trade_no'=>$out_trade_no, 'uid'=>$pid]);
            if(!$trade_no) throw new Exception('当前订单不存在！');;
		}else{
            throw new Exception('订单号不能为空');
		}

        $refund_no = date("YmdHis").rand(11111,99999);
        if(!empty($queryArr['out_refund_no']) && strlen($queryArr['out_refund_no']) > 5){ //判断商户是否重复提交退款
            $out_refund_no = daddslashes($queryArr['out_refund_no']);
            $refund_order = $DB->find('refundorder', '*', ['out_refund_no'=>$out_refund_no, 'uid'=>$pid]);
            if($refund_order && $refund_order['status'] == 1){
                $result = ['code'=>0, 'refund_no'=>$refund_order['refund_no'], 'out_refund_no'=>$refund_order['out_refund_no'], 'trade_no'=>$refund_order['trade_no'], 'uid'=>$refund_order['uid'], 'money'=>$refund_order['money'], 'reducemoney'=>$refund_order['reducemoney'], 'msg'=>'已存在相同退款单号！退款金额¥'.$refund_order['money']];
                return $result;
            }elseif($refund_order && $refund_order['status'] == 0){
                $refund_no = $refund_order['refund_no'];
            }
        }
        
        $result = \lib\Order::refund($refund_no, $trade_no, $money, 1, $pid, $out_refund_no);
        if($result['code'] == 0){
            $result['msg'] = '退款成功！退款金额¥'.$result['money'];
        }
        return $result;
    }

    public static function refundquery(){
        global $conf, $DB, $queryArr;

        $pid=intval($queryArr['pid']);
        if(!$conf['user_refund']) throw new Exception('管理员未开启商户后台自助退款');

        if(!empty($queryArr['refund_no'])){
			$refund_no=daddslashes($queryArr['refund_no']);
            $refund_order = $DB->find('refundorder', '*', ['refund_no'=>$refund_no, 'uid'=>$pid]);
		}elseif(!empty($queryArr['out_refund_no'])){
			$out_refund_no=daddslashes($queryArr['out_refund_no']);
            $refund_order = $DB->find('refundorder', '*', ['out_refund_no'=>$out_refund_no, 'uid'=>$pid]);
		}else{
            throw new Exception('商户退款单号不能为空');
		}

        if(!$refund_order)throw new Exception('退款记录不存在');

        $out_trade_no = $DB->findColumn('order', 'out_trade_no', ['trade_no'=>$refund_order['trade_no']]);

        $result = ['code'=>0, 'refund_no'=>$refund_order['refund_no'], 'out_refund_no'=>$refund_order['out_refund_no'], 'trade_no'=>$refund_order['trade_no'], 'out_trade_no'=>$out_trade_no, 'uid'=>$refund_order['uid'], 'money'=>$refund_order['money'], 'reducemoney'=>$refund_order['reducemoney'], 'status'=>$refund_order['status'], 'addtime'=>$refund_order['addtime'], 'endtime'=>$refund_order['endtime']];

        return $result;
    }

    public static function close(){
        global $conf, $DB, $userrow, $queryArr;

        $pid=intval($queryArr['pid']);

        if(!empty($queryArr['trade_no'])){
			$trade_no=daddslashes($queryArr['trade_no']);
		}elseif(!empty($queryArr['out_trade_no'])){
			$out_trade_no=daddslashes($queryArr['out_trade_no']);
            $trade_no = $DB->findColumn('order', 'trade_no', ['out_trade_no'=>$out_trade_no, 'uid'=>$pid]);
            if(!$trade_no) throw new Exception('当前订单不存在！');;
		}else{
            throw new Exception('订单号不能为空');
		}

        $result = \lib\Order::close($trade_no, $pid);
        if($result['code'] == 0){
            $result['msg'] = '订单关闭成功';
        }
        return $result;
    }
}