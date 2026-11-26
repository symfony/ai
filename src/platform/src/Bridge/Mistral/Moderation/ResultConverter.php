<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Moderation;

use Symfony\AI\Platform\Bridge\Mistral\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Mistral\Moderation;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\Result\ModerationResult;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\Result\Verdict;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Moderation;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['results']) || !\is_array($data['results']) || [] === $data['results']) {
            throw new RuntimeException('Response does not contain moderation results.');
        }

        $verdicts = array_map(static fn (array $result): Verdict => new Verdict(
            $result['categories'] ?? [],
            $result['category_scores'] ?? [],
        ), array_values($data['results']));

        return new ObjectResult(new ModerationResult($verdicts, $data['id'] ?? '', $data['model'] ?? ''));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
