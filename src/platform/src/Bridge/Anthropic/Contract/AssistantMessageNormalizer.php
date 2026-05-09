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
     *         type: 'thinking'|'text'|'tool_use',
     *         id?: string,
     *         name?: string,
     *         input?: array<string, mixed>,
     *         text?: string,
     *         thinking?: string,
     *         signature?: string
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parts = $data->getContent();

        if (1 === \count($parts) && $parts[0] instanceof Text) {
            return [
                'role' => 'assistant',
                'content' => $parts[0]->getText(),
            ];
        }

        $blocks = [];
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
