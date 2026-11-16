<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ToolNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Tool;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Tool::class => true,
        ];
    }

    /**
     * @param Tool $data
     *
     * @return array{
     *     type: 'function',
     *     function: array{
     *         name: string,
     *         description: string,
     *         parameters: JsonSchema|array{type: 'object', properties: \stdClass}
     *     }
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $data->getName(),
                'description' => $data->getDescription(),
                // An empty array would be encoded as a JSON list, not as an object
                'parameters' => $data->getParameters() ?? ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];
    }
}
