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
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
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
     *         type: 'thinking'|'text'|'tool_use'|'server_tool_use'|'bash_code_execution_tool_result'|'text_editor_code_execution_tool_result',
     *         id?: string,
     *         tool_use_id?: string,
     *         name?: string,
     *         input?: array<string, mixed>,
     *         content?: array<string, mixed>,
     *         text?: string,
     *         thinking?: string,
     *         signature?: string
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
}
