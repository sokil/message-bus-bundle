# Message Bus Bundle

Tools for Symfony Messenger.

* [Installation](#installation)
* [Symfony Bundle Configuration](#symfony-bundle-configuration)
* [Transports](#transports)
  * [Dummy Transport](#dummy-transport)
* [Serializing messages](#serializing-messages)
  * [Default serializers](#default-serializers)
  * [Message type](#message-type)
  * [Portable serializer](#portable-serializer)
    * [Motivation](#motivation)
    * [Serializer service configuration](#serializer-service-configuration)
    * [Message mapping](#message-mapping)
* [Middlewares](#middlewares)
  * [Building AMQP routing key from message type](#building-amqp-routing-key-from-message-type)
* [Producing messages to external system](#producing-messages-to-external-system)
* [Consuming messages from external system](#consuming-messages-from-external-system)

## Installation

```
composer req sokil/message-bus-bundle
```

### Symfony Bundle Configuration

If you are using Symfony Framework, you may register library as Symfony Bundle,
and it automatically configures services for you. Add bundle to your `config/bundles.php`:

```php
<?php

return [
  // ...
  Sokil\MessageBusBundle\MessageBusBundle::class => ['all' => true],
];
```

## Transports

### Dummy Transport

This transport accepts envelopes and skip them on send, and throw error when try to consume something.

Use dummy transport with prefix `dummy://`

## Serializing messages

### Default serializers

`Symfony Messenger` contains `PhpSerializer` which use standard `serialize/unserialize` php functions, and `Serializer` 
which use Symfony serializer, but also generates serialized data with fully qualified PHP class names. 

This may be fine for cases when producer and consumer both running on same codebase, but there may be fails of 
unserialization when consuming occurred after release where class was renamed.

So for safe and cross-platform communication we need serializer completely independent of PHP serialization process 
and runtime.

### Message type

To separate serialised message from PHP runtime and serialization functions, we need to replace message and stamp class name 
with message and stamp string types. 

This is done in service `\Sokil\MessageBusBundle\Service\TypeLocator`:

```php
$typeLocator = new TypeLocator(
    [
        DelayStamp::class => 'delay',
        BusNameStamp::class => 'busName',
        SentStamp::class => 'sent',
        TransportMessageIdStamp::class => 'transportMessageId',
    ],
    [
        UserCreated::class => 'user.created',
    ],
)
```

If you use Symfony framework, this already done in service `sokil.message_bus.type_locator`.
To define mapping from class names to types, add it to framework configuration in `config/packages/message_bus.yaml`:

```yaml
message_bus:
    stamps:
        Symfony\Component\Messenger\Stamp\DelayStamp:
            type: delay
        Symfony\Component\Messenger\Stamp\BusNameStamp:
            type: busName
        Symfony\Component\Messenger\Stamp\SentStamp:
            type: sent
        Symfony\Component\Messenger\Stamp\TransportMessageIdStamp:
            type: transportMessageId
        Symfony\Component\Messenger\Stamp\ErrorDetailsStamp:
            type: errorDetails
        Symfony\Component\Messenger\Stamp\RedeliveryStamp:
            type: redelivery
    messages:
        App\User\Event\UserCreated:
            type: user.created
```

Also you may add attribute to your event instead of configuring in packages config:

```php
<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Stubs\Event;

use Sokil\MessageBusBundle\Attribute\Message;

#[Message(type: 'user.updated')]
class UserUpdated
{
}
```

### Portable serializer

#### Motivation

Portable serializer produces data completely independent of PHP serialization process
and runtime.

For example:

```php
[
    'headers' => [
        'X-Message-Type' => 'user.created',
        'Content-Type' => 'application/json',
        'X-Message-Stamp-BusName' => '[{"busName":"event.bus"}]',
        'X-Message-Stamp-TransportMessageId' => '[{"id":42}]',
    ],
    'body' => '{"userId":"abcdef","email":"test@test.com","createdAt":"2022-01-26T10:16:00+00:00"}',
]
```

#### Serializer service configuration

If Symfony Framework used, place configuration to your `config/packages/message_bus.yaml`:

```yaml
message_bus:
    serializers:
        some_serializer:
            class: Sokil\MessageBusBundle\Serializer\PortableSerializer
            format: json
            normalizers:
                - Sokil\MessageBusBundle\Stubs\Normalizer\EmailNormalizer
```

This creates service `sokil.message_bus.serializer.some_serializer.json`.

This serializer then may be configured for using with messenger transport in `config/packages/messenger.yaml`:

```
framework:
    messenger:
        transports:
            # https://symfony.com/doc/current/messenger.html#transport-configuration
            amqp:
                dsn: '%env(AMQP_MESSENGER_TRANSPORT_DSN)%'
                serializer: sokil.message_bus.serializer.some_serializer.json
                options:
                    exchange:
                        name: user.events
                        type: topic
                    queues: []
```

#### Message mapping

Above serializer serializes only fields annotated with `messenger` group.

```php
class UserCreated
{
    #[Groups(['messenger'])]
    private Uuid $userId;
}
```

Field name serialized as is. To prevent side effects of PHP class property rename, you may define serialized field name explicitly:

```php
class UserCreated
{
    #[Groups(['messenger'])]
    #[SerializedName('userId')]
    private Uuid $userId;
}
```

## Middlewares

### Building AMQP routing key from message type

Envelope without explicitly defined `AmqpStamp` will be produced with null routing key. This means it will not be sent 
to any queue.

With `\Sokil\MessageBusBundle\Middleware\AmqpMessageRoutingKeyByTypeMiddleware` we may generate routing key 
automatically by its [message type](#message-type) defined in  `message_bus.messages` parameter of Symfony Configuration. 

Routing key generated by pattern based on message type. This pattern may be 
configured in `config/packages/message_bus.yaml`:

```yaml
message_bus:
    middlewares:
        amqp_message_routing_key_by_type:
            pattern: "some-namespace.{messageType}"
```

When used in Symfony framework, service `sokil.message_bus.middleware.amqp_message_routing_key_by_type` 
defined automatically.

To use middleware, configure it to message bus in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        default_bus: command.bus
        
        buses:
            event.bus:
                default_middleware: allow_no_handlers
                middleware:
                    - 'sokil.message_bus.middleware.amqp_message_routing_key_by_type'
```

## Producing messages to external system

By default, Symfony messenger expects that every message has at lease one handler. 
When message dispatched to external system, there are no handlers in out system, so we need to define bus 
which allows no handlers in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```

If we use AMQP transport, it by default require bind exchange to queue explicitly, and it automatically creates 
exchange and queue if they not configured.

So we need to configure exchange without queues bound to in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            # https://symfony.com/doc/current/messenger.html#transport-configuration
            async:
                dsn: '%env(ASYNC_MESSENGER_TRANSPORT_DSN)%'
                serializer: sokil.message_bus.serializer.some_serializer.json
                options:
                    exchange:
                        name: user.events
                        type: topic
                    queues: []
```

Also, you need to configure portable serializer in `framework.messenger.transports.$.serializer` key because by default 
Messenger uses serializer that just serializes PHP objects by standard `serialize` function, so consumers in other 
systems written in other languages will not able to unserialize this message.

Every producing service needs to create own exchange, no other service may use this exchange to produce own messages.

## Consuming messages from external system

To consume external messages from AMQP we need to configure transport in `config/packages/messenger.yaml`::

```yaml
framework:
    messenger:
        transports:
            external_messages:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                serializer: sokil.message_bus.serializer.some_serializer.json
                options:
                    auto_setup: false
                    queues:
                        messages_from_external: ~
```

Now you may run worker to consume messages:

Note that [message types](#message-type) must be configured for all messages we expect to consume.

Queue must be bound by hand because `auto_setup: false` prevents it from automatic creation. 

```bash
$ php bin/console messenger:consume -vv external_messages
```
