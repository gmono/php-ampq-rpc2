<?php

namespace AmqpRPC\Lib;


class Util
{
  /**
   * 把json转换为类对象
   * @param string $json
   * @param string  $className
   * @return object|null
   */

  public static function jsonToClass($json, $className)
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

}
