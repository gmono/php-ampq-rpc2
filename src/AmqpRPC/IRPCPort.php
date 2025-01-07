<?php
namespace AmqpRPC;

use React\Promise\PromiseInterface;
use Rx\ObservableInterface;

interface IRPCPort {
    // 发送一个 RPC 消息
    public function send(RPCMessage $message): PromiseInterface;

    // 接收一个 RPC 消息
    public function receive(): ObservableInterface;
    //RPC通道服务表 返回一个数组 映射 serviceid->RPCServiceInfo
    public function getServices():PromiseInterface;
}


