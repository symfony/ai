<?php

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

final class TraceableAgent implements AgentInterface, ResetInterface
{
    public function __construct(
        private readonly AgentInterface $decorated,
        private readonly DataCollector $collector,
        private readonly RequestStack $requestStack,
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
            if ($this->requestStack->getMainRequest() === $this->requestStack->getCurrentRequest()) {
                $this->collector->collectChatCall(
                    'call',
                    microtime(true) - $startTime,
                    $messages,
                    $response,
                    $error
                );
            }
        }
    }

    public function getName(): string
    {
        return $this->decorated->getName();
    }

    public function reset(): void
    {
        if ($this->decorated instanceof ResetInterface) {
            $this->decorated->reset();
        }
    }
}
