<?php
/**
 * 该类是队列监控进程，常驻进程
 * 1.创建队列资源
 * 2.监控队列任务
 * 3.回收队列资源
 * 
 * @desc 交互协议: 参见Command类
 * @author <panghaibo@xiaozhu.com>
 * @date 2019/01/07
 * @copyright xiaozhu.com all rights reserved
 */
namespace XiaoZhu\RabbitXzBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use XiaoZhu\RabbitXzBundle\Util\Data;
use XiaoZhu\RabbitXzBundle\Util\Anet;
use XiaoZhu\RabbitXzBundle\Util\MonitorException;
use XiaoZhu\RabbitXzBundle\Util\ServerParser;

class QueueMonitorCommand extends Command
{
    protected static $defaultName = 'rabbitmq:monitor';
    
    //linux内核可能没有加大这个参数，所以客户端需要每次连接后断开
    const CLIENT_MAX = 1024;
    
    //接收远程命令的端口
    const MANAGER_PORT = 1314;
    
    /*
     * 产品名字 公司内部唯一
     */
    private $appName;
    
    /*
     * @var string php bin文件的路径
     */
    private $phpBin;
    
    /*
     * @var string 默认php路径
     */
    private $defaultPhpBin = '/usr/lib/php';
    
    /*
     * @var workspace路径
     */
    private $workspace;
    
    /*
     * @var 守护进程的pid
     */
    private $pid;
    
    /*
     * @var 进程当前活跃时间
     */
    private $updateTime;
    
    /*
     * @var string pid文件
     */
    private $pidFile;
    
    /*
     * @var socket unix file name
     */
    private $sockFileName = 'rabbitmonitor';
    
    /*
     * @var 本地管理进程的socket handler
     */
    private $localSocket;
    
    /*
     * @var 远程管理进程的handler
     */
    private $remoteSocket;
    
    /*
     * 服务器上一次错误
     */
    private $lastError;
    
    /*
     * @val int backlog
     */
    private $backlog = 100;
    
    /*
     * @val string 本机IP
     */
    private $ip;
    
    /*
     * 服务的监听端口
     */
    private $port;
    
    private $queueCache = 'cache.txt';//本地缓存远程的队列配置
    
    private $project;
    
    private $network;
    
    /**
     * 命令行配置
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this->setName(static::$defaultName);
        $this->addArgument('php', InputArgument::REQUIRED, 'The PHP Bin File Path Needed');
        $this->addArgument('workspace', InputArgument::REQUIRED, 'The Daemon Workspace Path Needed');
        $this->addArgument('ip', InputArgument::REQUIRED, 'The Daemon Run Enviroment Ip');
        $this->addArgument('app', InputArgument::REQUIRED, 'Your Product Name');
        $this->addArgument('port',InputArgument::OPTIONAL, 'Your App service Tcp Port');
    }
    
    /**
     * restore 本地队列配置缓存
     */
    public function restoreQueueConf() : bool
    {
        $queue = [];
        if (file_exists($this->queueCache)) {
            $queue = unserialize(file_get_contents($this->queueCache));
        }
        if (empty($queue) || !is_array($queue)) {
            return false;
        }
        Data::$runQueue = $queue;
        return true;
    }
    
    /**
     * 队列配置flush到本地文件
     */
    public function flushQueueToFile() : bool
    {
        @unlink($this->queueCache);
        $queue = [];
        if (is_array(Data::$runQueue)) {
            $queue = Data::$runQueue;
        }
        return (int) file_put_contents($this->queueCache, serialize($queue));
    }
    
    /**
     * 执行命令
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);
        while(true)
        {
            Data::$updateMonitor = time();
            $this->updateProcessInfo();
            $this->startQueueCheck();
            $this->network->netLoopApi();
            $this->flushQueueToFile();
            pcntl_signal_dispatch();
        }
    }
    
    public function startNewProcess($queue, $queueNo) : bool
    {
        $command = $this->phpBin;
        $argv = [$this->project .'/bin/console', 'rabbitmq:pigconsumer', $queue, $queueNo, $this->workspace, $this->sockFileName];
        $flag = pcntl_fork();
        if ($flag == 0) {
            $this->network->stop();//子进程关闭所有的描述符
            pcntl_exec($command, $argv);
        } elseif ($flag > 0) {
            Data::$heartBeadts[$queue][$queueNo] = [
                'pid' => $flag,
                'time' => time(),
                'memory' => '0K',
            ];
            Data::$childProcess[$flag] = array('queue' => $queue, 'queueNo' => $queueNo);
        }
        return true;
    }
    
    /**
     * 检查是否有需要启动的队列,新启动的监控可能还未收集到所有work进程的心跳，所以需要等待5分钟
     */
    public function startQueueCheck() : bool
    {
        if (time() - Data::$bornMonitor < 600) {
            return true;
        }
        foreach (Data::$runQueue as $queue => $works) {
            if ($works < 1) continue;
            for ($i = 1; $i <= $works; $i++) {
                if (!isset(Data::$heartBeadts[$queue][$i])) {
                    $this->startNewProcess($queue, $i);
                }
            }
        }
        //启动就挂掉的进程 可能来不及ping服务端
        foreach (Data::$heartBeadts as $queueName => $item) {
            foreach ($item as $queueNo => $info) {
                $pid = $info['pid'];
                $time = $info['time'];
                if (time() - $time > 300) {
                    posix_kill($pid, SIGUSR1);//确保进程死掉
                    if (isset(Data::$heartBeadts[$queueName][$queueNo])) {
                        unset(Data::$heartBeadts[$queueName][$queueNo]);
                    }
                }
            }
        }
        return true;
    }
    
    /**
     * 检测Monitor进程是否正常
     */
    private function processCheck()
    {
        if (file_exists($this->pidFile)) {
            $pInfo = trim(file_get_contents($this->pidFile));
            if (!empty($pInfo)) {
                if ($pInfo != $this->pid)
                {
                    throw MonitorException('管理进程已经存在');
                }
            }
        }
        return true;
    }
    
    /**
     * 更新注册表中进程的活跃时间
     */
    private function updateProcessInfo()
    {
        file_put_contents($this->pidFile, $this->pid);
    }
    
    /**
     * 初始化
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        $this->phpBin = $input->getArgument('php');
        $this->workspace = $input->getArgument('workspace');
        $this->ip = $input->getArgument('ip');
        $this->appName = $input->getArgument('app');
        $this->port = empty($input->getArgument('port')) ? self::MANAGER_PORT : intval($input->getArgument('port'));
        $this->bornTime = Data::$bornMonitor = time();
        $this->pid = getmypid();
        $this->project = $this->getApplication()->getKernel()->getProjectDir();
        //检测对workspace是否有读取权限
        if (!file_exists($this->workspace) || !is_dir($this->workspace) || !is_writable($this->workspace))
        {
            throw new MonitorException("Please Check The ".$this->workspace." Exists Or Writable Or Is Directory");
        }
        if (!extension_loaded('pcntl'))
        {
            throw new MonitorException('PHP Extension Named pnctl Can Not Found');
        }
        //检测监控程序是否异常
        $this->processCheck();
        chdir($this->workspace);
        $this->pidFile = 'monitor.pid';
        /*注册SIGCHLD信号处理防止出现僵尸进程*/
        pcntl_signal(SIGCHLD, array($this, 'chldSignal'));
        pcntl_signal(SIGUSR1, array($this, 'stopMonitor'));
        pcntl_signal(SIGINT, array($this, 'stopMonitor'));
        $this->network = new Anet();
        $parser = new ServerParser();
        $this->network->setParser($parser);
        $this->network->createUnixServer($this->sockFileName);
        $this->network->createTcpServer('0.0.0.0', $this->port);
        $this->restoreQueueConf();
    }
    
    public function chldSignal($signo)
    {
        $status = 0;
        $pid = 0;
        while (0 != ($pid = pcntl_waitpid(-1, $status, WNOHANG))) {
            if ($pid == -1 || pcntl_wifstopped($status) == false) continue;
            if (isset(Data::$childProcess[$pid])) {
                $queue = Data::$childProcess[$pid]['queue'];
                $queueNo = Data::$childProcess[$pid]['queueNo'];
                if (isset(Data::$heartBeadts[$queue][$queueNo])) unset(Data::$heartBeadts[$queue][$queueNo]);
                unset(Data::$childProcess[$pid]);
            }
        }
    }
    
    /**
     * 退出 或者 接收到信号 执行的退出程序
     */
    public function stopMonitor($signo)
    {
        $this->network->stop();
        exit(0);
    }
    
    /**
     * 清理资源
     */
    public function __destruct()
    {
        if (!empty($this->pidFile) && file_exists($this->pidFile)) {
            $pid = trim(file_get_contents($this->pidFile));
            if ($pid == $this->pid) {
                unlink($this->pidFile);
                unlink($this->sockFileName);
            }
        }
    }
}