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
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\InputNormalizer;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;
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
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MultiAgent implements AgentInterface
{
    /**
     * @param AgentInterface   $orchestrator Agent responsible for analyzing requests and selecting appropriate handoffs
     * @param Handoff[]        $handoffs     Handoff definitions for agent routing
     * @param AgentInterface   $fallback     Fallback agent when no handoff conditions match
     * @param non-empty-string $name         Name of the multi-agent
     * @param LoggerInterface  $logger       Logger for debugging handoff decisions
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private array $handoffs,
        private AgentInterface $fallback,
        private string $name = 'multi-agent',
        private LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $handoffs) {
            throw new InvalidArgumentException('MultiAgent requires at least 1 handoff.');
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
     * @throws ExceptionInterface When the agent encounters an error during orchestration or handoffs
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $cancellation = new Cancellation();

        return new Execution(function () use ($input, $options, $cancellation): \Generator {
            $messages = InputNormalizer::toMessageBag($input);
            $userMessages = $messages->withoutSystemMessage();

            $userMessage = $userMessages->getUserMessage();
            if (null === $userMessage) {
                throw new RuntimeException('No user message found in conversation.');
            }
            $userText = $userMessage->asText();
            $this->logger->debug('MultiAgent: Processing user message', ['user_text' => $userText]);

            $this->logger->debug('MultiAgent: Available agents for routing', ['agents' => array_map(static fn ($handoff) => [
                'to' => $handoff->getTo()->getName(),
                'when' => $handoff->getWhen(),
            ], $this->handoffs)]);

            $agentSelectionPrompt = $this->buildAgentSelectionPrompt($userText);

            // the selection round is internal machinery: its deltas spell out the Decision, not the answer
            $selection = yield from $this->delegate(
                $this->orchestrator,
                new MessageBag(Message::ofUser($agentSelectionPrompt)),
                array_merge($options, ['response_format' => Decision::class]),
                $cancellation,
                false,
            );

            if (null === $selection) {
                return;
            }

            $decision = $selection->getContent();

            if (!$decision instanceof Decision) {
                $this->logger->debug('MultiAgent: Failed to get decision, falling back to orchestrator');

                yield from $this->answerWith($this->orchestrator, $messages, $options, $cancellation);

                return;
            }

            $this->logger->debug('MultiAgent: Agent selection completed', [
                'selected_agent' => $decision->getAgentName(),
                'reasoning' => $decision->getReasoning(),
            ]);

            if (!$decision->hasAgent()) {
                $this->logger->debug('MultiAgent: Using fallback agent', ['reason' => 'no_agent_selected']);

                yield new Progress('handoff', \sprintf('Routing to fallback agent "%s".', $this->fallback->getName()), $decision);

                yield from $this->answerWith($this->fallback, $messages, $options, $cancellation);

                return;
            }

            // Find the target agent by name
            $targetAgent = null;
            foreach ($this->handoffs as $handoff) {
                if ($handoff->getTo()->getName() === $decision->getAgentName()) {
                    $targetAgent = $handoff->getTo();
                    break;
                }
            }

            if (!$targetAgent) {
                $this->logger->debug('MultiAgent: Target agent not found, using fallback agent', [
                    'requested_agent' => $decision->getAgentName(),
                    'reason' => 'agent_not_found',
                ]);

                yield new Progress('handoff', \sprintf('Routing to fallback agent "%s".', $this->fallback->getName()), $decision);

                yield from $this->answerWith($this->fallback, $messages, $options, $cancellation);

                return;
            }

            $this->logger->debug('MultiAgent: Delegating to agent', ['agent_name' => $decision->getAgentName()]);

            yield new Progress('handoff', \sprintf('Routing to agent "%s".', $targetAgent->getName()), $decision);

            // Call the selected agent with the original user question
            yield from $this->answerWith($targetAgent, new MessageBag($userMessage), $options, $cancellation);
        }, cancellation: $cancellation);
    }

    /**
     * Runs a delegated agent and yields its result, unless the execution was canceled.
     *
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, Progress|ResultUpdate, mixed, void>
     */
    private function answerWith(AgentInterface $agent, MessageBag $messages, array $options, Cancellation $cancellation): \Generator
    {
        $result = yield from $this->delegate($agent, $messages, $options, $cancellation);

        if (null !== $result) {
            yield new ResultUpdate($result);
        }
    }

    /**
     * Runs a delegated agent, forwarding its progress into this execution and returning the result it produced.
     *
     * @param array<string, mixed> $options
     * @param bool                 $forwardDeltas whether the delegated deltas are part of this agent's answer
     *
     * @return \Generator<int, Progress, mixed, ResultInterface|null> the result, or null when the execution was canceled
     */
    private function delegate(AgentInterface $agent, MessageBag $messages, array $options, Cancellation $cancellation, bool $forwardDeltas = true): \Generator
    {
        $result = null;

        foreach ($cancellation->forward($agent->call($messages, $options)) as $update) {
            if ($update instanceof ResultUpdate) {
                $result = $update->getResult();

                continue;
            }

            if (!$update instanceof Progress) {
                continue;
            }

            if (!$forwardDeltas && 'delta' === $update->getStage()) {
                continue;
            }

            yield $update;
        }

        if ($cancellation->isRequested()) {
            return null;
        }

        if (!$result instanceof ResultInterface) {
            throw new RuntimeException(\sprintf('The agent "%s" finished without producing a result.', $agent->getName()));
        }

        return $result;
    }

    private function buildAgentSelectionPrompt(string $userQuestion): string
    {
        $agentDescriptions = [];
        $agentNames = [];

        foreach ($this->handoffs as $handoff) {
            $triggers = implode(', ', $handoff->getWhen());
            $agentName = $handoff->getTo()->getName();
            $agentDescriptions[] = "- {$agentName}: {$triggers}";
            $agentNames[] = $agentName;
        }

        $agentDescriptions[] = "- {$this->fallback->getName()}: fallback agent for general/unmatched queries";
        $agentNames[] = $this->fallback->getName();

        $agentList = implode("\n", $agentDescriptions);
        $validAgents = implode('", "', $agentNames);

        return <<<PROMPT
            You are an intelligent agent orchestrator. Based on the user's question, determine which specialized agent should handle the request.

            User question: "{$userQuestion}"

            Available agents and their capabilities:
            {$agentList}

            Analyze the user's question and select the most appropriate agent to handle this request.
            Return an empty string ("") for agentName if no specific agent matches the request criteria.

            Available agent names: {$validAgents}

            Provide your selection and explain your reasoning.
            PROMPT;
    }
}
