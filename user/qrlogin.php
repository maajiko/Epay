<?php
error_reporting(0);

session_start();
header('Content-type: application/json');
class qq_qrlogin{
	public function getqrpic(){
		$url='https://ssl.ptlogin2.qq.com/ptqrshow?appid=716027609&e=2&l=M&s=4&d=72&v=4&t=0.5409099'.time().'&daid=383&pt_3rd_aid=101487368';
		$arr=$this->get_curl($url,0,'https://xui.ptlogin2.qq.com/cgi-bin/xlogin?appid=716027609&daid=383&style=33&theme=2&login_text=%E6%8E%88%E6%9D%83%E5%B9%B6%E7%99%BB%E5%BD%95&hide_title_bar=1&hide_border=1&target=self&s_url=https%3A%2F%2Fgraph.qq.com%2Foauth2.0%2Flogin_jump&pt_3rd_aid=101487368&pt_feedback_link=https%3A%2F%2Fsupport.qq.com%2Fproducts%2F77942%3FcustomInfo%3Dwww.qq.com.appid101487368',0,1,0,0,1);
		preg_match('/qrsig=(.*?);/',$arr['header'],$match);
		if($qrsig=$match[1]){
			return array('saveOK'=>0,'qrsig'=>$qrsig,'data'=>base64_encode($arr['body']));
		}else{
			return array('saveOK'=>1,'msg'=>'二维码获取失败');
		}
	}
	public function qrlogin($qrsig){
		if(empty($qrsig))return array('saveOK'=>-1,'msg'=>'qrsig不能为空');
		$url='https://ssl.ptlogin2.qq.com/ptqrlogin?u1=https%3A%2F%2Fgraph.qq.com%2Foauth2.0%2Flogin_jump&ptqrtoken='.$this->getqrtoken($qrsig).'&ptredirect=0&h=1&t=1&g=1&from_ui=1&ptlang=2052&action=0-0-'.time().'0000&js_ver=21020514&js_type=1&login_sig='.$sig.'&pt_uistyle=40&aid=716027609&daid=383&pt_3rd_aid=101487368&';
		$ret = $this->get_curl($url,0,$url,'qrsig='.$qrsig.'; ',1);
		if(preg_match("/ptuiCB\('(.*?)'\)/", $ret, $arr)){
			$r=explode("','",str_replace("', '","','",$arr[1]));
			if($r[0]==0){
				preg_match('/uin=(\d+)&/',$ret,$uin);
				$uin=$uin[1];
				preg_match('/skey=@(.{9});/',$ret,$skey);
				if($uin && $skey[1]){
					$_SESSION['findpwd_qq']=$uin;
					return array('saveOK'=>0,'uin'=>$uin,'nickname'=>$r[5]);
				}else{
					return array('saveOK'=>4,'msg'=>'QQ验证未通过！');
				}
			}elseif($r[0]==65){
				return array('saveOK'=>1,'msg'=>'二维码已失效。');
			}elseif($r[0]==66){
				return array('saveOK'=>2,'msg'=>'二维码未失效。');
			}elseif($r[0]==67){
				return array('saveOK'=>3,'msg'=>'正在验证二维码。');
			}else{
				return array('saveOK'=>6,'msg'=>$r[4]);
			}
		}else{
			return array('saveOK'=>6,'msg'=>$ret);
		}
	}
	private function getqrtoken($qrsig){
        $len = strlen($qrsig);
        $hash = 0;
        for($i = 0; $i < $len; $i++){
            $hash += (($hash << 5) & 2147483647) + ord($qrsig[$i]) & 2147483647;
			$hash &= 2147483647;
        }
        return $hash & 2147483647;
    }
	private function get_curl($url,$post=0,$referer=0,$cookie=0,$header=0,$ua=0,$nobaody=0,$split=0){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept: application/json";
		$httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
		$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
		$httpheader[] = "Connection: close";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		if($post){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		if($header){
			curl_setopt($ch, CURLOPT_HEADER, TRUE);
		}
		if($cookie){
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		if($referer){
			curl_setopt($ch, CURLOPT_REFERER, $referer);
		}
		if($ua){
			curl_setopt($ch, CURLOPT_USERAGENT,$ua);
		}else{
			curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36');
		}
		if($nobaody){
			curl_setopt($ch, CURLOPT_NOBODY,1);

		}
		curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		$ret = curl_exec($ch);
		if ($split) {
			$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($ret, 0, $headerSize);
			$body = substr($ret, $headerSize);
			$ret=array();
			$ret['header']=$header;
			$ret['body']=$body;
		}
		curl_close($ch);
		return $ret;
	}
}

if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false)exit('{"saveOK":-1}');

$login=new qq_qrlogin();
if($_GET['do']=='qrlogin'){
	$array=$login->qrlogin($_GET['qrsig']);
}
if($_GET['do']=='getqrpic'){
	$array=$login->getqrpic();
}
echo json_encode($array);