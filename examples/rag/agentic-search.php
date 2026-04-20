<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\AgenticSearch\AgenticSearchTools;
use Symfony\AI\Fixtures\AgenticSearch\DocumentCorpus;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * Agentic Search Example
 *
 * Demonstrates an iterative, multi-hop search pattern inspired by Chroma's Context 1 model.
 * Instead of single-hop RAG (one query -> one result set), an LLM agent uses four
 * specialized tools to iteratively explore a document corpus:
 *
 * - corpus_search:        Semantic vector search for broad topic discovery (deduplicated across calls)
 * - corpus_grep:          Line-by-line keyword matching on full document content
 * - corpus_read_document: Full document retrieval by ID for deeper investigation
 * - corpus_prune:         Permanently exclude irrelevant documents from future results
 *
 * The agent plans its search strategy, discovers information across multiple hops,
 * and curates a focused set of relevant documents.
 */

echo "=== Agentic Search Example ===\n\n";
echo "Building document corpus from movie fixtures...\n";

// 1. Build the document corpus - one document per movie, preferring detailed markdown versions
$store = new InMemoryStore();
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());

$originalDocuments = [];
$documents = [];

// Load detailed markdown files and track which movies have them
$fixturesDir = dirname(__DIR__, 2).'/fixtures/movies';
$movieFiles = glob($fixturesDir.'/*.md') ?: [];
$detailedMovies = [];

foreach ($movieFiles as $file) {
    $id = (string) Uuid::v4();
    $content = (string) file_get_contents($file);

    // Extract title from first markdown heading
    $title = basename($file, '.md');
    if (preg_match('/^#\s+(.+)/m', $content, $matches)) {
        $title = $matches[1];
    }

    // Extract the plain movie name (without year) for dedup matching
    $plainTitle = preg_replace('/\s*\(\d{4}\)$/', '', $title) ?? $title;
    $detailedMovies[strtolower($plainTitle)] = true;

    $metadata = new Metadata([
        'source' => basename($file),
        'type' => 'detailed',
    ]);
    $metadata->setTitle($title);

    $textDoc = new TextDocument(id: $id, content: $content, metadata: $metadata);
    $documents[] = $textDoc;
    $originalDocuments[$id] = $textDoc;
}

// Add short descriptions only for movies that don't have a detailed markdown version
foreach (Movies::all() as $movie) {
    if (isset($detailedMovies[strtolower($movie['title'])])) {
        continue;
    }

    $id = (string) Uuid::v4();
    $content = 'Title: '.$movie['title']."\nDirector: ".$movie['director']."\nDescription: ".$movie['description'];

    $metadata = new Metadata($movie);
    $metadata->setTitle($movie['title']);

    $textDoc = new TextDocument(id: $id, content: $content, metadata: $metadata);
    $documents[] = $textDoc;
    $originalDocuments[$id] = $textDoc;
}

echo sprintf("Indexing %d documents...\n", count($documents));

// 2. Index documents into the vector store
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

echo "Done.\n\n";

// 3. Wire up the agentic search tools with event-based logging
$corpus = new DocumentCorpus($vectorizer, $store, $originalDocuments);
$tools = new AgenticSearchTools($corpus);

$dispatcher = new EventDispatcher();
$dispatcher->addListener(ToolCallSucceeded::class, static function (ToolCallSucceeded $event): void {
    $name = $event->getMetadata()->getName();
    $args = $event->getArguments();
    $resultPreview = $event->getResult()->getResult();

    if (is_string($resultPreview) && strlen($resultPreview) > 300) {
        $resultPreview = substr($resultPreview, 0, 300).'... (truncated)';
    }

    output()->writeln(sprintf('<info>[Tool Call] %s</info>', $name));
    output()->writeln(sprintf('  <comment>Args:</comment> %s', json_encode($args, \JSON_UNESCAPED_SLASHES)));
    output()->writeln(sprintf('  <comment>Result:</comment> %s', $resultPreview));
    output()->writeln('');
});

$toolbox = new Toolbox([$tools], logger: logger(), eventDispatcher: $dispatcher);
$processor = new AgentProcessor($toolbox, eventDispatcher: $dispatcher);

$systemPrompt = <<<'PROMPT'
You are a thorough search research agent. Your job is to find ALL relevant documents in a corpus to answer a research question completely.

## Strategy
1. Start by planning what information you need to find.
2. Use corpus_search for broad semantic discovery (find documents by topic/meaning).
3. Use corpus_grep for specific facts (names, dates, numbers, exact terms). This is especially powerful for structured fields like "Director:", "Cast", or other metadata.
4. Use corpus_read_document to get full document content when snippets aren't enough.
5. Use corpus_prune to permanently exclude irrelevant documents from your results.
6. Iterate: each discovery may reveal new leads requiring additional searches. A single search is almost never enough. Follow every lead — if a document mentions other related entities, search for those too.

## Important
- Search results are automatically deduplicated: each document appears only once across all your search calls.
- Pruned documents are permanently excluded from all future search and grep results.
- Search broadly first, then narrow down with grep or by reading full documents.
- Prune documents that turned out to be irrelevant to keep your working set focused.
- Do NOT stop after one round of searching. Combine corpus_search and corpus_grep with different queries to ensure full coverage. For example, if you find a director, grep for their name to find ALL their movies — do not rely on a single semantic search.
- Read every document that might be relevant. Skipping documents leads to incomplete answers.
- When you're done, provide a clear and exhaustive summary of your findings organized by the question asked.
PROMPT;

$agent = new Agent(
    $platform,
    'gpt-4.1',
    [new SystemPromptInputProcessor($systemPrompt), $processor],
    [$processor],
);

// 4. Ask a multi-hop question that requires iterative search
$question = 'Identify directors who have directed multiple movies in our corpus. '
    .'For these directors, list all their movies and identify if any actors appear in more than one of their films.';

echo "Question: {$question}\n\n";
echo "--- Agent Search Process ---\n\n";

$messages = new MessageBag(Message::ofUser($question));
$result = $agent->call($messages);

echo $result->getContent()."\n\n";

// 5. Show the final working set
echo "--- Final Working Set ---\n";
echo $corpus->formatFoundSummary()."\n";
