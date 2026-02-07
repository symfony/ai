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
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Result\ToolCall;

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

    /**
     * @param ?ToolCall[] $toolCalls
     */
    public static function ofAssistant(?string $content = null, ?array $toolCalls = null): AssistantMessage
    {
        return new AssistantMessage($content, $toolCalls);
    }

    public static function ofUser(mixed ...$content): UserMessage
    {
        $contextObject = null;
        if (\count($content) > 0) {
            $lastArg = $content[\count($content) - 1];
            if (\is_object($lastArg) && !$lastArg instanceof \Stringable && !$lastArg instanceof ContentInterface) {
                $contextObject = array_pop($content);
            }
        }

        foreach ($content as $entry) {
            if (!\is_string($entry) && !$entry instanceof \Stringable && !$entry instanceof ContentInterface) {
                throw new InvalidArgumentException(\sprintf('Content must be string, Stringable, or ContentInterface, "%s" given.', get_debug_type($entry)));
            }
        }

        $content = array_map(
            static fn (\Stringable|string|ContentInterface $entry) => match (true) {
                $entry instanceof ContentInterface => $entry,
                \is_string($entry) => new Text($entry),
                default => new Text((string) $entry),
            },
            $content,
        );

        if (null !== $contextObject) {
            $content[] = new Text(json_encode($contextObject, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        }

        $message = new UserMessage(...$content);

        if (null !== $contextObject) {
            $message->getMetadata()->add('structured_output_object', $contextObject);
        }

        return $message;
    }

    public static function ofToolCall(ToolCall $toolCall, string $content): ToolCallMessage
    {
        return new ToolCallMessage($toolCall, $content);
    }
}
