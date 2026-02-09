<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message\Content;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Model;

/**
 * @author Guillermo Lengemann <guillermo.lengemann@gmail.com>
 */
final class DocumentNormalizer extends ModelContractNormalizer
{
    /**
     * @param File $data
     *
     * @return array{type: 'input_file', filename: string, file_data: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_file',
            'filename' => $data->getFilename(),
            'file_data' => $data->asDataUrl(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return Document::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel && $model->supports(Capability::INPUT_PDF);
    }
}
