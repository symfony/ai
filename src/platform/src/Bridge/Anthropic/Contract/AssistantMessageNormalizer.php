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
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
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
        $content = $data->getContent();

        if (!is_iterable($content)) {
            if (!$data->hasToolCalls() && !$data->hasThinking()) {
                $value = $content->getContent();
                return [
                    'role' => 'assistant',
                    'content' => match (true) {
                        $content instanceof TextResult, \is_string($value) => $value,
                        $value instanceof \Stringable => (string) $value,
                        default => $this->normalizer->normalize($value, $format, $context),
                    },
                ];
            }

            $content = [$content];
        }

        $blocks = [];

        foreach ($content as $block) {
            if ($block instanceof ThinkingResult) {
                $thinkingBlock = [
                    'type' => 'thinking',
                    'thinking' => $block->getContent(),
                ];
                if (null !== $block->getSignature()) {
                    $thinkingBlock['signature'] = $block->getSignature();
                }
                $blocks[] = $thinkingBlock;
                continue;
            }

            if ($block instanceof TextResult) {
                $blocks[] = ['type' => 'text', 'text' => $block->getContent()];
                continue;
            }

            if ($block instanceof ToolCallResult) {
                foreach ($block->getContent() as $toolCall) {
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $toolCall->getId(),
                        'name' => $toolCall->getName(),
                        'input' => [] !== $toolCall->getArguments() ? $toolCall->getArguments() : new \stdClass(),
                    ];
                }
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
