<?php

namespace XiaoZhu\RabbitXzBundle\Command;

class AnonConsumerCommand extends BaseConsumerCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('rabbitmq:anon-consumer');
        $this->setDescription('Executes an anonymous consumer');
        $this->getDefinition()->getOption('messages')->setDefault(1);
        $this->getDefinition()->getOption('route')->setDefault('#');

    }

    protected function getConsumerService()
    {
        return 'xiao_zhu_rabbit_xz.%s_anon';
    }
}
