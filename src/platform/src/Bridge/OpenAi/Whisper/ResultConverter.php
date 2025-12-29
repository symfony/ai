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
        $result = ($options['verbose'] ?? false) ? $this->getVerboseResult($data) : new TextResult($data['text']);

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
            array_map(fn (array $segment) => new Segment($segment['start'], $segment['end'], $segment['text']), $data['segments']),
        ));
    }
}
