<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq\Exception;


use XiaoZhu\RabbitXzBundle\RabbitMq\ConsumerInterface;

class AckStopConsumerException extends StopConsumerException
{
    public function getHandleCode()
    {
        return ConsumerInterface::MSG_ACK;
    }

}
