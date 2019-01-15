<?php

namespace XiaoZhu\RabbitXzBundle\Command;

use Symfony\Component\Console\Input\InputArgument;

class MultipleConsumerCommand extends BaseConsumerCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Executes a consumer that uses multiple queues')
                ->setName('rabbitmq:multiple-consumer')
                ->addArgument('context', InputArgument::OPTIONAL, 'Context the consumer runs in')
        ;
    }

    protected function getConsumerService()
    {
        return 'xiao_zhu_rabbit_xz.%s_multiple';
    }

    protected function initConsumer($input)
    {
        parent::initConsumer($input);
        $this->consumer->setContext($input->getArgument('context'));
    }
}