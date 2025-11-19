<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Result;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ToolCall;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ToolCall::class => true,
        ];
    }

    /**
     * @param ToolCall $data
     *
     * @return array{
     *      id: string,
     *      type: 'function',
     *      function: array{
     *          name: string,
     *          arguments: string
     *      }
     *  }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'id' => $data->getId(),
            'type' => 'function',
            'function' => [
                'name' => $data->getName(),
                'arguments' => json_encode($data->getArguments()),
            ],
        ];
    }
}
