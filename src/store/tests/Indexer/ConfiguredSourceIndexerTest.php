<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Indexer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\ConfiguredSourceIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

final class ConfiguredSourceIndexerTest extends TestCase
{
    public function testIndexUsesDefaultSourceWhenNoneProvided()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $innerIndexer = new SourceIndexer($loader, $processor);
        $configuredIndexer = new ConfiguredSourceIndexer($innerIndexer, 'default-source');

        // When calling index() without source, it should use the default source
        $configuredIndexer->index();

        $this->assertCount(1, $store->documents);
    }

    public function testIndexOverridesDefaultSourceWhenProvided()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $innerIndexer = new SourceIndexer($loader, $processor);
        $configuredIndexer = new ConfiguredSourceIndexer($innerIndexer, 'default-source');

        // When calling index() with a source, it should override the default
        $configuredIndexer->index('override-source');

        $this->assertCount(1, $store->documents);
    }

    public function testIndexWithArrayDefaultSource()
    {
        $document1 = new TextDocument(Uuid::v4(), 'Document 1');
        $document2 = new TextDocument(Uuid::v4(), 'Document 2');
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vector3 = new Vector([0.7, 0.8, 0.9]);
        $vector4 = new Vector([1.0, 1.1, 1.2]);
        $loader = new InMemoryLoader([$document1, $document2]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2, $vector3, $vector4)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $innerIndexer = new SourceIndexer($loader, $processor);
        $configuredIndexer = new ConfiguredSourceIndexer($innerIndexer, ['source1', 'source2']);

        // When default source is an array, it should be used
        $configuredIndexer->index();

        // InMemoryLoader ignores source, so 2 sources * 2 docs = 4 docs
        $this->assertCount(4, $store->documents);
    }

    public function testIndexPassesOptionsToInnerIndexer()
    {
        $documents = [];
        for ($i = 0; $i < 100; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'Document '.$i, new Metadata(['index' => $i]));
        }

        $vectors = [];
        for ($i = 0; $i < 100; ++$i) {
            $vectors[] = new Vector([0.1 * $i, 0.2 * $i, 0.3 * $i]);
        }

        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult(...$vectors)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $innerIndexer = new SourceIndexer($loader, $processor);
        $configuredIndexer = new ConfiguredSourceIndexer($innerIndexer, 'default-source');

        // Pass custom chunk_size option
        $configuredIndexer->index(null, ['chunk_size' => 10]);

        $this->assertCount(100, $store->documents);
        // With chunk_size 10 and 100 documents, there should be 10 add calls
        $this->assertSame(10, $store->addCalls);
    }
}
