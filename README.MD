# 说明
基于amqp 的RPC库
## 接口
create(address,port,vhost) 自动配置交换机（使用topic交换机 基于service自动路由
async call(serviceid,funcname,pars:[])->json result

## 基本使用
主要类:RPCClient
配置项:
1. ACK安全模式（每次消息等待ACK 超时重发 重复忽略)，会增加延迟

