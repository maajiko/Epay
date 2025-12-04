<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='新增代付';
include './head.php';
?>
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">新增代付</h1>
</div>
<div class="wrapper-md control">
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
<div class="row">
	<div class="col-sm-12 col-md-10 col-lg-8 center-block" style="float: none;">
<?php

if(!$conf['user_transfer']) showmsg('未开启代付功能');

if(!$conf['transfer_rate'])$conf['transfer_rate'] = $conf['settle_rate'];

$app = isset($_GET['app'])?$_GET['app']:'alipay';

if(isset($_POST['submit'])){
	if(!checkRefererHost())exit();
	$out_biz_no = trim($_POST['out_biz_no']);
	$payee_account = htmlspecialchars(trim($_POST['payee_account']));
	$payee_real_name = htmlspecialchars(trim($_POST['payee_real_name']));
	$money = trim($_POST['money']);
	$desc = htmlspecialchars(trim($_POST['desc']));
	$pwd = trim($_POST['paypwd']);
	$pwdenc = getMd5Pwd($pwd, $userrow['uid']);
	if(empty($pwd) || $pwdenc!==$userrow['pwd'])showmsg('登录密码输入错误',3);
	if(empty($out_biz_no) || empty($payee_account) || empty($money))showmsg('必填项不能为空',3);
	if(strlen($out_biz_no)!=19 || !is_numeric($out_biz_no))showmsg('交易号输入不规范',3);
	if($desc && mb_strlen($desc)>32)showmsg('转账备注最多32个字',3);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money) || $money<=0)showmsg('转账金额输入不规范',3);
	$need_money = round($money + $money*$conf['transfer_rate']/100,2);
	if($userrow['settle']==0)showmsg('您的商户出现异常，无法使用代付功能',3);

	$result = \lib\Transfer::add($uid, $app, $out_biz_no, $payee_account, $payee_real_name, $money, $desc);

	if($result['code']==0){
		if($result['status'] == 1){
			$result='转账成功！转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}elseif($result['status'] == 3){
			$result='提交成功！请等待管理员审核转账。';
		}elseif(isset($result['wxpackage'])){
			$result='提交成功！请在付款记录页面扫描二维码确认收款，1天内未确认，将退还给商家。转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}else{
			$result='提交成功！转账处理中，请稍后在代付管理页面查看结果。转账单据号:'.$result['orderid'].' 支付时间:'.$result['paydate'];
		}
		$_SESSION['transfer_desc'] = $desc;
		showmsg($result,1,'./transfer.php');
	}else{
		$result='转账失败，'.$result['msg'];
		showmsg($result,4);
	}
}

$out_biz_no = date("YmdHis").rand(11111,99999);
$desc = $_SESSION['transfer_desc'];

if($conf['settle_type']==1){
	$today=date("Y-m-d").' 00:00:00';
	$order_today=$DB->getColumn("SELECT SUM(realmoney) from pre_order where uid={$uid} and tid<>2 and status=1 and endtime>='$today'");
	if(!$order_today) $order_today = 0;
	$enable_money=round($userrow['money']-$order_today,2);
	if($enable_money<0)$enable_money=0;
}else{
	$enable_money=$userrow['money'];
}

$copy = [];
if(isset($_GET['copy'])){
	$copy = $DB->find('transfer', '*', ['biz_no'=>trim($_GET['copy'])]);
}
?>
	<div class="panel panel-default">
		<div class="panel-heading font-bold">
			新增代付
		</div>
		<div class="panel-body">
			<ul class="nav nav-tabs">
				<?php if($conf['transfer_alipay']>0 || $conf['transfer_alipay']==-1){?><li class="<?php echo $app=='alipay'?'active':null;?>"><a href="?app=alipay">支付宝</a></li><?php }?>
				<?php if($conf['transfer_wxpay']>0 || $conf['transfer_wxpay']==-1){?><li class="<?php echo $app=='wxpay'?'active':null;?>"><a href="?app=wxpay">微信</a></li><?php }?>
				<?php if($conf['transfer_qqpay']>0 || $conf['transfer_qqpay']==-1){?><li class="<?php echo $app=='qqpay'?'active':null;?>"><a href="?app=qqpay">QQ钱包</a></li><?php }?>
				<?php if($conf['transfer_bank']>0 || $conf['transfer_bank']==-1){?><li class="<?php echo $app=='bank'?'active':null;?>"><a href="?app=bank">银行卡</a></li><?php }?>
			</ul>

			<div class="tab-pane active" id="alipay">
          <form action="?app=<?php echo $app?>" method="POST" role="form">
			<input type="hidden" name="type" value="<?php echo $app?>"/>
			<input type="hidden" name="rate" value="<?php echo $conf['transfer_rate']?>"/>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">交易号</div>
				<input type="text" name="out_biz_no" value="<?php echo $out_biz_no?>" class="form-control" required/>
			</div></div>
<?php if($app=='alipay'){?>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">支付宝账号</div>
				<input type="text" name="payee_account" value="<?php echo $copy['account']?>" class="form-control" required placeholder="支付宝登录账号或支付宝UID"/>
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
					<a id="getopenid" class="btn btn-default">获取</a>
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
				<div class="input-group-btn"><button type="button" class="btn btn-default recent-payer-btn" data-type="bank"><i class="fa fa-address-book"/></i></button></div>
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
				<div class="input-group"><div class="input-group-addon">可转账余额</div>
				<input type="text" value="<?php echo $enable_money?>" class="form-control" disabled/>
				<?php if($conf['recharge']==1){?><div class="input-group-btn"><a href="./recharge.php" class="btn btn-default">充值</a></div><?php }?>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">转账金额</div>
				<input type="text" name="money" value="" class="form-control" placeholder="RMB/元" required/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">需支付金额</div>
				<input type="text" name="need" value="" class="form-control" disabled/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">转账备注</div>
				<input type="text" name="desc" value="<?php echo $desc?>" class="form-control" placeholder="选填，默认为：<?php echo $conf['transfer_desc']?>"/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">验证登录密码</div>
				<input type="text" name="paypwd" value="" class="form-control" required/>
			</div></div>
            <p><input type="submit" name="submit" value="立即转账" class="btn btn-primary form-control"/></p>
          </form>
        </div>
		</div>
		<div class="panel-footer">
		<h4><span class="glyphicon glyphicon-info-sign"></span>注意事项</h4>
		  交易号可以防止重复转账，同一个交易号只能提交同一次转账。<br/>
		  代付手续费是<?php echo $conf['transfer_rate']; ?>%<?php if($conf['transfer_minmoney']>0)echo '，单笔最小代付'.$conf['transfer_minmoney'].'元'; if($conf['transfer_maxmoney']>0)echo '，单笔最大代付'.$conf['transfer_maxmoney'].'元';?>
		  <?php if($conf['settle_type']==1){?><br/>可转账余额为截止到前一天你的收入+充值的余额。<?php }?>
        </div>
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
function showneed(){
	var money = parseFloat($("input[name='money']").val());
	var rate = parseFloat($("input[name='rate']").val());
	if(isNaN(money) || isNaN(rate))return;
	var need = (money + money * (rate/100)).toFixed(2);
	$("input[name='need']").val(need)
}
function checkopenid(){
	$.ajax({
		type: "GET",
		dataType: "json",
		url: "ajax.php?act=getopenid",
		success: function (data, textStatus) {
			if (data.code == 0) {
				layer.msg('Openid获取成功');
				layer.close($.openidform);
				$("input[name='payee_account']").val(data.openid);
			}else if($.ostart==true){
				setTimeout('checkopenid()', 2000);
			}else{
				return false;
			}
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
			return false;
		}
	});
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

$(document).ready(function(){
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
	$("input[name='money']").blur(function(){
		showneed()
	});
	$('#getopenid').click(function () {
		if ($(this).attr("data-lock") === "true") return;
		$(this).attr("data-lock", "true");
		$.ajax({
			type : "GET",
			url : "ajax.php?act=qrcode",
			dataType : 'json',
			success : function(data) {
				$('#getopenid').attr("data-lock", "false");
				if(data.code == 0){
					$.openidform = layer.open({
					  type: 1,
					  title: '请收款方使用微信扫描以下二维码',
					  skin: 'layui-layer-demo',
					  anim: 2,
					  shadeClose: true,
					  content: '<div id="qrcode" class="list-group-item text-center" style="height:250px"></div>',
					  success: function(){
						$('#qrcode').qrcode({
							text: data.url,
							width: 230,
							height: 230,
							foreground: "#000000",
							background: "#ffffff",
							typeNumber: -1
						});
						$.ostart = true;
						setTimeout('checkopenid()', 2000);
					  },
					  end: function(){
						$.ostart = false;
					  }
					});
				}else{
					layer.alert(data.msg, {icon: 0});
				}
			},
			error:function(data){
				layer.msg('服务器错误', {icon: 2});
				return false;
			}
		});
	});
})
</script>