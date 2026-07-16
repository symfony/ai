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
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Implemented by agents whose call can be split into a non-blocking dispatch and a blocking await.
 *
 * This enables concurrent execution — for example running several parallel workflow places whose
 * platform requests overlap on the wire.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface DeferrableAgentInterface extends AgentInterface
{
    /**
     * Runs the input processors and invokes the platform, without awaiting the result.
     *
     * @param array<string, mixed> $options
     */
    public function prepare(MessageBag $messages, array $options = []): DeferredAgentCall;

    /**
     * Awaits a prepared call and runs the output processors.
     */
    public function finish(DeferredAgentCall $call): ResultInterface;
}
