<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class Message
{
    // Disabled by default, just a bridge to the specific messages
    private function __construct()
    {
    }

    public static function forSystem(\Stringable|string|Template $content): SystemMessage
    {
        if ($content instanceof Template) {
            return new SystemMessage($content);
        }

        return new SystemMessage($content instanceof \Stringable ? (string) $content : $content);
    }

    public static function ofAssistant(string|ContentInterface|ResultInterface ...$parts): AssistantMessage
    {
        $content = [];
        foreach ($parts as $part) {
            array_push($content, ...self::toContent($part));
        }

        return new AssistantMessage(...$content);
    }

    public static function ofUser(\Stringable|string|ContentInterface ...$content): UserMessage
    {
        $content = array_map(
            static fn (\Stringable|string|ContentInterface $entry) => match (true) {
                $entry instanceof ContentInterface => $entry,
                \is_string($entry) => new Text($entry),
                default => new Text((string) $entry),
            },
            $content,
        );

        return new UserMessage(...$content);
    }

    public static function ofToolCall(ToolCall $toolCall, string $content): ToolCallMessage
    {
        return new ToolCallMessage($toolCall, $content);
    }

    /**
     * @return list<ContentInterface>
     */
    private static function toContent(string|ContentInterface|ResultInterface $part): array
    {
        if (\is_string($part)) {
            return [new Text($part)];
        }

        if ($part instanceof ContentInterface) {
            return [$part];
        }

        if ($part instanceof TextResult) {
            return [new Text($part->getContent(), $part->getSignature())];
        }

        if ($part instanceof ThinkingResult) {
            return [new Thinking($part->getContent() ?? '', $part->getSignature())];
        }

        if ($part instanceof ToolCallResult) {
            return array_values($part->getContent());
        }

        if ($part instanceof ExecutableCodeResult) {
            return [new ExecutableCode($part->getContent(), $part->getLanguage(), $part->getId())];
        }

        if ($part instanceof CodeExecutionResult) {
            return [new CodeExecution($part->isSucceeded(), $part->getContent(), $part->getId())];
        }

        if ($part instanceof MultiPartResult) {
            $content = [];
            foreach ($part->getContent() as $inner) {
                array_push($content, ...self::toContent($inner));
            }

            return $content;
        }

        // Aggressive on purpose: we'd rather fail loudly than silently drop a part
        // and leave the next turn's request missing context. If you hit this with a
        // legitimate response, please open an issue at https://github.com/symfony/ai
        // with a minimal reproducer (model, request, raw provider response) so we
        // can add a mapping.
        throw new InvalidArgumentException(\sprintf('Unsupported assistant message part of type "%s".', $part::class));
    }
}
