<?php
include("../includes/common.php");
$title='转账付款';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<?php
$app = isset($_GET['app'])?$_GET['app']:'alipay';

if(isset($_POST['submit'])){
	if(!checkRefererHost())exit();
	$out_biz_no = trim($_POST['out_biz_no']);
	if(!isset($_POST['paypwd']) || $_POST['paypwd']!==$conf['admin_paypwd'])showmsg('支付密码错误',3);
	$payee_account = htmlspecialchars(trim($_POST['payee_account']));
	$payee_real_name = htmlspecialchars(trim($_POST['payee_real_name']));
	$money = trim($_POST['money']);
	$desc = htmlspecialchars(trim($_POST['desc']));
	if(empty($out_biz_no) || empty($payee_account) || empty($money))showmsg('必填项不能为空',3);
	if(strlen($out_biz_no)!=19 || !is_numeric($out_biz_no))showmsg('交易号输入不规范',3);
	if($desc && mb_strlen($desc)>32)showmsg('转账备注最多32个字',3);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money) || $money<=0)showmsg('转账金额输入不规范',3);

	$channelid = isset($_POST['channel'])?$_POST['channel']:null;

	$result = \lib\Transfer::add(0, $app, $out_biz_no, $payee_account, $payee_real_name, $money, $desc, null, $channelid);

	if($result['code']==0){
		if($result['status'] == 1){
			$result='转账成功！转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}elseif($result['status'] == 3){
			$result='提交成功！请等待管理员审核转账。';
		}elseif(isset($result['wxpackage'])){
			$result='提交成功！请在付款记录页面扫描二维码确认收款，1天内未确认，将退还给商家。转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}else{
			$result='提交成功！转账处理中，请稍后在付款记录页面查看结果。转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}
		showmsg($result,1,'./transfer.php');
	}else{
		$result='转账失败：'.$result['msg'];
		showmsg($result,4);
	}
}

$out_biz_no = date("YmdHis").rand(11111,99999);

$channel_select = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%".$app."%')");

if($app=='alipay'){
	$default_channel = $conf['transfer_alipay'];
}elseif($app=='wxpay'){
	$default_channel = $conf['transfer_wxpay'];
}elseif($app=='qqpay'){
	$default_channel = $conf['transfer_qqpay'];
}elseif($app=='bank'){
	$default_channel = $conf['transfer_bank'];
}
$copy = [];
if(isset($_GET['account']) && isset($_GET['username'])){
	$copy = [
		'account' => htmlspecialchars(trim($_GET['account'])),
		'username' => htmlspecialchars(trim($_GET['username'])),
	];
}elseif(isset($_GET['copy'])){
	$copy = $DB->find('transfer', '*', ['biz_no'=>trim($_GET['copy'])]);
	$default_channel = $copy['channel'];
}
?>

	  <div class="panel panel-primary">
        <div class="panel-heading"><h3 class="panel-title">转账付款</h3></div>
        <div class="panel-body">
		<ul class="nav nav-tabs">
			<li class="<?php echo $app=='alipay'?'active':null;?>"><a href="?app=alipay">支付宝</a></li><li class="<?php echo $app=='wxpay'?'active':null;?>"><a href="?app=wxpay">微信</a></li><li class="<?php echo $app=='qqpay'?'active':null;?>"><a href="?app=qqpay">QQ钱包</a></li><li class="<?php echo $app=='bank'?'active':null;?>"><a href="?app=bank">银行卡</a></li>
		</ul>
		<div class="tab-pane active" id="alipay">
          <form action="?app=<?php echo $app?>" method="POST" role="form">
			<input type="hidden" name="type" value="<?php echo $app?>"/>
		    <div class="form-group">
				<div class="input-group"><div class="input-group-addon">通道选择</div>
				<select name="channel" class="form-control" default="<?php echo $default_channel?>">
					<?php foreach($channel_select as $channel){echo '<option value="'.$channel['id'].'">'.$channel['name'].''.($channel['id']==$default_channel?'（默认）':'').'</option>';} ?>
				</select>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">交易号</div>
				<input type="text" name="out_biz_no" value="<?php echo $out_biz_no?>" class="form-control" required/>
			</div></div>
<?php if($app=='alipay'){?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">支付宝账号</div>
				<input type="text" name="payee_account" value="<?php echo $copy['account']?>" class="form-control" required placeholder="支付宝登录账号或支付宝UID或支付宝Openid"/>
				<div class="input-group-btn"><button type="button" class="btn btn-default recent-payer-btn" data-type="alipay"><i class="fa fa-address-book"/></i></button></div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">支付宝姓名</div>
				<input type="text" name="payee_real_name" value="<?php echo $copy['username']?>" class="form-control" placeholder="不填写则不校验真实姓名"/>
			</div></div>
<?php }elseif($app=='wxpay'){?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">Openid</div>
				<input type="text" name="payee_account" value="<?php echo $copy['account']?>" class="form-control" required placeholder="只能填写微信Openid"/>
				<div class="input-group-btn">
					<button type="button" class="btn btn-default recent-payer-btn" data-type="wxpay"><i class="fa fa-address-book"/></i></button>
					<a href="./gettoken.php?app=wechat" class="btn btn-default">获取</a>
				</div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">真实姓名</div>
				<input type="text" name="payee_real_name" value="<?php echo $copy['username']?>" class="form-control" placeholder="不填写则不校验真实姓名"/>
			</div></div>
<?php }elseif($app=='qqpay'){?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">收款方QQ</div>
				<input type="text" name="payee_account" value="<?php echo $copy['account']?>" class="form-control" required/>
				<div class="input-group-btn"><button type="button" class="btn btn-default recent-payer-btn" data-type="qqpay"><i class="fa fa-address-book"/></i></button></div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">真实姓名</div>
				<input type="text" name="payee_real_name" value="<?php echo $copy['username']?>" class="form-control" placeholder="不填写则不校验真实姓名"/>
			</div></div>
<?php }elseif($app=='bank'){?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">银行卡号</div>
				<input type="text" name="payee_account" value="<?php echo $copy['account']?>" class="form-control" required placeholder="收款方银行卡号"/>
				<div class="input-group-btn"><button type="button" class="btn btn-default recent-payer-btn" data-type="bank"><i class="fa fa-address-book"/></i></button></div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">姓名</div>
				<input type="text" name="payee_real_name" value="<?php echo $copy['username']?>" class="form-control" placeholder="收款方银行账户名称"/>
			</div></div>
<?php }?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">转账金额</div>
				<input type="text" name="money" value="" class="form-control" placeholder="RMB/元" required/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">转账备注</div>
				<input type="text" name="desc" value="<?php echo $copy['desc']?>" class="form-control" placeholder="可留空，默认为：<?php echo $app=='alipay'?$conf['transfer_name']:$conf['transfer_desc']?>"/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">支付密码</div>
				<input type="text" name="paypwd" value="" class="form-control" required/>
			</div></div>
            <p><input type="submit" name="submit" value="立即转账" class="btn btn-primary form-control"/></p>
			<p><a href="javascript:balanceQuery()" class="btn btn-block btn-default">查询账户余额</a></p>
          </form>
        </div>
		</div>
		<div class="panel-footer">
          <span class="glyphicon glyphicon-info-sign"></span> 交易号可以防止重复转账，同一个交易号只能提交同一次转账。<br/>
		  <a href="./set.php?mod=account">修改支付密码</a><br/>
		  【<a href="./transfer_batch.php?type=<?php echo $app?>">批量转账</a>】
        </div>
      </div>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}

function saveRecentPayer(type, account, name) {
	var key = 'recent_payers_' + type;
	var payers = JSON.parse(localStorage.getItem(key) || '[]');
	
	payers = payers.filter(function(payer) {
		return payer.account !== account;
	});
	
	payers.unshift({
		account: account,
		name: name,
		timestamp: new Date().getTime()
	});

	if (payers.length > 5) {
		payers = payers.slice(0, 5);
	}
	
	localStorage.setItem(key, JSON.stringify(payers));
}

function getRecentPayers(type) {
	var key = 'recent_payers_' + type;
	return JSON.parse(localStorage.getItem(key) || '[]');
}

function showRecentPayers(type) {
	var payers = getRecentPayers(type);
	
	if (payers.length === 0) {
		layer.msg('暂无最近付款记录');
		return;
	}
	
	var html = '<div class="recent-payers-popup">';
	html += '<h4 style="margin:15px;">最近付款人</h4>';
	html += '<div class="list-group" style="max-height:300px;overflow-y:auto;">';
	
	payers.forEach(function(payer, index) {
		html += '<a href="javascript:void(0)" class="list-group-item payer-item" data-account="' + payer.account + '" data-name="' + (payer.name || '') + '">';
		html += '<div><strong>' + payer.account + '</strong></div>';
		if (payer.name) {
			html += '<div style="font-size:12px;color:#666;">' + payer.name + '</div>';
		}
		html += '</a>';
	});
	
	html += '</div></div>';
	
	layer.open({
		type: 1,
		title: false,
		closeBtn: 1,
		area: ['400px', 'auto'],
		shadeClose: true,
		content: html,
		success: function(layero) {
			$(layero).find('.payer-item').on('click', function() {
				var account = $(this).data('account');
				var name = $(this).data('name');
				
				$('input[name="payee_account"]').val(account);
				if (name) {
					$('input[name="payee_real_name"]').val(name);
				}
				
				layer.closeAll();
			});
		}
	});
}

$(document).ready(function() {
	$('.recent-payer-btn').on('click', function() {
		var type = $(this).data('type');
		showRecentPayers(type);
	});

	$('form').on('submit', function() {
		var type = $('input[name="type"]').val();
		var account = $('input[name="payee_account"]').val();
		var name = $('input[name="payee_real_name"]').val();
		
		if (account) {
			saveRecentPayer(type, account, name);
		}
	});
});

function balanceQuery(){
	var type = $("input[name=type]").val();
	var channel = $("select[name=channel]").val();
	if(channel == ''){
		layer.alert('请先选择通道');return;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=balance_query',
		dataType : 'json',
		data : {type: type, channel: channel},
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				if(data.msg){
					layer.alert(data.msg);
				}else{
					layer.alert('账户可用余额：'+data.amount+'元');
				}
			}else{
				if(data.msg.indexOf('插件方法不存在')>-1) data.msg = '该通道不支持查询账户余额';
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
</script>
