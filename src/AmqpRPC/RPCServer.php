<?php
namespace AmqpRPC;
use Exception;
//deferred = taskcompletesource
use React\Promise\Deferred;
//promise = task /promise
use React\Promise;
use function React\Promise\resolve;
class RPCServer implements IRPCPort {
    private $methods;

    public function __construct() {
        $this->methods = [];
    }

    // 注册 RPC 方法
    public function registerMethod($methodName, $callback) {
        $this->methods[$methodName] = $callback;
    }

    // 处理 RPC 请求
    public function receive(): \React\Promise\PromiseInterface {
        $deferred = new Deferred();
        
        // 模拟接收到 RPC 请求
        $rpcMessageJson = '{"callid":1,"serviceid":"mathService","method":"add","params":[5,10]}';
        $rpcMessage = RPCMessage::fromJson($rpcMessageJson);

        if (isset($this->methods[$rpcMessage->method])) {
            // 异步执行方法
            $this->handleRequest($rpcMessage)->then(function($response) use ($deferred) {
                $deferred->resolve($response);
            });
        } else {
            $rpcMessage->error = "Method {$rpcMessage->method} not found.";
            $deferred->resolve($rpcMessage->toJson());
        }

        return $deferred->promise();
    }

    private function handleRequest(RPCMessage $rpcMessage): \React\Promise\PromiseInterface {
        $deferred = new Deferred();

        // 异步处理方法调用
        $method = $rpcMessage->method;
        $params = $rpcMessage->params;
        
        if (isset($this->methods[$method])) {
            // 异步执行方法
            try {
                $result = call_user_func($this->methods[$method], $params);
                $rpcMessage->result = $result;
                $deferred->resolve($rpcMessage->toJson());
            } catch (Exception $e) {
                $rpcMessage->error = $e->getMessage();
                $deferred->resolve($rpcMessage->toJson());
            }
        } else {
            $rpcMessage->error = "Method {$rpcMessage->method} not found.";
            $deferred->resolve($rpcMessage->toJson());
        }

        return $deferred->promise();
    }

    // 实现 send 方法（暂时不实现，因为此类是接收消息的）
    public function send(RPCMessage $message): \React\Promise\PromiseInterface {
        // 此方法可以根据需求实现，例如发送到另一个服务
        return resolve("Message sent");
    }
}
