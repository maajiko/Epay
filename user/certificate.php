<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='实名认证';
include './head.php';
?>
<?php
$isqrcode = false;
if(isset($_GET['qrcode']) && $_GET['qrcode'] == '1'){
	if(!isset($_SESSION[$uid.'_certify'])){
		exit("<script language='javascript'>window.location.href='./certificate.php';</script>");
	}
	$isqrcode = true;
}

if(strlen($userrow['phone'])==11){
	$userrow['phone']=substr($userrow['phone'],0,3).'****'.substr($userrow['phone'],7,10);
}

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;


if ($isqrcode && ($conf['cert_open']==1 || $conf['cert_open']==5)) {
	$page = 'alipayqrcode';
}elseif($isqrcode && $conf['cert_open']==3){
	$page = 'alipayqrcode2';
}elseif($isqrcode && $conf['cert_open']==4){
	$page = 'wxqrcode';
}else{
	if($conf['cert_corpopen'] == 1){
		if(isset($_GET['certtype']) && $_GET['certtype']=='1'){
			$page = 'corpinput';
		}elseif(isset($_GET['certtype']) && $_GET['certtype']=='0'){
			$page = 'personinput';
		}else{
			$page = 'typeselect';
		}
	}else{
		$page = 'personinput';
	}
}
?>
<link rel="stylesheet" href="./assets/css/certificate.css" type="text/css" />
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">个人资料</h1>
</div>
<div class="wrapper-md control">
<?php if(!$conf['cert_open'])showmsg('未开启实名认证功能');?>
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
<div class="tab-container ng-isolate-scope">
<ul class="nav nav-tabs">
	<li style="width: 25%;" align="center">
		<a href="userinfo.php?mod=api">API信息</a>
	</li>
	<li style="width: 25%;" align="center">
		<a href="editinfo.php">修改资料</a>
	</li>
	<li style="width: 25%;" align="center">
		<a href="userinfo.php?mod=account">修改密码</a>
	</li>
	<li style="width: 25%;" align="center" class="active">
		<a href="certificate.php">实名认证</a>
	</li>
</ul>
<?php
if($conf['cert_open']==2 && $userrow['cert']!=1){
	include ROOT.'user/certificate_mobile.php';
	exit;
}
?>
	<div class="tab-content">
		<div class="tab-pane ng-scope active">
			<div class="row step-line nav nav-pills nav-justified steps verified">
				<div id="tag1" class="col-sm-12 col-md-4 mt-step-col first fill complete-active">
					<div class="mt-step-col-cont row bg-primary">
						<div class="col-xs-3 bg-primary-l">
							<i class="icon glyphicon glyphicon-edit"></i>
						</div>
						<div class="col-xs-9 bg-primary-r">
							<div class="mt-step-title uppercase font-grey-cascade ">填写认证信息
							</div>
						</div>
					</div>
				</div>
				<div id="tag2" class="col-sm-12 col-md-4 mt-step-col <?php if($userrow['cert']==1||$isqrcode)echo 'complete-active';?>">
					<div class="mt-step-col-cont row">
						<div class="col-xs-3 bg-primary-l">
							<i class="icon fa fa-qrcode"></i>
						</div>
						<div class="col-xs-9 bg-primary-r">
							<div class="mt-step-title uppercase font-grey-cascade ">
								<?php if($conf['cert_open']==4){?>微信扫码快捷认证<?php }else{?>支付宝扫码快捷认证<?php }?></div>
						</div>
					</div>
				</div>
				<div id="tag3" class="col-sm-12 col-md-4 mt-step-col last <?php if($userrow['cert']==1)echo 'complete-active';?>">
					<div class="mt-step-col-cont row ">
						<div class="col-xs-3 bg-primary-l">
							<i class="icon fa fa-check-circle-o"></i>
						</div>
						<div class="col-xs-9 bg-primary-r">
							<div class="mt-step-title uppercase font-grey-cascade ">
								认证完成</div>
						</div>
					</div>
				</div>
			</div>
			<div class="line line-dashed b-b line-lg pull-in"></div>
<?php if($userrow['cert']==1 && !isset($_GET['upgrade'])){?>
			<div class="row">
				<div class="col-xs-12 col-sm-6">
					<img src="https://imgcache.qq.com/open_proj/proj_qcloud_v2/mc_2014/user/auth/css/mod/img/sfz.jpg" class="pull-right">
				</div>
				<div class="col-xs-12 col-sm-6">
					<h4>恭喜您已通过<?php echo $conf['sitename']?>实名认证！</h4>
					<p>认证类型：<?php echo show_cert_type($userrow['certtype'])?><?php if($userrow['certtype']==0)echo '&nbsp;&nbsp;<span class="text-muted">[<a href="certificate.php?certtype=1&upgrade=1" style="color: #98a6ad;">升级到企业认证</a></span>]';?></p>
					<p>认证方式：<?php echo show_cert_method($userrow['certmethod'])?></p>
					<p>真实姓名：<?php echo showstar((strlen($userrow['certname'])-3)/3).substr($userrow['certname'],-3)?></p>
					<p>身份证号：<?php echo substr($userrow['certno'],0,3).showstar(11).substr($userrow['certno'],-4)?></p>
					<p>认证时间：<?php echo $userrow['certtime']?></p>
				</div>
			</div>
<?php }else{?>

<?php if($conf['cert_money']>0){?>
			<div class="alert alert-info alert-dismissible" role="alert" style="line-height: 26px;">
<p>认证需要<b><?php echo $conf['cert_money']; ?></b>元，请确保你的账号内有<?php echo $conf['cert_money']; ?>元余额[<a href="recharge.php">点此充值</a>]，认证成功会自动扣除，认证失败不扣费</p>
			</div>
<?php }?>

<?php if($page=='alipayqrcode'){?>
			<div id="step2">
			<form class="form-horizontal devform">
				<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
				<center><div id="qrcode"></div>
				<p class="text-muted" style="line-height: 26px;">请使用支付宝APP扫描二维码</p>
				<?php if(checkmobile()){?><p><a href="javascript:openAlipay()" id="jumplink" class="btn btn-success">点此跳转到支付宝</a></p><p class="text-muted">到支付宝确认之后请返回此页面查看结果</p><?php }?>
				<p><a href="./certificate.php" class="btn btn-default btn-sm">返回重新填写</a></p>
				</center>
			</form>
			</div>
<?php }elseif($page=='alipayqrcode2'){?>
			<div id="step2">
			<form class="form-horizontal devform">
				<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
				<center><div id="qrcode"></div>
				<p class="text-muted" style="line-height: 26px;">请使用支付宝APP扫描二维码</p>
				<p><a href="javascript:jumpAlipay()" id="jumplink" class="btn btn-success">点此跳转到支付宝</a></p>
				<p><a href="./certificate.php" class="btn btn-default btn-sm">返回重新填写</a></p>
				</center>
			</form>
			</div>
<?php }elseif($page=='wxqrcode'){?>
			<div id="step2">
			<form class="form-horizontal devform">
				<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
				<center><div id="qrcode"></div>
				<p class="text-muted" style="line-height: 26px;">请使用微信扫描二维码</p>
				<?php if(checkmobile()){?><p class="text-muted" style="line-height: 26px;">手机用户可保存上方二维码到手机中</p><p class="text-muted" style="line-height: 26px;">微信扫一扫中选择“相册”即可</p><?php }?>
				<p><a href="./certificate.php" class="btn btn-default btn-sm">返回重新填写</a></p>
				</center>
			</form>
			</div>
<?php }elseif($page=='personinput'){?>
			<div id="step1">
			<form class="form-horizontal devform">
				<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
				<input type="hidden" name="certtype" value="0">
				<div class="form-group">
					<label class="col-sm-2 control-label">认证方式</label>
					<div class="col-sm-9">
					<div class="certification_type">
						<?php if($conf['cert_open']==4){?><img src="/assets/icon/wxpay.ico" width="25">&nbsp;&nbsp;微信快捷认证<?php }else{?><img src="/assets/icon/alipay.ico" width="25">&nbsp;&nbsp;支付宝快捷认证<?php }?>
					</div>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">证件类型</label>
					<div class="col-sm-9">
						<select class="form-control" name="certcardtype"><option value="0">大陆居民身份证</option><?php if($conf['cert_open']==1){?><option value="1">港澳通行证</option><option value="2">台湾通行证</option><option value="3">港澳居住证</option><option value="4">台湾居住证</option><option value="5">外国人永居证</option><?php }?></select>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">证件号码</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certno" value="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">真实姓名</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certname" value="">
					</div>
				</div>
				<div class="form-group">
				  <div class="col-sm-offset-2 col-sm-9"><div class="input-group"><font color="red"><i class="fa fa-info-circle"></i>&nbsp;<?php if($conf['cert_open']==4){?>微信账号的实名信息需与所填身份证号码对应，否则无法认证成功<?php }else{?>支付宝账号的实名信息需与所填身份证号码对应，否则无法认证成功<?php }?></font></div>
				 </div>
				</div>
				<div class="form-group">
				  <div class="col-sm-offset-2 col-sm-4"><input type="button" id="certSubmit" value="提交认证" class="btn btn-primary form-control"/><br/>
				 </div>
				</div>
			</form>
			<div class="alert alert-warning alert-dismissible" role="alert" style="line-height: 26px;font-size: 13px;margin-top: 50px;">
<p>实名认证是使用<?php if($conf['cert_open']==4){?>微信<?php }else{?>支付宝<?php }?>扫码快捷认证的方式，无需人工审核，本站承诺为用户的隐私及其他个人信息采取严格保密措施，请放心填写。</p>
			</div>
			</div>
<?php }elseif($page=='corpinput'){?>
			<div id="step1">
			<form class="form-horizontal devform">
				<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
				<input type="hidden" name="certtype" value="1">
				<div class="form-group">
					<label class="col-sm-2 control-label">认证方式</label>
					<div class="col-sm-9">
					<div class="certification_type">
						<?php if($conf['cert_open']==4){?><img src="/assets/icon/wxpay.ico" width="25">&nbsp;&nbsp;微信快捷认证<?php }else{?><img src="/assets/icon/alipay.ico" width="25">&nbsp;&nbsp;支付宝快捷认证<?php }?>
					</div>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">公司名称</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certcorpname" value="" placeholder="填写公司名称">
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">营业执照号码</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certcorpno" value="" placeholder="填写统一社会信用代码">
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">法人姓名</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certname" value="" placeholder="填写法人的姓名">
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label">法人身份证号</label>
					<div class="col-sm-9">
						<input class="form-control" type="text" name="certno" value="" placeholder="填写法人的身份证号">
					</div>
				</div>
				<div class="form-group">
				  <div class="col-sm-offset-2 col-sm-9"><div class="input-group"><p><font color="red"><i class="fa fa-info-circle"></i>&nbsp;<?php if($conf['cert_open']==4){?>微信账号的实名信息需与所填法人身份证号码对应，否则无法认证成功<?php }else{?>支付宝账号的实名信息需与所填法人身份证号码对应，否则无法认证成功<?php }?></font></p><p><font color="grey"><i class="fa fa-info-circle"></i>&nbsp;建议先去 <a href="https://www.tianyancha.com/" target="_blank" rel="noopener noreferrer">天眼查</a> 查询自己企业的信息，直接复制填入，避免填错。</font></p></div>
				 </div>
				</div>
				<div class="form-group">
				  <div class="col-sm-offset-2 col-sm-4"><input type="button" id="certSubmit" value="提交认证" class="btn btn-primary form-control"/><br/>
				 </div>
				</div>
			</form>
			<div class="alert alert-warning alert-dismissible" role="alert" style="line-height: 26px;font-size: 13px;margin-top: 50px;">
<p>实名认证是使用法人<?php if($conf['cert_open']==4){?>微信<?php }else{?>支付宝<?php }?>扫码快捷认证的方式，无需人工审核，本站承诺为用户的隐私及其他个人信息采取严格保密措施，请放心填写。</p>
			</div>
			</div>
<?php }elseif($page=='typeselect'){?>
			<div class="row" style="margin:10px 5px 15px -10px;">
				<div class="col-12 col-md-6">
					<div class="type-item" style="border-radius:20px">
						<div class="type-icon personal"></div>
						<div class="type-title">个人认证</div>
						<div class="type-desc">用个人身份信息进行认证</div>
						<ul class="list-unstyled type-info">
							<li><i class="fa fa-check-circle"></i> 全自动审核，即时通过</li>
						</ul>
						<a href="certificate.php?certtype=0" class="btn btn-primary">立即认证</a>
					</div>
				</div>
				<div class="col-12  col-md-6">
					<div class="type-item" style="border-radius:20px">
						<div class="type-icon enterprises"></div>
						<div class="type-title">企业认证</div>
						<div class="type-desc">用企业信息进行认证</div>
						<ul class="list-unstyled type-info">
							<li><i class="fa fa-check-circle"></i> 全自动审核，即时通过</li>
						</ul>
						<a href="certificate.php?certtype=1" class="btn btn-primary">立即认证</a>
					</div>
				</div>
			</div>
<?php }?>
<?php }?>
		</div>
	</div>
</div>
</div>
    </div>
  </div>
<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
<?php if($isqrcode){?>
var qrcode_url;
$(document).ready(function(){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	var csrf_token=$("input[name='csrf_token']").val();
	$.ajax({
		type : "POST",
		url : "ajax2.php?act=cert_geturl",
		data : {csrf_token:csrf_token},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 1){
				qrcode_url = data.url;
				$('#qrcode').qrcode({
					text: qrcode_url,
					width: 280,
					height: 280,
					foreground: "#000000",
					background: "#ffffff",
					typeNumber: -1
				});
				setTimeout(certQuery, 5000);
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		}
	});
});
<?php }else{?>
$(document).ready(function(){
	$("#certSubmit").click(function(){
		var certtype=$("input[name='certtype']").val();
		var certname=$("input[name='certname']").val();
		var certno=$("input[name='certno']").val();
		var certcorpname=$("input[name='certcorpname']").val();
		var certcorpno=$("input[name='certcorpno']").val();
		var certcardtype=$("select[name='certcardtype']").val();
		var csrf_token=$("input[name='csrf_token']").val();
		if(certname=='' || certno==''){
			layer.alert('请确保各项不能为空');return false;
		}
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=certificate",
			data : {certtype:certtype,certname:certname,certno:certno,certcorpname:certcorpname,certcorpno:certcorpno,certcardtype:certcardtype,csrf_token:csrf_token},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					window.location.href='./certificate.php?qrcode=1';
				}else if(data.code == 2){
					layer.alert(data.msg, {icon: 1}, function(){ window.location.href='./certificate.php'; });
				}else if(data.code == -2){
					var confirmobj = layer.confirm(data.msg, {
					  icon: 0, btn: ['关联认证','取消']
					}, function(){
						certBind(data.uid);
					}, function(){
						layer.close(confirmobj);
					});
				}else{
					layer.alert(data.msg, {icon: 2});
				}
			}
		});
	});
});
<?php }?>
function openAlipay(){
	var scheme = 'alipays://platformapi/startapp?appId=20000067&url=';
	scheme += encodeURIComponent(qrcode_url);
	window.location.href = scheme;
}
function jumpAlipay(){
	if( /Android|SymbianOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone|Midp/i.test(navigator.userAgent)) {
		var scheme = 'alipays://platformapi/startapp?appId=20000067&url=';
		scheme += encodeURIComponent(qrcode_url);
		window.location.href = scheme;
	}else{
		window.location.href = qrcode_url;
	}
}
function certBind(touid){
	var certname=$("input[name='certname']").val();
	var certno=$("input[name='certno']").val();
	var csrf_token=$("input[name='csrf_token']").val();
	layer.prompt({title: '请输入商户ID'+uid+'的商户密钥', value: '', formType: 0}, function(text, index){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax2.php?act=cert_bind',
			data : {touid:touid,certname:certname,certno:certno,csrf_token:csrf_token},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert(data.msg, {icon: 1}, function(){window.location.reload()});
				}else{
					layer.alert(data.msg, {icon: 2});
				}
			},
			error:function(data){
				layer.msg('服务器错误');
				return false;
			}
		});
	});
}
function certQuery(){
	var csrf_token=$("input[name='csrf_token']").val();
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=cert_query',
		data : {csrf_token:csrf_token},
		dataType : 'json',
		async: true,
		success : function(data) {
			if(data.code == 1){
				if(data.passed == true){
					layer.msg('实名认证成功！', {icon: 1,time: 10000,shade:[0.3, "#000"]});
					setTimeout(function(){ window.location.href='./certificate.php' }, 800);
				}else{
					setTimeout(certQuery, 3000)
				}
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
</script>