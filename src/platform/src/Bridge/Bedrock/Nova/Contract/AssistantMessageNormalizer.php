<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract;

use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
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
     *     content: array<array{
     *         toolUse?: array{
     *             toolUseId: string,
     *             name: string,
     *             input: mixed,
     *         },
     *         text?: string,
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data->hasToolCalls()) {
            return [
                'role' => 'assistant',
                'content' => array_map(static function (ToolCall $toolCall) {
                    return [
                        'toolUse' => [
                            'toolUseId' => $toolCall->getId(),
                            'name' => $toolCall->getName(),
                            'input' => [] !== $toolCall->getArguments() ? $toolCall->getArguments() : new \stdClass(),
                        ],
                    ];
                }, $data->getToolCalls()),
            ];
        }

        return [
            'role' => 'assistant',
            'content' => [['text' => $data->getContent()]],
        ];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Nova;
    }
}
