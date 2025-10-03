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
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Exception\RuntimeException;

final readonly class Vectorizer implements VectorizerInterface
{
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function vectorizeEmbeddableDocuments(array $documents, array $options = []): array
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

    public function vectorize(string|\Stringable $string, array $options = []): Vector
    {
        $this->logger->debug('Vectorizing string', ['string' => (string) $string]);

        $result = $this->platform->invoke($this->model, (string) $string, $options);
        $vectors = $result->asVectors();

        if (!isset($vectors[0])) {
            throw new RuntimeException('No vector returned for string vectorization.');
        }

        return $vectors[0];
    }
}
