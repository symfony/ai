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

        if ($url->containsAny('embeddings')) {
            $crawler = new JsonCrawler($response->getContent());

            if ([] === $embeddings = $crawler->find('$.data[0].embedding')) {
                throw new InvalidArgumentException('No embeddings found in the response.');
            }

            return new VectorResult(...array_map(
                static function (mixed $embedding): VectorInterface {
                    if (!\is_array($embedding)) {
                        throw new InvalidArgumentException('Expected embedding to be an array.');
                    }

                    return new Vector(array_map(
                        static function (mixed $v): float {
                            if (!\is_float($v) && !\is_int($v)) {
                                throw new InvalidArgumentException('Expected embedding value to be a number.');
                            }

                            return (float) $v;
                        },
                        array_values($embedding),
                    ));
                },
                $embeddings,
            ));
        }

        if ($url->containsAny('completions') && ($options['stream'] ?? false)) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        if ($url->containsAny('completions')) {
            $crawler = new JsonCrawler($response->getContent());

            $completionContent = $crawler->find('$.choices[0].message.content');

            if ([] === $completionContent || !\is_string($completionContent[0])) {
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

            if (!\is_array($images[0] ?? null)) {
                throw new InvalidArgumentException('No images found in the response.');
            }

            /** @var list<string> $imageList */
            $imageList = $images[0];

            if (1 < \count($imageList)) {
                return new ChoiceResult(array_map(
                    static fn (string $imageAsBase64): BinaryResult => new BinaryResult(base64_decode($imageAsBase64)),
                    $imageList,
                ));
            }

            if (!\is_string($imageList[0])) {
                throw new InvalidArgumentException('Expected image data to be a base64 string.');
            }

            return new BinaryResult(base64_decode($imageList[0]));
        }

        if ($url->containsAny('video/retrieve')) {
            return new BinaryResult($response->getContent());
        }

        if ($url->containsAny('transcription')) {
            $transcription = $response->toArray();

            if (!\is_string($transcription['text'] ?? null)) {
                throw new InvalidArgumentException('No transcription text found in the response.');
            }

            return new TextResult($transcription['text']);
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
            if (!\is_array($chunk)) {
                continue;
            }

            $choices = $chunk['choices'] ?? [];

            if (\is_array($choices) && [] !== $choices) {
                $firstChoice = $choices[0] ?? [];

                if (\is_array($firstChoice)) {
                    $delta = $firstChoice['delta'] ?? [];

                    if (\is_array($delta)) {
                        $content = $delta['content'] ?? '';

                        if (\is_string($content)) {
                            yield $content;
                        }
                    }
                }
            }

            $usage = $chunk['usage'] ?? null;

            if (\is_array($usage)) {
                $promptTokens = isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null;
                $completionTokens = isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : null;
                $totalTokens = isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null;

                yield new TokenUsage(
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    totalTokens: $totalTokens,
                );
            }
        }
    }
}
