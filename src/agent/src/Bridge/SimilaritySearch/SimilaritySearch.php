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
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Store\RetrieverInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('similarity_search', description: 'Searches for documents similar to a query or sentence.')]
final class SimilaritySearch implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly RetrieverInterface $retriever,
    ) {
    }

    /**
     * @param string $searchTerm string used for similarity search
     */
    public function __invoke(string $searchTerm): SimilaritySearchResult
    {
        // taken before retrieving, since a suspending retriever lets a parallel call replace the collection
        $sources = $this->getSourceCollection();
        $documents = iterator_to_array($this->retriever->retrieve($searchTerm), false);

        foreach ($documents as $document) {
            $sources->add(new DocumentSource($document));
        }

        return new SimilaritySearchResult($documents);
    }
}
