<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 29/12/17
 * Time: 18:07
 */

namespace rrortega\sms\core\Sender;


use rrortega\sms\core\Encoder\GsmEncoder;
use rrortega\sms\core\Exception\SenderException;
use rrortega\sms\core\Model\Message;
use rrortega\sms\core\Smpp\Address;
use rrortega\sms\core\Smpp\Client;
use rrortega\sms\core\Smpp\SMPP;
use rrortega\sms\core\Transport\SocketTransport;

class TwilioSender extends AbstractSender
{

  /**
   * @param Message $message
   * @return Message
   */
  protected function sendSMS(Message $message)
  {
    $sid = $this->getConfig("sid");
    $token = $this->getConfig("token");
    // resource url & authentication
    $uri = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/SMS/Messages.json';
    $auth = $sid . ':' . $token;
    $fields =
      '&To=' . urlencode($message->getRecipient()) .
      '&From=' . urlencode($message->getRemitent()) .
      '&Body=' . urlencode($message->getPlainText());
    // start cURL
    $res = curl_init();
    // set cURL options
    curl_setopt($res, CURLOPT_URL, $uri);
    curl_setopt($res, CURLOPT_POST, 3); // number of fields
    curl_setopt($res, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($res, CURLOPT_USERPWD, $auth); // authenticate
    curl_setopt($res, CURLOPT_RETURNTRANSFER, true); // don't echo
    // send cURL
    $result = json_decode(curl_exec($res));
    if (empty($result))
      throw new SenderException("Error sending message");

    if (isset($result->code) && !empty($result->code))
      throw new SenderException($result->message);


    $id = isset($result->sid) ? $result->sid : null;

    if (!empty($id))
      $message->setId($id);

    $message->setStatus(
      !empty($id) ? Message::SUCCESS : Message::FAILED
    );

    return $message;
  }

}