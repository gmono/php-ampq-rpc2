<?php
namespace AmqpRPC;

use React\Promise\PromiseInterface;
use Rx\ObservableInterface;

/**
 * RPC服务提供者接口 支持接收调用消息 发送返回消息
 */
interface IRPCServicePort{

}


//消息收发端口
interface IMessagePort{
    //发送消息到一个频道
    public function sendMessage(string $channel,string $msg):PromiseInterface;
    //从某个频道获取接收器
    public function receive(string $channel):ObservableInterface;
}
//对接到队列 适用主题交换机
//channel:*  xxx.* xxx.*.xxx 等规则
class AMQPMessagePort implements IMessagePort{
    public $host="";
    public $vhost="";
    public $port=5432;
    public $username="";
    public $password="";
    //统一前缀
    public $prefix="";

    public $exchange="";//交换机选择
    public function startListen(){
        //开始监听
        //todo:连接并监听
    }
    
    /**
     * 获取一个channel的接收器
     */
    public function receive(string $channel): ObservableInterface {
    }
    
    /**
     * 发送消息到channel
     */
    public function sendMessage(string $channel, string $msg): PromiseInterface {
    }
}

/**
 * 
 * rpc服务提供者 支持一个服务
 */
class RPCServer{
    public $endpoint;//所属的endpoint
    public $self_serviceId;//自己的服务id
    public $methods=[]; //服务提供的函数表
    //注册函数
    public function registerFunction(string $name,$func){
        $this->methods[$name]=$func;
    }

    public function init(){

    }
    public function onCall($name,$pars){
        if($pars==null) $pars=[];
        if(isset($this->methods,$name)){
            $func=$this->methods[$name];
            //用参数表调用func
            $func(...$pars);
        }
    }
}
/**
 * RPC端点 每个端点可以提供一组服务 共享同一个instanceid
 */
class RPCEndpoint{
    public $self_instanceId="sys";
    public $messagePort;
    //通过端点发送消息
    public function send(RPCMessage $msg){
        //追加instance属性
        $msg->instanceId=$this->self_instanceId;
    }
    //合成channel
    public function getChannel($type,$service)
    {
        if($type=="call"){
            //"service.call"
        }
    }
    public function init(){
        $this->messagePort->receive(){

        }
    }
    //监听对某个服务的调用消息
    public function receiveCall($service):ObservableInterface{

    }
    //监听对某个服务的返回消息
    public function receiveReturn($service){

    }
    
    //获取某个服务提供者的专属rpc server对象
    public function getServiceServer($serviceId){

    }
}
/**
 * RPC调用接口 支持初始化 发送调用消息 接收返回来的result消息
 */
interface IRPCCallPort
{

    //异步初始化对象
    public function init():PromiseInterface;
    // 发送调用消息 自动根据参数选择发送方式
    public function send(RPCMessage $message): PromiseInterface;
    // 接收一个 RPC 消息
    public function receive(): ObservableInterface;
    //rpc实例注册表 返回一个实例列表 通常通过组播搜集 超时5秒
    // //基本接口只提供一个global服务和一个global实例 只支持单调用
    // public function getInstanceInfos(): PromiseInterface;
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

