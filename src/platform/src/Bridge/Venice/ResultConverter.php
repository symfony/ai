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
        $url = new UnicodeString($result->getObject()->getInfo('url'));

        if ($url->containsAny('completions') && ($options['stream'] ?? false)) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        $crawler = new JsonCrawler($result->getObject()->getContent());

        return match (true) {
            $url->containsAny('completions') && [] !== $crawler->find('$.choices[0].message.content') => new TextResult($crawler->find('$.choices[0].message.content')[0]),
            $url->containsAny('speech') => new BinaryResult($result->getObject()->getContent()),
            $url->containsAny('embeddings') && [] !== $crawler->find('$.data[0].embedding') => new VectorResult(...array_map(
                static fn (array $embeddings): VectorInterface => new Vector($embeddings),
                $crawler->find('$.data[0].embedding'),
            )),
            $url->containsAny('generations') && [] !== $crawler->find('$.data[*].url') => new TextResult(implode("\n", $crawler->find('$.data[*].url'))),
            default => throw new RuntimeException('Unsupported model capability.'),
        };
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function convertCompletionToGenerator(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $content = $data['choices'][0]['delta']['content'] ?? null;

            if (null !== $content) {
                yield $content;
            }

            if (isset($data['usage'])) {
                yield new TokenUsage(
                    promptTokens: $data['usage']['prompt_tokens'],
                    completionTokens: $data['usage']['completion_tokens'] ?? 0,
                    totalTokens: $data['usage']['total_tokens'],
                );
            }
        }
    }
}
