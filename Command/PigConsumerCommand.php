<?php
/**
 * 小猪消费着二次开发
 */
namespace XiaoZhu\RabbitXzBundle\Command;

use XiaoZhu\RabbitXzBundle\Command\BaseRabbitMqCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use XiaoZhu\RabbitXzBundle\Util\Anet;
use XiaoZhu\RabbitXzBundle\Util\Stat;
use XiaoZhu\RabbitXzBundle\Util\ClientParser;

class PigConsumerCommand extends BaseRabbitMqCommand
{
    /**
     * 消费连接
     */
    public $consumer;
    
    /**
     * 队列名字
     */
    public $queueName;
    
    /**
     * 队列编号
     */
    public $queueNo;
    
    /**
     * 当前进程的进程id
     */
    public $pid;
    
    /**
     * 上次激活时间
     */
    public $updateTime;
    
    /**
     * 与监控进程沟通的 unix socket文件
     */
    public $unixSock;
    
    /**
     * 进程的启动时间
     */
    public $bornTime;
    
    /*
     * 工作目录
     */
    public $workspace;
    
    /**
     * 是否当前进程需要立刻退出
     */
    public $needStop = false;
    
    protected static $defaultName = 'rabbitmq:pigconsumer';
    
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Executes a consumer');
        $this->setName('rabbitmq:pigconsumer');
        $this
        ->addArgument('queueName', InputArgument::REQUIRED, 'Consumer Name')
        ->addArgument('queueNo',  InputArgument::REQUIRED, 'Consumer Sequence')
        ->addArgument('workspace',  InputArgument::REQUIRED, 'Consumer WorkSpace')
        ->addArgument('unixsock', InputArgument::REQUIRED, 'Monitor unix sock file path')
        ;
    }
    
    /**
     * 和监控通信，通信失败没有关系
     */
    protected function monitor() : bool
    {
        $net = new Anet();
        $parser = new ClientParser();
        $net->setParser($parser);
        $client = $net->getUnixClient($this->unixSock);
        if ($client->getConnectState() != ANET::ANET_CONNECTED) {
            return false;
        }
        //询问当前进程是否可以停止
        $status = $client->canExit($this->queueName, $this->queueNo, $this->pid, $this->bornTime);
        if ($status == true) {
            $this->consumer->stopConsuming();
            exit(0);
        }
        $status = $client->ping($this->queueName, $this->queueNo, $this->pid, Stat::memoryUseage());
        return $client->close();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initWorker($input);
        while(true) {
            $this->monitor();
            $this->updateTime = time();
            $this->runQueue();
            pcntl_signal_dispatch();
        }
    }
    
    /**
     * 消费程序的封装
     * @return bool
     */
    protected function runQueue()
    {   
        try {
            $this->getConsumer();
            $this->consumer->pigConsume(120);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }
    
    /**
     * 初始化消费进程
     */
    public function initWorker(InputInterface $input)
    {
        $this->pid = posix_getpid();
        $this->bornTime = time();
        $this->updateTime = time();
        if (!extension_loaded('pcntl')) {
            exit(0);
        }
        pcntl_signal(SIGUSR1, array($this, 'sigUsr1Deal'));
        $this->queueName = $input->getArgument('queueName');
        $this->queueNo = $input->getArgument('queueNo');
        $this->workspace = $input->getArgument('workspace');
        $this->unixSock = $input->getArgument('unixsock');
        if (empty($this->queueName) || empty($this->workspace) || empty($this->unixSock)) {
            exit(0);
        }
        chdir($this->workspace);
    }
    
    protected function sigUsr1Deal($signo)
    {
        try {
            $this->consumer->stopConsuming();
        } catch (AMQPTimeoutException $e) {}
        exit(0);
    }
    
    protected function getConsumerService()
    {
        return 'xiao_zhu_rabbit_xz.%s_consumer';
    }
    
    /**
     * 如果网络断开我们就重新连接
     * @return bool
     */
    protected function getConsumer()
    {
        try {
            if ($this->consumer == null || false == $this->consumer->getChannel()->getConnection()->isConnected()) {
                if ($this->consumer != null) {
                    $this->consumer->reconnect();
                }
                $this->consumer = $this->getContainer()->get(sprintf($this->getConsumerService(), $this->queueName));
                $option = $this->consumer->getQueueOptions();
                if (isset($option['routing_keys']) && !empty($option['routing_keys'])) {
                    $this->consumer->setRoutingKey($option['routing_keys'][0]);
                }
                $this->consumer->setupConsumer();
            }
            return true;
        } catch(\Exception $e) {
            exit("down");
        }
    }
}
