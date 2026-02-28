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
use Symfony\AI\Store\Document\Loader\RstToctreeLoader;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Retriever;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Symfony Docs RAG with RstToctreeLoader ===\n\n";
echo "This example demonstrates loading the Symfony documentation from RST files,\n";
echo "indexing them into a vector store, and retrieving relevant sections for a question.\n\n";

// 1. Clone or update Symfony docs
$docsDir = __DIR__.'/.symfony-docs';
if (!is_dir($docsDir.'/.git')) {
    output()->writeln('Cloning symfony/symfony-docs (this may take a moment)...');
    exec('git clone --depth 1 https://github.com/symfony/symfony-docs.git '.escapeshellarg($docsDir), $out, $code);
    if (0 !== $code) {
        output()->writeln('<error>Failed to clone symfony-docs repository.</error>');
        exit(1);
    }
} else {
    output()->writeln('Updating symfony/symfony-docs...');
    exec('git -C '.escapeshellarg($docsDir).' pull --ff-only', $out, $code);
}

// 2. Load & Index
$store = new InMemoryStore();
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$processor = new DocumentProcessor($vectorizer, $store, logger: logger());

$loader = new RstToctreeLoader();
$indexer = new SourceIndexer($loader, $processor);

output()->writeln('Indexing Symfony docs — this will produce many chunks and use embedding API credits...');
$indexer->index($docsDir.'/index.rst');
output()->writeln('<info>Indexing complete.</info>');

// 3. Retrieve
$retriever = new Retriever($store, $vectorizer, logger());
$question = $argv[1] ?? 'How do I create a custom console command in Symfony?';

output()->writeln('');
output()->writeln(sprintf('Question: <comment>%s</comment>', $question));
output()->writeln('');

foreach ($retriever->retrieve($question, ['maxItems' => 5]) as $document) {
    $metadata = $document->getMetadata();
    output()->writeln(sprintf(
        '<info>[%.4f]</info> <comment>%s</comment> (%s)',
        $document->getScore() ?? 0.0,
        $metadata->getSectionTitle() ?? '(no title)',
        $metadata->getSource() ?? '(unknown)',
    ));

    $text = $metadata->getText() ?? '';
    output()->writeln('  '.substr($text, 0, 200).'...');
    output()->writeln('');
}
