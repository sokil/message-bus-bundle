<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Serializer;

use PHPUnit\Framework\TestCase;
use Sokil\MessageBusBundle\Stubs\Event\UserCreated;
use Sokil\MessageBusBundle\Stubs\Normalizer\EmailNormalizer;
use Sokil\MessageBusBundle\Stubs\ValueObject\Email;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer as SymfonyTransportSerializer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer as PhpTransportSerializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class StandardSerializerTest extends TestCase
{
    private function buildSymfonyTransportSerializer(): SymfonyTransportSerializer
    {
        return new SymfonyTransportSerializer(
            new Serializer(
                [
                    new EmailNormalizer(),
                    new DateTimeNormalizer(),
                    new ArrayDenormalizer(),
                    new ObjectNormalizer(
                        new ClassMetadataFactory(
                            new AttributeLoader()
                        )
                    ),
                ],
                [new JsonEncoder()]
            ),
            'json',
            [
                'groups' => ['messenger'],
            ]
        );
    }

    private function buildPhpTransportSerializer(): PhpTransportSerializer
    {
        return new PhpTransportSerializer();
    }

    public function testEncodeWithSymfonySerializer()
    {
        $serializer = $this->buildSymfonyTransportSerializer();

        $serializedEnvelope = $serializer->encode(
            new Envelope(
                new UserCreated(
                    'abcdef',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-26 10:16:00')
                ),
                [
                    new AmqpStamp('some-routing-key'),
                    new BusNameStamp('event.bus'),
                    new TransportMessageIdStamp(42)
                ]
            )
        );

        $this->assertEquals(
            [
                'body' => '{"userId":"abcdef","email":"test@test.com","createdAt":"2022-01-26T10:16:00+00:00"}',
                'headers' => [
                    'type' => 'Sokil\MessageBusBundle\Stubs\Event\UserCreated',
                    'X-Message-Stamp-Symfony\Component\Messenger\Stamp\BusNameStamp' => '[[]]',
                    'X-Message-Stamp-Symfony\Component\Messenger\Stamp\TransportMessageIdStamp' => '[[]]',
                    'Content-Type' => 'application/json',
                ],
            ],
            $serializedEnvelope
        );
    }
}
