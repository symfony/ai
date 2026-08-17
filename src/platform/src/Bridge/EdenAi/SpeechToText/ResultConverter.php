<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;

use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Bridge\EdenAi\ResultMetadataTrait;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements ResultConverterInterface
{
    use ErrorHandlingTrait;
    use ResultMetadataTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $httpResponse = $result->getObject();

        // The asynchronous endpoint answers 202 when it accepts the job, and 200 when polled.
        $this->assertSuccessfulResponse($httpResponse, [200, 202]);

        $data = $result->getData();

        if ('fail' === ($data['status'] ?? null) || 'failed' === ($data['status'] ?? null)) {
            throw new RuntimeException(\sprintf('Eden AI request failed: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['output']['text']) || !\is_string($data['output']['text'])) {
            throw new RuntimeException('Response does not contain text.');
        }

        $textResult = new TextResult($data['output']['text']);

        $this->attachEdenAiMetadata($textResult, $data, [
            'diarization' => $data['output']['diarization'] ?? null,
        ]);

        return $textResult;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
