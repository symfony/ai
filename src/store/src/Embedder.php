<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Embedder
{
    private ClockInterface $clock;

    public function __construct(
        private PlatformInterface $platform,
        private Model $model,
        private StoreInterface $store,
        ?ClockInterface $clock = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->clock = $clock ?? Clock::get();
    }

    /**
     * @param TextDocument|TextDocument[] $documents
     */
    public function embed(TextDocument|array $documents, int $chunkSize = 0, int $sleep = 0): void
    {
        if ($documents instanceof TextDocument) {
            $documents = [$documents];
        }

        if ([] === $documents) {
            $this->logger->debug('No documents to embed');

            return;
        }

        $chunks = 0 !== $chunkSize ? array_chunk($documents, $chunkSize) : [$documents];

        foreach ($chunks as $chunk) {
            $this->store->add(...$this->createVectorDocuments($chunk));

            if (0 !== $sleep) {
                $this->clock->sleep($sleep);
            }
        }
    }

    /**
     * @param TextDocument[] $documents
     *
     * @return VectorDocument[]
     */
    private function createVectorDocuments(array $documents): array
    {
        if ($this->model->supports(Capability::INPUT_MULTIPLE)) {
            $response = $this->platform->request($this->model, array_map(fn (TextDocument $document) => $document->content, $documents));

            $vectors = $response->getContent();
        } else {
            $responses = [];
            foreach ($documents as $document) {
                $responses[] = $this->platform->request($this->model, $document->content);
            }

            $vectors = [];
            foreach ($responses as $response) {
                $vectors = array_merge($vectors, $response->getContent());
            }
        }

        $vectorDocuments = [];
        foreach ($documents as $i => $document) {
            $vectorDocuments[] = new VectorDocument($document->id, $vectors[$i], $document->metadata);
        }

        return $vectorDocuments;
    }
}
