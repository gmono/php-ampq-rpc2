<?php
namespace AmqpRPC\Ports;
use AmqpRPC\IRPCPort;
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
   
  /**
   * @inheritDoc
   */
  public function getServices(): Promise\PromiseInterface {
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
}
