<?php
namespace XiaoZhu\RabbitXzBundle\Util;

class Client
{   
    private $source;
    
    private $network;
    
    private $callback;//回调函数
    
    public function __construct($source, CommandParse $command, Anet $network)
    {
        $this->source = $source;
        $this->network = $network;
    }
    
    /**
     * 向监控进程心跳自己的状态
     */
    public function ping(string $queueName, int $queueNo, int $pid, $memory) : bool
    {
        $command = "PING $queueName $queueNo $pid $memory\n";
        return $this->network->commandControl->sendCommand($this->source, $command);
    }
    
    /**
     * 向监控查询当前队列需要执行的操作
     */
    public function canExit(string $queueName, int $queueNo, int $pid, int $bornTime):bool
    {
        $command = "EXIT $queueName $queueNo $pid $bornTime\n";
        return $this->network->commandControl->sendCommand($this->source, $command);
    }
    
    public function close()
    {
        $this->network->closeClient($this->source);
        return true;
    }
    
}