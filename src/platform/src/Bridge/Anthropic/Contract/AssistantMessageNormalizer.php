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
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

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
        $hasBlocks = $data->hasToolCalls() || $data->hasThinkingContent();

        if (!$hasBlocks) {
            return [
                'role' => 'assistant',
                'content' => $data->getContent(),
            ];
        }

        $blocks = [];

        if ($data->hasThinkingContent()) {
            $thinkingBlock = [
                'type' => 'thinking',
                'thinking' => $data->getThinkingContent(),
            ];
            if (null !== $data->getThinkingSignature()) {
                $thinkingBlock['signature'] = $data->getThinkingSignature();
            }
            $blocks[] = $thinkingBlock;
        }

        if (null !== $data->getContent()) {
            $blocks[] = ['type' => 'text', 'text' => $data->getContent()];
        }

        if ($data->hasToolCalls()) {
            foreach ($data->getToolCalls() as $toolCall) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'input' => [] !== $toolCall->getArguments() ? $toolCall->getArguments() : new \stdClass(),
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
