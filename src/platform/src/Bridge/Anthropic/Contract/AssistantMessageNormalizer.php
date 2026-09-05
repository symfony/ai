<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param AssistantMessage $data
     *
     * @return array{
     *     role: 'assistant',
     *     content: string|list<array{
     *         type: 'thinking'|'text'|'tool_use'|'server_tool_use'|'web_search_tool_result'|'bash_code_execution_tool_result'|'text_editor_code_execution_tool_result',
     *         id?: string,
     *         tool_use_id?: string,
     *         name?: string,
     *         input?: array<string, mixed>,
     *         content?: array<mixed>,
     *         text?: string,
     *         thinking?: string,
     *         signature?: string,
     *         ...
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parts = $data->getContent();

        if ([] === $parts) {
            return [
                'role' => 'assistant',
                'content' => '',
            ];
        }

        if (1 === \count($parts) && $parts[0] instanceof Text) {
            return [
                'role' => 'assistant',
                'content' => $parts[0]->getText(),
            ];
        }

        $blocks = [];
        $executedAsBash = [];
        foreach ($parts as $part) {
            if ($part instanceof Thinking) {
                $block = [
                    'type' => 'thinking',
                    'thinking' => $part->getContent(),
                ];
                if (null !== $part->getSignature()) {
                    $block['signature'] = $part->getSignature();
                }
                $blocks[] = $block;
                continue;
            }

            if ($part instanceof Text) {
                $blocks[] = ['type' => 'text', 'text' => $part->getText()];
                continue;
            }

            if ($part instanceof ToolCall) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $part->getId(),
                    'name' => $part->getName(),
                    'input' => [] !== $part->getArguments() ? $part->getArguments() : new \stdClass(),
                ];
                continue;
            }

            if ($part instanceof WebSearch) {
                foreach (self::toWebSearchBlocks($part) as $webSearchBlock) {
                    $blocks[] = $webSearchBlock;
                }

                continue;
            }

            if ($part instanceof ExecutableCode) {
                // ResultConverter sets language='bash' for bash_code_execution and null for text_editor_code_execution.
                $isBash = 'bash' === $part->getLanguage();
                $block = [
                    'type' => 'server_tool_use',
                    'name' => $isBash ? 'bash_code_execution' : 'text_editor_code_execution',
                    'input' => $isBash ? ['command' => $part->getCode()] : ['file_text' => $part->getCode()],
                ];
                if (null !== $part->getId()) {
                    $block['id'] = $part->getId();
                    $executedAsBash[$part->getId()] = $isBash;
                }
                $blocks[] = $block;
                continue;
            }

            if ($part instanceof CodeExecution) {
                $isBash = $executedAsBash[$part->getId() ?? ''] ?? true;
                $block = ['type' => $isBash ? 'bash_code_execution_tool_result' : 'text_editor_code_execution_tool_result'];
                if (null !== $part->getId()) {
                    $block['tool_use_id'] = $part->getId();
                }
                if ($isBash) {
                    $block['content'] = [
                        'type' => 'bash_code_execution_result',
                        'stdout' => $part->getOutput() ?? '',
                        'stderr' => '',
                        'return_code' => $part->isSucceeded() ? 0 : 1,
                        'content' => [],
                    ];
                }
                $blocks[] = $block;
            }
        }

        return [
            'role' => 'assistant',
            'content' => $blocks,
        ];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }

    /**
     * A hosted web search replays as the blocks Anthropic sent for it - the `server_tool_use` call
     * and its `web_search_tool_result` - which `ResultConverter` keeps in the result's signature.
     * A signature that is absent, unreadable, or written by another provider replays nothing, so an
     * assistant turn crossing bridges drops the search instead of sending Claude a foreign block.
     *
     * @return list<array<string, mixed>>
     */
    private static function toWebSearchBlocks(WebSearch $webSearch): array
    {
        $signature = $webSearch->getSignature();

        if (null === $signature) {
            return [];
        }

        try {
            $decoded = json_decode($signature, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        $blocks = [];
        foreach ($decoded as $block) {
            if (!\is_array($block)) {
                return [];
            }

            if (\is_string($block['caller']['tool_id'] ?? null)) {
                throw new InvalidArgumentException('Cannot replay an Anthropic web search initiated by code execution because its surrounding code execution blocks are not supported.');
            }

            $isCall = 'server_tool_use' === ($block['type'] ?? null) && 'web_search' === ($block['name'] ?? null);

            if (!$isCall && 'web_search_tool_result' !== ($block['type'] ?? null)) {
                return [];
            }

            /* @var array<string, mixed> $block */
            $blocks[] = $block;
        }

        return $blocks;
    }
}
