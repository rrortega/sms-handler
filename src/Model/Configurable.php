<?php
/**
 * Created by PhpStorm.
 * User: rrortega
 * Profile: https://github.com/rrortega
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

  private function _getValueOf($key, $list = [], $default = null)
  {
    if (array_key_exists($key, $list))
      return $list[$key];

    $spl = explode(".", $key);

    if (!array_key_exists($spl[0], $list)) {
      if (count($spl) > 1)
        throw new \Exception(
          sprintf("Configuration key [%s] not found", $spl[0])
        );
      return $default;
    }

    $list = $list[array_shift($spl)];
    $key = implode(".", $spl);
    return $this->_getValueOf($key, $list, $default);

  }

  public function getConfig($key, $default = null)
  {
    return $this->_getValueOf($key, $this->config,$default);
  }

  public function addConfig($key, $val)
  {
    return $this->config[$key] = $val;
  }
}