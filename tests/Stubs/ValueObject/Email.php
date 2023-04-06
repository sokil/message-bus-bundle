<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Stubs\ValueObject;

class Email implements \Stringable
{
    private string $value;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $value)
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException('value must be valid email');
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public static function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
