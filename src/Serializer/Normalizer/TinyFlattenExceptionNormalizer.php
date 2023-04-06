<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Serializer\Normalizer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Original FlattenExceptionNormalizer used to normalize exception data and put it to redelivery stamp.
 * After few redelivery tries this stamp may contain a lot of data and crash handling of message on some transports.
 *
 * This is replacement for original normaliser, used because there is to option to disable listener
 * `AddErrorDetailsStampListener` which adds this stamp to message.
 *
 * @see \Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer
 * @see \Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener
 */
final class TinyFlattenExceptionNormalizer implements DenormalizerInterface, ContextAwareNormalizerInterface
{
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $normalized = [
            'message' => $object->getMessage(),
            'code' => $object->getCode(),
        ];

        return $normalized;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): FlattenException
    {
        $object = new FlattenException();

        $object->setMessage($data['message']);
        $object->setCode($data['code']);

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return FlattenException::class === $type;
    }
}
