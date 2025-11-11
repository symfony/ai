<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCall $data
     *
     * @return array{
     *     arguments: string,
     *     call_id: string,
     *     name: string,
     *     type: 'function_call'
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'arguments' => json_encode($data->getArguments()),
            'call_id' => $data->getId(),
            'name' => $data->getName(),
            'type' => 'function_call',
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCall::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
