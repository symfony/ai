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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
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
     * @param Handoff[]           $handoffs Handoff definitions for agent routing
     * @param non-empty-string    $name     Name of the multi-agent
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private array $handoffs,
        private string $name = 'multi-agent',
        private LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $handoffs) {
            throw new InvalidArgumentException('Handoffs array cannot be empty.');
        }

        if (\count($handoffs) < 2) {
            throw new InvalidArgumentException('MultiAgent requires at least 2 handoffs. For a single handoff, use the agent directly.');
        }
    }

    /**
     * @return non-empty-string
     */
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
        $userMessages = $messages->withoutSystemMessage();

        // Ask orchestrator which agent to target using JSON response format
        $userText = self::extractUserMessage($userMessages);
        $this->logger->debug('MultiAgent: Processing user message', ['user_text' => $userText]);

        // Log available handoffs and agents
        $agentDetails = array_map(fn ($handoff) => [
            'to' => $handoff->getTo()->getName(),
            'when' => $handoff->getWhen(),
        ], $this->handoffs);
        $this->logger->debug('MultiAgent: Available agents for routing', ['agents' => $agentDetails]);

        $agentSelectionPrompt = $this->buildAgentSelectionPrompt($userText);
        $agentSelectionMessages = new MessageBag(Message::ofUser($agentSelectionPrompt));

        $selectionResult = $this->orchestrator->call($agentSelectionMessages, $options);
        $responseContent = $selectionResult->getContent();
        $this->logger->debug('MultiAgent: Received orchestrator response', ['response' => $responseContent]);

        // Parse JSON response
        $selectionData = json_decode($responseContent, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->debug('MultiAgent: JSON parsing failed, falling back to orchestrator', ['json_error' => json_last_error_msg()]);

            return $this->orchestrator->call($messages, $options);
        }

        $agentName = $selectionData['agentName'] ?? null;
        $reasoning = $selectionData['reasoning'] ?? 'No reasoning provided';
        $this->logger->debug('MultiAgent: Agent selection completed', [
            'selected_agent' => $agentName,
            'reasoning' => $reasoning,
        ]);

        // If no specific agent is selected, fall back to orchestrator
        if (!$agentName || 'null' === $agentName) {
            $this->logger->debug('MultiAgent: Falling back to orchestrator', ['reason' => 'no_agent_selected']);

            return $this->orchestrator->call($messages, $options);
        }

        // Find the target agent by name
        $targetAgent = null;
        foreach ($this->handoffs as $handoff) {
            if ($handoff->getTo()->getName() === $agentName) {
                $targetAgent = $handoff->getTo();
                break;
            }
        }

        if (!$targetAgent) {
            $this->logger->debug('MultiAgent: Target agent not found, falling back to orchestrator', [
                'requested_agent' => $agentName,
                'reason' => 'agent_not_found',
            ]);

            return $this->orchestrator->call($messages, $options);
        }

        $this->logger->debug('MultiAgent: Delegating to agent', ['agent_name' => $agentName]);
        $originalMessages = new MessageBag(self::findUserMessage($userMessages));

        return $targetAgent->call($originalMessages, $options);
    }

    private static function extractUserMessage(MessageBag $messages): string
    {
        foreach ($messages->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                $textParts = [];
                foreach ($message->content as $content) {
                    if ($content instanceof Text) {
                        $textParts[] = $content->text;
                    }
                }

                return implode(' ', $textParts);
            }
        }

        throw new RuntimeException('No user message found in conversation.');
    }

    private static function findUserMessage(MessageBag $messages): UserMessage
    {
        foreach ($messages->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }

        throw new RuntimeException('No user message found in conversation.');
    }

    private function buildAgentSelectionPrompt(string $userQuestion): string
    {
        $agentDescriptions = [];
        $agentNames = ['null'];

        foreach ($this->handoffs as $handoff) {
            $triggers = implode(', ', $handoff->getWhen());
            $agentName = $handoff->getTo()->getName();
            $agentDescriptions[] = "- {$agentName}: {$triggers}";
            $agentNames[] = $agentName;
        }

        $agentList = implode("\n", $agentDescriptions);
        $validAgents = implode('", "', $agentNames);

        return <<<PROMPT
            You are an intelligent agent orchestrator. Based on the user's question, determine which specialized agent should handle the request.
            
            User question: "{$userQuestion}"
            
            Available agents and their capabilities:
            {$agentList}
            
            Analyze the user's question and select the most appropriate agent. If no specific agent is needed, select "null".
            
            Respond with JSON in this exact format:
            {
              "agentName": "<one of: \"{$validAgents}\">",
              "reasoning": "<your reasoning for the selection>"
            }
            
            The agentName must be exactly one of the available agent names or "none".
            PROMPT;
    }
}

