<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Contract;

use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Model;

class DocumentUrlNormalizer extends ModelContractNormalizer
{
    /**
     * @param DocumentUrl $data
     *
     * @return array{type: 'document_url', document_url: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'document_url',
            'document_url' => $data->url,
        ];
    }

    protected function supportedDataClass(): string
    {
        return DocumentUrl::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Mistral;
    }
}
