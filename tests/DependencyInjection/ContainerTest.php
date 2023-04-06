<?php

declare(strict_types=1);

namespace Sokil\MessageBus\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sokil\MessageBus\Middleware\AmqpMessageRoutingKeyByTypeMiddleware;
use Sokil\MessageBus\Service\TypeLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class ContainerTest extends TestCase
{
    private function buildContainer(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();

        $extension = new MessengerBusExtension();
        $containerBuilder->registerExtension($extension);

        $yamlConfig = <<<CONFIG
stamps:
    Symfony\Component\Messenger\Stamp\DelayStamp:
        type: delay
    Symfony\Component\Messenger\Stamp\BusNameStamp:
        type: busName
    Symfony\Component\Messenger\Stamp\SentStamp:
        type: sent
    Symfony\Component\Messenger\Stamp\TransportMessageIdStamp:
        type: transportMessageId
messages:
    App\User\Event\UserCreated:
        type: user.created
serializers:
    portable:
        class: Sokil\MessageBus\Serializer\PortableSerializer
        format: json
        normalizers:
            - Sokil\MessageBus\Stubs\Normalizer\EmailNormalizer
middlewares:
    amqp_message_routing_key_by_type:
        pattern: "some-namespace.{messageType}"
CONFIG;

        $containerBuilder->loadFromExtension(
            $extension->getAlias(),
            Yaml::parse($yamlConfig)
        );

        // build container
        $containerBuilder
            ->addCompilerPass(new class() implements CompilerPassInterface {
                public function process(ContainerBuilder $container)
                {
                    foreach ($container->getDefinitions() as $definition) {
                        $definition->setPublic(true);
                    }
                }

            })
            ->compile();

        return $containerBuilder;
    }

    public function testTypeLocator()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('message_bus.type_locator');

        $this->assertInstanceOf(TypeLocator::class, $typeLocator);
    }

    public function testSerializer()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('message_bus.serializer.portable.json');

        $this->assertInstanceOf(SerializerInterface::class, $typeLocator);
    }

    public function testAmqpMiddleware()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('message_bus.middleware.amqp_message_routing_key_by_type');

        $this->assertInstanceOf(AmqpMessageRoutingKeyByTypeMiddleware::class, $typeLocator);
    }
}
