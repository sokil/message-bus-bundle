<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Serializer;

use PHPUnit\Framework\TestCase;
use Sokil\MessageBus\Service\TypeLocator;
use Sokil\MessageBus\Stubs\Event\UserCreated;
use Sokil\MessageBus\Stubs\Normalizer\EmailNormalizer;
use Sokil\MessageBus\Stubs\ValueObject\Email;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

class PortableSerializerTest extends TestCase
{
    private function buildPortableSerializer(): PortableSerializer
    {
        return new PortableSerializer(
            new TypeLocator(
                [
                    DelayStamp::class => 'delay',
                    BusNameStamp::class => 'busName',
                    SentStamp::class => 'sent',
                    TransportMessageIdStamp::class => 'transportMessageId',
                    ErrorDetailsStamp::class => 'errorDetails',
                    RedeliveryStamp::class => 'redelivery',
                ],
                [
                    UserCreated::class => 'user.created',
                ],
            ),
            [
                new EmailNormalizer(),
            ],
            'json'
        );
    }

    public function testEncode()
    {
        $portableSerializer = $this->buildPortableSerializer();

        $serializedEnvelope = $portableSerializer->encode(
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

        $this->assertSame(
            [
                'headers' => [
                    'X-Message-Type' => 'user.created',
                    'Content-Type' => 'application/json',
                    'X-Message-Stamp-BusName' => '[{"busName":"event.bus"}]',
                    'X-Message-Stamp-TransportMessageId' => '[{"id":42}]',
                ],
                'body' => '{"userId":"abcdef","email":"test@test.com","createdAt":"2022-01-26T10:16:00+00:00"}',
            ],
            $serializedEnvelope
        );
    }

    public function testDecode()
    {
        $portableSerializer = $this->buildPortableSerializer();

        $unserializedEnvelope = $portableSerializer->decode(
            [
                'headers' => [
                    'X-Message-Type' => 'user.created',
                    'Content-Type' => 'application/json',
                    'X-Message-Stamp-BusName' => '[{"busName":"event.bus"}]',
                    'X-Message-Stamp-TransportMessageId' => '[{"id":42}]',
                ],
                'body' => '{"userId":"abcdef","email":"test@test.com","createdAt":"2022-01-26T10:16:00+00:00"}',
            ]
        );

        /** @var UserCreated $userCreated */
        $userCreated = $unserializedEnvelope->getMessage();

        $this->assertInstanceOf(UserCreated::class, $userCreated);
        $this->assertSame('abcdef', $userCreated->getUserId());
        $this->assertSame('abcdef', $userCreated->getUserId());
        $this->assertSame('test@test.com', $userCreated->getEmailValue());
        $this->assertSame(
            '2022-01-26T10:16:00+00:00',
            $userCreated->getCreatedAt()->format(\DateTimeImmutable::RFC3339)
        );

        $this->assertSame(42, $unserializedEnvelope->last(TransportMessageIdStamp::class)->getId());
        $this->assertSame('event.bus', $unserializedEnvelope->last(BusNameStamp::class)->getBusName());
    }

    public function testSerializeRedeliveredWithErrorDetailsStamp()
    {
        $exception = new \RuntimeException('Some exception');
        $portableSerializer = $this->buildPortableSerializer();

        $originalEnvelope = new Envelope(
            new UserCreated(
                'abcdef',
                new Email('test@test.com'),
                new \DateTimeImmutable('2022-01-26 10:16:00')
            ),
            [
                ErrorDetailsStamp::create($exception)
            ]
        );

        $serialisedEnvelope = $portableSerializer->encode($originalEnvelope);

        $deserializedEnvelope = $portableSerializer->decode($serialisedEnvelope);

        $this->assertEquals($originalEnvelope->getMessage(), $deserializedEnvelope->getMessage());
    }
}
