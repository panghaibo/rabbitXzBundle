<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq;

class Fallback implements ProducerInterface
{
    public function publish($msgBody, $routingKey = '', $additionalProperties = array())
    {
        return false;
    }
}
