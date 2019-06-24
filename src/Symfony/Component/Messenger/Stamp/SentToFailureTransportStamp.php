<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp applied when a message is sent to the failure transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class SentToFailureTransportStamp implements StampInterface
{
    private $originalReceiverName;

    public function __construct(string $originalReceiverName)
    {
        $this->originalReceiverName = $originalReceiverName;
    }

    public function getOriginalReceiverName(): string
    {
        return $this->originalReceiverName;
    }
}
