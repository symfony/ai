<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\SummaryGeneratorTransformer;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;

final class SummaryGeneratorTransformerTest extends TestCase
{
    public function testSummaryIsStoredInMetadata()
    {
        $platform = PlatformTestHandler::createPlatform(new TextResult('This is a concise summary.'));
        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini');

        $document = new TextDocument('doc-1', 'This is the full document content that needs to be summarized.');
        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('This is a concise summary.', $result[0]->getMetadata()->getSummary());
    }

    public function testOriginalDocumentIsYielded()
    {
        $platform = PlatformTestHandler::createPlatform(new TextResult('Summary text.'));
        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini');

        $document = new TextDocument('doc-1', 'Document content.');
        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame('Document content.', $result[0]->getContent());
    }

    public function testDualDocModeYieldsAdditionalSummaryDocument()
    {
        $platform = PlatformTestHandler::createPlatform(new TextResult('A short summary.'));
        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini', yieldSummaryDocuments: true);

        $document = new TextDocument('doc-1', 'Full content here.');
        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $result);

        // First: original document with summary in metadata
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame('A short summary.', $result[0]->getMetadata()->getSummary());

        // Second: summary document with summary as content
        $this->assertSame('A short summary.', $result[1]->getContent());
    }

    public function testSummaryDocumentHasSameMetadata()
    {
        $platform = PlatformTestHandler::createPlatform(new TextResult('Summary.'));
        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini', yieldSummaryDocuments: true);

        $metadata = new Metadata([Metadata::KEY_SOURCE => 'test.rst']);
        $document = new TextDocument('doc-1', 'Content.', $metadata);
        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $result);
        $this->assertSame('test.rst', $result[1]->getMetadata()->getSource());
    }

    public function testLlmIsCalledOncePerDocument()
    {
        $invocations = 0;
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')->willReturnCallback(
            static function () use (&$invocations): \Symfony\AI\Platform\Result\DeferredResult {
                ++$invocations;

                return PlatformTestHandler::createPlatform(new TextResult('Summary.'))->invoke('test', 'test');
            }
        );

        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini');

        $documents = [
            new TextDocument('doc-1', 'First document.'),
            new TextDocument('doc-2', 'Second document.'),
        ];

        iterator_to_array($transformer->transform($documents));

        $this->assertSame(2, $invocations);
    }

    public function testMultipleDocumentsAreTransformed()
    {
        $platform = PlatformTestHandler::createPlatform(new TextResult('Summary text.'));
        $transformer = new SummaryGeneratorTransformer($platform, 'gpt-4o-mini');

        $documents = [
            new TextDocument('doc-1', 'First document content.'),
            new TextDocument('doc-2', 'Second document content.'),
        ];

        $result = iterator_to_array($transformer->transform($documents));

        $this->assertCount(2, $result);
        foreach ($result as $doc) {
            $this->assertTrue($doc->getMetadata()->hasSummary());
        }
    }
}
