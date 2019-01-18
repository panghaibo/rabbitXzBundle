<?php
/**
 * 管理数据的类
 * 该类的作用是管理监控数据
 * 其他地方使用请注意内存管理
 */
namespace XiaoZhu\RabbitXzBundle\Util;

class Data
{   
    /*
     * @var array 本项目本机器 运行的队列程序
     */
    public static $runQueue = [];
    
    /* 
     * @var array 本项目本机器需要重启的队列程序
     */
    public static $rebootQueue = [];
    
    /*
     * 本机器队列的心跳监控数据
     */
    public static $heartBeadts = [];
    
    public static $bornMonitor;
    
    public static $updateMonitor;
}