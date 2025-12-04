<?php
/**
 * QQ互联
**/
include("../includes/common.php");

if(isset($_GET['act']) && $_GET['act']=='qrlogin' && $conf['login_qq']==2){
	if(isset($_SESSION['findpwd_qq']) && $qq=$_SESSION['findpwd_qq']){
		$userrow=$DB->getRow("SELECT * FROM pre_user WHERE qq_uid=:qq LIMIT 1", [':qq'=>$qq]);
		unset($_SESSION['findpwd_qq']);
		if($userrow){
			$uid=$userrow['uid'];
			$key=$userrow['key'];
			if($islogin2==1){
				exit('{"code":-1,"msg":"当前QQ已绑定商户ID:'.$uid.'，请勿重复绑定！"}');
			}
			$DB->insert('log', ['uid'=>$uid, 'type'=>'QQ快捷登录', 'date'=>'NOW()', 'ip'=>$clientip, 'city'=>$city]);
			$session=md5($uid.$key.$password_hash);
			$expiretime=time()+2592000;
			$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
			setcookie("user_token", $token, time() + 2592000);
			$DB->exec("update `pre_user` set `lasttime`=NOW() where `uid`='$uid'");
			$result=array("code"=>0,"msg"=>"登录成功！正在跳转到用户中心","url"=>"./");
		}elseif($islogin2==1){
			$sds=$DB->exec("update `pre_user` set `qq_uid`=:qq where `uid`=:uid", [':qq'=>$qq, ':uid'=>$uid]);
			$result=array("code"=>0,"msg"=>"已成功绑定QQ账号！","url"=>"./editinfo.php");
		}else{
			$_SESSION['Oauth_qq_uid']=$openId;
			$result=array("code"=>0,"msg"=>"请输入商户ID和密钥完成绑定和登录","url"=>"./login.php?connect=true");
		}
	}else{
		$result=array("code"=>-1, "msg"=>"验证失败，请重新扫码");
	}
	exit(json_encode($result));
}

$QC_config['appid']=$conf['login_qq_appid'];
$QC_config['appkey']=$conf['login_qq_appkey'];
$QC_config['callback']=$siteurl.'user/connect.php';

$Oauth_config['apiurl']=$conf['login_apiurl'];
$Oauth_config['appid']=$conf['login_appid'];
$Oauth_config['appkey']=$conf['login_appkey'];
$Oauth_config['callback']=$siteurl.'user/connect.php';

if($_GET['code'] && ($conf['login_qq']==1 || $conf['login_qq']==3 || $conf['login_wx']==-1 || $conf['login_alipay']==-1)){
	if($conf['login_qq']==1 && !isset($_GET['type'])){
		$QC=new \lib\QC($QC_config);
		$access_token=$QC->qq_callback();
		$openid=$QC->get_openid($access_token);
		$typename = 'QQ';
		$typecolumn = 'qq_uid';
	}else{
		$type = isset($_GET['type'])?$_GET['type']:exit('{"code":-1,"msg":"no type"}');
		if($type == 'qq'){
			$typename = 'QQ';
			$typecolumn = 'qq_uid';
		}elseif($type == 'wx'){
			$typename = '微信';
			$typecolumn = 'wx_uid';
		}elseif($type == 'alipay'){
			$typename = '支付宝';
			$typecolumn = 'alipay_uid';
		}
		$Oauth=new \lib\Oauth($Oauth_config);
		$arr = $Oauth->callback();
		if(isset($arr['code']) && $arr['code']==0){
			$openid=$arr['social_uid'];
			$access_token=$arr['access_token'];
		}elseif(isset($arr['code'])){
			sysmsg($arr['msg']);
		}else{
			sysmsg('获取登录数据失败');
		}
	}

	$userrow=$DB->getRow("SELECT * FROM pre_user WHERE {$typecolumn}=:openid limit 1", [':openid'=>$openid]);
	if($userrow){
		$uid=$userrow['uid'];
		$key=$userrow['key'];
		if($islogin2==1){
			@header('Content-Type: text/html; charset=UTF-8');
			exit("<script language='javascript'>alert('当前{$typename}已绑定商户ID:{$uid}，请勿重复绑定！');window.location.href='./editinfo.php';</script>");
		}
		$DB->insert('log', ['uid'=>$uid, 'type'=>$typename.'快捷登录', 'date'=>'NOW()', 'ip'=>$clientip, 'city'=>$city]);
		$session=md5($uid.$key.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		setcookie("user_token", $token, time() + 2592000);
		$DB->exec("update `pre_user` set `lasttime`=NOW() where `uid`='$uid'");
		exit("<script language='javascript'>window.location.href='./';</script>");
	}elseif($islogin2==1){
		$sds=$DB->exec("update `pre_user` set `{$typecolumn}`=:openid where `uid`='$uid'", [':openid'=>$openid]);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('已成功绑定{$typename}！');window.location.href='./editinfo.php';</script>");
	}else{
		$_SESSION['Oauth_'.$typecolumn]=$openid;
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('请输入商户ID和密钥完成绑定和登录');window.location.href='./login.php?connect=true';</script>");
	}
}elseif($islogin2==1 && isset($_GET['unbind'])){
	$DB->exec("update `pre_user` set `qq_uid`=NULL where `uid`='$uid'");
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功解绑QQ！');window.location.href='./editinfo.php';</script>");
}elseif($islogin2==1 && !isset($_GET['bind'])){
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已登陆！');window.location.href='./';</script>");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<title>QQ扫码登录 | <?php echo $conf['sitename']?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>animate.css/3.7.2/animate.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/font.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/app.css" type="text/css" />
<style>input:-webkit-autofill{-webkit-box-shadow:0 0 0px 1000px white inset;-webkit-text-fill-color:#333;}img.logo{width:14px;height:14px;margin:0 5px 0 3px;}</style>
</head>
<body>
<div class="app app-header-fixed  ">
<div class="container w-xxl w-auto-xs" ng-controller="SigninFormController" ng-init="app.settings.container = false;">
<span class="navbar-brand block m-t" id="sitename"><?php echo $conf['sitename']?></span>
<div class="m-b-lg">
<div class="wrapper text-center">
<strong>QQ扫码登录</strong>
</div>
<form name="form" class="form-validation">
<div class="text-danger wrapper text-center" ng-show="authError">
</div>
	<div class="form-group" style="text-align: center;">
		<div class="list-group-item list-group-item-info" style="font-weight: bold;" id="login">
			<span id="loginmsg">请使用QQ手机版扫描二维码</span><span id="loginload" style="padding-left: 10px;color: #790909;">.</span>
		</div>
		<div id="qrimg" class="list-group-item">
		</div>
		<div class="list-group-item" id="mobile" style="display:none;"><button type="button" onclick="qrlogin()" class="btn btn-success btn-block">我已完成登录</button></div>
		<div class="list-group-item">
		<div class="btn-group">
		<a href="login.php" class="btn btn-primary btn-rounded"><i class="fa fa-user"></i>&nbsp;返回登录</a>
		<a href="reg.php" class="btn btn-info btn-rounded"><i class="fa fa-user-plus"></i>&nbsp;注册账号</a>
		</div>
		</div>
	</div>
</div>
</form>
</div>
<div class="text-center">
<p>
<small class="text-muted"><a href="/"><?php echo $conf['sitename']?></a><br>&copy; 2016~<?php echo date("Y")?></small>
</p>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="./assets/js/qrlogin.js"></script>
</body>
</html>