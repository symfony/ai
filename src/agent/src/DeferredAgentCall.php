<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * An agent call dispatched to the platform but not yet awaited.
 *
 * Produced by {@see DeferrableAgentInterface::prepare()} and consumed by
 * {@see DeferrableAgentInterface::finish()}. It carries the post-input-processing
 * context the agent needs to run its output processors once the result is awaited.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DeferredAgentCall
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly DeferredResult $deferredResult,
        public readonly string $model,
        public readonly MessageBag $messages,
        public readonly array $options = [],
    ) {
    }
}
