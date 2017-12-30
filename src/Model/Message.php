<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 29/12/17
 * Time: 18:09
 */

namespace rrortega\sms\core\Model;


class Message
{
  const SCHEDULE = "SCHEDULE";
  const PROCESING = "PROCESING";
  const SUCCESS = "SUCCESS";
  const FAILED = "FAILED";

  protected $id;
  protected $from;
  protected $recipient;
  protected $plainText;
  protected $status = self::SCHEDULE;

  /**
   * Message constructor.
   */
  private function __construct()
  {
    $this->id = gmp_strval(
      gmp_init(
        substr(
          md5(uniqid("", true)),
          0, 16),
        16),
      10);
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param string $id
   * @return Message
   */
  public function setId($id)
  {
    $this->id = $id;
    return $this;
  }

  public function __clone()
  {
    throw new \Exception("Message can not be cloned");
  }

  /**
   * @return Message
   */
  public static function create()
  {
    return new self;
  }

  /**
   * @return mixed
   */
  public function getFrom()
  {
    return $this->from;
  }

  /**
   * @param mixed $from
   * @return Message
   */
  public function setFrom($from)
  {
    $this->from = $from;
    return $this;
  }

  /**
   * @return string
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param string $status
   * @return Message
   */
  public function setStatus($status)
  {
    $this->status = $status;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getRecipient()
  {
    return $this->recipient;
  }

  /**
   * @param mixed $recipient
   * @return Message
   */
  public function setRecipient($recipient)
  {
    $this->recipient = $recipient;
    return $this;
  }

  /**
   * @return string
   */
  public function getPlainText()
  {
    return $this->plainText;
  }

  /**
   * @param mixed $plainText
   * @return Message
   */
  public function setPlainText($plainText)
  {
    $this->plainText = $plainText;
    return $this;
  }

  public function getEncodeMessage($encode = self::UTF8)
  {
    $rf = new \ReflectionClass($this);
    $enc = null;
    foreach ($rf->getConstants() as $k => $v) {
      if (strtoupper($encode) == $v) {
        $enc = strtoupper($encode);
        break;
      }
    }
    if (empty($enc))
      throw new \Exception("Invalid encode $encode");

    return $this->plainText;
  }
}