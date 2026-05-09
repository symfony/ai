<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper;

use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Segment;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Transcript;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Whisper;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();
        $response = $result->getObject();

        if (401 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'];
            throw new AuthenticationException($errorMessage);
        }

        if (400 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Bad Request';
            throw new BadRequestException($errorMessage);
        }

        if (429 === $response->getStatusCode()) {
            throw new RateLimitExceededException();
        }

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!($options['verbose'] ?? false) && !isset($data['text'])) {
            throw new RuntimeException(\sprintf('The response is missing the required "text" field. Response data: "%s"', json_encode($data)));
        }
        if ($options['verbose'] ?? false) {
            /** @var array{text: string, language: string, duration: float, segments: array<array{start: float, end: float, text: string}>} $data */
            $result = $this->getVerboseResult($data);
        } else {
            $result = new TextResult($data['text']);
        }

        if (isset($data['usage'])) {
            $result->getMetadata()->add('usage', $data['usage']);
        }

        return $result;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array{
     *     text: string,
     *     language: string,
     *     duration: float,
     *     segments: array{start: float, end: float, text: string}[]
     * } $data
     */
    private function getVerboseResult(array $data): ObjectResult
    {
        if (!isset($data['text']) || !isset($data['language']) || !isset($data['duration']) || !isset($data['segments'])) {
            throw new RuntimeException('The verbose response is missing required fields: text, language, duration, or segments.');
        }

        return new ObjectResult(new Transcript(
            $data['text'],
            $data['language'],
            $data['duration'],
            array_map(static fn (array $segment) => new Segment($segment['start'], $segment['end'], $segment['text']), $data['segments']),
        ));
    }
}
