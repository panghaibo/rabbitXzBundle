<?php

namespace XiaoZhu\RabbitXzBundle\Event;

use XiaoZhu\RabbitXzBundle\RabbitMq\Consumer;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class BeforeProcessingMessageEvent
 *
 * @package XiaoZhu\RabbitXzBundle\Command
 */
class BeforeProcessingMessageEvent extends AMQPEvent
{
    const NAME = AMQPEvent::BEFORE_PROCESSING_MESSAGE;

    /**
     * BeforeProcessingMessageEvent constructor.
     *
     * @param AMQPMessage $AMQPMessage
     */
    public function __construct(Consumer $consumer, AMQPMessage $AMQPMessage)
    {
        $this->setConsumer($consumer);
        $this->setAMQPMessage($AMQPMessage);
    }
}
