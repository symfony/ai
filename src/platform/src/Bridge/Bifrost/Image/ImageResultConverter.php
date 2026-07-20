<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Image;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof ImageModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        $data = $result->getData();

        if (!isset($data['data']) || !\is_array($data['data']) || [] === $data['data']) {
            throw new RuntimeException('No image generated.');
        }

        $images = [];
        $revisedPrompt = null;

        foreach ($data['data'] as $image) {
            if (!\is_array($image)) {
                throw new RuntimeException('Each item in the "data" array must be an object describing a generated image.');
            }

            if (isset($image['revised_prompt']) && \is_string($image['revised_prompt'])) {
                $revisedPrompt = $image['revised_prompt'];
            }

            if (isset($image['url']) && \is_string($image['url']) && '' !== $image['url']) {
                $images[] = new UrlImage($image['url']);

                continue;
            }

            if (isset($image['b64_json']) && \is_string($image['b64_json']) && '' !== $image['b64_json']) {
                $images[] = new Base64Image($image['b64_json']);

                continue;
            }

            throw new RuntimeException('Each generated image must expose either a "url" or a "b64_json" field.');
        }

        return new ImageResult($revisedPrompt, $images);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
