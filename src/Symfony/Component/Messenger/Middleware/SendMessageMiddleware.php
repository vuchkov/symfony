<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @experimental in 4.3
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    private $sendersLocator;
    private $eventDispatcher;

    public function __construct(SendersLocatorInterface $sendersLocator, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->sendersLocator = $sendersLocator;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = [
            'message' => $envelope->getMessage(),
            'class' => \get_class($envelope->getMessage()),
        ];

        $sender = null;

        if ($envelope->all(ReceivedStamp::class)) {
            // it's a received message, do not send it back
            $this->logger->info('Received message {class}', $context);
        } else {
            /** @var RedeliveryStamp|null $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            // dispatch event unless this is a redelivery
            $shouldDispatchEvent = null === $redeliveryStamp;
            foreach ($this->getSenders($envelope, $redeliveryStamp) as $alias => $sender) {
                if (null !== $this->eventDispatcher && $shouldDispatchEvent) {
                    $event = new SendMessageToTransportsEvent($envelope);
                    $this->eventDispatcher->dispatch($event);
                    $envelope = $event->getEnvelope();
                    $shouldDispatchEvent = false;
                }

                $this->logger->info('Sending message {class} with {sender}', $context + ['sender' => \get_class($sender)]);
                $envelope = $sender->send($envelope->with(new SentStamp(\get_class($sender), \is_string($alias) ? $alias : null)));
            }
        }

        if (null === $sender) {
            return $stack->next()->handle($envelope, $stack);
        }

        // message should only be sent and not be handled by the next middleware
        return $envelope;
    }

    /**
     * * @return iterable|SenderInterface[]
     */
    private function getSenders(Envelope $envelope, ?RedeliveryStamp $redeliveryStamp): iterable
    {
        if (null !== $redeliveryStamp) {
            return [
                $redeliveryStamp->getSenderClassOrAlias() => $this->sendersLocator->getSenderByAlias($redeliveryStamp->getSenderClassOrAlias()),
            ];
        }

        return $this->sendersLocator->getSenders($envelope);
    }
}
