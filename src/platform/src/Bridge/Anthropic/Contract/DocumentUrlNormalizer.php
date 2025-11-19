<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DocumentUrlNormalizer extends ModelContractNormalizer
{
    /**
     * @param DocumentUrl $data
     *
     * @return array{type: 'document', source: array{type: 'url', url: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'document',
            'source' => [
                'type' => 'url',
                'url' => $data->getUrl(),
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return DocumentUrl::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }
}
