<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Command\RetrieveCommand;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RetrieveCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new RetrieveCommand(new ServiceLocator([]));

        $this->assertSame('ai:store:retrieve', $command->getName());
        $this->assertSame('Retrieve documents from a store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('retriever'));
        $this->assertTrue($definition->hasArgument('query'));
        $this->assertTrue($definition->hasOption('limit'));

        $retrieverArgument = $definition->getArgument('retriever');
        $this->assertSame('Name of the retriever to use', $retrieverArgument->getDescription());
        $this->assertTrue($retrieverArgument->isRequired());

        $queryArgument = $definition->getArgument('query');
        $this->assertSame('Search query', $queryArgument->getDescription());
        $this->assertFalse($queryArgument->isRequired());

        $limitOption = $definition->getOption('limit');
        $this->assertSame('Maximum number of results to return', $limitOption->getDescription());
        $this->assertSame('10', $limitOption->getDefault());
    }

    public function testCommandCannotRetrieveFromNonExistingRetriever()
    {
        $command = new RetrieveCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" retriever does not exist.');
        $tester->execute([
            'retriever' => 'foo',
            'query' => 'test query',
        ]);
    }

    public function testCommandCanRetrieveDocuments()
    {
        $metadata = new Metadata();
        $metadata->setText('Test document content');
        $metadata->setSource('test-source.txt');

        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            $metadata,
            0.95,
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with('test query', ['maxItems' => 10])
            ->willReturn([$document]);

        $command = new RetrieveCommand(new ServiceLocator([
            'blog' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'blog',
            'query' => 'test query',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Retrieving documents using "blog" retriever', $display);
        $this->assertStringContainsString('Searching for: "test query"', $display);
        $this->assertStringContainsString('Result #1', $display);
        $this->assertStringContainsString('0.95', $display);
        $this->assertStringContainsString('test-source.txt', $display);
        $this->assertStringContainsString('Test document content', $display);
        $this->assertStringContainsString('Found 1 result(s) using "blog" retriever.', $display);
    }

    public function testCommandCanRetrieveDocumentsWithCustomLimit()
    {
        $documents = [];
        for ($i = 0; $i < 3; ++$i) {
            $metadata = new Metadata();
            $metadata->setText('Document '.$i);
            $documents[] = new VectorDocument(
                Uuid::v4(),
                new Vector([0.1, 0.2, 0.3]),
                $metadata,
                0.9 - ($i * 0.1),
            );
        }

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with('my query', ['maxItems' => 5])
            ->willReturn($documents);

        $command = new RetrieveCommand(new ServiceLocator([
            'products' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'products',
            'query' => 'my query',
            '--limit' => '5',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Result #1', $display);
        $this->assertStringContainsString('Result #2', $display);
        $this->assertStringContainsString('Result #3', $display);
        $this->assertStringContainsString('Found 3 result(s) using "products" retriever.', $display);
    }

    public function testCommandShowsWarningWhenNoResultsFound()
    {
        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with('unknown query', ['maxItems' => 10])
            ->willReturn([]);

        $command = new RetrieveCommand(new ServiceLocator([
            'articles' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'articles',
            'query' => 'unknown query',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('No results found.', $display);
    }

    public function testCommandThrowsExceptionOnRetrieverError()
    {
        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->willThrowException(new RuntimeException('Connection failed'));

        $command = new RetrieveCommand(new ServiceLocator([
            'docs' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while retrieving with "docs": Connection failed');
        $tester->execute([
            'retriever' => 'docs',
            'query' => 'test',
        ]);
    }

    public function testCommandTruncatesLongText()
    {
        $longText = str_repeat('a', 300);
        $metadata = new Metadata();
        $metadata->setText($longText);

        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            $metadata,
            0.8,
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->willReturn([$document]);

        $command = new RetrieveCommand(new ServiceLocator([
            'test' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'test',
            'query' => 'search',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString(str_repeat('a', 200).'...', $display);
        $this->assertStringNotContainsString(str_repeat('a', 201), $display);
    }

    public function testCommandHandlesDocumentWithoutSourceOrText()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(),
            0.75,
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->willReturn([$document]);

        $command = new RetrieveCommand(new ServiceLocator([
            'minimal' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'minimal',
            'query' => 'test',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Result #1', $display);
        $this->assertStringContainsString('0.75', $display);
        $this->assertStringContainsString('Found 1 result(s)', $display);
    }

    public function testCommandHandlesDocumentWithoutScore()
    {
        $metadata = new Metadata();
        $metadata->setText('Some content');

        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            $metadata,
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->willReturn([$document]);

        $command = new RetrieveCommand(new ServiceLocator([
            'noscore' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'noscore',
            'query' => 'test',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('n/a', $display);
    }

    public function testCommandRespectsLimit()
    {
        $documents = [];
        for ($i = 0; $i < 10; ++$i) {
            $metadata = new Metadata();
            $metadata->setText('Document '.$i);
            $documents[] = new VectorDocument(
                Uuid::v4(),
                new Vector([0.1, 0.2, 0.3]),
                $metadata,
            );
        }

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with('test', ['maxItems' => 3])
            ->willReturn($documents);

        $command = new RetrieveCommand(new ServiceLocator([
            'many' => static fn (): RetrieverInterface => $retriever,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'retriever' => 'many',
            'query' => 'test',
            '--limit' => '3',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Result #1', $display);
        $this->assertStringContainsString('Result #2', $display);
        $this->assertStringContainsString('Result #3', $display);
        $this->assertStringNotContainsString('Result #4', $display);
        $this->assertStringContainsString('Found 3 result(s)', $display);
    }
}
