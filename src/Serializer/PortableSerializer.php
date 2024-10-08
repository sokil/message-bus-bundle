<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Sokil\MessageBusBundle\Serializer\Normalizer\TinyFlattenExceptionNormalizer;
use Sokil\MessageBusBundle\Service\TypeLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerTransportSerializerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class PortableSerializer implements MessengerTransportSerializerInterface
{
    private const MESSAGE_TYPE_HEADER_NAME = 'X-Message-Type';
    private const CONTENT_TYPE_NAME = 'Content-Type';
    private const STAMP_HEADER_NAME_PREFIX = 'X-Message-Stamp-';

    private const CONTENT_TYPE_MAP = [
        JsonEncoder::FORMAT => 'application/json',
    ];

    private SerializerInterface $serializer;

    public function __construct(
        private TypeLocator $typeLocator,
        array $normalizers,
        private string $format,
    ) {
        switch ($format) {
            case JsonEncoder::FORMAT:
                $encoders = [new JsonEncoder()];
                break;
            default:
                throw new \InvalidArgumentException('Format not supported');
        }

        $this->serializer = new Serializer(
            array_merge(
                $normalizers,
                [
                    new DateTimeNormalizer(),
                    new ArrayDenormalizer(),
                    new TinyFlattenExceptionNormalizer(),
                    new ObjectNormalizer(
                        new ClassMetadataFactory(new AttributeLoader())
                    ),
                ]
            ),
            $encoders
        );
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        /** @psalm-var array{body: string, headers: array<string, string>} $encodedEnvelope */
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body"');
        }

        $messageType = $encodedEnvelope['headers'][self::MESSAGE_TYPE_HEADER_NAME] ?? null;
        if (empty($messageType)) {
            throw new MessageDecodingFailedException('Can not find message type in envelope headers');
        }

        try {
            $messageClassName = $this->typeLocator->getMessageClassNameByType($messageType);
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Message type is unknown', (int) $e->getCode(), $e);
        }

        try {
            $stamps = $this->decodeStamps($encodedEnvelope);
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Error decoding stamps', (int) $e->getCode(), $e);
        }

        try {
            $message = $this->serializer->deserialize(
                $encodedEnvelope['body'],
                $messageClassName,
                $this->format
            );
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Error decoding message', (int) $e->getCode(), $e);
        }

        if (!is_object($message)) {
            throw new MessageDecodingFailedException(
                sprintf(
                    'Deserialized result must be object, "%s" found',
                    gettype($message)
                )
            );
        }

        return (new Envelope($message, $stamps));
    }

    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $messageType = $this->typeLocator->getMessageTypeByClassName(\get_class($envelope->getMessage()));

        $headers = [
            self::MESSAGE_TYPE_HEADER_NAME => $messageType,
            self::CONTENT_TYPE_NAME => self::CONTENT_TYPE_MAP[$this->format] ?? 'text/plain',
        ];

        $headers += $this->encodeStamps($envelope);

        $body = $this->serializer->serialize(
            $envelope->getMessage(),
            $this->format,
            [
                'groups' => ['messenger'],
                \Symfony\Component\Messenger\Transport\Serialization\Serializer::MESSENGER_SERIALIZATION_CONTEXT
            ]
        );

        return [
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * @psalm-param array{body: string, headers: array<string, string>} $encodedEnvelope
     *
     * @psalm-return array<int, StampInterface>
     */
    private function decodeStamps(array $encodedEnvelope): array
    {
        /** @psalm-var array<int, StampInterface> $allStamps */
        $allStamps = [];

        foreach ($encodedEnvelope['headers'] as $envelopeHeaderName => $envelopeHeaderValue) {
            if (!str_starts_with($envelopeHeaderName, self::STAMP_HEADER_NAME_PREFIX)) {
                continue;
            }

            $stampType = lcfirst(substr($envelopeHeaderName, \strlen(self::STAMP_HEADER_NAME_PREFIX)));

            try {
                $stampClassName = $this->typeLocator->getStampClassNameByType($stampType);

                /** @var array<int, StampInterface> $stamps */
                $stamps = $this->serializer->deserialize(
                    $envelopeHeaderValue,
                    $stampClassName . '[]',
                    $this->format
                );
            } catch (\Throwable $e) {
                throw new MessageDecodingFailedException('Could not decode stamp', (int) $e->getCode(), $e);
            }

            $allStamps = array_merge($allStamps, $stamps);
        }

        return $allStamps;
    }

    private function encodeStamps(Envelope $envelope): array
    {
        /** @psalm-var array<string, StampInterface[]> $allStamps */
        $allStamps = $envelope->all();

        if (!$allStamps) {
            return [];
        }

        $headers = [];
        foreach ($allStamps as $stampClassName => $stamps) {
            $stampType = $this->typeLocator->getStampTypeByClassName($stampClassName);
            $headers[self::STAMP_HEADER_NAME_PREFIX . ucfirst($stampType)] = $this->serializer->serialize(
                $stamps,
                $this->format
            );
        }

        return $headers;
    }
}
