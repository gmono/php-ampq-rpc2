<?php

use Bunny\Client;
use Ramsey\Uuid\Rfc4122\UuidV1;
use Ramsey\Uuid\Uuid;
use React\Promise\Deferred;

$uuid = Uuid::getFactory();

//获取一个新的uuid
function uuid()
{
  global $uuid;
  return $uuid->uuid1()->toString();
}


//rpc调用
class HttpRPCCall
{
  public $callid;
  public $serviceId;
  public $funcname;
  public $pars;

  public function __construct($serviceid, $funcname)
  {
    $this->callid = uuid();
    $this->serviceId = $serviceid;
    $this->funcname = $funcname;
  }
}

function tojson($msg)
{
  return json_encode($msg);
}

//返回数据包
class HttpRPCResult
{
  //对应到调用
  public $callid;
  public $nodeid;//节点id
  public $result;//返回值 任意数组或关联数组
  public $serviceId;//服务id

  public $error;//如果是null表示没有错误

}
//反序列化到result对象
function jsonToResult($text): HttpRPCResult
{
  return jsonToClass($text, "HttpRPCResult");
}


function createConnection(MQAddress $mq, $ssl)
{
  $connection = [
    'host' => $mq->host,
    'vhost' => $mq->vhost,    // The default vhost is /
    'user' => $mq->username, // The default user is guest
    'password' => $mq->password, // The default password is guest

  ];
  if ($ssl) {
    $connection["ssl"] = [
      'cafile' => 'ca.pem',
      'local_cert' => 'client.cert',
      'local_pk' => 'client.key',
    ];
  }

  return $connection;
}


class ResultCallback
{
  //接收的都是result对象 其中有丰富信息
  public $cbk;
  public $errCbk;
  //0表示单调用 1表示多调用 多调用会一直等到超时时间到了才会返回
  public $type = 0;
  public $last_time;//过期时间时间戳 可以被自动过滤


  //是否为多调用
  public function multiCall()
  {
    return $this->type == 1;
  }

  public $results = [];
  //如果是单调用且已经设置了一个result则直接调用 如果是多调用则直接处理results数组
  public function autoCall()
  {
    if ($this->multiCall()) {
      $cbk = $this->cbk;
      $cbk($this->results);
    } else {
      $t = $this->results[0];
      if ($t->error != null) {
        $func = $this->errCbk;
        $func($t);
      } else {
        $func = $this->cbk;
        $func($t);
      }
    }
    //设置为已经调用
    $this->called=true;
  }
  //如果是单调用会自动调用
  public function putResult(HttpRPCResult $obj)
  {
    //如果已经调用则不接受新的
    if ($this->called)
      return;
    if ($this->multiCall()) {
      if ($this->isOverTime()) {
        $this->autoCall();
      } else {
        array_push($this->results, $obj);
      }
    } else {
      array_push($this->results, $obj);
      $this->autoCall();
    }
  }
  public function isValid()
  {
    return ($this->type == 0 || !$this->isOverTime()) && !$this->called;
  }

  //是否超时
  public function isOverTime()
  {
    return $this->last_time < microtime(true);
  }
  //是否已经被调用过
  public $called = false;
}
/**
 * 无协程的http 辅助队列的回调客户端
 */
class HttpRPCClient
{
  public $addr;
  public $client;
  public $channel_send;
  //全局发送 对接到fanout交换机
  public $channel_send_global;



  public function __construct(MQAddress $addr)
  {
    $this->addr = $addr;
    //client
    $this->client = new Client(createConnection($addr, false));
    $this->client->connect();

    //随机调用
    $this->channel_send = $this->client->channel();
    $this->channel_send_global = $this->client->channel();
    //连接队列
    $this->channel_send->queueDeclare("random_call");
    $this->channel_send_global->queueDeclare("publish_call");
    $this->channel_send->exchangeDeclare("call_direct", "direct");
    $this->channel_send_global->exchangeDeclare("call_fanout", "fanout");
  }
  //random global
  public function send($msg, $target = "global")
  {
    $text = tojson($msg);
    switch ($target) {
      case "random":
        $this->channel_send->publish($text, [], "call_direct", "random_call");
        break;
      case "global":
        $this->channel_send_global->publish($text, [], "publish_call", "call_fanout");
        break;
      default:
        throw new ErrorException("不存在的目标类型");
    }
  }
  //----接收器部分 外部接收

  //回调函数参数为httprpcresult
  public $callTable = [];

  //timeout单位毫秒
  function setCallback($callid, $cbk, $type, $timeout)
  {
    $obj = new ResultCallback();
    $obj->cbk = $cbk;
    $obj->type = $type;
    $obj->last_time = microtime(true) + ((float) $timeout) / 1000;
    if ($obj->isValid()) {
      $this->callTable[$callid] = $obj;
    } else
      throw new Exception("错误回调");
  }
  function getCallback($callid)
  {
    if (isset($this->callTable, $callid)) {
      return $this->callTable[$callid];
    }
    return null;
  }


  public function multiReturn(HttpRPCResult $obj)
  {

  }
  /**
   * 通用返回通道
   *  注意 msg为文本 json
   */
  public function putResult(string $msg)
  {
    $obj = jsonToResult($msg);
    $cbk = $this->getCallback($obj->callid);
    if ($cbk == null) {
      echo "忽略一个返回值，callid:{$obj->callid}";
    } else {
      unset($this->callTable, $obj->callid);
    }
  }

  public static $RANDOM_CALL = "random";
  public static $GLOBAL_CALL = "global";
  //进行调用 并等待
  public function call($target, $service, $funcname, $pars)
  {

    $res = new Deferred();
    $t = new HttpRPCCall($service, $funcname);
    $t->pars = $pars;
    $this->callTable[$t->callid] = function (HttpRPCResult $r) use ($res) {
      if ($r->error == null)
        $res->resolve($r->result);
      else
        $res->reject($r->error);
    };
    $this->send($t, $target);
    return $res->promise();
  }



  //----------核心功能函数


}

/**
 * 节点系统api 
 * 基本api对象
 */
class ServiceApi
{
  public $client;
  public $target;
  public function __construct(HttpRPCClient $client, $target = HttpRPCClient::$RANDOM_CALL)
  {
    $this->client = $client;
    $this->target = $target;
  }


}


//系统api
class SystemApi extends ServiceApi
{
  //函数
  public function Hello(array $texts)
  {
    //测试函数
    return $this->client->call($this->target, "system", "Hello", $texts);
  }
  //广播消息
  public function getNodeList()
  {

  }
}


class ManagerApi extends ServiceApi
{
  public function getProgramList()
  {

  }
}