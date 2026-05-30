<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock\Recording;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Converts a {@see ResultInterface} to and from a JSON-serializable array for {@see Cassette}.
 *
 * Supported result types (v1): text, object, vector, tool call, and text-delta streams. Result
 * metadata and token usage are not preserved. Unsupported result or delta types throw.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ResultInterface $result): array
    {
        if ($result instanceof TextResult) {
            return [
                'type' => 'text',
                'content' => $result->getContent(),
                'signature' => $result->getSignature(),
            ];
        }

        if ($result instanceof ObjectResult) {
            $content = $result->getContent();

            return [
                'type' => 'object',
                'is_object' => \is_object($content),
                'content' => json_encode($content, \JSON_THROW_ON_ERROR),
            ];
        }

        if ($result instanceof VectorResult) {
            return [
                'type' => 'vector',
                'vectors' => array_map(static fn (Vector $vector): array => $vector->getData(), $result->getContent()),
            ];
        }

        if ($result instanceof ToolCallResult) {
            return [
                'type' => 'toolcall',
                'tool_calls' => array_map(static fn (ToolCall $toolCall): array => [
                    'id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'arguments' => $toolCall->getArguments(),
                    'signature' => $toolCall->getSignature(),
                ], $result->getContent()),
            ];
        }

        if ($result instanceof StreamResult) {
            $deltas = [];
            foreach ($result->getContent() as $delta) {
                if (!$delta instanceof \Stringable) {
                    throw new InvalidArgumentException(\sprintf('Cannot record stream delta of type "%s"; only text deltas are supported.', get_debug_type($delta)));
                }

                $deltas[] = (string) $delta;
            }

            return [
                'type' => 'stream',
                'deltas' => $deltas,
            ];
        }

        throw new InvalidArgumentException(\sprintf('Cannot record result of type "%s".', $result::class));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ResultInterface
    {
        $type = $data['type'] ?? null;

        if ('text' === $type) {
            return new TextResult((string) $data['content'], $data['signature'] ?? null);
        }

        if ('object' === $type) {
            $content = json_decode((string) $data['content'], !($data['is_object'] ?? false), flags: \JSON_THROW_ON_ERROR);

            return new ObjectResult($content);
        }

        if ('vector' === $type) {
            return new VectorResult(array_map(
                static fn (array $vector): Vector => new Vector(array_map(static fn ($value): float => (float) $value, $vector)),
                $data['vectors'] ?? [],
            ));
        }

        if ('toolcall' === $type) {
            return new ToolCallResult(array_map(
                static fn (array $toolCall): ToolCall => new ToolCall(
                    (string) $toolCall['id'],
                    (string) $toolCall['name'],
                    $toolCall['arguments'] ?? [],
                    $toolCall['signature'] ?? null,
                ),
                $data['tool_calls'] ?? [],
            ));
        }

        if ('stream' === $type) {
            $deltas = $data['deltas'] ?? [];

            return new StreamResult((static function () use ($deltas): \Generator {
                foreach ($deltas as $text) {
                    yield new TextDelta((string) $text);
                }
            })());
        }

        throw new InvalidArgumentException(\sprintf('Cannot rebuild result of unknown type "%s".', \is_string($type) ? $type : get_debug_type($type)));
    }
}
