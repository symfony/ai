<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MultiAgent;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A multi-agent system that coordinates multiple specialized agents.
 *
 * This agent acts as a central orchestrator, delegating tasks to specialized agents
 * based on handoff rules and managing the conversation flow between agents.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final class MultiAgent implements AgentInterface
{
    /**
     * @param array<string, AgentInterface> $agents Map of agent names to agent instances
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private array $agents,
        private HandoffConfig $config,
        private string $name = 'multi-agent',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface When the agent encounters an error during orchestration or handoffs
     */
    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $currentAgent = $this->orchestrator;

        while (true) {
            // Get response from current agent
            $result = $currentAgent->call($messages, $options);
            $content = $result->getContent();

            // Check if LLM response mentions any agent name for handoff
            $targetAgentName = null;
            foreach (array_keys($this->agents) as $agentName) {
                if (str_contains(strtolower($content), strtolower($agentName))) {
                    $targetAgentName = $agentName;
                    break;
                }
            }
            
            if (null === $targetAgentName) {
                // No handoff needed, return the result
                return $result;
            }

            // Prepare for handoff
            $currentAgent = $this->agents[$targetAgentName];
            
            // Add the current response to the message history
            $messages = $messages->with(new Message($content, 'assistant'));
            
            // Add delegation prompt
            $messages = $messages->with(new Message($this->config->getDelegationPrompt(), 'user'));
        }
    }

}