<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Command\ToolsCallBatchCommand;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Invocation\HandlerInvoker;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsCallBatchCommandTest extends TestCase
{
    public function testBatchOfTwoToolsSucceedsAndAttributesEachResult()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-add', 'params' => ['a' => 2, 'b' => 3]],
                ['tool' => 'sample-echo', 'params' => ['text' => 'hello']],
            ]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);

        $this->assertCount(2, $results);
        $this->assertSame('sample-add', $results[0]['tool']);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame(5, $results[0]['result']['sum']);
        $this->assertNull($results[0]['error']);

        $this->assertSame('sample-echo', $results[1]['tool']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('hello', $results[1]['result']);
    }

    public function testFailingCallDoesNotAbortTheOthers()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-fail'],
                ['tool' => 'sample-add', 'params' => ['a' => 1, 'b' => 1]],
            ]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);

        $this->assertFalse($results[0]['ok']);
        $this->assertSame('sample-fail', $results[0]['tool']);
        $this->assertNull($results[0]['result']);
        $this->assertStringContainsString('Sample tool failure', $results[0]['error']);

        $this->assertTrue($results[1]['ok']);
        $this->assertSame(2, $results[1]['result']['sum']);
    }

    public function testEmptyBatchIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '[]']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('at least one call', $tester->getDisplay());
    }

    public function testMissingJsonOptionIsReported()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not enough arguments', $tester->getDisplay());
    }

    public function testInvalidJsonIsReported()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '{not json}']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid JSON', $tester->getDisplay());
    }

    public function testNonArrayJsonIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '{"tool": "sample-add"}']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('must be a JSON array', $tester->getDisplay());
    }

    public function testCallMissingToolNameIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '[{"params": {"a": 1}}]']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('missing a "tool" name', $tester->getDisplay());
    }

    public function testUnknownToolNameIsReportedAsAPartialFailure()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['tool' => 'does-not-exist']]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);
        $this->assertFalse($results[0]['ok']);
        $this->assertStringContainsString('not found', $results[0]['error']);
    }

    public function testMutatingLookingToolIsRejectedFromTheBatch()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['tool' => 'rector-apply']]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);
        $this->assertFalse($results[0]['ok']);
        $this->assertStringContainsString('looks mutating', $results[0]['error']);
    }

    public function testJsonFormatIsRejectedForUnsupportedFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['tool' => 'sample-add', 'params' => ['a' => 1]]]),
            '--format' => 'xml',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown output format', $tester->getDisplay());
    }

    public function testToonFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['tool' => 'sample-add', 'params' => ['a' => 1, 'b' => 2]]]),
            '--format' => 'toon',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = Toon::decode($tester->getDisplay());
        $this->assertTrue($results[0]['ok']);
        $this->assertSame(3, $results[0]['result']['sum']);
    }

    public function testPrettyFormatRendersEachToolAsASection()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-echo', 'params' => ['text' => 'hi']],
                ['tool' => 'sample-fail'],
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('sample-echo', $output);
        $this->assertStringContainsString('hi', $output);
        $this->assertStringContainsString('sample-fail', $output);
        $this->assertStringContainsString('Sample tool failure', $output);
        $this->assertStringContainsString('1 of 2 calls succeeded.', $output);
    }

    public function testPrettyFormatRendersAnObjectResultAsUnpaddedKeyValueLines()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-tags', 'params' => ['sku' => 'A-1', 'tags' => ['x', 'y']]],
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/^ sku: A-1$/m', $output);
        $this->assertMatchesRegularExpression('/^ tags: \["x","y"\]$/m', $output);
    }

    public function testPrettyFormatFallsBackToJsonForAnOversizedResult()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-blob', 'params' => ['size' => 9000]],
                ['tool' => 'sample-echo', 'params' => ['text' => 'hi']],
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertSame(1, substr_count($output, 'Pretty rendering was skipped'));
        $this->assertStringContainsString('"blob": "'.str_repeat('x', 9000).'"', $output);
        $this->assertStringContainsString('2 of 2 calls succeeded.', $output);
    }

    private function createCommand(): ToolsCallBatchCommand
    {
        $container = new ContainerBuilder();
        $container->set(SampleTool::class, new SampleTool());

        $logger = new NullLogger();
        $discoverer = new ReflectionDiscoverer($logger);
        $registry = new CapabilityRegistry(__DIR__.'/../..', ['_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []]], [], $discoverer, $logger);
        $invoker = new ToolInvoker(new HandlerInvoker($container));

        return new class($registry, $invoker) extends ToolsCallBatchCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
