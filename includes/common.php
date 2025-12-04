<?php
//error_reporting(0);
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('VERSION', '3096');
define('DB_VERSION', '2053');
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('PAYPAGE_ROOT', SYSTEM_ROOT.'pages/');
define('TEMPLATE_ROOT', ROOT.'template/');
define('PLUGIN_ROOT', ROOT.'plugins/');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");

if(!isset($nosession) || !$nosession)session_start();

if(!function_exists("is_https")){
	function is_https() {
		if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443){
			return true;
		}elseif(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')){
			return true;
		}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
			return true;
		}elseif(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https'){
			return true;
		}
		return false;
	}
}

$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].'/';

include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

if($is_defend){
	include_once(SYSTEM_ROOT."txprotect.php");
}

require ROOT.'config.php';
define('DBQZ', $dbconfig['dbqz']);

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="/install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="/install/">点此安装</a>';
exit();
}


$CACHE=new \lib\Cache();
$conf=$CACHE->pre_fetch();
define('SYS_KEY', $conf['syskey']);
if(!$conf['localurl'])$conf['localurl'] = $siteurl;
$password_hash='!@#%!s!0';

if ($conf['version'] < DB_VERSION) {
    if (!$install) {
		header('Content-type:text/html;charset=utf-8');
        echo '请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>';
        exit;
    }
}

include_once(SYSTEM_ROOT."functions.php");
include_once(SYSTEM_ROOT."member.php");

require_once SYSTEM_ROOT."vendor/autoload.php";

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="/install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}

if($conf['cdnpublic']==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($conf['cdnpublic']==2){
	$cdnpublic = 'https://s4.zstatic.net/ajax/libs/';
}elseif($conf['cdnpublic']==4){
	$cdnpublic = 'https://cdnjs.snrat.com/ajax/libs/';
}else{
	$cdnpublic = '/assets/vendor/';
}

if(empty($conf['public_key'])){
	$key_pair = generate_key_pair();
	if($key_pair){
		$conf['public_key'] = $key_pair['public_key'];
		$conf['private_key'] = $key_pair['private_key'];
		saveSetting('public_key', $conf['public_key']);
		saveSetting('private_key', $conf['private_key']);
		$CACHE->clear();
		unset($key_pair);
	}
}
?>