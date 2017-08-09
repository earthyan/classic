<?php
require 'PHPMailerAutoload.php';

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
$mail->addAddress('601883074@qq.com', 'jupao');     // Add a recipient
//$mail->addAddress('ellen@example.com');               // Name is optional
//$mail->addReplyTo('info@example.com', 'Information');
$mail->addCC('hainan@8btc.com');
$mail->addBCC('hainan@8btc.com');

$mail->addAttachment('./123456.jpg');         // Add attachments
//$mail->addAttachment('', '');    // Optional name
$mail->isHTML(true);                                  // Set email format to HTML

$mail->Subject = '第6次成功';
$mail->Body    = '又学会一手';
//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

if(!$mail->send()) {
    echo 'Message could not be sent.'."\n";
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}