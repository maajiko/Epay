<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$other=isset($_GET['other'])?true:false;
$trade_no=daddslashes($_GET['trade_no']);
$sitename=base64_decode(daddslashes($_GET['sitename']));
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if(!$row)sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($row['status']==1)sysmsg('该订单已完成支付，请勿重复支付');
$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");
$paytype = \lib\Channel::getTypes($row['uid'], $gid);

if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
	$paytype = array_values($paytype);
	foreach($paytype as $i=>$s){
		if($s['name']=='wxpay'){
			$temp = $paytype[$i];
			$paytype[$i] = $paytype[0];
			$paytype[0] = $temp;
		}
	}
}
?>
<!DOCTYPE html>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0" name="viewport">
<title>收银台 | <?php echo $sitename?$sitename:$conf['sitename']?> </title>
<link href="/assets/css/reset.css" rel="stylesheet" type="text/css">
<link href="/assets/css/main12.css?v=2" rel="stylesheet" type="text/css">
</head>
<body style="background-color:#f9f9f9">
<!--导航-->
<div class="w100 navBD12">
    <div class="w1080 nav12">
        <div class="nav12-left">
            <img src="/assets/img/logo.png">
        </div>
		<div class="nav12-right">
            收银台
        </div>

    </div>
</div>
<input type="hidden" name="trade_no" value="<?php echo $trade_no?>"/>
<!--订单金额-->
<?php if($other){?>
<div class="w1080 order-amount12" style="height: auto;">
    <h2><font style="color: red">当前支付方式暂时关闭维护，请更换其他方式支付</font></h2>
</div>
<?php if(in_array('qqpay',array_column($paytype,'name'))){?>
<div class="w1080 order-amount12" style="height: auto;">
    <h2 style="font-size:18px"><font style="color: green">如果您需要微信支付请将微信余额转到QQ再选择QQ钱包支付！</font></h2>
	<h3><a href="./wx.html" style="font-size:20px;color:blue">点击查看微信余额转到QQ钱包教程</a></h3>
</div>
<?php }}else{?>
<div class="w1080 order-amount12">
    <ul class="order-amount12-left">
        <li>
            <span>商品名称：</span>
            <span><?php echo $row['name']?></span>
        </li>
        <li>
            <span>订单号：</span>
            <span><?php echo $trade_no?></span>
        </li>
		<li>
            <span>创建时间：</span>
            <span><?php echo $row['addtime']?></span>
        </li>
    </ul>
    <div class="order-amount12-right">
        <span>订单金额：</span>
        <strong><?php echo $row['money']?></strong>
        <span>元</span>
    </div>  
</div>
<?php }?>
<!--支付方式-->
<div class="w1080 PayMethod12">
    <div class="row">
        <h2>支付方式</h2>
        <ul class="types">
		<?php foreach($paytype as $rows){?>
          <li class="pay_li" value="<?php echo $rows['id']?>">
             <img src="/assets/icon/<?php echo $rows['name']?>.ico">
                    <span><?php echo $rows['showname']?></span>
          </li>
		<?php }?>
        </ul>
    </div>
</div>
<!--立即支付-->
<div class="w1080 immediate-pay12">
  <div class="immediate-pay12-right">
      <span>需支付：<strong><?php echo $row['realmoney']?$row['realmoney']:$row['money']?></strong>元<?php if($row['realmoney'] && $row['realmoney']!=$row['money'])echo '（包含'.($row['realmoney']-$row['money']).'元手续费）';?></span>
        <a class="immediate_pay">立即支付</a>
    </div>
</div>
<div class="mt_agree">
  <div class="mt_agree_main">
    <h2>提示信息</h2>
    <p id="errorContent" style="text-align:center;line-height:36px;"></p>
    <a class="close_btn">确定</a>
  </div>
</div>
<!--底部-->
<div class="w1080 footer12">
    <p> <?php echo $sitename?$sitename:$conf['sitename']?></p>
</div>

<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	$(".types li").click(function(){
		$(".types li").each(function(){
			$(this).attr('class','');
		});
		$(this).attr('class','active');
	});
	$(document).on("click", ".immediate_pay", function () {
		var value = $(".types").find('.active').attr('value');
		var trade_no = $("input[name='trade_no']").val();
		window.location.href='./submit2.php?typeid='+value+'&trade_no='+trade_no;
	});
	$(".types li:first").click();
})
</script>
</body>
</html>