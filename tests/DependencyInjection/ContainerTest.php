<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sokil\MessageBusBundle\DependencyInjection\CompilerPass\MessageBusCompilerPass;
use Sokil\MessageBusBundle\Middleware\AmqpMessageRoutingKeyByTypeMiddleware;
use Sokil\MessageBusBundle\Service\TypeLocator;
use Sokil\MessageBusBundle\Stubs\Event\UserCreated;
use Sokil\MessageBusBundle\Stubs\Event\UserUpdated;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class ContainerTest extends TestCase
{
    private function buildContainer(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();

        // add compiler pass
        $containerBuilder->addCompilerPass(new MessageBusCompilerPass());

        // configure extension
        $extension = new MessageBusExtension();
        $containerBuilder->registerExtension($extension);

        // read app config
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
    Sokil\MessageBusBundle\Stubs\Event\UserCreated:
        type: user.created
serializers:
    portable:
        class: Sokil\MessageBusBundle\Serializer\PortableSerializer
        format: json
        normalizers:
            - Sokil\MessageBusBundle\Stubs\Normalizer\EmailNormalizer
middlewares:
    amqp_message_routing_key_by_type:
        pattern: "some-namespace.{messageType}"
CONFIG;

        $containerBuilder->loadFromExtension(
            $extension->getAlias(),
            Yaml::parse($yamlConfig)
        );

        // load app services
        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(\dirname(__DIR__) . '/Resources/config')
        );

        $loader->load('services_test.yaml');

        // build container
        $containerBuilder
            ->addCompilerPass(new class () implements CompilerPassInterface {
                public function process(ContainerBuilder $container)
                {
                    $ids = [
                        'sokil.message_bus.type_locator',
                        'sokil.message_bus.middleware.amqp_message_routing_key_by_type',
                        'sokil.message_bus.serializer.portable.json',
                    ];

                    foreach ($ids as $id) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            })
            ->compile();

        return $containerBuilder;
    }

    public function testTypeLocator()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('sokil.message_bus.type_locator');

        $this->assertInstanceOf(TypeLocator::class, $typeLocator);
    }

    public function testSerializer()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('sokil.message_bus.serializer.portable.json');

        $this->assertInstanceOf(SerializerInterface::class, $typeLocator);
    }

    public function testAmqpMiddleware()
    {
        $container = $this->buildContainer();

        $typeLocator = $container->get('sokil.message_bus.middleware.amqp_message_routing_key_by_type');

        $this->assertInstanceOf(AmqpMessageRoutingKeyByTypeMiddleware::class, $typeLocator);
    }

    public function testConfigureTypeByConfig(): void
    {
        $container = $this->buildContainer();

        /** @var TypeLocator $typeLocator */
        $typeLocator = $container->get('sokil.message_bus.type_locator');

        $this->assertSame(
            'user.created',
            $typeLocator->getMessageTypeByClassName(UserCreated::class)
        );
    }

    public function testConfigureTypeByAttribute(): void
    {
        $container = $this->buildContainer();

        /** @var TypeLocator $typeLocator */
        $typeLocator = $container->get('sokil.message_bus.type_locator');

        $this->assertSame(
            'user.updated',
            $typeLocator->getMessageTypeByClassName(UserUpdated::class)
        );
    }
}
