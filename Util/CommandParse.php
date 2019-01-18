<?php
namespace XiaoZhu\RabbitXzBundle\Util;

class CommandParse
{
    const PING = 'PING';//心跳监控
    
    const SHUT = 'SHUT'; //队列进程停止工作
    
    const REBOOT = 'REBOOT';
    
    const REGISTER = 'REGISTER';//监控进程第一次启动的时候需要向监控中心注册自己
    
    const QUEUE = 'QUEUE';//本机器需要启动的队列
    
    const REBOOTQUEUE = 'REBOOTQUEUE';//本机器需要重启的队列
    
    const STOP = 'STOP';//本机器需要立刻停止的队列
    
    const MONITOR = 'MONITOR';//监控中心需要监控进程提供本机器的监控状态
    
    const ERROR = 'ERROR'; //错误消息 后面紧接着错误
    
    const OK = 'OK'; //正确执行
    
    const STAT = 'STAT';//监控统计
    
    const QUIT = 'QUIT'; //客户端退出命令
    
    const ADD = 'ADD';//新增执行队列
    
    const EXIT = 'EXIT';
    
    private $network;
    
    public function setNetwork(Anet $network)
    {
        $this->network = $network;
    }
    
    /**
     * 根据消息文本解析消息
     * @param string $command
     */
    public function parseCommand($socket, string $command) : bool
    {
        $command = trim($command);
        if ($command == self::QUIT) {
            return $this->quitCommand($socket);
        } elseif ($command == self::STAT) {
            return $this->statCommand($socket, '');
        }else {
            $prefix = strtolower(substr($command, 0, strpos($command, ' ')));
            $suffix = substr($command, strpos($command, ' ') + 1);
        }
        $commandMethod = $prefix.'Command';
        if (!method_exists(__CLASS__, $commandMethod)) {
            return true;
        }
        return call_user_func(array($this, $commandMethod), $socket, $suffix);
    }
    
    public function okCommand($socket) :bool
    {
        return true;
    }
    
    public function errorCommand($socket) :bool
    {
        return false;
    }
    
    public function quitCommand($socket) :bool
    {
        $replay = "OK server close connection\n";
        Anet::writen($socket, $replay);
        $this->network->closeClient($socket);
        return true;
    }
    
    public function exitCommand($socket, string $suffix) : bool
    {
        $command = explode(' ', $suffix);
        $queueName = $queueNo = $pid = $bornTime = null;
        if (count($command) == 4)  list($queueName, $queueNo, $pid, $bornTime) = $command;
        if (empty($queueName) || $queueNo === null || empty($pid) || empty($bornTime)) {
            $replay = "ERROR bad exit command\n";
        } elseif (!isset(Data::$runQueue[$queueName]) || Data::$runQueue[$queueName] < $queueNo) {
            $replay = "OK this work stop right now\n";
        } elseif (isset(Data::$rebootQueue[$queueName]) && Data::$rebootQueue[$queueName] > $bornTime) {
            $replay = "OK this work stop right now\n";
        } else {
            $replay = "ERROR do nothing\n";
        }
        Anet::writen($socket, $replay);
        return true;
    }
    
    public function pingCommand($socket, string $suffix) : bool
    {
        $command = explode(' ', $suffix);
        $queueName = $queueNo = $pid = $memory = null;
        if (count($command) == 4)  list($queueName, $queueNo, $pid, $memory) = $command;
        if (empty($queueName) || $queueNo === null || empty($pid)) {
            $replay = "ERROR bad command\n";
        } elseif (!isset(Data::$runQueue[$queueName])) {
            $replay = "ERROR this queue already remove\n";
        } else {
            Data::$heartBeadts[$queueName][$queueNo] = ['time' => time(), 'pid' => $pid, 'memory' => $memory];
            $replay = "OK ping success\n";
        }
        Anet::writen($socket, $replay);
        return true;
    }
    
    public function addCommand($socket, string $suffix) :bool
    {
        $command = explode(' ', $suffix);
        $queueName = $queueNo = null;
        if (count($command) == 2) list($queueName, $queueNos) = $command;
        $replay = "OK add queue success\n";
        if (empty($queueName) || !is_numeric($queueNos)) {
            $replay = "ERROR bad command\n";
        } else {
            if ($queueNos > 0) {
                Data::$runQueue[$queueName] = intval($queueNos);
            } elseif (isset(Data::$runQueue[$queueName])) {
                unset(Data::$runQueue[$queueName]);
            }
        }
        Anet::writen($socket, $replay);
        return true;
    }
    
    /**
     * 查看当前管理进程的状态
     * @param unknown $socket
     * @param string $suffix
     * @return bool
     */
    public function statCommand($socket, string $suffix) : bool
    {
        $queue = $suffix;
        if (!empty($queue)) {
            $head = <<<DESC
         %s basic infomation:
         ########################################################################################
         需要启动的进程数量: %d   实际启动的work进程数量:%d
         ########################################################################################
         work列表：\n
DESC;
            $works = 0;
            $actWorks = 0;
            $str = '';
            if (isset(Data::$heartBeadts[$queue])) {
                $actWorks = count(Data::$heartBeadts[$queue]);
                foreach (Data::$heartBeadts[$queue] as $queueNo => $info) {
                    $pid = $info['pid'];
                    $time = date('Y-m-d H:i:s', $info['time']);
                    $mem = $info['memory'];
                    $str .= "\t\t 编号: $queueNo  上次心跳时间：$time  进程编号: $pid  占用内存:$mem\n";
                }
            }
            $head .= $str;
            if (isset(Data::$runQueue[$queue])) $works = Data::$runQueue[$queue];
            $replay = sprintf($head, $queue, $works, $actWorks);
        } else {
            $head = <<<DESC
         Monitor basic infomation:
         ########################################################################################
         启动时间: %s 活跃时间:%s 启动的队列: %d 内存占用: %s
         ########################################################################################
         启动队列：\n    
DESC;
            $str = '';
            foreach (Data::$runQueue as $queue => $number) {
                $hearts = isset(Data::$heartBeadts[$queue]) ? count(Data::$heartBeadts[$queue]) : 0;
                if (strlen($queue) < 20) $queue = str_pad($queue, 20);
                $str .= "\t\t队列key: $queue 本机器需要work数量: $number   实际work数量：$hearts\n";
            }
            $head .= $str;
            $memUse = Stat::memoryUseage();
            $replay = sprintf($head, date('Y-m-d H:i:s', Data::$bornMonitor), date('Y-m-d H:i:s', Data::$updateMonitor), count(Data::$runQueue), $memUse);  
        }       
        Anet::writen($socket, $replay);
        return true;
    }
    
    public function rebootCommand($socket, string $suffix) :bool
    {
        $queueName = $suffix;
        $replay = " OK add reboot queue\n";
        if (empty($queueName)) {
            $replay = "ERROR bad command\n";
        } elseif (!isset(Data::$runQueue[$queueName])) {
            $replay = "ERROR no queue named $queueName run this server\n";
        } else {
            Data::$rebootQueue[$queueName] = time();
        }
        Anet::writen($socket, $replay);
        return true;
    }
    
    /**
     * 发送命令
     */
    public function sendCommand($socket, string $command) : bool
    {
        $res = $this->network->socketCanWrite($socket);
        if ($res == false) return false;
        $res = $this->network->writen($socket, $command);
        if (empty($res)) return false;
        $res = $this->network->socketCanRead($socket);
        if (empty($res)) return false;
        $ack = socket_read($socket, Anet::MAX_READ_BYTES, PHP_NORMAL_READ);
        if (empty($ack)) return false;
        return $this->parseCommand($socket, $ack);
    }
}