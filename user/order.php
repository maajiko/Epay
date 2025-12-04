<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='订单记录';
include './head.php';
?>
<?php

$type_select = '<option value="0">支付方式</option>';
$rs = $DB->getAll("SELECT * FROM pre_type WHERE status=1 ORDER BY id ASC");
foreach($rs as $row){
	$type_select .= '<option value="'.$row['id'].'">'.$row['showname'].'</option>';
}
unset($rs);

?>
<style>
#orderItem .orderTitle{word-break:keep-all;}
#orderItem .orderContent{word-break:break-all;}
.dates{max-width: 120px;}
.fixed-table-toolbar,.fixed-table-pagination{padding: 15px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">订单记录</h1>
</div>
<div class="wrapper-md control">
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
	<div class="panel panel-default">
		<div class="panel-heading font-bold">
			<h3 class="panel-title">订单记录</h3>
		</div>

	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
		<input type="hidden" name="channel" value="">
		<input type="hidden" name="subchannel" value="">
		<input type="hidden" name="applyid" value="">
	      <div class="form-group">
			<select class="form-control" name="type">
			  <option value="1">系统订单号</option>
			  <option value="2">商户订单号</option>
			  <option value="9">接口订单号</option>
			  <option value="10">用户交易单号</option>
			  <option value="11">渠道交易单号</option>
			  <option value="3">商品名称</option>
			  <option value="4">商品金额</option>
			  <option value="5">实付金额</option>
			  <option value="6">网站域名</option>
			  <option value="7">支付IP</option>
			  <option value="8">支付账号</option>
			</select>
		  </div>
			<div class="form-group" id="searchword">
			  <input type="text" class="form-control" name="kw" placeholder="搜索内容" style="min-width: 200px;">
			</div>
			<div class="input-group input-daterange">
				<input type="text" id="starttime" name="starttime" class="form-control dates" placeholder="开始日期" autocomplete="off" title="留空则不限时间范围">
				<span class="input-group-addon" onclick="$('#starttime').val('');$('#endtime').val('');" title="清除"><i class="fa fa-chevron-right"></i></span>
				<input type="text" id="endtime" name="endtime" class="form-control dates" placeholder="结束日期" autocomplete="off" title="留空则不限时间范围">
			</div>
			<div class="form-group">
			  <select name="paytype" class="form-control"><?php echo $type_select?></select>
		    </div>
			<div class="form-group">
				<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">状态未支付</option><option value="1">状态已支付</option><option value="2">状态已退款</option><option value="3">状态已冻结</option></select>
			</div>
			<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
			<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-refresh"></i> 重置</a>
			<button type="button" onclick="statistics()" class="btn btn-default">&nbsp;统计&nbsp;</button>
			<button type="button" onclick="exportOrder()" class="btn btn-default">&nbsp;导出&nbsp;</button>
		</form>
      <table id="listTable">
	  </table>
	</div>
</div>
    </div>
  </div>
  <a style="display: none;" href="" id="vurl" rel="noreferrer" target="_blank"></a>

<div class="modal" id="modal-statistics" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">订单统计概况</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" data-dismiss="modal">关闭</button>
			</div>
		</div>
	</div>
</div>
<template id="statistics">
<ul class="list-inline" style="margin-bottom: 0;padding-bottom: 10px;border-bottom: 1px solid #dddddd;">
    <li>订单总金额：<span style="font-weight: 600;">¥ {totalMoney}</span></li>
    <li>已支付金额：<span style="font-weight: 600;">¥ {successMoney}</span></li>
    <li>未支付金额：<span style="font-weight: 600;">¥ {unpaidMoney}</span></li>
    <li>已退款金额：<span style="font-weight: 600;">¥ {refundMoney}</span></li>
    <li>总收入利润: <span style="font-weight: 600;">¥ {platformProfit}</li>
</ul>
<ul class="list-inline" style="padding-top:10px;margin-bottom: 0;">
    <li>订单总数：<span style="font-weight: 600;">{totalCount}</span></li>
    <li>已支付订单：<span style="font-weight: 600;">{successCount}</span></li>
    <li>未支付订单：<span style="font-weight: 600;">{unpaidCount}</span></li>
    <li>已退款订单：<span style="font-weight: 600;">{refundCount}</span></li>
	<li>订单成功率：<span style="font-weight: 600;">{successRate}%</span></li>
</ul>
</template>

<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
var is_user_refund = '<?php echo $conf['user_refund']?>';
var is_print = '<?php echo $conf['orderprint']==1&&$userrow['print_order']>0?'1':'0';?>';
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 20;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax2.php?act=orderList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'trade_no',
				title: '系统订单号/商户订单号',
				formatter: function(value, row, index) {
					return '<a href="javascript:showOrder(\''+value+'\')" title="点击查看详情">'+value+'</a></b><br/>'+row.out_trade_no;
				}
			},
			{
				field: 'name',
				title: '商品名称'
			},
			{
				field: 'money',
				title: '商品金额',
				formatter: function(value, row, index) {
					return '¥<b>'+value+'</b>';
				}
			},
			{
				field: 'realmoney',
				title: '实际支付',
				formatter: function(value, row, index) {
					return '¥<b>'+value+'</b>';
				}
			},
			{
				field: 'typename',
				title: '支付方式',
				formatter: function(value, row, index) {
					var html = value ? '<b><img src="/assets/icon/'+value+'.ico" width="16" onerror="this.style.display=\'none\'">'+row.typeshowname+'</b>' : '';
					if(row.subchannel > 0 && row.submchid > 0){
						html += '('+row.submchid+')';
					}
					return html;
				}
			},
			{
				field: 'addtime',
				title: '创建时间/完成时间',
				formatter: function(value, row, index) {
					return value+'<br/>'+(row.endtime??'&nbsp;');
				}
			},
			{
				field: 'status',
				title: '支付状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						text = '<font color=green>已支付</font>';
					}else if(value == '2'){
						text = '<font color=red>已退款</font>';
						if(row.refundmoney > 0 && row.refundmoney < row.realmoney){
							text += '<br/><font color=red>('+row.refundmoney+'元)</font>';
						}
					}else if(value == '3'){
						text = '<font color=red>已冻结</font>';
					}else if(value == '4'){
						text = '<font color=orange>预授权</font>';
					}else{
						text = '<font color=blue>未支付</font>';
					}
					if(row.plugin=='alipayd'){
						if(row.settle == '1'){
							text += '<br/><font color=#8c8f93>待结算</font>';
						}else if(row.settle == '2'){
							text += '<br/><font color=#37db3c>结算成功</font>';
						}else if(row.settle == '3'){
							text += '<br/><font color=#ed6565>结算失败</font>';
						}
					}else if(row.plugin=='alipayrp'){
						if(row.settle == '1'){
							text += '<br/><font color=#8c8f93>待转账</font>';
						}else if(row.settle == '2'){
							text += '<br/><font color=#37db3c>转账成功</font>';
						}else if(row.settle == '3'){
							text += '<br/><font color=#ed6565>转账失败</font>';
						}
					}
					return text;
				}
			},
			{
				field: '',
				title: '操作',
				formatter: function(value, row, index) {
					var html = '<a href="./record.php?type=3&kw='+row.trade_no+'" class="btn btn-info btn-xs">明细</a>&nbsp;<a href="javascript:callnotify(\''+row.trade_no+'\')" class="btn btn-success btn-xs">补单</a>';
					if(is_user_refund=='1' && (row.status=='1' || row.status=='3' || row.status=='2' && row.refundmoney > 0 && row.refundmoney < row.realmoney)){
						html += '&nbsp;<a href="javascript:refund(\''+row.trade_no+'\')" class="btn btn-danger btn-xs">退款</a>';
					}
					if(is_print=='1'&&row.status=='1'){
						html += '&nbsp;<a href="javascript:void(0);" onclick="printOrder(\''+row.trade_no+'\')" class="btn btn-warning btn-xs">打印</a>';
					}
					return html;
				}
			},
		],
	});

	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
})

function callnotify(trade_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=notify',
		data : {trade_no:trade_no},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#vurl").attr("href",data.url);
				document.getElementById("vurl").click();
				listTable();
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
	return false;
}
function callreturn(trade_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=notify',
		data : {trade_no:trade_no,isreturn:1},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#vurl").attr("href",data.url);
				document.getElementById("vurl").click();
				listTable();
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
	return false;
}
function refund(trade_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=refund_query',
		data : {trade_no:trade_no},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.open({
					area: ['360px'],
					title: '退款确认',
					content: '<p>此操作将直接原路退款该订单，每个订单只能操作一次退款，退款金额不能大于订单金额。</p><div class="form-group"><div class="input-group"><div class="input-group-addon">退款金额</div><input type="text" class="form-control" name="refund2" value="'+data.money+'" placeholder="请输入退款金额" autocomplete="off"/></div></div><div class="form-group"><div class="input-group"><div class="input-group-addon">登录密码</div><input type="text" class="form-control" name="paypwd" value="" placeholder="请输入用户登录密码" autocomplete="off"/></div></div>',
					yes: function(){
						var money = $("input[name='refund2']").val();
						var paypwd = $("input[name='paypwd']").val();
						if(money == '' || paypwd == ''){
							layer.alert('金额或密码不能为空');return;
						}
						var ii = layer.load(2, {shade:[0.1,'#fff']});
						$.ajax({
							type : 'POST',
							url : 'ajax2.php?act=refund_submit',
							data : {trade_no:trade_no, money:money, pwd:paypwd},
							dataType : 'json',
							success : function(data) {
								layer.close(ii);
								if(data.code == 0){
									layer.alert(data.msg, {icon:1}, function(){ layer.closeAll();searchSubmit(); });
								}else{
									layer.alert(data.msg, {icon:7});
								}
							},
							error:function(data){
								layer.close(ii);
								layer.msg('服务器错误');
							}
						});
					}
				});
			}else{
				layer.alert(data.msg, {icon:7});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function showOrder(trade_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	var status = ['<span class="label label-primary">未支付</span>','<span class="label label-success">已支付</span>','<span class="label label-danger">已退款</span>','<span class="label label-info">已冻结</span>','<span class="label label-warning">预授权</span>'];
	$.ajax({
		type : 'GET',
		url : 'ajax2.php?act=order&trade_no='+trade_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var data = data.data;
				var item = '<table class="table table-condensed table-hover" id="orderItem">';
				item += '<tr><td colspan="6" style="text-align:center" class="orderTitle"><b>订单信息</b></td></tr>';
				item += '<tr class="orderTitle"><td class="info" class="orderTitle">系统订单号</td><td colspan="5" class="orderContent">'+data.trade_no+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">商户订单号</td><td colspan="5" class="orderContent">'+data.out_trade_no+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">接口订单号</td><td colspan="5" class="orderContent">'+data.api_trade_no+'</td></tr>';
				if(data.bill_mch_trade_no){
					item += '<tr><td class="info" class="orderTitle">渠道交易单号</td><td colspan="5" class="orderContent">'+data.bill_mch_trade_no+'</td></tr>';
				}
				if(data.bill_trade_no){
					item += '<tr><td class="info" class="orderTitle">用户交易单号</td><td colspan="5" class="orderContent">'+data.bill_trade_no+'</td></tr>';
				}
				item += '<tr><td class="info" class="orderTitle">支付方式</td><td colspan="5" class="orderContent">'+data.typename+'</td></tr>';
				if(data.subchannel > 0){
					item += '<tr><td class="info" class="orderTitle">自定义子通道</td><td colspan="5" class="orderContent">'+data.subchannelname+'</td></tr>';
				}
				item += '<tr><td class="info" class="orderTitle">商品名称</td><td colspan="5" class="orderContent">'+data.name+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">订单金额</td><td colspan="5" class="orderContent">'+data.money+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">实际支付金额</td><td colspan="5" class="orderContent">'+data.realmoney+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">商户分成金额</td><td colspan="5" class="orderContent">'+data.getmoney+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">创建时间</td><td colspan="5" class="orderContent">'+data.addtime+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">完成时间</td><td colspan="5" class="orderContent">'+data.endtime+'</td></tr>';
				if(data.status==2){
					item += '<tr><td class="info" class="orderTitle">退款时间</td><td colspan="5" class="orderContent">'+data.refundtime+'</td></tr>';
				}
				item += '<tr><td class="info" class="orderTitle" title="只有在官方通道支付完成后才能显示">支付账号</td><td colspan="5" class="orderContent">'+data.buyer+'</td></tr>';
				if(data.mobile){
					item += '<tr><td class="info" class="orderTitle">手机号码</td><td colspan="5" class="orderContent">'+data.mobile+'</td></tr>';
				}
				item += '<tr><td class="info" class="orderTitle">网站域名</td><td colspan="5" class="orderContent"><a href="http://'+data.domain+'" target="_blank" rel="noreferrer">'+data.domain+'</a></td></tr>';
				item += '<tr><td class="info" class="orderTitle">支付IP</td><td colspan="5" class="orderContent"><a href="https://m.ip138.com/iplookup.asp?ip='+data.ip+'" target="_blank" rel="noreferrer">'+data.ip+'</a></td></tr>';
				item += '<tr><td class="info" class="orderTitle">扩展参数</td><td colspan="5" class="orderContent">'+data.param+'</td></tr>';
				item += '<tr><td class="info" class="orderTitle">订单状态</td><td colspan="5" class="orderContent">'+status[data.status]+'</td></tr>';
				if(data.status>0){
					item += '<tr><td class="info" class="orderTitle">通知状态</td><td colspan="5" class="orderContent">'+(data.notify==0?'<span class="label label-success">通知成功</span>':'<span class="label label-danger">通知失败</span>（已通知'+data.notify+'次）')+'</td></tr>';
				}
				item += '<tr><td colspan="6" style="text-align:center" class="orderTitle"><b>订单操作</b></td></tr>';
				item += '<tr><td colspan="6"><a href="javascript:callnotify(\''+data.trade_no+'\')" class="btn btn-xs btn-default">重新通知(异步)</a>&nbsp;<a href="javascript:callreturn(\''+data.trade_no+'\')" class="btn btn-xs btn-default">重新通知(同步)</a>'+(data.combine==1?'&nbsp;<a href="javascript:showSubOrders(\''+data.trade_no+'\')" class="btn btn-xs btn-default">查看子订单列表</a>':'')+'</td></tr>';
				item += '</table>';
				var area = [$(window).width() > 480 ? '480px' : '100%', ';max-height:100%'];
				layer.open({
				  type: 1,
				  area: area,
				  title: '订单详细信息',
				  skin: 'layui-layer-rim',
				  content: item
				});
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function statistics(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : 'POST',
        url : 'ajax2.php?act=statistics',
        data: $('#searchToolbar').serializeArray(),
        dataType : 'json',
        success : function(data) {
            layer.close(ii);
            if(data.code == 0){
                var element = $('#modal-statistics');
                var htmlContent = $("#statistics").html().replace(/\{(\w+)\}/g, function (match, key) {
                    return data.data[key] || '';
                });
                element.find('.modal-body').html(htmlContent);
                element.modal('show');
            }else{
                layer.alert(data.msg);
            }
        },
        error:function(data){
            layer.close(ii);
            layer.msg('服务器错误');
        }
    });
}
function showSubOrders(trade_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax2.php?act=subOrders&trade_no='+trade_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var list = data.data;
				var status = ['<span class="label label-primary">未支付</span>','<span class="label label-success">已支付</span>','<span class="label label-danger">已退款</span>'];
				var settle = ['<span class="label label-info">未结算</span>','<span class="label label-success">已结算</span>'];
				var item = '<table class="table table-condensed table-hover" id="orderItem">';
				item += '<thead><th class="orderTitle">系统订单号</th><th class="orderTitle">接口订单号</th><th class="orderTitle">订单金额</th class="orderTitle"><th class="orderTitle">订单状态</th><th class="orderTitle">结算状态</th></thead><tbody>';
				for(var i=0; i<list.length; i++){
					var statustext = status[list[i].status];
					if(list[i].status == 2 && list[i].refundmoney > 0 && list[i].refundmoney < list[i].money){
						statustext += '<font color=red>('+list[i].refundmoney+')</font>';
					}
					item += '<tr><td>'+list[i].sub_trade_no+'</td><td>'+list[i].api_trade_no+'</td><td>¥<b>'+list[i].money+'</b></td><td>'+statustext+'</td><td>'+(data.settle>0?settle[list[i].settle]:'')+'</td></tr>';
				}
				item += '</tbody></table>';
				var area = [$(window).width() > 680 ? '680px' : '100%'];
				layer.open({
				  type: 1,
				  area: area,
				  title: '合单支付 - 子订单列表',
				  skin: 'layui-layer-rim',
				  shadeClose: true,
				  content: item
				});
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function exportOrder(){
	var params = {};
	$('#searchToolbar').find(':input[name]').each(function() {
		params[$(this).attr('name')] = $(this).val()
	})
	if(params['starttime'] == '' && params['endtime'] == ''){
		layer.alert('请选择导出时间范围');
		return false;
	}
	window.location.href='./download.php?act=order&'+$.param(params);
}
function printOrder(trade_no){
	layer.confirm('是否重新打印此订单小票？',{btn:['确定','取消'],title:'打印小票'}, function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax2.php?act=printOrder',
			data : {trade_no:trade_no},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					layer.alert('打印指令已发送到打印机', {icon:1}, function(){ layer.closeAll(); });
				}else{
					layer.alert(data.msg, {icon:7});
				}
			},
			error:function(data){
				layer.close(ii);
				layer.msg('服务器错误');
			}
		});
	});
}
</script>