<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Ocr;

use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result\BoundingBox;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements ResultConverterInterface
{
    use ErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Ocr;
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->assertSuccessfulResponse($httpResponse);

        $data = $result->getData();

        if ('fail' === ($data['status'] ?? null)) {
            throw new RuntimeException(\sprintf('Eden AI request failed: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['output']['text']) || !\is_string($data['output']['text'])) {
            throw new RuntimeException('Response does not contain text.');
        }

        return new ObjectResult(new OcrResult(
            $data['output']['text'],
            $this->extractBoundingBoxes($data['output']['bounding_boxes'] ?? null),
            $data['provider'] ?? null,
            isset($data['cost']) ? (float) $data['cost'] : null,
            $data['original_response'] ?? null,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * Bounding boxes are optional and provider-specific, so an entry that is not a readable box is
     * skipped rather than allowed to escape as a TypeError: the text is the result, the boxes are
     * an extra, and one malformed box should not lose a call that was already billed.
     *
     * @return list<BoundingBox>
     */
    private function extractBoundingBoxes(mixed $boundingBoxes): array
    {
        if (!\is_array($boundingBoxes)) {
            return [];
        }

        $extracted = [];

        foreach ($boundingBoxes as $boundingBox) {
            if (!\is_array($boundingBox)) {
                continue;
            }

            $text = $boundingBox['text'] ?? '';

            $extracted[] = new BoundingBox(
                \is_string($text) ? $text : '',
                isset($boundingBox['left']) ? (float) $boundingBox['left'] : null,
                isset($boundingBox['top']) ? (float) $boundingBox['top'] : null,
                isset($boundingBox['width']) ? (float) $boundingBox['width'] : null,
                isset($boundingBox['height']) ? (float) $boundingBox['height'] : null,
            );
        }

        return $extracted;
    }
}
