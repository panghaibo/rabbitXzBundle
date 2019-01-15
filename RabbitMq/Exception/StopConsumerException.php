<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq\Exception;
use XiaoZhu\RabbitXzBundle\RabbitMq\ConsumerInterface;

/**
 * If this exception is thrown in consumer service the message
 * will not be ack and consumer will stop
 * if using demonized, ex: supervisor, the consumer will actually restart
 * Class StopConsumerException
 * @package XiaoZhu\RabbitXzBundle\RabbitMq\Exception
 */
class StopConsumerException extends \RuntimeException
{
    public function getHandleCode()
    {
        return ConsumerInterface::MSG_SINGLE_NACK_REQUEUE;
    }

}

