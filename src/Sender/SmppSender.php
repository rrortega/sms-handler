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
use rrortega\sms\core\Model\Message;
use rrortega\sms\core\Smpp\Address;
use rrortega\sms\core\Smpp\Client;
use rrortega\sms\core\Smpp\SMPP;
use rrortega\sms\core\Transport\SocketTransport;

class SmppSender extends AbstractSender
{

  /** @var TransportInterface */
  private $transport;
  /** @var Client */
  private $smpp;


  /**
   * @param Message $message
   * @return Message
   */
  protected function sendSMS(Message $message)
  {
    $this->openSmppConnection();

    if (!empty($message->getRemitent()))
      $from = new SmppAddress(
        $message->getRemitent(),
        is_numeric($message->getRemitent())
          ? SMPP::TON_INTERNATIONAL
          : SMPP::TON_ALPHANUMERIC
      );
    else
      $from = new Address(
        "SMPP" . date("mdhis"),
        SMPP::TON_ALPHANUMERIC
      );

    $to = new Address(
      intval(preg_replace("/\.|\(|\)|-|\s|\+/", "", $message->getRecipient())),
      SMPP::TON_INTERNATIONAL,
      SMPP::NPI_E164
    );
    $encodedMessage = GsmEncoder::utf8_to_gsm0338($message->getPlainText());

    $id = $this->smpp->sendSMS($from, $to, $encodedMessage);

    if (!empty($id))
      $message->setId($id);

    $message->setStatus(
      !empty($id) ? Message::SUCCESS : Message::FAILED
    );

    $this->closeSmppConnection();

    return $message;
  }

  private function openSmppConnection()
  {

    $host = $this->getConfig("host");
    $user = $this->getConfig("user");
    $pass = $this->getConfig("pass");
    $port = $this->getConfig("port");
    $timeout = $this->getConfig("timeout", 10000);
    $debug = $this->getConfig("debug", false);

    $this->transport = new SocketTransport([$host], $port);
    $this->transport->setSendTimeout($timeout);
    $this->smpp = new Client($this->transport);

    $this->transport->debug = $debug;
    $this->smpp->debug = $debug;
    $this->transport->open();
    $this->smpp->bindTransmitter($user, $pass);
  }

  private function closeSmppConnection()
  {
    $this->smpp->close();
    $this->transport->close();
  }
}