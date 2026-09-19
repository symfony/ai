<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe;

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\AnswerInterface;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\NoulAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ScoreAnswer;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Jev;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        if (422 === $httpResponse->getStatusCode()) {
            throw new BadRequestException($this->extractErrorMessage($httpResponse) ?? 'Unprocessable Entity');
        }

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['answers']) || !\is_array($data['answers'])) {
            throw new RuntimeException('Response does not contain answers.');
        }

        $answers = [];
        foreach ($data['answers'] as $id => $answer) {
            $answers[$id] = $this->convertAnswer((string) $id, $answer);
        }

        return new ObjectResult(new Answers($answers));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * TypeSafe nests its errors under a "detail" key, which the default implementation of the trait does not read:
     * a message for API errors, and a list of violations for payloads failing validation.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $detail = $data['detail'] ?? null;

        if (\is_string($detail)) {
            return $detail;
        }

        if (!\is_array($detail)) {
            return null;
        }

        if (\is_string($detail['message'] ?? null)) {
            return $detail['message'];
        }

        $violations = [];
        foreach ($detail as $violation) {
            if (\is_array($violation) && isset($violation['loc'], $violation['msg'])) {
                $violations[] = \sprintf('%s: %s', implode('.', $violation['loc']), $violation['msg']);
            }
        }

        return [] !== $violations ? implode('; ', $violations) : null;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function convertAnswer(string $id, array $answer): AnswerInterface
    {
        $type = $answer['type'] ?? null;

        $requiredKeys = match ($type) {
            'noul' => ['noul'],
            'choice' => ['choice', 'probabilities', 'confidence'],
            'score' => ['score', 'legend', 'confidence'],
            default => [],
        };

        foreach ($requiredKeys as $key) {
            if (!isset($answer[$key])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" is missing the "%s" key.', $id, $type, $key));
            }
        }

        return match ($type) {
            'noul' => NoulAnswer::fromArray($answer),
            'choice' => ChoiceAnswer::fromArray($answer),
            'score' => ScoreAnswer::fromArray($answer),
            default => throw new RuntimeException(\sprintf('Answer "%s" has an unsupported type "%s".', $id, \is_string($type) ? $type : get_debug_type($type))),
        };
    }
}
