<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Producing;

use PHPUnit\Framework\TestCase;
use Sokil\MessageBusBundle\Middleware\AmqpMessageRoutingKeyByTypeMiddleware;
use Sokil\MessageBusBundle\Service\TypeLocator;
use Sokil\MessageBusBundle\Stubs\Event\UserCreated;
use Sokil\MessageBusBundle\Stubs\Event\UserUpdated;
use Sokil\MessageBusBundle\Stubs\ValueObject\Email;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;

class AmqpProduceTest extends TestCase
{
    /**
     * Message has no routing key
     * Exchange config does not declare default routing key
     */
    public function testProduceToDirectExchangeNoRoutingKey()
    {
        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();

        $amqpTransportFactory = new AmqpTransportFactory();
        $amqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            [
                'exchange' => [
                    'name' => $exchangeName,
                    'type' => 'direct',
                    //'default_publish_routing_key' => null,
                ],
                'queues' => [
                    $queueName => [
                        'binding_keys' => ['some_binding_key'],
                    ],
                ]
            ],
            new PhpSerializer()
        );

        $sentEnvelope = $amqpTransport->send(
            new Envelope(
                new UserCreated(
                    '1234567',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-26 01:02:08')
                )
            )
        );

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertNull($receivedEnvelope);
    }

    /**
     * Message has no routing key
     * Exchange config does not declare default routing key
     */
    public function testProduceToDirectExchangeDefaultRoutingKey()
    {
        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();

        $amqpTransportFactory = new AmqpTransportFactory();
        $amqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            [
                'exchange' => [
                    'name' => $exchangeName,
                    'type' => 'direct',
                    'default_publish_routing_key' => 'some_binding_key',
                ],
                'queues' => [
                    $queueName => [
                        'binding_keys' => ['some_binding_key'],
                    ],
                ]
            ],
            new PhpSerializer()
        );

        $sentEnvelope = $amqpTransport->send(
            new Envelope(
                new UserCreated(
                    '1234567',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-26 01:02:08')
                )
            )
        );

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertNotNull($receivedEnvelope);
        $this->assertInstanceOf(UserCreated::class, $receivedEnvelope->getMessage());
    }

    public function testProduceToTopicExchangeNoRoutingKey()
    {
        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();

        $amqpTransportFactory = new AmqpTransportFactory();
        $amqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            [
                'exchange' => [
                    'name' => $exchangeName,
                    'type' => 'topic',
                ],
                'queues' => [
                    $queueName => [
                        'binding_keys' => ['some.*.key'],
                    ],
                ]
            ],
            new PhpSerializer()
        );

        $sentEnvelope = $amqpTransport->send(
            new Envelope(
                new UserCreated(
                    '1234567',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-26 01:02:08')
                )
            )
        );

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertNull($receivedEnvelope);
    }

    public function testProduceToTopicExchangeWithRoutingKey()
    {
        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();

        $amqpTransportFactory = new AmqpTransportFactory();
        $amqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            [
                'exchange' => [
                    'name' => $exchangeName,
                    'type' => 'topic',
                ],
                'queues' => [
                    $queueName => [
                        'binding_keys' => ['some.*.key'],
                    ],
                ]
            ],
            new PhpSerializer()
        );

        $sentEnvelope = $amqpTransport->send(
            new Envelope(
                new UserCreated(
                    '1234567',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-26 01:02:08')
                ),
                [
                    new AmqpStamp('some.usercreated.key')
                ]
            )
        );

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertNotNull($receivedEnvelope);
        $this->assertInstanceOf(UserCreated::class, $receivedEnvelope->getMessage());
    }

    public function testProduceWithGeneratedRoutingKeyFromMessageType()
    {
        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();

        $sendersLocator = new ServiceLocator([
            'amqp' => function() use($exchangeName, $queueName) {
                $amqpTransportFactory = new AmqpTransportFactory();
                return $amqpTransportFactory->createTransport(
                    getenv('AMQP_DSN'),
                    [
                        'exchange' => [
                            'name' => $exchangeName,
                            'type' => 'topic',
                        ],
                        'queues' => [
                            $queueName => [
                                'binding_keys' => ['some-namespace.#'],
                            ],
                        ]
                    ],
                    new PhpSerializer()
                );
            }
        ]);

        $amqpRoutingKeyMiddleware = new AmqpMessageRoutingKeyByTypeMiddleware(
            new TypeLocator(
                [
                    DelayStamp::class => 'delay',
                    BusNameStamp::class => 'busName',
                    SentStamp::class => 'sent',
                    TransportMessageIdStamp::class => 'transportMessageId',
                ],
                [
                    UserCreated::class => 'user.created',
                ],
            ),
            'some-namespace.{messageType}'
        );

        $senderMiddleware = new SendMessageMiddleware(
            new SendersLocator(
                [
                    UserCreated::class => ['amqp'],
                ],
                $sendersLocator
            )
        );

        $messageBus = new MessageBus([
            $amqpRoutingKeyMiddleware,
            $senderMiddleware
        ]);

        $messageBus->dispatch(
            new UserCreated(
                'aaaaa',
                new Email('test@test.com'),
                new \DateTimeImmutable('2022-01-25 01:02:03')
            )
        );

        /** @var TransportInterface $amqpTransport */
        $amqpTransport = $sendersLocator->get('amqp');

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertNotNull($receivedEnvelope);
        $this->assertInstanceOf(UserCreated::class, $receivedEnvelope->getMessage());
    }
}
