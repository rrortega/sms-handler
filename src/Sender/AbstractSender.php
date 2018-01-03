<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 29/12/17
 * Time: 17:48
 */

namespace rrortega\sms\core\Sender;


use rrortega\sms\core\Model\Configurable;
use rrortega\sms\core\Model\Message;

abstract class AbstractSender extends Configurable
{
  /**
   * @var Message
   */
  protected $lastSentMessage = null;

  /**
   * @param Message $message
   * @return Message
   */
  abstract protected function sendSMS(Message $message);

  /**
   * @param Message $message
   * @return bool
   */
  public function send(Message $message)
  {
    $this->lastSentMessage = $this->sendSMS($message);
    return $this->lastSentMessage->getStatus() == Message::SUCCESS;
  }

  /**
   * @return Message
   */
  public function getLastSentMessage()
  {
    return $this->lastSentMessage;
  }

}