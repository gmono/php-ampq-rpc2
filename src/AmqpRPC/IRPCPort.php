<?php
namespace AmqpRPC;

use React\Promise\PromiseInterface;
use Rx\ObservableInterface;

/**
 * 每个rpc通道上可以有多个服务 每个服务可以有多个实例 通过实例表来注册
 */
interface IRPCPort
{

    //异步初始化对象
    public function init():PromiseInterface;
    // 发送一个 RPC 消息
    public function send(RPCMessage $message): PromiseInterface;

    // 接收一个 RPC 消息
    public function receive(): ObservableInterface;
    //rpc实例注册表 返回一个实例列表 通常通过组播搜集 超时5秒
    //基本接口只提供一个global服务和一个global实例 只支持单调用
    public function getInstanceInfos(): PromiseInterface;
}


/**
 * rpc服务提供者实例信息
 * 每个实例可以实现多个服务
 * 每次注册一个实例 
 */
class RPCInstanceInfo
{
    public $serviceId = [];
    public $instanceId = "";
    public function checkValid()
    {
        return $this->serviceId != null &&
            count($this->serviceId) > 0 &&
            $this->instanceId != null &&
            trim($this->instanceId) != "";
    }
}


/**
 * rpc服务注册表 拥有实例列表 服务列表 可通过服务查询实例列表 可查询某个实例的服务列表
 */
class RPCServiceRegisterList
{
    //基本实例列表
    public $instances = []; //实例列表 原始
    public $idToIns = []; //从id搜索实例 id 不可重复
    public $serviceToInstance = [];  //从服务搜索实例

    public $services = [];//服务列表 合并 为serviceid->true

    //注册一组实例
    public function addRange(array $instances, $strict = true)
    {
        array_push($this->instances, ...$instances);
        //合并其他
        foreach ($instances as $item) {
            $sid = $item->serviceId;
            $id = $item->instanceId;
            //如果id 重复则报错
            if (isset($this->idToIns, $id)) {
                if ($strict)
                    throw new \Exception("错误，实例id重复");
                else
                    //非严格模式下 会跳过
                    continue;
            }
            $this->idToIns[$id] = $item;
            //加入到服务注册表
            array_push($this->serviceToInstance[$sid], $item);
            if (!isset($this->services, $sid)) {
                $this->services[$sid] = true;
            }
        }

    }
}

