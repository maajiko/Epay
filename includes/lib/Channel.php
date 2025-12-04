<?php
namespace lib;

class Channel {

	static public function get($id, $channelinfo=null){
		global $DB;
		$value=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id' LIMIT 1");
		if(!$value) return null;
		$channel = ['id'=>$value['id'], 'name'=>$value['name'], 'mode'=>$value['mode'], 'type'=>$value['type'], 'plugin'=>$value['plugin'], 'apptype'=>$value['apptype'], 'appwxmp'=>$value['appwxmp'], 'appwxa'=>$value['appwxa'], 'costrate'=>$value['costrate'], 'daytop'=>$value['daytop'], 'daymaxorder'=>$value['daymaxorder']];

		$config = json_decode($value['config'], true);
		if(!empty($channelinfo) && !empty($config)){
			$arr = json_decode($channelinfo, true);
			foreach($config as $configkey => $configrow){
				if($configrow && substr($configrow, 0, 1) == '['){
					$key = substr($configrow,1,-1);
					$config[$configkey] = $arr[$key];
				}
			}
		}
		
		if(!empty($config)){
			$channel = array_merge($channel, $config);
		}
		return $channel;
	}

	static public function getSub($id){
		global $DB;
		$value=$DB->getRow("SELECT A.*,B.info,B.id subid,B.name subname FROM pre_subchannel B INNER JOIN pre_channel A ON B.channel=A.id WHERE B.id='$id'");
		if(!$value) return null;
		$channel = ['id'=>$value['id'], 'subid'=>$value['subid'], 'name'=>$value['name'], 'subname'=>$value['subname'], 'mode'=>$value['mode'], 'type'=>$value['type'], 'plugin'=>$value['plugin'], 'apptype'=>$value['apptype'], 'appwxmp'=>$value['appwxmp'], 'appwxa'=>$value['appwxa'], 'costrate'=>$value['costrate'], 'daytop'=>$value['daytop'], 'daymaxorder'=>$value['daymaxorder']];

		$config = json_decode($value['config'], true);
		if(!empty($value['info']) && !empty($config)){
			$arr = json_decode($value['info'], true);
			foreach($config as $configkey => $configrow){
				if($configrow && substr($configrow, 0, 1) == '['){
					$key = substr($configrow,1,-1);
					$config[$configkey] = $arr[$key];
				}
			}
			if(isset($arr['apptype']) && !empty($arr['apptype'])){
				$channel['apptype'] = $arr['apptype'];
			}
			if(isset($arr['appwxmp']) && $arr['appwxmp']>0){
				$channel['appwxmp'] = $arr['appwxmp'];
				$channel['subappwxmp'] = 1;
			}
			if(isset($arr['appwxa']) && $arr['appwxa']>0){
				$channel['appwxa'] = $arr['appwxa'];
				$channel['subappwxa'] = 1;
			}
		}
		if(!empty($config)){
			$channel = array_merge($channel, $config);
		}
		return $channel;
	}

	static public function getGroup($gid){
		global $DB;
		$group=$DB->getRow("SELECT * FROM pre_group WHERE gid='{$gid}' LIMIT 1");
		if(!$group)$group=$DB->getRow("SELECT * FROM pre_group WHERE gid=0 LIMIT 1");
		$info = json_decode($group['info'],true);

		$rows = $DB->getAll("SELECT * FROM pre_type WHERE status=1 ORDER BY id ASC");
		$paytype = [];
		foreach($rows as $row){
			$paytype[$row['id']] = $row['name'];
		}

		$subchannel_type = [];
		foreach($info as $id=>$row){
			if(!isset($paytype[$id]))continue;
			if($row['channel'] == -2){
				$subchannel_type[] = $paytype[$id];
			}
		}
		$group['subchannel_type'] = $subchannel_type;
		return $group;
	}

	static public function info($id, $gid = 0){
		global $DB;
		$value=$DB->getRow("SELECT id,plugin,type,rate,apptype,mode,paymin,paymax FROM pre_channel WHERE id='$id' LIMIT 1");
		$money_rate = $value['rate'];
		if($gid>0)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid='$gid' LIMIT 1");
		if(!$groupinfo)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid=0 LIMIT 1");
		if($groupinfo){
			$info = json_decode($groupinfo,true);
			$groupinfo = $info[$value['type']];
			if(is_array($groupinfo) && !empty($groupinfo['rate'])){
				$money_rate = $groupinfo['rate'];
			}
		}
		return ['typeid'=>$value['type'], 'plugin'=>$value['plugin'], 'channel'=>$value['id'], 'rate'=>$money_rate, 'apptype'=>$value['apptype'], 'mode'=>$value['mode'], 'paymin'=>$value['paymin'], 'paymax'=>$value['paymax']];
	}

	static public function getWeixin($id){
		global $DB;
		$value=$DB->getRow("SELECT * FROM pre_weixin WHERE id='$id' LIMIT 1");
		return $value;
	}

	// 支付提交处理（输入支付方式名称）
	static public function submit($type, $uid=0, $gid=0, $money=0, $sub_mch_id=0){
		global $DB, $device;
		if($device == 'mobile' || checkmobile()==true){
			$sqls = " AND (device=0 OR device=2)";
		}else{
			$sqls = " AND (device=0 OR device=1)";
		}
		$paytype=$DB->getRow("SELECT id,name,status FROM pre_type WHERE name=:type{$sqls} LIMIT 1", [':type'=>$type]);
		if(!$paytype || $paytype['status']==0)sysmsg('支付方式(type)不存在');
		$typeid = $paytype['id'];
		$typename = $paytype['name'];

		return self::getSubmitInfo($typeid, $typename, $uid, $gid, $money, $sub_mch_id);
	}

	// 支付提交处理2（输入支付方式ID）
	static public function submit2($typeid, $uid=0, $gid=0, $money=0){
		global $DB;
		$paytype=$DB->getRow("SELECT id,name,status FROM pre_type WHERE id='$typeid' LIMIT 1");
		if(!$paytype || $paytype['status']==0)sysmsg('支付方式(type)不存在');
		$typename = $paytype['name'];

		return self::getSubmitInfo($typeid, $typename, $uid, $gid, $money);
	}

	//获取通道、插件、费率信息
	static public function getSubmitInfo($typeid, $typename, $uid, $gid, $money, $sub_mch_id=0){
		global $DB;
		if($gid>0)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid='$gid' LIMIT 1");
		if(!$groupinfo)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid=0 LIMIT 1");
		if($groupinfo){
			$info = json_decode($groupinfo,true);
			$groupinfo = $info[$typeid];
			if(is_array($groupinfo)){
				$channel = $groupinfo['channel'];
				$money_rate = $groupinfo['rate'];
			}
			else{
				$channel = -1;
				$money_rate = null;
			}
			if($channel==0){ //当前商户关闭该通道
				return false;
			}
			elseif($channel==-1){ //随机可用通道
				$rows=$DB->getAll("SELECT id,plugin,status,rate,apptype,mode,paymin,paymax,timestart,timestop FROM pre_channel WHERE type='$typeid' AND status=1 AND daystatus=0 ORDER BY id ASC");
				if(count($rows)>0){
					$newrows = [];
					foreach($rows as $row){
						if($money>0 && !empty($row['paymin']) && $row['paymin']>0 && $money<$row['paymin'])continue;
						if($money>0 && !empty($row['paymax']) && $row['paymax']>0 && $money>$row['paymax'])continue;
						if(!isNullOrEmpty($row['timestart']) && !isNullOrEmpty($row['timestop']) && ($row['timestart']>0 || $row['timestop']>0)){
							$hour = date('H');
							if($row['timestart'] < $row['timestop']){
								if($hour < $row['timestart'] || $hour > $row['timestop']) continue;
							}else{
								if($hour < $row['timestart'] && $hour > $row['timestop']) continue;
							}
						}
						$newrows[] = $row;
					}
					if(count($newrows)>0){
						$row = $newrows[array_rand($newrows)];
					}else{
						$row = $rows[array_rand($rows)];
					}
					if(empty($money_rate))$money_rate = $row['rate'];
					return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$row['id'], 'subchannel'=>0, 'rate'=>$money_rate, 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax']];
				}
			}
			elseif($channel==-4){ //顺序可用通道
				$rows=$DB->getAll("SELECT id,plugin,status,rate,apptype,mode,paymin,paymax,timestart,timestop FROM pre_channel WHERE type='$typeid' AND status=1 AND daystatus=0 ORDER BY id ASC");
				if(count($rows)>0){
					$newrows = [];
					foreach($rows as $row){
						if($money>0 && !empty($row['paymin']) && $row['paymin']>0 && $money<$row['paymin'])continue;
						if($money>0 && !empty($row['paymax']) && $row['paymax']>0 && $money>$row['paymax'])continue;
						if(!isNullOrEmpty($row['timestart']) && !isNullOrEmpty($row['timestop']) && ($row['timestart']>0 || $row['timestop']>0)){
							$hour = date('H');
							if($row['timestart'] < $row['timestop']){
								if($hour < $row['timestart'] || $hour > $row['timestop']) continue;
							}else{
								if($hour < $row['timestart'] && $hour > $row['timestop']) continue;
							}
						}
						$newrows[] = $row;
					}
					if(count($newrows)==0) return false;
					$index = $DB->getColumn("SELECT `index` FROM pre_group WHERE gid='$gid' LIMIT 1");
					$index = $index % count($newrows);
					$row = $newrows[$index];
					$index = ($index + 1) % count($newrows);
					$DB->exec("UPDATE pre_group SET `index`='$index' WHERE gid='$gid'");
					if(empty($money_rate))$money_rate = $row['rate'];
					return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$row['id'], 'subchannel'=>0, 'rate'=>$money_rate, 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax']];
				}
			}
			elseif($channel==-5){ //首个可用通道
				$rows=$DB->getAll("SELECT id,plugin,status,rate,apptype,mode,paymin,paymax,timestart,timestop FROM pre_channel WHERE type='$typeid' AND status=1 AND daystatus=0 ORDER BY id ASC");
				if(count($rows)>0){
					$newrows = [];
					foreach($rows as $row){
						if($money>0 && !empty($row['paymin']) && $row['paymin']>0 && $money<$row['paymin'])continue;
						if($money>0 && !empty($row['paymax']) && $row['paymax']>0 && $money>$row['paymax'])continue;
						if(!isNullOrEmpty($row['timestart']) && !isNullOrEmpty($row['timestop']) && ($row['timestart']>0 || $row['timestop']>0)){
							$hour = date('H');
							if($row['timestart'] < $row['timestop']){
								if($hour < $row['timestart'] || $hour > $row['timestop']) continue;
							}else{
								if($hour < $row['timestart'] && $hour > $row['timestop']) continue;
							}
						}
						$newrows[] = $row;
					}
					if(count($newrows)==0) return false;
					$row = $newrows[0];
					if(empty($money_rate))$money_rate = $row['rate'];
					return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$row['id'], 'subchannel'=>0, 'rate'=>$money_rate, 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax']];
				}
			}
			elseif($channel==-2){ //用户自定义子通道
				$sql = "";
				if($sub_mch_id>0){
					$sql = " AND B.apply_id='$sub_mch_id'";
				}
				$rows=$DB->getAll("SELECT A.id,plugin,A.status,rate,apptype,mode,paymin,paymax,B.id subid,timestart,timestop FROM pre_subchannel B INNER JOIN pre_channel A ON B.channel=A.id WHERE B.uid='$uid' AND A.type='$typeid' AND A.status=1 AND B.status=1 AND daystatus=0{$sql} ORDER BY B.usetime ASC");
				if(count($rows)>0){
					$newrows = [];
					foreach($rows as $row){
						if($money>0 && !empty($row['paymin']) && $row['paymin']>0 && $money<$row['paymin'])continue;
						if($money>0 && !empty($row['paymax']) && $row['paymax']>0 && $money>$row['paymax'])continue;
						if(!isNullOrEmpty($row['timestart']) && !isNullOrEmpty($row['timestop']) && ($row['timestart']>0 || $row['timestop']>0)){
							$hour = date('H');
							if($row['timestart'] < $row['timestop']){
								if($hour < $row['timestart'] || $hour > $row['timestop']) continue;
							}else{
								if($hour < $row['timestart'] && $hour > $row['timestop']) continue;
							}
						}
						$newrows[] = $row;
					}
					if(count($newrows)>0){
						$row = $newrows[0];
					}else{
						$row = $rows[0];
					}
					if(empty($money_rate))$money_rate = $row['rate'];
					$DB->exec("UPDATE pre_subchannel SET usetime=NOW() WHERE id='{$row['subid']}'");
					return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$row['id'], 'subchannel'=>$row['subid'], 'rate'=>$money_rate, 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax']];
				}
			}
			elseif($channel==-3){ //随机可用轮询组
				$rows = $DB->getAll("SELECT * FROM pre_roll WHERE type='$typeid' AND status=1 LIMIT 1");
				if(count($rows)>0){
					$row = $rows[array_rand($rows)];
					$groupinfo['type'] = 'roll';
					$channel = $row['id'];
					goto ROLL_START;
				}
				return false;
			}
			else{
				ROLL_START:
				if($groupinfo['type']=='roll'){ //解析轮询组
					$channel = self::getChannelFromRoll($channel, $money);
					if(!$channel || $channel==0){ //当前轮询组未开启
						return false;
					}
				}
				//获取轮询组对应通道
				$row=$DB->getRow("SELECT plugin,status,rate,apptype,mode,paymin,paymax,timestart,timestop FROM pre_channel WHERE id='$channel' LIMIT 1");
				if(empty($money_rate))$money_rate = $row['rate'];
				return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$channel, 'subchannel'=>0, 'rate'=>$money_rate, 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax'],'timestart'=>$row['timestart'],'timestop'=>$row['timestop']];
			}
		}else{
			//未设置用户组
			$row=$DB->getRow("SELECT id,plugin,status,rate,apptype,mode,paymin,paymax,timestart,timestop FROM pre_channel WHERE type='$typeid' AND status=1 AND daystatus=0 ORDER BY rand() LIMIT 1");
			if($row){
				return ['typeid'=>$typeid, 'typename'=>$typename, 'plugin'=>$row['plugin'], 'channel'=>$row['id'], 'subchannel'=>0, 'rate'=>$row['rate'], 'apptype'=>$row['apptype'], 'mode'=>$row['mode'], 'paymin'=>$row['paymin'], 'paymax'=>$row['paymax'],'timestart'=>$row['timestart'],'timestop'=>$row['timestop']];
			}
		}
		return false;
	}

	// 获取当前商户可用支付方式
	static public function getTypes($uid, $gid=0){
		global $DB;
		if(checkmobile()==true){
			$sqls = " AND (device=0 OR device=2)";
		}else{
			$sqls = " AND (device=0 OR device=1)";
		}
		$rows = $DB->getAll("SELECT * FROM pre_type WHERE status=1{$sqls} ORDER BY id ASC");
		$paytype = [];
		foreach($rows as $row){
			$paytype[$row['id']] = $row;
		}
		if($gid>0)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid='$gid' LIMIT 1");
		if(!$groupinfo)$groupinfo=$DB->getColumn("SELECT info FROM pre_group WHERE gid=0 LIMIT 1");
		if($groupinfo){
			$info = json_decode($groupinfo,true);
			foreach($info as $id=>$row){
				if(!isset($paytype[$id]))continue;
				if($row['channel']==0){
					unset($paytype[$id]);
				}elseif($row['channel']==-1 || $row['channel']==-4 || $row['channel']==-5){
					$channel=$DB->getRow("SELECT rate,status FROM pre_channel WHERE type='$id' AND status=1 LIMIT 1");
					if(!$channel){
						unset($paytype[$id]);
					}elseif(empty($row['rate'])){
						$paytype[$id]['rate']=$channel['rate'];
					}else{
						$paytype[$id]['rate']=$row['rate'];
					}
				}elseif($row['channel']==-2){
					$channel=$DB->getRow("SELECT A.id,A.status,rate FROM pre_subchannel B INNER JOIN pre_channel A ON B.channel=A.id WHERE B.uid='$uid' AND A.type='$id' AND A.status=1 AND B.status=1 LIMIT 1");
					if(!$channel){
						unset($paytype[$id]);
					}elseif(empty($row['rate'])){
						$paytype[$id]['rate']=$channel['rate'];
					}else{
						$paytype[$id]['rate']=$row['rate'];
					}
				}elseif($row['channel']==-3){
					$channel=$DB->getRow("SELECT id,status FROM pre_roll WHERE type='$id' AND status=1 LIMIT 1");
					if(!$channel){
						unset($paytype[$id]);
					}else{
						$paytype[$id]['rate']=$row['rate'];
					}
				}else{
					if($row['type']=='roll'){
						$status=$DB->getColumn("SELECT status FROM pre_roll WHERE id='{$row['channel']}' LIMIT 1");
					}else{
						$status=$DB->getColumn("SELECT status FROM pre_channel WHERE id='{$row['channel']}' LIMIT 1");
						if(empty($row['rate'])) $row['rate'] = $DB->getColumn("SELECT rate FROM pre_channel WHERE id='{$row['channel']}' LIMIT 1");
					}
					if(!$status || $status==0)unset($paytype[$id]);
					else $paytype[$id]['rate']=$row['rate'];
				}
			}
		}else{
			foreach($paytype as $id=>$row){
				$status=$DB->getColumn("SELECT status FROM pre_channel WHERE type='$id' AND status=1 limit 1");
				if(!$status || $status==0)unset($paytype[$id]);
				else{
					$paytype[$id]['rate']=$DB->getColumn("SELECT rate FROM pre_channel WHERE type='$id' AND status=1 limit 1");
				}
			}
		}
		return $paytype;
	}

	//根据轮询组ID获取支付通道ID
	static private function getChannelFromRoll($channel, $money){
		global $DB;
		$row=$DB->getRow("SELECT * FROM pre_roll WHERE id='$channel' LIMIT 1");
		if($row['status']==1){
			$info = self::rollinfo_decode($row['info'],true);

			//先根据支付金额与限额过滤可用支付通道
			$channelids = [];
			foreach($info as $inforow){
				$channelids[] = $inforow['name'];
			}
			$channelids = implode(',',$channelids);
			$rows=$DB->getAll("SELECT id,paymin,paymax,timestart,timestop FROM pre_channel WHERE id IN ($channelids) AND status=1 AND daystatus=0");
			$newids = [];
			foreach($rows as $channelrow){
				if($money>0 && !empty($channelrow['paymin']) && $channelrow['paymin']>0 && $money<$channelrow['paymin'])continue;
				if($money>0 && !empty($channelrow['paymax']) && $channelrow['paymax']>0 && $money>$channelrow['paymax'])continue;
				if(!isNullOrEmpty($channelrow['timestart']) && !isNullOrEmpty($channelrow['timestop']) && ($channelrow['timestart']>0 || $channelrow['timestop']>0)){
					$hour = date('H');
					if($channelrow['timestart'] < $channelrow['timestop']){
						if($hour < $channelrow['timestart'] || $hour > $channelrow['timestop']) continue;
					}else{
						if($hour < $channelrow['timestart'] && $hour > $channelrow['timestop']) continue;
					}
				}
				$newids[] = $channelrow['id'];
			}
			if(count($newids)==0)return false;
			
			$newinfo = [];
			foreach($info as $inforow){
				if(in_array($inforow['name'], $newids))$newinfo[]=$inforow;
			}

			if($row['kind']==2){
				return $newids[0];
			}elseif($row['kind']==1){
				$channel = self::random_weight($newinfo);
			}else{
				$index = $row['index'] % count($newinfo);
				$channel = $newinfo[$index]['name'];
				$index = ($row['index'] + 1) % count($newinfo);
				$DB->exec("UPDATE pre_roll SET `index`='$index' WHERE id='{$row['id']}'");
			}
			return $channel;
		}
		return false;
	}

	//解析轮询组info
	static private function rollinfo_decode($content){
		$result = [];
		$arr = explode(',',$content);
		foreach($arr as $row){
			$a = explode(':',$row);
			$result[] = ['name'=>$a[0], 'weight'=>$a[1]];
		}
		return $result;
	}

	//加权随机
	static private function random_weight($arr){
		$weightSum = 0;
		foreach ($arr as $value) {
			$weightSum += intval($value['weight']);
		}
		if($weightSum<=0)return false;
		$randNum = mt_rand(1, $weightSum);
		foreach ($arr as $v) {
			if ($randNum <= $v['weight']) {
				return $v['name'];
			}
			$randNum -=$v['weight'];
		}
	}

	static private function in_range($range, $money){
		if(empty($range))return true;
		$range = explode(',', $range);
		foreach($range as $row){
			if(strpos($row, '-') !== false){
				$minmax = explode('-', $row);
				if($money >= intval($minmax[0]) && $money <= intval($minmax[1])){
					return true;
				}
			}else{
				if($money == intval($row)){
					return true;
				}
			}
		}
		return false;
	}
}
