<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Executor;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Executor that delegates to an AgentInterface.
 *
 * Reads input from the state (configurable key), calls the agent,
 * and writes the result content back to state under a configurable output key.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AgentExecutor implements ExecutorInterface
{
    /**
     * @param non-empty-string $inputKey  State key holding the input prompt/messages
     * @param non-empty-string $outputKey State key to write the agent result into
     */
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly string $inputKey = 'input',
        private readonly string $outputKey = 'output',
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        $input = $state->get($this->inputKey);

        if ($input instanceof MessageBag) {
            $messages = $input;
        } elseif (\is_string($input)) {
            $messages = new MessageBag(Message::ofUser($input));
        } else {
            throw new WorkflowExecutorException(\sprintf('AgentExecutor expects state key "%s" to contain a string or MessageBag, got "%s".', $this->inputKey, get_debug_type($input)));
        }

        try {
            $result = $this->agent->call($messages);
        } catch (\Throwable $e) {
            throw new WorkflowExecutorException(\sprintf('Agent execution failed at place "%s": "%s".', $place, $e->getMessage()), 0, $e);
        }

        return $state->set($this->outputKey, $result->getContent());
    }
}
