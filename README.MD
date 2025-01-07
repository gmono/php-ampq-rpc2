# 说明
基于amqp 的RPC库
## 接口
create(address,port,vhost) 自动配置交换机（使用topic交换机 基于service自动路由
async call(serviceid,funcname,pars:[])->json result

## 基本使用
主要类:RPCClient
自动装配：
1. 注入RPCClient类型到服务容器中
2. 注入IRPCClient单例到或factory服务容器中

手动指定port:
1. 直接注入一个创建好的RPCClient对象到服务容器中

## Ports
1. LocalRPCPort 调试用port，本地异步调用
2. 