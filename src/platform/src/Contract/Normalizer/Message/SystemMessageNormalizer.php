<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message;

use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SystemMessageNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof SystemMessage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SystemMessage::class => true,
        ];
    }

    /**
     * @param SystemMessage $data
     *
     * @return array{role: 'system', content: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'role' => $data->getRole()->value,
            'content' => $data->getContent(),
        ];
    }
}
