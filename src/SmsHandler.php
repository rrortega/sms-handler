<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 29/12/17
 * Time: 17:45
 */

namespace rrortega\sms\core;


use rrortega\sms\core\Model\Configurable;
use rrortega\sms\core\Model\Message;
use rrortega\sms\core\Sender\AbstractSender;
use rrortega\sms\core\Sender\SmppSender;

class SmsHandler extends Configurable
{
  /**
   * @var AbstractSender
   */
  protected $sender;

  /**
   * SmsHandler constructor.
   * @param array $config []
   * ex: ["sender"=[
   *        "class"=>"XYZSender",
   *        "conf"=>["host"=>"www.com","user"=>"username","pass"=>"pass"] //custom configuration for driver
   *    ]
   * ]
   */
  public function __construct(array $config = [])
  {
    $this->setConfiguration($config);
    $_class = $this->getConfig('sender.class');
    $_config = $this->getConfig('sender.conf');

    $this->sender = new $_class();
    $this->sender->setConfiguration($_config);
  }

  /**
   * @param $from
   * @param $recipient
   * @param $body
   * @return Message
   */
  public function sendSms($from, $recipient, $body)
  {
    $this->sender->send(
      Message::create()
        ->setFrom($from)
        ->setRecipient($recipient)
        ->setPlainText($body)
    );
    return $this->sender->getLastSentMessage();
  }
}