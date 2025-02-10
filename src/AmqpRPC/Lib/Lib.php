<?php



/**
 * 把json转换为类对象
 * @param string $json
 * @param string  $className
 * @return object|null
 */
function jsonToClass($json, $className)
{
  $data = json_decode($json, true); // 将 JSON 解码为关联数组
  if (is_array($data)) {
    $object = new $className();
    foreach ($data as $key => $value) {
      if (property_exists($object, $key)) {
        $object->$key = $value;
      }
    }
    return $object;
  }
  return null;
}

/**
 * mq地址
 */

class MQAddress
{
  public $host;
  public $vhost;
  public $port;
  public $username;
  public $password;



}

