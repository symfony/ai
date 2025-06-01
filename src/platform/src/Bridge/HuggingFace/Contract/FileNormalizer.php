<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Contract;

use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class FileNormalizer extends ModelContractNormalizer
{
    protected function supportedDataClass(): string
    {
        return File::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return true;
    }

    /**
     * @param File $data
     *
     * @return array{
     *     headers: array<'Content-Type', string>,
     *     body: string
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'headers' => ['Content-Type' => $data->getFormat()],
            'body' => $data->asBinary(),
        ];
    }
}
