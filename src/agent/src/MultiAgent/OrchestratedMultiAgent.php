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
    public function __construct(
        private AgentInterface $orchestratorAgent,
        private HandoffConfiguration $configuration,
        private string $name = 'orchestrated-multi-agent',
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
        $handoffCount = 0;
        $currentAgent = $this->orchestratorAgent;

        while ($handoffCount < $this->configuration->getMaxHandoffs()) {
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
            $handoffCount++;
            $currentAgent = $triggeredRule->getTargetAgent();
            
            // Add the current response to the message history
            $currentMessages = $currentMessages->with(new Message($content, 'assistant'));
            
            // Add delegation prompt if available
            if (null !== $triggeredRule->getPrompt()) {
                $currentMessages = $currentMessages->with(new Message($triggeredRule->getPrompt(), 'user'));
            } elseif (null !== $this->configuration->getDelegationPrompt()) {
                $currentMessages = $currentMessages->with(new Message($this->configuration->getDelegationPrompt(), 'user'));
            }
        }

        throw new RuntimeException(sprintf('Maximum handoffs (%d) exceeded during multi-agent orchestration.', $this->configuration->getMaxHandoffs()));
    }

    /**
     * Create a new orchestrated multi-agent with a different configuration.
     */
    public function withConfiguration(HandoffConfiguration $configuration): self
    {
        return new self($this->orchestratorAgent, $configuration, $this->name);
    }

    /**
     * Create a new orchestrated multi-agent with a different orchestrator.
     */
    public function withOrchestrator(AgentInterface $orchestrator): self
    {
        return new self($orchestrator, $this->configuration, $this->name);
    }

    /**
     * Create a new orchestrated multi-agent with a different name.
     */
    public function withName(string $name): self
    {
        return new self($this->orchestratorAgent, $this->configuration, $name);
    }

    public function getConfiguration(): HandoffConfiguration
    {
        return $this->configuration;
    }

    public function getOrchestratorAgent(): AgentInterface
    {
        return $this->orchestratorAgent;
    }
}