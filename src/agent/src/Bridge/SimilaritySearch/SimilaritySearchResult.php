<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SimilaritySearch;

use Symfony\AI\Store\Document\VectorDocumentInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SimilaritySearchResult implements \JsonSerializable
{
    /**
     * @param list<VectorDocumentInterface> $documents
     */
    public function __construct(
        private readonly array $documents,
    ) {
    }

    /**
     * @return list<VectorDocumentInterface>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (VectorDocumentInterface $document): array => ['id' => $document->getId()] + $document->getMetadata()->getArrayCopy(),
            $this->documents,
        );
    }
}
