<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function test_rabbitmq_availability($host, $port, $user, $password, $vhost = 'bthost', $queue = 'test_queue') {
    try {
        // 创建连接
        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        // 声明一个队列
        $channel->queue_declare($queue, false, false, false, false);
        echo "队列 '$queue' 声明成功。\n";

        // 发送一条测试消息
        $msg = new AMQPMessage('Hello RabbitMQ!');
        $channel->basic_publish($msg, '', $queue);
        echo "消息发布成功。\n";

        // 接收该消息
        $callback = function ($msg) {
            echo '接收到消息: ', $msg->body, "\n";
        };

        $channel->basic_consume($queue, '', false, true, false, false, $callback);

        // 等待并处理消息
        while ($channel->is_consuming()) {
            $channel->wait();
            break; // 处理一条消息后退出循环
        }

        // 清理工作
        $channel->queue_delete($queue);
        echo "队列 '$queue' 删除成功。\n";

        $channel->close();
        $connection->close();
        echo "连接关闭成功。\n";

        return true;
    } catch (Exception $e) {
        echo "无法连接到 RabbitMQ 服务器: ", $e->getMessage(), "\n";
        return false;
    }
}

// 测试函数
if (test_rabbitmq_availability('159.75.243.179', 5672, 'test', 'testtest')) {
    echo "RabbitMQ 服务器可用！\n";
} else {
    echo "RabbitMQ 服务器不可用！\n";
}
?>