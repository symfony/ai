<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Compression;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Summarizes older messages using an LLM while keeping recent messages intact.
 *
 * This strategy splits the conversation into two parts:
 * - Older messages that get summarized into a compact form
 * - Recent messages that are kept as-is for context
 *
 * The summary is injected as part of the system message.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SummarizationStrategy implements CompressionStrategyInterface
{
    private const SUMMARIZATION_PROMPT = <<<PROMPT
        Summarize the following conversation history concisely. Focus on:
        - Key decisions made
        - Important information exchanged
        - Current state of any tasks
        - Any relevant context for continuing the conversation

        Keep the summary brief but informative. Do not include greetings or pleasantries.

        Conversation:
        {conversation}
        PROMPT;

    /**
     * @param string   $model      Model to use for summarization (can be a smaller/faster model)
     * @param int      $threshold  Number of messages that triggers compression
     * @param int      $keepRecent Number of recent messages to keep uncompressed
     * @param Template $template   Optional custom prompt template for summarization
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly int $threshold = 20,
        private readonly int $keepRecent = 6,
        private readonly Template $template = new Template('string', self::SUMMARIZATION_PROMPT),
    ) {
    }

    public function shouldCompress(MessageBag $messages): bool
    {
        return $this->threshold < \count($messages->withoutSystemMessage());
    }

    public function compress(MessageBag $messages): MessageBag
    {
        $systemMessage = $messages->getSystemMessage();
        $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

        // Split into messages to summarize and messages to keep
        $toSummarize = \array_slice($nonSystemMessages, 0, -$this->keepRecent);
        $toKeep = \array_slice($nonSystemMessages, -$this->keepRecent);

        if ([] === $toSummarize) {
            return $messages;
        }

        $conversationText = $this->formatConversation($toSummarize);

        if ('' === trim($conversationText)) {
            return $messages;
        }

        // Build new system message with summary
        $systemContent = '';
        if (null !== $systemMessage) {
            $systemContent = $systemMessage->getContent().\PHP_EOL.\PHP_EOL;
        }
        $systemContent .= '# Previous Conversation Summary'.\PHP_EOL.\PHP_EOL.$this->generateSummary($conversationText);

        $newSystemMessage = Message::forSystem($systemContent);

        return new MessageBag($newSystemMessage, ...$toKeep);
    }

    /**
     * @param list<MessageInterface> $messages
     */
    private function formatConversation(array $messages): string
    {
        $lines = [];

        foreach ($messages as $message) {
            $text = $this->extractText($message);
            if (null === $text || '' === $text) {
                continue;
            }

            $role = match (true) {
                $message instanceof UserMessage => 'User',
                $message instanceof AssistantMessage => 'Assistant',
                $message instanceof ToolCallMessage => 'Tool',
                default => 'Unknown',
            };

            $lines[] = \sprintf('%s: %s', $role, $text);
        }

        return implode(\PHP_EOL, $lines);
    }

    private function extractText(MessageInterface $message): ?string
    {
        if ($message instanceof UserMessage) {
            return $message->asText();
        }

        if ($message instanceof AssistantMessage) {
            return $message->getContent();
        }

        if ($message instanceof ToolCallMessage) {
            // Include tool name and truncated result
            $toolName = $message->getToolCall()->getName();
            $content = $message->getContent();
            $truncated = mb_substr($content, 0, 200);
            if (mb_strlen($content) > 200) {
                $truncated .= '...';
            }

            return \sprintf('[%s] %s', $toolName, $truncated);
        }

        return null;
    }

    private function generateSummary(string $conversationText): string
    {
        return $this->platform->invoke(
            $this->model,
            new MessageBag(Message::ofUser($this->template)),
            ['template_vars' => ['conversation' => $conversationText]],
        )->asText();
    }
}
