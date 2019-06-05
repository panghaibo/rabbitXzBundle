<?php
/**
 * 随着出问题的进程分发速度会立刻减慢
 * @author ocean
 *
 */

namespace XiaoZhu\RabbitXzBundle\Util;

class RateLimiter
{
    //最大分发进程的时间300s
    const MAX_LIMIT_TIME = 120;
    
    public $queueName;
    
    public $queueNo;
    
    public $nextTime;
    
    public $duration = 20;
    
    public $counter = 0;
    
    public function __construct(string $queueName, int $queueNo)
    {
        $this->queueName = $queueName;
        $this->queueNo = $queueNo;
    }
    
    public function limit() : bool {
        if (empty($this->nextTime)) {
            $this->nextTime = time() + $this->duration;
            return false;
        }
        if (time() < $this->nextTime) {
            return true;
        }
        $this->nextTime = time() + $this->duration;
        return false;
    }
    
}