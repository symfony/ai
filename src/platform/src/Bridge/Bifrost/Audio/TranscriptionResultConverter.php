<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Audio;

use Symfony\AI\Platform\Bridge\Bifrost\Audio\Result\Segment;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\Result\Transcript;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TranscriptionResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof TranscriptionModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        $data = $result->getData();

        if (isset($data['error']) && \is_array($data['error'])) {
            $code = \is_string($data['error']['code'] ?? null) ? $data['error']['code'] : '-';
            $type = \is_string($data['error']['type'] ?? null) ? $data['error']['type'] : '-';
            $param = \is_string($data['error']['param'] ?? null) ? $data['error']['param'] : '-';
            $message = \is_string($data['error']['message'] ?? null) ? $data['error']['message'] : '-';

            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $code, $type, $param, $message));
        }

        $verbose = (bool) ($options['verbose'] ?? false);

        if ($verbose) {
            $transcription = $this->buildTranscript($data);
        } else {
            if (!isset($data['text']) || !\is_string($data['text'])) {
                throw new RuntimeException(\sprintf('The response is missing the required "text" field. Response data: "%s"', json_encode($data)));
            }

            $transcription = new TextResult($data['text']);
        }

        if (isset($data['usage']) && \is_array($data['usage'])) {
            $transcription->getMetadata()->add('usage', $data['usage']);
        }

        return $transcription;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildTranscript(array $data): ObjectResult
    {
        if (
            !isset($data['text'], $data['language'], $data['duration'], $data['segments'])
            || !\is_string($data['text'])
            || !\is_string($data['language'])
            || !is_numeric($data['duration'])
            || !\is_array($data['segments'])
        ) {
            throw new RuntimeException('The verbose response is missing required fields: text, language, duration, or segments.');
        }

        $segments = [];
        foreach ($data['segments'] as $segment) {
            if (
                !\is_array($segment)
                || !isset($segment['start'], $segment['end'], $segment['text'])
                || !is_numeric($segment['start'])
                || !is_numeric($segment['end'])
                || !\is_string($segment['text'])
            ) {
                throw new RuntimeException('A transcript segment is missing required fields: start, end, or text.');
            }

            $segments[] = new Segment((float) $segment['start'], (float) $segment['end'], $segment['text']);
        }

        return new ObjectResult(new Transcript($data['text'], $data['language'], (float) $data['duration'], $segments));
    }
}
