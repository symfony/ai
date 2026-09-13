<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Exception\RuntimeException;

final class Vectorizer implements VectorizerInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $includeText = false,
    ) {
    }

    public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocumentInterface|array
    {
        if (\is_string($values) || $values instanceof \Stringable) {
            return $this->vectorizeString($values, $options);
        }

        if ($values instanceof EmbeddableDocumentInterface) {
            return $this->vectorizeEmbeddableDocument($values, $options);
        }

        if ([] === $values) {
            return [];
        }

        $firstElement = reset($values);
        if ($firstElement instanceof EmbeddableDocumentInterface) {
            $this->validateArray($values, EmbeddableDocumentInterface::class);

            return $this->vectorizeEmbeddableDocuments($values, $options);
        }

        if (\is_string($firstElement) || $firstElement instanceof \Stringable) {
            $this->validateArray($values, 'string|stringable');

            return $this->vectorizeStrings($values, $options);
        }

        throw new RuntimeException('Array must contain only strings, Stringable objects, or EmbeddableDocumentInterface instances.');
    }

    /**
     * @param array<mixed> $values
     */
    private function validateArray(array $values, string $expectedType): void
    {
        foreach ($values as $value) {
            if ('string|stringable' === $expectedType) {
                if (!\is_string($value) && !$value instanceof \Stringable) {
                    throw new RuntimeException('Array must contain only strings or Stringable objects.');
                }
            } elseif (!$value instanceof $expectedType) {
                throw new RuntimeException(\sprintf('Array must contain only "%s" instances.', $expectedType));
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface
     */
    private function vectorizeString(string|\Stringable $string, array $options = []): Vector
    {
        $stringValue = (string) $string;
        $this->logger->debug('Vectorizing string', ['string' => $stringValue]);

        $result = $this->platform->invoke($this->model, $stringValue, $options);
        $vectors = $result->asVectors();

        if (!isset($vectors[0])) {
            throw new RuntimeException('No vector returned for string vectorization.');
        }

        return $vectors[0];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface
     */
    private function vectorizeEmbeddableDocument(EmbeddableDocumentInterface $document, array $options = []): VectorDocumentInterface
    {
        $this->logger->debug('Vectorizing embeddable document', ['document_id' => $document->getId()]);
        $result = $this->platform->invoke($this->model, $document->getContent(), $options);
        $vectors = $result->asVectors();

        if (!isset($vectors[0])) {
            throw new RuntimeException('No vector returned for vectorization.');
        }

        // Preserve the original text in metadata so downstream consumers
        // (e.g. text search, reranking) can access it via Metadata::getText().
        $metadata = $document->getMetadata();
        if ($this->includeText && !$metadata->hasText()) {
            $metadata->setText($document->getContent());
        }

        return new VectorDocument($document->getId(), $vectors[0], $metadata);
    }

    /**
     * @param array<string|\Stringable> $strings
     * @param array<string, mixed>      $options
     *
     * @return array<Vector>
     */
    private function vectorizeStrings(array $strings, array $options = []): array
    {
        $stringCount = \count($strings);
        $this->logger->info('Starting vectorization of strings', ['string_count' => $stringCount]);

        // Convert all values to strings
        $stringValues = array_map(static fn (string|\Stringable $s) => (string) $s, $strings);

        $result = $this->platform->invoke($this->model, $stringValues, $options);
        $vectors = $result->asVectors();

        $this->logger->info('Vectorization process completed', ['string_count' => $stringCount, 'vector_count' => \count($vectors)]);

        return $vectors;
    }

    /**
     * @param array<EmbeddableDocumentInterface> $documents
     * @param array<string, mixed>               $options
     *
     * @return array<VectorDocumentInterface>
     */
    private function vectorizeEmbeddableDocuments(array $documents, array $options = []): array
    {
        $documentCount = \count($documents);
        $this->logger->info('Starting vectorization process', ['document_count' => $documentCount]);

        $result = $this->platform->invoke($this->model, array_map(static fn (EmbeddableDocumentInterface $document) => $document->getContent(), $documents), $options);
        $vectors = $result->asVectors();

        $vectorDocuments = [];
        foreach ($documents as $i => $document) {
            // Preserve the original text in metadata so downstream consumers
            // (e.g. text search, reranking) can access it via Metadata::getText().
            $metadata = $document->getMetadata();
            if ($this->includeText && !$metadata->hasText()) {
                $metadata->setText($document->getContent());
            }

            $vectorDocuments[] = new VectorDocument($document->getId(), $vectors[$i], $metadata);
        }

        $this->logger->info('Vectorization process completed', [
            'document_count' => $documentCount,
            'vector_document_count' => \count($vectorDocuments),
        ]);

        return $vectorDocuments;
    }
}
