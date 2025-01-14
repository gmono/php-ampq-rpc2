<?php
namespace AmqpRPC;


class RPCMessage
{
  public $callid;    // 单次调用的唯一标识
  public $serviceid; // 服务ID
  public $method;    // 目标方法
  public $params;    // 参数
  public $result;    // 返回结果
  public $error;     // 错误信息
  public $callMode=0;//1表示safe调用

  
  //在调用时表示多调用消息 返回时表示多调用返回中的一条消息
  public $multiCall;//多调用消息 多调用返回需要带serviceid以表示响应者

  // 0表示调用 1表示返回结果 2表示返回错误
  public $type = 0;

  // 构造方法
  public function __construct()
  {

  }

  // 序列化为 JSON 格式
  public function toJson()
  {
    return json_encode($this);
  }

  // 从 JSON 格式反序列化
  public static function fromJson($json)
  {
    $data = json_decode($json, true);
    $msg = new RPCMessage();
    $msg->callid = $data['callid'];
    $msg->serviceid = $data['serviceid'];
    $msg->method = $data['method'];
    $msg->params = $data['params'];
    $msg->result = $data['result'] ?? null;
    $msg->error = $data['error'] ?? null;
    return $msg;
  }

  // 创建调用消息（type = 0）
  public static function createCall($callid, $serviceid, $method, $params = null)
  {
    $msg = new RPCMessage();
    $msg->callid = $callid;
    $msg->serviceid = $serviceid;
    $msg->method = $method;
    $msg->params = $params;
    $msg->type = 0; // 设置为调用类型
    return $msg;
  }

  // 创建返回结果消息（type = 1）
  public static function createResult($callid, $result)
  {
    $msg = new RPCMessage();
    $msg->callid=$callid;
    $msg->type = 1;  // 设置为返回结果类型
    $msg->result = $result;
    return $msg;
  }

  // 创建错误消息（type = 2）
  public static function createError($callid, $error)
  {
    $msg = new RPCMessage();
    $msg->callid=$callid;
    $msg->type = 2;  // 设置为错误类型
    $msg->error = $error;
    return $msg;
  }
}
