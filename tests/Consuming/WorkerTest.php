<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Consuming;

use Sokil\MessageBus\Serializer\PortableSerializer;
use Sokil\MessageBus\Service\TypeLocator;
use Sokil\MessageBus\Stubs\Event\UserCreated;
use Sokil\MessageBus\Stubs\Normalizer\EmailNormalizer;
use Sokil\MessageBus\Stubs\ValueObject\Email;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class WorkerTest extends TestCase
{
    public function testConsumeRedeliveryOnHandleError()
    {
        $message = new UserCreated(
            '1234567',
            new Email('test@test.com'),
            new \DateTimeImmutable('2022-01-26 01:02:08')
        );

        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();
        $transportName = 'amqp_transport';

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
            new PortableSerializer(
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
                    ]
                ),
                [
                    new EmailNormalizer(),
                ],
                JsonEncoder::FORMAT
            )
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $eventDispatcher->addSubscriber(
            new SendFailedMessageForRetryListener(
                new ServiceLocator([
                    $transportName => function() use ($amqpTransport) {
                        return $amqpTransport;
                    }
                ]),
                new ServiceLocator([
                    $transportName => function() {
                        return new MultiplierRetryStrategy();
                    }
                ])
            )
        );

        $handlersLocator = $this->createMock(HandlersLocatorInterface::class);
        $handlersLocator->method('getHandlers')->willReturn(
            [
                new HandlerDescriptor(
                    function () {
                        throw new \RuntimeException('Handle exception');
                    }
                )
            ]
        );

        $amqpTransport->send(
            new Envelope($message)
        );

        $worker = new Worker(
            [
                $transportName => $amqpTransport,
            ],
            new MessageBus([
                new HandleMessageMiddleware(
                    $handlersLocator
                )
            ]),
            $eventDispatcher
        );

        $worker->run();

        // wait for re-enqueue
        sleep(2);

        /** @var \Generator $receivedEnvelopeGenerator */
        $receivedEnvelopeGenerator = $amqpTransport->get();

        /** @var Envelope $receivedEnvelope */
        $receivedEnvelope = $receivedEnvelopeGenerator->current();

        $this->assertEquals($message, $receivedEnvelope->getMessage());
    }

    public function testConsumeRedeliveryOnUnserializeError()
    {
        $this->expectException(MessageDecodingFailedException::class);

        $message = new UserCreated(
            '1234567',
            new Email('test@test.com'),
            new \DateTimeImmutable('2022-01-26 01:02:08')
        );

        $exchangeName = __METHOD__ . time();
        $queueName = __METHOD__ . time();
        $transportName = 'amqp_transport';

        $transportOptions = [
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
        ];

        $portableSerializer = new PortableSerializer(
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
                ]
            ),
            [
                new EmailNormalizer(),
            ],
            JsonEncoder::FORMAT
        );

        $amqpTransportFactory = new AmqpTransportFactory();

        // transport started on producer microservice
        $producerAmqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            $transportOptions,
            new PhpSerializer()
        );

        $producerAmqpTransport->send(new Envelope($message));

        // transport started on consumer microservice
        $consumerAmqpTransport = $amqpTransportFactory->createTransport(
            getenv('AMQP_DSN'),
            $transportOptions,
            $portableSerializer
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $eventDispatcher->addSubscriber(
            new SendFailedMessageForRetryListener(
                new ServiceLocator([
                    $transportName => function() use ($consumerAmqpTransport) {
                        return $consumerAmqpTransport;
                    }
                ]),
                new ServiceLocator([
                    $transportName => function() {
                        return new MultiplierRetryStrategy();
                    }
                ])
            )
        );

        $handlersLocator = $this->createMock(HandlersLocatorInterface::class);
        $handlersLocator->method('getHandlers')->willReturn(
            [
                new HandlerDescriptor(function () {})
            ]
        );

        $worker = new Worker(
            [
                $transportName => $consumerAmqpTransport,
            ],
            new MessageBus([
                new HandleMessageMiddleware(
                    $handlersLocator
                )
            ]),
            $eventDispatcher
        );

        $worker->run();
    }
}
