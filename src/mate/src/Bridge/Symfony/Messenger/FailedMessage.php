<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Messenger;

/**
 * What the failed-messages tool knows about one message in a failure transport.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FailedMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $messageClass,
        public readonly ?string $exceptionClass,
        public readonly ?string $exceptionMessage,
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly string $traceAsString,
        public readonly int $retryCount,
        public readonly ?\DateTimeImmutable $firstFailedAt,
        public readonly ?\DateTimeImmutable $lastFailedAt,
        public readonly ?string $originalTransport,
    ) {
    }
}
