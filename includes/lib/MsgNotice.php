<?php
namespace lib;

use Exception;

class MsgNotice
{
    public static function send($scene, $uid, $param){
        global $DB, $conf;
        $scene_all = ['complain', 'mchrisk'];
        if($uid == 0){
            if(in_array($scene, $scene_all)){
                $switch = self::getMessageSwitch($scene.'_all');
            }else{
                $switch = self::getMessageSwitch($scene);
            }
            if($switch == 1){
                $receiver = $conf['mail_recv']?$conf['mail_recv']:$conf['mail_name'];
                return self::send_mail_msg($scene, $receiver, $param);
            }elseif($switch == 2){
                return self::send_robot_msg($scene, $conf['msgrobot_url'], $param, true);
            }
        }else{
            $userrow = $DB->find('user', 'phone,email,wx_uid,codename,msgconfig,voice_order,voice_devid,print_order,print_config', ['uid'=>$uid]);
            $userrow['msgconfig'] = unserialize($userrow['msgconfig']);

            if($conf['voicenotice'] == 1 && $scene == 'order' && $userrow['voice_order'] == 1 && $param['name']!='付款码收款'){
                self::send_voice($userrow['voice_devid'], $param['type'], $param['money']);
            }
            if($conf['orderprint'] == 1 && $scene == 'order' && ($userrow['print_order'] == 2 || $userrow['print_order'] == 1 && $param['tid']==3)){
                $param['codename'] = $userrow['codename'];
                $print_config = unserialize($userrow['print_config']);
                (new Printer($conf['print_appid'], $conf['print_appsecret']))->print($print_config['devid'], $param, $print_config['count'], $print_config['voice']==1, true);
            }

            if($scene == 'order' && $userrow['msgconfig']['order_money']>0 && $param['money']<$userrow['msgconfig']['order_money']) return false;
            if($scene == 'balance') $param['msgmoney'] = $userrow['msgconfig']['balance_money'];

            if($userrow['msgconfig'][$scene] == 1 && !empty($userrow['wx_uid'])){
                self::send_wechat_tplmsg($scene, $userrow['wx_uid'], $param);
            }elseif($userrow['msgconfig'][$scene] == 2 && !empty($userrow['email']) && self::getMessageSwitch($scene) == 1){
                self::send_mail_msg($scene, $userrow['email'], $param);
            }elseif($userrow['msgconfig'][$scene] == 3 && !empty($userrow['phone'])){
                if($scene == 'balance'){
                    $tpl_code = $conf['sms_tpl_balance'];
                    $tpl_param = ['code'=>$param['msgmoney']];
                }elseif($scene == 'complain'){
                    $tpl_code = $conf['sms_tpl_complain'];
                    $tpl_param = ['code'=>$param['trade_no']];
                }
                if(!empty($tpl_code)){
                    send_sms_common($userrow['phone'], $tpl_code, $tpl_param);
                }
            }elseif($userrow['msgconfig'][$scene] == 4 && !empty($userrow['msgconfig']['msgrobot_url']) && self::getMessageSwitch($scene) == 1){
                self::send_robot_msg($scene, $userrow['msgconfig']['msgrobot_url'], $param);
            }
            if($scene == 'group' && $conf['msgconfig_group'] == 1 && !empty($userrow['email'])){
                self::send_mail_msg($scene, $userrow['email'], $param);
            }
            if($scene == 'group' && !empty($conf['sms_tpl_group']) && !empty($userrow['phone'])){
                $tpl_param = ['code'=>$param['group']];
                send_sms_common($userrow['phone'], $conf['sms_tpl_group'], $tpl_param);
            }

            if(in_array($scene, $scene_all)){
                $switch = self::getMessageSwitch($scene.'_all');
                if($switch == 1){
                    $receiver = $conf['mail_recv']?$conf['mail_recv']:$conf['mail_name'];
                    self::send_mail_msg($scene, $receiver, $param);
                }elseif($switch == 2){
                    return self::send_robot_msg($scene, $conf['msgrobot_url'], $param, true);
                }elseif($switch == 3 && !empty($conf['msgrobot_phone']) && !empty($conf['sms_tpl_complain'])){
                    $tpl_code = $conf['sms_tpl_complain'];
                    $tpl_param = ['code'=>$param['trade_no']];
                    return send_sms_common($conf['msgrobot_phone'], $tpl_code, $tpl_param);
                }
            }
        }
    }

    /**
     * https://acnlfexk4r1d.feishu.cn/wiki/SwDcwFwR4iTc5jk6BPTctAWXnDc
     */
    public static function send_voice($devid, $type, $money){
        global $conf, $CACHE;
        $url = 'http://iot.solomo-info.com:9306/admin/common/msgpush';
        $param = [
            'agent_id' => $conf['voice_username'],
            'agent_secret' => $conf['voice_apikey'],
            'sbx_id' => $devid,
            'msg' => $type.'收款'.$money.'元',
            'debug' => 0,
        ];
        $data = get_curl($url, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);
        $result = json_decode($data, true);
        if(isset($result['result']) && $result['result'] == 'success'){
            return true;
        }else{
            $errmsg = '发送云喇叭消息失败，'.$result['msg'];
            $CACHE->save('voiceerrmsg', ['errmsg'=>$errmsg, 'time'=>date('Y-m-d H:i:s')], 86400);
            return false;
        }
    }

    public static function send_wechat_tplmsg($scene, $openid, $param){
        global $conf, $siteurl, $CACHE;
        $wid = $conf['login_wx'];
        if($scene == 'order'){
            $template_id = $conf['wxnotice_tpl_order'];
            if(strlen($param['out_trade_no']) > 32) $param['out_trade_no'] = substr($param['out_trade_no'], 0, 32);
            if(mb_strlen($param['name']) > 20) $param['name'] = mb_substr($param['name'], 0, 20);
            $data = [];
            if($conf['wxnotice_tpl_order_no']) $data[$conf['wxnotice_tpl_order_no']] = ['value'=>$param['trade_no']];
            if($conf['wxnotice_tpl_order_name']) $data[$conf['wxnotice_tpl_order_name']] = ['value'=>$param['name']];
            if($conf['wxnotice_tpl_order_money']) $data[$conf['wxnotice_tpl_order_money']] = ['value'=>'¥'.$param['money']];
            if($conf['wxnotice_tpl_order_time']) $data[$conf['wxnotice_tpl_order_time']] = ['value'=>$param['time']];
            if($conf['wxnotice_tpl_order_outno']) $data[$conf['wxnotice_tpl_order_outno']] = ['value'=>$param['out_trade_no']];
            $jumpurl = $siteurl.'user/order.php';
        }elseif($scene == 'settle'){
            $template_id = $conf['wxnotice_tpl_settle'];
            $data = [];
            if($conf['wxnotice_tpl_settle_type']) $data[$conf['wxnotice_tpl_settle_type']] = ['value'=>'结算成功'];
            if($conf['wxnotice_tpl_settle_account']) $data[$conf['wxnotice_tpl_settle_account']] = ['value'=>$param['account']];
            if($conf['wxnotice_tpl_settle_money']) $data[$conf['wxnotice_tpl_settle_money']] = ['value'=>'¥'.$param['money']];
            if($conf['wxnotice_tpl_settle_realmoney']) $data[$conf['wxnotice_tpl_settle_realmoney']] = ['value'=>'¥'.$param['realmoney']];
            if($conf['wxnotice_tpl_settle_time']) $data[$conf['wxnotice_tpl_settle_time']] = ['value'=>$param['time']];
            $jumpurl = isset($param['jumpurl']) ? $param['jumpurl'] : $siteurl.'user/settle.php';
        }elseif($scene == 'login'){
            $template_id = $conf['wxnotice_tpl_login'];
            $data = [];
            if($conf['wxnotice_tpl_login_user']) $data[$conf['wxnotice_tpl_login_user']] = ['value'=>$param['user']];
            if($conf['wxnotice_tpl_login_time']) $data[$conf['wxnotice_tpl_login_time']] = ['value'=>$param['time']];
            if($conf['wxnotice_tpl_login_name']) $data[$conf['wxnotice_tpl_login_name']] = ['value'=>$conf['sitename']];
            if($conf['wxnotice_tpl_login_ip']) $data[$conf['wxnotice_tpl_login_ip']] = ['value'=>$param['clientip']];
            if($conf['wxnotice_tpl_login_iploc']) $data[$conf['wxnotice_tpl_login_iploc']] = ['value'=>$param['ipinfo']];
            $jumpurl = $siteurl.'user/';
        }elseif($scene == 'complain'){
            $template_id = $conf['wxnotice_tpl_complain'];
            $data = [];
            if(mb_strlen($param['name']) > 20) $param['name'] = mb_substr($param['name'], 0, 20);
            if(mb_strlen($param['reason']) > 20) $param['reason'] = mb_substr($param['reason'], 0, 20);
            if($conf['wxnotice_tpl_complain_order_no']) $data[$conf['wxnotice_tpl_complain_order_no']] = ['value'=>$param['trade_no']];
            if($conf['wxnotice_tpl_complain_time']) $data[$conf['wxnotice_tpl_complain_time']] = ['value'=>$param['time']];
            if($conf['wxnotice_tpl_complain_reason']) $data[$conf['wxnotice_tpl_complain_reason']] = ['value'=>$param['content']];
            if($conf['wxnotice_tpl_complain_type']) $data[$conf['wxnotice_tpl_complain_type']] = ['value'=>$param['type']];
            if($conf['wxnotice_tpl_complain_name']) $data[$conf['wxnotice_tpl_complain_name']] = ['value'=>$param['name']];
            $jumpurl = $siteurl.'user/';
        }elseif($scene == 'balance'){
            $template_id = $conf['wxnotice_tpl_balance'];
            $data = [];
            if($conf['wxnotice_tpl_balance_user']) $data[$conf['wxnotice_tpl_balance_user']] = ['value'=>$param['user']];
            if($conf['wxnotice_tpl_balance_time']) $data[$conf['wxnotice_tpl_balance_time']] = ['value'=>$param['time']];
            if($conf['wxnotice_tpl_balance_money']) $data[$conf['wxnotice_tpl_balance_money']] = ['value'=>$param['money']];
            if($conf['wxnotice_tpl_balance_msg']) $data[$conf['wxnotice_tpl_balance_msg']] = ['value'=>'为避免造成订单失败，请及时充值'];
            $jumpurl = $siteurl.'user/';
        }
        if(empty($template_id) || empty($wid)) return false;
    
        $wechat = new \lib\wechat\WechatAPI($wid);
        try{
            return $wechat->sendTemplateMessage($openid, $template_id, $jumpurl, $data);
        }catch(Exception $e){
            $errmsg = $e->getMessage();
            $CACHE->save('wxtplerrmsg', ['errmsg'=>$errmsg, 'time'=>date('Y-m-d H:i:s')], 86400);
            //echo $errmsg;
            return false;
        }
    }

    private static function send_mail_msg($scene, $receiver, $param){
        global $conf, $CACHE;
        [$title, $content] = self::get_msg_tpl($scene, $param);
        if(empty($content)) return;
        $result = send_mail($receiver, $title, $content);
        if($result === true) return true;

        if(!empty($result)){
            $CACHE->save('mailerrmsg', ['errmsg'=>$result, 'time'=>date('Y-m-d H:i:s')], 86400);
        }
        return false;
    }

    private static function send_robot_msg($scene, $url, $param, $admin = false){
        global $conf, $CACHE;
        if(empty($url)) return false;
        [$title, $content] = self::get_msg_tpl($scene, $param);
        if(empty($content)) return;
        
        return self::robot_webhook($url, $title, $content, $admin);
    }

    public static function robot_webhook($url, $title, $content, $admin = false){
        global $conf, $CACHE;
        $content = str_replace(['*', '<br/>', '<b>', '</b>'], ['\*', "\n", '**', '**'], $content);
        
        if (strpos($url, 'oapi.dingtalk.com')) {
            $content = '### '.$title."  \n ".str_replace("\n", "  \n ", $content);
            $post = [
                'msgtype' => 'markdown',
                'markdown' => [
                    'title' => $title,
                    'text' => $content,
                ],
            ];
        } elseif (strpos($url, 'qyapi.weixin.qq.com')) {
            $content = '## '.$title."\n".$content;
            $post = [
                'msgtype' => 'markdown',
                'markdown' => [
                    'content' => $content,
                ],
            ];
        } elseif (strpos($url, 'open.feishu.cn') || strpos($url, 'open.larksuite.com')) {
            $content = str_replace(['\*', '**'], ['*', ''], strip_tags($content));
            $post = [
                'msg_type' => 'text',
                'content' => [
                    'text' => $content,
                ],
            ];
        } else {
            if($admin){
                $CACHE->save('mailerrmsg', ['errmsg'=>'不支持的Webhook地址', 'time'=>date('Y-m-d H:i:s')], 86400);
            }
            return false;
        }
        $result = get_curl($url, json_encode($post), 0, 0, 0, 0, 0, ['Content-Type: application/json; charset=UTF-8']);
        $arr = json_decode($result, true);
        if (isset($arr['errcode']) && $arr['errcode'] == 0 || isset($arr['code']) && $arr['code'] == 0) {
            return true;
        } else {
            if($admin){
                $CACHE->save('mailerrmsg', ['errmsg'=>$result, 'time'=>date('Y-m-d H:i:s')], 86400);
            }
        }
        return false;
    }

    private static function get_msg_tpl($scene, $param){
        global $conf;
        if($scene == 'regaudit'){
            $title = '新注册商户待审核提醒';
            $content = '尊敬的'.$conf['sitename'].'管理员，网站有新注册的商户待审核，请及时前往用户列表审核处理。<br/><b>商户ID：</b>'.$param['uid'].'<br/><b>注册账号：</b>'.$param['account'].'<br/><b>注册时间：</b>'.date('Y-m-d H:i:s');
        }elseif($scene == 'apply'){
            $title = '新的提现待处理提醒';
            $content = '尊敬的'.$conf['sitename'].'管理员，商户'.$param['uid'].'发起了手动提现申请，请及时处理。<br/><b>商户ID：</b>'.$param['uid'].'<br/><b>提现方式：</b>'.$param['type'].'<br/><b>提现金额：</b>'.$param['realmoney'].'<br/><b>提交时间：</b>'.date('Y-m-d H:i:s');
        }elseif($scene == 'domain'){
            $title = '新的授权支付域名待审核提醒';
            $content = '尊敬的'.$conf['sitename'].'管理员，商户'.$param['uid'].'提交了新的授权支付域名，请及时审核处理。<br/><b>商户ID：</b>'.$param['uid'].'<br/><b>授权域名：</b>'.$param['domain'].'<br/><b>提交时间：</b>'.date('Y-m-d H:i:s');
        }elseif($scene == 'order'){
            $title = '新订单通知 - '.$conf['sitename'];
            $content = '尊敬的商户，您有一条新订单通知。<br/><b>商品名称：</b>'.$param['name'].'<br/><b>订单金额：</b>¥'.$param['money'].'<br/><b>支付方式：</b>'.$param['type'].'<br/><b>商户订单号：</b>'.$param['out_trade_no'].'<br/><b>系统订单号：</b>'.$param['trade_no'].'<br/><b>支付完成时间：</b>'.$param['time'];
        }elseif($scene == 'settle'){
            $title = '结算完成通知 - '.$conf['sitename'];
            $content = '尊敬的商户，今日结算已完成，请查收。<br/><b>结算金额：</b>¥'.$param['money'].'<br/><b>实际到账：</b>¥'.$param['realmoney'].'<br/><b>结算账号：</b>'.$param['account'].'<br/><b>结算完成时间：</b>'.$param['time'];
        }elseif($scene == 'login'){
            $title = '账号登录通知 - '.$conf['sitename'];
            $content = '尊敬的商户，您的账号<b>'.$param['user'].'</b>已于'.$param['time'].'成功登录到商户平台。<br/><b>登录IP：</b>'.$param['clientip'].'<br/><b>登录时间：</b>'.$param['time'];
        }elseif($scene == 'complain'){
            $title = '支付交易投诉通知 - '.$conf['sitename'];
            $content = '尊敬的商户，'.$param['type'].'！<br/><b>系统订单号：</b>'.$param['trade_no'].'<br/><b>投诉原因：</b>'.$param['title'].'<br/><b>投诉详情：</b>'.$param['content'].'<br/><b>商品名称：</b>'.$param['ordername'].'<br/><b>订单金额：</b>¥'.$param['money'].'<br/><b>投诉时间：</b>'.$param['time'];
        }elseif($scene == 'mchrisk'){
            $title = '渠道商户违规处置通知 - '.$conf['sitename'];
            $content = '尊敬的商户，您有新的渠道商户违规处置记录！<br/><b>渠道子商户号：</b>'.$param['mchid'].'<br/><b>商户名称：</b>'.$param['mchname'].'<br/><b>风险类型：</b>'.$param['risk_desc'].'<br/><b>管控能力：</b>'.$param['punish_type'].'<br/><b>管控开始时间：</b>'.$param['punish_time'].'<br/><b>解除方式：</b>'.$param['recover_way'];
        }elseif($scene == 'balance'){
            $title = '商户余额不足提醒 - '.$conf['sitename'];
            $content = '尊敬的商户，您的手续费余额不足'.$param['msgmoney'].'元，为避免造成订单失败请及时充值。<br/>当前余额：'.$param['money'].'元';
        }elseif($scene == 'group'){
            $title = '会员用户组到期提醒 - '.$conf['sitename'];
            $content = '尊敬的商户，您的购买的会员用户组 <b>'.$param['group'].'</b> 已于 '.$param['endtime'].' 到期，请及时前往商户平台续费。';
        }
        return [$title, $content];
    }

    private static function getMessageSwitch($scene){
        global $conf;
        if(isset($conf['msgconfig_'.$scene])){
            return $conf['msgconfig_'.$scene];
        }
        return false;
    }
}