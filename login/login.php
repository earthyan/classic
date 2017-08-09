
<?php
//新浪微博
session_start();
include_once( './weibo/config.php' );
include_once( './weibo/saetv2.ex.class.php' );
$o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
$weibo_url = $o->getAuthorizeURL( WB_CALLBACK_URL );
//qq
include_once("./qq/Oauth.class.php");
include_once("./qq/config.php");
$qc = new QC(QQ_APPID,QQ_SECRET,QQ_CALLBACK_URL);
$qq_url = $qc->get_api_url();
//weixin
include_once("./weixin/Weixin.php");
include_once('./weixin/config.php');
$weixin = new Weixin(WX_AKEY,WX_CALLBACK_URL);
$weixin_url = $weixin->get_api_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>第三方登录</title>
</head>
<body>
<p><a href="<?php echo $weibo_url;?>"><img src="./weibo/weibo_login.png" title="点击进入授权页面" alt="点击进入授权页面" border="0" /></a></p>
<p><a href="<?php echo $qq_url;?>"><img src="qq/qq_login.png" title="点击进入授权页面" alt="点击进入授权页面" border="0" /></a></p>
<p><a href="<?php echo $weixin_url?>"><img src="./weixin/123.png" title="点击进入授权页面" alt="点击进入授权页面" border="0" /></a></p>
</body>
</html>
