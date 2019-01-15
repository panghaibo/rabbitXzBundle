<?php

namespace XiaoZhu\RabbitXzBundle;

use XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler\InjectEventDispatcherPass;
use XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler\RegisterPartsPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class XiaoZhuRabbitXzBundle extends Bundle
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
        if (!$this->container->hasParameter('xiao_zhu_rabbit_xz.base_amqp')) {
            return;
        }
        $connections = $this->container->getParameter('xiao_zhu_rabbit_xz.base_amqp');
        foreach ($connections as $connection) {
            if ($this->container->initialized($connection)) {
                $this->container->get($connection)->close();
            }
        }
    }
}
