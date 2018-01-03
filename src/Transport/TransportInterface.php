<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 2/01/18
 * Time: 22:13
 */

namespace rrortega\sms\core\Transport;


interface TransportInterface
{
  /**
   * @return null
   */
  public function open();

  /**
   * @return null
   */
  public function close();

  /**
   * @param integer $length
   *
   * @return null|string
   */
  public function read($length);

  /**
   * @param integer $length
   *
   * @return null|string
   */
  public function readAll($length);

  /**
   * @param mixed $buffer
   * @param integer $length
   */
  public function write($buffer, $length);
}