<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Date: 29/12/17
 * Time: 17:48
 */

namespace rrortega\sms\core\Model;


class Configurable
{
  protected $config = [];

  public function setConfiguration(array $config = [])
  {
    $this->config = $config;
  }

  private function _getValueOf($key, $list = [])
  {
    if (array_key_exists($key, $list))
      return $list[$key];

    $spl = explode(".", $key);

    if (!array_key_exists($spl[0], $list))
      throw new \Exception(
        sprintf("Configuration key [%s] not found", $spl[0])
      );

    $list = $list[array_shift($spl)];
    $key = implode(".", $spl);
    return $this->_getValueOf($key, $list);

  }

  public function getConfig($key)
  {
    return $this->_getValueOf($key, $this->config);
  }

  public function addConfig($key, $val)
  {
    return $this->config[$key] = $val;
  }
}