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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('similarity_search', description: 'Searches for documents similar to a query or sentence.')]
final class SimilaritySearch
{
    /**
     * @var VectorDocument[]
     */
    public array $usedDocuments = [];

    public function __construct(
        private readonly VectorizerInterface $vectorizer,
        private readonly StoreInterface $store,
    ) {
    }

    /**
     * @param string $searchTerm string used for similarity search
     */
    public function __invoke(string $searchTerm): string
    {
        $vector = $this->vectorizer->vectorize($searchTerm);
        $this->usedDocuments = iterator_to_array($this->store->query(new VectorQuery($vector)));

        if ([] === $this->usedDocuments) {
            return 'No results found';
        }

        $result = 'Found documents with following information:'.\PHP_EOL;
        foreach ($this->usedDocuments as $document) {
            $result .= json_encode($document->getMetadata());
        }

        return $result;
    }
}
