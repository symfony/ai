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
 * An orchestrated multi-agent system that coordinates multiple specialized agents.
 *
 * This agent acts as a central orchestrator, delegating tasks to specialized agents
 * based on handoff rules and managing the conversation flow between agents.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final class OrchestratedMultiAgent implements AgentInterface
{
    /**
     * @param array<string, AgentInterface> $agents Map of agent names to agent instances
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private HandoffConfiguration $configuration,
        private array $agents = [],
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
        $currentMessages = $messages;
        $currentAgent = $this->orchestrator;

        while (true) {
            // Get response from current agent
            $result = $currentAgent->call($currentMessages, $options);
            $content = $result->getContent();

            // Check if we need to handoff to another agent
            $triggeredRule = $this->configuration->findTriggeredRule($content);
            
            if (null === $triggeredRule) {
                // No handoff needed, return the result
                return $result;
            }

            // Prepare for handoff
            $agentName = $triggeredRule->getAgentName();
            if (!isset($this->agents[$agentName])) {
                throw new RuntimeException(sprintf('Agent "%s" not found in agent registry.', $agentName));
            }
            $currentAgent = $this->agents[$agentName];
            
            // Add the current response to the message history
            $currentMessages = $currentMessages->with(new Message($content, 'assistant'));
            
            // Add delegation prompt if available
            if (null !== $triggeredRule->getPrompt()) {
                $currentMessages = $currentMessages->with(new Message($triggeredRule->getPrompt(), 'user'));
            } elseif (null !== $this->configuration->getDelegationPrompt()) {
                $currentMessages = $currentMessages->with(new Message($this->configuration->getDelegationPrompt(), 'user'));
            }
        }

    }




    public function getConfiguration(): HandoffConfiguration
    {
        return $this->configuration;
    }

    public function getOrchestrator(): AgentInterface
    {
        return $this->orchestrator;
    }

    /**
     * @return array<string, AgentInterface>
     */
    public function getAgents(): array
    {
        return $this->agents;
    }
}