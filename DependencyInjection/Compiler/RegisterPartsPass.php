<?php

namespace XiaoZhu\RabbitXzBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterPartsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds('xiao_zhu_rabbit_xz.base_amqp');
        $container->setParameter('xiao_zhu_rabbit_xz.base_amqp', array_keys($services));
        if (!$container->hasDefinition('xiao_zhu_rabbit_xz.parts_holder')) {
            return;
        }

        $definition = $container->getDefinition('xiao_zhu_rabbit_xz.parts_holder');

        $tags = array(
            'xiao_zhu_rabbit_xz.base_amqp',
            'xiao_zhu_rabbit_xz.binding',
            'xiao_zhu_rabbit_xz.producer',
            'xiao_zhu_rabbit_xz.consumer',
            'xiao_zhu_rabbit_xz.multi_consumer',
            'xiao_zhu_rabbit_xz.anon_consumer',
            'xiao_zhu_rabbit_xz.batch_consumer',
            'xiao_zhu_rabbit_xz.rpc_client',
            'xiao_zhu_rabbit_xz.rpc_server',
        );

        foreach ($tags as $tag) {
            foreach ($container->findTaggedServiceIds($tag) as $id => $attributes) {
                $definition->addMethodCall('addPart', array($tag, new Reference($id)));
            }
        }
    }
}
