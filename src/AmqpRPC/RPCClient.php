<?php
namespace AmqpRPC;
use Exception;
use Ramsey\Uuid\Guid\Guid;
use React\Promise\Deferred;
use react\Promise;
use Rx\Scheduler;
function startLoop()
{
    $loop = Factory::create();
    //You only need to set the default scheduler once
    Scheduler::setDefaultFactory(function () use ($loop) {
        return new EventLoopScheduler($loop);
    });

}

//普通单调用和多调用 
class RPCClient
{
    private $port;
    private $cbks = [];
    public function __construct(IRPCPort $port)
    {
        $this->port = $port;
        $this->mount();
    }

    public function onReceived(RPCMessage $msg)
    {
        $callid = $msg->callid;

        if (isset($this->cbks[$callid])) {
            try {
                $cbk = $this->cbks[$callid];
                $needfree = false;
                if ($msg->type == 1) {
                    //返回 结果 直接删除
                    $needfree = true;
                    $cbk($msg->result);
                } else if ($msg->type == 2) {
                    //调用错误回调
                    $needfree = true;
                    $cbk($msg->error);
                } else
                    throw new Exception("消息类型错误");

            }catch(Exception $e){
                //错误时检查
            }
            
            finally {
                if ($needfree) {
                    $this->clearCbk($callid);
                }
            }
        }else throw new Exception("找不到监听器");
    }

    private $dispose;
    public function mount()
    {
        $this->dispose = $this->port->receive()->subscribe(function (RPCMessage $msg) use ($this) {
            $this->onReceived($msg);
        });
    }
    //解除订阅
    public function unmount()
    {
        if ($this->dispose != null) {
            $this->dispose->dispose();
        }
    }
    private $errs = [];
    // 发送请求并接收响应
    //普通队列调用
    public function call($serviceid, $method, $params = null)
    {
        $callid = Guid::uuid1()->toString();
        $rpcMessage = RPCMessage::createCall($callid, $serviceid, $method, $params);
        // 异步发送消息
        $res = new Deferred();
        $this->port->send($rpcMessage);
        //增加回调
        $this->cbks[$callid] = function ($data) use ($res) {
            $res->resolve($data);
        };
        $this->errs[$callid] = function ($e) use ($res) {
            $res->reject($e);
        };
        //监听
        return $res->promise();

    }

    //普通多调用
    public function callMulti($serviceid,$method,$params=null){
        
    }


    //清理回调
    public function clearCbk($callid)
    {
        if (isset($this->cbks[$callid])) {
            unset($this->cbks[$callid]);
        }
        if (isset($this->cbks[$callid])) {
            unset($this->cbks[$callid]);
        }
    }



}
