<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle\Transport\Dummy;

use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class DummyMessengerTransport implements TransportInterface
{
    /**
     * Sends the given envelope.
     *
     * The sender can read different stamps for transport configuration,
     * like delivery delay.
     *
     * If applicable, the returned Envelope should contain a TransportMessageIdStamp.
     */
    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }

    /**
     * Receives some messages.
     *
     * While this method could return an unlimited number of messages,
     * the intention is that it returns only one, or a "small number"
     * of messages each time. This gives the user more flexibility:
     * they can finish processing the one (or "small number") of messages
     * from this receiver and move on to check other receivers for messages.
     * If this method returns too many messages, it could cause a
     * blocking effect where handling the messages received from one
     * call to get() takes a long time, blocking other receivers from
     * being called.
     *
     * If applicable, the Envelope should contain a TransportMessageIdStamp.
     *
     * If a received message cannot be decoded, the message should not
     * be retried again (e.g. if there's a queue, it should be removed)
     * and a MessageDecodingFailedException should be thrown.
     *
     * @throws TransportException If there is an issue communicating with the transport
     *
     * @return Envelope[]
     */
    public function get(): iterable
    {
        throw new \LogicException('Consuming not allowed');
    }

    /**
     * @param Envelope $envelopeAcknowledges that the passed message was handled.
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function ack(Envelope $envelope): void
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Called when handling the message failed and it should not be retried.
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function reject(Envelope $envelope): void
    {
        throw new \Exception('Not implemented');
    }
}
