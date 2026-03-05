<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

/**
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class CallbackStreamListener extends AbstractStreamListener
{
    /**
     * @param \Closure(): void $onComplete
     */
    public function __construct(private readonly \Closure $onComplete)
    {
    }

    public function onComplete(CompleteEvent $event): void
    {
        ($this->onComplete)();
    }
}
