<?php
/**
 * 监控进程的网络库
 */
namespace XiaoZhu\RabbitXzBundle\Util;

class Anet
{
    const ANET_NONE = 0;
    
    const ANET_READ = 1;
    
    const ANET_WRITE = 2;
    
    const MAX_CLIENTS = 1024;//防止linux 没有epoll select 
    
    const MAX_READ_BYTES = 2048;
    
    public $waitTime = 3;
    
    private $read = [];
    
    private $write = [];
    
    private $firedClient = [];
    
    private $clientNums = 0;
    
    private $client = [];
    
    public $commandControl;
    
    private $lastError = '';
    
    private $servers = [];
    
    public function getLastError() : string
    {
        return $this->lastError;
    }
    
    public function setLastError(string $error) : void
    {
        $this->lastError = $error;
    }
    
    public function __construct(CommandParse $command)
    {
        $this->commandControl = $command;
        $this->commandControl->setNetwork($this);
    }
    
    public function closeClient($source)
    {
        unset($this->client[intval($source)]);
        $this->removeResourceApi($source, self::ANET_READ|self::ANET_WRITE);
        $this->clientNums--;
        socket_close($source);   
    }
    
    public function registerResource($source, int $flag) : bool
    {
        if ($flag & self::ANET_READ) {
            $this->read[intval($source)] = $source;
        }
        if ($flag & self::ANET_WRITE) {
            $this->write[intval($source)] = $source;
        }
        return true;
    }
    
    public function removeResourceApi($source, $flag) : bool
    {
        if ($flag & self::ANET_READ) {
            unset($this->read[intval($source)]);
        }
        if ($flag & self::ANET_WRITE) {
            unset($this->write[intval($source)]);
        }
        return true;
    }
    
    public function netLoopApi($timeout = 0)
    {
        $reactors = $this->loop($timeout);
        if ($reactors == 0) return true;
        foreach ($this->firedClient as $fd => $event) {
            if (isset($this->servers[$fd])) {
                $this->acceptClient($this->servers[$fd]);
                continue;
            }
            if ($event | self::ANET_READ) {
                $buffer = socket_read($this->client[$fd], self::MAX_READ_BYTES, PHP_NORMAL_READ);
                //客户端断开连接，php能读到空字符串
                if ($buffer === false || $buffer == "") {
                    $this->closeClient($this->client[$fd]);
                    continue;
                }
                $this->commandControl->parseCommand($this->client[$fd], $buffer);
            }
        }
        return true;
    }
    
    public function loop($timeout) : int
    {
        $this->firedClient = [];
        $reads = $this->read;
        $writes = $this->write;
        $excepts = null;
        $timeout = empty($timeout) ? $this->waitTime : $timeout;
        $reactors = @socket_select($reads, $writes, $excepts, $timeout);
        if ($reactors == 0 || $reactors === false) {
            return 0;
        }
        foreach ($reads as $fd) {
            $this->firedClient[intval($fd)] = self::ANET_READ;
        }
        foreach ($writes as $fd) {
            if (isset($this->firedClient[intval($fd)])) {
                $this->firedClient[intval($fd)] |= self::ANET_WRITE; 
            } else {
                $this->firedClient[intval($fd)] = self::ANET_WRITE;
            }
        }
        return $reactors;
    }
    
    public static function writen($source, string $msg) : bool
    {
        $length = strlen($msg);
        if ($length == 0) return true;
        while ($length > 0) {
            $nbytes = @socket_write($source, $msg, $length);
            if ($nbytes === false) {
                return false;
            }
            $length -= $nbytes;
            $msg = substr($msg, $nbytes);
        }
        return true;
    }
    
    /**
     * 创建UNIX服务端监听进程
     */
    public function createUnixServer($sockPath) : bool
    {
        $localSocket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($localSocket == false) {
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        $res = socket_bind($localSocket, $sockPath);
        if ($res == false) {
            socket_close($res);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        $res = socket_listen($localSocket, 100);
        if ($res == false) {
            socket_close($res);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        socket_set_nonblock($localSocket);
        $this->servers[intval($localSocket)] = $localSocket;
        $this->registerResource($localSocket, self::ANET_READ);
        return true;
    }
    
    /**
     * 创建远程服务端进程
     */
    public function createTcpServer(string $ip, int $port) : bool
    {
        $service = implode(':', [$ip, $port]);
        $localSocket = socket_create(AF_INET, SOCK_STREAM, 0);
        if ($localSocket == false) {
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        $res = socket_bind($localSocket, $ip, $port);
        if ($res == false) {
            socket_close($res);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        $res = socket_listen($localSocket, 100);
        if ($res == false) {
            socket_close($res);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        if (!socket_set_option($localSocket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            socket_close($localSocket);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        socket_set_nonblock($localSocket);
        $this->servers[intval($localSocket)] = $localSocket;
        $this->registerResource($localSocket, self::ANET_READ);
        return true;
    }
    
    /**
     *  接收来自客户端的连接
     */
    public function acceptClient($socket)
    {
        $fd = null;
        while(($fd = @socket_accept($socket)) != false)
        {
            if ($this->clientNums >= self::MAX_CLIENTS) {
                socket_close($fd);
                continue;
            }
            socket_set_nonblock($socket);
            $this->clientNums++;
            $this->client[intval($fd)] = $fd;
            $this->registerResource($fd, Anet::ANET_READ);
        }
        return true;
    }
    
    /**
     * 查询该socket能否写入消息
     * @param unknown $sock
     */
    public function socketCanWrite($sock) : bool
    {
        $read = $excp = [];
        $write = [$sock];
        $reactors = socket_select($read, $write, $excp, $this->waitTime);
        if ($reactors == 0 || $reactors === false) {
            return false;
        }
        return true;
    }
    
    /**
     * 查询该socket能否写入消息
     * @param unknown $sock
     */
    public function socketCanRead($sock) : bool
    {
        $write = $excp = [];
        $read = [$sock];
        $reactors = socket_select($read, $write, $excp, $this->waitTime);
        if ($reactors == 0 || $reactors === false) {
            return false;
        }
        return true;
    }
    
    /**
     * 停止网络服务
     */
    public function stop()
    {
        foreach ($this->servers as $server) {
            socket_close($server);
        }
    }
    
    
    /**
     * 获取unix socket客户端
     * @param string $sockPath
     */
    public function getUnixClient(string $sockPath) : Client
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket == false) {
            return null;
        }
        $res = @socket_connect($socket, $sockPath);
        if ($res == false) {
            socket_close($socket);
            return null;
        }
        $this->registerResource($socket, self::ANET_READ);
        $this->client[intval($socket)] = $socket;
        $this->clientNums++;
        $client = new Client($socket, $this->commandControl, $this);
        return $client;
    }
}
