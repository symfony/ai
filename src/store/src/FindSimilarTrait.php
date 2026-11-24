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

use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Implements StoreInterface::findSimilar() with a vector query on the vector of the found document.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
trait FindSimilarTrait
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<VectorDocumentInterface>
     */
    public function findSimilar(string $id, array $options = []): iterable
    {
        $document = $this->find($id);

        if (null === $document) {
            return [];
        }

        $limit = $options['limit'] ?? null;
        if (null !== $limit) {
            // the document is its own nearest neighbor, so one more is fetched to make up for leaving it out
            $options['limit'] = $limit + 1;
        }

        $similarDocuments = [];
        foreach ($this->query(new VectorQuery($document->getVector()), $options) as $similarDocument) {
            if ((string) $similarDocument->getId() === (string) $document->getId()) {
                continue;
            }

            $similarDocuments[] = $similarDocument;

            if (\count($similarDocuments) === $limit) {
                break;
            }
        }

        return $similarDocuments;
    }
}
