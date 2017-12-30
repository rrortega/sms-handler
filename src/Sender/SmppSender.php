<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 29/12/17
 * Time: 18:07
 */

namespace rrortega\sms\core\Sender;


use GsmEncoder;
use rrortega\sms\core\Model\Message;
use SMPP;
use SmppAddress;
use SmppClient;
use SocketTransport;

class SmppSender extends AbstractSender
{
  /**
   * @param Message $message
   * @return Message
   */
  protected function sendSMS(Message $message)
  {
    $host = $this->getConfig("host");
    $user = $this->getConfig("user");
    $pass = $this->getConfig("pass");

    // Construct transport and client
    $transport = new SocketTransport([
      $host
    ], 2775);
    $transport->setRecvTimeout(10000);
    $smpp = new SmppClient($transport);

    // Activate binary hex-output of server interaction
    $smpp->debug = true;
    $transport->debug = true;

    // Open the connection
    $transport->open();
    $smpp->bindTransmitter($user, $pass);

// Optional connection specific overrides
//SmppClient::$sms_null_terminate_octetstrings = false;
//SmppClient::$csms_method = SmppClient::CSMS_PAYLOAD;
//SmppClient::$sms_registered_delivery_flag = SMPP::REG_DELIVERY_SMSC_BOTH;


    if (!empty($message->getRemitent()))
      $from = new SmppAddress(
        $message->getRemitent(),
        is_numeric($message->getRemitent())
          ? SMPP::TON_INTERNATIONAL
          : SMPP::TON_ALPHANUMERIC
      );
    else
      $from = new SmppAddress("SMPP" . date("mdhis"), SMPP::TON_ALPHANUMERIC);

    $to = new SmppAddress($message->getRecipient(), SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);

// Send
    // Prepare message
    $encodedMessage = GsmEncoder::utf8_to_gsm0338($message->getPlainText());

    $id = $smpp->sendSMS($from, $to, $encodedMessage);

    if (!empty($id))
      $message->setId($id);

    $message->setStatus(
      !empty($id) ? Message::SUCCESS : Message::FAILED
    );

// Close connection
    $smpp->close();

    return $message;
  }
}