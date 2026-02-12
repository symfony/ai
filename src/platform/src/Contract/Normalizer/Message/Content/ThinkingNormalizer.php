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

use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ThinkingNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Thinking;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Thinking::class => true,
        ];
    }

    /**
     * @param Thinking $data
     *
     * @return array{type: 'thinking', thinking: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return ['type' => 'thinking', 'thinking' => $data->getThinking()];
    }
}
