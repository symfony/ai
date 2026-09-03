<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;

use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\ResultMetadataTrait;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Eden AI returns the generated images under an "items" list, each carrying both
 * base64-encoded content ("image") and a temporary resource URL ("image_resource_url").
 * The base64 content is exposed directly, so no extra request is needed.
 *
 * The "num_images" input accepts up to 10, and every generated image is billed, so all of
 * them are returned: a single image as a BinaryResult, several as a MultiPartResult, which
 * mirrors the OpenAI image bridge. The resource URLs are exposed as result metadata.
 *
 * Note that the "output_schema" advertised by GET /v3/info describes a single item without
 * the enclosing "items" list; the endpoint really wraps them.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements ResultConverterInterface
{
    use ErrorHandlingTrait;
    use ResultMetadataTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof ImageGeneration;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $httpResponse = $result->getObject();

        $this->assertSuccessfulResponse($httpResponse);

        $data = $result->getData();

        if ('fail' === ($data['status'] ?? null)) {
            throw new RuntimeException(\sprintf('Eden AI request failed: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        $items = $data['output']['items'] ?? null;

        if (!\is_array($items) || [] === $items) {
            throw new RuntimeException('Response does not contain a generated image.');
        }

        $images = [];
        $resourceUrls = [];

        foreach ($items as $item) {
            if (!\is_array($item) || !isset($item['image']) || !\is_string($item['image']) || '' === $item['image']) {
                continue;
            }

            // Validates the payload and throws when it is not decodable base64.
            $images[] = BinaryResult::fromBase64($item['image'], 'image/png');

            if (isset($item['image_resource_url']) && \is_string($item['image_resource_url'])) {
                $resourceUrls[] = $item['image_resource_url'];
            }
        }

        if ([] === $images) {
            throw new RuntimeException('Response does not contain a generated image.');
        }

        $converted = 1 === \count($images) ? $images[0] : new MultiPartResult($images);

        $this->attachEdenAiMetadata($converted, $data, [
            'image_resource_urls' => [] === $resourceUrls ? null : $resourceUrls,
        ]);

        return $converted;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
