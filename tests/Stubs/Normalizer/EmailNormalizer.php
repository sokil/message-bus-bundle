<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Stubs\Normalizer;

use Sokil\MessageBus\Stubs\ValueObject\Email;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EmailNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if (!$object instanceof Email) {
            throw new InvalidArgumentException(sprintf('Invalid object passed, must be %s', Email::class));
        }

        return $object->getValue();
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof Email;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = [])
    {
        if (!is_string($data)) {
            throw new UnexpectedValueException('UUID must be passed as string');
        }

        try {
            return new Email($data);
        } catch (\Throwable $e) {
            throw new UnexpectedValueException('Invalid UUID string representation', 0, $e);
        }
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return is_a($type, Email::class, true);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
