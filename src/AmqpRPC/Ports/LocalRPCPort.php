<?php
namespace AmqpRPC\Ports;
use AmqpRPC\IRPCPort;
use AmqpRPC\RPCInstanceInfo;
use AmqpRPC\RPCServiceInfo;
use AmqpRPC\RPCServiceRegisterList;
use Exception;
//deferred = taskcompletesource
use React\Promise\Deferred;
//promise = task /promise
use React\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * 测试用的本地port 本地异步调用 直接消息传递 作为注册服务器同时作为port 支持注册多服务 支持泛调用和随机调用 以及指定调用
 */
class LocalRPCPort implements IRPCPort {
   
  private $table;
  public function __construct() {
    $this->table=new RPCServiceRegisterList();
  }
  /**
   * @inheritDoc
   */
  public function getInstanceInfos(): Promise\PromiseInterface {
  }
  
  /**
   * @inheritDoc
   */
  public function receive(): \Rx\ObservableInterface {
  }
  
  /**
   * @inheritDoc
   */
  public function send(\AmqpRPC\RPCMessage $message): Promise\PromiseInterface {
  }
  /**
   * @inheritDoc
   */
  public function init(): Promise\PromiseInterface {
    function getins($name){
      
      $ins=new RPCInstanceInfo();
      $ins->serviceId=["com.gmono.test"];
      $ins->instanceId=$name;
    }

    $this->table->addRange([getins("test1"),getins("test2")]);
  }
}
