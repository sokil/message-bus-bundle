<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Middleware;

use PHPUnit\Framework\TestCase;
use Sokil\MessageBus\Service\TypeLocator;
use Sokil\MessageBus\Stubs\Event\UserCreated;
use Sokil\MessageBus\Stubs\ValueObject\Email;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

class AmqpMessageRoutingKeyByTypeMiddlewareTest extends TestCase
{
    public function testHandle()
    {
        $middleware = new AmqpMessageRoutingKeyByTypeMiddleware(
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

        $envelope = $middleware->handle(
            new Envelope(
                new UserCreated(
                    'aaaaa',
                    new Email('test@test.com'),
                    new \DateTimeImmutable('2022-01-25 01:02:03')
                )
            ),
            new StackMiddleware()
        );

        /** @var AmqpStamp $amqpStamp */
        $amqpStamp = $envelope->last(AmqpStamp::class);

        $this->assertNotNull($amqpStamp);

        $this->assertSame(
            'some-namespace.user.created',
            $amqpStamp->getRoutingKey()
        );
    }
}
