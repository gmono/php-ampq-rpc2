<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use React\EventLoop\Factory;
use React\Promise\Promise;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\Subject\Subject;
use function React\Promise\race;


$loop = Factory::create();
function setTimeout($cbk, $time)
{
  global $loop;
  $loop->addTimer($time, function () use ($cbk) {
    $cbk();
  });
}
//假设回调在最后一个参数传递
function cbkToPromise($func, $catch = false, $pos = "last")
{
  $f = function (...$args) use ($func) {
    return new Promise(function ($re, $rj) use ($func, $args) {
      $new = array_merge([], $args, [
        function ($res) use ($re, $rj) {
          //接收返回的内容
          $re($res);
        }
      ]);
      //如果catch则再添加一个error捕获函数
      $func(...$new);
    });
  };
  return $f;
}

//基于promise 的异步延时
function delay($time)
{
  return new Promise(function ($re, $rj) use ($time) {
    setTimeout(function () use ($re, $rj) {
      $re();
    }, $time);
  });
}
interface IMQPair
{
  public function name(): string;
  public function receiveMq(): string;
  public function sendMq(): string;
  public function exchange_send(): string;
  public function exchange_receive(): string;
  public function exchangeType_send(): string;
  public function exchangeType_receive(): string;

}


/**
 * 发布订阅模式
 * 单队列发送 单队列接收 接收使用direct交换机 发送使用fanout
 * 可与directmq同名
 */
class PublishMQPair implements IMQPair
{
  public $m_name;

  public function name(): string
  {
    return $this->m_name;
  }
  public function sendMq(): string
  {
    return $this->name() . "_pub_send";
  } //发送队列 调用队列
  public function receiveMq(): string
  {
    return $this->name() . "_pub_result";
  }//接收队列
  public function exchange_send(): string
  {
    return $this->name() . "_pub_exchange_send";
  } //目标交换机
  public function exchange_receive(): string
  {
    return $this->name() . "_pub_exchange_receive";
  }
  public function exchangeType_send(): string
  {
    return "fanout";
  }
  public function exchangeType_receive(): string
  {
    return "direct";
  }
}
/**
 * MQ队列pair
 * 普通单队列模式
 */
class MQPair implements IMQPair
{
  public $m_name;

  public function name(): string
  {
    return $this->m_name;
  }
  public function sendMq(): string
  {
    return $this->name() . "send";
  } //发送队列 调用队列
  public function receiveMq(): string
  {
    return $this->name() . "result";
  }//接收队列

  //发送接收共用一个交换机
  public function exchange_send(): string
  {
    return $this->name() . "exchange";
  } //目标交换机
  public function exchange_receive(): string
  {
    return $this->name() . "exchange";
  } //目标交换机
  public function exchangeType_send(): string
  {
    return "direct";
  }
  public function exchangeType_receive(): string
  {
    return "direct";
  }

}







//简单rpc调用器基于一个pair
//普通单一发送统一返回 随机调用队列
class MQConnection
{


  //发送消息
  public function sendMessage($msg)
  {
    $text = json_encode($msg);
    $m = new AMQPMessage($text);
    $this->send->basic_publish($m, $this->mqs->exchange_send(), $this->sendQueue);
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
    $this->onReceived = new Subject();
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
    $exchange = $this->mqs->exchange_send();
    $rexchange = $this->mqs->exchange_receive();
    $sendtype = $this->mqs->exchangeType_send();
    $recetype = $this->mqs->exchangeType_receive();
    //声明发送频道
    $channel = $connection->channel();
    $queue = $this->mqs->sendMq();
    $channel->exchange_declare($exchange, $sendtype, false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->send = $channel;
    $this->sendQueue = $queue;
    //声明接收频道
    $channel = $connection->channel();
    $queue = $this->mqs->receiveMq();
    $channel->exchange_declare($rexchange, $recetype, false, true, false);

    $channel->queue_declare($queue, false, false, false, false);
    $this->receive = $channel;

    $this->recQueue = $queue;
    //监听receive频道
    $this->receive->basic_consume($this->recQueue, '', false, true, false, false, function ($msg) use ($this) {
      //接收消息
      $x = $msg->body;
      $this->onReceived->onNext($x);
    });
  }
}

class SimpleCallMsg
{
  public $callid;
  public $funcname;
  public $pars;
  public function toJson()
  {
    return json_encode($this);
  }
  public static function fromJson(string $text): SimpleCallMsg
  {
    return (jsonToClass($text, "SimpleCallMsg"));
  }
}

//简单返回消息
class SimpleResultMsg
{
  public $callid;
  public $result;
  //null表示没有错误
  public $error = null;

  public function toJson()
  {
    return json_encode($this);
  }


  public static function fromJson(string $text): SimpleResultMsg
  {
    return (jsonToClass($text, "SimpleResultMsg"));
  }
}


//单调用单返回和多调用多返回
//多返回会有一个test阶段 发送test call所有提供者响应
class SimpleRPCClient
{
  public $connect;


  public $currentId = 0;
  public function nextId()
  {
    $this->currentId++;
    return $this->currentId;
  }
  //0表示单返回 1表示多返回
  public $mode = 0;

  function receive(): Observable
  {
    return $this->connect->onReceived->map(function (string $msg) {
      return SimpleResultMsg::fromJson($msg);
    });
  }
  //过滤result id 
  function filterResult($callid)
  {
    return $this->receive()->filter(function (SimpleResultMsg $msg) use ($callid) {
      return $msg->callid == $callid;
    });
  }

  //等待一个返回结果
  function waitResult($callid, int $timeout)
  {
    //表示是否为实际任务返回
    $isreturned=false;
    $timer=delay($timeout);
    
    $task=new Promise(function ($resolve, $reject) use ($callid, $timeout) {
      $this->subscribeOnce($this->filterResult($callid), function ($msg) use ($resolve, $reject) {
        
        $resolve($msg);
      });

    });
    $all=race([$timer,$task]);
    $all->then(function(){
      //查看是哪个返回
    });
  }
  function subscribeOnce(Observable $ob, callable $func)
  {

    // $limit=Observable::timer($timeout);
    $dis = $ob->subscribeCallback(function ($msg) use (&$dis, $func) {
      $dis->dispose();
      $func($msg);
    });
    return $dis;
  }

  public function __construct(MQConnection $conn)
  {
    $this->connect = $conn;
    //监听消息
    $conn->onReceived->subscribeCallback(function (SimpleResultMsg $msg) {
      //响应消息
    });

  }

  //调用 超时毫秒 多调用中如果注册client全部返回 则直接返回
  //forcetimeout开启时 一定会等到超时
  public function single_call($name, $pars, $timeout = 5000)
  {
    $msg = new SimpleCallMsg();
    $msg->callid = $this->nextId();
    $msg->funcname = $name;
    $msg->pars = $pars;
    $this->connect->sendMessage($msg);
    //监听
    return new Promise(function ($r, $j) use ($this, $msg, $timeout) {
      $this->waitResult($msg->callid, $timeout)->then(function (SimpleResultMsg $res) use ($r, $j) {
        //根据结果判断
        if ($res->error == null)
          $r($res->result);
        else
          $j($res->error);
      })->catch(function (Throwable $err) use ($j) {
        $j($err->getMessage());
      });
    });
  }

  public function multi_call($timeout = 5000)
  {
    $this->connect->sendMessage();
  }



  //---发送接收

  //测试调用所有client响应 在超时时间内响应的会被记录
  //所有未记录的client发送的返回会被丢弃
  function search(int $timeout)
  {
    if ($this->mode == 1) {

    } else
      throw new Exception("错误，单返回模式不能搜索");
  }
  function start()
  {
    $this->connect->startConnect();
    //监听receive队列消息 
  }
}