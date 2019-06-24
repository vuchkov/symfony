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
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    /**
     * @expectedException \TypeError
     * @expectedExceptionMessage Invalid argument provided to "Symfony\Component\Messenger\MessageBus::dispatch()": expected object, but got string.
     */
    public function testItDispatchInvalidMessageType()
    {
        (new MessageBus())->dispatch('wrong');
    }

    public function testItCallsMiddleware()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope, $stack);
            });

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturn($envelope)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
        ]);

        $bus->dispatch($message);
    }

    public function testThatAMiddlewareCanAddSomeStampsToTheEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, [new ReceivedStamp('transport')]);
        $envelopeWithAnotherStamp = $envelope->with(new AnEnvelopeStamp());

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope->with(new AnEnvelopeStamp()), $stack);
            });

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope, $stack);
            });

        $thirdMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $thirdMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
            ->willReturn($envelopeWithAnotherStamp)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
            $thirdMiddleware,
        ]);

        $bus->dispatch($envelope);
    }

    public function testThatAMiddlewareCanUpdateTheMessageWhileKeepingTheEnvelopeStamps()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, $stamps = [new ReceivedStamp('transport')]);

        $changedMessage = new DummyMessage('Changed');
        $expectedEnvelope = new Envelope($changedMessage, $stamps);

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(function ($envelope, $stack) use ($expectedEnvelope) {
                return $stack->next()->handle($expectedEnvelope, $stack);
            });

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($expectedEnvelope, $this->anything())
            ->willReturn($envelope)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
        ]);

        $bus->dispatch($envelope);
    }

    public function testItAddsTheStamps()
    {
        $finalEnvelope = (new MessageBus())->dispatch(new \stdClass(), [new DelayStamp(5), new BusNameStamp('bar')]);
        $this->assertCount(2, $finalEnvelope->all());
    }

    public function testItAddsTheStampsToEnvelope()
    {
        $finalEnvelope = (new MessageBus())->dispatch(new Envelope(new \stdClass()), [new DelayStamp(5), new BusNameStamp('bar')]);
        $this->assertCount(2, $finalEnvelope->all());
    }
}
