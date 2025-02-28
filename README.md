# 简单httprpc通信
1. 提供一个putResult接口用于外部提交返回结果（可对接到同步http接口
2. 提供一个中心的RPCClient类
3. 提供数个包装RPCClient 通过client进行api调用的类

## 中心路由API（system）
id为system用于调用节点适配器api，可以进行系统级设置如修改结果提交url等

## 程序管理器API（manager）
id为manager用于调用节点程序管理器api，支持获取节点程序列表，程序启停 状态监控等


## demo
```php

use AmqpRPC\HTTP\HttpRPCClient;
use AmqpRPC\HTTP\SystemApi;

use AmqpRPC\HTTP;
use AmqpRPC\Lib\MQAddress;

$addr=new MQAddress();
$addr->host="159.75.243.179";
$addr->vhost="bthost";
$addr->username="test";
$addr->password="testtest";
$addr->port=5672;
$client=new HttpRPCClient($addr);

//tp5接口 post 文本body参数 直接传递到putResult
function postResult(string $jsontext){
    global $client;
    $client->putResult($jsontext);
}

//其他接口 调用api并设置返回后执行的操作
function otherPort(){
    global $client;
    $api=new SystemApi($client);
    $api->Hello(["1","2"])->then(function($res){
        echo $res;
    })->catch(function($err){
        echo $err;
    });
}

function init()
{
    global $client;
    $api=new SystemApi($client);
    $api->setReturnUrl("xxxxxx");
}

```