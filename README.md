# rabbitXzBundle
rabbitmq 本程序基于开源 https://github.com/php-amqplib/RabbitMqBundle 二次开发 加入监控程序和部分优化和个性化定制

### 安装程序包

composer require panghaibo/xz-rabbitmq-bundle dev-master

### 配置文件 可以参考 php-amqplib/RabbitMqBundle

配置文件中增加 `xiao_zhu_rabbit_xz` 节点

```yaml
xiao_zhu_rabbit_xz:
    connections:
        default:
            cluster: 
                0: '10.0.2.114:5672,10.0.2.114:5672' #第一个集群 一般一个集群由三台机器组成，连接的时候会随机选择可以使用的节点
                1: '10.0.2.114:5672,10.0.2.114:5672' 
            user:     'panghaibo'  #用户名 由运维给出
            password: '123456'  #密码 由运维给出
            vhost:    'lodgeUnitOrder' #虚拟host 由运维给出
            lazy:     false
            connection_timeout: 60
            read_write_timeout: 10
            keepalive: false
            heartbeat: 5
            use_socket: true
    producers:
        queue_haibo_test:   #一般用队列key
            connection:       default
            exchange_options: {name: 'queue_haibo_test', type: topic}
            enable_logger: true
    consumers:
        queue_haibo_test: #一般用队列key 监控的时候基于这个key
            connection:       default
            exchange_options: {name: 'queue_haibo_test', type: topic}
            callback:         App\Acme\TestBundle\Consumer\QueueHaiboConsumer
            queue_options:    {name: 'haibo_test'}
            qos_options:      {prefetch_size: 0, prefetch_count: 1, global: false}
            enable_logger: true
```

### 监控程序的启动
由于php的常驻进程在某些条件下会退出执行，我们确保监控进程一直存在，因此我们使用crontab启动监控进程：如下示例：
```
*/1 * * * * /usr/bin/php /data1/www/my-project/bin/console rabbitmq:monitor /usr/bin/php /tmp/queue 127.0.0.1 newapp 1314 >/dev/null 2>&1

```
* /data1/www/my-project/bin/console: 代表symfony bin/console目录
* rabbitmq:monitor : 监控程序
* /usr/bin/php 当前机器的使用的php版本bin路径
* /tmp/queue 队列的工作目录该目录必须存在 且可读可写
* 127.0.0.1 代表本机器的ip 监控进程会向监控中心注册本监控
* newapp 代表公司项目的的名称 按照业务分且唯一
* 1314 代表监控监听的端口

### 启动队列
```
telnet 127.0.0.1 1314
Connected to 127.0.0.1.
Escape character is '^]'.
ADD queue_haibo_test 3   #代表需要在本机器启动队列key `queue_haibo_test` 3个消费进程 0代表不启动消费进程
OK success
REBOOT queue_haibo_test  #代表重启本机器的queue_haibo_test 队列
ok success
STAT    #查看当前机器有多少任务 以及监控进程本身的一些信息
STAT queue_haibo_test 代表查看 queue_haibo_test 消费着的具体信息 以及向监控进程心跳的时间 每隔120s 会向监控中心心跳消费者信息
QUIT #退出


```



