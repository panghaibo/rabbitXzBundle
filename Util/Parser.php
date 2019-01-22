<?php
namespace XiaoZhu\RabbitXzBundle\Util;

abstract class Parser
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
    
    abstract public function parseCommand(Client $client) : bool;
}