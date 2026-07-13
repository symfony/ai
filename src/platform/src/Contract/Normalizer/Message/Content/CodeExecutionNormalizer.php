<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message\Content;

use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CodeExecutionNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CodeExecution;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            CodeExecution::class => true,
        ];
    }

    /**
     * @param CodeExecution $data
     *
     * @return array{type: 'code_execution', succeeded: bool, output: string|null, id: string|null}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'code_execution',
            'succeeded' => $data->isSucceeded(),
            'output' => $data->getOutput(),
            'id' => $data->getId(),
        ];
    }
}
