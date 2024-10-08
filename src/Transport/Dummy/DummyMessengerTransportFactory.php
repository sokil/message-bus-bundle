<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Transport\Dummy;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @implements TransportFactoryInterface<DummyMessengerTransport>
 */
class DummyMessengerTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new DummyMessengerTransport();
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'dummy://');
    }
}
