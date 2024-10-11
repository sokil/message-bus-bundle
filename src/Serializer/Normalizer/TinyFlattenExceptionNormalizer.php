<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Serializer\Normalizer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
\Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Original FlattenExceptionNormalizer used to normalize exception data and put it to redelivery stamp.
 * After few redelivery tries this stamp may contain a lot of data and crash handling of message on some transports.
 *
 * This is replacement for original normalizer, used because there is to option to disable listener
 * `AddErrorDetailsStampListener` which adds this stamp to message.
 *
 * @see \Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer
 * @see \Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener
 */
final class TinyFlattenExceptionNormalizer implements DenormalizerInterface, NormalizerInterface
{
    /**
     * @param FlattenException $object
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $normalized = [
            'message' => $object->getMessage(),
            'code' => $object->getCode(),
            'class' => $object->getClass(),
            'file' => $object->getFile(),
            'line' => $object->getLine(),
            'trace' => [],
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
        $object->setClass($data['class']);
        $object->setFile($data['file']);
        $object->setLine($data['line']);
        $object->setTrace($data['trace'], $data['file'], $data['line']);

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return FlattenException::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            FlattenException::class => true,
        ];
    }
}
