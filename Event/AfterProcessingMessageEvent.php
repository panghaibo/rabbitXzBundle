<?php

namespace XiaoZhu\RabbitXzBundle\Event;

use XiaoZhu\RabbitXzBundle\RabbitMq\Consumer;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class AfterProcessingMessageEvent
 *
 * @package XiaoZhu\RabbitXzBundle\Event
 */
class AfterProcessingMessageEvent extends AMQPEvent
{
    const NAME = AMQPEvent::AFTER_PROCESSING_MESSAGE;

    /**
     * AfterProcessingMessageEvent constructor.
     *
     * @param AMQPMessage $AMQPMessage
     */
    public function __construct(Consumer $consumer, AMQPMessage $AMQPMessage)
    {
        $this->setConsumer($consumer);
        $this->setAMQPMessage($AMQPMessage);
    }
}
