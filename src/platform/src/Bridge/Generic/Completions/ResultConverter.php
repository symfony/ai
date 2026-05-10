<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * This default implementation is based on the OpenAI GPT completion API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
class ResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof CompletionsModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
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
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new RateLimitExceededException(null, $errorMessage);
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
