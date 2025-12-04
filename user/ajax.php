<?php
include("../includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'sysinfo':
	$data = [
		'sitename' => $conf['sitename'],
		'public_key' => $conf['public_key'],
		'reg' => $conf['reg_open']==1,
		'reg_pay' => $conf['reg_pay']==1,
		'reg_pay_price' => $conf['reg_pay_price'],
		'reg_invitecode' => $conf['reg_open']==2,
		'login_alipay' => $conf['login_alipay']>0 || $conf['login_alipay']==-1,
		'login_qq' => $conf['login_qq']>0 || $conf['login_qq']==-1,
		'login_wx' => $conf['login_wx']>0 || $conf['login_wx']==-1,
		'login_key' => !$conf['close_keylogin'],
	];
	exit(json_encode(['code'=>0, 'data'=>$data]));
break;
case 'testpay':
	if(!$conf['test_open'])exit('{"code":-1,"msg":"未开启测试支付"}');
	$money=trim($_POST['money']);
	$typeid=intval($_POST['typeid']);
	$name = '支付测试';
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额不合法"}');
	if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])exit('{"code":-1,"msg":"最大支付金额是'.$conf['pay_maxmoney'].'元"}');
	if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])exit('{"code":-1,"msg":"最小支付金额是'.$conf['pay_minmoney'].'元"}');
	if($conf['captcha_open_test']==1){
		if(!isset($_SESSION['gtserver']))exit('{"code":-1,"msg":"验证加载失败"}');
		if(!verify_captcha())exit('{"code":-1,"msg":"验证失败，请重新验证"}');
	}

	$trade_no=date("YmdHis").rand(11111,99999);
	$return_url=$siteurl.'user/test.php?ok=1&trade_no='.$trade_no;
	$domain=getdomain($return_url);
	if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`) VALUES (:trade_no, :out_trade_no, :uid, 3, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['test_pay_uid'], ':name'=>$name, ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip]))exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');
	$result = ['code'=>0, 'msg'=>'succ', 'url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$trade_no];
	exit(json_encode($result));
break;
case 'login':
	$type=intval($_POST['type']);
	$user=trim($_POST['user']);
	$pass=trim($_POST['pass']);
	$enc_type = isset($_POST['enc']) ? $_POST['enc'] : '0';
	if(empty($user) || empty($pass))exit('{"code":-1,"msg":"请确保各项不能为空"}');
	//if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');

	if($conf['captcha_open_login']==1){
		if(!isset($_SESSION['gtserver']))exit('{"code":-1,"msg":"验证加载失败"}');
		if(!verify_captcha())exit('{"code":-1,"msg":"验证失败，请重新验证"}');
	}

	if($enc_type == '1'){
		$plain = '';
		$private_key = base64ToPem($conf['private_key'], 'PRIVATE KEY');
		$pkey = openssl_pkey_get_private($private_key);
		if(!openssl_private_decrypt(base64_decode($pass), $plain, $pkey, OPENSSL_PKCS1_PADDING)){
			exit(json_encode(['code'=>-1,'msg'=>'密码解密失败']));
		}
		$pass = $plain;
	}
	if($type==1 && is_numeric($user) && strlen($user)<=6)$type=0;
	if($type==1){
		$userrow=$DB->getRow("SELECT * FROM pre_user WHERE email=:user OR phone=:user limit 1", [':user'=>$user]);
		$pass=getMd5Pwd($pass, $userrow['uid']);
	}else{
		if($conf['close_keylogin']==1)exit('{"code":-1,"msg":"未开启密钥登录，请使用账号密码登录！"}');
		$userrow=$DB->getRow("SELECT * FROM pre_user WHERE uid=:user limit 1", [':user'=>$user]);
		if($userrow && $userrow['keylogin']==0){
			exit('{"code":-1,"msg":"该商户未开启密钥登录，请使用账号密码登录！"}');
		}
	}
	if($userrow && ($type==0 && $pass==$userrow['key'] || $type==1 && $pass==$userrow['pwd'])) {
		$uid = $userrow['uid'];
		if($alipay_uid=$_SESSION['Oauth_alipay_uid']){
			$DB->update('user', ['alipay_uid'=>$alipay_uid], ['uid'=>$uid]);
			unset($_SESSION['Oauth_alipay_uid']);
		}
		if($qq_uid=$_SESSION['Oauth_qq_uid']){
			$DB->update('user', ['qq_uid'=>$qq_uid], ['uid'=>$uid]);
			unset($_SESSION['Oauth_qq_uid']);
		}
		if($wxa_uid=$_SESSION['Oauth_wxa_uid']){
			$DB->update('user', ['wxa_uid'=>$wxa_uid], ['uid'=>$uid]);
			unset($_SESSION['Oauth_wxa_uid']);
		}
		$city=get_ip_city($clientip);
		$DB->insert('log', ['uid'=>$uid, 'type'=>'普通登录', 'date'=>'NOW()', 'ip'=>$clientip, 'city'=>$city]);

		if(!isset($_SESSION['wxnotice_login_uid']) || $_SESSION['wxnotice_login_uid']!=$uid){
			if(\lib\MsgNotice::send('login', $uid, ['user'=>$user, 'clientip'=>$clientip, 'ipinfo'=>$city, 'time'=>date('Y-m-d H:i:s')])){
				$_SESSION['wxnotice_login_uid'] = $uid;
			}
		}
		$session=md5($uid.$userrow['key'].$password_hash);
		$expiretime=time()+604800;
		$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		ob_clean();
		setcookie("user_token", $token, time() + 2592000);
		$DB->exec("update `pre_user` set `lasttime`=NOW() where `uid`='$uid'");
		if(empty($userrow['account']) || empty($userrow['username'])){
			$result=array("code"=>0,"user_token"=>$token,"msg"=>"登录成功！正在跳转到收款账号设置","url"=>"./editinfo.php?start=1");
		}else{
			$result=array("code"=>0,"user_token"=>$token,"msg"=>"登录成功！正在跳转到用户中心","url"=>"./");
		}
		unset($_SESSION['csrf_token']);
	}else {
		$result=array("code"=>-1,"msg"=>"用户名或密码不正确！");
	}
	exit(json_encode($result));
break;
case 'wxalogin':
	$code = isset($_POST['code'])?trim($_POST['code']):exit('{"code":-1,"msg":"code不能为空"}');
	$wxinfo = \lib\Channel::getWeixin($conf['login_wxa']);
	if(!$wxinfo)exit('{"code":-1,"msg":"快捷登录微信小程序不存在"}');
	try{
		$openid = wechat_applet_oauth($code, $wxinfo);
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}
	$userrow=$DB->getRow("SELECT * FROM pre_user WHERE wxa_uid='$openid' LIMIT 1");
	if($userrow){
		$uid=$userrow['uid'];
		$key=$userrow['key'];
		$DB->insert('log', ['uid'=>$uid, 'type'=>'微信快捷登录', 'date'=>'NOW()', 'ip'=>$clientip]);
		$session=md5($uid.$key.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		setcookie("user_token", $token, time() + 2592000);
		$DB->exec("update `pre_user` set `lasttime`=NOW() where `uid`='$uid'");
		$result=array("code"=>0,"user_token"=>$token,"msg"=>"登录成功！");
		exit(json_encode($result));
	}else{
		$_SESSION['Oauth_wxa_uid'] = $openid;
		exit('{"code":-2,"msg":"未绑定"}');
	}
break;
case 'connect':
	$type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');
	$bind = isset($_POST['bind'])?$_POST['bind']:null;
	if($type == 'qq' && $conf['login_qq']==3 || $type == 'wx' && $conf['login_wx']==-1 || $type == 'alipay' && $conf['login_alipay']==-1){
		if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好聚合登录信息"}');
		$Oauth_config = [
			'apiurl' => $conf['login_apiurl'],
			'appid' => $conf['login_appid'],
			'appkey' => $conf['login_appkey'],
			'callback' => $siteurl.'user/connect.php'
		];
		$Oauth = new \lib\Oauth($Oauth_config);
		$res = $Oauth->login($type);
		if(isset($res['code']) && $res['code']==0){
			$result = ['code'=>0, 'url'=>$res['url']];
		}elseif(isset($res['code'])){
			$result = ['code'=>-1, 'msg'=>$res['msg']];
		}else{
			$result = ['code'=>-1, 'msg'=>'聚合登录接口请求失败'];
		}
	}elseif($type == 'qq' && $conf['login_qq']==1){
		$QC_config = [
			'appid' => $conf['login_qq_appid'],
			'appkey' => $conf['login_qq_appkey'],
			'callback' => $siteurl.'user/connect.php'
		];
		$QC=new \lib\QC($QC_config);
		$url = $QC->qq_login(true);
		$result = ['code'=>0, 'url'=>$url];
	}elseif($type == 'qq' && $conf['login_qq']==2){
		$result = ['code'=>0, 'url'=>'connect.php'.($bind=='1'?'?bind=1':'')];
	}elseif($type == 'wx' && $conf['login_wx']>0){
		$result = ['code'=>0, 'url'=>'wxlogin.php'.($bind=='1'?'?bind=1':'')];
	}elseif($type == 'alipay' && $conf['login_alipay']>0){
		$result = ['code'=>0, 'url'=>'oauth.php'.($bind=='1'?'?bind=1':'')];
	}else{
		$result = ['code'=>-1, 'msg'=>'未开启当前登录方式'];
	}
	exit(json_encode($result));
break;
case 'captcha':
	if($conf['captcha_version'] == '1'){
		$captcha_id = !empty($conf['captcha_id'])?$conf['captcha_id']:'54088bb07d2df3c46b79f80300b0abbe';
		$result = ['success'=>1, 'gt'=>$captcha_id, 'version'=>1];
	}else{
		$GtSdk = new \lib\GeetestLib($conf['captcha_id'], $conf['captcha_key']);
		$data = array(
			'user_id' => isset($uid)?$uid:'public',
			'client_type' => "web",
			'ip_address' => $clientip
		);
		$result = $GtSdk->pre_process($data);
		$result['version'] = 0;
	}
	$_SESSION['gtserver'] = $result['success'];
	exit(json_encode($result));
break;
case 'sendcode':
	$sendto=htmlspecialchars(strip_tags(trim($_POST['sendto'])));
	if($conf['reg_open']==0)exit('{"code":-1,"msg":"未开放商户申请"}');
	if(isset($_SESSION['send_code_time']) && $_SESSION['send_code_time']>time()-10){
		exit('{"code":-1,"msg":"请勿频繁发送验证码"}');
	}

	if(!isset($_SESSION['gtserver']))exit('{"code":-1,"msg":"验证加载失败"}');
	if(!verify_captcha())exit('{"code":-1,"msg":"验证失败，请重新验证"}');

	if($conf['verifytype']==1){
		$row=$DB->getRow("select * from pre_user where phone=:phone limit 1", [':phone'=>$sendto]);
		if($row){
			exit('{"code":-1,"msg":"该手机号已经注册过商户，如需找回商户信息，请返回登录页面点击找回商户"}');
		}
		$type = 1;
	}else{
		$row=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$sendto]);
		if($row){
			exit('{"code":-1,"msg":"该邮箱已经注册过商户，如需找回商户信息，请返回登录页面点击找回商户"}');
		}
		$type = 0;
	}
	$result = \lib\VerifyCode::send_code('reg', $type, $sendto);
	if($result === true){
		$_SESSION['send_code_time']=time();
		exit('{"code":0,"msg":"succ"}');
	}else{
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
break;
case 'reg':
	if($conf['reg_open']==0)exit('{"code":-1,"msg":"未开放商户申请"}');
	$email=htmlspecialchars(strip_tags(trim($_POST['email'])));
	$phone=htmlspecialchars(strip_tags(trim($_POST['phone'])));
	$code=trim($_POST['code']);
	$pwd=trim($_POST['pwd']);
	$invitecode=trim($_POST['invitecode']);
	$enc_type = isset($_POST['enc']) ? $_POST['enc'] : '0';

	if(isset($_SESSION['reg_submit']) && $_SESSION['reg_submit']>time()-600){
		exit('{"code":-1,"msg":"请勿频繁注册"}');
	}
	if($conf['verifytype']==1 && empty($phone) || $conf['verifytype']==0 && empty($email) || empty($code) || empty($pwd)){
		exit('{"code":-1,"msg":"请确保各项不能为空"}');
	}
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($enc_type == '1'){
		$plain = '';
		$private_key = base64ToPem($conf['private_key'], 'PRIVATE KEY');
		$pkey = openssl_pkey_get_private($private_key);
		if(!openssl_private_decrypt(base64_decode($pwd), $plain, $pkey, OPENSSL_PKCS1_PADDING)){
			exit(json_encode(['code'=>-1,'msg'=>'密码解密失败']));
		}
		$pwd = $plain;
	}
	if (strlen($pwd) < 6) {
		exit('{"code":-1,"msg":"密码不能低于6位"}');
	}elseif ($pwd == $email) {
		exit('{"code":-1,"msg":"密码不能和邮箱相同"}');
	}elseif ($pwd == $phone) {
		exit('{"code":-1,"msg":"密码不能和手机号码相同"}');
	}elseif (is_numeric($pwd)) {
		exit('{"code":-1,"msg":"密码不能为纯数字"}');
	}

	if($conf['reg_open']==2){
		$inviterow = $DB->find('invitecode', '*', ['code'=>$invitecode]);
		if(!$inviterow)exit('{"code":-1,"msg":"邀请码不存在"}');
		if($inviterow['status']==1)exit('{"code":-1,"msg":"邀请码已被使用"}');
	}

	if($conf['verifytype']==1){
		if(!is_numeric($phone) || strlen($phone)!=11){
			exit('{"code":-1,"msg":"手机号码不正确"}');
		}
		$row=$DB->getRow("select * from pre_user where phone=:phone limit 1", [':phone'=>$phone]);
		if($row){
			exit('{"code":-1,"msg":"该手机号已经注册过商户，如需找回商户信息，请返回登录页面点击找回商户"}');
		}
	}else{
		if(!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $email)){
			exit('{"code":-1,"msg":"邮箱格式不正确"}');
		}
		$row=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$email]);
		if($row){
			exit('{"code":-1,"msg":"该邮箱已经注册过商户，如需找回商户信息，请返回登录页面点击找回商户"}');
		}
	}
	if($conf['verifytype']==1){
		$sendto = $phone;
		$type = 1;
	}else{
		$sendto = $email;
		$type = 0;
	}
	$result = \lib\VerifyCode::verify_code('reg', $type, $sendto, $code);
	if($result !== true){
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
	$upid = $_SESSION['invite_uid']?$_SESSION['invite_uid']:0;
	if($conf['reg_pay']==1){
		$urow = $DB->getRow("SELECT uid,gid FROM pre_user WHERE uid='{$conf['reg_pay_uid']}' limit 1");
		if(!$urow)exit('{"code":-1,"msg":"注册收款商户ID不存在"}');
		$return_url = $siteurl.'user/reg.php?regok=1';
		$trade_no=date("YmdHis").rand(11111,99999);
		$domain=getdomain($return_url);
		if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`status`) VALUES (:trade_no, :out_trade_no, :uid, 1, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['reg_pay_uid'], ':name'=>'商户申请', ':money'=>$conf['reg_pay_price'], ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip]))
			exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');

		$cacheData = ['verifytype'=>$conf['verifytype'], 'email'=>$email, 'phone'=>$phone, 'pwd'=>$pwd, 'upid'=>$upid];
		if($inviterow) $cacheData['invitecodeid'] = $inviterow['id'];
		$sds = $CACHE->save('reg_'.$trade_no ,$cacheData, 3600);
		if($sds){
			\lib\VerifyCode::void_code();
			$paytype = \lib\Channel::getTypes($urow['uid'], $urow['gid']);
			$result=array("code"=>2,"msg"=>"订单创建成功！","trade_no"=>$trade_no,"need"=>$conf['reg_pay_price'],"paytype"=>$paytype);
			unset($_SESSION['csrf_token']);
		}else{
			$result=array("code"=>-1,"msg"=>"订单创建失败！".$DB->error());
		}
	}else{
		$key = random(32);
		$paystatus = $conf['user_review']==1?2:1;
		$sds=$DB->exec("INSERT INTO `pre_user` (`upid`, `key`, `money`, `email`, `phone`, `addtime`, `pay`, `settle`, `keylogin`, `apply`, `status`) VALUES (:upid, :key, '0.00', :email, :phone, NOW(), :paystatus, 1, 0, 0, 1)", [':upid'=>$upid, ':key'=>$key, ':email'=>$email, ':phone'=>$phone, ':paystatus'=>$paystatus]);
		$uid=$DB->lastInsertId();
		if($sds){
			$pwd = getMd5Pwd($pwd, $uid);
			$DB->exec("update `pre_user` set `pwd` ='{$pwd}' where `uid`='$uid'");
			if(!empty($email)){
				$sub = $conf['sitename'].' - 注册成功通知';
				$msg = '<h2>商户注册成功通知</h2>感谢您注册'.$conf['sitename'].'！<br/>您的登录账号：'.($info['email']?$info['email']:$info['phone']).'<br/>您的商户ID：'.$uid.'<br/>您的商户秘钥：'.$key.'<br/>'.$conf['sitename'].'官网：<a href="http://'.$_SERVER['HTTP_HOST'].'/" target="_blank">'.$_SERVER['HTTP_HOST'].'</a><br/>【<a href="'.$siteurl.'user/" target="_blank">商户管理后台</a>】';
				send_mail($email, $sub, $msg);
			}
			\lib\VerifyCode::void_code();
			if($inviterow){
				$DB->update('invitecode', ['status'=>1, 'uid'=>$uid, 'usetime'=>'NOW()'], ['id'=>$inviterow['id']]);
			}
			$_SESSION['reg_submit']=time();
			$result=array("code"=>1,"msg"=>"申请商户成功！","uid"=>$uid,"key"=>$key);
			unset($_SESSION['csrf_token']);
			if($paystatus == 2){
				\lib\MsgNotice::send('regaudit', 0, ['uid'=>$uid, 'account'=>$info['email']?$info['email']:$info['phone']]);
			}
		}else{
			$result=array("code"=>-1,"msg"=>"申请商户失败！".$DB->error());
		}
	}
	exit(json_encode($result));
break;
case 'sendcode2':
	$verifytype=$_POST['type'];
	$sendto=htmlspecialchars(strip_tags(trim($_POST['sendto'])));
	if(isset($_SESSION['send_code_time']) && $_SESSION['send_code_time']>time()-10){
		exit('{"code":-1,"msg":"请勿频繁发送验证码"}');
	}

	if(!isset($_SESSION['gtserver']))exit('{"code":-1,"msg":"验证加载失败"}');
	if(!verify_captcha())exit('{"code":-1,"msg":"验证失败，请重新验证"}');

	if($verifytype=='phone'){
		$userrow=$DB->getRow("select * from pre_user where phone=:phone limit 1", [':phone'=>$sendto]);
		if(!$userrow){
			exit('{"code":-1,"msg":"该手机号未找到注册商户"}');
		}
		$type = 1;
	}else{
		$userrow=$DB->getRow("select * from pre_user where email=:email limit 1", [':email'=>$sendto]);
		if(!$userrow){
			exit('{"code":-1,"msg":"该邮箱未找到注册商户"}');
		}
		$type = 0;
	}
	$result = \lib\VerifyCode::send_code('find', $type, $sendto);
	if($result === true){
		$_SESSION['send_code_time']=time();
		exit(json_encode(['code'=>0, 'msg'=>'succ']));
	}else{
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
break;
case 'findpwd':
	$verifytype=$_POST['type'];
	$account=htmlspecialchars(strip_tags(trim($_POST['account'])));
	$code=trim($_POST['code']);
	$pwd=trim($_POST['pwd']);
	$enc_type = isset($_POST['enc']) ? $_POST['enc'] : '0';

	if(empty($account) || empty($code) || empty($pwd)){
		exit('{"code":-1,"msg":"请确保各项不能为空"}');
	}
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($enc_type == '1'){
		$plain = '';
		$private_key = base64ToPem($conf['private_key'], 'PRIVATE KEY');
		$pkey = openssl_pkey_get_private($private_key);
		if(!openssl_private_decrypt(base64_decode($pwd), $plain, $pkey, OPENSSL_PKCS1_PADDING)){
			exit(json_encode(['code'=>-1,'msg'=>'密码解密失败']));
		}
		$pwd = $plain;
	}
	if (strlen($pwd) < 6) {
		exit('{"code":-1,"msg":"密码不能低于6位"}');
	}elseif ($pwd == $account && $verifytype=='email') {
		exit('{"code":-1,"msg":"密码不能和邮箱相同"}');
	}elseif ($pwd == $account && $verifytype=='phone') {
		exit('{"code":-1,"msg":"密码不能和手机号码相同"}');
	}elseif (is_numeric($pwd)) {
		exit('{"code":-1,"msg":"密码不能为纯数字"}');
	}
	if($verifytype=='phone'){
		if(!is_numeric($account) || strlen($account)!=11){
			exit('{"code":-1,"msg":"手机号码不正确"}');
		}
		$userrow=$DB->getRow("select * from pre_user where phone=:account limit 1", [':account'=>$account]);
		if(!$userrow){
			exit('{"code":-1,"msg":"该手机号未找到注册商户"}');
		}
	}else{
		if(!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $account)){
			exit('{"code":-1,"msg":"邮箱格式不正确"}');
		}
		$userrow=$DB->getRow("select * from pre_user where email=:account limit 1", [':account'=>$account]);
		if(!$userrow){
			exit('{"code":-1,"msg":"该邮箱未找到注册商户"}');
		}
	}
	if($verifytype=='phone'){
		$type = 1;
	}else{
		$type = 0;
	}
	$result = \lib\VerifyCode::verify_code('find', $type, $account, $code);
	if($result !== true){
		exit(json_encode(['code'=>-1, 'msg'=>$result]));
	}
	$pwd = getMd5Pwd($pwd, $userrow['uid']);
	$sqs=$DB->exec("update `pre_user` set `pwd`='{$pwd}' where `uid`='{$userrow['uid']}'");
	if($sqs!==false){
		\lib\VerifyCode::void_code();
		exit('{"code":1,"msg":"重置密码成功！请牢记新密码"}');
	}else{
		exit('{"code":-1,"msg":"重置密码失败！'.$DB->error().'"}');
	}
break;
case 'qrcode':
	unset($_SESSION['openid']);
	if(!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'],$_SERVER['HTTP_HOST'])){
		$qrcode = $conf['localurl_wxpay'].'user/openid.php?sid='.session_id();
	}else{
		$qrcode = $siteurl.'user/openid.php?sid='.session_id();
	}
	$result=array("code"=>0,"msg"=>"succ","url"=>$qrcode);
	exit(json_encode($result));
	break;
case 'getopenid':
	if(isset($_SESSION['openid']) && !empty($_SESSION['openid'])){
		$openid = $_SESSION['openid'];
		unset($_SESSION['openid']);
		$result=array("code"=>0,"msg"=>"succ","openid"=>$openid);
	}else{
		$result=array("code"=>-1);
	}
	exit(json_encode($result));
	break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}