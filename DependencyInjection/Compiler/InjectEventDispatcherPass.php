<?php

namespace XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class InjectEventDispatcherPass
 *
 * @package XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler
 */
class InjectEventDispatcherPass implements CompilerPassInterface
{
    const EVENT_DISPATCHER_SERVICE_ID = 'event_dispatcher';

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::EVENT_DISPATCHER_SERVICE_ID)) {
            return;
        }
        $taggedConsumers = $container->findTaggedServiceIds('xiao_zhu_rabbit_xz.base_amqp');

        foreach ($taggedConsumers as $id => $tag) {
            $definition = $container->getDefinition($id);
            $definition->addMethodCall(
                'setEventDispatcher',
                array(
                    new Reference(self::EVENT_DISPATCHER_SERVICE_ID, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                )
            );
        }

    }
}
