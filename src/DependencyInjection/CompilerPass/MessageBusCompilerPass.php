<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\DependencyInjection\CompilerPass;

use Sokil\MessageBusBundle\Attribute\Message;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MessageBusCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $this->registerTypesFromTags($container);
    }

    private function registerTypesFromTags(ContainerBuilder $container): void
    {
        $eventDefinitions = $container->findTaggedServiceIds(Message::TAG);

        $messageClassNameToTypeMap = [];

        foreach ($eventDefinitions as $serviceId => $tags) {
            $service = $container->getDefinition($serviceId);
            $className = $service->getClass();

            foreach ($tags as $tag) {
                if (empty($tag['type'])) {
                    throw new \InvalidArgumentException('Name not defined in event');
                }

                if (in_array($tag['type'], $messageClassNameToTypeMap)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Type "%s" already defined in message',
                        $tag['type']
                    ));
                }

                $messageClassNameToTypeMap[$className] = $tag['type'];
            }
        }

        if (count($messageClassNameToTypeMap) > 0) {
            $typeLocatorDefinition = $container->getDefinition('sokil.message_bus.type_locator');
            $typeLocatorDefinition->addMethodCall(
                'appendMessageClassNameToTypeMap',
                [$messageClassNameToTypeMap]
            );
        }
    }
}
