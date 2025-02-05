<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use React\Promise\Promise;
use Rx\Subject\Subject;



interface IMQPair{
  public function name():string;
  public function receiveMq():string;
  public function sendMq():string;
  public function exchange_send():string;
  public function exchange_receive():string;
  public function exchangeType_send():string;
  public function exchangeType_receive():string;
  
}


/**
 * 发布订阅模式
 * 单队列发送 单队列接收 接收使用direct交换机 发送使用fanout
 * 可与directmq同名
 */
class PublishMQPair implements IMQPair{
  public string $m_name;

  public function name()
  {
    return $this->m_name;
  }
  public function sendMq(){
    return $this->name()."_pub_send";
  } //发送队列 调用队列
  public function receiveMq(){
    return $this->name()."_pub_result";
  }//接收队列
  public function exchange_send(){
    return $this->name()."_pub_exchange_send";
  } //目标交换机
  public function exchange_receive(){
    return $this->name()."_pub_exchange_receive";
  }
  public function exchangeType_send(){
    return "fanout";
  }
  public function exchangeType_receive()  {
    return "direct";
  }
}
/**
 * MQ队列pair
 * 普通单队列模式
 */
class MQPair implements IMQPair
{
  public string $m_name;

  public function name()
  {
    return $this->m_name;
  }
  public function sendMq(){
    return $this->name()."send";
  } //发送队列 调用队列
  public function receiveMq(){
    return $this->name()."result";
  }//接收队列

  //发送接收共用一个交换机
  public function exchange_send(){
    return $this->name()."exchange";
  } //目标交换机
  public function exchange_receive(){
    return $this->name()."exchange";
  } //目标交换机
  public function exchangeType_send(){
    return "direct";
  }
  public function exchangeType_receive()  {
    return "direct";
  }

}


class MQAddress
{
  public $host;
  public $vhost;
  public $port;
  public $username;
  public $password;



}



//强类型反序列化
function jsonToClass($json, $className) {
  function jsonToClass($json, $className) {
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



//简单rpc调用器基于一个pair
//普通单一发送统一返回 随机调用队列
class MQConnection
{


  //发送消息
  public function sendMessage($msg){
    $text=json_encode($msg);
    $m=new AMQPMessage($text);
    $this->send->basic_publish($m,$this->mqs->exchange_send(),$this->sendQueue);
  }
  public $onReceived; //发送给是json文本
  //连接的基本名称 决定队列的名字 
  public $name;


  public $mqs;
  public $address;
  public function __construct(MQAddress $address, IMQPair $pair)
  {
    $this->mqs = $pair;
    $this->address = $address;
    $this->onReceived=new Subject();
    $this->startConnect();
  }

  public $send;
  public $receive;

  public $recQueue;
  public $sendQueue;


  function startConnect()
  {
    // 创建连接
    $connection = new AMQPStreamConnection(
      $this->address->host,
      $this->address->port,
      $this->address->username,
      $this->address->password,
      $this->address->vhost
    );

    //接受和发送用同一个交换机
    $exchange=$this->mqs->exchange_send();
    $rexchange=$this->mqs->exchange_receive();
    $sendtype=$this->mqs->exchangeType_send();
    $recetype=$this->mqs->exchangeType_receive();
    //声明发送频道
    $channel = $connection->channel();
    $queue = $this->mqs->sendMq;
    $channel->exchange_declare($exchange, $sendtype, false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->send = $channel;
    $this->sendQueue=$queue;
    //声明接收频道
    $channel = $connection->channel();
    $queue = $this->mqs->receiveMq;
    $channel->exchange_declare($rexchange, $recetype, false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->receive = $channel;

    $this->recQueue=$queue;
    //监听receive频道
    $this->receive->basic_consume($this->recQueue,'',false,true,false,false,function($msg) use($this){
      //接收消息
      $x=$msg->body;
      $this->onReceived->onNext($x);
    });
  }
}

class SimpleCallMsg
{
  public $callid;
  public $funcname;
  public $pars;
  public function toJson(){

  }
  public function fromJson(string $text){

  }
}

//简单返回消息
class SimpleResultMsg{
  public $callid;
  public $result;
  //null表示没有错误
  public $error=null;

  public function toJson(){

  }

  
  public function fromJson(string $text){

  }
}
//单调用单返回和多调用多返回
//多返回会有一个test阶段 发送test call所有提供者响应
class SimpleRPCClient{
  public $connect;

  //0表示单返回 1表示多返回
  public $mode=0;
  public function __construct(MQConnection $conn) {
    $this->connect=$conn;

  }

  //调用 超时毫秒 多调用中如果注册client全部返回 则直接返回
  //forcetimeout开启时 一定会等到超时
  public function single_call($timeout=5000){
    
  }

  
  
  //---发送接收

  //测试调用所有client响应 在超时时间内响应的会被记录
  //所有未记录的client发送的返回会被丢弃
  function search(int $timeout){
    if($this->mode==1){
      
    }else throw new Exception("错误，单返回模式不能搜索");
  }
  function start(){
    $this->connect->startConnect();
    //监听receive队列消息 
  }
}