<?php

namespace XiaoZhu\RabbitXzBundle;

use XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler\InjectEventDispatcherPass;
use XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler\RegisterPartsPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RabbitXzBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        
        $container->addCompilerPass(new RegisterPartsPass());
        $container->addCompilerPass(new InjectEventDispatcherPass());
    }
    
    /**
     * {@inheritDoc}
     */
    public function shutdown()
    {
        parent::shutdown();
        if (!$this->container->hasParameter('old_sound_rabbit_mq.base_amqp')) {
            return;
        }
        $connections = $this->container->getParameter('old_sound_rabbit_mq.base_amqp');
        foreach ($connections as $connection) {
            if ($this->container->initialized($connection)) {
                $this->container->get($connection)->close();
            }
        }
    }
}
