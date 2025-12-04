<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'transferList':
	$sql=" 1=1";
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$sql.=" AND `uid`='$uid'";
	}
	if(isset($_POST['type']) && !empty($_POST['type'])) {
		$type = intval($_POST['type']);
		$sql.=" AND `type`='$type'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `status`={$dstatus}";
	}
	if(!empty($_POST['starttime']) || !empty($_POST['endtime'])){
		if(!empty($_POST['starttime'])){
			$starttime = daddslashes($_POST['starttime']);
			$sql.=" AND addtime>='{$starttime} 00:00:00'";
		}
		if(!empty($_POST['endtime'])){
			$endtime = daddslashes($_POST['endtime']);
			$sql.=" AND addtime<='{$endtime} 23:59:59'";
		}
	}
	if(isset($_POST['value']) && !empty($_POST['value'])) {
		if($_POST['column']=='username'||$_POST['column']=='desc'){
			$sql.=" AND `{$_POST['column']}` LIKE '%{$_POST['value']}%'";
		}else{
			$sql.=" AND `{$_POST['column']}`='{$_POST['value']}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_transfer WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_transfer WHERE{$sql} order by biz_no desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		if($row['type'] == 'wxpay' && $row['status'] == 0 && !empty($row['ext'])){
			if(substr($row['ext'], 0, 4) == 'http'){
				$row['jumpurl'] = $row['ext'];
			}else{
				$row['jumpurl'] = $siteurl.'paypage/wxtrans.php?id='.$row['biz_no'].'&type=transfer';
			}
		}
		if($row['status'] == 4){
			$row['jumpurl'] = \lib\Transfer::red_url($row['biz_no']);
		}
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;

case 'statistics':
	$sql=" 1=1";
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$sql.=" AND `uid`='$uid'";
	}
	if(isset($_POST['type']) && !empty($_POST['type'])) {
		$type = intval($_POST['type']);
		$sql.=" AND `type`='$type'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `status`={$dstatus}";
	}
	if(!empty($_POST['starttime']) || !empty($_POST['endtime'])){
		if(!empty($_POST['starttime'])){
			$starttime = daddslashes($_POST['starttime']);
			$sql.=" AND addtime>='{$starttime} 00:00:00'";
		}
		if(!empty($_POST['endtime'])){
			$endtime = daddslashes($_POST['endtime']);
			$sql.=" AND addtime<='{$endtime} 23:59:59'";
		}
	}
	if(isset($_POST['value']) && !empty($_POST['value'])) {
		if($_POST['column']=='username'||$_POST['column']=='desc'){
			$sql.=" AND `{$_POST['column']}` LIKE '%{$_POST['value']}%'";
		}else{
			$sql.=" AND `{$_POST['column']}`='{$_POST['value']}'";
		}
	}
	$totalMoney = $DB->getColumn("SELECT SUM(money) FROM pre_transfer WHERE{$sql} AND status<>2");
	$resultCount = $DB->getRow("SELECT 
    COUNT(*) AS totalCount,
    COUNT(status = 0 OR NULL) AS status0count,
    COUNT(status = 1 OR NULL) AS status1count,
    COUNT(status = 2 OR NULL) AS status2count,
    COUNT(status = 3 OR NULL) AS status3count
    FROM pre_transfer WHERE{$sql}");
	exit(json_encode(['code'=>0, 'data'=>['totalMoney'=>number_format($totalMoney, 2, '.', '') ?? 0.00, 'totalCount'=>$resultCount['totalCount'], 'status0count'=>$resultCount['status0count'], 'status1count'=>$resultCount['status1count'], 'status2count'=>$resultCount['status2count'], 'status3count'=>$resultCount['status3count']]]));
break;

case 'transfer_query':
	$biz_no=trim($_GET['biz_no']);
	$result = \lib\Transfer::status($biz_no);
	exit(json_encode($result));
break;
case 'transfer_result':
	$biz_no=trim($_GET['biz_no']);
    $row = $DB->find('transfer', 'biz_no,result', ['biz_no' => $biz_no]);
	if(!$row) exit('{"code":-1,"msg":"付款记录不存在！"}');
	$result = ['code'=>0,'msg'=>$row['result']?$row['result']:'未知'];
	exit(json_encode($result));
break;
case 'transfer_cancel':
	$biz_no=trim($_POST['biz_no']);
	$result = \lib\Transfer::cancel($biz_no);
	exit(json_encode($result));
break;
case 'balance_query':
	$type = $_POST['type'];
	$channel = isset($_POST['channel'])?intval($_POST['channel']):$conf['transfer_'.$type];
	$channel = \lib\Channel::get($channel);
	if(!$channel)exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	$user_id = isset($_POST['user_id'])?$_POST['user_id']:null;
	$result = \lib\Transfer::balance($type, $channel, $user_id);
	exit(json_encode($result));
break;
case 'setTransferStatus':
	$biz_no=$_POST['biz_no'];
	$order = $DB->find('transfer', '*', ['biz_no' => $biz_no]);
	if(!$order) exit('{"code":-1,"msg":"付款记录不存在！"}');
	$status=intval($_POST['status']);
	$reason = trim($_POST['reason']);
	$data = ['status'=>$status];
	if(!empty($reason)) $data['result'] = $reason;
	if($status == 1 && empty($order['paytime'])) $data['paytime'] = date('Y-m-d H:i:s');
	if($DB->update('transfer', $data, ['biz_no' => $biz_no])){
		if($status == 2 && ($order['status'] == 3 || $order['status'] == 0) && $order['uid'] > 0){
			changeUserMoney($order['uid'], $order['costmoney'], true, '代付退回', $biz_no);
		}
		exit('{"code":0,"msg":"succ"}');
	}
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delTransfer':
	$biz_no=$_POST['biz_no'];
	if($DB->delete('transfer', ['biz_no' => $biz_no])!==false)exit('{"code":0,"msg":"succ"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'refundTransfer':
	$biz_no=$_POST['biz_no'];
	$order = $DB->find('transfer', '*', ['biz_no' => $biz_no]);
    if(!$order) exit('{"code":-1,"msg":"付款记录不存在！"}');
	if($DB->exec("UPDATE pre_transfer SET status='2' WHERE biz_no='$biz_no'")){
		if($order['uid'] > 0){
			changeUserMoney($order['uid'], $order['costmoney'], true, '代付退回', $biz_no);
		}
	}
	exit('{"code":0,"msg":"已成功将¥'.$order['costmoney'].'退给商户'.$order['uid'].'"}');
break;
case 'transfer_proof':
	$biz_no=trim($_POST['biz_no']);
	$result = \lib\Transfer::proof($biz_no);
	exit(json_encode($result));
break;
case 'operation': //批量操作订单
	$status=is_numeric($_POST['status'])?intval($_POST['status']):exit('{"code":-1,"msg":"请选择操作"}');
	$checkbox=$_POST['checkbox'];
	$i=0;
	foreach($checkbox as $biz_no){
		if($status==3){
			$DB->delete('transfer', ['biz_no' => $biz_no]);
			continue;
		}
		$order = $DB->find('transfer', '*', ['biz_no' => $biz_no]);
		if($order){
			$data = ['status'=>$status];
			if($status == 1 && empty($order['paytime'])) $data['paytime'] = date('Y-m-d H:i:s');
			if($DB->update('transfer', $data, ['biz_no' => $biz_no])){
				if($status == 2 && ($order['status'] == 3 || $order['status'] == 0) && $order['uid'] > 0){
					changeUserMoney($order['uid'], $order['costmoney'], true, '代付退回', $biz_no);
				}
				$i++;
			}
		}
	}
	exit('{"code":0,"msg":"成功改变'.$i.'条订单状态"}');
break;

case 'batch_submit':
	$type = isset($_POST['type'])?$_POST['type']:'alipay';
	$out_biz_no = date("YmdHis").rand(11111,99999);
	if(!isset($_POST['paypwd']) || $_POST['paypwd']!==$conf['admin_paypwd'])exit('{"code":-2,"msg":"支付密码错误"}');
	$payee_account = htmlspecialchars(trim($_POST['account']));
	$payee_real_name = htmlspecialchars(trim($_POST['name']));
	$money = trim($_POST['money']);
	$desc = htmlspecialchars(trim($_POST['desc']));
	if(empty($payee_account) || empty($money))exit('{"code":-2,"msg":"必填项不能为空"}');
	if($desc && mb_strlen($desc)>32)exit('{"code":-2,"msg":"转账备注最多32个字"}');
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money) || $money<=0)exit('{"code":-2,"msg":"转账金额输入不规范"}');

	$channelid = isset($_POST['channel'])?$_POST['channel']:null;

	$result = \lib\Transfer::add(0, $type, $out_biz_no, $payee_account, $payee_real_name, $money, $desc, null, $channelid);

	if($result['code']==0){
		if($result['status'] == 1){
			$msg='转账成功！转账单据号:'.$result['orderid'];
		}elseif($result['status'] == 3){
			$msg='提交成功！请等待管理员审核转账。';
		}elseif(isset($result['wxpackage'])){
			$msg='提交成功！请在付款记录页面扫码确认收款。转账单据号:'.$result['orderid'];
		}else{
			$msg='提交成功！转账处理中，请稍后查询结果。转账单据号:'.$result['orderid'];
		}
		exit(json_encode(['code'=>0, 'status'=>$result['status'], 'msg'=>$msg]));
	}else{
		if(!isset($result['errcode']) || in_array($result['errcode'], \lib\Transfer::$payee_err_code)){
            //$data = ['biz_no'=>$out_biz_no, 'out_biz_no'=>$out_biz_no, 'uid'=>0, 'type'=>$type, 'channel'=>$channelid, 'account'=>$payee_account, 'username'=>$payee_real_name, 'money'=>$money, 'costmoney'=>$money, 'addtime'=>'NOW()', 'pay_order_no'=>$result['orderid'], 'status'=>2, 'desc'=>$desc, 'result'=>$result['msg']];
            //$DB->insert('transfer', $data);
			exit(json_encode(['code'=>-1, 'msg'=>$result['msg']]));
		}else{
			exit(json_encode(['code'=>-2, 'msg'=>$result['msg']]));
		}
	}
break;

case 'stat':
	$startday = trim($_POST['startday']);
	$endday = trim($_POST['endday']);
	if(!$startday || !$endday)exit(json_encode(['code'=>0, 'msg'=>'no day']));
	$sql="`addtime`>='{$startday} 00:00:00' AND `addtime`<='{$endday} 23:59:59' AND status=1";
	if(isset($_POST['type']) && !empty($_POST['type'])) {
		$type = trim($_POST['type']);
		$sql.=" AND `type`='$type'";
	}
	$list = $DB->getAll("SELECT account,username,COUNT(*) AS order_count,SUM(money) AS money
		FROM pre_transfer
		WHERE {$sql}
		GROUP BY account,username
		ORDER BY money DESC");
	exit(json_encode($list));
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}