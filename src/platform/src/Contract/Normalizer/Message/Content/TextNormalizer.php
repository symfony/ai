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

use Symfony\AI\Platform\Message\Content\Text;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Text;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Text::class => true,
        ];
    }

    /**
     * @param Text $data
     *
     * @return array{type: 'text', text: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return ['type' => 'text', 'text' => $data->getText()];
    }
}
