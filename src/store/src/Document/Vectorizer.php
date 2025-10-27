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
use Symfony\AI\Platform\Capability;
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
    ) {
    }

    public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocument|array
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
    private function vectorizeEmbeddableDocument(EmbeddableDocumentInterface $document, array $options = []): VectorDocument
    {
        $this->logger->debug('Vectorizing embeddable document', ['document_id' => $document->getId()]);
        $result = $this->platform->invoke($this->model, $document->getContent(), $options);
        $vectors = $result->asVectors();

        if (!isset($vectors[0])) {
            throw new RuntimeException('No vector returned for vectorization.');
        }

        return new VectorDocument($document->getId(), $vectors[0], $document->getMetadata());
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
        $stringValues = array_map(fn (string|\Stringable $s) => (string) $s, $strings);

        if ($this->platform->getModelCatalog()->getModel($this->model)->supports(Capability::INPUT_MULTIPLE)) {
            $this->logger->debug('Using batch vectorization with model that supports multiple inputs');
            $result = $this->platform->invoke($this->model, $stringValues, $options);

            $vectors = $result->asVectors();
            $this->logger->debug('Batch vectorization completed', ['vector_count' => \count($vectors)]);
        } else {
            $this->logger->debug('Using sequential vectorization for model without multiple input support');
            $results = [];
            foreach ($stringValues as $i => $string) {
                $this->logger->debug('Vectorizing string', ['string_index' => $i]);
                $results[] = $this->platform->invoke($this->model, $string, $options);
            }

            $vectors = [];
            foreach ($results as $result) {
                $vectors = array_merge($vectors, $result->asVectors());
            }
            $this->logger->debug('Sequential vectorization completed', ['vector_count' => \count($vectors)]);
        }

        $this->logger->info('Vectorization process completed', ['string_count' => $stringCount, 'vector_count' => \count($vectors)]);

        return $vectors;
    }

    /**
     * @param array<EmbeddableDocumentInterface> $documents
     * @param array<string, mixed>               $options
     *
     * @return array<VectorDocument>
     */
    private function vectorizeEmbeddableDocuments(array $documents, array $options = []): array
    {
        $documentCount = \count($documents);
        $this->logger->info('Starting vectorization process', ['document_count' => $documentCount]);

        if ($this->platform->getModelCatalog()->getModel($this->model)->supports(Capability::INPUT_MULTIPLE)) {
            $this->logger->debug('Using batch vectorization with model that supports multiple inputs');
            $result = $this->platform->invoke($this->model, array_map(fn (EmbeddableDocumentInterface $document) => $document->getContent(), $documents), $options);

            $vectors = $result->asVectors();
            $this->logger->debug('Batch vectorization completed', ['vector_count' => \count($vectors)]);
        } else {
            $this->logger->debug('Using sequential vectorization for model without multiple input support');
            $results = [];
            foreach ($documents as $i => $document) {
                $this->logger->debug('Vectorizing document', ['document_index' => $i, 'document_id' => $document->getId()]);
                $results[] = $this->platform->invoke($this->model, $document->getContent(), $options);
            }

            $vectors = [];
            foreach ($results as $result) {
                $vectors = array_merge($vectors, $result->asVectors());
            }
            $this->logger->debug('Sequential vectorization completed', ['vector_count' => \count($vectors)]);
        }

        $vectorDocuments = [];
        foreach ($documents as $i => $document) {
            $vectorDocuments[] = new VectorDocument($document->getId(), $vectors[$i], $document->getMetadata());
        }

        $this->logger->info('Vectorization process completed', [
            'document_count' => $documentCount,
            'vector_document_count' => \count($vectorDocuments),
        ]);

        return $vectorDocuments;
    }
}
