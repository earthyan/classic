<?php
include_once('connect.php');

$sql = "select * from echarts_map";
$query = mysql_query($sql);
while($row=mysql_fetch_array($query)){
	$arr[] = array(
		'name' => $row['province'],
		'value' => $row['gdp']
	);
}

mysql_close($link);
echo json_encode($arr,JSON_UNESCAPED_UNICODE);
//[{"name":"北京","value":"2.29"},{"name":"新疆","value":"0.94"}]
?>