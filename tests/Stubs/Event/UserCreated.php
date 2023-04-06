<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Stubs\Event;

use Sokil\MessageBus\Stubs\ValueObject\Email;
use Symfony\Component\Serializer\Annotation\Groups;

class UserCreated
{
    #[Groups(['messenger'])]
    private string $userId;

    #[Groups(['messenger'])]
    private Email $email;

    #[Groups(['messenger'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $userId,
        Email $email,
        \DateTimeImmutable $createdAt
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->createdAt = $createdAt;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * This getter not marked with attribute "messenger" and will not be serialized
     */
    public function getEmailValue(): string
    {
        return $this->email->getValue();
    }
}
