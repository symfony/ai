<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Image;

use Symfony\AI\Platform\Bridge\Ovh\Image;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Image;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        $data = $result->getData();

        if (!isset($data['data'][0])) {
            throw new RuntimeException('No image generated.');
        }

        $mimeType = 'image/'.($data['output_format'] ?? 'png');

        $images = [];
        foreach ($data['data'] as $image) {
            if (!isset($image['b64_json'])) {
                throw new RuntimeException('The image is not base64-encoded, only base64-encoded images are supported.');
            }

            $images[] = BinaryResult::fromBase64($image['b64_json'], $mimeType);
        }

        if (1 === \count($images)) {
            return $images[0];
        }

        return new MultiPartResult($images);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
