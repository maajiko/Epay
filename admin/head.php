<?php
@header('Content-Type: text/html; charset=UTF-8');

$admin_cdnpublic = 0;
if($admin_cdnpublic==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($admin_cdnpublic==2){
	$cdnpublic = 'https://s4.zstatic.net/ajax/libs/';
}elseif($admin_cdnpublic==4){
	$cdnpublic = 'https://cdnjs.snrat.com/ajax/libs/';
}else{
	$cdnpublic = '/assets/vendor/';
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo $title ?></title>
  <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="../assets/css/bootstrap-table.css?v=1" rel="stylesheet"/>
  <link href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
  <script src="<?php echo $cdnpublic?>modernizr/2.8.3/modernizr.min.js"></script>
  <script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
  <script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <!--[if lt IE 9]>
    <script src="<?php echo $cdnpublic?>html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="<?php echo $cdnpublic?>respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
</head>
<body>
<?php if($islogin==1){?>
  <nav class="navbar navbar-fixed-top navbar-default">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">导航按钮</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="./">支付管理中心</a>
      </div><!-- /.navbar-header -->
      <div id="navbar" class="collapse navbar-collapse">
        <ul class="nav navbar-nav navbar-right">
          <li class="<?php echo checkIfActive('index,')?>">
            <a href="./"><i class="fa fa-home"></i> 平台首页</a>
          </li>
          <li class="<?php echo checkIfActive('order,export,ps_receiver,ps_order,buyerstat,blacklist')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-list"></i> 收款订单<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./order.php">订单管理</a></li>
              <li><a href="./export.php">导出订单</a><li>
              <li><a href="./buyerstat.php">支付用户统计</a></li>
              <li><a href="./blacklist.php">黑名单管理</a></li>
              <li role="separator" class="divider"></li>
              <li><a href="./ps_receiver.php">分账规则</a><li>
              <li><a href="./ps_order.php">分账记录</a><li>
            </ul>
          </li>
          <li class="<?php echo checkIfActive('settle,settle_batch,slist,transfer,transfer_add,transfer_export,transfer_red,transfer_batch,transfer_stat')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cloud"></i> 付款管理<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./slist.php">结算管理</a></li>
              <li><a href="./settle.php">批量结算</a><li>
              <li role="separator" class="divider"></li>
              <li><a href="./transfer.php">付款记录</a><li>
              <li><a href="./transfer_add.php">新增付款</a><li>
              <li><a href="./transfer_red.php">创建红包</a><li>
              <li><a href="./transfer_stat.php">付款统计</a><li>
              <li><a href="./transfer_export.php">导出付款记录</a><li>
              <?php if(class_exists('\\lib\\AlipaySATF\\AlipaySATF')){?><li><a href="./satf_transfer.php">安全发转账记录</a></li><?php }?>
            </ul>
          </li>
		  <li class="<?php echo checkIfActive('ulist,glist,gedit,group,record,uset,domain,ustat,invitecode,uexport')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user"></i> 商户管理<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./ulist.php">用户列表</a></li>
			  <li><a href="./glist.php">用户组设置</a></li>
			  <li><a href="./group.php">用户组购买</a></li>
			  <li><a href="./record.php">资金明细</a></li>
        <li><a href="./ustat.php">支付统计</a></li>
        <?php if($conf['pay_domain_forbid']==1 || $conf['pay_domain_open']==1){?><li><a href="./domain.php">授权域名</a></li><?php }?>
        <?php if($conf['reg_open']==2){?><li><a href="./invitecode.php">邀请码管理</a></li><?php }?>
            </ul>
          </li>
		  <li class="<?php echo checkIfActive('pay_channel,pay_roll,pay_type,pay_plugin,pay_weixin,applyments_channel,applyments_merchant,applyments_form')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-credit-card"></i> 支付接口<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./pay_channel.php">支付通道</a></li>
			  <li><a href="./pay_type.php">支付方式</a></li>
			  <li><a href="./pay_plugin.php">支付插件</a></li>
        <li><a href="./pay_roll.php">支付通道轮询</a></li>
        <li><a href="./pay_weixin.php">公众号小程序</a></li>
        <?php if(class_exists('\\lib\\Applyments\\CommUtil')){?><li><a href="./applyments_channel.php">进件渠道管理</a></li>
        <li><a href="./applyments_merchant.php">进件商户管理</a></li><?php }?>
            </ul>
          </li>
		  <li class="<?php echo checkIfActive('set,gonggao,set_wxkf')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cog"></i> 系统设置<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./set.php?mod=site">网站信息配置</a></li>
			  <li><a href="./set.php?mod=pay">支付相关配置</a><li>
        <li><a href="./set.php?mod=risk">风控检测配置</a><li>
        <li><a href="./set.php?mod=settle">结算规则配置</a><li>
			  <li><a href="./set.php?mod=transfer">转账付款配置</a><li>
			  <li><a href="./set.php?mod=oauth">快捷登录配置</a><li>
        <li><a href="./set.php?mod=notice">消息提醒配置</a><li>
			  <li><a href="./set.php?mod=certificate">实名认证配置</a><li>
			  <li><a href="./gonggao.php">网站公告配置</a></li>
			  <li><a href="./set.php?mod=template">首页模板配置</a><li>
			  <li><a href="./set.php?mod=mail">邮箱与短信配置</a><li>
			  <li><a href="./set.php?mod=upimg">网站Logo上传</a><li>
			  <li><a href="./set.php?mod=cron">计划任务配置</a><li>
        <li><a href="./set_wxkf.php">H5跳转微信客服支付</a></li>
            </ul>
          </li>
		  <li class="<?php echo checkIfActive('clean,log,risk,gettoken,complain,complain_info')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cube"></i> 其他功能<b class="caret"></b></a>
            <ul class="dropdown-menu">
			  <li><a href="./risk.php">风控记录</a><li>
			  <li><a href="./log.php">登录日志</a><li>
			  <li><a href="./clean.php">数据清理</a><li>
        <li><a href="./gettoken.php">获取用户标识</a><li>
        <?php if(class_exists('\\lib\\Complain\\CommUtil')){?><li><a href="./complain.php">支付交易投诉</a></li><?php }?>
        <?php if(class_exists('\\lib\\WxMchRisk')){?><li><a href="./mchrisk.php">渠道商户违规记录</a></li><?php }?>
            </ul>
          </li>
          <li><a href="./login.php?logout" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-power-off"></i> 退出登录</a></li>
        </ul>
      </div><!-- /.navbar-collapse -->
    </div><!-- /.container -->
  </nav><!-- /.navbar -->
<?php }?>