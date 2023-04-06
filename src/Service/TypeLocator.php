<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Service;

class TypeLocator
{
    /**
     * @var string[]
     *
     * @psalm-var array<string, string> $stampClassNameToTypeMap
     */
    private array $stampClassNameToTypeMap;

    /**
     * @var string[]
     *
     * @psalm-var array<string, string> $messageClassNameToTypeMap
     */
    private array $messageClassNameToTypeMap;

    /**
     * @psalm-param array<string, string> $stampClassNameToTypeMap
     * @psalm-param array<string, string> $messageClassNameToTypeMap
     */
    public function __construct(
        array $stampClassNameToTypeMap,
        array $messageClassNameToTypeMap
    ) {
        $this->stampClassNameToTypeMap = $stampClassNameToTypeMap;
        $this->messageClassNameToTypeMap = $messageClassNameToTypeMap;
    }

    public function getStampClassNameByType(string $stampType): string
    {
        $stampClassName = array_search($stampType, $this->stampClassNameToTypeMap);
        if ($stampClassName === false) {
            throw new \RuntimeException(sprintf('Unknown stamp type "%s"', $stampType));
        }

        return $stampClassName;
    }

    public function getStampTypeByClassName(string $stampClassName): string
    {
        $stampType = $this->stampClassNameToTypeMap[$stampClassName] ?? null;
        if (empty($stampType)) {
            throw new \RuntimeException(sprintf('Unknown stamp class name "%s"', $stampClassName));
        }

        return $stampType;
    }

    public function getMessageClassNameByType(string $messageType): string
    {
        $messageClassName = array_search($messageType, $this->messageClassNameToTypeMap);
        if ($messageClassName === false) {
            throw new \RuntimeException(sprintf('Unknown message type "%s"', $messageType));
        }

        return $messageClassName;
    }

    public function getMessageTypeByClassName(string $messageClassName): string
    {
        $messageType = $this->messageClassNameToTypeMap[$messageClassName] ?? null;
        if (empty($messageType)) {
            throw new \RuntimeException(sprintf('Unknown message class name "%s"', $messageClassName));
        }

        return $messageType;
    }
}
