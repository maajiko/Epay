<?php
include("../includes/common.php");
if($islogin2==1){}else exit('{"code":-3,"msg":"No Login"}');
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'info':
	if($userrow['open_wxa'] == 0) exit('{"code":-1,"msg":"商户未开启微信小程序功能"}');
	$name = !empty($userrow['certname']) ? $userrow['certname'] : $userrow['username'];
	if($userrow['status']==0){
		$status = '已封禁';
	}elseif($userrow['pay']==0 && $userrow['settle']==0){
		$status = '关闭支付、结算';
	}elseif($userrow['pay']==0){
		$status = '关闭支付';
	}elseif($userrow['settle']==0){
		$status = '关闭结算';
	}elseif($conf['cert_force']==1 && $userrow['cert']==0){
		$status = '未实名认证';
	}elseif($userrow['pay']==2){
		$status = '待审核';
	}else{
		$status = '正常';
	}
	$complain_total = $DB->getColumn("SELECT count(*) from pre_complain WHERE uid=$uid AND status=0");
	$groupconfig = getGroupConfig($userrow['gid']);
	$conf = array_merge($conf, $groupconfig);
	$mygroup = $DB->getRow("SELECT * FROM pre_group WHERE gid='{$userrow['gid']}'");
	$mygroupname = $mygroup['name'] ? $mygroup['name'] : '默认用户组';
	$gexpire = $userrow['endtime'] ? date("Y-m-d", strtotime($userrow['endtime'])) : '永久';
	$merchant = authcode($uid, 'ENCODE', SYS_KEY);
	$code_url = $conf['onecode']==1 || $userrow['open_code'] == 1 ? $siteurl.'paypage/?merchant='.urlencode($merchant) : null;
	$result = [
		'code' => 0,
		'user' => [
			'uid' => $uid,
			'name' => $name,
			'money' => $userrow['money'],
			'deposit' => $userrow['deposit'],
			'status' => $status,
			'gid' => $userrow['gid'],
			'gname' => $mygroupname,
			'gexpire' => $gexpire,
			'key' => $userrow['key'],
			'settle_type' => $userrow['settle_id'],
			'settle_account' => $userrow['account'],
			'settle_name' => $userrow['username'],
			'codename' => $userrow['codename'],
			'alipay_uid' => $userrow['alipay_uid'],
			'qq_uid' => $userrow['qq_uid'],
			'wx_uid' => $userrow['wx_uid'],
			'email' => $userrow['email'],
			'phone' => $userrow['phone'],
			'qq' => $userrow['qq'],
			'url' => $userrow['url'],
			'cert' => $userrow['cert'],
			'certtype' => $userrow['certtype'],
			'certmethod' => $userrow['certmethod'],
			'certno' => !empty($userrow['certno']) ? substr($userrow['certno'],0,3).showstar(11).substr($userrow['certno'],-4) : null,
			'certname' => !empty($userrow['certname']) ? showstar((strlen($userrow['certname'])-3)/3).substr($userrow['certname'],-3) : null,
			'certtime' => $userrow['certtime'],
			'certcorpno' => $userrow['certcorpno'],
			'certcorpname' => $userrow['certcorpname'],
			'addtime' => $userrow['addtime'],
			'lasttime' => $userrow['lasttime'],
			'endtime' => $userrow['endtime'],
			'keylogin' => $userrow['keylogin'],
			'mode' => $userrow['mode'],
			'api_refund' => $userrow['refund'],
			'api_transfer' => $userrow['transfer'],
			'keytype' => $userrow['keytype'],
			'publickey' => $userrow['publickey'],
			'ordername' => $userrow['ordername'],
			'msgconfig' => unserialize($userrow['msgconfig']),
			'remain_money' => $userrow['remain_money'],
			'voice_devid' => $userrow['voice_devid'],
			'voice_order' => $userrow['voice_order'],
			'pay_maxmoney' => $userrow['pay_maxmoney'],
			'pay_minmoney' => $userrow['pay_minmoney'],
			'complain_total' => $complain_total,
			'code_url' => $code_url,
			'faceimg' => ($userrow['qq'])?'https://q2.qlogo.cn/headimg_dl?bs=qq&dst_uin='.$userrow['qq'].'&src_uin='.$userrow['qq'].'&fid='.$userrow['qq'].'&spec=100&url_enc=0&referer=bu_interface&term_type=PC':$siteurl.'assets/img/user.png',
		],
		'sys' => [
			'sitename' => $conf['sitename'],
			'cert' => $conf['cert_open']>0,
			'deposit' => $conf['user_deposit']>0,
			'withdraw' => $conf['settle_open']==2||$conf['settle_open']==3,
			'recharge' => $conf['recharge']==1,
			'groupbuy' => $conf['group_buy']==1,
			'domain' => $conf['pay_domain_open']==1,
			'complain' => class_exists('\\lib\\Complain\\CommUtil') && $conf['complain_open']==1,
			'mchrisk' => class_exists('\\lib\\WxMchRisk') && $conf['mchrisk_open']==1,
			'applyments' => class_exists('\\lib\\Applyments\\CommUtil'),
			'transfer' => $conf['user_transfer']==1,
			'alipaysatf' => class_exists('\\lib\\AlipaySATF\\AlipaySATF') && $conf['alipay_satf']==1,
			'onecode' => $conf['onecode']==1 || $userrow['open_code'] == 1,
			'invite' => $conf['invite_open']==1,
			'cert_type' => intval($conf['cert_open']),
			'cert_corp' => $conf['cert_corpopen'] == 1,
			'cert_money' => $conf['cert_money'],
			'public_key' => $conf['public_key'],
			'apiurl' => !empty($conf['apiurl'])?$conf['apiurl']:$siteurl,
		],
	];
	exit(json_encode($result));
break;
case 'getcount':
	$lastday=date("Y-m-d",strtotime("-1 day"));
	$today=date("Y-m-d");

	$orders=$DB->getColumn("SELECT count(*) FROM pre_order WHERE uid={$uid} AND status=1");
	$orders_today=$DB->getColumn("SELECT count(*) from pre_order WHERE uid={$uid} AND status=1 AND date='$today'");

	$settle_money=$DB->getColumn("SELECT sum(realmoney) FROM pre_settle WHERE uid={$uid} and status=1");
	$settle_money=round($settle_money,2);

	$order_today_all = round($DB->getColumn("SELECT sum(money) FROM pre_order WHERE uid={$uid} AND status=1 AND date='$today'"),2);
	$order_lastday_all = round($DB->getColumn("SELECT sum(money) FROM pre_order WHERE uid={$uid} AND status=1 AND date='$lastday'"),2);

	$transfer_today_all = round($DB->getColumn("SELECT sum(money) FROM pre_transfer WHERE uid={$uid} AND status<>2 AND addtime>='$today'"),2);
	$transfer_lastday_all = round($DB->getColumn("SELECT sum(money) FROM pre_transfer WHERE uid={$uid} AND status<>2 AND addtime>='$lastday' AND addtime<'$today'"),2);

	$channels = [];
	$types = \lib\Channel::getTypes($uid, $userrow['gid']);
	foreach($types as $row){
		$order_today = round($DB->getColumn("SELECT sum(money) FROM pre_order WHERE uid={$uid} AND status=1 AND date='$today' AND type={$row['id']}"),2);
		$order_lastday = round($DB->getColumn("SELECT sum(money) FROM pre_order WHERE uid={$uid} AND status=1 AND date='$lastday' AND type={$row['id']}"),2);

		$orderrow = $DB->getRow("SELECT COUNT(*) allnum,COUNT(IF(status>0, 1, NULL)) sucnum FROM pre_order WHERE uid={$uid} AND addtime>='$today' AND type={$row['id']}");
		$success_rate = $orderrow && $orderrow['allnum'] > 0 ? round($orderrow['sucnum']/$orderrow['allnum']*100,2) : 100;

		$channels[] = ['name'=>$row['name'], 'showname'=>$row['showname'], 'rate'=>round(100-$row['rate'], 2), 'order_today'=>$order_today, 'order_lastday'=>$order_lastday, 'success_rate'=>$success_rate];
	}

	$result=['code'=>0, 'orders'=>$orders, 'orders_today'=>$orders_today, 'settle_money'=>$settle_money, 'order_today_all'=>$order_today_all, 'order_lastday_all'=>$order_lastday_all, 'transfer_today_all'=>$transfer_today_all, 'transfer_lastday_all'=>$transfer_lastday_all, 'channels'=>$channels];
	exit(json_encode($result));
break;
case 'sendcode':
	$situation=trim($_POST['situation']);
	$target=htmlspecialchars(strip_tags(trim($_POST['target'])));
	if(isset($_SESSION['send_code_time']) && $_SESSION['send_code_time']>time()-10){
		exit('{"code":-1,"msg":"请勿频繁发送验证码"}');
	}
	if(!isset($_SESSION['gtserver']))exit('{"code":-1,"msg":"验证加载失败"}');
	if(!verify_captcha($uid))exit('{"code":-1,"msg":"验证失败，请重新验证"}');

	if($conf['verifytype']==1 || $situation=='bindphone'){
		if($situation=='bind' || $situation=='bindphone'){
			if(empty($target) || strlen($target)!=11){
				exit('{"code":-1,"msg":"请填写正确的手机号码！"}');
			}
			if($target==$userrow['phone']){
				exit('{"code":-1,"msg":"你填写的手机号码和之前一样"}');
			}
			$row=$DB->getRow("select * from pre_user where phone=:phone limit 1", [':phone'=>$target]);
			if($row){
				exit('{"code":-1,"msg":"该手机号码已经绑定过其它商户"}');
			}
		}else{
			if(empty($userrow['phone']) || strlen($userrow['phone'])!=11){
				exit('{"code":-1,"msg":"请先绑定手机号码！"}');
			}
			$target=$userrow['phone'];
		}
		$type = 1;
	}else{
		if($situation=='bind'){
			if(!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $target)){
				exit('{"code":-1,"msg":"邮箱格式不正确"}');
			}
			if($target==$userrow['email']){
				exit('{"code":-1,"msg":"你填写的邮箱和之前一样"}');
			}
			$row=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$target]);
			if($row){
				exit('{"code":-1,"msg":"该邮箱已经绑定过其它商户"}');
			}
		}else{
			if(empty($userrow['email']) || strpos($userrow['email'],'@')===false){
				exit('{"code":-1,"msg":"请先绑定邮箱！"}');
			}
			$target=$userrow['email'];
		}
		$type = 0;
	}
	$result = \lib\VerifyCode::send_code('edit', $type, $target, $uid);
	if($result === true){
		$_SESSION['send_code_time']=time();
		exit(json_encode(['code'=>0, 'msg'=>'succ']));
	}else{
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
break;
case 'verifycode':
	$code=trim($_POST['code']);
	
	if($conf['verifytype']==1){
		$sendto = $userrow['phone'];
		$type = 1;
	}else{
		$sendto = $userrow['email'];
		$type = 0;
	}
	$result = \lib\VerifyCode::verify_code('edit', $type, $sendto, $code, $uid);
	if($result === true){
		$_SESSION['verify_ok']=$uid;
		\lib\VerifyCode::void_code();
		exit(json_encode(['code'=>1, 'msg'=>'succ']));
	}else{
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
break;
case 'completeinfo':
	$type=intval($_POST['stype']);
	$account=htmlspecialchars(strip_tags(trim($_POST['account'])));
	$username=htmlspecialchars(strip_tags(trim($_POST['username'])));
	$email=htmlspecialchars(strip_tags(trim($_POST['email'])));
	$qq=htmlspecialchars(strip_tags(trim($_POST['qq'])));
	$url=htmlspecialchars(strip_tags(trim($_POST['url'])));

	if(empty($account) || empty($username) || empty($qq) || empty($url)){
		exit('{"code":-1,"msg":"请确保每项都不为空"}');
	}
	if(!empty($userrow['account']) && !empty($userrow['username'])){
		exit('{"code":-1,"msg":"你已完善相关信息"}');
	}
	if($type==1 && strlen($account)!=11 && strpos($account,'@')==false){
		exit('{"code":-1,"msg":"请填写正确的支付宝账号！"}');
	}
	if($type==2 && strlen($account)<3){
		exit('{"code":-1,"msg":"请填写正确的微信"}');
	}
	if($type==3 && (strlen($account)<5 || strlen($account)>10 || !is_numeric($account))){
		exit('{"code":-1,"msg":"请填写正确的QQ号码"}');
	}
	if(strlen($qq)<5 || strlen($qq)>10 || !is_numeric($qq)){
		exit('{"code":-1,"msg":"请填写正确的QQ"}');
	}
	if(strlen($url)<4 || strpos($url,'.')==false){
		exit('{"code":-1,"msg":"请填写正确的网站域名！"}');
	}
	$data = ['settle_id'=>$type, 'account'=>$account, 'username'=>$username, 'qq'=>$qq, 'url'=>$url];
	if($conf['verifytype']==1){
		if(!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $email)){
			exit('{"code":-1,"msg":"邮箱格式不正确"}');
		}
		if($email!=$userrow['email']){
			$row=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$email]);
			if($row){
				exit('{"code":-1,"msg":"该邮箱已经绑定过其它商户，如需找回，请退出登录后找回密码"}');
			}
			$data['email'] = $email;
		}
	}
	if($DB->update('user', $data, ['uid'=>$uid])!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_settle':
	$type=intval($_POST['stype']);
	$account=htmlspecialchars(strip_tags(trim($_POST['account'])));
	$username=htmlspecialchars(strip_tags(trim($_POST['username'])));

	if($account==null || $username==null){
		exit('{"code":-1,"msg":"请确保每项都不为空"}');
	}
	if($type==1 && strlen($account)!=11 && strpos($account,'@')==false){
		exit('{"code":-1,"msg":"请填写正确的支付宝账号！"}');
	}
	if($type==2 && strlen($account)<3){
		exit('{"code":-1,"msg":"请填写正确的微信"}');
	}
	if($type==3 && (strlen($account)<5 || strlen($account)>10 || !is_numeric($account))){
		exit('{"code":-1,"msg":"请填写正确的QQ号码"}');
	}
	if($userrow['type']!=2 && !empty($userrow['account']) && !empty($userrow['username']) && ($userrow['account']!=$account || $userrow['username']!=$username) && $_SESSION['verify_ok']!==$uid){
		if($conf['verifytype']==1 && (empty($userrow['phone']) || strlen($userrow['phone'])!=11)){
			exit('{"code":-1,"msg":"请先绑定手机号码！"}');
		}elseif($conf['verifytype']==0 && (empty($userrow['email']) || strpos($userrow['email'],'@')===false)){
			exit('{"code":-1,"msg":"请先绑定邮箱！"}');
		}
		exit('{"code":2,"msg":"need verify"}');
	}
	$data = ['settle_id'=>$type, 'account'=>$account, 'username'=>$username];
	if($DB->update('user', $data, ['uid'=>$uid])!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_info':
	$email=htmlspecialchars(strip_tags(trim($_POST['email'])));
	$qq=htmlspecialchars(strip_tags(trim($_POST['qq'])));
	$url=htmlspecialchars(strip_tags(trim($_POST['url'])));
	$keylogin=intval($_POST['keylogin']);
	$refund=intval($_POST['refund']);
	$transfer=intval($_POST['transfer']);
	$remain_money=trim($_POST['remain_money']);
	$pay_maxmoney=trim($_POST['pay_maxmoney']);
	$pay_minmoney=trim($_POST['pay_minmoney']);

	if($qq==null || $url==null){
		exit('{"code":-1,"msg":"请确保每项都不为空"}');
	}
	if(strlen($qq)<5 || strlen($qq)>10 || !is_numeric($qq)){
		exit('{"code":-1,"msg":"请填写正确的QQ"}');
	}
	if(strlen($url)<4 || strpos($url,'.')==false){
		exit('{"code":-1,"msg":"请填写正确的网站域名！"}');
	}
	$data = ['qq'=>$qq, 'url'=>$url, 'keylogin'=>$keylogin, 'refund'=>$refund, 'transfer'=>$transfer, 'remain_money'=>$remain_money, 'pay_maxmoney'=>$pay_maxmoney, 'pay_minmoney'=>$pay_minmoney];
	if($conf['verifytype']==1){
		if($email!=$userrow['email']){
			$row=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$email]);
			if($row){
				exit('{"code":-1,"msg":"该邮箱已经绑定过其它商户，如需找回，请退出登录后找回密码"}');
			}
			if(!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $email)){
				exit('{"code":-1,"msg":"邮箱格式不正确"}');
			}
		}
		$data['email'] = $email;
	}
	$sqs = $DB->update('user', $data, ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_keytype':
	$keytype=intval($_POST['keytype']);
	$sqs = $DB->update('user', ['keytype'=>$keytype], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_voice':
	$voice_devid=trim($_POST['voice_devid']);
	$voice_order=intval($_POST['voice_order']);
	$sqs = $DB->update('user', ['voice_devid'=>$voice_devid, 'voice_order'=>$voice_order], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_print':
	$print_order=intval($_POST['print_order']);
	$print_devid=trim($_POST['print_devid']);
	$print_count=trim($_POST['print_count']);
	$print_voice=intval($_POST['print_voice']);
	$print_config=serialize(['devid'=>$print_devid, 'count'=>$print_count, 'voice'=>$print_voice]);
	$sqs = $DB->update('user', ['print_order'=>$print_order, 'print_config'=>$print_config], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_channel_info':
	$setting=$_POST['setting'];
	$channelinfo = json_encode($setting);

	$sqs=$DB->update('user', ['channelinfo'=>$channelinfo], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_mode':
	$mode=intval($_POST['mode']);

	$sqs=$DB->update('user', ['mode'=>$mode], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_msgconfig':
	$msgconfig = [
		'order' => intval($_POST['notice_order']),
		'settle' => intval($_POST['notice_settle']),
		'login' => intval($_POST['notice_login']),
		'complain' => intval($_POST['notice_complain']),
		'mchrisk' => intval($_POST['notice_mchrisk']),
		'order_money' => trim($_POST['notice_order_money']),
		'balance' => intval($_POST['notice_balance']),
		'balance_money' => trim($_POST['notice_balance_money']),
		'msgrobot_url' => trim($_POST['notice_msgrobot_url']),
	];

	$sqs=$DB->update('user', ['msgconfig'=>serialize($msgconfig)], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'edit_bind':
	$email=htmlspecialchars(strip_tags(trim($_POST['email'])));
	$phone=htmlspecialchars(strip_tags(trim($_POST['phone'])));
	$code=trim($_POST['code']);

	if($code==null || $email==null && $phone==null){
		exit('{"code":-1,"msg":"请确保每项都不为空"}');
	}
	if(empty($_SESSION['verify_ok']) || $_SESSION['verify_ok']!=$uid){
		if($conf['verifytype']==1 && !empty($userrow['phone']) && strlen($userrow['phone'])==11){
			exit('{"code":2,"msg":"请先完成验证"}');
		}elseif($conf['verifytype']==0 && !empty($userrow['email']) && strpos($userrow['email'],'@')!==false && !empty($email) && empty($phone)){
			exit('{"code":2,"msg":"请先完成验证"}');
		}
	}
	if($conf['verifytype']==1 || $conf['verifytype']==0 && empty($email) && !empty($phone)){
		$sendto = $phone;
		$type = 1;
	}else{
		$sendto = $email;
		$type = 0;
	}
	$result = \lib\VerifyCode::verify_code('edit', $type, $sendto, $code, $uid);
	if($result !== true){
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
	if($conf['verifytype']==1 || $conf['verifytype']==0 && empty($email) && !empty($phone)){
		$sqs=$DB->update('user', ['phone'=>$phone], ['uid'=>$uid]);
	}else{
		$sqs=$DB->update('user', ['email'=>$email], ['uid'=>$uid]);
	}
	if($sqs!==false){
		\lib\VerifyCode::void_code();
		exit('{"code":1,"msg":"succ"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'checkbind':
	if($conf['verifytype']==1 && (empty($userrow['phone']) || strlen($userrow['phone'])!=11)){
		exit('{"code":1,"msg":"bind"}');
	}elseif($conf['verifytype']==0 && (empty($userrow['email']) || strpos($userrow['email'],'@')===false)){
		exit('{"code":1,"msg":"bind"}');
	}elseif(isset($_SESSION['verify_ok']) && $_SESSION['verify_ok']===$uid){
		exit('{"code":1,"msg":"bind"}');
	}else{
		exit('{"code":2,"msg":"need verify"}');
	}
break;
case 'resetKey':
	if(isset($_POST['submit'])){
		$key = random(32);
		$sql = "UPDATE pre_user SET `key`='$key' WHERE uid='$uid'";
		if($DB->exec($sql)!==false)exit('{"code":0,"msg":"重置密钥成功","key":"'.$key.'"}');
		else exit('{"code":-1,"msg":"重置密钥失败['.$DB->error().']"}');
	}
break;
case 'createRsaPair':
	if(isset($_POST['submit'])){
		$keypair = generate_key_pair();
		$DB->update('user', ['publickey'=>$keypair['public_key']], ['uid'=>$uid]);
		exit(json_encode(['code'=>0, 'msg'=>'succ', 'public_key'=>$keypair['public_key'], 'private_key'=>$keypair['private_key']]));
	}
break;
case 'edit_pwd':
	$oldpwd=trim($_POST['oldpwd']);
	$newpwd=trim($_POST['newpwd']);
	$newpwd2=trim($_POST['newpwd2']);

	if(!empty($userrow['pwd']) && $oldpwd==null || $newpwd==null || $newpwd2==null){
		exit('{"code":-1,"msg":"请确保每项都不为空"}');
	}
	if(!empty($userrow['pwd']) && getMd5Pwd($oldpwd, $uid)!=$userrow['pwd']){
		exit('{"code":-1,"msg":"旧密码不正确"}');
	}
	if($newpwd!=$newpwd2){
		exit('{"code":-1,"msg":"两次输入密码不一致！"}');
	}
	if($oldpwd==$newpwd){
		exit('{"code":-1,"msg":"旧密码和新密码相同！"}');
	}
	if (strlen($newpwd) < 6) {
		exit('{"code":-1,"msg":"新密码不能低于6位"}');
	}elseif ($newpwd == $userrow['email']) {
		exit('{"code":-1,"msg":"新密码不能和邮箱相同"}');
	}elseif ($newpwd == $userrow['phone']) {
		exit('{"code":-1,"msg":"新密码不能和手机号码相同"}');
	}elseif (is_numeric($newpwd)) {
		exit('{"code":-1,"msg":"新密码不能为纯数字"}');
	}
	$pwd = getMd5Pwd($newpwd, $uid);
	$sqs=$DB->exec("update `pre_user` set `pwd` ='{$pwd}' where `uid`='$uid'");
	if($sqs!==false){
		exit('{"code":1,"msg":"修改密码成功！请牢记新密码"}');
	}else{
		exit('{"code":-1,"msg":"修改密码失败！'.$DB->error().'"}');
	}
break;
case 'edit_codename':
	$codename=htmlspecialchars(strip_tags(trim($_POST['codename'])));

	$msg = '';
	if(!empty($userrow['voice_devid'])){
		$url = 'http://iot.solomo-info.com:9306/admin/common/msgpush';
	        $param = [
	            'agent_id' => $conf['voice_username'],
	            'agent_secret' => $conf['voice_apikey'],
	            'sbx_id' => $userrow['voice_devid'],
	            'msg' => json_encode(['cmd'=>'setpara','user'=>$codename], JSON_UNESCAPED_UNICODE),
	            'debug' => 0,
	        ];
	        $data = get_curl($url, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);
	        $result = json_decode($data, true);
	        if(isset($result['result']) && $result['result'] == 'success'){
				$msg = '设置音响名称成功';
	        }else{
				$msg = '设置音响名称失败，'.$result['msg'];
	        }
	}

	$sqs=$DB->update('user', ['codename'=>$codename], ['uid'=>$uid]);
	if($sqs!==false){
		exit('{"code":1,"msg":"保存成功！'.$msg.'"}');
	}else{
		exit('{"code":-1,"msg":"保存失败！'.$DB->error().'"}');
	}
break;
case 'certificate':
	$certname=htmlspecialchars(strip_tags(trim($_POST['certname'])));
	$certno=htmlspecialchars(strip_tags(trim($_POST['certno'])));
	$certtype=intval($_POST['certtype']);
	$certcardtype=intval($_POST['certcardtype']);
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($userrow['cert']==1 &&($certtype==0 || $certtype==1 && $userrow['certtype']==1))exit('{"code":-1,"msg":"你已完成实名认证"}');
	if($conf['cert_money']>0 && $userrow['money']<$conf['cert_money'])exit('{"code":-1,"msg":"账户余额不足'.$conf['cert_money'].'元，无法完成认证"}');
	if(empty($certname) || empty($certno))exit('{"code":-1,"msg":"请确保各项不能为空"}');
	if(strlen($certname)<3)exit('{"code":-1,"msg":"姓名填写错误"}');
	if($certcardtype==0 && !is_idcard($certno))exit('{"code":-1,"msg":"身份证号不正确"}');
	/*$row=$DB->getRow("SELECT uid,phone,email FROM pre_user WHERE certname=:certname AND certno=:certno AND cert=1 LIMIT 1", [':certno'=>$certno, ':certname'=>$certname]);
	if($row){
		exit('{"code":-2,"msg":"账号:'.($row['phone']?$row['phone']:$row['email']).'(商户ID:'.$row['uid'].')已经使用此身份认证，是否将该认证信息关联到当前商户？关联需要输入商户ID '.$row['uid'].' 的商户密钥","uid":"'.$row['uid'].'"}');
	}*/
	if($certtype==1){
		$certcorpno=htmlspecialchars(strip_tags(trim($_POST['certcorpno'])));
		$certcorpname=htmlspecialchars(strip_tags(trim($_POST['certcorpname'])));
		if(empty($certcorpno) || empty($certcorpname))exit('{"code":-1,"msg":"公司名称和营业执照号码不能为空"}');
		$checkres = check_corp_cert($certcorpname, $certcorpno, $certname);
		if($checkres['code']!=0)exit('{"code":-1,"msg":"'.$checkres['msg'].'"}');
	}
	if($conf['cert_open'] == 1){ //支付宝身份验证
		switch($certcardtype){
			case 0: $alipaycerttype = 'IDENTITY_CARD'; break;
			case 1: $alipaycerttype = 'HOME_VISIT_PERMIT_HK_MC'; break;
			case 2: $alipaycerttype = 'HOME_VISIT_PERMIT_TAIWAN'; break;
			case 3: $alipaycerttype = 'RESIDENCE_PERMIT_HK_MC'; break;
			case 4: $alipaycerttype = 'RESIDENCE_PERMIT_TAIWAN'; break;
			case 5: $alipaycerttype = 'PERMANENT_RESIDENCE_FOREIGNER'; break;
			default: exit('{"code":-1,"msg":"未知的证件类型"}'); break;
		}
		if(!$conf['cert_channel'])exit('{"code":-1,"msg":"未配置支付宝身份验证通道"}');
		$channel = \lib\Channel::get($conf['cert_channel']);
		if(!$channel)exit('{"code":-1,"msg":"当前实名认证通道信息不存在"}');
		$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
		$alipay_config['return_url'] = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode($siteurl.'user/alipaycertok.php?state='.urlencode(authcode($uid, 'ENCODE', SYS_KEY)));
		try{
			$certify = new \Alipay\AlipayCertifyService($alipay_config);
			$outer_order_no = date("YmdHis").rand(000,999).$uid;
			$certifyResult = $certify->initialize($outer_order_no, $certname, $certno, $alipaycerttype, 'SMART_FACE');
		}catch(Exception $e){
			exit('{"code":-1,"msg":"支付宝接口返回异常'.$e->getMessage().'"}');
		}
		
		if(isset($certifyResult['certify_id'])){
			$_SESSION[$uid.'_certify']=true;
			$sqs=$DB->exec("update `pre_user` set `cert`=0,`certtype`=:certtype,`certmethod`=:certmethod,`certno`=:certno,`certname`=:certname,`certtoken`=:certtoken where `uid`=:uid", [':certtype'=>$certtype, ':certmethod'=>0, ':certno'=>$certno, ':certname'=>$certname, ':certtoken'=>$certifyResult['certify_id'], ':uid'=>$uid]);
			if($sqs!==false){
				if ($certtype==1) {
					$DB->exec("update `pre_user` set `certcorpno`=:certcorpno,`certcorpname`=:certcorpname where `uid`=:uid", [':certcorpno'=>$certcorpno, ':certcorpname'=>$certcorpname, ':uid'=>$uid]);
				}
				exit(json_encode(['code'=>1, 'msg'=>'ok', 'certify_id'=>$certifyResult['certify_id']]));
			}else{
				exit('{"code":-1,"msg":"保存信息失败'.$DB->error().'"}');
			}
		}else{
			exit('{"code":-1,"msg":"支付宝接口返回异常['.$certifyResult['sub_code'].']'.$certifyResult['sub_msg'].'"}');
		}
	}elseif($conf['cert_open'] == 2){ //手机号三要素实名认证
		if(empty($userrow['phone']))exit('{"code":-1,"msg":"你还未绑定手机号码"}');
		$res = check_cert($certno, $certname, $userrow['phone']);
		if($res['code']==0){
			$sqs=$DB->exec("update `pre_user` set `cert`=1,`certtype`=:certtype,`certmethod`=:certmethod,`certno`=:certno,`certname`=:certname,`certtime`=NOW() where `uid`=:uid", [':certtype'=>$certtype, ':certmethod'=>2, ':certno'=>$certno, ':certname'=>$certname, ':uid'=>$uid]);
			if($conf['cert_money']>0){
				changeUserMoney($uid, $conf['cert_money'], false, '实名认证');
			}
			exit('{"code":2,"msg":"恭喜您成功提交实名认证！"}');
		}else{
			exit('{"code":-1,"msg":"认证结果：'.$res['msg'].'"}');
		}
	}elseif($conf['cert_open'] == 3){ //支付宝实名信息验证
		if(!$conf['cert_channel'])exit('{"code":-1,"msg":"未配置支付宝实名信息验证通道"}');
		$channel = \lib\Channel::get($conf['cert_channel']);
		if(!$channel)exit('{"code":-1,"msg":"当前实名认证通道信息不存在"}');
		$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
		try{
			$certdoc = new \Alipay\AlipayCertdocService($alipay_config);
			$result = $certdoc->preconsult($certname, $certno);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"支付宝接口返回异常'.$e->getMessage().'"}');
		}
		
		$_SESSION[$uid.'_certify']=true;
		$sqs=$DB->exec("update `pre_user` set `cert`=0,`certtype`=:certtype,`certmethod`=:certmethod,`certno`=:certno,`certname`=:certname,`certtoken`=:certtoken where `uid`=:uid", [':certtype'=>$certtype, ':certmethod'=>0, ':certno'=>$certno, ':certname'=>$certname, ':certtoken'=>$result['verify_id'], ':uid'=>$uid]);
		if($sqs!==false){
			if ($certtype==1) {
				$DB->exec("update `pre_user` set `certcorpno`=:certcorpno,`certcorpname`=:certcorpname where `uid`=:uid", [':certcorpno'=>$certcorpno, ':certcorpname'=>$certcorpname, ':uid'=>$uid]);
			}
			exit(json_encode(['code'=>1, 'msg'=>'ok', 'verify_id'=>$result['verify_id']]));
		}else{
			exit('{"code":-1,"msg":"保存信息失败'.$DB->error().'"}');
		}
	}elseif($conf['cert_open'] == 4){ //微信扫码实名认证
		if(!$conf['cert_qcloudid'] || !$conf['cert_qcloudkey'])exit('{"code":-1,"msg":"未配置腾讯云SecretId和SecretKey"}');
		$qcloud = new \lib\QcloudFaceid($conf['cert_qcloudid'], $conf['cert_qcloudkey']);
		$callbackurl = $siteurl.'user/alipaycertok.php?state='.$uid;
		$result = $qcloud->GetRealNameAuthToken($certname, $certno, $callbackurl);
		if(isset($result['AuthToken'])){
			$_SESSION[$uid.'_certify']=true;
			$_SESSION['qrcode_url'] = $result['RedirectURL'];
			$sqs=$DB->exec("update `pre_user` set `cert`=0,`certtype`=:certtype,`certmethod`=:certmethod,`certno`=:certno,`certname`=:certname,`certtoken`=:certtoken where `uid`=:uid", [':certtype'=>$certtype, ':certmethod'=>1, ':certno'=>$certno, ':certname'=>$certname, ':certtoken'=>$result['AuthToken'], ':uid'=>$uid]);
			if($sqs!==false){
				if ($certtype==1) {
					$DB->exec("update `pre_user` set `certcorpno`=:certcorpno,`certcorpname`=:certcorpname where `uid`=:uid", [':certcorpno'=>$certcorpno, ':certcorpname'=>$certcorpname, ':uid'=>$uid]);
				}
				exit(json_encode(['code'=>1, 'msg'=>'ok', 'wx_token'=>$result['AuthToken']]));
			}else{
				exit('{"code":-1,"msg":"保存信息失败'.$DB->error().'"}');
			}
		}else{
			exit('{"code":-1,"msg":"接口返回异常['.$result['Error']['Code'].']'.$result['Error']['Message'].'"}');
		}
	}elseif($conf['cert_open'] == 5){ //阿里云金融级实人认证
		if(!$conf['cert_aliyunid'] || !$conf['cert_aliyunkey'] || !$conf['cert_aliyunsceneid'])exit('{"code":-1,"msg":"未配置阿里云接口信息"}');
		$aliyun = new \lib\AliyunCertify($conf['cert_aliyunid'], $conf['cert_aliyunkey'], $conf['cert_aliyunsceneid']);
		$outer_order_no = date("YmdHis").rand(000,999).$uid;
		$return_url = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode($siteurl.'user/alipaycertok.php?state='.urlencode(authcode($uid, 'ENCODE', SYS_KEY)));
		$result = $aliyun->initialize($outer_order_no, $certname, $certno, $return_url);
        if (isset($result['Code']) && $result['Code']==200) {
			$_SESSION[$uid.'_certify']=true;
			$_SESSION['qrcode_url'] = $result['Data']['certifyUrl'];
			$sqs=$DB->exec("update `pre_user` set `cert`=0,`certtype`=:certtype,`certmethod`=:certmethod,`certno`=:certno,`certname`=:certname,`certtoken`=:certtoken where `uid`=:uid", [':certtype'=>$certtype, ':certmethod'=>0, ':certno'=>$certno, ':certname'=>$certname, ':certtoken'=>$result['Data']['certifyId'], ':uid'=>$uid]);
			if($sqs!==false){
				if ($certtype==1) {
					$DB->exec("update `pre_user` set `certcorpno`=:certcorpno,`certcorpname`=:certcorpname where `uid`=:uid", [':certcorpno'=>$certcorpno, ':certcorpname'=>$certcorpname, ':uid'=>$uid]);
				}
				exit(json_encode(['code'=>1, 'msg'=>'ok', 'certify_id'=>$result['Data']['certifyId']]));
			}else{
				exit('{"code":-1,"msg":"保存信息失败'.$DB->error().'"}');
			}
        }else{
			exit('{"code":-1,"msg":"接口返回异常['.$result['Code'].']'.$result['Message'].'"}');
		}
	}else{
		exit('{"code":-1,"msg":"网站未开启实名认证功能"}');
	}
break;
case 'cert_geturl':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(isset($_SESSION[$uid.'_certify'])){
		if($conf['cert_open'] == 1){
			$url = $siteurl.'user/alipaycert.php?uid='.$uid.'&certtoken='.$userrow['certtoken'];
			exit(json_encode(['code'=>1, 'msg'=>'ok', 'url'=>$url]));
		}elseif($conf['cert_open'] == 3){
			$channel = \lib\Channel::get($conf['cert_channel']);
			if(!$channel)exit('{"code":-1,"msg":"当前实名认证通道信息不存在"}');
			$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
			$certdoc = new \Alipay\AlipayCertdocService($alipay_config);
			$redirect_uri = $siteurl.'user/alipaycertok.php';
			$state = authcode($uid, 'ENCODE', SYS_KEY);
			$url = $certdoc->oauth($redirect_uri, $userrow['certtoken'], $state, true);
			exit(json_encode(['code'=>1, 'msg'=>'ok', 'url'=>$url]));
		}else{
			$url = $_SESSION['qrcode_url'];
			if(!$url)exit('{"code":-1,"msg":"二维码图片不存在"}');
			exit(json_encode(['code'=>1, 'msg'=>'ok', 'url'=>$url]));
		}
	}else{
		exit('{"code":-1,"msg":"Access Denied"}');
	}
break;
case 'cert_query':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$cert = $DB->getColumn("select cert from pre_user where uid=$uid");
	if($cert == 1){
		unset($_SESSION[$uid.'_certify']);
		unset($_SESSION['qrcode_url']);
		exit('{"code":1,"msg":"succ","passed":true}');
	}else{
		exit('{"code":1,"msg":"succ","passed":false}');
	}
break;
case 'order': //订单详情
	$trade_no=$_GET['trade_no'];
	$row=$DB->getRow("select A.*,B.showname typename from pre_order A left join pre_type B on A.type=B.id where trade_no=:trade_no and uid=:uid limit 1", [':trade_no'=>$trade_no, ':uid'=>$uid]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$row['subchannelname'] = $row['subchannel'] > 0 ? $DB->findColumn('subchannel', 'name', ['id'=>$row['subchannel']]) : '';
	if($row['status']==2){
		$row['refundtime'] = $DB->findColumn('refundorder', 'addtime', ['trade_no'=>$trade_no], 'refund_no DESC');
	}
	$result=array("code"=>0,"msg"=>"succ","data"=>$row);
	exit(json_encode($result));
break;
case 'subOrders':
	$trade_no=trim($_GET['trade_no']);
	$list = \lib\Payment::getSubOrders($trade_no);
	exit(json_encode(['code'=>0, 'data'=>$list, 'settle'=>$DB->findColumn('order', 'settle', ['trade_no'=>$trade_no])]));
break;
case 'notify':
	$trade_no=$_POST['trade_no'];
	$row=$DB->find('order', '*', ['trade_no'=>$trade_no, 'uid'=>$uid]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']==0)exit('{"code":-1,"msg":"订单尚未支付，无法重新通知！"}');
	$url=creat_callback($row);
	if($row['notify']>0)
		$DB->exec("update pre_order set notify=0 where trade_no=:trade_no", [':trade_no'=>$trade_no]);
	exit('{"code":0,"url":"'.($_POST['isreturn']==1?$url['return']:$url['notify']).'"}');
break;
case 'printOrder':
	$trade_no=$_POST['trade_no'];
	$row=$DB->find('order', '*', ['trade_no'=>$trade_no, 'uid'=>$uid]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']==0)exit('{"code":-1,"msg":"订单尚未支付，无法打印！"}');
	if(!$conf['orderprint'] || $userrow['print_order']==0)exit('{"code":-1,"msg":"未开启打印功能"}');
	$print_config = unserialize($userrow['print_config']);
	$typeshowname = $DB->findColumn('type', 'showname', ['id'=>$row['type']]);
	$param = ['trade_no'=>$row['trade_no'], 'name'=>$row['name'], 'money'=>$row['money'], 'type'=>$typeshowname, 'time'=>$row['endtime'], 'tid'=>$row['tid'], 'codename'=>$userrow['codename'], 'remark'=>$row['param']];
	try{
		(new \lib\Printer($conf['print_appid'], $conf['print_appsecret']))->print($print_config['devid'], $param);
		exit('{"code":0,"msg":"打印成功"}');
	}catch(Exception $e){
		exit('{"code":-1,"msg":"打印失败：'.$e->getMessage().'"}');
	}
break;
case 'settle_result':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select result from pre_settle where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前结算记录不存在！"}');
	$result = ['code'=>0,'msg'=>$row['result']?$row['result']:'未知'];
	exit(json_encode($result));
break;
case 'recharge':
	$money=trim(daddslashes($_POST['money']));
	$typeid=intval($_POST['typeid']);
	$name = '充值余额 UID:'.$uid;
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($userrow['pay']==0)exit('{"code":-1,"msg":"当前商户已被封禁"}');
	//if($conf['cert_force']==1 && $userrow['cert']==0)exit('{"code":-1,"msg":"当前商户未完成实名认证，无法收款"}');
	if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额不合法"}');
	if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])exit('{"code":-1,"msg":"最大支付金额是'.$conf['pay_maxmoney'].'元"}');
	if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])exit('{"code":-1,"msg":"最小支付金额是'.$conf['pay_minmoney'].'元"}');
	$trade_no=date("YmdHis").rand(11111,99999);
	$return_url=$siteurl.'user/recharge.php?ok=1&trade_no='.$trade_no;
	$domain=getdomain($return_url);
	$param = json_encode(['uid'=>$uid]);
	if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`,`param`) VALUES (:trade_no, :out_trade_no, :uid, 2, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0, :param)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['reg_pay_uid'], ':name'=>$name, ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param]))exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');
	unset($_SESSION['csrf_token']);
	$result = ['code'=>0, 'msg'=>'succ', 'url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$trade_no];
	exit(json_encode($result));
break;
case 'groupinfo':
	$gid=intval($_POST['gid']);
	$row=$DB->getRow("select * from pre_group where gid='$gid' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前会员等级不存在！"}');
	if($row['isbuy']==0)
		exit('{"code":-1,"msg":"当前会员等级无法购买！"}');
	if($gid==$userrow['gid'] && $userrow['endtime']==null)exit('{"code":-1,"msg":"你已购买此会员等级，请勿重复购买"}');
	$result = ['code'=>0,'msg'=>'succ','gid'=>$gid,'name'=>$row['name'],'price'=>$row['price'],'expire'=>$row['expire']];
	exit(json_encode($result));
break;
case 'groupbuy':
	$gid=intval($_POST['gid']);
	$row=$DB->getRow("select * from pre_group where gid='$gid' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前会员等级不存在！"}');
	if($row['isbuy']==0)
		exit('{"code":-1,"msg":"当前会员等级无法购买！"}');
	if($gid==$userrow['gid'] && $userrow['endtime']==null)exit('{"code":-1,"msg":"你已购买此会员等级，请勿重复购买"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$money = $row['price'];
	$num=intval($_POST['num']);
	$typeid=intval($_POST['typeid']);
	if($num<=0 || $num>300)exit('{"code":-1,"msg":"数量不正确"}');
	$money = round($money * $num, 2);
	if($row['expire']>0){
		$expirenum = $num*$row['expire'];
		if($gid==$userrow['gid'])$endtime = date("Y-m-d",strtotime("+ {$expirenum} month", strtotime($userrow['endtime'])));
		else $endtime = date("Y-m-d",strtotime("+ {$expirenum} month"));
	}else{
		$endtime = null;
	}
	if($typeid==0){
		if($money>$userrow['money'])exit('{"code":-1,"msg":"余额不足，请选择其他方式支付"}');
		changeUserMoney($uid, $money, false, '购买会员');
		changeUserGroup($uid, $gid, $endtime);

		if($userrow['upid'] > 0){
			$upgid = $DB->findColumn('user', 'gid', ['uid'=>$userrow['upid']]);
			$groupconfig = getGroupConfig($upgid);
			$conf_n = array_merge($conf, $groupconfig);
			if($conf_n['invite_open'] == 1 && $conf_n['invite_groupbuy_rate'] > 0){
				$invite_money = round($money * $conf_n['invite_groupbuy_rate'] / 100, 2);
				if($invite_money > 0){
					changeUserMoney($userrow['upid'], $invite_money, true, '邀请购买会员');
				}
			}
		}

		unset($_SESSION['csrf_token']);
		$result = ['code'=>1, 'msg'=>'购买会员成功！'];
		exit(json_encode($result));
	}else{
		$name = '购买会员-'.$row['name'];
		$trade_no=date("YmdHis").rand(11111,99999);
		$return_url=$siteurl.'user/groupbuy.php?ok=1&trade_no='.$trade_no;
		$domain=getdomain($return_url);
		$param = json_encode(['uid'=>$uid, 'gid'=>$gid, 'endtime'=>$endtime]);
		if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`,`param`) VALUES (:trade_no, :out_trade_no, :uid, 4, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0, :param)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['reg_pay_uid'], ':name'=>$name, ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param]))exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');
		unset($_SESSION['csrf_token']);
		$result = ['code'=>0, 'msg'=>'succ', 'url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$trade_no];
		exit(json_encode($result));
	}
break;
case 'addDomain':
	if(!$conf['pay_domain_open']) exit('{"code":-1,"msg":"未开启授权支付域名添加"}');
	$domain = trim(daddslashes($_POST['domain']));
	if(empty($domain))exit('{"code":-1,"msg":"域名不能为空"}');
	if(!checkDomain($domain))exit('{"code":-1,"msg":"域名格式不正确"}');
	if($DB->getRow("select * from pre_domain where uid=:uid and domain=:domain limit 1", [':uid'=>$uid, ':domain'=>$domain]))
		exit('{"code":-1,"msg":"该域名已存在，请勿重复添加"}');
	if(!$DB->exec("INSERT INTO `pre_domain` (`uid`,`domain`,`status`,`addtime`) VALUES (:uid, :domain, 0, NOW())", [':uid'=>$uid, ':domain'=>$domain]))exit('{"code":-1,"msg":"添加失败'.$DB->error().'"}');
	\lib\MsgNotice::send('domain', 0, ['uid'=>$uid, 'domain'=>$domain]);
	exit(json_encode(['code'=>0, 'msg'=>'添加域名成功！']));
break;
case 'delDomain':
	if(!$conf['pay_domain_open']) exit('{"code":-1,"msg":"未开启授权支付域名添加"}');
	$id = intval($_POST['id']);
	if(!$DB->exec("DELETE FROM pre_domain WHERE id='$id' and uid='$uid'"))exit('{"code":-1,"msg":"删除失败'.$DB->error().'"}');
	exit(json_encode(['code'=>0, 'msg'=>'succ']));
break;

case 'orderList':
	$paytype = [];
	$paytypes = [];
	$rs = $DB->getAll("SELECT * FROM pre_type WHERE status=1");
	foreach($rs as $row){
		$paytype[$row['id']] = $row['showname'];
		$paytypes[$row['id']] = $row['name'];
	}
	unset($rs);

	$sql=" A.uid=$uid";
	if(isset($_POST['paytype']) && !empty($_POST['paytype'])) {
		$type = intval($_POST['paytype']);
		$sql.=" AND A.`type`='$type'";
	}elseif(isset($_POST['channel']) && !empty($_POST['channel'])) {
		$channel = intval($_POST['channel']);
		$sql.=" AND A.`channel`='$channel'";
	}elseif(isset($_POST['subchannel']) && !empty($_POST['subchannel'])) {
		$subchannel = intval($_POST['subchannel']);
		$sql.=" AND A.`subchannel`='$subchannel'";
	}elseif(isset($_POST['applyid']) && !empty($_POST['applyid'])) {
		$applyid = intval($_POST['applyid']);
		$sql.=" AND A.`subchannel` IN (SELECT id FROM pre_subchannel WHERE apply_id='{$applyid}')";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND A.status='{$dstatus}'";
	}
	if(!empty($_POST['starttime']) || !empty($_POST['endtime'])){
		if(!empty($_POST['starttime'])){
			$starttime = daddslashes($_POST['starttime']);
			$sql.=" AND A.addtime>='{$starttime} 00:00:00'";
		}
		if(!empty($_POST['endtime'])){
			$endtime = daddslashes($_POST['endtime']);
			$sql.=" AND A.addtime<='{$endtime} 23:59:59'";
		}
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND A.`trade_no`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND A.`out_trade_no`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND A.`name` like '%{$kw}%'";
		}elseif($_POST['type']==4){
			$sql.=" AND A.`money`='{$kw}'";
		}elseif($_POST['type']==5){
			$sql.=" AND A.`realmoney`='{$kw}'";
		}elseif($_POST['type']==6){
			$sql.=" AND A.`domain`='{$kw}'";
		}elseif($_POST['type']==7){
			$sql.=" AND A.`ip`='{$kw}'";
		}elseif($_POST['type']==8){
			$sql.=" AND A.`buyer`='{$kw}'";
		}elseif($_POST['type']==9){
			$sql.=" AND A.`api_trade_no`='{$kw}'";
		}elseif($_POST['type']==10){
			$sql.=" AND A.`bill_trade_no`='{$kw}'";
		}elseif($_POST['type']==11){
			$sql.=" AND A.`bill_mch_trade_no`='{$kw}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_order A WHERE{$sql}");
	$list = $DB->getAll("SELECT A.*,B.plugin,C.apply_id submchid FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id LEFT JOIN pre_subchannel C ON A.subchannel=C.id WHERE{$sql} order by trade_no desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['typename'] = $paytypes[$row['type']];
		$row['typeshowname'] = $paytype[$row['type']];
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'statistics':
    $sql=" A.uid=$uid";
	if(isset($_POST['paytype']) && !empty($_POST['paytype'])) {
		$type = intval($_POST['paytype']);
		$sql.=" AND A.`type`='$type'";
	}elseif(isset($_POST['channel']) && !empty($_POST['channel'])) {
		$channel = intval($_POST['channel']);
		$sql.=" AND A.`channel`='$channel'";
	}elseif(isset($_POST['subchannel']) && !empty($_POST['subchannel'])) {
		$subchannel = trim($_POST['subchannel']);
		$subchannel = explode('|', $subchannel);
		$subchannel = array_map('intval', $subchannel);
		$sql.=" AND A.`subchannel` IN (".implode(",", $subchannel).")";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND A.status='{$dstatus}'";
	}
	if(!empty($_POST['starttime']) || !empty($_POST['endtime'])){
		if(!empty($_POST['starttime'])){
			$starttime = daddslashes($_POST['starttime']);
			$sql.=" AND A.addtime>='{$starttime} 00:00:00'";
		}
		if(!empty($_POST['endtime'])){
			$endtime = daddslashes($_POST['endtime']);
			$sql.=" AND A.addtime<='{$endtime} 23:59:59'";
		}
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND A.`trade_no`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND A.`out_trade_no`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND A.`name` like '%{$kw}%'";
		}elseif($_POST['type']==4){
			$sql.=" AND A.`money`='{$kw}'";
		}elseif($_POST['type']==5){
			$sql.=" AND A.`realmoney`='{$kw}'";
		}elseif($_POST['type']==6){
			$sql.=" AND A.`domain`='{$kw}'";
		}elseif($_POST['type']==7){
			$sql.=" AND A.`ip`='{$kw}'";
		}elseif($_POST['type']==8){
			$sql.=" AND A.`buyer`='{$kw}'";
		}elseif($_POST['type']==9){
			$sql.=" AND A.`api_trade_no`='{$kw}'";
		}elseif($_POST['type']==10){
			$sql.=" AND A.`bill_trade_no`='{$kw}'";
		}
	}
    // 统计数据
    $resultMoneyData = $DB->getRow("SELECT 
    SUM(money) AS totalMoney,
    SUM(CASE WHEN A.status = 1 THEN money ELSE 0 END) AS successMoney,
    SUM(CASE WHEN A.status = 0 THEN money ELSE 0 END) AS unpaidMoney,
    SUM(CASE WHEN A.status = 2 THEN refundmoney ELSE 0 END) AS refundMoney
    FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql} order by trade_no desc");

    $resultCount = $DB->getRow("SELECT 
    COUNT(*) AS totalCount,
    SUM(CASE WHEN A.status = 1 THEN 1 ELSE 0 END) AS successCount,
    SUM(CASE WHEN A.status = 0 THEN 1 ELSE 0 END) AS unpaidCount,
    SUM(CASE WHEN A.status = 2 THEN 1 ELSE 0 END) AS refundCount
    FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql} order by trade_no desc");

    // 获取平台总收入利润
    $platformProfit = $DB->getColumn("SELECT SUM(A.profitmoney) FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE {$sql} AND status = 1 order by trade_no desc");

	$result = [
        'totalMoney' => number_format($resultMoneyData['totalMoney'], 2, '.', '') ?? 0.00,
        'successMoney' => number_format($resultMoneyData['successMoney'], 2, '.', '') ?? 0.00,
        'unpaidMoney' => number_format($resultMoneyData['unpaidMoney'], 2, '.', '') ?? 0.00,
        'refundMoney' => number_format($resultMoneyData['refundMoney'], 2, '.', '') ?? 0.00,
        'totalCount' => $resultCount['totalCount'] ?? '0',
        'successCount' => $resultCount['successCount'] ?? '0',
        'unpaidCount' => $resultCount['unpaidCount'] ?? '0',
        'refundCount' => $resultCount['refundCount'] ?? '0',
        'platformProfit' => number_format($platformProfit, 2, '.', '') ?? 0.00
    ];
	$result['successRate'] = $result['totalCount'] > 0 ? round(($result['totalCount']-$result['unpaidCount']) / $result['totalCount'] * 100, 2) : 0;
	exit(json_encode(['code'=>0, 'data'=>$result]));
break;

case 'recordList':
	$sql=" uid=$uid";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND `type`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND `money`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND `trade_no`='{$kw}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_record WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_record WHERE{$sql} order by id desc limit $offset,$limit");

	exit(json_encode(['total'=>$total, 'rows'=>$list]));
break;
case 'settleList':
	$sql=" uid=$uid";
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND status='{$dstatus}'";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_settle WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_settle WHERE{$sql} order by id desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		if($row['type'] == 2 && $row['status'] == 1 && !empty($row['transfer_ext']) && time() - strtotime($row['transfer_date']) <= 86400){
			if(substr($row['ext'], 0, 4) == 'http'){
				$row['jumpurl'] = $row['ext'];
			}else{
				$row['jumpurl'] = $siteurl.'paypage/wxtrans.php?id='.$row['id'].'&type=settle';
			}
		}
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'transferList':
	$sql=" uid=$uid";
	if(isset($_POST['paytype']) && !empty($_POST['paytype'])) {
		$type = intval($_POST['paytype']);
		$sql.=" AND `type`='$type'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `status`='{$dstatus}'";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND `biz_no`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND `out_biz_no`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND `pay_order_no`='{$kw}'";
		}elseif($_POST['type']==4){
			$sql.=" AND `account`='{$kw}'";
		}elseif($_POST['type']==5){
			$sql.=" AND `username` LIKE '%{$kw}%'";
		}elseif($_POST['type']==6){
			$sql.=" AND `money`='{$kw}'";
		}
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
case 'transfer_statistics':
	$sql=" uid=$uid";
	if(isset($_POST['paytype']) && !empty($_POST['paytype'])) {
		$type = intval($_POST['paytype']);
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
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND `biz_no`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND `out_biz_no`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND `pay_order_no`='{$kw}'";
		}elseif($_POST['type']==4){
			$sql.=" AND `account`='{$kw}'";
		}elseif($_POST['type']==5){
			$sql.=" AND `username`='{$kw}'";
		}elseif($_POST['type']==6){
			$sql.=" AND `money`='{$kw}'";
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

case 'transfer_result':
	$biz_no=trim($_GET['biz_no']);
	$row=$DB->find('transfer', 'result', ['biz_no'=>$biz_no, 'uid'=>$uid]);
	if(!$row)
		exit('{"code":-1,"msg":"当前付款记录不存在！"}');
	$result = ['code'=>0,'msg'=>$row['result']?$row['result']:'未知'];
	exit(json_encode($result));
break;
case 'transfer_query':
	$biz_no=trim($_GET['biz_no']);
	$result = \lib\Transfer::status($biz_no);
	exit(json_encode($result));
break;
case 'transfer_proof':
	$biz_no=trim($_POST['biz_no']);
	$result = \lib\Transfer::proof($biz_no);
	exit(json_encode($result));
break;

case 'refund_query': //退款查询
	$trade_no=daddslashes(trim($_POST['trade_no']));
	$result = \lib\Order::refund_info($trade_no, 1, $uid);
	exit(json_encode($result));
break;
case 'refund_submit': //确认退款
	$trade_no=daddslashes(trim($_POST['trade_no']));
	$pwd=trim($_POST['pwd']);
	$money = trim($_POST['money']);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额输入错误"}');
	if(getMd5Pwd($pwd, $userrow['uid'])!=$userrow['pwd'])
		exit('{"code":-1,"msg":"登录密码输入错误！"}');
	
	$refund_no = date("YmdHis").rand(11111,99999);
	$result = \lib\Order::refund($refund_no, $trade_no, $money, 1, $uid);
	if($result['code'] == 0){
		$result['msg'] = '退款成功！退款金额¥'.$result['money'];
	}
	exit(json_encode($result));
break;

case 'inviteStat':
	$lastday=date("Y-m-d",strtotime("-1 day")).' 00:00:00';
	$today=date("Y-m-d").' 00:00:00';

	$invite_users=$DB->getColumn("SELECT count(*) FROM pre_user WHERE upid={$uid}");
	$income_today=$DB->getColumn("SELECT sum(money) FROM pre_record WHERE uid={$uid} AND type='邀请返现' AND date>='$today'");
	$income_today=round($income_today,2);
	$income_lastday=$DB->getColumn("SELECT sum(money) FROM pre_record WHERE uid={$uid} AND type='邀请返现' AND date>='$lastday' AND date<'$today'");
	$income_lastday=round($income_lastday,2);

	$result=['code'=>0, 'invite_users'=>$invite_users, 'income_today'=>$income_today, 'income_lastday'=>$income_lastday];
	exit(json_encode($result));
break;
case 'inviteList':
	$sql=" upid=$uid";
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_user WHERE{$sql}");
	$list = $DB->getAll("SELECT uid,upid,addtime,lasttime,status FROM pre_user WHERE{$sql} order by uid desc limit $offset,$limit");

	exit(json_encode(['total'=>$total, 'rows'=>$list]));
break;

case 'deposit_recharge':
	$money=trim(daddslashes($_POST['money']));
	$typeid=intval($_POST['typeid']);
	if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额不合法"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($typeid==0){
		if($money>$userrow['money'])exit('{"code":-1,"msg":"余额不足，请选择其他方式支付"}');
		changeUserMoney($uid, $money, false, '充值保证金');
		$deposit = $userrow['deposit'] > 0 ? round($userrow['deposit'] + $money, 2) : $money;
		$DB->exec("UPDATE pre_user SET deposit=:deposit WHERE uid=:uid", [':deposit'=>$deposit, ':uid'=>$uid]);
		unset($_SESSION['csrf_token']);
		$result = ['code'=>1, 'msg'=>'成功充值'.$money.'元保证金！'];
		exit(json_encode($result));
	}else{
		$name = '充值保证金 UID:'.$uid;
		if($userrow['pay']==0)exit('{"code":-1,"msg":"当前商户已被封禁"}');
		if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])exit('{"code":-1,"msg":"最大支付金额是'.$conf['pay_maxmoney'].'元"}');
		if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])exit('{"code":-1,"msg":"最小支付金额是'.$conf['pay_minmoney'].'元"}');
		$trade_no=date("YmdHis").rand(11111,99999);
		$return_url=$siteurl.'user/deposit.php?ok=1&trade_no='.$trade_no;
		$domain=getdomain($return_url);
		$param = json_encode(['uid'=>$uid]);
		if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`,`param`) VALUES (:trade_no, :out_trade_no, :uid, 5, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0, :param)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['reg_pay_uid'], ':name'=>$name, ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param]))exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');
		unset($_SESSION['csrf_token']);
		$result = ['code'=>0, 'msg'=>'succ', 'url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$trade_no];
		exit(json_encode($result));
	}
break;
case 'deposit_withdraw':
	$money=trim(daddslashes($_POST['money']));
	if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额不合法"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($money>$userrow['deposit'])exit('{"code":-1,"msg":"保证金不足"}');
	if($conf['user_deposit_day']>0){
		$days = intval($conf['user_deposit_day']);
		$orders = $DB->getColumn("SELECT count(*) FROM pre_order WHERE uid='{$uid}' AND status=1 AND addtime>DATE_SUB(NOW(),INTERVAL {$days} DAY)");
		if($orders>0)exit('{"code":-1,"msg":"你在最近'.$days.'天内有订单，无法提取保证金"}');
		$complains = $DB->getColumn("SELECT count(*) FROM pre_complain WHERE uid='{$uid}' AND addtime>DATE_SUB(NOW(),INTERVAL {$days} DAY)");
		if($complains>0)exit('{"code":-1,"msg":"你在最近'.$days.'天内有投诉记录，无法提取保证金"}');
	}
	$deposit = round($userrow['deposit'] - $money, 2);
	$DB->exec("UPDATE pre_user SET deposit=:deposit WHERE uid=:uid", [':deposit'=>$deposit, ':uid'=>$uid]);
	changeUserMoney($uid, $money, true, '提取保证金');
	unset($_SESSION['csrf_token']);
	$result = ['code'=>0, 'msg'=>'成功提取'.$money.'元保证金！'];
	exit(json_encode($result));
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}