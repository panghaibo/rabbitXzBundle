<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq;

interface DequeuerAwareInterface
{
    public function setDequeuer(DequeuerInterface $dequeuer);
}
