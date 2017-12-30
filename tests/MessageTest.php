<?php

use rrortega\sms\core\Model\Message;

/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 30/12/17
 * Time: 21:22
 */
class MessageTest extends PHPUnit_Framework_TestCase
{

  public function testSerializableMessage()
  {
    /** @var Message $m */
    $m = unserialize(base64_decode("TzozMToicnJvcnRlZ2Fcc21zXGNvcmVcTW9kZWxcTWVzc2FnZSI6NTp7czo1OiIAKgBpZCI7czoxOToiOTQyODAzMjM1NDE1NzAyMDI4NCI7czoxMToiACoAcmVtaXRlbnQiO2k6MTExMTExMTExMTE7czoxMjoiACoAcmVjaXBpZW50IjtpOjIyMjIyMjIyMjI7czoxMjoiACoAcGxhaW5UZXh0IjtzOjI0OiJUaGlzIGlzIHRoZSB0ZXN0IG1lc3NhZ2UiO3M6OToiACoAc3RhdHVzIjtzOjg6IlNDSEVEVUxFIjt9"));
    $this->assertEquals("This is the test message", $m->getPlainText());
  }
}

