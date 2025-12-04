<?php
if(!defined('IN_CRONLITE'))exit();
?><!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>开发文档 - <?php echo $conf['sitename']?></title>
    <!-- jQuery-->
    <script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
    <!-- layui -->
    <link rel="stylesheet" href="<?php echo $cdnpublic?>layui/2.6.13/css/layui.css" />
    <script src="<?php echo $cdnpublic?>layui/2.6.13/layui.js"></script>
    <!-- zTree -->
    <link rel="stylesheet" href="<?php echo $cdnpublic?>zTree.v3/3.5.42/css/zTreeStyle/zTreeStyle.min.css" />
    <script src="<?php echo $cdnpublic?>zTree.v3/3.5.42/js/jquery.ztree.core.min.js"></script>
    <!-- SyntaxHighlighter -->
    <script src="/assets/doc/js/shCore.min.js" type="text/javascript"></script>
    <link rel="stylesheet" type="text/css" href="/assets/doc/css/shCoreDefault.css"/>
    <!-- 自定义 -->
    <link rel="stylesheet" href="/assets/doc/css/style.css" />
    <script src="/assets/doc/js/home.js"></script>
    <link rel="stylesheet" href="/assets/doc/css/docView.css" />
    <script src="/assets/doc/js/docView.js"></script>
</head>
<body>
    <!-- top-begin -->
    <div id="navbar">
        <div class="bg-blur" style="background: white!important;"></div>
        <div class="navbar-body">
            <ul class="layui-nav" lay-filter="">
                <div class="navRight">
                    <li class="layui-nav-item layui-this" lay-unselect>
                        <a href="/" style="padding-right: 40px;">返回官网</a>
                    </li>
                </div>
            </ul>
            <div class="nav-menu">
                <a href="" class="logo"><img src="/assets/img/logo.png"/></a>
                <a href="javascript:;" id="navMenuLeft"><i class="layui-icon layui-icon-spread-left"></i></a>
                <a href="javascript:;" id="navMenuRight"><i class="layui-icon">&#xe61a;</i></a>
            </div>
            <div class="clear"></div>
        </div>
    </div>
    <script>
    function showMask()
    {
        $('#mask').show();
    }
    function hideMask()
    {
        $('#mask').hide();
    }
    function clickMask()
    {
        closeMenuLeft();
        closeMenuRight();
    }
    function openMenuLeft()
    {
        $('#leftbar').addClass('show-item');
        $('#navMenuLeft').addClass('active');
        var ico = $('#navMenuLeft .layui-icon');
        ico.removeClass('layui-icon-spread-left');
        ico.addClass('layui-icon-shrink-right');
        showMask();
    }
    function closeMenuLeft()
    {
        $('#leftbar').removeClass('show-item');
        $('#navMenuLeft').removeClass('active');
        var ico = $('#navMenuLeft .layui-icon');
        ico.removeClass('layui-icon-shrink-right');
        ico.addClass('layui-icon-spread-left');
        hideMask();
    }
    $('#navMenuLeft').click(function(){
        var isShow = $('#leftbar').hasClass('show-item');
        if(isShow)
        {
            closeMenuLeft();
        }
        else
        {
            closeMenuRight();
            openMenuLeft();
        }
    });
    function openMenuRight()
    {
        $('#navbar > .navbar-body > .layui-nav').addClass('show-item')
        $('#navMenuRight').addClass('active').find('i').html('&#xe619;');
        showMask();
    }
    function closeMenuRight()
    {
        $('#navbar > .navbar-body > .layui-nav').removeClass('show-item')
        $('#navMenuRight').removeClass('active').find('i').html('&#xe61a;');
        hideMask();
    }
    $('#navMenuRight').click(function(){
        var isShow = $('#navbar > .navbar-body > .layui-nav').hasClass('show-item');
        if(isShow)
        {
            closeMenuRight();
        }
        else
        {
            closeMenuLeft();
            openMenuRight();
        }
    });
    </script>
    <!-- top-end -->

    <!-- left-begin -->
    <div id="leftbar" class="layui-nav-side">
        <div class="layui-tab layui-tab-brief" style="margin-top:0">
            <ul class="layui-tab-title">
                <li class="layui-this"><i class="layui-icon">&#xe705;</i> 目录</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">
                    <ul id="treeDirectory" class="ztree showIcon"></ul>
                </div>
                <div class="layui-tab-item">
                    <div class="searchBox">
                        <div id="searchForm">
                            <div class="inputBox">
                                <input type="text" id="search-keyword" autocomplete="off" name="keyword" placeholder="搜索关键词" class="layui-input"/>
                                <i class="layui-icon input-icon">&#xe615;</i>
                            </div>
                        </div>
                        <ul id="treeSearch">
                        </ul>
                        <div class="searchResultNone">
                            <i class="layui-icon">&#xe615;</i>
                            <p>未搜索到结果</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="copyright noScroll"><?php echo $conf['sitename']?></div>
        </div>
    </div>
    <script id="searchListTemplate" type="text/html">
        {{#  layui.each(d, function(index, item){ }}
        <li>
            <a href="{{ item.url }}">
                <h3>{{ item.searchedTitle }}</h3>
                <p>{{ item.searchedContent }}</p>
            </a>
        </li>
        {{#  }) }}
    </script>
    <!-- left-end -->

    <div id="body">
        <div id="content_body" name="content_body" style="width:100%;height:100%;border:none;overflow: auto;">
            <div id="article-content" class="markdown-body">
                <script>
                    var catalogList = [{"id":1,"parent_id":0,"title":"接口说明","mdFileName":"index.md","url":"index.html","level":0},{"id":2,"parent_id":0,"title":"签名规则","mdFileName":"sign_note.md","url":"sign_note.html","level":0},{"id":3,"parent_id":0,"title":"支付方式列表","mdFileName":"paytype.md","url":"paytype.html","level":0},{"id":4,"parent_id":0,"title":"支付相关接口","level":0},{"id":5,"parent_id":4,"title":"页面跳转支付","mdFileName":"pay_submit.md","url":"pay_submit.html","level":1},{"id":6,"parent_id":4,"title":"统一下单接口","mdFileName":"pay_create.md","url":"pay_create.html","level":1},{"id":7,"parent_id":4,"title":"订单查询","mdFileName":"pay_query.md","url":"pay_query.html","level":1},{"id":8,"parent_id":4,"title":"支付结果通知","mdFileName":"pay_notify.md","url":"pay_notify.html","level":1},{"id":9,"parent_id":4,"title":"订单退款","mdFileName":"pay_refund.md","url":"pay_refund.html","level":1},{"id":10,"parent_id":4,"title":"订单退款查询","mdFileName":"pay_refundquery.md","url":"pay_refundquery.html","level":1},{"id":11,"parent_id":4,"title":"关闭订单","mdFileName":"pay_close.md","url":"pay_close.html","level":1},{"id":20,"parent_id":0,"title":"商户相关接口","level":0},{"id":21,"parent_id":20,"title":"查询商户信息","mdFileName":"merchant_info.md","url":"merchant_info.html","level":1},{"id":22,"parent_id":20,"title":"查询订单列表","mdFileName":"merchant_orders.md","url":"merchant_orders.html","level":1},{"id":30,"parent_id":0,"title":"代付相关接口","level":0},{"id":31,"parent_id":30,"title":"转账发起","mdFileName":"transfer_submit.md","url":"transfer_submit.html","level":1},{"id":32,"parent_id":30,"title":"转账查询","mdFileName":"transfer_query.md","url":"transfer_query.html","level":1},{"id":33,"parent_id":30,"title":"可用余额查询","mdFileName":"transfer_balance.md","url":"transfer_balance.html","level":1},{"id":50,"parent_id":0,"title":"SDK下载","pageTitle":"接口说明","mdFileName":"sdk.md","url":"sdk.html","level":0}];
                    initTree(catalogList);
                </script>
                <h1 id="统一下单接口"><a href="#统一下单接口">统一下单接口</a></h1><blockquote>此接口可用于服务器后端发起支付请求，会返回支付二维码链接、支付跳转url等。</blockquote><h4 id="请求地址："><a href="#请求地址：">请求地址：</a></h4><p><code><?php echo $conf['apiurl']?>api/pay/create</code></p><h4 id="请求方式："><a href="#请求方式：">请求方式：</a></h4><p>POST</p><h4 id="请求参数说明："><a href="#请求参数说明：">请求参数说明：</a></h4><table><thead><tr><th align="left">字段名</th><th align="left">变量名</th><th align="left">必填</th><th align="left">类型</th><th align="left">示例值</th><th align="left">描述</th></tr></thead><tbody><tr><td align="left">商户ID</td><td align="left">pid</td><td align="left">是</td><td align="left">Int</td><td align="left">1001</td><td align="left"> </td></tr><tr><td align="left">接口类型</td><td align="left">method</td><td align="left">是</td><td align="left">String</td><td align="left">web</td><td align="left"><a href="#接口类型列表">接口类型列表</a></td></tr><tr><td align="left">设备类型</td><td align="left">device</td><td align="left">否</td><td align="left">String</td><td align="left">pc</td><td align="left">仅通用网页支付需要传 <a href="#设备类型列表">设备类型列表</a></td></tr><tr><td align="left">支付方式</td><td align="left">type</td><td align="left">是</td><td align="left">String</td><td align="left">alipay</td><td align="left"><a href="./paytype.html">支付方式列表</a></td></tr><tr><td align="left">商户订单号</td><td align="left">out_trade_no</td><td align="left">是</td><td align="left">String</td><td align="left">20160806151343349</td><td align="left"> </td></tr><tr><td align="left">异步通知地址</td><td align="left">notify_url</td><td align="left">是</td><td align="left">String</td><td align="left">http://www.pay.com/notify_url.php</td><td align="left">服务器异步通知地址</td></tr><tr><td align="left">跳转通知地址</td><td align="left">return_url</td><td align="left">是</td><td align="left">String</td><td align="left">http://www.pay.com/return_url.php</td><td align="left">页面跳转通知地址</td></tr><tr><td align="left">商品名称</td><td align="left">name</td><td align="left">是</td><td align="left">String</td><td align="left">VIP会员</td><td align="left">如超过127个字节会自动截取</td></tr><tr><td align="left">商品金额</td><td align="left">money</td><td align="left">是</td><td align="left">String</td><td align="left">1.00</td><td align="left">单位：元，最大2位小数</td></tr><tr><td align="left">用户IP地址</td><td align="left">clientip</td><td align="left">是</td><td align="left">String</td><td align="left">192.168.1.100</td><td align="left">用户发起支付的IP地址</td></tr><tr><td align="left">业务扩展参数</td><td align="left">param</td><td align="left">否</td><td align="left">String</td><td align="left">没有请留空</td><td align="left">支付后原样返回</td></tr><tr><td align="left">被扫支付授权码</td><td align="left">auth_code</td><td align="left">否</td><td align="left">String</td><td align="left"> </td><td align="left">仅被扫支付需要传</td></tr><tr><td align="left">用户Openid</td><td align="left">sub_openid</td><td align="left">否</td><td align="left">String</td><td align="left"> </td><td align="left">仅JSAPI支付需要传</td></tr><tr><td align="left">公众号AppId</td><td align="left">sub_appid</td><td align="left">否</td><td align="left">String</td><td align="left"> </td><td align="left">仅JSAPI支付需要传</td></tr><tr><td align="left">自定义通道ID</td><td align="left">channel_id</td><td align="left">否</td><td align="left">Int</td><td align="left"> </td><td align="left">对应进件商户列表的ID，未进件请勿传</td></tr><tr><td align="left">买家身份证号</td><td align="left">cert_no</td><td align="left">否</td><td align="left">String</td><td align="left"> </td><td align="left">可限制指定买家，仅支持支付宝官方接口</td></tr><tr><td align="left">买家真实姓名</td><td align="left">cert_name</td><td align="left">否</td><td align="left">String</td><td align="left"> </td><td align="left">可限制指定买家，仅支持支付宝官方接口</td></tr><tr><td align="left">买家最小年龄</td><td align="left">min_age</td><td align="left">否</td><td align="left">Int</td><td align="left"> </td><td align="left">可限制买家年龄，仅支持支付宝官方接口</td></tr><tr><td align="left">当前时间戳</td><td align="left">timestamp</td><td align="left">是</td><td align="left">String</td><td align="left">1721206072</td><td align="left">10位整数，单位秒</td></tr><tr><td align="left">签名字符串</td><td align="left">sign</td><td align="left">是</td><td align="left">String</td><td align="left"> </td><td align="left">参考签名规则</td></tr><tr><td align="left">签名类型</td><td align="left">sign_type</td><td align="left">是</td><td align="left">String</td><td align="left">RSA</td><td align="left">默认为RSA</td></tr></tbody></table><h4 id="返回参数说明："><a href="#返回参数说明：">返回参数说明：</a></h4><table><thead><tr><th align="left">字段名</th><th align="left">变量名</th><th align="left">类型</th><th align="left">示例值</th><th align="left">描述</th></tr></thead><tbody><tr><td align="left">返回状态码</td><td align="left">code</td><td align="left">Int</td><td align="left">0</td><td align="left">0为成功，其它值为失败</td></tr><tr><td align="left">错误信息</td><td align="left">msg</td><td align="left">String</td><td align="left"> </td><td align="left">失败时返回原因</td></tr><tr><td align="left">平台订单号</td><td align="left">trade_no</td><td align="left">String</td><td align="left">20160806151343349</td><td align="left">平台内部的订单号</td></tr><tr><td align="left">发起支付类型</td><td align="left">pay_type</td><td align="left">String</td><td align="left">jump</td><td align="left">参考 <a href="#发起支付类型说明">发起支付类型说明</a></td></tr><tr><td align="left">发起支付参数</td><td align="left">pay_info</td><td align="left">String</td><td align="left">weixin://wxpay/bizpayurl?pr=04IPMKM</td><td align="left">根据不同的发起支付类型，返回内容也不一样</td></tr><tr><td align="left">当前时间戳</td><td align="left">timestamp</td><td align="left">String</td><td align="left">1721206072</td><td align="left">10位整数，单位秒</td></tr><tr><td align="left">签名字符串</td><td align="left">sign</td><td align="left">String</td><td align="left"> </td><td align="left">参考签名规则</td></tr><tr><td align="left">签名类型</td><td align="left">sign_type</td><td align="left">String</td><td align="left">RSA</td><td align="left">默认为RSA</td></tr></tbody></table><p>返回示例：</p><pre><code class="json">{
    &quot;code&quot;: 0,
    &quot;trade_no&quot;: &quot;20160806151343349&quot;,
    &quot;pay_type&quot;: &quot;qrcode&quot;,
    &quot;pay_info&quot;: &quot;weixin://wxpay/bizpayurl?pr=04IPMKM&quot;
}</code></pre><pre><code class="json">{
    &quot;code&quot;: 0,
    &quot;trade_no&quot;: &quot;20160806151343351&quot;,
    &quot;pay_type&quot;: &quot;jsapi&quot;,
    &quot;pay_info&quot;: &quot;{\&quot;appId\&quot;:\&quot;wx2421b1c4370ec43b\&quot;,\&quot;timeStamp\&quot;:\&quot;1395712654\&quot;,\&quot;nonceStr\&quot;:\&quot;e61463f8efa94090b1f366cccfbbb444\&quot;,\&quot;package\&quot;:\&quot;prepay_id=up_wx21201855730335ac86f8c43d1889123400\&quot;,\&quot;signType\&quot;:\&quot;RSA\&quot;,\&quot;paySign\&quot;:\&quot;oR9d8PuhnIc+YZ8cBHFCwfgpaK9gd7vaRvkYD7rthRAZ\&quot;}&quot;
}</code></pre><pre><code class="json">{
    &quot;code&quot;: 0,
    &quot;trade_no&quot;: &quot;2024072320222180092&quot;,
    &quot;pay_type&quot;: &quot;scan&quot;,
    &quot;pay_info&quot;: &quot;{\&quot;type\&quot;:\&quot;wxpay\&quot;,\&quot;trade_no\&quot;:\&quot;2024072320222180092\&quot;,\&quot;api_trade_no\&quot;:\&quot;4200002345202407238253501450\&quot;,\&quot;buyer\&quot;:\&quot;o9uAcc6VlZxhcujpKIqQuWWoDQc\&quot;,\&quot;money\&quot;:\&quot;1.00\&quot;}&quot;
}</code></pre><pre><code class="json">{
    &quot;code&quot;: 0,
    &quot;trade_no&quot;: &quot;2024072320222180018&quot;,
    &quot;pay_type&quot;: &quot;wxplugin&quot;,
    &quot;pay_info&quot;: &quot;{\&quot;appId\&quot;:\&quot;wxc237fd59fbb634ae\&quot;,\&quot;supplierId\&quot;:\&quot;123456\&quot;,\&quot;shopId\&quot;:\&quot;123456\&quot;,\&quot;orderId\&quot;:\&quot;2024072320222180092\&quot;}&quot;
}</code></pre><pre><code class="json">{
    &quot;code&quot;: 0,
    &quot;trade_no&quot;: &quot;2024072320222180018&quot;,
    &quot;pay_type&quot;: &quot;wxapp&quot;,
    &quot;pay_info&quot;: &quot;{\&quot;appId\&quot;:\&quot;wxbb48bac536053072\&quot;,\&quot;miniProgramId\&quot;:\&quot;gh_bf9cd8cf50b5\&quot;,\&quot;path\&quot;:\&quot;pages/fromAppPay/index?orderid=123456\&quot;,\&quot;extraData\&quot;:\&quot;\&quot;}&quot;
}</code></pre><h4 id="接口类型列表"><a href="#接口类型列表">接口类型列表</a></h4><table><thead><tr><th align="left">调用值</th><th align="left">描述</th></tr></thead><tbody><tr><td align="left">web</td><td align="left">通用网页支付（会根据device判断，自动<br />返回跳转url/二维码/小程序跳转url等）</td></tr><tr><td align="left">jump</td><td align="left">跳转支付（仅会返回跳转url）</td></tr><tr><td align="left">jsapi</td><td align="left">JSAPI支付（小程序内支付使用，仅返回JSAPI参数，<br />需传入sub_openid和sub_appid参数）</td></tr><tr><td align="left">app</td><td align="left">APP支付（iOS/安卓APP内支付使用，<br />仅返回APP支付参数，或APP拉起微信小程序参数）</td></tr><tr><td align="left">scan</td><td align="left">付款码支付（需传入auth_code参数，<br />支付成功后返回订单信息）</td></tr><tr><td align="left">applet</td><td align="left">小程序支付（微信小程序内使用，<br />返回微信小程序插件参数或跳转小程序参数）</td></tr></tbody></table><h4 id="设备类型列表"><a href="#设备类型列表">设备类型列表</a></h4><table><thead><tr><th align="left">调用值</th><th align="left">描述</th></tr></thead><tbody><tr><td align="left">pc</td><td align="left">电脑浏览器（默认）</td></tr><tr><td align="left">mobile</td><td align="left">手机浏览器</td></tr><tr><td align="left">qq</td><td align="left">手机QQ内浏览器</td></tr><tr><td align="left">wechat</td><td align="left">微信内浏览器</td></tr><tr><td align="left">alipay</td><td align="left">支付宝客户端</td></tr></tbody></table><h4 id="发起支付类型说明"><a href="#发起支付类型说明">发起支付类型说明</a></h4><table><thead><tr><th align="left">发起支付类型</th><th align="left">描述</th></tr></thead><tbody><tr><td align="left">jump</td><td align="left">返回支付跳转url</td></tr><tr><td align="left">html</td><td align="left">返回html代码，用于支付跳转</td></tr><tr><td align="left">qrcode</td><td align="left">返回支付二维码</td></tr><tr><td align="left">urlscheme</td><td align="left">返回微信/支付宝小程序跳转url scheme</td></tr><tr><td align="left">jsapi</td><td align="left">返回用于发起JSAPI支付的参数</td></tr><tr><td align="left">app</td><td align="left">返回用于发起APP支付的参数</td></tr><tr><td align="left">scan</td><td align="left">付款码支付成功,返回支付订单信息</td></tr><tr><td align="left">wxplugin</td><td align="left">返回要拉起的微信小程序插件参数，<br />用于未开通支付能力的小程序发起支付，</td></tr><tr><td align="left">wxapp</td><td align="left">返回要拉起的微信小程序和路径，<br />用于APP内拉起微信小程序支付</td></tr></tbody></table><h4 id="其他说明："><a href="#其他说明：">其他说明：</a></h4><ul><li>代码中需根据接口返回的pay_type值来展示具体的支付页面，例如扫码页面等。如果不懂怎么展示支付页面，可在method传入jump，这样pay_type就只会返回jump，直接跳转支付即可。</li><li>付款码支付可不传支付类型type字段，会根据auth_code的数字自动判断支付类型。</li><li>微信小程序插件支付，不同支付平台拉起支付方式不一样，可联系客服获取对接小程序插件的文档。</li><li>APP拉起微信小程序可参考<a href="https://developers.weixin.qq.com/doc/oplatform/Mobile_App/Launching_a_Mini_Program/Launching_a_Mini_Program.html">微信官方文档</a>。</li></ul>            <div id="mask" style="display:none" onclick="clickMask()"></div>
            </div>
        </div>
    </div>

    <script>
        $(function(){
            var leftBarTimeout = null;
            $('#leftbar').hover(function(e){
                if(null !== leftBarTimeout)
                {
                    clearTimeout(leftBarTimeout);
                    leftBarTimeout = null;
                }
                if(e.type === 'mouseenter')
                {
                    $('.left-show-hide').fadeIn(250);
                }
                else if($('#leftbar').css('left') == '0px')
                {
                    $('.left-show-hide').fadeOut(500);
                }
            });
        });
        function showLeftbar()
        {
            $('#leftbar').css('left', 0);
            $('#body').css('padding-left','');
            $('.left-show-hide > i.layui-icon').html('&#xe603;');
        }
        function hideLeftbar()
        {
            $('#leftbar').css('left', '-240px');
            $('#body').css('padding-left',0);
            $('.left-show-hide > i.layui-icon').html('&#xe602;');
            $('.left-show-hide').fadeIn(250);
        }
        $('.left-show-hide').click(function(){
            if($('#leftbar').css('left') == '0px')
            {
                hideLeftbar();
            }
            else
            {
                showLeftbar();
            }
        })
    </script>
</body>
</html>