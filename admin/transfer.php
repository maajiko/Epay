<?php
/**
 * 付款记录
**/
include("../includes/common.php");
$title='付款记录';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
tbody tr>td:nth-child(5){max-width:300px;word-break: break-all;}
.dates{max-width: 120px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
  <div class="container-fluid" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
  <div class="form-group">
    <label>搜索</label>
	<select name="column" class="form-control"><option value="biz_no">交易号</option><option value="out_biz_no">商户交易号</option><option value="pay_order_no">接口交易号</option><option value="account">付款账号</option><option value="username">姓名</option><option value="money">付款金额</option><option value="desc">备注</option></select>
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="value" placeholder="搜索内容" value="">
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="uid" style="width: 100px;" placeholder="商户号" value="">
  </div>
  <div class="input-group input-daterange">
	<input type="text" id="starttime" name="starttime" class="form-control dates" placeholder="开始日期" autocomplete="off" title="留空则不限时间范围">
	<span class="input-group-addon" onclick="$('#starttime').val('');$('#endtime').val('');" title="清除"><i class="fa fa-chevron-right"></i></span>
	<input type="text" id="endtime" name="endtime" class="form-control dates" placeholder="结束日期" autocomplete="off" title="留空则不限时间范围">
  </div>
  <div class="form-group">
	<select name="type" class="form-control"><option value="">所有付款方式</option><option value="alipay">支付宝</option><option value="wxpay">微信</option><option value="qqpay">QQ钱包</option><option value="bank">银行卡</option></select>
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">正在处理</option><option value="1">转账成功</option><option value="2">转账失败</option><option value="3">待处理</option></select>
  </div>
  <button type="submit" class="btn btn-primary">搜索</button>
  <div class="btn-group">
	<a href="./transfer_add.php" class="btn btn-success">新增付款</a>
	<button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
	</button>
	<ul class="dropdown-menu">
    	<li><a href="./transfer_red.php">创建红包</a></li>
		<li><a href="./transfer_export.php">导出记录</a></li>
	</ul>
  </div>
  <a href="javascript:searchClear()" class="btn btn-default" title="刷新付款记录"><i class="fa fa-refresh"></i></a>
  <div class="btn-group" role="group">
	<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">批量操作 <span class="caret"></span></button>
	<ul class="dropdown-menu"><li><a href="javascript:operation(1)">改为成功</a></li><li><a href="javascript:operation(2)">改为失败</a></li><li><a href="javascript:operation(3)">删除记录</a></li></ul>
  </div>
  <button type="button" onclick="statistics()" class="btn btn-default">&nbsp;统计&nbsp;</button>
</form>

<div class="modal" id="modal-statistics" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">付款统计</h4>
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
    <li>总转账金额：<span style="font-weight: 600;">¥ {totalMoney}</span></li>
</ul>
<ul class="list-inline" style="padding-top:10px;margin-bottom: 0;">
	<li>总条数：<span style="font-weight: 600;">{totalCount}</span></li>
    <li>正在处理：<span style="font-weight: 600;">{status0count}</span></li>
    <li>转账成功：<span style="font-weight: 600;">{status1count}</span></li>
    <li>转账失败：<span style="font-weight: 600;">{status2count}</span></li>
    <li>待处理：<span style="font-weight: 600;">{status3count}</span></li>
</ul>
</template>

      <table id="listTable">
	  </table>
    </div>
  </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 30;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax_transfer.php?act=transferList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: '',
				checkbox: true
			},
			{
				field: 'biz_no',
				title: '交易号<br/>第三方交易号',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b><br/>'+row.pay_order_no;
				}
			},
			{
				field: 'uid',
				title: '商户号',
				formatter: function(value, row, index) {
					return value>0?'<a href="./ulist.php?column=uid&value='+value+'" target="_blank">'+value+'</a>':'管理员';
				}
			},
			{
				field: 'type',
				title: '付款方式<br/>备注',
				formatter: function(value, row, index) {
					let typename = '';
					if(value == 'alipay'){
						typename='<img src="/assets/icon/alipay.ico" width="16" onerror="this.style.display=\'none\'">支付宝';
					}else if(value == 'wxpay'){
						typename='<img src="/assets/icon/wxpay.ico" width="16" onerror="this.style.display=\'none\'">微信';
					}else if(value == 'qqpay'){
						typename='<img src="/assets/icon/qqpay.ico" width="16" onerror="this.style.display=\'none\'">QQ钱包';
					}else if(value == 'bank'){
						typename='<img src="/assets/icon/bank.ico" width="16" onerror="this.style.display=\'none\'">银行卡';
					}
					return typename+(row.channel>0 ? '(<a href="./pay_channel.php?kw='+row.channel+'" target="_blank">'+row.channel+'</a>)'+'<br/>'+(row.desc?'<font color="#bf7fef">'+row.desc+'</font>':'')+'' : '');
				}
			},
			{
				field: 'account',
				title: '付款账号<br/>姓名',
				formatter: function(value, row, index) {
					return ''+value+'<br/>'+row.username+'';
				}
			},
			{
				field: 'money',
				title: '付款金额<br/>花费金额',
				formatter: function(value, row, index) {
					return '¥<b>'+value+'</b><br/>¥<b>'+row.costmoney+'</b>';
				}
			},
			{
				field: 'paytime',
				title: '提交时间<br/>付款时间',
				formatter: function(value, row, index) {
					return (row.addtime ? row.addtime : value)+'<br/>'+value;
				}
			},
			{
				field: 'status',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<font color=green>转账成功</font>';
					}else if(value == '2'){
						return '<a href="javascript:showResult(\''+row.biz_no+'\')" title="点此查看失败原因"><font color=red>转账失败</font></a>';
					}else if(value == '3'){
						return '<font color=blue>待处理</font>';
					}else if(value == '4'){
						return '<font color=#26a7e8>待领取</font><br/><a href="javascript:showQrcode(\''+row.jumpurl+'\',\''+row.type+'\')" class="btn btn-xs btn-success"><i class="fa fa-qrcode"></i> 红包码</a>';
					}else{
						return '<a href="javascript:queryStatus(\''+row.biz_no+'\')" title="点此查询转账状态"><font color=orange>正在处理</font></a>' + (row.jumpurl ? '<br/><a href="javascript:showQrcode(\''+row.jumpurl+'\',\''+row.type+'\')" class="btn btn-xs btn-success"><i class="fa fa-qrcode"></i> 确认收款</a>' : '');
					}
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					let html = '';
					if(row.status == '1'){
						html += '<a href="javascript:setStatusFail(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">改为失败</a> <a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a><br/>';
						if(row.channel > 0){
							html += '<a href="javascript:getProof(\''+row.biz_no+'\')" class="btn btn-default btn-xs">获取凭证</a> ';
						}
						html += '<a href="transfer_add.php?app='+row.type+'&copy='+row.biz_no+'" class="btn btn-default btn-xs">复制</a>';
					}else if(row.status == '2'){
						html += '<a href="javascript:setStatusSuccess(\''+row.biz_no+'\')" class="btn btn-success btn-xs">改为成功</a> <a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a><br/><a href="transfer_add.php?app='+row.type+'&copy='+row.biz_no+'" class="btn btn-default btn-xs">复制</a>';
					}else if(row.status == '3'){
						html += '<a href="javascript:setStatusSuccess(\''+row.biz_no+'\')" class="btn btn-success btn-xs">成功</a> <a href="javascript:setStatusFail(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">失败</a><br/>';
						if(row.uid > 0) html += '<a href="javascript:refund(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">退回</a>';
						else html += '<a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
						html += ' <a href="transfer_add.php?app='+row.type+'&copy='+row.biz_no+'" class="btn btn-default btn-xs">复制</a>';
					}else if(row.status == '4'){
						if(row.uid > 0) html += '<a href="javascript:refund(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">退回</a>';
						else html += '<a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
					}else{
						html += '<a href="javascript:queryStatus(\''+row.biz_no+'\')" class="btn btn-info btn-xs">查询状态</a> ';
						if(row.jumpurl) html += '<a href="javascript:cancel(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">撤销</a>';
						else if(row.uid > 0) html += '<a href="javascript:refund(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">退回</a>';
						else html += '<a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
						html += '<br/><a href="transfer_add.php?app='+row.type+'&copy='+row.biz_no+'" class="btn btn-default btn-xs">复制</a>';
					}
					return html;
				}
			},
		],
	})
})
function statistics(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : 'POST',
        url : 'ajax_transfer.php?act=statistics',
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
function showResult(biz_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_transfer.php?act=transfer_result&biz_no='+biz_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg, {icon:0, title:'失败原因', shadeClose:true});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function queryStatus(biz_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_transfer.php?act=transfer_query&biz_no='+biz_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				searchSubmit();
				layer.alert(data.msg, {title:'查询结果'});
			}else{
				layer.alert(data.msg, {icon:2, title:'查询失败'});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function setStatusSuccess(biz_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'post',
		url : 'ajax_transfer.php?act=setTransferStatus',
		data : {biz_no:biz_no, status:'1'},
		dataType : 'json',
		success : function(ret) {
			layer.close(ii);
			if (ret.code != 0) {
				alert(ret.msg);
			}
			searchSubmit();
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function setStatusFail(biz_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_transfer.php?act=transfer_result&biz_no='+biz_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var reason = data.msg == '未知' ? '' : data.msg;
				layer.prompt({title: '输入失败原因', value: reason, formType: 0}, function(text, index){
					var ii = layer.load(2, {shade:[0.1,'#fff']});
					$.ajax({
						type : 'post',
						url : 'ajax_transfer.php?act=setTransferStatus',
						data : {biz_no:biz_no, status:'2', reason: text},
						dataType : 'json',
						success : function(ret) {
							layer.close(ii);
							if (ret.code != 0) {
								alert(ret.msg);
							}
							layer.closeAll();
							searchSubmit();
						},
						error:function(data){
							layer.close(ii);
							layer.msg('服务器错误');
						}
					});
				});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
	
}
function delItem(biz_no) {
	var confirmobj = layer.confirm('你确实要删除此付款记录吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=delTransfer',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.closeAll();
				searchSubmit();
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function refund(biz_no){
	var confirmobj = layer.confirm('确定将余额退给商户并改为失败状态吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=refundTransfer',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.alert(data.msg, {icon:1}, function(){ layer.closeAll();searchSubmit(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function cancel(biz_no){
	var confirmobj = layer.confirm('是否确定撤销转账订单？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=transfer_cancel',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.alert(data.msg, {icon:1}, function(){ layer.closeAll();searchSubmit(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function getProof(biz_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=transfer_proof',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				if(data.download_url){
					layer.alert('获取转账凭证成功！<a href="'+data.download_url+'" target="_blank">点击下载凭证</a>', {icon:1, title:'获取凭证'});
				}else{
					layer.alert(data.msg, {icon:1, title:'获取凭证'});
				}
			}else{
				layer.alert(data.msg, {icon:2, title:'获取失败'});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function operation(status){
	var selected = $('#listTable').bootstrapTable('getSelections');
	if(selected.length == 0){
		layer.msg('未选择记录', {time:1500});return;
	}
	if(status == 3 && !confirm('确定要删除已选中的'+selected.length+'条记录吗？')) return;
	var checkbox = new Array();
	$.each(selected, function(key, item){
		checkbox.push(item.biz_no)
	})
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=operation',
		data : {status:status, checkbox:checkbox},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				searchSubmit();
				layer.alert(data.msg);
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('请求超时');
			searchSubmit();
		}
	});
	return false;
}
function showQrcode(url, type){
	var typename = type == 'alipay' ? '支付宝' : '微信';
	layer.open({
		type: 1,
		title: '收款方使用'+typename+'扫描以下二维码',
		skin: 'layui-layer-demo',
		shadeClose: true,
		content: '<div id="qrcode" class="list-group-item text-center"></div>',
		btn: ['复制链接', '关闭'],
		success: function(){
			$('#qrcode').qrcode({
				text: url,
				width: 230,
				height: 230,
				foreground: "#000000",
				background: "#ffffff",
				typeNumber: -1
			});
		},
		yes: function(index, layero){
			var textarea = document.createElement("textarea");
			textarea.value = url;
			document.body.appendChild(textarea);
			textarea.select();
			document.execCommand("copy");
			document.body.removeChild(textarea);
			layer.msg('已复制到剪切板', {icon: 1, time: 900});
		}
	});
}
$(document).ready(function(){
	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
})
</script>