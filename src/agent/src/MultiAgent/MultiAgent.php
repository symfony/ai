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
     * @param array<string, AgentInterface> $agents Map of agent names to agent instances
     * @param HandoffRule[] $rules Rules for agent handoffs
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private array $agents,
        private array $rules,
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
        $userMessages = $messages->withoutSystemMessage();
        
        // Extract the original user message
        $originalUserMessage = null;
        foreach ($userMessages->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                $originalUserMessage = $message;
                break;
            }
        }
        
        if (null === $originalUserMessage) {
            throw new RuntimeException('No user message found in conversation.');
        }

        // Ask orchestrator which agent to target using JSON response format
        $userText = $this->extractTextFromUserMessage($originalUserMessage);
        $agentSelectionPrompt = $this->buildAgentSelectionPrompt($userText);
        $agentSelectionMessages = new MessageBag(Message::ofUser($agentSelectionPrompt));
        
        $selectionResult = $this->orchestrator->call($agentSelectionMessages, $options);
        $responseContent = $selectionResult->getContent();
        
        // Parse JSON response
        $selectionData = json_decode($responseContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->orchestrator->call($messages, $options);
        }
        
        $agentName = $selectionData['agentName'] ?? null;
        
        // If no specific agent is selected, fall back to orchestrator
        if (!$agentName || $agentName === 'null' || !isset($this->agents[$agentName])) {
            return $this->orchestrator->call($messages, $options);
        }
        
        // Pass the original user question to the selected agent
        $targetAgent = $this->agents[$agentName];
        $originalMessages = new MessageBag($originalUserMessage);
        
        return $targetAgent->call($originalMessages, $options);
    }
    
    private function extractTextFromUserMessage(UserMessage $message): string
    {
        $textParts = [];
        foreach ($message->content as $content) {
            if ($content instanceof Text) {
                $textParts[] = $content->text;
            }
        }
        
        return implode(' ', $textParts);
    }
    
    private function buildAgentSelectionPrompt(string $userQuestion): string
    {
        $agentDescriptions = [];
        $agentNames = ['null'];
        
        foreach ($this->rules as $rule) {
            $triggers = implode(', ', $rule->getTriggers());
            $agentDescriptions[] = "- {$rule->getAgentName()}: {$triggers}";
            $agentNames[] = $rule->getAgentName();
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