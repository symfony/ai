<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\Service\ResetInterface;

final class TraceableAgent implements AgentInterface, ResetInterface
{
    public function __construct(
        private readonly AgentInterface $decorated,
        private readonly DataCollector $collector,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $startTime = microtime(true);
        $error = null;
        $response = null;

        try {
            return $response = $this->decorated->call($messages, $options);
        } catch (\Throwable $e) {
            $error = $e;
            throw $e;
        } finally {
            $this->collector->collectAgentCall(
                'call',
                microtime(true) - $startTime,
                $messages,
                $response,
                $error
            );
        }
    }

    public function reset(): void
    {
        if ($this->decorated instanceof ResetInterface) {
            $this->decorated->reset();
        }
    }

    public function getName(): string
    {
        return 'TraceableAgent';
    }
}
