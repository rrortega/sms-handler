<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
 * Date: 2/01/18
 * Time: 22:28
 */

namespace rrortega\sms\core\Smpp;
use rrortega\sms\core\Exception\SmppException;
use rrortega\sms\core\Transport\TransportInterface;

/**
 * Class for receiving or sending sms through SMPP protocol.
 * This is a reduced implementation of the SMPP protocol, and as such not all features will or ought to be available.
 * The purpose is to create a lightweight and simplified SMPP client.
 *
 * @see http://en.wikipedia.org/wiki/Short_message_peer-to-peer_protocol - SMPP 3.4 protocol specification
 *
 */
class Client
{
  // SMPP bind parameters
  public static $systemType = "WWW";
  public static $interfaceVersion = 0x34;
  public static $addressTon = 0;
  public static $addressNpi = 0;
  public static $addressRange = "";
  // ESME transmitter parameters
  public static $smsServiceType = "";
  public static $smsEsmClass = 0x00;
  public static $smsProtocolId = 0x00;
  public static $smsPriorityFlag = 0x00;
  public static $smsRegisteredDeliveryFlag = 0x00;
  public static $smsReplaceIfPresentFlag = 0x00;
  public static $smsSmDefaultMsgId = 0x00;
  /**
   * SMPP v3.4 says octect string are "not necessarily NULL terminated".
   * Switch to toggle this feature
   * @var boolean
   */
  public static $smsNullTerminateOctetStrings = true;
  /**
   * Use sarMsgRefNum and sar_total_segments with 16 bit tags
   * @var integer
   */
  const CSMS_16BIT_TAGS = 0;
  /**
   * Use message payload for CSMS
   * @var integer
   */
  const CSMS_PAYLOAD = 1;
  /**
   * Embed a UDH in the message with 8-bit reference.
   * @var integer
   */
  const CSMS_8BIT_UDH = 2;
  public static $csmsMethod = self::CSMS_16BIT_TAGS;
  public $debug;
  protected $pduQueue;
  protected $transport;
  protected $debugHandler;
  // Used for reconnect
  protected $mode;
  private $login;
  private $pass;
  protected $sequenceNumber;
  protected $sarMsgRefNum;

  /**
   * Construct the SMPP class
   *
   * @param TransportInterface $transport
   * @param string $debugHandler
   */
  public function __construct(TransportInterface $transport, $debugHandler = null)
  {
    // Internal parameters
    $this->sequenceNumber = 1;
    $this->debug = false;
    $this->pduQueue = array();
    $this->transport = $transport;
    $this->debugHandler = $debugHandler ? $debugHandler : 'error_log';
    $this->mode = null;
  }

  /**
   * Binds the receiver. One object can be bound only as receiver or only as trancmitter.
   *
   * @param string $login - ESME system_id
   * @param string $pass - ESME password
   *
   * @return mixed
   */
  public function bindReceiver($login, $pass)
  {
    if (!$this->transport->isOpen()) {
      return false;
    }
    if ($this->debug) {
      call_user_func($this->debugHandler, 'Binding receiver...');
    }
    $response = $this->_bind($login, $pass, SMPP::BIND_RECEIVER);
    if ($this->debug) {
      call_user_func($this->debugHandler, "Binding status  : " . $response->status);
    }
    $this->mode = 'receiver';
    $this->login = $login;
    $this->pass = $pass;
    return null;
  }

  /**
   * Binds the transmitter. One object can be bound only as receiver or only as trancmitter.
   *
   * @param string $login - ESME system_id
   * @param string $pass - ESME password
   *
   * @return mixed
   */
  public function bindTransmitter($login, $pass)
  {
    if (!$this->transport->isOpen()) {
      return false;
    }
    if ($this->debug) {
      call_user_func($this->debugHandler, 'Binding transmitter...');
    }
    $response = $this->_bind($login, $pass, SMPP::BIND_TRANSMITTER);
    if ($this->debug) {
      call_user_func($this->debugHandler, "Binding status  : " . $response->status);
    }
    $this->mode = 'transmitter';
    $this->login = $login;
    $this->pass = $pass;
    return null;
  }

  /**
   * Closes the session on the SMSC server.
   */
  public function close()
  {
    if (!$this->transport->isOpen()) {
      return;
    }
    if ($this->debug) {
      call_user_func($this->debugHandler, 'Unbinding...');
    }
    $response = $this->sendCommand(SMPP::UNBIND, "");
    if ($this->debug) {
      call_user_func($this->debugHandler, "Unbind status   : " . $response->status);
    }
    $this->transport->close();
  }

  /**
   * Parse a timestring as formatted by SMPP v3.4 section 7.1.
   * Returns an unix timestamp if $newDates is false or DateTime/DateInterval is missing,
   * otherwise an object of either DateTime or DateInterval is returned.
   *
   * @param string $input
   * @param boolean $newDates
   *
   * @return mixed
   */
  public function parseSmppTime($input, $newDates = true)
  {
    // Check for support for new date classes
    if (!class_exists('DateTime') || !class_exists('DateInterval')) {
      $newDates = false;
    }
    $numMatch = preg_match(
      '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1})(\\d{2})([R+-])$/',
      $input,
      $matches);
    if (!$numMatch) {
      return null;
    }
    list($whole, $y, $m, $d, $h, $i, $s, $t, $n, $p) = $matches;
    // Use strtotime to convert relative time into a unix timestamp
    if ($p == 'R') {
      if ($newDates) {
        $spec = "P";
        if ($y) {
          $spec .= $y . 'Y';
        }
        if ($m) {
          $spec .= $m . 'M';
        }
        if ($d) {
          $spec .= $d . 'D';
        }
        if ($h || $i || $s) {
          $spec .= 'T';
        }
        if ($h) {
          $spec .= $h . 'H';
        }
        if ($i) {
          $spec .= $i . 'M';
        }
        if ($s) {
          $spec .= $s . 'S';
        }
        return new \DateInterval($spec);
      } else {
        return strtotime("+$y year +$m month +$d day +$h hour +$i minute $s +second");
      }
    } else {
      $offsetHours = floor($n / 4);
      $offsetMinutes = ($n % 4) * 15;
      $time = sprintf("20%02s-%02s-%02sT%02s:%02s:%02s%s%02s:%02s",
        $y, $m, $d, $h, $i, $s, $p, $offsetHours, $offsetMinutes); // Not Y3K safe
      if ($newDates) {
        return new \DateTime($time);
      } else {
        return strtotime($time);
      }
    }
  }

  /**
   * Query the SMSC about current state/status of a previous sent SMS.
   * You must specify the SMSC assigned message id and source of the sent SMS.
   * Returns an associative array with elements: message_id, final_date, message_state and error_code.
   *     message_state would be one of the SMPP::STATE_* constants. (SMPP v3.4 section 5.2.28)
   *     error_code depends on the telco network, so could be anything.
   *
   * @param string $messageid
   * @param Address $source
   *
   * @return array
   */
  public function queryStatus($messageid, Address $source)
  {
    $pduBody = pack('a' . (strlen($messageid) + 1) . 'cca' . (strlen($source->value) + 1), $messageid, $source->ton, $source->npi, $source->value);
    $reply = $this->sendCommand(SMPP::QUERY_SM, $pduBody);
    if (!$reply || $reply->status != SMPP::ESME_ROK) {
      return null;
    }
    // Parse reply
    $posId = strpos($reply->body, "\0", 0);
    $posDate = strpos($reply->body, "\0", $posId + 1);
    $data = array();
    $data['message_id'] = substr($reply->body, 0, $posId);
    $data['final_date'] = substr($reply->body, $posId, $posDate - $posId);
    $data['final_date'] = $data['final_date'] ? $this->parseSmppTime(trim($data['final_date'])) : null;
    $status = unpack("cmessage_state/cerror_code", substr($reply->body, $posDate + 1));
    return array_merge($data, $status);
  }

  /**
   * Read one SMS from SMSC. Can be executed only after bindReceiver() call.
   * This method bloks. Method returns on socket timeout or enquire_link signal from SMSC.
   *
   * @return array|false associative array or false when reading failed or no more sms.
   */
  public function readSMS()
  {
    $commandId = SMPP::DELIVER_SM;
    // Check the queue
    $ql = count($this->pduQueue);
    for ($i = 0; $i < $ql; $i++) {
      $pdu = $this->pduQueue[$i];
      if ($pdu->id == $commandId) {
        //remove response
        array_splice($this->pduQueue, $i, 1);
        return $this->parseSMS($pdu);
      }
    }
    // Read pdu
    do {
      $pdu = $this->readPDU();
      if ($pdu === false) {
        return false; // TSocket v. 0.6.0+ returns false on timeout
      }
      //check for enquire link command
      if ($pdu->id == SMPP::ENQUIRE_LINK) {
        $response = new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00");
        $this->sendPDU($response);
      } else {
        if ($pdu->id != $commandId) {
          // if this is not the correct PDU add to queue
          array_push($this->pduQueue, $pdu);
        }
      }
    } while ($pdu && $pdu->id != $commandId);
    if ($pdu) {
      return $this->parseSMS($pdu);
    }
    return false;
  }

  /**
   * Send one SMS to SMSC. Can be executed only after bindTransmitter() call.
   * $message is always in octets regardless of the data encoding.
   * For correct handling of Concatenated SMS, message must be encoded with GSM 03.38 (data_coding 0x00) or UCS-2BE (0x08).
   * Concatenated SMS'es uses 16-bit reference numbers, which gives 152 GSM 03.38 chars or 66 UCS-2BE chars per CSMS.
   *
   * @param Address $from From
   * @param Address $to To
   * @param string $message Message
   * @param array $tags (optional)
   * @param integer $dataCoding (optional)
   * @param integer $priority (optional)
   * @param string $scheduleDeliveryTime (optional)
   * @param string $validityPeriod (optional)
   *
   * @return string message id
   */
  public function sendSMS(Address $from,
                          Address $to,
                          $message,
                          $tags = null,
                          $dataCoding = SMPP::DATA_CODING_DEFAULT,
                          $priority = 0x00,
                          $scheduleDeliveryTime = null,
                          $validityPeriod = null)
  {
    if ($this->debug) {
      call_user_func($this->debugHandler, "Sending SMS...");
      call_user_func($this->debugHandler, "From: " . $from->value);
      call_user_func($this->debugHandler, "To: " . $to->value);
      call_user_func($this->debugHandler, "Message: " . $message);
    }
    $msgLength = strlen($message);
    if ($msgLength > 160 && $dataCoding != SMPP::DATA_CODING_UCS2 && $dataCoding != SMPP::DATA_CODING_DEFAULT) {
      return false;
    }
    $csmsSplit = 0;
    switch ($dataCoding) {
      case SMPP::DATA_CODING_UCS2:
        $singleSmsOctetLimit = 140; // in octets, 70 UCS-2 chars
        $csmsSplit = 132; // There are 133 octets available, but this would split the UCS the middle so use 132 instead
        break;
      case SMPP::DATA_CODING_DEFAULT:
        $singleSmsOctetLimit = 160; // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
        $csmsSplit = 152; // send 152 chars in each SMS since, we will use 16-bit CSMS ids (SMSC will format data)
        break;
      default:
        $singleSmsOctetLimit = 254; // From SMPP standard
        break;
    }
    // Figure out if we need to do CSMS, since it will affect our PDU
    if ($msgLength > $singleSmsOctetLimit) {
      $doCsms = true;
      if (!self::$csmsMethod != self::CSMS_PAYLOAD) {
        $parts = $this->splitMessageString($message, $csmsSplit, $dataCoding);
        $shortMessage = reset($parts);
        $csmsReference = $this->getCsmsReference();
      }
    } else {
      $shortMessage = $message;
      $doCsms = false;
    }
    // Deal with CSMS
    if ($doCsms) {
      if (self::$csmsMethod == self::CSMS_PAYLOAD) {
        $payload = new Tag(Tag::MESSAGE_PAYLOAD, $message, $msgLength);
        return $this->submit_sm(
          $from,
          $to,
          null,
          (empty($tags) ? array($payload) : array_merge($tags, $payload)),
          $dataCoding,
          $priority,
          $scheduleDeliveryTime,
          $validityPeriod
        );
      } else {
        if (self::$csmsMethod == self::CSMS_8BIT_UDH) {
          $seqnum = 1;
          foreach ($parts as $part) {
            $udh = pack('cccccc', 5, 0, 3, substr($csmsReference, 1, 1), count($parts), $seqnum);
            $res = $this->submit_sm(
              $from,
              $to,
              $udh . $part,
              $tags,
              $dataCoding,
              $priority,
              $scheduleDeliveryTime,
              $validityPeriod,
              (self::$smsEsmClass | 0x40)
            );
            $seqnum++;
          }
          return $res;
        } else {
          $sarMsgRefNum = new Tag(Tag::SAR_MSG_REF_NUM, $csmsReference, 2, 'n');
          $sarTotalSegments = new Tag(Tag::SAR_TOTAL_SEGMENTS, count($parts), 1, 'c');
          $seqnum = 1;
          foreach ($parts as $part) {
            $sartags = array($sarMsgRefNum, $sarTotalSegments, new Tag(Tag::SAR_SEGMENT_SEQNUM, $seqnum, 1, 'c'));
            $res = $this->submit_sm(
              $from,
              $to,
              $part,
              (empty($tags) ? $sartags : array_merge($tags, $sartags)),
              $dataCoding,
              $priority,
              $scheduleDeliveryTime,
              $validityPeriod
            );
            $seqnum++;
          }
          return $res;
        }
      }
    }
    return $this->submit_sm($from, $to, $shortMessage, $tags, $dataCoding);
  }

  /**
   * Perform the actual submit_sm call to send SMS.
   * Implemented as a protected method to allow automatic sms concatenation.
   * Tags must be an array of already packed and encoded TLV-params.
   *
   * @param Address $source
   * @param Address $destination
   * @param string $shortMessage
   * @param array $tags
   * @param integer $dataCoding
   * @param integer $priority
   * @param string $scheduleDeliveryTime
   * @param string $validityPeriod
   * @param string $esmClass
   *
   * @return string message id
   */
  protected function submit_sm(Address $source,
                               Address $destination,
                               $shortMessage = null,
                               $tags = null,
                               $dataCoding = SMPP::DATA_CODING_DEFAULT,
                               $priority = 0x00,
                               $scheduleDeliveryTime = null,
                               $validityPeriod = null,
                               $esmClass = null)
  {
    if (is_null($esmClass)) {
      $esmClass = self::$smsEsmClass;
    }
    // Construct PDU with mandatory fields
    $pdu = pack('a1cca' . (strlen($source->value) + 1) . 'cca' . (strlen($destination->value) + 1) . 'ccc' . ($scheduleDeliveryTime ? 'a16x' : 'a1') . ($validityPeriod ? 'a16x' : 'a1') . 'ccccca' . (strlen($shortMessage) + (self::$smsNullTerminateOctetStrings ? 1 : 0)),
      self::$smsServiceType,
      $source->ton,
      $source->npi,
      $source->value,
      $destination->ton,
      $destination->npi,
      $destination->value,
      $esmClass,
      self::$smsProtocolId,
      $priority,
      $scheduleDeliveryTime,
      $validityPeriod,
      self::$smsRegisteredDeliveryFlag,
      self::$smsReplaceIfPresentFlag,
      $dataCoding,
      self::$smsSmDefaultMsgId,
      strlen($shortMessage), //sm_length
      $shortMessage//short_message
    );
    // Add any tags
    if (!empty($tags)) {
      /** @var Tag $tag */
      foreach ($tags as $tag) {
        $pdu .= $tag->getBinary();
      }
    }
    $response = $this->sendCommand(SMPP::SUBMIT_SM, $pdu);
    $body = unpack("a*msgid", $response->body);
    return $body['msgid'];
  }

  /**
   * Get a CSMS reference number for sarMsgRefNum.
   * Initializes with a random value, and then returns the number in sequence with each call.
   * @return int
   */
  protected function getCsmsReference()
  {
    $limit = (self::$csmsMethod == self::CSMS_8BIT_UDH) ? 255 : 65535;
    if (!isset($this->sarMsgRefNum)) {
      $this->sarMsgRefNum = mt_rand(0, $limit);
    }
    $this->sarMsgRefNum++;
    if ($this->sarMsgRefNum > $limit) {
      $this->sarMsgRefNum = 0;
    }
    return $this->sarMsgRefNum;
  }

  /**
   * Split a message into multiple parts, taking the encoding into account.
   * A character represented by an GSM 03.38 escape-sequence shall not be split in the middle.
   * Uses str_split if at all possible, and will examine all split points for escape chars if it's required.
   *
   * @param string $message
   * @param integer $split
   * @param integer $dataCoding
   *
   * @return array|string
   */
  protected function splitMessageString($message, $split, $dataCoding = SMPP::DATA_CODING_DEFAULT)
  {
    switch ($dataCoding) {
      case SMPP::DATA_CODING_DEFAULT:
        $msgLength = strlen($message);
        // Do we need to do php based split?
        $numParts = floor($msgLength / $split);
        if ($msgLength % $split == 0) {
          $numParts--;
        }
        $slowSplit = false;
        for ($i = 1; $i <= $numParts; $i++) {
          if ($message[$i * $split - 1] == "\x1B") {
            $slowSplit = true;
            break;
          };
        }
        if (!$slowSplit) {
          return str_split($message, $split);
        }
        // Split the message char-by-char
        $parts = array();
        $part = null;
        $n = 0;
        for ($i = 0; $i < $msgLength; $i++) {
          $c = $message[$i];
          // reset on $split or if last char is a GSM 03.38 escape char
          if ($n == $split || ($n == ($split - 1) && $c == "\x1B")) {
            $parts[] = $part;
            $n = 0;
            $part = null;
          }
          $part .= $c;
        }
        $parts[] = $part;
        return $parts;
      case SMPP::DATA_CODING_UCS2: // UCS2-BE can just use str_split since we send 132 octets per message, which gives a fine split using UCS2
      default:
        return str_split($message, $split);
    }
  }

  /**
   * Binds the socket and opens the session on SMSC
   *
   * @param string $login - ESME system_id
   * @param string $pass - ESME password
   * @param mixed $commandId Command ID
   *
   * @return Pdu
   *
   * @throws SmppException
   */
  protected function _bind($login, $pass, $commandId)
  {
    // Make PDU body
    $pduBody = pack(
      'a' . (strlen($login) + 1) .
      'a' . (strlen($pass) + 1) .
      'a' . (strlen(self::$systemType) + 1) .
      'CCCa' . (strlen(self::$addressRange) + 1),
      $login, $pass, self::$systemType,
      self::$interfaceVersion, self::$addressTon,
      self::$addressNpi, self::$addressRange
    );
    $response = $this->sendCommand($commandId, $pduBody);
    if ($response->status != SMPP::ESME_ROK) {
      throw new SmppException(SMPP::getStatusMessage($response->status), $response->status);
    }
    return $response;
  }

  /**
   * Parse received PDU from SMSC.
   *
   * @param Pdu $pdu - received PDU from SMSC.
   *
   * @return array parsed PDU
   *
   * @throws SmppException
   * @throws \InvalidArgumentException
   */
  protected function parseSMS(Pdu $pdu)
  {
    // Check command id
    if ($pdu->id != SMPP::DELIVER_SM) {
      throw new \InvalidArgumentException('PDU is not an received SMS');
    }
    // Unpack PDU
    $ar = unpack("C*", $pdu->body);
    // Read mandatory params
    $serviceType = $this->getString($ar, 6, true);
    $sourceAddressTon = next($ar);
    $sourceAddressNpi = next($ar);
    $sourceAddress = $this->getString($ar, 21);
    $source = new Address($sourceAddress, $sourceAddressTon, $sourceAddressNpi);
    $destinationAddressTon = next($ar);
    $destinationAddressNpi = next($ar);
    $destinationAddress = $this->getString($ar, 21);
    $destination = new Address($destinationAddress, $destinationAddressTon, $destinationAddressNpi);
    $esmClass = next($ar);
    $protocolId = next($ar);
    $priorityFlag = next($ar);
    next($ar); // schedule_delivery_time
    next($ar); // validity_period
    $registeredDelivery = next($ar);
    next($ar); // replace_if_present_flag
    $dataCoding = next($ar);
    next($ar); // sm_default_msg_id
    $smLength = next($ar);
    $message = $this->getString($ar, $smLength);
    // Check for optional params, and parse them
    if (current($ar) !== false) {
      $tags = array();
      do {
        $tag = $this->parseTag($ar);
        if ($tag !== false) {
          $tags[] = $tag;
        }
      } while (current($ar) !== false);
    } else {
      $tags = null;
    }
    if (($esmClass & SMPP::ESM_DELIVER_SMSC_RECEIPT) != 0) {
      $sms = new DeliveryReceipt($pdu->id, $pdu->status, $pdu->sequence, $pdu->body, $serviceType, $source, $destination, $esmClass, $protocolId, $priorityFlag, $registeredDelivery, $dataCoding, $message, $tags);
      $sms->parseDeliveryReceipt();
    } else {
      $sms = new Sms($pdu->id, $pdu->status, $pdu->sequence, $pdu->body, $serviceType, $source, $destination, $esmClass, $protocolId, $priorityFlag, $registeredDelivery, $dataCoding, $message, $tags);
    }
    if ($this->debug) {
      call_user_func($this->debugHandler, "Received sms:\n" . print_r($sms, true));
    }
    // Send response of recieving sms
    $response = new Pdu(SMPP::DELIVER_SM_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00");
    $this->sendPDU($response);
    return $sms;
  }

  /**
   * Send the enquire link command.
   * @return Pdu
   */
  public function enquireLink()
  {
    $response = $this->sendCommand(SMPP::ENQUIRE_LINK, null);
    return $response;
  }

  /**
   * Respond to any enquire link we might have waiting.
   * If will check the queue first and respond to any enquire links we have there.
   * Then it will move on to the transport, and if the first PDU is enquire link respond,
   * otherwise add it to the queue and return.
   *
   */
  public function respondEnquireLink()
  {
    // Check the queue first
    $ql = count($this->pduQueue);
    for ($i = 0; $i < $ql; $i++) {
      $pdu = $this->pduQueue[$i];
      if ($pdu->id == SMPP::ENQUIRE_LINK) {
        //remove response
        array_splice($this->pduQueue, $i, 1);
        $this->sendPDU(new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
      }
    }
    // Check the transport for data
    if ($this->transport->hasData()) {
      $pdu = $this->readPDU();
      if ($pdu->id == SMPP::ENQUIRE_LINK) {
        $this->sendPDU(new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
      } else if ($pdu) {
        array_push($this->pduQueue, $pdu);
      }
    }
  }

  /**
   * Reconnect to SMSC.
   * This is mostly to deal with the situation were we run out of sequence numbers
   */
  protected function reconnect()
  {
    $this->close();
    sleep(1);
    $this->transport->open();
    $this->sequenceNumber = 1;
    if ($this->mode == 'receiver') {
      $this->bindReceiver($this->login, $this->pass);
    } else {
      $this->bindTransmitter($this->login, $this->pass);
    }
  }

  /**
   * Sends the PDU command to the SMSC and waits for response.
   *
   * @param integer $id - command ID
   * @param string $pduBody - PDU body
   *
   * @return Pdu
   *
   * @throws SmppException
   */
  protected function sendCommand($id, $pduBody)
  {
    if (!$this->transport->isOpen()) {
      return false;
    }
    $pdu = new Pdu($id, 0, $this->sequenceNumber, $pduBody);
    $this->sendPDU($pdu);
    $response = $this->readPDU_resp($this->sequenceNumber, $pdu->id);
    if ($response === false) {
      throw new SmppException('Failed to read reply to command: 0x' . dechex($id));
    }
    if ($response->status != SMPP::ESME_ROK) {
      throw new SmppException(SMPP::getStatusMessage($response->status), $response->status);
    }
    $this->sequenceNumber++;
    // Reached max sequence number, spec does not state what happens now, so we re-connect
    if ($this->sequenceNumber >= 0x7FFFFFFF) {
      $this->reconnect();
    }
    return $response;
  }

  /**
   * Prepares and sends PDU to SMSC.
   * @param Pdu $pdu
   */
  protected function sendPDU(Pdu $pdu)
  {
    $length = strlen($pdu->body) + 16;
    $header = pack("NNNN", $length, $pdu->id, $pdu->status, $pdu->sequence);
    if ($this->debug) {
      call_user_func($this->debugHandler, "Send PDU         : $length bytes");
      call_user_func($this->debugHandler, ' ' . chunk_split(bin2hex($header . $pdu->body), 2, " "));
      call_user_func($this->debugHandler, ' ' . chunk_split(bin2hex($header . $pdu->body), 2, " "));
      call_user_func($this->debugHandler, ' commandId      : 0x' . dechex($pdu->id));
      call_user_func($this->debugHandler, ' sequence number : ' . $pdu->sequence);
    }
    $this->transport->write($header . $pdu->body, $length);
  }

  /**
   * Waits for SMSC response on specific PDU.
   * If a GENERIC_NACK with a matching sequence number, or null sequence is received instead it's also accepted.
   * Some SMPP servers, ie. logica returns GENERIC_NACK on errors.
   *
   * @param integer $seqNumber - PDU sequence number
   * @param integer $commandId - PDU command ID
   *
   * @return Pdu
   * @throws SmppException
   */
  protected function readPDU_resp($seqNumber, $commandId)
  {
    // Get response cmd id from command id
    $commandId = $commandId | SMPP::GENERIC_NACK;
    // Check the queue first
    $ql = count($this->pduQueue);
    for ($i = 0; $i < $ql; $i++) {
      $pdu = $this->pduQueue[$i];
      if (
        ($pdu->sequence == $seqNumber && ($pdu->id == $commandId || $pdu->id == SMPP::GENERIC_NACK)) ||
        ($pdu->sequence == null && $pdu->id == SMPP::GENERIC_NACK)
      ) {
        // remove response pdu from queue
        array_splice($this->pduQueue, $i, 1);
        return $pdu;
      }
    }
    // Read PDUs until the one we are looking for shows up, or a generic nack pdu with matching sequence or null sequence
    do {
      $pdu = $this->readPDU();
      if ($pdu) {
        if ($pdu->sequence == $seqNumber && ($pdu->id == $commandId || $pdu->id == SMPP::GENERIC_NACK)) {
          return $pdu;
        }
        if ($pdu->sequence == null && $pdu->id == SMPP::GENERIC_NACK) {
          return $pdu;
        }
        array_push($this->pduQueue, $pdu); // unknown PDU push to queue
      }
    } while ($pdu);
    return false;
  }

  /**
   * Reads incoming PDU from SMSC.
   *
   * @return Pdu
   * @throws \RuntimeException
   */
  protected function readPDU()
  {
    // Read PDU length
    $bufLength = $this->transport->read(4);
    if (!$bufLength) {
      return false;
    }
    $length = unpack("Nlength", $bufLength);
    $length = $length['length'];
    // Read PDU headers
    $bufHeaders = $this->transport->read(12);
    if (!$bufHeaders) {
      return false;
    }
    $headers = unpack("NcommandId/NcommandStatus/NsequenceNumber", $bufHeaders);
    // Read PDU body
    if ($length - 16 > 0) {
      $body = $this->transport->readAll($length - 16);
      if (!$body) {
        throw new RuntimeException('Could not read PDU body');
      }
    } else {
      $body = null;
    }
    if ($this->debug) {
      call_user_func($this->debugHandler, "Read PDU         : $length bytes");
      call_user_func($this->debugHandler, ' ' . chunk_split(bin2hex($bufLength . $bufHeaders . $body), 2, " "));
      call_user_func($this->debugHandler, " command id      : 0x" . dechex($headers['commandId']));
      call_user_func($this->debugHandler, " command status  : 0x" . dechex($headers['commandStatus']) . " " . SMPP::getStatusMessage($headers['commandStatus']));
      call_user_func($this->debugHandler, ' sequence number : ' . $headers['sequenceNumber']);
    }
    return new Pdu($headers['commandId'], $headers['commandStatus'], $headers['sequenceNumber'], $body);
  }

  /**
   * Reads C style null padded string from the char array.
   * Reads until $maxlen or null byte.
   *
   * @param array &$ar - input array
   * @param integer $maxlen - maximum length to read.
   * @param boolean $firstRead - is this the first bytes read from array?
   *
   * @return string.
   */
  protected function getString(&$ar, $maxlen = 255, $firstRead = false)
  {
    $s = "";
    $i = 0;
    do {
      $c = ($firstRead && $i == 0) ? current($ar) : next($ar);
      if ($c != 0) {
        $s .= chr($c);
      }
      $i++;
    } while ($i < $maxlen && $c != 0);
    return $s;
  }

  /**
   * Read a specific number of octets from the char array.
   * Does not stop at null byte
   *
   * @param array &$ar - input array
   * @param integer $length - length
   *
   * @return string
   */
  protected function getOctets(&$ar, $length)
  {
    $s = "";
    for ($i = 0; $i < $length; $i++) {
      $c = next($ar);
      if ($c === false) {
        return $s;
      }
      $s .= chr($c);
    }
    return $s;
  }

  protected function parseTag(&$ar)
  {
    $unpackedData = unpack('nid/nlength', pack("C2C2", next($ar), next($ar), next($ar), next($ar)));
    if (!$unpackedData) {
      throw new \InvalidArgumentException('Could not read tag data');
    }
    // Sometimes SMSC return an extra null byte at the end
    if ($unpackedData['length'] == 0 && $unpackedData['id'] == 0) {
      return false;
    }
    $value = $this->getOctets($ar, $unpackedData['length']);
    $tag = new Tag($unpackedData['id'], $value, $unpackedData['length']);
    if ($this->debug) {
      call_user_func($this->debugHandler, "Parsed tag:");
      call_user_func($this->debugHandler, " id     :0x" . dechex($tag->id));
      call_user_func($this->debugHandler, " length :" . $tag->length);
      call_user_func($this->debugHandler, " value  :" . chunk_split(bin2hex($tag->value), 2, " "));
    }
    return $tag;
  }
}