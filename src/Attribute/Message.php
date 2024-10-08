<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class Message
{
    public const TAG = 'messenger.message';

    public function __construct(
        public ?string $type,
    ) {
    }
}
