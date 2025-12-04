<?php
/**
 * 系统设置
**/
include("../includes/common.php");
$title='系统设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:null;
$mods=['site'=>'网站信息','pay'=>'支付相关','risk'=>'风控检测','settle'=>'结算规则','transfer'=>'转账付款','oauth'=>'快捷登录','notice'=>'消息提醒','certificate'=>'实名认证','template'=>'首页模板','gonggao'=>'公告与排版','mail'=>'邮箱与短信','upimg'=>'LOGO设置','iptype'=>'IP地址','cron'=>'计划任务','proxy'=>'中转代理','account'=>'修改密码'];
?>
<ul class="nav nav-pills">
	<?php foreach($mods as $key=>$name){echo '<li class="'.($key==$mod?'active':null).'"><a href="set.php?mod='.$key.'">'.$name.'</a></li>';} ?>
</ul>
<?php
$conf=$CACHE->pre_fetch();
if($mod=='site'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">网站信息配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站名称</label>
	  <div class="col-sm-10"><input type="text" name="sitename" value="<?php echo $conf['sitename']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">首页标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="<?php echo $conf['title']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">关键字</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="<?php echo $conf['keywords']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站描述</label>
	  <div class="col-sm-10"><input type="text" name="description" value="<?php echo $conf['description']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">公司/组织名称</label>
	  <div class="col-sm-10"><input type="text" name="orgname" value="<?php echo $conf['orgname']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">回调专用网址</label>
	  <div class="col-sm-10"><input type="text" name="localurl" value="<?php echo $conf['localurl']; ?>" class="form-control" placeholder="留空则与本站网址一致"/><font color="green">必须以http://或https://开头，以/结尾，填错会导致订单无法回调</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户对接网址</label>
	  <div class="col-sm-10"><input type="text" name="apiurl" value="<?php echo $conf['apiurl']; ?>" class="form-control" placeholder="留空则与本站网址一致"/><font color="green">用户中心显示的支付对接地址，留空则与本站网址一致</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">联系邮箱</label>
	  <div class="col-sm-10"><input type="text" name="email" value="<?php echo $conf['email']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">客服ＱＱ</label>
	  <div class="col-sm-10"><input type="text" name="kfqq" value="<?php echo $conf['kfqq']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">加群链接</label>
	  <div class="col-sm-10"><input type="text" name="qqqun" value="<?php echo $conf['qqqun']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">APP下载链接</label>
	  <div class="col-sm-10"><input type="text" name="appurl" value="<?php echo $conf['appurl']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户验证方式</label>
	  <div class="col-sm-10"><select class="form-control" name="verifytype" default="<?php echo $conf['verifytype']?>"><option value="0">邮箱验证</option><option value="1">手机验证</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">开放注册</label>
	  <div class="col-sm-10"><select class="form-control" name="reg_open" default="<?php echo $conf['reg_open']?>"><option value="1">开启</option><option value="0">关闭</option><option value="2">仅邀请注册</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">注册后可不填结算账户</label>
	  <div class="col-sm-10"><select class="form-control" name="reg_input_settle" default="<?php echo $conf['reg_input_settle']?>"><option value="0">否</option><option value="1">是</option></select><font color="green">如不做平台代收，可设置为是</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">开启注册审核</label>
	  <div class="col-sm-10"><select class="form-control" name="user_review" default="<?php echo $conf['user_review']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">注册付费</label>
	  <div class="col-sm-10"><select class="form-control" name="reg_pay" default="<?php echo $conf['reg_pay']?>"><option value="1">开启</option><option value="0">关闭</option></select></div>
	</div><br/>
	<div id="reg_pay_div" style="<?php echo $conf['reg_pay']==0?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-2 control-label">注册付费金额</label>
	  <div class="col-sm-10"><input type="text" name="reg_pay_price" value="<?php echo $conf['reg_pay_price']; ?>" class="form-control"/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户编辑自定义接口信息</label>
	  <div class="col-sm-10"><select class="form-control" name="user_settings_edit" default="<?php echo $conf['user_settings_edit']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">测试支付</label>
	  <div class="col-sm-10"><select class="form-control" name="test_open" default="<?php echo $conf['test_open']?>"><option value="1">开启</option><option value="0">关闭</option></select></div>
	</div><br/>
	<div id="setform3" style="<?php echo $conf['test_open']==0?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-2 control-label">测试支付收款商户ID</label>
	  <div class="col-sm-10"><input type="text" name="test_pay_uid" value="<?php echo $conf['test_pay_uid']; ?>" class="form-control" placeholder="填写在本站注册的商户UID"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">测试支付开启验证码</label>
	  <div class="col-sm-10"><select class="form-control" name="captcha_open_test" default="<?php echo $conf['captcha_open_test']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">极验滑动验证码ID</label>
	  <div class="col-sm-10"><input type="text" name="captcha_id" value="<?php echo $conf['captcha_id']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">极验滑动验证码密钥</label>
	  <div class="col-sm-10"><input type="text" name="captcha_key" value="<?php echo $conf['captcha_key']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">极验版本</label>
	  <div class="col-sm-10"><select class="form-control" name="captcha_version" default="<?php echo $conf['captcha_version']?>"><option value="0">V3.0</option><option value="1">V4.0</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">登录开启验证码</label>
	  <div class="col-sm-10"><select class="form-control" name="captcha_open_login" default="<?php echo $conf['captcha_open_login']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">密钥登录</label>
	  <div class="col-sm-10"><select class="form-control" name="close_keylogin" default="<?php echo $conf['close_keylogin']?>"><option value="1">关闭</option><option value="0">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户中心风格</label>
	  <div class="col-sm-10"><select class="form-control" name="user_style" default="<?php echo $conf['user_style']?>"><option value="0">黑色（1）</option><option value="1">黑色（2）</option><option value="2">棕色（1）</option><option value="3">棕色（2）</option><option value="4">蓝色（1）</option><option value="5">蓝色（2）</option><option value="6">紫色（1）</option><option value="7">紫色（2）</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">公共静态资源CDN</label>
	  <div class="col-sm-10"><select class="form-control" name="cdnpublic" default="<?php echo $conf['cdnpublic']?>">
	  <option value="2">ZstaticCDN</option>
	  <option value="4">未闻花名CDN</option>
	  <option value="1">360CDN</option>
	  <option value="0">本地</option>
	  </select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">首页显示模式</label>
	  <div class="col-sm-10"><select class="form-control" name="homepage" default="<?php echo $conf['homepage']?>"><option value="0">默认显示首页</option><option value="1">直接跳转登录页面</option><option value="2">显示其它指定网址</option></select></div>
	</div><br/>
	<div class="form-group" id="setform4" style="<?php echo $conf['homepage']!=2?'display:none;':null; ?>">
	  <label class="col-sm-2 control-label">显示网址URL</label>
	  <div class="col-sm-10"><input type="text" name="homepage_url" value="<?php echo $conf['homepage_url']; ?>" class="form-control" placeholder="将以frame方式显示"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<script>
$("select[name='reg_open']").change(function(){
	if($(this).val() == 0){
		$("#setform1").hide();
	}else{
		$("#setform1").show();
	}
});
$("select[name='reg_pay']").change(function(){
	if($(this).val() == 1){
		$("#reg_pay_div").show();
	}else{
		$("#reg_pay_div").hide();
	}
});
$("select[name='test_open']").change(function(){
	if($(this).val() == 1){
		$("#setform3").show();
	}else{
		$("#setform3").hide();
	}
});
$("select[name='homepage']").change(function(){
	if($(this).val() == 2){
		$("#setform4").show();
	}else{
		$("#setform4").hide();
	}
});
</script>
<?php
}elseif($mod=='paypwd_n' && $_POST['do']=='submit'){
	if(!checkRefererHost())exit;
	$oldpwd=trim($_POST['oldpwd']);
	$newpwd=trim($_POST['newpwd']);
	$newpwd2=trim($_POST['newpwd2']);
	if(!empty($newpwd) && !empty($newpwd2)){
		if($oldpwd!=$conf['admin_paypwd'])showmsg('旧密码不正确！',3);
		if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
		if(strlen($newpwd)<6)showmsg('密码不能少于6位',3);
		if(strlen($newpwd)>100)showmsg('密码位数过长',3);
		saveSetting('admin_paypwd',$newpwd);
	}else{
		showmsg('新密码不能为空',3);
	}
	$ad=$CACHE->clear();
	if($ad)showmsg('修改成功！',1);
	else showmsg('修改失败！<br/>'.$DB->error(),4);
}elseif($mod=='account_n' && $_POST['do']=='submit'){
	if(!checkRefererHost())exit;
	$user=trim($_POST['user']);
	$oldpwd=trim($_POST['oldpwd']);
	$newpwd=trim($_POST['newpwd']);
	$newpwd2=trim($_POST['newpwd2']);
	if($user==null)showmsg('用户名不能为空！',3);
	saveSetting('admin_user',$user);
	if(!empty($newpwd) && !empty($newpwd2)){
		if($oldpwd!=$conf['admin_pwd'])showmsg('旧密码不正确！',3);
		if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
		saveSetting('admin_pwd',$newpwd);
	}
	$ad=$CACHE->clear();
	if($ad)showmsg('修改成功！请重新登录',1);
	else showmsg('修改失败！<br/>'.$DB->error(),4);
}elseif($mod=='account'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">管理员账号配置</h3></div>
<div class="panel-body">
  <form action="./set.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户名</label>
	  <div class="col-sm-10"><input type="text" name="user" value="<?php echo $conf['admin_user']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">旧密码</label>
	  <div class="col-sm-10"><input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的管理员密码"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">重输密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付密码修改</h3></div>
<div class="panel-body">
  <form action="./set.php?mod=paypwd_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">旧密码</label>
	  <div class="col-sm-10"><input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的支付密码"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">重输密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
          <span class="glyphicon glyphicon-info-sign"></span> 支付密码用于转账接口以及API退款时使用，默认为123456
        </div>
</div>
<?php
}elseif($mod=='template'){
	$mblist = \lib\Template::getList();
?>
<style>.mblist{margin-bottom: 20px;} .mblist img{height: 110px;}</style>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">首页模板设置</h3></div>
<div class="panel-body">
  <h4>当前使用模板：</h4>
  <div class="row text-center">
	  <div class="col-xs-6 col-sm-4">
		<img class="img-responsive img-thumbnail img-rounded" src="/template/<?php echo $conf['template']?>/preview.png" onerror="this.src='/assets/img/NoImg.png'">
	  </div>
	  <div class="col-xs-6 col-sm-4">
		<p>模板名称：<?php echo $conf['template']?></p>
	  </div>
  </div>
  <hr/>
  <h4>更换模板：</h4>
  <div class="row text-center">
  <?php foreach($mblist as $template){?>
	  <div class="col-xs-6 col-sm-4 mblist">
		<a href="javascript:changeTemplate('<?php echo $template?>')"><img class="img-responsive img-thumbnail img-rounded" src="/template/<?php echo $template?>/preview.png" onerror="this.src='/assets/img/NoImg.png'" title="点击更换到该模板"><br/><?php echo $template?></a>
	  </div>
  <?php }?>
  </div>
</div>
</div>
<?php
}elseif($mod=='iptype'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户IP地址获取设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">用户IP地址获取方式</label>
	  <div class="col-sm-10"><select class="form-control" name="ip_type" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option></select></div>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
此功能设置用于防止用户伪造IP请求。<br/>
X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br/>
X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br/>
REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br/>
<b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
</div>
</div>
<script>
$(document).ready(function(){
	$.ajax({
		type : "GET",
		url : "ajax.php?act=iptype",
		dataType : 'json',
		async: true,
		success : function(data) {
			$("select[name='ip_type']").empty();
			var defaultv = $("select[name='ip_type']").attr('default');
			$.each(data, function(k, item){
				$("select[name='ip_type']").append('<option value="'+k+'" '+(defaultv==k?'selected':'')+'>'+ item.name +' - '+ item.ip +' '+ item.city +'</option>');
			})
		}
	});
})
</script>
<?php
}elseif($mod=='pay'){
	$alipay_channel = $DB->getAll("SELECT * FROM pre_channel WHERE plugin='alipay' OR plugin='alipaysl' OR plugin='alipayd'");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付相关配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">最大支付金额</label>
	  <div class="col-sm-9"><input type="text" name="pay_maxmoney" value="<?php echo $conf['pay_maxmoney']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">最小支付金额</label>
	  <div class="col-sm-9"><input type="text" name="pay_minmoney" value="<?php echo $conf['pay_minmoney']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商品屏蔽关键词</label>
	  <div class="col-sm-9"><input type="text" name="blockname" value="<?php echo $conf['blockname']; ?>" class="form-control"/><font color="green">多个关键词用|隔开。如果触发屏蔽会在<a href="./risk.php">风控记录</a>里面显示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商品屏蔽显示内容</label>
	  <div class="col-sm-9"><input type="text" name="blockalert" value="<?php echo $conf['blockalert']; ?>" class="form-control" placeholder="该商品禁止出售"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商品名称自定义</label>
	  <div class="col-sm-9"><input type="text" name="ordername" value="<?php echo $conf['ordername']; ?>" class="form-control" placeholder="默认使用原商品名称"/><font color="green">支持变量值：[name]原商品名称，[order]支付订单号，[outorder]商户订单号，[time]时间戳，[qq]当前商户的联系QQ，[phone]当前商户的手机号</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">扫码页面隐藏商品名称</label>
	  <div class="col-sm-9"><select class="form-control" name="pageordername" default="<?php echo $conf['pageordername']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">回调参数去除商品名称</label>
	  <div class="col-sm-9"><select class="form-control" name="notifyordername" default="<?php echo $conf['notifyordername']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">未填联系QQ禁止支付</label>
	  <div class="col-sm-9"><select class="form-control" name="forceqq" default="<?php echo $conf['forceqq']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">本站网址(支付宝专用)</label>
	  <div class="col-sm-9"><input type="text" name="localurl_alipay" value="<?php echo $conf['localurl_alipay']; ?>" class="form-control" placeholder="留空默认使用当前网址"/><font color="green">适用于网站有多个绑定域名，由于支付宝官方限制域名，使用未登记域名会有违约风险，填写指定网址后，使用支付宝支付都会跳转到该网址再跳转到支付宝。必须以http://或https://开头，以/结尾，留空则使用当前网址</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">本站网址(微信专用)</label>
	  <div class="col-sm-9"><input type="text" name="localurl_wxpay" value="<?php echo $conf['localurl_wxpay']; ?>" class="form-control" placeholder="留空默认使用当前网址"/><font color="green">适用于网站有多个绑定域名，由于微信公众号只能授权一个域名，填写指定网址后，使用微信公众号支付或获取openid都会跳转到该网址再跳转到微信。必须以http://或https://开头，以/结尾，留空则使用当前网址</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信小程序支付跳转小程序页面路径</label>
	  <div class="col-sm-9"><input type="text" name="wxminipay_path" value="<?php echo $conf['wxminipay_path']; ?>" class="form-control" placeholder=""/><font color="green">留空默认为pages/pay/pay</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">满多少随机增减金额</label>
	  <div class="col-sm-9"><input type="text" name="pay_payaddstart" value="<?php echo $conf['pay_payaddstart']; ?>" class="form-control" placeholder="订单满多少随机增减金额，留空不随机增减"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">随机增减金额范围</label>
	  <div class="col-sm-4"><input type="text" name="pay_payaddmin" value="<?php echo $conf['pay_payaddmin']; ?>" class="form-control" placeholder="最小增加金额，负数为减少"/></div>
	  <div class="col-sm-4"><input type="text" name="pay_payaddmax" value="<?php echo $conf['pay_payaddmax']; ?>" class="form-control" placeholder="最大增加金额，负数为减少"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝电脑网站支付使用扫码模式</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_paymode" default="<?php echo $conf['alipay_paymode']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后，如果使用支付宝"电脑网站支付"，则直接显示二维码，这种模式下跳转回网站速度更快</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝直付通&微信收付通结算时间</label>
	  <div class="col-sm-9"><select class="form-control" name="direct_settle_time" default="<?php echo $conf['direct_settle_time']?>"><option value="0">支付后立即结算</option><option value="1">支付后延迟24小时结算</option></select><font color="green">如设置延迟结算，则支付资金默认是冻结状态，需定时访问计划任务里面的url才可以结算</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝扫码链接替换地址</label>
	  <div class="col-sm-9"><input type="text" name="alipay_qrcode_url" value="<?php echo $conf['alipay_qrcode_url']; ?>" class="form-control" placeholder="留空则不进行替换"/><font color="green">默认请留空，乱填会导致无法支付，若支付宝扫码支付链接的域名部分为本站域名，填写后将替换本站域名</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信扫码链接替换地址</label>
	  <div class="col-sm-9"><input type="text" name="wxpay_qrcode_url" value="<?php echo $conf['wxpay_qrcode_url']; ?>" class="form-control" placeholder="留空则不进行替换"/><font color="green">默认请留空，乱填会导致无法支付，若微信扫码支付链接的域名部分为本站域名，填写后将替换本站域名</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">黑名单支付订单后操作</label>
	  <div class="col-sm-9"><select class="form-control" name="black_payact" default="<?php echo $conf['black_payact']?>"><option value="0">无</option><option value="1">订单不回调</option></select><font color="green">仅适用于支付前黑名单账号无法拦截的支付方式</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝&微信分账描述</label>
	  <div class="col-sm-9"><input type="text" name="profits_desc" value="<?php echo $conf['profits_desc']; ?>" class="form-control" placeholder="默认为空，仅用于支付宝和微信官方接口分账"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">分账失败的24小时后重试</label>
	  <div class="col-sm-9"><select class="form-control" name="profits_failretry" default="<?php echo $conf['profits_failretry']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付宝快捷登录相关设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">网页快捷登录通道</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_web_login" default="<?php echo $conf['alipay_web_login']?>"><option value="0">关闭</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?></select><font color="green">用于非支付宝官方插件的生活号支付时使用。需在支付宝应用内配置<b>授权回调地址</b></font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝官方插件也用上述通道登录</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_web_login_all" default="<?php echo $conf['alipay_web_login_all']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后，支付宝官方支付插件需要快捷登录时，也全部使用上述选择的通道，不再使用当前支付用的通道登录，避免需要重复配置授权回调地址。如需开启，务必使用uid模式，不能用openid模式</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝小程序通道</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_mini_login" default="<?php echo $conf['alipay_mini_login']?>"><option value="0">关闭</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?></select><font color="green">用于所有插件的支付宝小程序支付。需在支付宝应用内配置<b>服务器域名白名单</b>，并将指定的页面源码加入到你的小程序里面，才能用于发起支付。如用于非当前应用支付，还需在JSAPI支付签约页面关联小程序AppID</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝手机网站支付前快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_wappaylogin" default="<?php echo $conf['alipay_wappaylogin']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后，可在手机网站支付前获取用户支付宝uid，用于黑名单屏蔽。电脑网站支付不支持</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝当面付支付前快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_qrpaylogin" default="<?php echo $conf['alipay_qrpaylogin']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后，可在当面付/订单码支付前获取用户支付宝uid，用于黑名单屏蔽。其他支付不支持</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝快捷登录获取用户手机号</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_getmobile" default="<?php echo $conf['alipay_getmobile']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">需要先在支付宝应用里面隐私申请手机号码字段，开启后，可在支付前获取用户手机号并保存。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝加密AES密钥</label>
	  <div class="col-sm-9"><input type="text" name="alipay_aes_key" value="<?php echo $conf['alipay_aes_key']; ?>" class="form-control" placeholder="在“支付宝应用-接口内容加密方式”配置"/><font color="green">用于支付宝小程序应用获取用户手机号</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">商户支付功能设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启余额充值</label>
	  <div class="col-sm-9"><select class="form-control" name="recharge" default="<?php echo $conf['recharge']?>"><option value="1">开启</option><option value="0">关闭</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">注册/充值/购买会员收款商户ID</label>
	  <div class="col-sm-9"><input type="text" name="reg_pay_uid" value="<?php echo $conf['reg_pay_uid']; ?>" class="form-control" placeholder="填写在本站注册的商户UID"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启聚合收款码</label>
	  <div class="col-sm-9"><select class="form-control" name="onecode" default="<?php echo $conf['onecode']?>"><option value="1">开启</option><option value="0">关闭</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启授权支付域名添加</label>
	  <div class="col-sm-9"><select class="form-control" name="pay_domain_open" default="<?php echo $conf['pay_domain_open']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">未授权支付域名禁止支付</label>
	  <div class="col-sm-9"><select class="form-control" name="pay_domain_forbid" default="<?php echo $conf['pay_domain_forbid']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启商户后台自助退款</label>
	  <div class="col-sm-9"><select class="form-control" name="user_refund" default="<?php echo $conf['user_refund']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">订单退款手续费承担方</label>
	  <div class="col-sm-9"><select class="form-control" name="refund_fee_type" default="<?php echo $conf['refund_fee_type']?>"><option value="0">平台（默认）</option><option value="1">商户</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">手续费最低扣除设置</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="payfee_lessthan" value="<?php echo $conf['payfee_lessthan']; ?>" class="form-control" placeholder="手续费低于多少元时"/><span class="input-group-addon">元</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="payfee_mincost" value="<?php echo $conf['payfee_mincost']; ?>" class="form-control" placeholder="最低扣除多少元"/><span class="input-group-addon">元</span></div></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Applyments\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-3 control-label">开启商户后台添加分账规则（仅进件商户）</label>
	  <div class="col-sm-9"><select class="form-control" name="user_profitsharing" default="<?php echo $conf['user_profitsharing']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邀请返现功能</label>
	  <div class="col-sm-9"><select class="form-control" name="invite_open" default="<?php echo $conf['invite_open']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邀请分成比例计算类型</label>
	  <div class="col-sm-9"><select class="form-control" name="invite_order_type" default="<?php echo $conf['invite_order_type']?>"><option value="0">按订单金额（默认）</option><option value="1">按手续费金额</option><option value="2">按手续费利润</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邀请分成比例</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="invite_rate" value="<?php echo $conf['invite_rate']; ?>" class="form-control"/><span class="input-group-addon">%</span></div><font color="green">被邀请用户支付订单，给邀请者分成的比例</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邀请返现不能超过订单手续费</label>
	  <div class="col-sm-9"><select class="form-control" name="invite_order_fee" default="<?php echo $conf['invite_order_fee']?>"><option value="0">是（默认）</option><option value="1">否</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邀请返现分成时机</label>
	  <div class="col-sm-9"><select class="form-control" name="invite_mode" default="<?php echo $conf['invite_mode']?>"><option value="0">每笔订单立即返现（默认）</option><option value="1">每日0点定时返现</option></select><font color="green">若选择定时返现，需确保计划任务的订单统计任务正常运行</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">购买会员邀请分成比例</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="invite_groupbuy_rate" value="<?php echo $conf['invite_groupbuy_rate']; ?>" class="form-control" placeholder="留空为不分成"/><span class="input-group-addon">%</span></div><font color="green">被邀请用户购买会员，给邀请者分成的比例</font></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Applyments\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-3 control-label">进件邀请分成比例</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="invite_apply_rate" value="<?php echo $conf['invite_apply_rate']; ?>" class="form-control" placeholder="留空为不分成"/><span class="input-group-addon">%</span></div><font color="green">被邀请用户进件付费，给邀请者分成的比例</font></div>
	</div><br/><?php }?>
	<?php if(class_exists('\\lib\\WxMchRisk')){?><div class="form-group">
	  <label class="col-sm-3 control-label">用户中心开启渠道商户违规记录</label>
	  <div class="col-sm-9"><select class="form-control" name="mchrisk_open" default="<?php echo $conf['mchrisk_open']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启商户保证金功能</label>
	  <div class="col-sm-9"><select class="form-control" name="user_deposit" default="<?php echo $conf['user_deposit']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户保证金最低充值金额</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="user_deposit_min" value="<?php echo $conf['user_deposit_min']; ?>" class="form-control" placeholder="留空则不限制保证金最低充值金额"/><span class="input-group-addon">元</span></div><font color="green">填写后，商户需要充值足额保证金，才能发起支付</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户保证金最少冻结天数</label>
	  <div class="col-sm-9"><input type="text" name="user_deposit_day" value="<?php echo $conf['user_deposit_day']; ?>" class="form-control" placeholder="留空则不校验最少冻结天数"/><font color="green">填写后，在提取保证金时会校验近X天内无订单、无投诉</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付交易投诉相关配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">投诉记录获取范围</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_range" default="<?php echo $conf['complain_range']?>"><option value="0">仅本站订单相关投诉</option><option value="1">全部投诉记录</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">用户中心开启交易投诉处理功能</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_open" default="<?php echo $conf['complain_open']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后商户可自行处理支付宝/微信的交易投诉</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">自动冻结与解冻被投诉订单</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_freeze_order" default="<?php echo $conf['complain_freeze_order']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">投诉自动回复并处理</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_auto_reply" default="<?php echo $conf['complain_auto_reply']?>"><option value="0">否</option><option value="1">自动回复并处理</option><option value="2">只自动回复</option><option value="3">只自动回复(仅微信)</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信重复投诉后仍然自动回复并处理</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_auto_reply_repeat" default="<?php echo $conf['complain_auto_reply_repeat']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">投诉自动回复内容</label>
	  <div class="col-sm-9"><input type="text" name="complain_auto_reply_con" value="<?php echo $conf['complain_auto_reply_con']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">投诉自动拉黑支付账号</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_auto_black" default="<?php echo $conf['complain_auto_black']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">投诉自动拉黑支付IP</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_auto_ipblack" default="<?php echo $conf['complain_auto_ipblack']?>"><option value="0">否</option><option value="1">拉黑3天</option><option value="2">拉黑5天</option><option value="3">拉黑7天</option></select></div>
	</div><br/>
	<!--div class="form-group">
	  <label class="col-sm-3 control-label">投诉订单自动退款</label>
	  <div class="col-sm-9"><select class="form-control" name="complain_auto_refund" default="<?php echo $conf['complain_auto_refund']?>"><option value="0">否</option><option value="1">是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">金额低于多少自动退款</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="complain_auto_refund_money" value="<?php echo $conf['complain_auto_refund_money']; ?>" class="form-control" placeholder="不填写则不限制金额"/><span class="input-group-addon">元</span></div></div>
	</div><br/-->
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div><?php }?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付宝/微信支付合单支付配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<h4 style="text-align: center;">微信支付合单支付配置</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信开启合单支付</label>
	  <div class="col-sm-9"><select class="form-control" name="wxcombine_open" default="<?php echo $conf['wxcombine_open']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">只支持微信官方支付V3和服务商版插件，可将大额订单分为多个子单进行合单支付。最小分3个子单，最大50个子单。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝开启合单支付</label>
	  <div class="col-sm-9"><select class="form-control" name="alicombine_open" default="<?php echo $conf['alicombine_open']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">只支持支付宝直付通版插件，可将大额订单分为多个子单进行合单支付。最小分2个子单，最大6个子单。仅支持手机网站支付和APP支付</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">合单支付最小金额</label>
	  <div class="col-sm-9"><input type="text" name="wxcombine_minmoney" value="<?php echo $conf['wxcombine_minmoney']; ?>" class="form-control" placeholder="超过该金额进行合单支付，否则是普通支付"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">合单支付子单最大金额</label>
	  <div class="col-sm-9"><input type="text" name="wxcombine_submoney" value="<?php echo $conf['wxcombine_submoney']; ?>" class="form-control" placeholder="子单超过该金额则增加子单数量"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='risk'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付频率限制相关设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">限制每IP每天支付笔数</label>
	  <div class="col-sm-9"><input type="text" name="pay_iplimit" value="<?php echo $conf['pay_iplimit']; ?>" class="form-control" placeholder="留空为不限制"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">IP白名单</label>
	  <div class="col-sm-9"><input type="text" name="pay_iplimit_white" value="<?php echo $conf['pay_iplimit_white']; ?>" class="form-control" placeholder="多个IP用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">限制每支付账号每天支付笔数</label>
	  <div class="col-sm-9"><input type="text" name="pay_userlimit" value="<?php echo $conf['pay_userlimit']; ?>" class="form-control" placeholder="留空为不限制"/><font color="green">只支持支付宝JS支付与微信公众号支付</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">限制每支付账号每天支付总额</label>
	  <div class="col-sm-9"><input type="text" name="pay_daymoney" value="<?php echo $conf['pay_daymoney']; ?>" class="form-control" placeholder="留空为不限制"/><font color="green">只支持支付宝JS支付与微信公众号支付</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">创建订单人机验证</label>
	  <div class="col-sm-9"><select class="form-control" name="pay_verify" default="<?php echo $conf['pay_verify']?>"><option value="0">关闭</option><option value="1">按规则开启</option><option value="2">指定商户开启</option><option value="3">全部商户开启</option></select><font color="green">在商户网站通过submit发起支付的时候，可先进行人机验证再创建订单，防止被刷订单，开启后无法通过mapi接口发起支付</font></div>
	</div><br/>
	<div id="pay_verify_form" style="<?php echo $conf['pay_verify']!=1?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">人机验证开启规则1</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="pay_verify_check_second" value="<?php echo $conf['pay_verify_check_second']; ?>" class="form-control" placeholder="检测商户多少秒内"/><span class="input-group-addon">秒</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="pay_verify_check_count" value="<?php echo $conf['pay_verify_check_count']; ?>" class="form-control" placeholder="超过多少个订单时"/><span class="input-group-addon">个订单</span></div></div>
	  <div class="col-sm-offset-3 col-sm-9"><div class="input-group"><span class="input-group-addon">成功率</span><input type="text" name="pay_verify_check_rate" value="<?php echo $conf['pay_verify_check_rate']; ?>" class="form-control" placeholder="成功率低于多少时（不填写则不启用该条规则）"/><span class="input-group-addon">%</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">人机验证开启规则2</label>
	  <div class="col-sm-9"><div class="input-group"><span class="input-group-addon">单个IP</span><input type="text" name="pay_verify_check_ip" value="<?php echo $conf['pay_verify_check_ip']; ?>" class="form-control" placeholder="1小时内连续未支付订单数量（不填写则不启用该条规则）"/><span class="input-group-addon">个订单</span></div></div>
	</div><br/>
	</div>
	<div id="pay_verify_form2" style="<?php echo $conf['pay_verify']!=2?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启人机验证的商户</label>
	  <div class="col-sm-9"><input type="text" name="pay_verify_check_uid" value="<?php echo $conf['pay_verify_check_uid']; ?>" class="form-control" placeholder="多个商户号用|隔开"/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">人机验证类型</label>
	  <div class="col-sm-9"><select class="form-control" name="pay_verify_type" default="<?php echo $conf['pay_verify_type']?>"><option value="0">JS跳转验证（速度快）</option><option value="1">极验无感验证（速度慢）</option><option value="2">极验滑动验证（速度慢且体验差）</option><option value="3">禁止支付</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付风控检测设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <h4 style="text-align: center;">检测通道下连续未支付订单数量自动关闭支付通道</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_channel" default="<?php echo $conf['auto_check_channel']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="check_channel_second" value="<?php echo $conf['check_channel_second']; ?>" class="form-control" placeholder="检测多少秒内"/><span class="input-group-addon">秒</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="check_channel_failcount" value="<?php echo $conf['check_channel_failcount']; ?>" class="form-control" placeholder="连续未支付订单数量"/><span class="input-group-addon">个订单</span></div></div>
	  <div class="col-sm-offset-3 col-sm-9"><div class="input-group"><span class="input-group-addon">通道ID</span><input type="text" name="check_channel_ids" value="<?php echo $conf['check_channel_ids']; ?>" class="form-control" placeholder="留空为全部通道，多个通道ID用,隔开"/></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="check_channel_notice" default="<?php echo $conf['check_channel_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<h4 style="text-align: center;">检测商户订单支付成功率自动关闭商户支付权限</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_sucrate" default="<?php echo $conf['auto_check_sucrate']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="check_sucrate_second" value="<?php echo $conf['check_sucrate_second']; ?>" class="form-control" placeholder="检测多少秒内"/><span class="input-group-addon">秒</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="check_sucrate_count" value="<?php echo $conf['check_sucrate_count']; ?>" class="form-control" placeholder="超过多少个订单时"/><span class="input-group-addon">个订单</span></div></div>
	  <div class="col-sm-offset-3 col-sm-9"><div class="input-group"><span class="input-group-addon">成功率</span><input type="text" name="check_sucrate_value" value="<?php echo $conf['check_sucrate_value']; ?>" class="form-control" placeholder="成功率低于多少时自动关闭商户支付权限"/><span class="input-group-addon">%</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="check_sucrate_notice" default="<?php echo $conf['check_sucrate_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<h4 style="text-align: center;">检测商户订单回调失败次数自动关闭商户支付权限</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_notify" default="<?php echo $conf['auto_check_notify']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="check_notify_count" value="<?php echo $conf['check_notify_count']; ?>" class="form-control" placeholder="连续回调失败订单数量"/><span class="input-group-addon">个订单</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="check_notify_notice" default="<?php echo $conf['check_notify_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><h4 style="text-align: center;">检测商户订单投诉率自动关闭商户支付权限</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_complain" default="<?php echo $conf['auto_check_complain']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="check_complain_rate" value="<?php echo $conf['check_complain_rate']; ?>" class="form-control" placeholder="7天内投诉率高于多少时，填写1~100的数字"/><span class="input-group-addon">%</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="check_complain_notice" default="<?php echo $conf['check_complain_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/><?php }?>
	<h4 style="text-align: center;">检测单个IP连续未支付订单数量自动封禁IP</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_payip" default="<?php echo $conf['auto_check_payip']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="check_payip_second" value="<?php echo $conf['check_payip_second']; ?>" class="form-control" placeholder="单个IP连续多少秒内"/><span class="input-group-addon">秒</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="check_payip_count" value="<?php echo $conf['check_payip_count']; ?>" class="form-control" placeholder="超过多少个未支付订单时"/><span class="input-group-addon">个订单</span></div></div>
	</div><br/>
	<h4 style="text-align: center;">检测单个支付账号订单支付速度自动封禁账号</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="auto_check_payspeed" default="<?php echo $conf['auto_check_payspeed']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <label class="col-sm-3 control-label">参数设置</label>
	  <div class="col-sm-4"><div class="input-group"><input type="text" name="check_payspeed_second" value="<?php echo $conf['check_payspeed_second']; ?>" class="form-control" placeholder="单个支付账号多少秒内"/><span class="input-group-addon">秒</span></div></div>
	  <div class="col-sm-5"><div class="input-group"><input type="text" name="check_payspeed_count" value="<?php echo $conf['check_payspeed_count']; ?>" class="form-control" placeholder="成功支付订单数量"/><span class="input-group-addon">个订单</span></div></div>
	  <div class="col-sm-offset-3 col-sm-9"><div class="input-group"><input type="text" name="check_payspeed_days" value="<?php echo $conf['check_payspeed_days']; ?>" class="form-control" placeholder="封禁天数"/><span class="input-group-addon">天</span></div></div>
	</div><br/>
	<h4 style="text-align: center;">根据下单异常提示关键词自动关闭支付通道</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">下单异常提示关键词</label>
	  <div class="col-sm-9"><input type="text" name="check_paymsg" value="<?php echo $conf['check_paymsg']; ?>" class="form-control" placeholder="留空为不开启，多个关键词用|隔开"/><font color="green">例如填写“收款功能已被限制”，当支付下单出现该异常提示时，自动关闭该支付通道</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="check_paymsg_notice" default="<?php echo $conf['check_paymsg_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<h4 style="text-align: center;">支付宝直付通结算失败自动关闭支付通道</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">功能开关</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_settle_check" default="<?php echo $conf['alipay_settle_check']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发送提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_settle_notice" default="<?php echo $conf['alipay_settle_notice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div><br/>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
开启后在计划任务设置会多出一个url，定时访问该url后才生效。自动关闭的商户会在<a href="./risk.php">风控记录</a>里面显示。
</div>
</div>
<script>
$("select[name='pay_verify']").change(function(){
	if($(this).val() == 1){
		$("#pay_verify_form").show();
		$("#pay_verify_form2").hide();
	}else if($(this).val() == 2){
		$("#pay_verify_form").hide();
		$("#pay_verify_form2").show();
	}else{
		$("#pay_verify_form").hide();
		$("#pay_verify_form2").hide();
	}
});
</script>
<?php
}elseif($mod=='settle'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">结算规则配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算总开关</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_open" default="<?php echo $conf['settle_open']?>"><option value="0">关闭结算功能</option><option value="1">只开启每日自动结算</option><option value="2">只开启手动申请结算</option><option value="3">开启自动+手动结算</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算周期</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_type" default="<?php echo $conf['settle_type']?>"><option value="0">D+0（可结算全部余额）</option><option value="1">D+1（可结算前1天的余额）</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">手动结算自动转账</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_transfer" default="<?php echo $conf['settle_transfer']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">如开启，需要先开启转账付款功能</font></div>
	</div><br/>
	<div id="settle_transfer_form" style="<?php echo $conf['settle_transfer']!=1?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">超出多少元不自动转账</label>
	  <div class="col-sm-9"><input type="text" name="settle_transfermax" value="<?php echo $conf['settle_transfermax']; ?>" class="form-control" placeholder="留空则不限制最大自动转账金额"/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每日手动申请次数限制</label>
	  <div class="col-sm-9"><input type="text" name="settle_maxlimit" value="<?php echo $conf['settle_maxlimit']; ?>" class="form-control" placeholder="留空则不限制每日手动申请结算次数"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">最低结算金额</label>
	  <div class="col-sm-9"><input type="text" name="settle_money" value="<?php echo $conf['settle_money']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算手续费</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="settle_rate" value="<?php echo $conf['settle_rate']; ?>" class="form-control"/><span class="input-group-addon">%</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算手续费最小</label>
	  <div class="col-sm-9"><input type="text" name="settle_fee_min" value="<?php echo $conf['settle_fee_min']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算手续费最大</label>
	  <div class="col-sm-9"><input type="text" name="settle_fee_max" value="<?php echo $conf['settle_fee_max']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝结算开关</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_alipay" default="<?php echo $conf['settle_alipay']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信结算开关</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_wxpay" default="<?php echo $conf['settle_wxpay']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">QQ钱包结算开关</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_qqpay" default="<?php echo $conf['settle_qqpay']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">银行卡结算开关</label>
	  <div class="col-sm-9"><select class="form-control" name="settle_bank" default="<?php echo $conf['settle_bank']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户满多少金额自动转账</label>
	  <div class="col-sm-9"><input type="text" name="auto_settle_money" value="<?php echo $conf['auto_settle_money']; ?>" class="form-control" placeholder="留空为不自动转账"/><font color="green">如开启，需要先开启转账付款功能，填写后，计划任务将出现一个新的url，只支持支付宝结算方式</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<script>
$("select[name='settle_transfer']").change(function(){
	if($(this).val() == 1){
		$("#settle_transfer_form").show();
	}else{
		$("#settle_transfer_form").hide();
	}
});
</script>
<?php
}elseif($mod=='gonggao'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">其他公告与排版设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户中心弹出公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="modal" rows="5" placeholder="不填写则不显示弹出公告"><?php echo $conf['modal']?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">注册页面弹出公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="zhuce" rows="5" placeholder="不填写则不显示弹出公告"><?php echo $conf['zhuce']?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">首页底部排版</label>
	  <div class="col-sm-10"><textarea class="form-control" name="footer" rows="3" placeholder="可填写备案号等"><?php echo $conf['footer']?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/><br/>
	  <a href="./gonggao.php" class="btn btn-default btn-block">用户中心公告列表</a>
	 </div>
	</div>
  </form>
</div>
<?php
}elseif($mod=='transfer'){
	$alipay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%alipay%')");
	$wxpay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%wxpay%')");
	$qqpay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%qqpay%')");
	$bank_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%bank%')");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">转账付款配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">付款方显示名称</label>
	  <div class="col-sm-9"><input type="text" name="transfer_name" value="<?php echo $conf['transfer_name']; ?>" class="form-control" placeholder="支付宝转账时显示"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">付款默认备注</label>
	  <div class="col-sm-9"><input type="text" name="transfer_desc" value="<?php echo $conf['transfer_desc']; ?>" class="form-control" placeholder="微信和QQ转账时显示"/></div>
	</div><hr/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝转账接口通道</label>
	  <div class="col-sm-9"><select class="form-control" name="transfer_alipay" default="<?php echo $conf['transfer_alipay']?>" onchange="changeAliChannel(this)"><option value="0">关闭</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select><font color="green">请先添加支付插件为alipay的支付通道。需签约“转账到支付宝账户”产品。<br/>若选择“手动转账”，用户提交代付后为待转账状态，需管理员手动转账并更改状态。</font></div>
	</div><br/>
	<?php if(class_exists('\\lib\\AlipaySATF\\AlipaySATF')){?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝安全发</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_satf" default="<?php echo $conf['alipay_satf']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green" id="alipay_satf_note" style="display:none">需开通安全发-服务商模式才能使用。<br/>订阅“资金单据状态变更通知”、“资金二级商户KYB代进件状态通知接口”，应用网关地址：<?php echo $siteurl?>pay/appgw/<span class="channelid">0</span>/</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝安全发手续费</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_satf_fee_type" default="<?php echo $conf['alipay_satf_fee_type']?>"><option value="0">从商户余额扣除手续费（默认）</option><option value="1">手续费转账到指定支付宝账户</option></select></div>
	</div><br/>
	<div id="alipay_satf_fee_type_div" style="display:none">
	<div class="form-group">
	  <label class="col-sm-3 control-label">手续费接收支付宝UID</label>
	  <div class="col-sm-9"><input type="text" name="alipay_satf_fee_account" value="<?php echo $conf['alipay_satf_fee_account']; ?>" class="form-control" placeholder="填写2088开头的支付宝UID"/></div>
	</div><br/>
	</div>
	<?php }?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信转账接口通道</label>
	  <div class="col-sm-9"><select class="form-control" name="transfer_wxpay" default="<?php echo $conf['transfer_wxpay']?>"><option value="0">关闭</option><?php foreach($wxpay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select><font color="green">请先添加支付插件为wxpay或wxpayn的支付通道。需申请“企业付款”（V2插件）或“商家转账”（V3插件）产品。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信V3转账产品类型</label>
	  <div class="col-sm-9"><select class="form-control" name="transfer_wxpay_type" default="<?php echo $conf['transfer_wxpay_type']?>"><option value="0">商家转账到零钱（旧）</option><option value="1">商家转账（新）</option></select></div>
	</div><br/>
	<div id="transfer_wxpay_new_div" style="display:none">
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信商家转账-场景ID</label>
	  <div class="col-sm-9"><input type="text" name="transfer_wxpay_scene_id" value="<?php echo $conf['transfer_wxpay_scene_id']; ?>" class="form-control" placeholder=""/><font color="green">可前往“微信商户平台-产品中心-商家转账”中申请。如：1001-现金营销</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信商家转账-报备信息类型</label>
	  <div class="col-sm-9"><input type="text" name="transfer_wxpay_info_type" value="<?php echo $conf['transfer_wxpay_info_type']; ?>" class="form-control" placeholder=""/><font color="green">请根据<a href="https://pay.weixin.qq.com/doc/v3/merchant/4012711988#%EF%BC%883%EF%BC%89%E6%8C%89%E8%BD%AC%E8%B4%A6%E5%9C%BA%E6%99%AF%E6%8A%A5%E5%A4%87%E8%83%8C%E6%99%AF%E4%BF%A1%E6%81%AF" target="_blank" rel="noreferrer">产品文档</a>确认当前转账场景下需传入的信息类型，需按要求填入，有多个类型时用|隔开</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信商家转账-报备信息内容</label>
	  <div class="col-sm-9"><input type="text" name="transfer_wxpay_info_content" value="<?php echo $conf['transfer_wxpay_info_content']; ?>" class="form-control" placeholder=""/><font color="green">请根据信息类型，描述当前这笔转账单的转账背景，有多个类型时用|隔开</font></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">QQ钱包转账接口通道</label>
	  <div class="col-sm-9"><select class="form-control" name="transfer_qqpay" default="<?php echo $conf['transfer_qqpay']?>"><option value="0">关闭</option><?php foreach($qqpay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select><font color="green">请先添加支付插件为qqpay的支付通道。需申请“企业付款”产品。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">银行卡转账接口通道</label>
	  <div class="col-sm-9"><select class="form-control" name="transfer_bank" default="<?php echo $conf['transfer_bank']?>"><option value="0">关闭</option><?php foreach($bank_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select><font color="green">请先添加支付插件为alipay的支付通道。需签约“转账到银行卡”产品。或wxpay插件，签约“付款到银行卡”。部分其他插件也有付款到银行卡功能</font></div>
	</div><hr/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">用户中心代付功能</label>
	  <div class="col-sm-9"><select class="form-control" name="user_transfer" default="<?php echo $conf['user_transfer']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">用户中心创建红包功能</label>
	  <div class="col-sm-9"><select class="form-control" name="user_transfer_red" default="<?php echo $conf['user_transfer_red']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">最小代付金额限制</label>
	  <div class="col-sm-9"><input type="text" name="transfer_minmoney" value="<?php echo $conf['transfer_minmoney']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">最大代付金额限制</label>
	  <div class="col-sm-9"><input type="text" name="transfer_maxmoney" value="<?php echo $conf['transfer_maxmoney']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每日对单人最大代付笔数限制</label>
	  <div class="col-sm-9"><input type="text" name="transfer_maxlimit" value="<?php echo $conf['transfer_maxlimit']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代付手续费</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="transfer_rate" value="<?php echo $conf['transfer_rate']; ?>" class="form-control" placeholder="留空则与结算手续费一样"/><span class="input-group-addon">%</span></div></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<script>
function changeAliChannel(obj){
	var channel = $(obj).val()
	$(".channelid").text(channel)
}
$("select[name='alipay_satf']").change(function(){
	if($(this).val() == 1){
		$("#alipay_satf_note").show();
	}else{
		$("#alipay_satf_note").hide();
	}
});
$("select[name='alipay_satf_fee_type']").change(function(){
	if($(this).val() == 1){
		$("#alipay_satf_fee_type_div").show();
	}else{
		$("#alipay_satf_fee_type_div").hide();
	}
});
$("select[name='transfer_wxpay_type']").change(function(){
	if($(this).val() == 1){
		$("#transfer_wxpay_new_div").show();
	}else{
		$("#transfer_wxpay_new_div").hide();
	}
})
$(document).ready(function(){
	$("select[name='transfer_alipay']").change()
	$("select[name='alipay_satf']").change()
	$("select[name='alipay_satf_fee_type']").change()
	$("select[name='transfer_wxpay_type']").change()
})
</script>
<?php
}elseif($mod=='certificate'){
	$alipay_channel = $DB->getAll("SELECT * FROM pre_channel WHERE plugin='alipay'");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">实名认证接口配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-3 control-label">是否开启实名认证</label>
	  <div class="col-sm-9"><select class="form-control" name="cert_open" default="<?php echo $conf['cert_open']?>"><option value="0">关闭</option><option value="1">支付宝身份验证</option><option value="3">支付宝实名信息验证</option><option value="5">阿里云金融级实人认证</option><option value="4">微信扫码实名认证</option><option value="2">手机号三要素实名认证</option></select></div>
	</div><br/>
	<div id="setform2" style="<?php echo $conf['cert_open']!=1&&$conf['cert_open']!=3?'display:none;':null; ?>">
    <div class="form-group">
	  <label class="col-sm-3 control-label">支付宝通道选择</label>
	  <div class="col-sm-9"><select class="form-control" name="cert_channel" default="<?php echo $conf['cert_channel']?>"><option value="0">关闭</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?></select><font color="green">请先添加支付插件为alipay的支付通道</font></div>
	</div><br/>
	</div>
	<div id="setform3" style="<?php echo $conf['cert_open']!=2?'display:none;':null; ?>">
    <div class="form-group">
	  <label class="col-sm-3 control-label">APPCODE</label>
	  <div class="col-sm-9"><input type="text" name="cert_appcode" value="<?php echo $conf['cert_appcode']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	</div>
	<div id="setform4" style="<?php echo $conf['cert_open']!=4?'display:none;':null; ?>">
    <div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretId</label>
	  <div class="col-sm-9"><input type="text" name="cert_qcloudid" value="<?php echo $conf['cert_qcloudid']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretKey</label>
	  <div class="col-sm-9"><input type="text" name="cert_qcloudkey" value="<?php echo $conf['cert_qcloudkey']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	</div>
	<div id="setform5" style="<?php echo $conf['cert_open']!=5?'display:none;':null; ?>">
    <div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKeyId</label>
	  <div class="col-sm-9"><input type="text" name="cert_aliyunid" value="<?php echo $conf['cert_aliyunid']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKeySecret</label>
	  <div class="col-sm-9"><input type="text" name="cert_aliyunkey" value="<?php echo $conf['cert_aliyunkey']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云认证场景ID</label>
	  <div class="col-sm-9"><input type="text" name="cert_aliyunsceneid" value="<?php echo $conf['cert_aliyunsceneid']; ?>" class="form-control" placeholder="阿里云金融级实人认证-接入设置里面"/></div>
	</div><br/>
	</div>
	<div id="setform1" style="<?php echo $conf['cert_open']==0?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启企业认证方式</label>
	  <div class="col-sm-9"><select class="form-control" name="cert_corpopen" default="<?php echo $conf['cert_corpopen']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div id="setform6" style="<?php echo $conf['cert_corpopen']!=1?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">企业信息校验接口APPCODE</label>
	  <div class="col-sm-9"><input type="text" name="cert_appcode2" value="<?php echo $conf['cert_appcode2']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户强制认证</label>
	  <div class="col-sm-9"><select class="form-control" name="cert_force" default="<?php echo $conf['cert_force']?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后商户必须实名认证，才能正常使用支付接口收款</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">实名认证费用</label>
	  <div class="col-sm-9"><input type="text" name="cert_money" value="<?php echo $conf['cert_money']; ?>" class="form-control" placeholder="留空或0为免认证费用"/><font color="green">支付宝身份验证接口是1元/次。设置实名认证费用后，认证成功将从商户余额扣除，如果是付费注册商户建议免认证费</font></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
<br/><b>支付宝身份验证：</b><a href="https://b.alipay.com/signing/productDetailV2.htm?productId=I1080300001000010588" target="_blank" rel="noreferrer">申请地址</a>，该接口费用1元/人，支持人脸识别，同一个人重复验证不重复收费
<br/><b>支付宝实名信息验证：</b><a href="https://opendocs.alipay.com/open/repo-00hddl" target="_blank" rel="noreferrer">申请地址</a>，该接口完全免费，授权回调地址填写：<?php echo $siteurl.'user/oauth.php';?>
<br/><b>阿里云金融级实人认证：</b><a href="https://www.aliyun.com/product/cloudauth" target="_blank" rel="noreferrer">申请地址</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取密钥</a>，该接口费用1元/人，支持人脸识别，同一个人重复验证不重复收费。无需签约，只需要阿里云企业认证账号即可开通。
<br/><b>微信扫码实名认证：</b><a href="https://cloud.tencent.com/product/faceid" target="_blank" rel="noreferrer">申请地址</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer">获取密钥</a>，接口0.3元/次
<br/><b>手机号三要素实名认证：</b><a href="https://market.aliyun.com/products/57000002/cmapi031847.html" target="_blank" rel="noreferrer">点击进入</a>
<br/><b>企业信息校验接口：</b><a href="https://market.aliyun.com/products/56928005/cmapi00043309.html" target="_blank" rel="noreferrer">点击进入</a>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">OCR文字识别接口配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">文字识别接口</label>
	  <div class="col-sm-9"><select class="form-control" name="ocr_type" default="<?php echo $conf['ocr_type']??'aliyun'?>"><option value="aliyun">阿里云</option><option value="baidu">百度云</option></select></div>
	</div><br/>
	<div id="set_ocr_aliyun" style="<?php echo $conf['ocr_type']=='baidu'?'display:none;':null; ?>">
    <div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKeyId</label>
	  <div class="col-sm-9"><input type="text" name="ocr_aliyunid" value="<?php echo $conf['ocr_aliyunid']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKeySecret</label>
	  <div class="col-sm-9"><input type="text" name="ocr_aliyunkey" value="<?php echo $conf['ocr_aliyunkey']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	</div>
	<div id="set_ocr_baidu" style="<?php echo $conf['ocr_type']!='baidu'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">百度云API Key</label>
	  <div class="col-sm-9"><input type="text" name="ocr_baiduid" value="<?php echo $conf['ocr_baiduid']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">百度云Secret Key</label>
	  <div class="col-sm-9"><input type="text" name="ocr_baidukey" value="<?php echo $conf['ocr_baidukey']; ?>" class="form-control" placeholder=""/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
<br/><b>阿里云OCR文字识别：</b><a href="https://ai.aliyun.com/ocr" target="_blank" rel="noreferrer">开通地址</a>，需开通“个人证照识别”、“企业资质识别”
<br/><b>百度云OCR文字识别：</b><a href="https://cloud.baidu.com/product/ocr.html" target="_blank" rel="noreferrer">开通地址</a>
</div>
</div>
<script>
$("select[name='cert_open']").change(function(){
	if($(this).val() > 0){
		$("#setform1").show();
		if($(this).val() == 2){
			$("#setform2").hide();
			$("#setform3").show();
			$("#setform4").hide();
			$("#setform5").hide();
		}else if($(this).val() == 4){
			$("#setform2").hide();
			$("#setform3").hide();
			$("#setform4").show();
			$("#setform5").hide();
		}else if($(this).val() == 5){
			$("#setform2").hide();
			$("#setform3").hide();
			$("#setform4").hide();
			$("#setform5").show();
		}else{
			$("#setform2").show();
			$("#setform3").hide();
			$("#setform4").hide();
			$("#setform5").hide();
		}
	}else{
		$("#setform1").hide();
		$("#setform2").hide();
		$("#setform3").hide();
		$("#setform4").hide();
		$("#setform5").hide();
	}
});
$("select[name='cert_corpopen']").change(function(){
	if($(this).val() == 1){
		$("#setform6").show();
	}else{
		$("#setform6").hide();
	}
});
$("select[name='ocr_type']").change(function(){
	if($(this).val() == 'aliyun'){
		$("#set_ocr_aliyun").show();
		$("#set_ocr_baidu").hide();
	}else if($(this).val() == 'baidu'){
		$("#set_ocr_aliyun").hide();
		$("#set_ocr_baidu").show();
	}
});
</script>
<?php
}elseif($mod=='oauth'){
	$alipay_channel = $DB->getAll("SELECT * FROM pre_channel WHERE plugin='alipay' OR plugin='alipaysl' OR plugin='alipayd'");
	$wxpay_channel = $DB->getAll("SELECT * FROM pre_weixin WHERE type=0");
	$wxapplet_channel = $DB->getAll("SELECT * FROM pre_weixin WHERE type=1");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">快捷登录配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">QQ快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="login_qq" default="<?php echo $conf['login_qq']?>"><option value="0">关闭</option><option value="1">QQ互联官方快捷登录</option><option value="2">手机QQ扫码登录</option><option value="3">彩虹聚合登录</option></select><a href="https://connect.qq.com" target="_blank" rel="noreferrer">QQ互联申请地址</a>，回调地址填写：<?php echo $siteurl.'user/connect.php';?></div>
	</div><br/>
	<div id="setform1" style="<?php echo $conf['login_qq']!=1?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">QQ快捷登录Appid</label>
	  <div class="col-sm-9"><input type="text" name="login_qq_appid" value="<?php echo $conf['login_qq_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">QQ快捷登录Appkey</label>
	  <div class="col-sm-9"><input type="text" name="login_qq_appkey" value="<?php echo $conf['login_qq_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="login_alipay" default="<?php echo $conf['login_alipay']?>"><option value="0">关闭</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?><option value="-1">彩虹聚合登录</option></select><font color="green">请先添加支付插件为alipay的支付通道</font><br/><a href="https://openhome.alipay.com/platform/appManage.htm" target="_blank" rel="noreferrer">申请地址</a>，应用内添加功能"获取会员信息"，授权回调地址填写：<?php echo $siteurl.'user/oauth.php';?></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="login_wx" default="<?php echo $conf['login_wx']?>"><option value="0">关闭</option><?php foreach($wxpay_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?><option value="-1">彩虹聚合登录</option></select><font color="green">请先<a href="./pay_weixin.php" target="_blank">添加一个微信公众号</a>。需要服务号，并配置网页授权域名：<?php echo $_SERVER['HTTP_HOST'];?></font></div>
	</div><br/>
	<div id="setform2" style="<?php echo $conf['login_wx']<=0?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信多域名回调系统URL</label>
	  <div class="col-sm-9"><input type="text" name="wx_open_url" value="<?php echo $conf['wx_open_url']; ?>" class="form-control" placeholder="选填，以http://或https://开头，结尾不要有/"/><font color="green">选填，填写后将替换https://open.weixin.qq.com，<a href="https://github.com/netcccyun/wxredirect/releases" target="_blank" rel="noreferrer">微信多域名回调系统</a>用于解决微信公众号网页授权域名数量限制的问题</font></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">微信小程序快捷登录</label>
	  <div class="col-sm-9"><select class="form-control" name="login_wxa" default="<?php echo $conf['login_wxa']?>"><option value="0">关闭</option><?php foreach($wxapplet_channel as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].'</option>';} ?></select><font color="green">请先<a href="./pay_weixin.php" target="_blank">添加一个微信小程序</a>。用于微信小程序客户端快捷登录</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php if($conf['login_qq']==3 || $conf['login_alipay']==-1 || $conf['login_wx']==-1){?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">彩虹聚合登录配置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录API接口地址</label>
	  <div class="col-sm-9"><input type="text" name="login_apiurl" value="<?php echo $conf['login_apiurl']; ?>" class="form-control" placeholder="API地址要以http://开头，以/结尾"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录应用APPID</label>
	  <div class="col-sm-9"><input type="text" name="login_appid" value="<?php echo $conf['login_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录应用APPKEY</label>
	  <div class="col-sm-9"><input type="text" name="login_appkey" value="<?php echo $conf['login_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php }?>
<script>
$("select[name='login_qq']").change(function(){
	if($(this).val() == 1){
		$("#setform1").show();
	}else if($(this).val() == 2){
		$("#setform1").hide();
	}else{
		$("#setform1").hide();
	}
});
$("select[name='login_wx']").change(function(){
	if($(this).val() > 0){
		$("#setform2").show();
	}else{
		$("#setform2").hide();
	}
});
</script>
<?php
}elseif($mod=='mailtest'){
	$mail_name = $conf['mail_recv']?$conf['mail_recv']:$conf['mail_name'];
	if(!empty($mail_name)){
	$result=send_mail($mail_name,'邮件发送测试。','这是一封测试邮件！<br/><br/>来自：'.$siteurl);
	if($result==1)
		showmsg('邮件发送成功！',1);
	else
		showmsg('邮件发送失败！'.$result,3);
	}
	else
		showmsg('您还未设置邮箱！',3);
}elseif($mod=='mail'){
	$wxpay_channel = $DB->getAll("SELECT * FROM pre_weixin WHERE type=0");
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">发信邮箱设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">发信模式</label>
	  <div class="col-sm-10"><select class="form-control" name="mail_cloud" default="<?php echo $conf['mail_cloud']?>"><option value="0">SMTP发信</option><option value="1">搜狐Sendcloud</option><option value="2">阿里云邮件推送</option></select></div>
	</div><br/>
	<div id="frame_set1" style="<?php echo $conf['mail_cloud']>1?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-2 control-label">SMTP服务器</label>
	  <div class="col-sm-10"><input type="text" name="mail_smtp" value="<?php echo $conf['mail_smtp']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">SMTP端口</label>
	  <div class="col-sm-10"><input type="text" name="mail_port" value="<?php echo $conf['mail_port']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">邮箱账号</label>
	  <div class="col-sm-10"><input type="text" name="mail_name" value="<?php echo $conf['mail_name']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">邮箱密码</label>
	  <div class="col-sm-10"><input type="text" name="mail_pwd" value="<?php echo $conf['mail_pwd']; ?>" class="form-control"/></div>
	</div><br/>
	</div>
	<div id="frame_set2" style="<?php echo $conf['mail_cloud']==0?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-2 control-label">API_USER</label>
	  <div class="col-sm-10"><input type="text" name="mail_apiuser" value="<?php echo $conf['mail_apiuser']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">API_KEY</label>
	  <div class="col-sm-10"><input type="text" name="mail_apikey" value="<?php echo $conf['mail_apikey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">发信邮箱</label>
	  <div class="col-sm-10"><input type="text" name="mail_name2" value="<?php echo $conf['mail_name2']; ?>" class="form-control"/></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">收信邮箱</label>
	  <div class="col-sm-10"><input type="text" name="mail_recv" value="<?php echo $conf['mail_recv']; ?>" class="form-control" placeholder="不填默认为发信邮箱"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/><?php if($conf['mail_name']){?>[<a href="set.php?mod=mailtest">给 <?php echo $conf['mail_recv']?$conf['mail_recv']:$conf['mail_name']?> 发一封测试邮件</a>]<?php }?>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
使用普通模式发信时，建议使用QQ邮箱，SMTP服务器smtp.qq.com，端口465，密码不是QQ密码也不是邮箱独立密码，是QQ邮箱设置界面生成的<a href="https://service.mail.qq.com/detail/0/75"  target="_blank" rel="noreferrer">授权码</a>。<br/>阿里云邮件推送：<a href="https://www.aliyun.com/product/directmail" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取AK/SK</a>
</div>
</div>
<script>
$("select[name='mail_cloud']").change(function(){
	if($(this).val() == 0){
		$("#frame_set1").show();
		$("#frame_set2").hide();
	}else{
		$("#frame_set1").hide();
		$("#frame_set2").show();
	}
});
</script>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">短信接口设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">接口选择</label>
	  <div class="col-sm-10"><select class="form-control" name="sms_api" default="<?php echo $conf['sms_api']?>"><option value="0">企信通短信接口</option><option value="1">腾讯云短信接口</option><option value="2">阿里云短信接口</option><option value="3">ThinkAPI短信接口</option><option value="4">短信宝短信接口</option></select></div>
	</div><br/>
	<div class="form-group" id="showAppId" style="<?php echo $conf['sms_api']==0||$conf['sms_api']==3?'display:none;':null; ?>">
	  <label class="col-sm-2 control-label">AppId</label>
	  <div class="col-sm-10"><input type="text" name="sms_appid" value="<?php echo $conf['sms_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">AppKey</label>
	  <div class="col-sm-10"><input type="text" name="sms_appkey" value="<?php echo $conf['sms_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group" id="showSign" style="<?php echo $conf['sms_api']==0?'display:none;':null; ?>">
	  <label class="col-sm-2 control-label">短信签名内容</label>
	  <div class="col-sm-10"><input type="text" name="sms_sign" value="<?php echo $conf['sms_sign']; ?>" class="form-control"/><font color="green">必须是已添加、并通过审核的短信签名。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">商户注册模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_reg" value="<?php echo $conf['sms_tpl_reg']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">找回密码模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_find" value="<?php echo $conf['sms_tpl_find']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">修改结算账号模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_edit" value="<?php echo $conf['sms_tpl_edit']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">余额不足通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_balance" value="<?php echo $conf['sms_tpl_balance']; ?>" class="form-control" placeholder="留空则不开启短信通知，模板有1个最低余额变量code"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户组到期通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_group" value="<?php echo $conf['sms_tpl_group']; ?>" class="form-control" placeholder="留空则不开启用户组到期通知，模板有1个用户组名称变量code"/></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-2 control-label">订单投诉通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="sms_tpl_complain" value="<?php echo $conf['sms_tpl_complain']; ?>" class="form-control" placeholder="留空则不开启短信通知，模板有1个订单号变量code"/></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
腾讯云短信接口：<a href="https://console.cloud.tencent.com/sms/smslist" target="_blank" rel="noreferrer">点此进入</a><br/>阿里云短信接口：<a href="https://dysms.console.aliyun.com/dysms.htm" target="_blank" rel="noreferrer">点此进入</a><br/>ThinkAPI短信接口：<a href="https://www.kancloud.cn/topthink-doc/think-api/2203721" target="_blank" rel="noreferrer">点此进入</a>，签名内容处填写签名ID<br/>企信通短信接口：<a href="http://sms.php.gs/" target="_blank" rel="noreferrer">点此进入</a><br/>
短信宝短信接口：<a href="https://www.smsbao.com/" target="_blank" rel="noreferrer">点此进入</a>，模板ID处写模板内容（不含签名，验证码用{code}代替）<br/>
短信验证码模板只需要1个验证码变量code即可。
</div>
</div>
<script>
$("select[name='sms_api']").change(function(){
	if($(this).val() == 0){
		$("#showAppId").hide();
		$("#showSign").hide();
	}else if($(this).val() == 3){
		$("#showAppId").hide();
		$("#showSign").show();
	}else{
		$("#showAppId").show();
		$("#showSign").show();
	}
});
</script>
<?php
}elseif($mod=='notice'){
$errmsg = $CACHE->read('wxtplerrmsg');
if($errmsg){
	$arr = unserialize($errmsg);
	$errmsg = $arr['time'].' - '.$arr['errmsg'];
}

$errmsg2 = $CACHE->read('mailerrmsg');
if($errmsg2){
	$arr = unserialize($errmsg2);
	$errmsg2 = $arr['time'].' - '.$arr['errmsg'];
}
?>
<style>.wxparam{display: inline;width: 100px; margin: 0 3px;}</style>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">微信公众号消息提醒设置</h3></div>
<div class="panel-body">
<?php if($errmsg){?><div class="alert alert-warning">上一次报错信息：<?php echo $errmsg;?></div><?php }?>
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">公众号消息开关</label>
	  <div class="col-sm-10"><select class="form-control" name="wxnotice" default="<?php echo $conf['wxnotice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新订单通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_order" value="<?php echo $conf['wxnotice_tpl_order']; ?>" class="form-control" placeholder="留空则不开启此消息"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新订单通知模板参数</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_order_no" value="<?php echo $conf['wxnotice_tpl_order_no']; ?>" class="form-control wxparam" placeholder="订单编号"/><input type="text" name="wxnotice_tpl_order_name" value="<?php echo $conf['wxnotice_tpl_order_name']; ?>" class="form-control wxparam" placeholder="商品名称"/><input type="text" name="wxnotice_tpl_order_money" value="<?php echo $conf['wxnotice_tpl_order_money']; ?>" class="form-control wxparam" placeholder="订单金额"/><input type="text" name="wxnotice_tpl_order_time" value="<?php echo $conf['wxnotice_tpl_order_time']; ?>" class="form-control wxparam" placeholder="交易时间"/><input type="text" name="wxnotice_tpl_order_outno" value="<?php echo $conf['wxnotice_tpl_order_outno']; ?>" class="form-control wxparam" placeholder="外部订单号"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">结算通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_settle" value="<?php echo $conf['wxnotice_tpl_settle']; ?>" class="form-control" placeholder="留空则不开启此消息"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">结算通知模板参数</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_settle_type" value="<?php echo $conf['wxnotice_tpl_settle_type']; ?>" class="form-control wxparam" placeholder="交易类型"/><input type="text" name="wxnotice_tpl_settle_account" value="<?php echo $conf['wxnotice_tpl_settle_account']; ?>" class="form-control wxparam" placeholder="结算账号"/><input type="text" name="wxnotice_tpl_settle_money" value="<?php echo $conf['wxnotice_tpl_settle_money']; ?>" class="form-control wxparam" placeholder="结算金额"/><input type="text" name="wxnotice_tpl_settle_realmoney" value="<?php echo $conf['wxnotice_tpl_settle_realmoney']; ?>" class="form-control wxparam" placeholder="实际到账"/><input type="text" name="wxnotice_tpl_settle_time" value="<?php echo $conf['wxnotice_tpl_settle_time']; ?>" class="form-control wxparam" placeholder="结算时间"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">登录通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_login" value="<?php echo $conf['wxnotice_tpl_login']; ?>" class="form-control" placeholder="留空则不开启此消息"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">登录通知模板参数</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_login_user" value="<?php echo $conf['wxnotice_tpl_login_user']; ?>" class="form-control wxparam" placeholder="登录帐号"/><input type="text" name="wxnotice_tpl_login_time" value="<?php echo $conf['wxnotice_tpl_login_time']; ?>" class="form-control wxparam" placeholder="登录时间"/><input type="text" name="wxnotice_tpl_login_name" value="<?php echo $conf['wxnotice_tpl_login_name']; ?>" class="form-control wxparam" placeholder="登录平台"/><input type="text" name="wxnotice_tpl_login_ip" value="<?php echo $conf['wxnotice_tpl_login_ip']; ?>" class="form-control wxparam" placeholder="IP地址"/><input type="text" name="wxnotice_tpl_login_iploc" value="<?php echo $conf['wxnotice_tpl_login_iploc']; ?>" class="form-control wxparam" placeholder="IP归属地"/></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-2 control-label">交易投诉通知模板ID</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_complain" value="<?php echo $conf['wxnotice_tpl_complain']; ?>" class="form-control" placeholder="留空则不开启此消息"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">交易投诉通知模板参数</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_complain_order_no" value="<?php echo $conf['wxnotice_tpl_complain_order_no']; ?>" class="form-control wxparam" placeholder="订单编号"/><input type="text" name="wxnotice_tpl_complain_time" value="<?php echo $conf['wxnotice_tpl_complain_time']; ?>" class="form-control wxparam" placeholder="投诉时间"/><input type="text" name="wxnotice_tpl_complain_reason" value="<?php echo $conf['wxnotice_tpl_complain_reason']; ?>" class="form-control wxparam" placeholder="投诉原因"/><input type="text" name="wxnotice_tpl_complain_type" value="<?php echo $conf['wxnotice_tpl_complain_type']; ?>" class="form-control wxparam" placeholder="投诉状态"/><input type="text" name="wxnotice_tpl_complain_name" value="<?php echo $conf['wxnotice_tpl_complain_name']; ?>" class="form-control wxparam" placeholder="商品名称"/></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <label class="col-sm-2 control-label">余额不足提醒模板ID</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_balance" value="<?php echo $conf['wxnotice_tpl_balance']; ?>" class="form-control" placeholder="留空则不开启此消息"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">余额不足提醒模板参数</label>
	  <div class="col-sm-10"><input type="text" name="wxnotice_tpl_balance_user" value="<?php echo $conf['wxnotice_tpl_balance_user']; ?>" class="form-control wxparam" placeholder="用户名"/><input type="text" name="wxnotice_tpl_balance_time" value="<?php echo $conf['wxnotice_tpl_balance_time']; ?>" class="form-control wxparam" placeholder="当前时间"/><input type="text" name="wxnotice_tpl_balance_money" value="<?php echo $conf['wxnotice_tpl_balance_money']; ?>" class="form-control wxparam" placeholder="当前余额"/><input type="text" name="wxnotice_tpl_balance_msg" value="<?php echo $conf['wxnotice_tpl_balance_msg']; ?>" class="form-control wxparam" placeholder="温馨提示"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
用于消息提醒的公众号，和快捷登录使用的公众号一样，也即必须同时开启微信快捷登录才可以使用该功能。<br/>
需申请模板消息的模板，不能用订阅消息！模板参数名类似于thingx、character_stringx、amountx等，在微信模板详情页面查看，无相关参数的可留空。如果不知道模板参数名怎么填写的，可先仔细阅读<a href="https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#%E5%8F%91%E9%80%81%E6%A8%A1%E6%9D%BF%E6%B6%88%E6%81%AF" target="_blank">发送模板消息接口文档</a>。<br/>
<b>需要用户在用户中心绑定微信快捷登录，并手动开启才能收到。</b>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">管理员接收消息设置</h3></div>
<div class="panel-body">
<?php if($errmsg2){?><div class="alert alert-warning">上一次报错信息：<?php echo $errmsg2;?></div><?php }?>
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">新注册商户待审核提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_regaudit" default="<?php echo $conf['msgconfig_regaudit']?>"><option value="0">关闭</option><option value="1">开启 - 邮件</option><option value="2">开启 - 群机器人</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户手动提现提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_apply" default="<?php echo $conf['msgconfig_apply']?>"><option value="0">关闭</option><option value="1">开启 - 邮件</option><option value="2">开启 - 群机器人</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">授权域名待审核提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_domain" default="<?php echo $conf['msgconfig_domain']?>"><option value="0">关闭</option><option value="1">开启 - 邮件</option><option value="2">开启 - 群机器人</option></select></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-3 control-label">全部交易投诉通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_complain_all" default="<?php echo $conf['msgconfig_complain_all']?>"><option value="0">关闭</option><option value="1">开启 - 邮件</option><option value="2">开启 - 群机器人</option><option value="3">开启 - 短信</option></select></div>
	</div><br/><?php }?>
	<?php if(class_exists('\\lib\\WxMchRisk')){?><div class="form-group">
	  <label class="col-sm-3 control-label">渠道商户违规通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_mchrisk_all" default="<?php echo $conf['msgconfig_mchrisk_all']?>"><option value="0">关闭</option><option value="1">开启 - 邮件</option><option value="2">开启 - 群机器人</option></select></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">风控检测提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_risk" default="<?php echo $conf['msgconfig_risk']?>"><option value="0">邮件</option><option value="1">群机器人</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">群机器人Webhook地址</label>
	  <div class="col-sm-9"><input type="text" name="msgrobot_url" value="<?php echo $conf['msgrobot_url']; ?>" class="form-control"/><font color="green">仅支持填写企业微信、钉钉、飞书群机器人的Webhook地址</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">管理员手机号码</label>
	  <div class="col-sm-9"><input type="text" name="msgrobot_phone" value="<?php echo $conf['msgrobot_phone']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户接收消息设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<h4 style="text-align: center;">用户接收消息渠道开关</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邮件消息开关</label>
	  <div class="col-sm-9"><select class="form-control" name="mailnotice" default="<?php echo $conf['mailnotice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">企业微信/钉钉/飞书群机器人开关</label>
	  <div class="col-sm-9"><select class="form-control" name="robotnotice" default="<?php echo $conf['robotnotice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<h4 style="text-align: center;">用户接收消息类型开关</h4>
	<div class="form-group">
	  <label class="col-sm-3 control-label">新订单通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_order" default="<?php echo $conf['msgconfig_order']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">结算通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_settle" default="<?php echo $conf['msgconfig_settle']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<?php if(class_exists('\\lib\\Complain\\CommUtil')){?><div class="form-group">
	  <label class="col-sm-3 control-label">交易投诉通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_complain" default="<?php echo $conf['msgconfig_complain']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/><?php }?>
	<?php if(class_exists('\\lib\\WxMchRisk')){?><div class="form-group">
	  <label class="col-sm-3 control-label">渠道商户违规通知</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_mchrisk" default="<?php echo $conf['msgconfig_mchrisk']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/><?php }?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">余额不足提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_balance" default="<?php echo $conf['msgconfig_balance']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">用户组到期提醒</label>
	  <div class="col-sm-9"><select class="form-control" name="msgconfig_group" default="<?php echo $conf['msgconfig_group']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
  	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
<b>用户接收消息的开关，除了在这里开启外，需要在用户中心手动开启才能收到！(用户组到期提醒除外)</b>
</div>
</div>
<?php
$errmsg3 = $CACHE->read('voiceerrmsg');
if($errmsg3){
	$arr = unserialize($errmsg3);
	$errmsg3 = $arr['time'].' - '.$arr['errmsg'];
}
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">云喇叭语音播报</h3></div>
<div class="panel-body">
<?php if($errmsg3){?><div class="alert alert-warning">上一次报错信息：<?php echo $errmsg3;?></div><?php }?>
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">云喇叭收款提醒开关</label>
	  <div class="col-sm-9"><select class="form-control" name="voicenotice" default="<?php echo $conf['voicenotice']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">云喇叭agent_id</label>
	  <div class="col-sm-9"><input type="text" name="voice_username" value="<?php echo $conf['voice_username']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">云喇叭agent_secret</label>
	  <div class="col-sm-9"><input type="text" name="voice_apikey" value="<?php echo $conf['voice_apikey']; ?>" class="form-control"/></div>
	</div><br/>
  	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
云喇叭对接<a href="https://m.qyypay.cn/" target="_blank" rel="noreferrer">启易付</a>硬件设备，支持聚合收款码语音播报。
</div>
</div>
<?php
$errmsg4 = $CACHE->read('printerrmsg');
if($errmsg4){
	$arr = unserialize($errmsg4);
	$errmsg4 = $arr['time'].' - '.$arr['errmsg'];
}
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">订单小票打印</h3></div>
<div class="panel-body">
<?php if($errmsg4){?><div class="alert alert-warning">上一次报错信息：<?php echo $errmsg4;?></div><?php }?>
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">订单小票打印开关</label>
	  <div class="col-sm-9"><select class="form-control" name="orderprint" default="<?php echo $conf['orderprint']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">APPID</label>
	  <div class="col-sm-9"><input type="text" name="print_appid" value="<?php echo $conf['print_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">APPSECRET</label>
	  <div class="col-sm-9"><input type="text" name="print_appsecret" value="<?php echo $conf['print_appsecret']; ?>" class="form-control"/></div>
	</div><br/>
  	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div><br/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
对接<a href="https://m.qyypay.cn/" target="_blank" rel="noreferrer">启易付</a>打印机，支持订单支付成功后自动打印小票功能，需同时在用户中心填写打印机序列号并开启打印功能。
</div>
</div>
<?php
}elseif($mod=='cron'){
?>
<style>.list-group-item{word-break: break-all;}</style>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">计划任务设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">计划任务访问密钥</label>
	  <div class="col-sm-10"><input type="text" name="cronkey" value="<?php echo $conf['cronkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">计划任务列表</h3></div>
<div class="panel-body">
<p>订单统计任务（0点后访问一次即可）</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=order&key=<?php echo $conf['cronkey']; ?></li>
<br/>
<p>自动生成结算任务（0点后访问一次即可）</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=settle&key=<?php echo $conf['cronkey']; ?></li>
<br/>
<p>订单异步通知重试任务<br/>（如果有订单出现通知失败的，可以通过此条任务自动重新通知，通知重试时间：1分钟，3分钟，20分钟，1小时，2小时）</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=notify&key=<?php echo $conf['cronkey']; ?></li>
<?php if($DB->getColumn("SELECT count(*) from pre_psreceiver WHERE status=1")>0 || $conf['direct_settle_time']==1){ ?>
<br/>
<p>订单分账&延迟结算任务</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=profitsharing&key=<?php echo $conf['cronkey']; ?></li><?php }?>
<?php if($conf['auto_check_sucrate']==1 || $conf['auto_check_channel']==1 || $conf['auto_check_complain']==1 || $conf['auto_check_payip']==1 || $conf['auto_check_payspeed']==1){ ?>
<br/>
<p>支付风控检测任务</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=check&key=<?php echo $conf['cronkey']; ?></li><?php }?>
<?php if($conf['auto_settle_money']){ ?>
<br/>
<p>自动转账任务</p>
<li class="list-group-item"><?php echo $siteurl?>cron.php?do=transfer&key=<?php echo $conf['cronkey']; ?></li><?php }?>
</div>
</div>
<?php
}
elseif($mod=='proxy'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">中转代理设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">中转代理开关</label>
	  <div class="col-sm-10"><select class="form-control" name="proxy" default="<?php echo $conf['proxy']?>">
	  <option value="0">关闭</option>
	  <option value="1">开启</option>
	  </select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">代理IP</label>
	  <div class="col-sm-10"><input type="text" name="proxy_server" value="<?php echo $conf['proxy_server']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">代理端口</label>
	  <div class="col-sm-10"><input type="text" name="proxy_port" value="<?php echo $conf['proxy_port']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">代理账号</label>
	  <div class="col-sm-10"><input type="text" name="proxy_user" value="<?php echo $conf['proxy_user']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">代理密码</label>
	  <div class="col-sm-10"><input type="text" name="proxy_pwd" value="<?php echo $conf['proxy_pwd']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">代理协议</label>
	  <div class="col-sm-10"><select class="form-control" name="proxy_type" default="<?php echo $conf['proxy_type']; ?>">
	  <option value="http">HTTP</option>
	  <option value="https">HTTPS</option>
	  <option value="sock4">SOCK4</option>
	  <option value="sock5">SOCK5</option>
	  </select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/>
	  <a href="javascript:testproxy()" class="btn btn-default btn-block">测试连通性</a>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
本功能开启后，在支付成功异步回调的时候，使用中转代理访问商户网站，可解决一些只能国内访问的网站回调问题，也可以防止本站服务器IP泄露。<br/>
自定义代理可以使用Windows服务器+CCProxy软件搭建<br/>
</div>
</div>
<?php
}elseif($mod=='upimg'){
echo '<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">更改首页LOGO</h3></div>
<div class="panel-body">';
if($_POST['s']==1){
if(!checkRefererHost())exit;
if(copy($_FILES['file']['tmp_name'], ROOT.'assets/img/logo.png')){
	echo "成功上传文件!<br>（可能需要清空浏览器缓存才能看到效果，按Ctrl+F5即可一键刷新缓存）";
}else{
	echo "上传失败，可能没有文件写入权限";
}
}
echo '<form action="set.php?mod=upimg" method="POST" enctype="multipart/form-data"><label for="file"></label><input type="file" name="file" id="file" /><input type="hidden" name="s" value="1" /><br><input type="submit" class="btn btn-primary btn-block" value="确认上传" /></form><br>现在的图片：<br><img src="../assets/img/logo.png?r='.rand(10000,99999).'" style="max-width:100%">';
echo '</div></div>';
}
?>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
function checkURL(obj)
{
	var url = $(obj).val();

	if (url.indexOf(" ")>=0){
		url = url.replace(/ /g,"");
	}
	if (url.toLowerCase().indexOf("http://")<0 && url.toLowerCase().indexOf("https://")<0){
		url = "http://"+url;
	}
	if (url.slice(url.length-1)!="/"){
		url = url+"/";
	}
	$(obj).val(url);
}
function saveSetting(obj){
	if($("input[name='localurl_alipay']").length>0 && $("input[name='localurl_alipay']").val()!=''){
		checkURL("input[name='localurl_alipay']");
	}
	if($("input[name='localurl_wxpay']").length>0 && $("input[name='localurl_wxpay']").val()!=''){
		checkURL("input[name='localurl_wxpay']");
	}
	if($("input[name='localurl']").length>0 && $("input[name='localurl']").val()!=''){
		checkURL("input[name='localurl']");
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {
					icon: 1,
					closeBtn: false
				}, function(){
				  window.location.reload()
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
	return false;
}
function changeTemplate(template){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : {template:template},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('更换模板成功！', {
					icon: 1,
					closeBtn: false
				}, function(){
				  window.location.reload()
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function testproxy(){
	var proxy_server = $("input[name='proxy_server']").val();
	var proxy_port = $("input[name='proxy_port']").val();
	var proxy_user = $("input[name='proxy_user']").val();
	var proxy_pwd = $("input[name='proxy_pwd']").val();
	var proxy_type = $("select[name='proxy_type']").val();
	if(proxy_server=='' || proxy_port==''){
		layer.alert('代理服务器和端口不能为空！');
		return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=testproxy',
		data : {proxy_server:proxy_server, proxy_port:proxy_port, proxy_user:proxy_user, proxy_pwd:proxy_pwd, proxy_type:proxy_type},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('连通性测试成功！', {icon: 1})
			}else{
				layer.alert('连通性测试失败：'+data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
</script>