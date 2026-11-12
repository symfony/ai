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
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class DocumentNormalizer extends ModelContractNormalizer
{
    /**
     * @param Document $data
     *
     * @return array{type: 'document', source: array{type: 'base64'|'text', media_type: string, data: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        // Anthropic takes a base64 source for PDF only, a text document is sent as plain text
        if (str_starts_with($data->getFormat(), 'text/')) {
            return [
                'type' => 'document',
                'source' => [
                    'type' => 'text',
                    'media_type' => 'text/plain',
                    'data' => $data->asBinary(),
                ],
            ];
        }

        return [
            'type' => 'document',
            'source' => [
                'type' => 'base64',
                'media_type' => $data->getFormat(),
                'data' => $data->asBase64(),
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return Document::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }
}
