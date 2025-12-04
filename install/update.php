<?php
error_reporting(0);
define('DB_VERSION', '2053');
require '../config.php';

@header('Content-Type: text/html; charset=UTF-8');

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
date_default_timezone_set("PRC");
$date = date("Y-m-d");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8");

$version = 0;
if($rs = $db->query("SELECT v FROM pay_config WHERE k='version'")){
	$version = $rs->fetchColumn();
}

if($version==DB_VERSION){
	exit('你的网站已经升级到最新版本了');
}elseif($version<2044){
	$sqls = file_get_contents('update2.sql');
	$sqls .= file_get_contents('update3.sql');
	$sqls=explode(';', $sqls);
	$sqls[]="UPDATE `pre_config` SET `v` = '".DB_VERSION."' where `k` = 'version'";
}elseif($version<DB_VERSION){
	$sqls = file_get_contents('update3.sql');
	$sqls=explode(';', $sqls);
	$sqls[]="UPDATE `pre_config` SET `v` = '".DB_VERSION."' where `k` = 'version'";
}else{
	exit('数据库不兼容，请重新安装！');
}
$sqls[]="UPDATE `pre_cache` SET `v` = '' where `k` = 'config'";
$success=0;$error=0;$errorMsg=null;
foreach ($sqls as $value) {
	$value=trim($value);
	if(empty($value))continue;
	$value = str_replace('pre_',$dbconfig['dbqz'].'_',$value);
	if($db->exec($value)===false){
		$error++;
		$dberror=$db->errorInfo();
		$errorMsg.=$dberror[2]."<br>";
	}else{
		$success++;
	}
}
echo '成功执行SQL语句'.$success.'条！<br/>';
if($errorMsg){
//echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
}
echo '<hr/><a href="/">点此返回首页</a>';
?>