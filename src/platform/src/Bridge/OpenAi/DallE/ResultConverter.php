<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\DallE;

use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;

/**
 * @see https://platform.openai.com/docs/api-reference/images/create
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof DallE;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $result = $result->getData();

        if (!isset($result['data'][0])) {
            throw new RuntimeException('No image generated.');
        }

        $images = [];
        foreach ($result['data'] as $image) {
            if ('url' === $options[PlatformSubscriber::RESPONSE_FORMAT]) {
                $images[] = new UrlImage($image['url']);

                continue;
            }

            $images[] = new Base64Image($image['b64_json']);
        }

        return new ImageResult($image['revised_prompt'] ?? null, ...$images);
    }
}
