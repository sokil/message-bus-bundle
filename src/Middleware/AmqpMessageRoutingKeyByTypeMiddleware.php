<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Middleware;

use Sokil\MessageBus\Service\TypeLocator;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Adds {@see AMQPStamp} with routing key only if still no {@see AMQPStamp} present in envelope
 */
class AmqpMessageRoutingKeyByTypeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TypeLocator $typeLocator,
        private string $routingKeyPattern
    ) {
        if (!str_contains($this->routingKeyPattern, '{messageType}')) {
            throw new \InvalidArgumentException('Invalid routing key pattern, "{messageType}" must be present');
        }
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var AmqpStamp|null $amqpStamp */
        $amqpStamp = $envelope->last(AmqpStamp::class);

        if ($amqpStamp === null || empty($amqpStamp->getRoutingKey())) {
            $messageType = $this->typeLocator->getMessageTypeByClassName(get_class($envelope->getMessage()));

            $routingKey = strtr(
                $this->routingKeyPattern,
                [
                    '{messageType}' => $messageType,
                ]
            );

            $envelope = $envelope
                ->withoutAll(AmqpStamp::class)
                ->with(
                    new AmqpStamp(
                        $routingKey,
                        $amqpStamp ? $amqpStamp->getFlags() : \AMQP_NOPARAM,
                        $amqpStamp ? $amqpStamp->getAttributes() : []
                    )
                );
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
