<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 29/12/17
 * Time: 18:09
 */

namespace rrortega\sms\core\Model;


use JsonSerializable;

class Message implements JsonSerializable
{
  const SCHEDULE = "SCHEDULE";
  const PROCESING = "PROCESING";
  const SUCCESS = "SUCCESS";
  const FAILED = "FAILED";

  protected $id;
  protected $remitent;
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
  public function getRemitent()
  {
    return $this->remitent;
  }

  /**
   * @param mixed $remitent
   * @return Message
   */
  public function setRemitent($remitent)
  {
    $this->remitent = $remitent;
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

  /**
   * Specify data which should be serialized to JSON
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed data which can be serialized by <b>json_encode</b>,
   * which is a value of any type other than a resource.
   * @since 5.4.0
   */
  public function jsonSerialize()
  {
    $data = [];
    $rf = new \ReflectionClass($this);
    foreach ($rf->getProperties() as $p) {
      $p->setAccessible(true);
      $v = $p->getValue($this);
      $data[$p->getName()] = $v instanceof \DateTime ? $v->format(
        \DateTime::ATOM
      ) : $v;

    }
    return $data;
  }
}