PHP-based SMS handler lib
=============

This is a SMS Handle lib for sending or receiving SMSs through sereral drivers

Using composser
-----
``` 
  composer require rrortega/sms-handler
``` 


 

Requires automaticaly install through composser: 
-----
 
 -  [php-smpp](https://github.com/onlinecity/php-smpp.git).


Basic usage example
-----

To send a SMS you can do:

``` php
<?php

require_once "vendor/autoload.php";

$handler = new \rrortega\sms\core\SmsHandler([
  "sender" => [
    "class" => \rrortega\sms\core\Sender\SmppSender::class,
    "conf" => [
      "host" => "smpp.host.com",
      "user" => "smppuser",
      "pass" => "smppasss"
    ]
  ]
]);

$m = $handler->sendSms("TEST SMPP", 521000000000, "Messaje sent using Smpp Driver");
$m->getStatus(); //SUCCESS or FAILED
$m->getId(); // message id
