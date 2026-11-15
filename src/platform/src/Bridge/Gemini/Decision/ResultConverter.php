<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * Converts the response of the Gemini Decisions API into {@see \Symfony\AI\Platform\Decision\Answer\Answers}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();
        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            // e.g. 402 for depleted prepayment credits
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $this->extractErrorMessage($httpResponse) ?? $httpResponse->getContent(false)));
        }

        return new ObjectResult((new AnswersConverter())->convert($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
