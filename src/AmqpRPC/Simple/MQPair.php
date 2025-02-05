<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;



interface IMQPair{
  public function name():string;
  public function receiveMq():string;
  public function sendMq():string;
  public function exchange():string;
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
  public function exchange(){
    return $this->name()."_pub_exchange";
  } //目标交换机
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
  public function exchange(){
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


//简单rpc调用器基于一个pair
//普通单一发送统一返回 随机调用队列
class MQConnection
{

  //连接的基本名称 决定队列的名字 
  public $name;


  public $mqs;
  public $address;
  public function __construct(MQAddress $address, IMQPair $pair)
  {
    $this->mqs = $pair;
    $this->address = $address;
    $this->startConnect();
  }

  public $send;
  public $receive;


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
    $exchange=$this->mqs->exchange->;
    //声明发送频道
    $channel = $connection->channel();
    $queue = $this->mqs->sendMq;
    $channel->exchange_declare($exchange, 'direct', false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->send = $channel;
    //声明接收频道
    $channel = $connection->channel();
    $queue = $this->mqs->receiveMq;
    $channel->exchange_declare($exchange, 'direct', false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->receive = $channel;
  }
}

//发布订阅模式连接 
//发布到多个队列 接收使用一个队列
//使用交换机名称区分
class PublishMQConnection{

}

class SimpleRPCClient{
  public $connect;
  public function __construct(MQConnection $conn) {
    $this->connect=$conn;

  }
  //---发送接收

  function start(){
    $this->connect->startConnect();
    $this->connect->receive->
  }
}