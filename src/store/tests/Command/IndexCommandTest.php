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
use Symfony\AI\Store\Command\IndexCommand;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Indexer\ConfiguredSourceIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\IndexerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class IndexCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new IndexCommand(new ServiceLocator([]));

        $this->assertSame('ai:store:index', $command->getName());
        $this->assertSame('Index documents into a store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('indexer'));
        $this->assertTrue($definition->hasOption('source'));

        $indexerArgument = $definition->getArgument('indexer');
        $this->assertSame('Name of the indexer to run', $indexerArgument->getDescription());
        $this->assertTrue($indexerArgument->isRequired());

        $sourceOption = $definition->getOption('source');
        $this->assertSame('Source(s) to index (overrides configured source)', $sourceOption->getDescription());
        $this->assertTrue($sourceOption->acceptValue());
        $this->assertTrue($sourceOption->isArray());
    }

    public function testCommandFailsForUnknownIndexer()
    {
        $command = new IndexCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" indexer does not exist.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'indexer' => 'foo',
        ]);
    }

    public function testCommandFailsForNonSourceIndexer()
    {
        $command = new IndexCommand(new ServiceLocator([
            'foo' => fn (): object => $this->createMock(IndexerInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" indexer is not a SourceIndexer.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'indexer' => 'foo',
        ]);
    }

    public function testCommandIndexesWithoutSource()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())->method('load')->willReturn([]);

        $indexer = $this->createSourceIndexer($loader);

        $command = new IndexCommand(new ServiceLocator([
            'blog' => static fn (): SourceIndexer => $indexer,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'indexer' => 'blog',
        ]);

        $this->assertStringContainsString('Documents indexed successfully using "blog" indexer.', $tester->getDisplay());
    }

    public function testCommandIndexesWithSingleSource()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())->method('load')->with('/path/to/file.txt')->willReturn([]);

        $indexer = $this->createSourceIndexer($loader);

        $command = new IndexCommand(new ServiceLocator([
            'blog' => static fn (): SourceIndexer => $indexer,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'indexer' => 'blog',
            '--source' => ['/path/to/file.txt'],
        ]);

        $this->assertStringContainsString('Documents indexed successfully using "blog" indexer.', $tester->getDisplay());
    }

    public function testCommandIndexesWithMultipleSources()
    {
        $received = [];

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static function (?string $source) use (&$received): array {
                $received[] = $source;

                return [];
            });

        $indexer = $this->createSourceIndexer($loader);

        $command = new IndexCommand(new ServiceLocator([
            'blog' => static fn (): SourceIndexer => $indexer,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'indexer' => 'blog',
            '--source' => ['first.txt', 'second.txt'],
        ]);

        $this->assertSame(['first.txt', 'second.txt'], $received);
        $this->assertStringContainsString('Documents indexed successfully using "blog" indexer.', $tester->getDisplay());
    }

    public function testCommandWrapsIndexerException()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())->method('load')->willThrowException(new RuntimeException('boom'));

        $indexer = $this->createSourceIndexer($loader);

        $command = new IndexCommand(new ServiceLocator([
            'blog' => static fn (): SourceIndexer => $indexer,
        ]));

        $tester = new CommandTester($command);

        try {
            $tester->execute([
                'indexer' => 'blog',
            ]);
            $this->fail(\sprintf('Expected "%s" to be thrown.', RuntimeException::class));
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('An error occurred while indexing with "blog": boom', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
            $this->assertSame('boom', $e->getPrevious()->getMessage());
        }
    }

    public function testCommandSupportsConfiguredSourceIndexer()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())->method('load')->with('configured-source')->willReturn([]);

        $indexer = new ConfiguredSourceIndexer($this->createSourceIndexer($loader), 'configured-source');

        $command = new IndexCommand(new ServiceLocator([
            'blog' => static fn (): ConfiguredSourceIndexer => $indexer,
        ]));

        $tester = new CommandTester($command);
        $tester->execute([
            'indexer' => 'blog',
        ]);

        $this->assertStringContainsString('Documents indexed successfully using "blog" indexer.', $tester->getDisplay());
    }

    public function testCommandCompletesIndexerNames()
    {
        $command = new IndexCommand(new ServiceLocator([
            'blog' => fn (): object => $this->createMock(IndexerInterface::class),
            'products' => fn (): object => $this->createMock(IndexerInterface::class),
        ]));

        $input = CompletionInput::fromString('ai:store:index ', 1);
        $input->bind($command->getDefinition());

        $suggestions = new CompletionSuggestions();
        $command->complete($input, $suggestions);

        $values = array_map(static fn (Suggestion $suggestion): string => $suggestion->getValue(), $suggestions->getValueSuggestions());

        $this->assertContains('blog', $values);
        $this->assertContains('products', $values);
    }

    private function createSourceIndexer(LoaderInterface $loader): SourceIndexer
    {
        $processor = new DocumentProcessor(
            $this->createMock(VectorizerInterface::class),
            $this->createMock(StoreInterface::class),
        );

        return new SourceIndexer($loader, $processor);
    }
}
