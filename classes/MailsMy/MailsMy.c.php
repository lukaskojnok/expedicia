<?php
// require $_SERVER["DOCUMENT_ROOT"] . 'config/common.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require BASE_ROOT . '/vendor/autoload.php';


class MailsMy {

  public $EMAIL_DATA = [];

  private $EMAIL_HOST;
  private $EMAIL_USERNAME;
  private $EMAIL_PASSWORD;
  private $EMAIL_FROM;

  public function __construct() {
    $this->EMAIL_DATA = [];

    // $this->EMAIL_HOST = "smtp.m1.websupport.sk";
    // $this->EMAIL_USERNAME = "test@ciarka.sk";
    // $this->EMAIL_PASSWORD = "M";
    // $this->EMAIL_FROM = "test@ciarka.sk";

    $this->EMAIL_HOST = WEB_EMAIL_SET_HOST;
    $this->EMAIL_USERNAME = WEB_EMAIL_SET_USERNAME;
    $this->EMAIL_PASSWORD = WEB_EMAIL_SET_PASSWORD;
    $this->EMAIL_FROM = WEB_EMAIL_SET_FROM;
  }

  public function sendMail() {

    require_once($_SERVER["DOCUMENT_ROOT"] . "/config/config.php");

    $mail = new PHPMailer(true);

    $body = "
      <!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
      <html>
      <head>
      <title>".WEB_URL."</title>
      <meta http-equiv='content-type' content='text/html; charset=utf-8'>
      </head>
      <body style='font-family: Tahoma; background: #fbfbfb;' bgcolor='#fbfbfb' align='center'>

      <center><table cellpadding='0' cellspacing='0' width='100%'><tr><td style='width: 100%; padding: 10px 5px 20px 5px'>

      <table cellpadding='0' cellspacing='0' style='display: block;'>
      <tr><td width='100%' style='display: block; width: 100%; color: #ffffff; font-size: 20px; font-family: Tahoma; padding: 18px 20px 10px 20px; text-align: left'>
        <img src='".WEB_URL."/img/logo_mail.png' width='364' height='50' alt='Logo'  />
      </td></tr></table>

      <table cellpadding='0' cellspacing='0' width='100%'><tr><td style='background: #ffffff; color: #000000; text-align: left'>

       <table cellpadding='0' cellspacing='0' width='100%'><tr><td style='padding: 15px; font-size: 14px; line-height: 120%; font-family: Tahoma; text-align: left;'>
        <pre style='display:none; white-space: normal; font-family: Tahoma; font-size: 13px; font-weight: bold; text-align: left'>
         ".$this->EMAIL_DATA["subject"]."<br/><br/>
        </pre>

        <pre style='white-space: normal; font-family: Tahoma; font-size: 13px; line-height:140%'>
         ".$this->EMAIL_DATA["text"]."
        </pre>

        <pre style='white-space: normal; font-family: Tahoma; font-size: 13px;'><br/>
         V prípade, že Vám tento e-mail nepatrí, alebo nebol vytvorený správne, prosíme kontaktujte nás na ".WEB_EMAIL_1.".

         <br/><br/><center>TENTO E-MAIL BOL VYGENEROVANÝ AUTOMATICKY SYSTÉMOM, PROSÍME NEODPOVEDAJTE NAŇ.</center></pre>
       <td></tr></table>
      <td></tr></table>


       <table cellpadding='0' cellspacing='0' width='100%'><tr><td style='padding: 10px 10px 10px 10px; background: #f2f2f2; color: #5d5d5d;
       font-size: 13px; font-family: Tahoma; text-align: left;'>
         <a href='".WEB_URL."' style='color: #000000;'>".WEB_URL."</a> |
         <a href='mailto:".WEB_EMAIL_1."' style='color: #000000;'>".WEB_EMAIL_1."</a>
       <td></tr></table>

      <td></tr></table></center>

      </body>
      </html>
    ";



    try {
      //Server settings
      // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
      $mail->isSMTP();                                            //Send using SMTP
      $mail->Host       = $this->EMAIL_HOST;                     //Set the SMTP server to send through
      $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
      $mail->Username   = $this->EMAIL_USERNAME;                     //SMTP username
      $mail->Password   = $this->EMAIL_PASSWORD;                               //SMTP password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
      $mail->Port       = 465;
      $mail->CharSet = "UTF-8";

      //Recipients
      $mail->setFrom( $this->EMAIL_FROM, WEB_URL_NAME );
      $mail->addAddress( $this->EMAIL_DATA["to"], '' );     //Add a recipient

      foreach ( $this->EMAIL_DATA["attachments"] as $key => $value ) {
        $mail->addAttachment( $value["0"], $value["1"] );
      }

      $mail->isHTML(true);
      $mail->Subject =  $this->EMAIL_DATA["subject"];
      $mail->Body = $body;
      // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

      $mail->send();
      // echo 'Message has been sent';
      return true;
    } catch (Exception $e) {
      // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
      echo false;
    }


   }
}


// $emailMy = new MailsMy();
// $emailMy->EMAIL_DATA = [
//   "to" => "lukas.kojnok@gmail.com",
//   "subject" => "ŽivoŠ Čeľko",
//   "text" => "dfasdf asdfdsa fsadfasf",
//   "attachments" => [],
// ];
// $emailMy->sendMail();
