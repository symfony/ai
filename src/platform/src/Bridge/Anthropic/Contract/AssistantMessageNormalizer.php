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
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ThinkingContent;
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
            if (!$data->hasToolCalls() && !$data->hasThinkingContent()) {
                return [
                    'role' => 'assistant',
                    'content' => $content instanceof Text ? $content->getText() : (string) $content,
                ];
            }

            $content = [$content];
        }

        $blocks = [];

        foreach ($content as $block) {
            if ($block instanceof ThinkingContent) {
                $thinkingBlock = [
                    'type' => 'thinking',
                    'thinking' => $block->thinking,
                ];
                if (null !== $block->signature) {
                    $thinkingBlock['signature'] = $block->signature;
                }
                $blocks[] = $thinkingBlock;
                continue;
            }

            if ($block instanceof Text) {
                $blocks[] = ['type' => 'text', 'text' => $block->getText()];
                continue;
            }

            if ($block instanceof ToolCall) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $block->getId(),
                    'name' => $block->getName(),
                    'input' => [] !== $block->getArguments() ? $block->getArguments() : new \stdClass(),
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
