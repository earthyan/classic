<?php
include_once("connect.php");

$action = $_GET['action'];
$id = 1;
$ip = get_client_ip();//获取ip

if($action=='red'){//红方投票
	vote(1,$id,$ip);
}elseif($action=='blue'){//蓝方投票
	vote(0,$id,$ip);
}else{
	echo jsons($id);
}

function vote($type,$id,$ip){
	$ip_sql=mysql_query("select ip from votes_ip where vid='$id' and ip='$ip'");
	$count=mysql_num_rows($ip_sql);
	if($count==0){//还没有投票
		if($type==1){//红方
			$sql = "update votes set likes=likes+1 where id=".$id;
		}else{//蓝方
			$sql = "update votes set unlikes=unlikes+1 where id=".$id;
		}
		mysql_query($sql);
		
		$sql_in = "insert into votes_ip (vid,ip) values ('$id','$ip')";
		mysql_query($sql_in);
		if(mysql_insert_id()>0){
			echo jsons($id);
		}else{
			$arr['success'] = 0;
			$arr['msg'] = '操作失败，请重试';
			echo json_encode($arr);
		}
	}else{
		$arr['success'] = 0;
		$arr['msg'] = '已经投票过了';
		echo json_encode($arr);
	}
}

function jsons($id){
	$query = mysql_query("select * from votes where id=".$id);
	$row = mysql_fetch_array($query);
	$red = $row['likes'];
	$blue = $row['unlikes'];
	$arr['success']=1;
	$arr['red'] = $red;
	$arr['blue'] = $blue;
	$red_percent = round($red/($red+$blue),3);
	$arr['red_percent'] = $red_percent;
	$arr['blue_percent'] = 1-$red_percent;
	
	return json_encode($arr);
}

//获取用户真实IP
function get_client_ip() {
	if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
		$ip = getenv("HTTP_CLIENT_IP");
	else
		if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
			$ip = getenv("HTTP_X_FORWARDED_FOR");
		else
			if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
				$ip = getenv("REMOTE_ADDR");
			else
				if (isset ($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
					$ip = $_SERVER['REMOTE_ADDR'];
				else
					$ip = "unknown";
	return ($ip);
}
?>