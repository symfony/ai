<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\StreamListener;
use Symfony\AI\Platform\Result\StreamResult;

final class StreamListenerTest extends TestCase
{
    public function testSearchResultsAreAddedToMetadataAndChunkIsSkipped()
    {
        $searchResults = [['url' => 'https://example.com', 'title' => 'Example']];
        $streamResult = new StreamResult((static function () use ($searchResults): \Generator {
            yield ['search_results' => $searchResults];
            yield 'Hello World';
        })());

        $streamResult->addListener(new StreamListener());

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Hello World'], $chunks);
        $this->assertTrue($streamResult->getMetadata()->has('search_results'));
        $this->assertSame($searchResults, $streamResult->getMetadata()->get('search_results'));
    }

    public function testCitationsAreAddedToMetadataAndChunkIsSkipped()
    {
        $citations = ['https://example.com/1', 'https://example.com/2'];
        $streamResult = new StreamResult((static function () use ($citations): \Generator {
            yield ['citations' => $citations];
            yield 'Hello World';
        })());

        $streamResult->addListener(new StreamListener());

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Hello World'], $chunks);
        $this->assertTrue($streamResult->getMetadata()->has('citations'));
        $this->assertSame($citations, $streamResult->getMetadata()->get('citations'));
    }

    public function testNonArrayChunksAreNotProcessed()
    {
        $streamResult = new StreamResult((static function (): \Generator {
            yield 'Hello ';
            yield 'World';
        })());

        $streamResult->addListener(new StreamListener());

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Hello ', 'World'], $chunks);
        $this->assertFalse($streamResult->getMetadata()->has('search_results'));
        $this->assertFalse($streamResult->getMetadata()->has('citations'));
    }

    public function testBothSearchResultsAndCitationsAreProcessed()
    {
        $searchResults = [['url' => 'https://example.com']];
        $citations = ['https://example.com/1'];
        $streamResult = new StreamResult((static function () use ($searchResults, $citations): \Generator {
            yield ['search_results' => $searchResults];
            yield 'Content';
            yield ['citations' => $citations];
        })());

        $streamResult->addListener(new StreamListener());

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Content'], $chunks);
        $this->assertSame($searchResults, $streamResult->getMetadata()->get('search_results'));
        $this->assertSame($citations, $streamResult->getMetadata()->get('citations'));
    }
}
