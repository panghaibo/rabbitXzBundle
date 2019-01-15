<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq;

use PhpAmqpLib\Message\AMQPMessage;

interface BatchConsumerInterface
{
    /**
     * @param   AMQPMessage[]   $messages
     *
     * @return  array|bool
     */
    public function batchExecute(array $messages);
}
