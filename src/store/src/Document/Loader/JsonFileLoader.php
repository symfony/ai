<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\JsonPath\Exception\InvalidJsonStringInputException;
use Symfony\Component\JsonPath\JsonCrawler;

/**
 * @author Larry Sule-balogun <suleabimbola@gmail.com>
 */
final class JsonFileLoader implements LoaderInterface
{
    /**
     * @param string                $id       JsonPath resolving to document IDs
     * @param string                $content  JsonPath resolving to document content
     * @param array<string, string> $metadata JsonPath map for metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly string $content,
        private readonly array $metadata = [],
    ) {
        if (!class_exists(JsonCrawler::class)) {
            throw new RuntimeException('The "symfony/json-path" package is required to use the JsonFileLoader.');
        }
    }

    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('JsonFileLoader requires a file path as source, null given.');
        }

        if (!is_file($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        $content = file_get_contents($source);
        if (false === $content) {
            throw new RuntimeException(\sprintf('Unable to read file "%s"', $source));
        }

        try {
            $crawler = new JsonCrawler($content);
        } catch (InvalidJsonStringInputException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $ids = $crawler->find($this->id);
        $contents = $crawler->find($this->content);

        if ([] === $ids || [] === $contents) {
            throw new RuntimeException('JsonPath expressions did not match any documents.');
        }

        if (\count($ids) !== \count($contents)) {
            throw new RuntimeException('ID and content JsonPath results must have the same length.');
        }

        $normalizedMetadata = $this->normalizeMetadata($crawler, $this->metadata);
        foreach ($ids as $index => $id) {
            $docMetadata = [];

            foreach ($normalizedMetadata as $key => $values) {
                if (isset($values[$index])) {
                    $docMetadata[$key] = $values[$index];
                }
            }

            $docMetadata[Metadata::KEY_SOURCE] = $source;

            yield new TextDocument(
                id: (string) $id,
                content: (string) $contents[$index],
                metadata: new Metadata($docMetadata),
            );
        }
    }

    /**
     * @param array<string, string> $metadata
     *
     * @return array<string, array<int, mixed>>
     */
    private function normalizeMetadata(JsonCrawler $crawler, array $metadata): array
    {
        $normalizedMetadata = [];
        foreach ($metadata as $key => $path) {
            $values = $crawler->find($path);
            if ([] === $values) {
                continue;
            }

            foreach ($values as $value) {
                if (\is_array($value) || \is_object($value)) {
                    throw new RuntimeException(\sprintf('Metadata "%s" must resolve to a scalar value.', $key));
                }
            }

            $normalizedMetadata[$key] = $values;
        }

        return $normalizedMetadata;
    }
}
