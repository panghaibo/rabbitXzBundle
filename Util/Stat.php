<?php
/**
 * 队列用到的一些统计方法都封装在这个类中
 */
namespace XiaoZhu\RabbitXzBundle\Util;

class Stat
{
    const GB = 1073741824;
    
    const MB = 1048576;
    
    const KB = 1024;
    
    /**
     * 获取当前内存占用
     */
    public static function memoryUseage($readable = true) : string
    {
        $memory = memory_get_usage();
        if (($memory / self::GB) > 1) {
            return round(($memory / self::GB), 2) . 'GB';
        } elseif (($memory / self::MB) > 1) {
            return round(($memory / self::MB), 2) . 'MB';
        } elseif (($memory / self::KB) > 1) {
            return round(($memory / self::KB), 2) . 'KB';
        }
        return $memory . 'B';
    }
}