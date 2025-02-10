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
class HttpRPCCall{
  public $callid;
  public $serviceId;
  public $funcname;
  public $pars;

  public function __construct($serviceid,$funcname) {
    $this->callid=uuid();
    $this->serviceId=$serviceid;
    $this->funcname=$funcname;
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
function jsonToResult($text):HttpRPCResult
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
    $this->channel_send=$this->client->channel();
    $this->channel_send_global=$this->client->channel();
    //连接队列
    $this->channel_send->queueDeclare("random_call");
    $this->channel_send_global->queueDeclare("publish_call");
    $this->channel_send->exchangeDeclare("call_direct","direct");
    $this->channel_send_global->exchangeDeclare("call_fanout","fanout");
  }
  public function send($msg,$target="global")
  {
    $text=tojson($msg);
    switch($target){
      case "random":
        $this->channel_send->publish($text,[],"call_direct","random_call");
        break;
      case "global":
        $this->channel_send_global->publish($text,[],"publish_call","call_fanout");
        break;
      default:
        throw new ErrorException("不存在的目标类型");
    }
  }
  //----接收器部分 外部接收

  //回调函数参数为httprpcresult
  public $callTable=[];

  /**
   * 通用返回通道
   *  注意 msg为文本 json
   */
  public function putResult(string $msg){
    $obj=jsonToResult($msg);
    if(isset($this->callTable,$obj->callid)){
      $func=$this->callTable[$obj->callid];
      $func($obj);
      unset($this->callTable,$obj->callid);
    }else {
      echo "忽略一个返回值，callid:{$obj->callid}";
    }
  }
  //进行调用 并等待
  public function call($service,$funcname,$pars){

    $res=new Deferred();
    $t=new HttpRPCCall($service,$funcname);
    $t->pars=$pars;
    $this->callTable[$t->callid]=function(HttpRPCResult $r) use($res){
      if($r->error==null)
        $res->resolve($r->result);
      else
        $res->reject($r->error);
    };
    return $res->promise();
  }



  //----------核心功能函数


}

/**
 * 节点系统api 
 * 
 */
class SystemServiceApi{
  public $client;
  public function __construct(HttpRPCClient $client) {
    $this->client= $client;
  }
  //函数
  public function Hello(array $texts){
    //测试函数
    return $this->client->call("system","Hello",$texts);
  }
  
}