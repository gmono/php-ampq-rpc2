<?php

// 任务 获取所有instance 的信息 包括其提供的服务
// 发送 等待 一旦收到 重置计数器 一般设置为2s 
//策略client广播接收信息 策略2 链式分布式查询
//使用mq做组播

//全局广播队列 globalSend globalReceive（client 发 server收

//服务泛调用队列 serviceCall
//服务随机单调用队列 serviceRandomCall
//全局返回队列
//泛调用使用发布订阅模式 随机采用消费者


