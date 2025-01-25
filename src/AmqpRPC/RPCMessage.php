<?php
namespace AmqpRPC;


class RPCMessage
{
  public $callid;    // 单次调用的唯一标识
  public $serviceId; // 服务类型id  
  public $instanceId; //实例id
  public $method;    // 目标方法
  public $params;    // 参数 可以为null表示没有参数
  public $result;    // 返回结果
  public $error;     // 错误信息
  public $callMode=0;//1表示safe调用 safe模式需要ack 保证
  public $sendType=0;//0表示确定单调用 1表示随机调用 2表示服务组调用 3 表示全局调用 其中2 3 为多返回

  public function isMultiReturn(){
    return $this->sendType==2||$this->sendType==3;
  }
  

  // 0表示调用 1表示返回结果 2表示返回错误
  public $type = 0;

  public function isCall(){
    return $this->type==0;
  }
  public function isResult(){
    return $this->type==1;
  }
  public function isErr(){
    return $this->type==2;
  }

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
    $msg->callid = $data['callid']??null;
    $msg->serviceId = $data['serviceId']??null;
    $msg->method = $data['method']??null;
    $msg->params = $data['params']??null;
    $msg->result = $data['result'] ?? null;
    $msg->error = $data['error'] ?? null;
    return $msg;
  }

  // 创建指定调用消息（type = 0） 给定目标服务id 目标实体id 方法名称 和参数表
  public static function createCall($callid, $serviceid,$instanceId, $method, $params = null)
  {
    $msg = new RPCMessage();
    $msg->callid = $callid;
    $msg->serviceId = $serviceid;
    $msg->instanceId=$instanceId;
    $msg->method = $method;
    $msg->params = $params;
    $msg->type = 0; // 设置为调用类型
    return $msg;
  }

  //创建随机调用消息
  public static function createRandomCall($callid, $serviceid, $method, $params = null){
    $msg = new RPCMessage();
    $msg->callid = $callid;
    $msg->serviceId = $serviceid;
    // $msg->instanceId=$instanceId;
    $msg->method = $method;
    $msg->params = $params;
    $msg->type = 0; // 设置为调用类型
    return $msg;
  }

  // 创建返回结果消息（type = 1）
  public static function createResult($callid, $result,$serviceId,$instanceId)
  {
    $msg = new RPCMessage();
    $msg->callid=$callid;
    $msg->type = 1;  // 设置为返回结果类型
    $msg->result = $result;
    $msg->serviceId=$serviceId;
    $msg->instanceId=$serviceId;
    return $msg;
  }

  // 创建错误消息（type = 2）
  public static function createError($callid, $error,$serviceId,$instanceId)
  {
    $msg = new RPCMessage();
    $msg->callid=$callid;
    $msg->type = 2;  // 设置为错误类型
    $msg->error = $error;

    $msg->serviceId=$serviceId;
    $msg->instanceId=$serviceId;
    
    return $msg;
  }
}
