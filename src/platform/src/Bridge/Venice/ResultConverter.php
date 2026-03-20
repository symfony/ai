<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $rawUrl = $response->getInfo('url');

        if (!\is_string($rawUrl)) {
            throw new RuntimeException('Expected URL info to be a string.');
        }

        $url = new UnicodeString($rawUrl);

        if ($url->containsAny('completions') && ($options['stream'] ?? false)) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        $crawler = new JsonCrawler($response->getContent());

        if ($url->containsAny('completions')) {
            $completionContent = $crawler->find('$.choices[0].message.content');

            if ([] !== $completionContent && \is_string($completionContent[0])) {
                return new TextResult($completionContent[0]);
            }
        }

        if ($url->containsAny('speech')) {
            return new BinaryResult($response->getContent());
        }

        if ($url->containsAny('embeddings')) {
            /** @var list<list<float>> $embeddings */
            $embeddings = $crawler->find('$.data[0].embedding');

            if ([] !== $embeddings) {
                return new VectorResult(...array_map(
                    static fn (array $embedding): VectorInterface => new Vector($embedding),
                    $embeddings,
                ));
            }
        }

        if ($url->containsAny('generations')) {
            $imageUrls = array_filter($crawler->find('$.data[*].url'), \is_string(...));

            if ([] !== $imageUrls) {
                return new TextResult(implode("\n", $imageUrls));
            }
        }

        throw new RuntimeException('Unsupported model capability.');
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function convertCompletionToGenerator(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $choices = $data['choices'] ?? [];
            $delta = \is_array($choices) && \is_array($choices[0] ?? null) ? ($choices[0]['delta'] ?? []) : [];
            $content = \is_array($delta) ? ($delta['content'] ?? null) : null;

            if (null !== $content) {
                yield $content;
            }

            $usage = $data['usage'] ?? null;

            if (\is_array($usage)) {
                yield new TokenUsage(
                    promptTokens: isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null,
                    completionTokens: isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : 0,
                    totalTokens: isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null,
                );
            }
        }
    }
}
