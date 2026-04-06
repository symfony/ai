<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Contract;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Joshua Behrens <code@joshua-behrens.de>
 */
final class ToolCallNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCall $data
     *
     * @return array{
     *     type: 'function',
     *     function: array{
     *         name: string,
     *         arguments: array<string, mixed>|\stdClass
     *     }
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $data->getName(),
                // stdClass forces empty object
                'arguments' => [] === $data->getArguments() ? new \stdClass() : $data->getArguments(),
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCall::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Ollama;
    }
}
