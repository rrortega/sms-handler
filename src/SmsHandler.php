<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 29/12/17
 * Time: 17:45
 */

namespace rrortega\sms\core;


use rrortega\sms\core\Model\Configurable;
use rrortega\sms\core\Model\Message;
use rrortega\sms\core\Sender\AbstractSender;

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
    $sender = new $_class();
    $sender->setConfiguration($_config);
    $this->setSender($sender);
  }

  /**
   * @param AbstractSender $sender
   * @return $this
   */
  public function setSender(AbstractSender $sender)
  {
    $this->sender = $sender;
    return $this;
  }

  /**
   * @param $remitent
   * @param $recipient
   * @param $body
   * @return Message
   */
  public function sendSms($remitent, $recipient, $body)
  {
    $this->sender->send(
      Message::create()
        ->setRemitent($remitent)
        ->setRecipient($recipient)
        ->setPlainText($body)
    );
    return $this->sender->getLastSentMessage();
  }
}