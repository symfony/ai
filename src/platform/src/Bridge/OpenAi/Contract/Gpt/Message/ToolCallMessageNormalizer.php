<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    use NormalizerAwareTrait;

    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *     type: 'function_call_output',
     *     call_id: string,
     *     output: string
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'function_call_output',
            'call_id' => $data->getToolCall()->getId(),
            'output' => $data->getContent(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
