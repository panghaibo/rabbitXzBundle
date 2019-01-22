<?php
namespace XiaoZhu\RabbitXzBundle\Util;

class Client
{
    private $source;
    
    private $conState;//连接状态
    
    private $lastErr;//网络错误
    
    private $type;
    
    private $wbuffer;
    
    private $obuffer;
    
    private $network;
    
    private $parser;
    
    public $fd;
    
    private $block;
    
    private $closeAfterReply = false;
    
    public function __construct(string $type, $block)
    {
        $this->conState = ANET::ANET_DISCONECTED;
        $this->type = $type;
        $this->block = $block;
    }
    
    public function setCloseAfterReply()
    {
        $this->closeAfterReply = true;
    }
    
    public function getCloseAfterReply() : bool
    {
        return $this->closeAfterReply;
    }
    
    public function close()
    {
        if (is_resource($this->source)) {
            socket_close($this->source);
        }
        return true;
    }
    
    public function setNetAndParser(Anet $net, Parser $parser)
    {
        $this->network = $net;
        $this->parser = $parser;
    }
    
    public function setConnectState(int $state) : bool
    {
        $this->conState = $state;
        return true;
    }
    
    public function getConnectState() : int
    {
        return $this->conState;
    }
    
    public function setError(string $msg) : Client
    {
        $this->lastErr = $msg;
        return $this;
    }
    
    public function getBlockStatus()
    {
        return $this->block;
    }
    
    public function setResource($socket) : Client
    {
        if ($this->block) {
            socket_set_block($socket);
        } else {
            socket_set_nonblock($socket);
        }
        $this->source = $socket;
        $this->fd = intval($socket);
        return $this;
    }
    
    public function getResource()
    {
        return $this->source;
    }
    
    public function setWbuffer(string $buffer)
    {
        $this->wbuffer = $buffer;
    }
    
    public function getWbuffer()
    {
        return $this->wbuffer;
    }
    
    public function setRbuffer(string $buffer)
    {
        $this->obuffer = $buffer;
    }
    
    public function getRbuffer()
    {
        return $this->obuffer;
    }
    
    /**
     * 向监控进程心跳自己的状态
     */
    public function ping(string $queueName, int $queueNo, int $pid, string $memory) : bool
    {
        $command = "PING $queueName $queueNo $pid $memory\n";
        $this->setWbuffer($command);
        return $this->network->getReply($this);
    }
    
    /**
     * 向监控查询当前队列需要执行的操作
     */
    public function canExit(string $queueName, int $queueNo, int $pid, int $bornTime):bool
    {
        $command = "EXIT $queueName $queueNo $pid $bornTime\n";
        $this->setWbuffer($command);
        return $this->network->getReply($this);
    }
}