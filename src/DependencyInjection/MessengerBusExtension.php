<?php

declare(strict_types=1);

namespace Sokil\MessageBus\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class MessengerBusExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');

        $this->defineTypeLocatorService($config, $container);
        $this->defineSerializerServices($config, $container);
        $this->setAMQPMiddlewarePattern($config, $container);
    }

    private function defineTypeLocatorService(array $config, ContainerBuilder $container)
    {
        // configure type locator
        if (!empty($config['stamps'])) {
            $stampClassNameToTypeMap = [];
            foreach ($config['stamps'] as $stampClassName => $stampConfiguration) {
                $stampClassNameToTypeMap[$stampClassName] = $stampConfiguration['type'] ?? $stampClassName;
            }

            $container
                ->getDefinition('message_bus.type_locator')
                ->setArgument(0, $stampClassNameToTypeMap);
        }

        if (!empty($config['messages'])) {
            $messageClassNameToTypeMap = [];
            foreach ($config['messages'] as $messageClassName => $messageConfiguration) {
                $messageClassNameToTypeMap[$messageClassName] = $messageConfiguration['type'] ?? $messageClassName;
            }

            $container
                ->getDefinition('message_bus.type_locator')
                ->setArgument(1, $messageClassNameToTypeMap);
        }
    }

    private function defineSerializerServices(array $config, ContainerBuilder $container): void
    {
        if (empty($config['serializers'])) {
            return;
        }

        foreach ($config['serializers'] as $serializerName => $serializerConfig) {
            // build normalizers
            $normalizers = [];
            foreach ($serializerConfig['normalizers'] as $normalizerClassName) {
                if (!$container->hasDefinition($normalizerClassName)) {
                    $container->setDefinition($normalizerClassName, new Definition($normalizerClassName));
                }

                $normalizers[] = new Reference($normalizerClassName);
            }

            // create serializer
            $container->setDefinition(
                sprintf(
                    'message_bus.serializer.%s.%s',
                    $serializerName,
                    $serializerConfig['format']
                ),
                new Definition(
                    $serializerConfig['class'],
                    [
                        $container->getDefinition('message_bus.type_locator'),
                        $normalizers,
                        $serializerConfig['format']
                    ]
                )
            );
        }
    }

    private function setAMQPMiddlewarePattern(array $config, ContainerBuilder $container): void
    {
        $pattern = $config['middlewares']['amqp_message_routing_key_by_type']['pattern'] ?? null;

        if (!empty($pattern)) {
            $middlewareDefinition = $container->getDefinition(
                'message_bus.middleware.amqp_message_routing_key_by_type'
            );

            $middlewareDefinition->setArgument(1, $pattern);
        }
    }
}
