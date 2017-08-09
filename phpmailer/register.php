<?php
include_once("PHPMailerAutoload.php");//邮件发送类

$username = stripslashes(trim($_POST['username']));
$con = mysql_connect("localhost","root","123456");
if (!$con)
{
    die('Could not connect: ' . mysql_error());
}

mysql_select_db("test", $con);

$query = mysql_query("select id from t_user where username='$username'");
$num = mysql_num_rows($query);
if($num==1){
    echo '用户名已存在，请换个其他的用户名';
    exit;
}
$password = md5(trim($_POST['password'])); //加密密码
$email = trim($_POST['email']); //邮箱
$regtime = time();

$token = md5($username.$password.$regtime); //创建用于激活识别码
$token_exptime = time()+60*60*24;//过期时间为24小时后

$sql = "insert into `t_user` (`username`,`password`,`email`,`token`,`token_exptime`,`regtime`)
values ('$username','$password','$email','$token','$token_exptime','$regtime')";

mysql_query($sql);

if(mysql_insert_id()){
    $mail = new PHPMailer;
//$mail->SMTPDebug = 3;                               // Enable verbose debug output
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.163.com';                         // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = 'berry1526@163.com';                 // SMTP username
    $mail->Password = 'berry1526';                           // SMTP password
    $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 587;                                    // TCP port to connect to

    $mail->setFrom('berry1526@163.com', 'hainan');
    $mail->addAddress($email);     // Add a recipient
//$mail->addAddress('ellen@example.com');               // Name is optional
//$mail->addReplyTo('info@example.com', 'Information');
//    $mail->addCC('hainan@8btc.com');
//    $mail->addBCC('hainan@8btc.com');

//    $mail->addAttachment('./123456.jpg');         // Add attachments
//$mail->addAttachment('', '');    // Optional name
    $mail->isHTML(true);

    $mail->Subject = "用户帐号激活";//邮件标题
    //邮件主体内容
    $mail->Body = "亲爱的".$username."：<br/>感谢您在我站注册了新帐号。<br/>请点击链接激活您的帐号。<br/>
    <a href='http://www.helloweba.com/demo/register/active.php?verify=".$token."' target=
'_blank'>http://www.helloweba.com/demo/register/active.php?verify=".$token."</a><br/>
    如果以上链接无法点击，请将它复制到你的浏览器地址栏中进入访问，该链接24小时内有效。";
    //发送邮件
    if(!$mail->send()) {
        $msg =  'Message could not be sent.'."\n";
        $msg.= 'Mailer Error: ' . $mail->ErrorInfo;
    } else {
        $msg = '恭喜您，注册成功！<br/>请登录到您的邮箱及时激活您的帐号！';
    }
}
echo $msg;
?>