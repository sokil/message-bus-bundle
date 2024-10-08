<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\DependencyInjection;

use Sokil\MessageBusBundle\Serializer\PortableSerializer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('message_bus');
        $rootNode = $treeBuilder->getRootNode();

        $this->configureMessages($rootNode);
        $this->configureStamps($rootNode);
        $this->configureSerializers($rootNode);
        $this->configureMiddlewares($rootNode);

        return $treeBuilder;
    }

    private function configureMessages(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('messages')
                    ->useAttributeAsKey('messageClassName')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('type')->isRequired()->end()
                        ->end()
                    ->end()
                ->end();
    }

    private function configureStamps(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('stamps')
                    ->defaultValue([])
                    ->useAttributeAsKey('messageClassName')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('type')->isRequired()->end()
                        ->end()
                    ->end()
                ->end();
    }

    private function configureSerializers(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('serializers')
                    ->useAttributeAsKey('serializer')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->defaultValue(PortableSerializer::class)
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        return !class_exists($v)
                                            || !is_subclass_of($v, SerializerInterface::class, true);
                                    })
                                    ->thenInvalid(
                                        sprintf(
                                            'Serializer must be instance of "%s"',
                                            SerializerInterface::class
                                        )
                                    )
                                ->end()
                            ->end()
                            ->scalarNode('format')->defaultValue('json')->end()
                            ->arrayNode('normalizers')
                                ->useAttributeAsKey('class')
                                ->normalizeKeys(false)
                                ->scalarPrototype()
                                    ->validate()
                                        ->ifTrue(function ($v) {
                                            if (!class_exists($v)) {
                                                return false;
                                            }

                                            return !is_subclass_of($v, NormalizerInterface::class, true)
                                                && !is_subclass_of($v, DenormalizerInterface::class, true);
                                        })
                                        ->thenInvalid(
                                            sprintf(
                                                'Normalizer must be instance of "%s"',
                                                NormalizerInterface::class
                                            )
                                        )
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
    }

    private function configureMiddlewares(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('middlewares')
                    ->children()
                        ->arrayNode('amqp_message_routing_key_by_type')
                            ->children()
                                ->scalarNode('pattern')
                                    ->defaultValue('{messageType}')
                                    ->validate()
                                        ->ifTrue(
                                            function ($v) {
                                                return !str_contains($v, '{messageType}');
                                            }
                                        )
                                        ->thenInvalid('Pattern must contain "{messageType}"')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
    }
}
