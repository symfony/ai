<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Contract;

use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Model;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class FileUrlNormalizer extends ModelContractNormalizer
{
    /**
     * @param DocumentUrl $data
     *
     * @return array{type: 'file_url', file_url: array{url: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'file_url',
            'file_url' => [
                'url' => $data->url,
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return DocumentUrl::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Perplexity;
    }
}
