<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Stubs\Event;

use Sokil\MessageBusBundle\Attribute\Message;
use Sokil\MessageBusBundle\Stubs\ValueObject\Email;
use Symfony\Component\Serializer\Annotation\Groups;

#[Message(type: 'user.updated')]
class UserUpdated
{
    #[Groups(['messenger'])]
    private string $userId;

    #[Groups(['messenger'])]
    private Email $email;

    #[Groups(['messenger'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $userId,
        Email $email,
        \DateTimeImmutable $updatedAt
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->updatedAt = $updatedAt;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * This getter not marked with attribute "messenger" and will not be serialized
     */
    public function getEmailValue(): string
    {
        return $this->email->getValue();
    }
}
