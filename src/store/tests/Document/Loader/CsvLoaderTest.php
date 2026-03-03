<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Loader\CsvLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;

/**
 * @author Ramy Hakam <ramyhakam1@gmail.com>
 */
final class CsvLoaderTest extends TestCase
{
    public function testLoadWithNullSource()
    {
        $loader = new CsvLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV loader requires a file path as source.');

        iterator_to_array($loader->load(null));
    }

    public function testLoadWithInvalidSource()
    {
        $loader = new CsvLoader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File "/invalid/source.csv" does not exist.');

        iterator_to_array($loader->load('/invalid/source.csv'));
    }

    public function testLoadWithValidSource()
    {
        $loader = new CsvLoader();

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        // 8 rows total, but row 6 has empty content and should be skipped
        $this->assertCount(7, $documents);
        $this->assertContainsOnlyInstancesOf(TextDocument::class, $documents);
        $this->assertSame('This is the first document content', $documents[0]->getContent());
        $this->assertSame('Second document with different text', $documents[1]->getContent());
        $this->assertSame('Third document for testing purposes', $documents[2]->getContent());
    }

    public function testSourceIsPresentInMetadata()
    {
        $loader = new CsvLoader();

        $source = $this->getFixturePath();
        $documents = iterator_to_array($loader->load($source));

        $this->assertCount(7, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame($source, $document->getMetadata()['_source']);
        $this->assertSame($source, $document->getMetadata()->getSource());
    }

    public function testLoadWithIdColumn()
    {
        $loader = new CsvLoader(idColumn: 'id');

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $this->assertCount(7, $documents);
        $this->assertSame('1', (string) $documents[0]->getId());
        $this->assertSame('2', (string) $documents[1]->getId());
        $this->assertSame('5', (string) $documents[4]->getId());
    }

    public function testLoadWithMetadataColumns()
    {
        $loader = new CsvLoader(metadataColumns: ['author', 'category']);

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $this->assertCount(7, $documents);
        $this->assertSame('John Doe', $documents[0]->getMetadata()['author']);
        $this->assertSame('Technology', $documents[0]->getMetadata()['category']);
        $this->assertSame('Jane Smith', $documents[1]->getMetadata()['author']);
        $this->assertSame('Science', $documents[1]->getMetadata()['category']);
    }

    public function testLoadWithOptionsOverride()
    {
        $loader = new CsvLoader();

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath(), [
                CsvLoader::OPTION_ID_COLUMN => 'id',
                CsvLoader::OPTION_METADATA_COLUMNS => ['author'],
            ])
        );

        $this->assertCount(7, $documents);
        $this->assertSame('1', (string) $documents[0]->getId());
        $this->assertSame('John Doe', $documents[0]->getMetadata()['author']);
    }

    public function testEmptyContentRowsAreSkipped()
    {
        $loader = new CsvLoader(idColumn: 'id');

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $ids = array_map(static fn ($doc) => (string) $doc->getId(), $documents);

        $this->assertContains('5', $ids);
        $this->assertNotContains('6', $ids);
        $this->assertContains('7', $ids);
    }

    public function testContentWithCommasIsHandledCorrectly()
    {
        $loader = new CsvLoader(idColumn: 'id');

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $doc4 = null;
        foreach ($documents as $doc) {
            if ('4' === (string) $doc->getId()) {
                $doc4 = $doc;
                break;
            }
        }

        $this->assertNotNull($doc4);
        $this->assertSame('Content with, comma inside', $doc4->getContent());
    }

    public function testContentWithQuotesIsHandledCorrectly()
    {
        $loader = new CsvLoader(idColumn: 'id');

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $doc8 = null;
        foreach ($documents as $doc) {
            if ('8' === (string) $doc->getId()) {
                $doc8 = $doc;
                break;
            }
        }

        $this->assertNotNull($doc8);
        $this->assertSame('Multi-word content with "quotes" inside', $doc8->getContent());
    }

    public function testRowIndexInMetadata()
    {
        $loader = new CsvLoader(idColumn: 'id');

        $documents = iterator_to_array(
            $loader->load($this->getFixturePath())
        );

        $this->assertSame(0, $documents[0]->getMetadata()['_row_index']);
        $this->assertSame(1, $documents[1]->getMetadata()['_row_index']);
    }

    private function getFixturePath(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/test-data.csv';
    }
}
