<?php
include("../includes/common.php");
$title='付款统计';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

?>
<style>
#orderItem .orderTitle{word-break:keep-all;}
#orderItem .orderContent{word-break:break-all;}
.dates{max-width: 120px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
  <div class="container" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
  <div class="input-group">
	<label>查询日期:</label>
  </div>
  <div class="input-group input-daterange">
	<input type="text" id="startday" name="startday" class="form-control dates" placeholder="开始日期" autocomplete="off" value="<?php echo date("Y-m-d")?>">
	<span class="input-group-addon"><i class="fa fa-chevron-right"></i></span>
	<input type="text" id="endday" name="endday" class="form-control dates" placeholder="结束日期" autocomplete="off" value="<?php echo date("Y-m-d")?>">
  </div>
  <div class="form-group">
	<select name="type" class="form-control"><option value="">所有付款方式</option><option value="alipay">支付宝</option><option value="wxpay">微信</option><option value="qqpay">QQ钱包</option><option value="bank">银行卡</option></select>
  </div>
  <button type="submit" class="btn btn-primary">&nbsp;搜索&nbsp;</button>
</form>

      <table id="listTable">
	  </table>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();
	var method = $("select[name='method']").val();

	$("#listTable").bootstrapTable({
		url: 'ajax_transfer.php?act=stat',
		pageNumber: 1,
		pageSize: 30,
		sidePagination: 'client',
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'account',
				title: '付款账号',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'username',
				title: '姓名',
				formatter: function(value, row, index) {
					return value;
				}
			},
			{
				field: 'order_count',
				title: '付款笔数',
				formatter: function(value, row, index) {
					return '<a href="./transfer.php?column=account&value='+row.account+'" target="_blank">'+value+'</a>';
				}
			},
			{
				field: 'money',
				title: '付款金额'
			}
		],
	})
})
$(document).ready(function(){
	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
})
</script>