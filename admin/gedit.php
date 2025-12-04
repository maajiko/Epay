<?php
include("../includes/common.php");
$title='新增/编辑用户组';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$paytype = [];
$rs = $DB->getAll("SELECT * FROM pre_type WHERE status=1 ORDER BY id ASC");
foreach($rs as $row){
	$paytype[$row['id']] = $row['showname'];
}
unset($rs);

$alipay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%alipay%')");
$wxpay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%wxpay%')");
$qqpay_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%qqpay%')");
$bank_channel = $DB->getAll("SELECT id,name,plugin FROM pre_channel WHERE plugin IN (SELECT name FROM pre_plugin WHERE transtypes LIKE '%bank%')");

$grouprow = [];
$act = isset($_GET['act'])?daddslashes($_GET['act']):null;
if($act == 'edit'){
	$id = isset($_GET['gid'])?intval($_GET['gid']):0;
	$grouprow = $DB->getRow("SELECT * FROM pre_group WHERE gid='$id' LIMIT 1");
	if(!$grouprow) showmsg('当前用户组不存在！',3);
	$title = '编辑用户组 GID:'.$id;
	$grouprow['info'] = json_decode($grouprow['info'], true);
	$grouprow['config'] = $grouprow['config'] ? json_decode($grouprow['config'], true) : [];
}else{
	$title = '新增用户组';
}
?>
<style>
.table>tbody>tr>td{vertical-align: middle;}
</style>
<div class="container" style="padding-top:70px;">
<div class="col-sm-12 col-md-10 center-block" style="float: none;">
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title"><?php echo $title?></h3></div>
<div class="panel-body">
	<form class="form-horizontal" id="form-store" onsubmit="return save(this)" method="POST">
		<input type="hidden" name="action" id="action" value="<?php echo $act?>"/>
		<input type="hidden" name="gid" id="gid"/>
		<div class="row">
		<div class="col-sm-12 col-md-7">
		<div class="form-group">
			<label class="col-sm-2 control-label no-padding-right">显示名称</label>
			<div class="col-sm-10">
				<input type="text" class="form-control" name="name" placeholder="不要与其他用户组名称重复" required>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label">通道费率</label>
			<div class="col-sm-10">
				<table class="table">
					<thead><tr><th style="min-width:100px">支付方式</th><th>选择支付通道</th><th>填写分成比例</th></tr></thead>
					<tbody>
<?php
foreach($paytype as $key=>$value)
{
$select = '';
$rs = $DB->getAll("SELECT * FROM pre_channel WHERE type='$key' AND status=1");
foreach($rs as $row){
	$select .= '<option value="'.$row['id'].'" rate="'.$row['rate'].'" type="channel">'.$row['name'].'</option>';
}
$rs = $DB->getAll("SELECT * FROM pre_roll WHERE type='$key' AND status=1");
foreach($rs as $row){
	$select .= '<option value="'.$row['id'].'" rate="'.$row['rate'].'" type="roll">'.$row['name'].'</option>';
}
echo '<tr><td><b>'.$value.'</b><input type="hidden" name="info['.$key.'][type]" value=""></td><td><select name="info['.$key.'][channel]" class="form-control" onchange="changeChannel('.$key.')"><option value="0">关闭</option><option value="-1" type="channel">随机可用通道</option><option value="-4" type="channel">顺序可用通道</option><option value="-5" type="channel">首个可用通道</option>'.$select.'<option value="-2" type="channel">用户自定义子通道</option><option value="-3" type="channel">随机可用轮询组</option></select></td><td><div class="input-group"><input type="text" class="form-control" name="info['.$key.'][rate]" placeholder="百分数"><span class="input-group-addon">%</span></div></td></tr>';
}
?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label">代付通道</label>
			<div class="col-sm-10">
				<table class="table">
					<thead><tr><th style="min-width:100px">转账方式</th><th>选择支付通道</th></tr></thead>
					<tbody>
					<tr><td><b>支付宝</b></td><td><select name="config[transfer_alipay]" class="form-control"><option value="">缺省（与系统设置一致）</option><?php foreach($alipay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select></td></tr>
					<tr><td><b>微信支付</b></td><td><select name="config[transfer_wxpay]" class="form-control"><option value="">缺省（与系统设置一致）</option><?php foreach($wxpay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select></td></tr>
					<tr><td><b>QQ钱包</b></td><td><select name="config[transfer_qqpay]" class="form-control"><option value="">缺省（与系统设置一致）</option><?php foreach($qqpay_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select></td></tr>
					<tr><td><b>银行卡</b></td><td><select name="config[transfer_bank]" class="form-control"><option value="">缺省（与系统设置一致）</option><?php foreach($bank_channel as $channel){echo '<option value="'.$channel['id'].'" plugin="'.$channel['plugin'].'">'.$channel['name'].'</option>';} ?><option value="-1">手动转账</option></select></td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label no-padding-right">用户变量</label>
			<div class="col-sm-10">
				<input type="text" class="form-control" name="settings" placeholder="没有请勿填写，格式：变量名1:显示名1,变量名2:显示名2"><font color="green">用于替换支付通道密钥配置里面的变量，如选“用户自定义子通道”，则此处不需要填写</font>
			</div>
		</div>
		</div>
		<div class="col-sm-12 col-md-5">
		<div class="form-group">
			<label class="col-sm-4 control-label">结算开关</label>
			<div class="col-sm-8">
				<select name="config[settle_open]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="1">只开启每日自动结算</option><option value="2">只开启手动申请结算</option><option value="3">开启自动+手动结算</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">结算周期</label>
			<div class="col-sm-8">
				<select name="config[settle_type]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="1">D+0（可结算全部余额）</option><option value="2">D+1（可结算前1天的余额）</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">自动转账</label>
			<div class="col-sm-8">
				<select name="config[settle_transfer]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="1">开启手动提现自动转账</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label no-padding-right">结算手续费</label>
			<div class="col-sm-8">
			<div class="input-group"><input type="text" name="config[settle_rate]" class="form-control" placeholder="留空则与系统设置一致"/><span class="input-group-addon">%</span></div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">余额充值</label>
			<div class="col-sm-8">
				<select name="config[recharge]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="0">关闭</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">聚合收款码</label>
			<div class="col-sm-8">
				<select name="config[onecode]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="0">关闭</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">代付功能</label>
			<div class="col-sm-8">
				<select name="config[user_transfer]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label no-padding-right">代付手续费</label>
			<div class="col-sm-8">
			<div class="input-group"><input type="text" name="config[transfer_rate]" class="form-control" placeholder="留空则与系统设置一致"/><span class="input-group-addon">%</span></div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">邀请返现</label>
			<div class="col-sm-8">
				<select name="config[invite_open]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label no-padding-right">邀请分成比例</label>
			<div class="col-sm-8">
			<div class="input-group"><input type="text" name="config[invite_rate]" class="form-control" placeholder="留空则与系统设置一致"/><span class="input-group-addon">%</span></div>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">商户保证金</label>
			<div class="col-sm-8">
				<select name="config[user_deposit]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="0">关闭</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">验证授权域名</label>
			<div class="col-sm-8">
				<select name="config[pay_domain_forbid]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="0">关闭</option><option value="1">开启</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">结算时间</label>
			<div class="col-sm-8">
				<select name="config[direct_settle_time]" class="form-control">
					<option value="">缺省（与系统设置一致）</option><option value="0">支付后立即结算</option><option value="1">支付后延迟24小时结算</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">满多少随机增减金额</label>
			<div class="col-sm-8"><input type="text" name="config[pay_payaddstart]" value="" class="form-control" placeholder="订单满多少随机增减金额，留空不随机增减"/></div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">随机增减金额范围</label>
			<div class="col-sm-4"><input type="text" name="config[pay_payaddmin]" value="" class="form-control" placeholder="最小增加金额，负数为减少"/></div>
			<div class="col-sm-4"><input type="text" name="config[pay_payaddmax]" value="" class="form-control" placeholder="最大增加金额，负数为减少"/></div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">最大支付金额</label>
			<div class="col-sm-8"><input type="text" name="config[pay_maxmoney]" value="" class="form-control" placeholder="留空则与系统设置一致"/></div>
		</div>
		<div class="form-group">
			<label class="col-sm-4 control-label">日支付限额</label>
			<div class="col-sm-8"><input type="text" name="config[pay_daymax]" value="" class="form-control" placeholder="留空则不限制"/></div>
		</div>
		<?php if(class_exists("\\lib\\Applyments\\CommUtil")){?><div class="form-group">
			<label class="col-sm-4 control-label">免费进件</label>
			<div class="col-sm-8">
				<select name="config[applyments_free]" class="form-control">
					<option value="0">否</option><option value="1">是</option>
				</select>
			</div>
		</div><?php }?>
		</div>
		</div>
		<input type="submit" class="btn btn-primary btn-block" value="保存"></form>
	</form>
	
<br/><a href="./glist.php">>>返回用户组设置</a>
</div>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var grouprow = <?php echo json_encode($grouprow)?>;
var action = $("#action").val();
if(action == 'edit'){
	var $form = $('#form-store');
	$.each(grouprow, function(key, value) {
		$form.find('[name="' + key + '"]').val(value);
	});
	$.each(grouprow.config, function(key, value) {
		var $input = $form.find('[name="config[' + key + ']"]');
		if ($input.is('select')) {
			var $option = $input.find('option[value="' + value + '"]');
			if ($option.length > 0) {
				$input.val(value);
			}
		}else{
			$input.val(value);
		}
	});
	$.each(grouprow.info, function (i, res) {
		$('select[name="info['+i+'][channel]"] option[type="'+res.type+'"][value="'+res.channel+'"]').prop('selected', true);
		$("input[name='info["+i+"][rate]']").val(res.rate);
		$("input[name='info["+i+"][type]']").val(res.type);
	})
}
function changeChannel(type){
	var rate = $("select[name='info["+type+"][channel]'] option:selected").attr('rate');
	var type2 = $("select[name='info["+type+"][channel]'] option:selected").attr('type');
	if($("input[name='info["+type+"][rate]']").val()=='')$("input[name='info["+type+"][rate]']").val(rate);
	$("input[name='info["+type+"][type]']").val(type2);
}
function save(form){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_user.php?act=saveGroup',
		data : $(form).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					window.location.href='./glist.php';
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
	return false;
}
</script>