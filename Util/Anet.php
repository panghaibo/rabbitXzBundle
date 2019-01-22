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
    
    const ANET_CONNECTED = 1;
    
    const ANET_DISCONECTED = 0;
    
    const ANET_UNIX = 'unix';
    
    const ANET_TCP = 'tcp';
    
    const ANET_BLOCK = 1;
    
    const ANET_NONBLOCK = 0;
    
    public $waitTime = 20;
    
    private $read = [];
    
    private $write = [];
    
    private $firedClient = [];
    
    private $clientNums = 0;
    
    private $client = [];
    
    private $parser;
    
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
    
    public function __construct()
    {
    }
    
    public function closeClient(Client $client)
    {
        unset($this->client[$client->fd]);
        $this->removeEventApi($client->getResource(), self::ANET_READ|self::ANET_WRITE);
        $this->clientNums--;
        $client->close();
    }
    
    public function openClient(Client $client)
    {
        $this->client[$client->fd] = $client;
        $this->clientNums++;
    }
    
    public function registerEventApi($socket, int $flag) : bool
    {
        $fd = intval($socket);
        if ($flag & self::ANET_READ) {
            $this->read[$fd] = $socket;
        }
        if ($flag & self::ANET_WRITE) {
            $this->write[$fd] = $socket;
        }
        return true;
    }
    
    public function removeEventApi($socket, int $flag) : bool
    {
        $fd = intval($socket);
        if ($flag & self::ANET_READ) {
            unset($this->read[$fd]);
        }
        if ($flag & self::ANET_WRITE) {
            unset($this->write[$fd]);
        }
        return true;
    }
    
    public function setParser(Parser $parser)
    {
        $this->parser = $parser;
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
                $client = $this->client[$fd];
                $this->getReply($client);
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
    
    public function writen($source, string $msg) : bool
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
        if (!socket_set_option($localSocket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            socket_close($localSocket);
            throw new AnetException(socket_strerror(socket_last_error()));
        }
        socket_set_nonblock($localSocket);
        $this->servers[intval($localSocket)] = $localSocket;
        $this->registerEventApi($localSocket, self::ANET_READ);
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
        $this->registerEventApi($localSocket, self::ANET_READ);
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
            $client = new Client(self::ANET_TCP, self::ANET_NONBLOCK);
            $client->setResource($fd);
            $client->setNetAndParser($this, new ServerParser());
            $this->openClient($client);
            $this->registerEventApi($fd, Anet::ANET_READ);
        }
        return true;
    }
    
    /**
     * 查询该socket能否写入消息
     * @param unknown $sock
     */
    public function socketCanWrite($sock, int $timeout = 1) : bool
    {
        $read = $excp = [];
        $write = [$sock];
        $reactors = socket_select($read, $write, $excp, $timeout);
        if ($reactors == 0 || $reactors === false) {
            return false;
        }
        return true;
    }
    
    /**
     * 查询该socket能否写入消息
     * @param unknown $sock
     */
    public function socketCanRead($sock, int $timeout = 1) : bool
    {
        $write = $excp = [];
        $read = [$sock];
        $reactors = socket_select($read, $write, $excp, $timeout);
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
        $client = new Client(ANET::ANET_UNIX, Anet::ANET_BLOCK);
        $client->setNetAndParser($this, new ClientParser());
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket == false) {
            return $client->setError(socket_strerror(socket_last_error()));
        }
        $res = @socket_connect($socket, $sockPath);
        if ($res == false) {
            return $client->setError(socket_strerror(socket_last_error()));
        }
        $canWrite = $this->socketCanWrite($socket);
        if ($canWrite) {
            $client->setConnectState(ANET::ANET_CONNECTED);
        }
        return $client->setResource($socket);
    }
    
    /**
     * 写消息处理
     */
    public function writeReply(Client $client) : bool
    {
        if (!empty($client->getWbuffer())) {
            $wstatus = $this->writen($client->getResource(), $client->getWbuffer());
            if ($wstatus == false) {
                return false;
            }
            $client->setWbuffer('');
            if ($client->getCloseAfterReply()) {
                $this->closeClient($client);
                return false;
            }
        }
        return true;
    }
    
    /**
     * 获取监控进程的响应信息
     */
    public function getReply(Client $client) : bool
    {
        $code = false;
        $status = $this->writeReply($client);
        if ($status == false) {
            return $status;
        }
        if (empty($client->getRbuffer())) {
            $rbuffer = socket_read($client->getResource(), self::MAX_READ_BYTES, PHP_NORMAL_READ);
            if (empty($rbuffer) && $client->getBlockStatus() == self::ANET_NONBLOCK) {
                $errno = socket_last_error($client->getResource());
                if ($errno == SOCKET_EAGAIN || $errno == SOCKET_EINTR) {
                    return true;
                }
            }
            if ($rbuffer !== false && empty($rbuffer)) {
                $this->closeClient($client);
                return false;
            }
            $client->setRbuffer($rbuffer);
            $code = $this->parser->parseCommand($client);
            $client->setRbuffer('');
            $this->writeReply($client);
        }
        return $code;
    }
}