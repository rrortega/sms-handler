<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 2/01/18
 * Time: 22:26
 */

namespace rrortega\sms\core\Smpp;

/**
 * PDUs class
 */
class Pdu
{
  public $id;
  public $status;
  public $sequence;
  public $body;
  /**
   * Create new generic PDU object
   *
   * @param integer $id
   * @param integer $status
   * @param integer $sequence
   * @param string  $body
   */
  public function __construct($id, $status, $sequence, $body)
  {
    $this->id = $id;
    $this->status = $status;
    $this->sequence = $sequence;
    $this->body = $body;
  }
}
