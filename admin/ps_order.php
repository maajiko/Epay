<?php
/**
 * 分账记录
**/
include("../includes/common.php");
$title='分账记录';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
.tips {cursor: pointer;}
.dates{max-width: 120px;}
@media screen and (max-width: 767px) {
.table-responsive {
    overflow-y: auto;
}
}
.type-logo{width: 18px;margin-top: -2px;padding-right: 4px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
  <div class="container" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
  <div class="form-group">
    <label>搜索</label>
	<select name="column" class="form-control"><option value="trade_no">系统订单号</option><option value="api_trade_no">接口订单号</option><option value="money">分账金额</option></select>
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="value" placeholder="搜索内容">
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="rid" style="width: 80px;" placeholder="分账规则" value="">
  </div>
  <div class="input-group input-daterange">
	<input type="text" id="starttime" name="starttime" class="form-control dates" placeholder="开始日期" autocomplete="off" title="留空则不限时间范围">
	<span class="input-group-addon" onclick="$('#starttime').val('');$('#endtime').val('');" title="清除"><i class="fa fa-chevron-right"></i></span>
	<input type="text" id="endtime" name="endtime" class="form-control dates" placeholder="结束日期" autocomplete="off" title="留空则不限时间范围">
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">待分账</option><option value="1">已提交</option><option value="2">分账成功</option><option value="3">分账失败</option><option value="4">已取消</option></select>
  </div>
  <button type="submit" class="btn btn-primary">搜索</button>
  <a href="javascript:searchClear()" class="btn btn-default" title="刷新记录列表"><i class="fa fa-refresh"></i></a>
  <div class="btn-group" role="group">
	<button type="button" id="batchAction" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="caret"></span></button>
	<ul class="dropdown-menu">
		<li><a href="javascript:batch_act('submit')" data-action-type="submit">批量分账</a></li>
		<li><a href="javascript:batch_act('return')" data-action-type="return">批量回退</a></li>
		<li><a href="javascript:batch_act('unfreeze')" data-action-type="unfreeze">批量取消</a></li>
		<li role="separator" class="divider"></li>
		<li><a href="javascript:operation('2')" data-action-type="2">改为成功</a></li>
		<li><a href="javascript:operation('3')" data-action-type="3">改为失败</a></li>
		<li><a href="javascript:operation('4')" data-action-type="4">改为取消</a></li>
		<li><a href="javascript:operation('5')" data-action-type="5">删除</a></li>
	</ul>
  </div>
  <button type="button" onclick="statistics()" class="btn btn-default">&nbsp;统计&nbsp;</button>
</form>
      <table id="listTable">
	  </table>
    </div>
  </div>
<div class="modal" id="modal-statistics" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">分账统计概况</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" data-dismiss="modal">关闭</button>
			</div>
		</div>
	</div>
</div>

<template id="statistics-template">
<ul class="list-inline" style="margin-bottom: 0;padding-bottom: 10px;border-bottom: 1px solid #dddddd;">
    <li>分账总金额：<span style="font-weight: 600;">¥ {totalMoney}</span></li>
    <li>成功分账：<span style="font-weight: 600;">¥ {successMoney}</span></li>
    <li>失败金额：<span style="font-weight: 600;">¥ {failMoney}</span></li>
</ul>
<ul class="list-inline" style="padding-top:10px;margin-bottom: 0;">
    <li>分账总数：<span style="font-weight: 600;">{totalCount}</span></li>
    <li>成功分账：<span style="font-weight: 600;">{successCount}</span></li>
    <li>分账失败：<span style="font-weight: 600;">{failCount}</span></li>
    <li>成功率：<span style="font-weight: 600;">{successRate}%</span></li>
</ul>
</template>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$.action_arr = {
	'submit': '提交分账',
	'return': '分账回退',
	'unfreeze': '取消分账',
	'query': '查询结果',
};
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 30;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax_profitsharing.php?act=orderList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		uniqueId: 'id',
		columns: [
			{
				field: '',
				checkbox: true
			},
			{
				field: 'trade_no',
				title: '系统订单号',
				formatter: function(value, row, index) {
					return '<b><a href="./order.php?column=trade_no&value='+value+'" target="_blank">'+value+'</a></b>';
				}
			},
			{
				field: 'rid',
				title: '分账规则',
				formatter: function(value, row, index) {
					return '<a href="./ps_receiver.php?column=id&value='+value+'" target="_blank">'+value+'</a>';
				}
			},
			{
				field: 'channelid',
				title: '支付方式(通道ID)',
				formatter: function(value, row, index) {
					return row.channelname?'<img src="/assets/icon/'+row.typename+'.ico" class="type-logo" onerror="this.style.display=\'none\'">'+row.typeshowname+'(<a href="./pay_channel.php?kw='+row.channelid+'" target="_blank" title='+row.channelname+'>'+row.channelid+'</a>)':null;
				}
			},
			{
				field: 'ordermoney',
				title: '订单金额',
				formatter: function(value, row, index) {
					return '¥<b>'+value+'</b>';
				}
			},
			{
				field: 'money',
				title: '分账金额',
				formatter: function(value, row, index) {
					return '¥<b>'+value+'</b>';
				}
			},
			{
				field: 'addtime',
				title: '时间'
			},
			{
				field: 'status',
				title: '分账状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<font color=orange>已提交</font>';
					}else if(value == '2'){
						return '<font color=green>分账成功</font>';
					}else if(value == '3'){
						return '<font color=red>分账失败</font>' + (row.result ? ' <span onclick="showmsg(\''+row.result+'\')" class="tips" title="失败原因"><i class="fa fa-info-circle"></i></span>' : '');
					}else if(value == '4'){
						return '<font color=grey>已取消</font>';
					}else{
						return '<font color=blue>待分账</font>';
					}
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<a href="javascript:do_query('+row.id+')" class="btn btn-info btn-xs">查询结果</a>';
					}else if(value == '2'){
						return '<a href="javascript:do_return('+row.id+')" class="btn btn-danger btn-xs">分账回退</a>';
					}else if(value == '3'){
						return '<a href="javascript:do_submit('+row.id+')" class="btn btn-primary btn-xs">重试</a>&nbsp;<a href="javascript:do_unfreeze('+row.id+')" class="btn btn-danger btn-xs">取消</a>';
					}else if(value == '0'){
						return '<a href="javascript:do_submit('+row.id+')" class="btn btn-primary btn-xs">提交分账</a>&nbsp;<a href="javascript:do_unfreeze('+row.id+')" class="btn btn-danger btn-xs">取消</a>';
					}
				}
			},
		]
	})
})
function operation(status){
	var selected = $('#listTable').bootstrapTable('getSelections');
	if(selected.length == 0){
		layer.msg('未选择订单', {time:1500});return;
	}
	var checkbox = new Array();
	$.each(selected, function(key, item){
		checkbox.push(item.id)
	})
	if(status == 5 && !confirm('确定要删除已选中的'+checkbox.length+'个订单吗？')) return;
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_profitsharing.php?act=operation',
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
function batch_act(action){
	var selected = $('#listTable').bootstrapTable('getSelections');
	if(selected.length == 0){
		layer.msg('未选择订单', {time:1500});return;
	}
	var checkbox = getBatchOperateData(selected, 'id')[action];
	if(checkbox.length == 0){
		layer.msg('要处理的订单数量为0', {time:1500});return;
	}
	var content_arr = {
		'submit': '是否将这'+checkbox.length+'条订单进行分账？',
		'return': '是否将这'+checkbox.length+'条已分账的订单回退？资金将从分账接收方的账户回退给分账方。',
		'unfreeze': '是否将这'+checkbox.length+'条订单取消分账？取消分账后将解冻资金到商户号，后续无法再次发起分账。'
	};
	var confirmobj = layer.confirm(content_arr[action], {
		title: $.action_arr[action],
		btn: ['确定','取消'],
		icon:0
	}, function(){
		batch_act_order(checkbox, 0, action);
	}, function(){
		layer.close(confirmobj);
	});
}
function batch_act_order(ids, index, action){
	if (index >= ids.length) {
		layer.alert('已成功'+$.action_arr[action]+ids.length+'条订单！', {icon: 1}, function(){
			layer.closeAll();searchSubmit();
		});
		return;
	}
	var id = ids[index];
	var row = $("#listTable").bootstrapTable('getRowByUniqueId', id);
	var ii = layer.msg('正在' + $.action_arr[action] + row.trade_no + '（'+row.money+'元）', {icon: 16, shade: 0.3, time: 0});
	var url_arr = {
		'submit': 'ajax_profitsharing.php?act=submit',
		'return': 'ajax_profitsharing.php?act=return',
		'unfreeze': 'ajax_profitsharing.php?act=unfreeeze'
	};
	$.ajax({
		type : 'POST',
		url : url_arr[action],
		data : {id:id},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			index > 0 && searchSubmit();
			if(data.code >= 0){
				batch_act_order(ids, index + 1, action);
			}else{
				layer.alert(row.trade_no + $.action_arr[action] + '失败：' + data.msg, {icon:7});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function getBatchOperateData(selections, field) {
	var counts = {
		'submit': field ? [] : 0, // 批量分账
		'return': field ? [] : 0, // 批量回退
		'unfreeze': field ? [] : 0, // 批量取消
	};
	selections.forEach(function(row) {
		var status = parseInt(row.status);
		if (status == 0) {
			if (field) {
				counts['submit'].push(row[field]);
				counts['unfreeze'].push(row[field]);
			} else {
				counts['submit']++;
				counts['unfreeze']++;
			}
		} else if (status == 2) {
			if (field) {
				counts['return'].push(row[field]);
			} else {
				counts['return']++;
			}
		} else if (status == 3) {
			if (field) {
				counts['submit'].push(row[field]);
				counts['unfreeze'].push(row[field]);
			} else {
				counts['submit']++;
				counts['unfreeze']++;
			}
		}
	});
	return counts;
}
function do_submit(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_profitsharing.php?act=submit',
		data : {id:id},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code >= 0){
				layer.alert(data.msg, {icon: 1}, function(){layer.closeAll();searchSubmit()});
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function do_query(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_profitsharing.php?act=query',
		data : {id:id},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var msg = '查询结果：正在分账';
				if(data.status == 1) msg = '查询结果：分账成功';
				else if(data.status == 2) msg = '查询结果：分账失败，原因：'+data.reason;
				layer.alert(msg, {icon: 1}, function(){layer.closeAll();searchSubmit()});
			}else{
				layer.alert('查询失败：'+data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function do_unfreeze(id){
	var confirmobj = layer.confirm('取消分账后将解冻资金到商户号，后续无法再次发起分账，是否继续？', {
		btn: ['确定','取消']
	}, function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax_profitsharing.php?act=unfreeeze',
			data : {id:id},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					layer.alert(data.msg, {icon: 1}, function(){layer.closeAll();searchSubmit()});
				}else{
					layer.alert('解冻剩余资金失败：'+data.msg, {icon: 2});
				}
			},
			error:function(data){
				layer.close(ii);
				layer.msg('服务器错误');
			}
		});
	});
}
function do_return(id){
	var confirmobj = layer.confirm('将已分账的资金从分账接收方的账户回退给分账方，是否继续？', {
		btn: ['确定','取消']
	}, function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax_profitsharing.php?act=return',
			data : {id:id},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					layer.alert(data.msg, {icon: 1}, function(){layer.closeAll();searchSubmit()});
				}else{
					layer.alert('退分账失败：'+data.msg, {icon: 2});
				}
			},
			error:function(data){
				layer.close(ii);
				layer.msg('服务器错误');
			}
		});
	});
}
function showmsg(result){
	layer.alert(result, {title:'失败原因'});
}
function editmoney(id, money){
	layer.prompt({title: '修改分账金额', value: money, formType: 0}, function(text, index){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax_profitsharing.php?act=editmoney',
			data : {id:id,money:text},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					layer.closeAll();
					layer.msg('修改成功', {time:800});
					searchSubmit()
				}else{
					layer.alert(data.msg, {icon: 2});
				}
			},
			error:function(data){
				layer.close(ii);
				layer.msg('服务器错误');
			}
		});
	});
}
function statistics(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : 'POST',
        url : 'ajax_profitsharing.php?act=statistics',
        data: $('#searchToolbar').serializeArray(),
        dataType : 'json',
        success : function(data) {
            layer.close(ii);
            if(data.code == 0){
                var element = $('#modal-statistics');
                var htmlContent = $("#statistics-template").html().replace(/\{(\w+)\}/g, function (match, key) {
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
$(document).ready(function(){
	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
	$("#batchAction").click(function(e) {
		e.preventDefault();
		var selections = $('#listTable').bootstrapTable('getSelections'),
			button = $(this);
		var counts = getBatchOperateData(selections);
		button.next('.dropdown-menu').find('a[data-action-type]').each(function() {
			var actionType = $(this).data('action-type');
			var count = counts[actionType];
			if(typeof count == 'undefined') return;
			var originalText = $(this).text().split('(')[0];
			$(this).text(originalText + (count > 0 ? ' (可执行' + count + '条)' : ' (0)'));
		});
	});
})
</script>