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
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
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
        $crawler = new JsonCrawler($result->getObject()->getContent());

        return match (true) {
            (new UnicodeString($result->getObject()->getInfo('url')))->containsAny('completions') && [] !== $crawler->find('$.choices[0].message.content') => new TextResult($crawler->find('$.choices[0].message.content')[0]),
            (new UnicodeString($result->getObject()->getInfo('url')))->containsAny('speech') => new BinaryResult($result->getObject()->getContent()),
            (new UnicodeString($result->getObject()->getInfo('url')))->containsAny('embeddings') && [] !== $crawler->find('$.data[0].embedding') => new VectorResult(...array_map(
                static fn (array $embeddings): VectorInterface => new Vector($embeddings),
                $crawler->find('$.data[0].embedding'),
            )),
            default => throw new RuntimeException('Unsupported model capability.'),
        };
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
