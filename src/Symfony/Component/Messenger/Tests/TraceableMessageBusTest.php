<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\TraceableMessageBus;

class TraceableMessageBusTest extends TestCase
{
    public function testItTracesDispatch()
    {
        $message = new DummyMessage('Hello');

        $stamp = new DelayStamp(5);
        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message, [$stamp])->willReturn(new Envelope($message));

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $traceableBus->dispatch($message, [$stamp]);
        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        $this->assertEquals([
            'message' => $message,
            'stamps' => [[$stamp]],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesDispatchWithEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = (new Envelope($message))->with($stamp = new AnEnvelopeStamp());

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($envelope)->willReturn($envelope);

        $traceableBus = new TraceableMessageBus($bus);
        $line = __LINE__ + 1;
        $traceableBus->dispatch($envelope);
        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        $this->assertEquals([
            'message' => $message,
            'stamps' => [[$stamp]],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }

    public function testItTracesExceptions()
    {
        $message = new DummyMessage('Hello');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->once())->method('dispatch')->with($message)->willThrowException($exception = new \RuntimeException('Meh.'));

        $traceableBus = new TraceableMessageBus($bus);

        try {
            $line = __LINE__ + 1;
            $traceableBus->dispatch($message);
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $tracedMessages = $traceableBus->getDispatchedMessages());
        $actualTracedMessage = $tracedMessages[0];
        unset($actualTracedMessage['callTime']); // don't check, too variable
        $this->assertEquals([
            'message' => $message,
            'exception' => $exception,
            'stamps' => [],
            'caller' => [
                'name' => 'TraceableMessageBusTest.php',
                'file' => __FILE__,
                'line' => $line,
            ],
        ], $actualTracedMessage);
    }
}
