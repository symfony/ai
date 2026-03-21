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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
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

        if ($url->containsAny('embeddings')) {
            $crawler = new JsonCrawler($response->getContent());

            if ([] === $embeddings = $crawler->find('$.data[0].embedding')) {
                throw new InvalidArgumentException('No embeddings found in the response.');
            }

            return new VectorResult(...array_map(
                static fn (array $embedding): VectorInterface => new Vector($embedding),
                $embeddings,
            ));
        }

        if ($url->containsAny('completions') && ($options['stream'] ?? false)) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        if ($url->containsAny('completions')) {
            $crawler = new JsonCrawler($response->getContent());

            $completionContent = $crawler->find('$.choices[0].message.content');

            if ([] === $completionContent) {
                throw new InvalidArgumentException('No completions found in the response.');
            }

            return new TextResult($completionContent[0]);
        }

        if ($url->containsAny('audio/speech')) {
            return new BinaryResult($response->getContent());
        }

        if ($url->containsAny('image/generate')) {
            $crawler = new JsonCrawler($response->getContent());

            $images = $crawler->find('$.images');

            if (1 < \count($images)) {
                return new ChoiceResult(...array_map(
                    static fn (string $imageAsBase64): BinaryResult => new BinaryResult(base64_decode($imageAsBase64)),
                    $images,
                ));
            }

            return new BinaryResult(base64_decode($images[0][0]));
        }

        if ($url->containsAny('transcription')) {
            return new TextResult($response->toArray()['text']);
        }

        throw new RuntimeException('Unsupported model capability.');
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function convertCompletionToGenerator(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $chunk) {
            yield new VeniceMessageChunk(
                $chunk['id'],
                $chunk['model'],
                \DateTimeImmutable::createFromFormat('U', $chunk['created']),
                $chunk['choices'][0]['delta']['content'] ?? '',
            );
        }
    }
}
