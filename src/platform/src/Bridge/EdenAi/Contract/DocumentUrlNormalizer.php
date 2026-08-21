<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Contract;

use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Model;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class DocumentUrlNormalizer extends ModelContractNormalizer
{
    /**
     * @param DocumentUrl $data
     *
     * @return array{file: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'file' => $data->getUrl(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return DocumentUrl::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Ocr || $model instanceof DocumentParser;
    }
}
