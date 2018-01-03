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
 * Represent SMSes
 */
class Sms extends Pdu
{
  public $serviceType;
  public $source;
  public $destination;
  public $esmClass;
  public $protocolId;
  public $priorityFlag;
  public $registeredDelivery;
  public $dataCoding;
  public $message;
  public $tags;
  // Unused in deliver_sm
  public $scheduleDeliveryTime;
  public $validityPeriod;
  public $smDefaultMsgId;
  public $replaceIfPresentFlag;
  /**
   * Construct a new SMS
   *
   * @param integer       $id
   * @param integer       $status
   * @param integer       $sequence
   * @param string        $body
   * @param string        $serviceType
   * @param SmppAddress   $source
   * @param SmppAddress   $destination
   * @param integer       $esmClass
   * @param integer       $protocolId
   * @param integer       $priorityFlag
   * @param integer       $registeredDelivery
   * @param integer       $dataCoding
   * @param string        $message
   * @param array         $tags
   * @param string        $scheduleDeliveryTime
   * @param string        $validityPeriod
   * @param integer       $smDefaultMsgId
   * @param integer       $replaceIfPresentFlag
   */
  public function __construct($id,
                              $status,
                              $sequence,
                              $body,
                              $serviceType,
                              SmppAddress $source,
                              SmppAddress $destination,
                              $esmClass,
                              $protocolId,
                              $priorityFlag,
                              $registeredDelivery,
                              $dataCoding,
                              $message,
                              $tags,
                              $scheduleDeliveryTime=null,
                              $validityPeriod=null,
                              $smDefaultMsgId=null,
                              $replaceIfPresentFlag=null)
  {
    parent::__construct($id, $status, $sequence, $body);
    $this->serviceType = $serviceType;
    $this->source = $source;
    $this->destination = $destination;
    $this->esmClass = $esmClass;
    $this->protocolId = $protocolId;
    $this->priorityFlag = $priorityFlag;
    $this->registeredDelivery = $registeredDelivery;
    $this->dataCoding = $dataCoding;
    $this->message = $message;
    $this->tags = $tags;
    $this->scheduleDeliveryTime = $scheduleDeliveryTime;
    $this->validityPeriod = $validityPeriod;
    $this->smDefaultMsgId = $smDefaultMsgId;
    $this->replaceIfPresentFlag = $replaceIfPresentFlag;
  }
}