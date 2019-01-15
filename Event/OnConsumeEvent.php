<?php

namespace XiaoZhu\RabbitXzBundle\Event;

use XiaoZhu\RabbitXzBundle\RabbitMq\Consumer;

/**
 * Class OnConsumeEvent
 *
 * @package XiaoZhu\RabbitXzBundle\Command
 */
class OnConsumeEvent extends AMQPEvent
{
    const NAME = AMQPEvent::ON_CONSUME;

    /**
     * OnConsumeEvent constructor.
     *
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer)
    {
        $this->setConsumer($consumer);
    }
}
