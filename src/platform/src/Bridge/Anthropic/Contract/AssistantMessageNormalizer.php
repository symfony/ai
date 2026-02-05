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
use Symfony\AI\Platform\Result\ToolCall;
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
     *         type: 'tool_use',
     *         id: string,
     *         name: string,
     *         input: array<string, mixed>
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = $data->hasToolCalls() ? array_map(static function (ToolCall $toolCall) {
            return [
                'type' => 'tool_use',
                'id' => $toolCall->getId(),
                'name' => $toolCall->getName(),
                'input' => [] !== $toolCall->getArguments() ? $toolCall->getArguments() : new \stdClass(),
            ];
        }, $data->getToolCalls()) : $this->normalizeContent($data->getContent(), $format, $context);

        return [
            'role' => 'assistant',
            'content' => $content,
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

    private function normalizeContent(object|string|null $content, ?string $format, array $context): ?string
    {
        if (null === $content) {
            return null;
        }

        if (\is_string($content)) {
            return $content;
        }

        if ($content instanceof \Stringable) {
            return (string) $content;
        }

        return json_encode(
            $this->normalizer->normalize($content, $format, $context),
            \JSON_THROW_ON_ERROR
        );
    }
}
