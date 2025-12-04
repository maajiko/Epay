<?php
namespace lib;

use Exception;

class Plugin {

	static public function getList(){
		$dir = PLUGIN_ROOT;
		$dirArray[] = NULL;
		if (false != ($handle = opendir($dir))) {
			$i = 0;
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != ".." && strpos($file, ".")===false) {
					$dirArray[$i] = $file;
					$i++;
				}
			}
			closedir($handle);
		}
		return $dirArray;
	}

	static public function getConfig($name){
		$filename = PLUGIN_ROOT.$name.'/'.$name.'_plugin.php';
		$classname = '\\'.$name.'_plugin';
		if(file_exists($filename)){
			include $filename;
			if (class_exists($classname, false) && property_exists($classname, 'info')) {
				return $classname::$info;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	static public function loadForPay($s){
		global $DB,$conf,$order,$channel,$ordername;
		if(preg_match('/^(.[a-zA-Z0-9]+)\/([0-9]+)\/$/',$s, $matchs)){
			$func = $matchs[1];
			$trade_no = $matchs[2];
			
			$order = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE trade_no=:trade_no limit 1", [':trade_no'=>$trade_no]);
			$userrow = $DB->find('user', 'gid,ordername,channelinfo', ['uid'=>$order['uid']]);
            if (!$order) {
				$channelinfo = $userrow?$userrow['channelinfo']:null;
				$channel = \lib\Channel::get($trade_no, $channelinfo);
				if(!$channel) throw new Exception('该订单号不存在，请返回来源地重新发起请求！');
				$trade_no = null;
            }else{
				$channelinfo = $userrow?$userrow['channelinfo']:null;
				$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $channelinfo);
				if(!$channel) throw new Exception('当前支付通道信息不存在');
				$channel['apptype'] = explode(',',$channel['apptype']);
	
				if(!empty($userrow['ordername']))$conf['ordername']=$userrow['ordername'];
				$ordername = !empty($conf['ordername'])?ordername_replace($conf['ordername'],$order['name'],$order['uid'],$trade_no,$order['out_trade_no']):$order['name'];
				$order['plugin'] = $channel['plugin'];
				if(!empty($order['cert_info'])){
					$cert_info = json_decode($order['cert_info'], true);
					$order['cert_no'] = $cert_info['cert_no'];
					$order['cert_name'] = $cert_info['cert_name'];
					$order['min_age'] = $cert_info['min_age'];
				}
			}
			$groupconfig = getGroupConfig($userrow['gid']);
			$conf = array_merge($conf, $groupconfig);

			$result = self::loadClass($channel['plugin'], $func, $trade_no);
			if($func == 'submit') {
				$result['submit'] = true;
			}
			return $result;
		}else{
			throw new Exception('URL参数不符合规范');
		}
	}

	static public function loadForSubmit($plugin, $trade_no, $ismapi=false){
		global $DB,$conf,$order,$channel,$ordername,$userrow;
		if(preg_match('/^(.[a-zA-Z0-9]+)$/',$plugin) && preg_match('/^(.[0-9]+)$/',$trade_no)){
			$func = 'submit';
			if($ismapi) $func = 'mapi';
			
			$channelinfo = $userrow?$userrow['channelinfo']:null;
			$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $channelinfo);
			if(!$channel)throw new Exception('当前支付通道信息不存在');
			$channel['apptype'] = explode(',',$channel['apptype']);
			if(!empty($userrow['ordername']))$conf['ordername']=$userrow['ordername'];
			$ordername = !empty($conf['ordername'])?ordername_replace($conf['ordername'],$order['name'],$order['uid'],$trade_no,$order['out_trade_no']):$order['name'];

			return self::loadClass($plugin, $func, $trade_no);
		}else{
			throw new Exception('URL参数不符合规范');
		}
	}

	static public function loadClass($plugin, $func, $trade_no){
		$filename = PLUGIN_ROOT.$plugin.'/'.$plugin.'_plugin.php';
		$classname = '\\'.$plugin.'_plugin';
        if (file_exists($filename)) {
			if(!defined("IN_PLUGIN")) define("IN_PLUGIN", true);
            define("PAY_ROOT", PLUGIN_ROOT.$plugin.'/');
            define("TRADE_NO", $trade_no);
            include $filename;
            if (class_exists($classname, false) && method_exists($classname, $func)) {
                return $classname::$func();
            } else {
				if($func == 'mapi' && class_exists($classname, false) && method_exists($classname, 'submit')){
					global $siteurl;
					return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
				}else{
					throw new Exception('插件方法不存在:'.$func);
				}
            }
        }else{
			throw new Exception('Pay file not found');
		}
	}

	
	static public function exists($name){
		$filename = PLUGIN_ROOT.$name.'/'.$name.'_plugin.php';
		if(file_exists($filename)){
			return true;
		}else{
			return false;
		}
	}

	static public function isrefund($name){
		$filename = PLUGIN_ROOT.$name.'/'.$name.'_plugin.php';
		$classname = '\\'.$name.'_plugin';
		if(file_exists($filename)){
			include $filename;
			if (class_exists($classname, false) && method_exists($classname, 'refund')) {
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	static public function refund($refund_no, $trade_no, $money, &$message){
		global $order,$channel,$DB;
		if(!preg_match('/^(.[0-9]+)$/',$trade_no))return false;
		$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$order['uid']]));
		if(!$channel){
			$message = '当前支付通道信息不存在';
			return false;
		}
		$order['refund_no'] = $refund_no;
		$order['refundmoney'] = $money;
		$filename = PLUGIN_ROOT.$channel['plugin'].'/'.$channel['plugin'].'_plugin.php';
		$classname = '\\'.$channel['plugin'].'_plugin';
		$func = 'refund';
		if($order['combine'] == 1) $func = 'refund_combine';
		if(file_exists($filename)){
			include $filename;
			if (class_exists($classname, false) && method_exists($classname, $func)) {
				if(!defined("IN_PLUGIN")) define("IN_PLUGIN", true);
				define("PAY_ROOT", PLUGIN_ROOT.$channel['plugin'].'/');
				define("TRADE_NO", $trade_no);
				$result = $classname::$func($order);
				if($result && $result['code']==0){
					return true;
				}else{
					$message = $result['msg'];
					return false;
				}
			}else{
				$message = '当前支付插件不支持API退款';
				return false;
			}
		}else{
			$message = '支付插件不存在';
			return false;
		}
	}

	static public function close($trade_no, &$message){
		global $order,$channel,$DB;
		if(!preg_match('/^(.[0-9]+)$/',$trade_no))return false;
		$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel'], $DB->findColumn('user', 'channelinfo', ['uid'=>$order['uid']]));
		if(!$channel){
			$message = '当前支付通道信息不存在';
			return false;
		}
		$filename = PLUGIN_ROOT.$channel['plugin'].'/'.$channel['plugin'].'_plugin.php';
		$classname = '\\'.$channel['plugin'].'_plugin';
		$func = 'close';
		if($order['combine'] == 1) $func = 'close_combine';
		if(file_exists($filename)){
			include $filename;
			if (class_exists($classname, false) && method_exists($classname, $func)) {
				if(!defined("IN_PLUGIN")) define("IN_PLUGIN", true);
				define("PAY_ROOT", PLUGIN_ROOT.$channel['plugin'].'/');
				define("TRADE_NO", $trade_no);
				$result = $classname::$func($order);
				if($result && $result['code']==0){
					return true;
				}else{
					$message = $result['msg'];
					return false;
				}
			}else{
				$message = '当前支付插件不支持关闭订单';
				return false;
			}
		}else{
			$message = '支付插件不存在';
			return false;
		}
	}

	static public function loadForAdmin($func){
		global $channel;
		$filename = PLUGIN_ROOT.$channel['plugin'].'/'.$channel['plugin'].'_plugin.php';
		$classname = '\\'.$channel['plugin'].'_plugin';
		if(file_exists($filename)){
			include_once $filename;
			if (class_exists($classname, false) && method_exists($classname, $func)) {
				if(!defined("IN_PLUGIN")) define("IN_PLUGIN", true);
				define("PAY_ROOT", PLUGIN_ROOT.$channel['plugin'].'/');
				return $classname::$func($channel);
			}else{
				throw new Exception('插件方法不存在:'.$func);
			}
		}else{
			throw new Exception('支付插件不存在');
		}
	}

	static public function call($func, $channel, $bizParam = null){
		$filename = PLUGIN_ROOT.$channel['plugin'].'/'.$channel['plugin'].'_plugin.php';
		$classname = '\\'.$channel['plugin'].'_plugin';
		if(file_exists($filename)){
			include_once $filename;
			if (class_exists($classname, false) && method_exists($classname, $func)) {
				if($bizParam){
					$result = $classname::$func($channel, $bizParam);
				}else{
					$result = $classname::$func($channel);
				}
				return $result;
			}else{
				return ['code'=>-1, 'msg'=>'插件方法不存在:'.$func];
			}
		}else{
			return ['code'=>-1, 'msg'=>'支付插件不存在'];
		}
	}

	static public function updateAll(){
		global $DB;
		$DB->exec("TRUNCATE TABLE pre_plugin");
		$list = self::getList();
		foreach($list as $name){
			if($config = self::getConfig($name)){
				if($config['name']!=$name)continue;
				$DB->insert('plugin',['name'=>$config['name'], 'showname'=>$config['showname'], 'author'=>$config['author'], 'link'=>$config['link'], 'types'=>implode(',',$config['types']), 'transtypes'=>$config['transtypes']?implode(',',$config['transtypes']):null]);
			}
		}
		return true;
	}

	static public function get($name){
		global $DB;
		$result = $DB->getRow("SELECT * FROM pre_plugin WHERE name=:name", [':name'=>$name]);
		return $result;
	}

	static public function getAll(){
		global $DB;
		$result = $DB->getAll("SELECT * FROM pre_plugin ORDER BY name ASC");
		return $result;
	}
}
