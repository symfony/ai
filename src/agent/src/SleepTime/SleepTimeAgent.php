<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\SleepTime;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @see https://arxiv.org/html/2504.13171v1
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SleepTimeAgent implements AgentInterface
{
    private int $callCount = 0;

    /**
     * @var MemoryBlock[]
     */
    private readonly array $memoryBlocks;

    /**
     * @param AgentInterface   $primaryAgent  Agent that handles user queries
     * @param AgentInterface   $sleepingAgent Agent that processes context during "sleep" phase
     * @param MemoryBlock[]    $memoryBlocks  Shared memory blocks between both agents
     * @param positive-int     $frequency     Number of primary agent calls between sleep invocations
     * @param non-empty-string $name          Name of this agent
     */
    public function __construct(
        private readonly AgentInterface $primaryAgent,
        private readonly AgentInterface $sleepingAgent,
        array $memoryBlocks,
        private readonly int $frequency = 5,
        private readonly string $name = 'sleep-time-agent',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $memoryBlocks) {
            throw new InvalidArgumentException('SleepTimeAgent requires at least one memory block.');
        }

        if ($frequency < 1) {
            throw new InvalidArgumentException(\sprintf('Sleep frequency must be at least 1, %d given.', $frequency));
        }

        $this->memoryBlocks = $memoryBlocks;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $result = $this->primaryAgent->call($messages, $options);

        ++$this->callCount;

        if (0 === $this->callCount % $this->frequency) {
            $this->sleep($messages);
        }

        return $result;
    }

    private function sleep(MessageBag $messages): void
    {
        $this->logger->debug('SleepTimeAgent: Starting sleep-time processing', [
            'call_count' => $this->callCount,
        ]);

        try {
            $sleepMessages = new MessageBag(
                Message::forSystem($this->buildSleepSystemPrompt()),
                Message::ofUser($this->buildConversationContext($messages)),
            );

            $this->sleepingAgent->call($sleepMessages);

            $this->logger->debug('SleepTimeAgent: Sleep-time processing completed');
        } catch (\Throwable $e) {
            $this->logger->warning('SleepTimeAgent: Sleep-time processing failed, proceeding without enrichment', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function buildSleepSystemPrompt(): string
    {
        $blockDescriptions = [];
        foreach ($this->memoryBlocks as $block) {
            $content = '' !== $block->getContent() ? $block->getContent() : '(empty)';
            $blockDescriptions[] = \sprintf('- **%s**: %s', $block->getLabel(), $content);
        }

        $blockList = implode(\PHP_EOL, $blockDescriptions);

        return <<<PROMPT
            You are a sleep-time analysis agent. Your job is to analyze conversation history
            and enrich shared memory blocks with insights, inferences, and anticipated questions.

            ## Available memory blocks
            {$blockList}

            ## Instructions
            Use the `rethink_memory` tool to update memory blocks with your analysis. You should:
            1. Summarize key themes and topics discussed
            2. Identify user preferences and patterns
            3. Anticipate likely follow-up questions
            4. Draw inferences from the conversation context
            5. Do not duplicate information already present in the memory blocks
            6. Be concise and factual in your memory updates

            Update each relevant memory block with enriched content.
            PROMPT;
    }

    private function buildConversationContext(MessageBag $messages): string
    {
        $context = "Here is the conversation to analyze:\n\n";

        foreach ($messages->getMessages() as $message) {
            $text = $this->extractText($message);

            if (null === $text || '' === $text) {
                continue;
            }

            $context .= \sprintf("[%s]: %s\n\n", $message->getRole()->value, $text);
        }

        return $context;
    }

    private function extractText(MessageInterface $message): ?string
    {
        if ($message instanceof UserMessage) {
            return $message->asText();
        }

        if ($message instanceof AssistantMessage) {
            return $message->getContent();
        }

        if ($message instanceof SystemMessage) {
            $content = $message->getContent();

            return \is_string($content) ? $content : null;
        }

        return null;
    }
}
