<?php
namespace XiaoZhu\RabbitXzBundle\Util;

class ServerParser extends Parser
{
    /**
     * 解析客户端命令
     * {@inheritDoc}
     * @see \XiaoZhu\RabbitXzBundle\Util\Parser::parseCommand()
     */
    public function parseCommand(Client $client) : bool
    {
        $command = trim($client->getRbuffer());
        if (empty($command)) {
            return false;
        }
        $client->setRbuffer('');
        if ($command == self::QUIT) {
            return $this->quitCommand($client);
        } elseif ($command == self::STAT) {
            return $this->statCommand($client, '');
        }else {
            $prefix = strtolower(substr($command, 0, strpos($command, ' ')));
            $suffix = substr($command, strpos($command, ' ') + 1);
        }
        $commandMethod = $prefix.'Command';
        if (!method_exists(__CLASS__, $commandMethod)) {
            $client->setWbuffer("ERROR bad command\n");
            return true;
        }
        return call_user_func(array($this, $commandMethod), $client, $suffix);
    }
    
    public function quitCommand(Client $client) :bool
    {
        $reply = "OK server close connection\n";
        $client->setWbuffer($reply);
        $client->setCloseAfterReply();
        return true;
    }
    
    public function exitCommand(Client $client, string $suffix) : bool
    {
        $command = explode(' ', $suffix);
        $queueName = $queueNo = $pid = $bornTime = null;
        if (count($command) == 4)  list($queueName, $queueNo, $pid, $bornTime) = $command;
        if (empty($queueName) || $queueNo === null || empty($pid) || empty($bornTime)) {
            $replay = "ERROR bad exit command\n";
            $client->setWbuffer($replay);
            return true;
        }
        $currentPid = null;
        if (isset(Data::$heartBeadts[$queueName][$queueNo])) {
            $currentPid = Data::$heartBeadts[$queueName][$queueNo]['pid'];
        }
        if (!isset(Data::$runQueue[$queueName]) || Data::$runQueue[$queueName] < $queueNo) {
            $replay = "OK this work stop right now\n";
        } elseif (isset(Data::$rebootQueue[$queueName]) && (($currentPid != null && $currentPid != $pid) || (Data::$rebootQueue[$queueName] > $bornTime))) {
            $replay = "OK this work stop right now\n";
        } else {
            $replay = "ERROR do nothing\n";
        }
        $client->setWbuffer($replay);
        return true;
    }
    
    public function pingCommand(Client $client, string $suffix) : bool
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
        $client->setWbuffer($replay);
        return true;
    }
    
    public function addCommand(Client $client, string $suffix) :bool
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
        $client->setWbuffer($replay);
        return true;
    }
    
    public function rebootCommand($client, string $suffix) :bool
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
        $client->setWbuffer($replay);
        return true;
    }
    
    /**
     * 查看当前管理进程的状态
     * @param unknown $socket
     * @param string $suffix
     * @return bool
     */
    public function statCommand($client, string $suffix) : bool
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
        $client->setWbuffer($replay);
        return true;
    }
}