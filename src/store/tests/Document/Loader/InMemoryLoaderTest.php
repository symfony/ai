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
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class InMemoryLoaderTest extends TestCase
{
    public function testLoadWithEmptyDocuments()
    {
        $loader = new InMemoryLoader();
        $documents = iterator_to_array($loader->load(null));

        $this->assertSame([], $documents);
    }

    public function testLoadWithSingleDocument()
    {
        $document = new TextDocument(Uuid::v4(), 'This is test content');
        $loader = new InMemoryLoader([$document]);

        $documents = iterator_to_array($loader->load(null));

        $this->assertCount(1, $documents);
        $this->assertSame($document, $documents[0]);
        $this->assertSame('This is test content', $documents[0]->getContent());
    }

    public function testLoadWithMultipleDocuments()
    {
        $document1 = new TextDocument(Uuid::v4(), 'First document');
        $document2 = new TextDocument(Uuid::v4(), 'Second document', new Metadata(['type' => 'test']));
        $loader = new InMemoryLoader([$document1, $document2]);

        $documents = iterator_to_array($loader->load(null));

        $this->assertCount(2, $documents);
        $this->assertSame($document1, $documents[0]);
        $this->assertSame($document2, $documents[1]);
        $this->assertSame('First document', $documents[0]->getContent());
        $this->assertSame('Second document', $documents[1]->getContent());
        $this->assertSame('test', $documents[1]->getMetadata()['type']);
    }

    public function testLoadIgnoresSourceParameter()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $loader = new InMemoryLoader([$document]);

        // Source parameter should be ignored - same result regardless of value
        $documentsWithNull = iterator_to_array($loader->load(null));
        $documentsWithString = iterator_to_array($loader->load('ignored-source'));

        $this->assertSame($documentsWithNull, $documentsWithString);
        $this->assertSame($document, $documentsWithNull[0]);
    }
}
