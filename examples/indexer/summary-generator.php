<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\SummaryGeneratorTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Summary Generator Transformer ===\n\n";
echo "This example demonstrates using the SummaryGeneratorTransformer to automatically\n";
echo "generate LLM-based summaries during document indexing and store them in metadata.\n\n";

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

$documents = [
    new TextDocument(
        Uuid::v4(),
        'Symfony is a set of reusable PHP components and a PHP framework for web projects. It was published as free software in 2005. The framework follows the model-view-controller design pattern, and provides components for routing, templating, form creation, authentication, and caching among others.',
        new Metadata(['title' => 'Symfony Framework'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Retrieval Augmented Generation (RAG) is a technique that combines the power of large language models with external knowledge retrieval. It works by first retrieving relevant documents from a knowledge base, then using those documents as context for the language model to generate more accurate and grounded responses.',
        new Metadata(['title' => 'RAG Technique'])
    ),
];

$indexer = new DocumentIndexer(
    new DocumentProcessor(
        vectorizer: $vectorizer,
        store: $store,
        transformers: [
            new SummaryGeneratorTransformer($platform, 'gpt-4o-mini'),
        ],
        logger: logger(),
    ),
);

echo "Indexing documents with LLM summary generation...\n\n";
$indexer->index($documents);

$vector = $vectorizer->vectorize('What is RAG?');
$results = $store->query(new VectorQuery($vector));

echo "Search results for 'What is RAG?':\n\n";
foreach ($results as $i => $document) {
    $summary = $document->getMetadata()->getSummary();
    echo sprintf("%d. %s\n", $i + 1, $document->getId());
    if (null !== $summary) {
        echo sprintf("   Summary: %s\n", $summary);
    }
    echo "\n";
}
