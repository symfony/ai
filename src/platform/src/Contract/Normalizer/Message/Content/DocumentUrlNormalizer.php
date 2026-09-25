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

use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DocumentUrlNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof DocumentUrl;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DocumentUrl::class => true,
        ];
    }

    /**
     * @param DocumentUrl $data
     *
     * @return array{type: 'document_url', document_url: array{url: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'document_url',
            'document_url' => ['url' => $data->getUrl()],
        ];
    }
}
