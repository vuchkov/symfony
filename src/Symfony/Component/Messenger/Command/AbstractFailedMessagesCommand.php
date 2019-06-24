<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @internal
 * @experimental in 4.3
 */
abstract class AbstractFailedMessagesCommand extends Command
{
    private $receiverName;
    private $receiver;

    public function __construct(string $receiverName, ReceiverInterface $receiver)
    {
        $this->receiverName = $receiverName;
        $this->receiver = $receiver;

        parent::__construct();
    }

    protected function getReceiverName()
    {
        return $this->receiverName;
    }

    /**
     * @return mixed|null
     */
    protected function getMessageId(Envelope $envelope)
    {
        /** @var TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return null !== $stamp ? $stamp->getId() : null;
    }

    protected function displaySingleMessage(Envelope $envelope, SymfonyStyle $io)
    {
        $io->title('Failed Message Details');

        /** @var SentToFailureTransportStamp|null $sentToFailureTransportStamp */
        $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
        /** @var RedeliveryStamp|null $lastRedeliveryStamp */
        $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);

        $rows = [
            ['Class', \get_class($envelope->getMessage())],
        ];

        if (null !== $id = $this->getMessageId($envelope)) {
            $rows[] = ['Message Id', $id];
        }

        $flattenException = null === $lastRedeliveryStamp ? null : $lastRedeliveryStamp->getFlattenException();
        if (null === $sentToFailureTransportStamp) {
            $io->warning('Message does not appear to have been sent to this transport after failing');
        } else {
            $rows = array_merge($rows, [
                ['Failed at', null === $lastRedeliveryStamp ? '' : $lastRedeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')],
                ['Error', null === $lastRedeliveryStamp ? '' : $lastRedeliveryStamp->getExceptionMessage()],
                ['Error Class', null === $flattenException ? '(unknown)' : $flattenException->getClass()],
                ['Transport', $sentToFailureTransportStamp->getOriginalReceiverName()],
            ]);
        }

        $io->table([], $rows);

        /** @var RedeliveryStamp[] $redeliveryStamps */
        $redeliveryStamps = $envelope->all(RedeliveryStamp::class);
        $io->writeln(' Message history:');
        foreach ($redeliveryStamps as $redeliveryStamp) {
            $io->writeln(sprintf('  * Message failed and redelivered to the <info>%s</info> transport at <info>%s</info>', $redeliveryStamp->getSenderClassOrAlias(), $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')));
        }
        $io->newLine();

        if ($io->isVeryVerbose()) {
            $io->title('Message:');
            $dump = new Dumper($io);
            $io->writeln($dump($envelope->getMessage()));
            $io->title('Exception:');
            $io->writeln(null === $flattenException ? '(no data)' : $flattenException->getTraceAsString());
        } else {
            $io->writeln(' Re-run command with <info>-vv</info> to see more message & error details.');
        }
    }

    protected function printPendingMessagesMessage(ReceiverInterface $receiver, SymfonyStyle $io)
    {
        if ($receiver instanceof MessageCountAwareInterface) {
            if (1 === $receiver->getMessageCount()) {
                $io->writeln('There is <comment>1</comment> message pending in the failure transport.');
            } else {
                $io->writeln(sprintf('There are <comment>%d</comment> messages pending in the failure transport.', $receiver->getMessageCount()));
            }
        }
    }

    protected function getReceiver(): ReceiverInterface
    {
        return $this->receiver;
    }
}
