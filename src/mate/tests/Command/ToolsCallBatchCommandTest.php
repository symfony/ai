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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Command\ToolsCallBatchCommand;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Invocation\HandlerInvoker;
use Symfony\AI\Mate\Invocation\ResourceReader;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\AI\Mate\Tests\Command\BatchFixtures\ChainTool;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleResources;
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

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
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
        $this->assertStringContainsString('exactly one of "tool" or "resource"', $tester->getDisplay());
    }

    public function testUnknownToolNameIsReportedAsAPartialFailure()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['tool' => 'does-not-exist']]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);
        $this->assertFalse($results[0]['ok']);
        $this->assertStringContainsString('not found', $results[0]['error']);
    }

    public function testUnknownCallKeyIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '[{"tool": "sample-add", "param": {"a": 1}}]']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('unknown key "param"', $tester->getDisplay());
    }

    public function testBatchMixesToolCallsAndResourceReads()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([
                ['tool' => 'sample-add', 'params' => ['a' => 2, 'b' => 3]],
                ['resource' => 'sample://payload'],
                ['resource' => 'sample://echo/hi'],
            ]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);

        $this->assertCount(3, $results);
        $this->assertSame(5, $results[0]['result']['sum']);
        $this->assertSame('sample://payload', $results[1]['resource']);
        $this->assertArrayNotHasKey('tool', $results[1]);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(42, $results[1]['result']['answer']);
        $this->assertTrue($results[2]['ok']);
        $this->assertSame('hi', $results[2]['result']['echo']);
    }

    public function testUnknownResourceFailsOnlyItsOwnCall()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            '--json' => json_encode([['resource' => 'unknown://nope'], ['resource' => 'sample://greeting']]),
            '--format' => 'json',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $results = json_decode($tester->getDisplay(), true);

        $this->assertFalse($results[0]['ok']);
        $this->assertSame('Resource "unknown://nope" not found', $results[0]['error']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('Hello from the Mate test fixture!', $results[1]['result']);
    }

    public function testACallMustNameExactlyOneOfToolAndResource()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '[{"tool": "sample-add", "resource": "sample://greeting"}]']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('exactly one of "tool" or "resource"', $tester->getDisplay());

        $tester->execute(['--json' => '[{"params": {"a": 1}}]']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('exactly one of "tool" or "resource"', $tester->getDisplay());
    }

    public function testAResourceReadRejectsParams()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => '[{"resource": "sample://greeting", "params": {"a": 1}}]']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('is a resource read', $tester->getDisplay());
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

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
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

    public function testThreeStepChainUsesTokenFromTheListAndHidesTheFirstStep()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'params' => ['count' => 2], 'output' => false],
            ['id' => 'one', 'tool' => 'chain-get', 'params' => ['token' => '{{list.profiles[0].token}}']],
            ['tool' => 'chain-search', 'params' => ['limit' => '{{list.total}}', 'query' => 'for {{one.token}}']],
        ], $status);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertCount(2, $results);
        $this->assertSame('one', $results[0]['id']);
        $this->assertSame('tok1', $results[0]['result']['untrusted_data']['token']);
        $this->assertArrayNotHasKey('id', $results[1]);
        $this->assertSame(2, $results[1]['result']['limit']);
        $this->assertSame('int', $results[1]['result']['limit_type']);
        $this->assertSame('for tok1', $results[1]['result']['query']);
    }

    public function testWholeExpressionKeepsArraysInNestedParams()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'output' => false],
            ['tool' => 'chain-items', 'params' => ['items' => '{{list.profiles[*].token}}']],
        ]);

        $this->assertSame(['tok1', 'tok2'], $results[0]['result']['items']);
    }

    public function testEscapeKeepsLiteralBraces()
    {
        $results = $this->runJson([['tool' => 'sample-echo', 'params' => ['text' => 'a \\{{b.c}}']]]);

        $this->assertSame('a {{b.c}}', $results[0]['result']);
    }

    public function testResolvedReferenceInResourceUri()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'output' => false],
            ['resource' => 'chain://profile/{{list.profiles[1].token}}'],
        ]);

        $this->assertSame('chain://profile/tok2', $results[0]['resource']);
        $this->assertSame('tok2', $results[0]['result']['untrusted_data']['token']);
    }

    public function testHiddenFailedCallIsStillShownAndFailsTheExitCode()
    {
        $results = $this->runJson([
            ['id' => 'boom', 'tool' => 'sample-fail', 'output' => false],
            ['tool' => 'sample-add', 'params' => ['a' => 1]],
        ], $status);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertCount(2, $results);
        $this->assertSame('boom', $results[0]['id']);
        $this->assertFalse($results[0]['ok']);
    }

    public function testPickOnToolResultKeepsNoticeAndFillsMissingPaths()
    {
        $results = $this->runJson([
            ['tool' => 'chain-list', 'pick' => ['profiles[0].token', 'total', 'profiles[*].status', 'nope.x']],
        ]);

        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $results[0]['result']['_security_notice']);
        $this->assertSame(['_security_notice', 'untrusted_data'], array_keys($results[0]['result']));
        $this->assertSame([
            'profiles[0].token' => 'tok1',
            'total' => 2,
            'profiles[*].status' => [201, 202],
            'nope.x' => null,
        ], $results[0]['result']['untrusted_data']);
        $this->assertSame(['nope.x'], $results[0]['missing_paths']);
    }

    public function testPickWithoutEnvelopeReplacesTheResult()
    {
        $results = $this->runJson([['tool' => 'sample-add', 'params' => ['a' => 2, 'b' => 3], 'pick' => ['sum']]]);

        $this->assertSame(['sum' => 5], $results[0]['result']);
        $this->assertArrayNotHasKey('missing_paths', $results[0]);
    }

    public function testPickOnResourceRead()
    {
        $results = $this->runJson([['resource' => 'chain://profile/abc', 'pick' => ['db.queries', 'db.slow']]]);

        $this->assertSame(['db.queries' => 7, 'db.slow' => null], $results[0]['result']['untrusted_data']);
        $this->assertSame(['db.slow'], $results[0]['missing_paths']);
    }

    public function testReferenceSeesTheFullResultNotThePickedOne()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'pick' => ['total']],
            ['tool' => 'chain-get', 'params' => ['token' => '{{list.profiles[1].token}}']],
        ]);

        $this->assertSame(['total' => 2], $results[0]['result']['untrusted_data']);
        $this->assertSame('tok2', $results[1]['result']['untrusted_data']['token']);
    }

    public function testEmptyListSkipsTheDependentCallButRunsTheRest()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'params' => ['count' => 0], 'output' => false],
            ['id' => 'one', 'tool' => 'chain-get', 'params' => ['token' => '{{list.profiles[0].token}}']],
            ['tool' => 'sample-add', 'params' => ['a' => 1, 'b' => 1]],
        ], $status);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertFalse($results[0]['ok']);
        $this->assertNull($results[0]['result']);
        $this->assertSame('chain-get', $results[0]['tool']);
        $this->assertStringContainsString('{{list.profiles[0].token}}', $results[0]['error']);
        $this->assertStringContainsString('"profiles" is empty', $results[0]['error']);
        $this->assertStringContainsString('empty list', $results[0]['error']);
        $this->assertTrue($results[1]['ok']);
    }

    public function testMissingFieldListsAvailableKeys()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'output' => false],
            ['tool' => 'chain-get', 'params' => ['token' => '{{list.profiles[0].nope}}']],
        ]);

        $this->assertFalse($results[0]['ok']);
        $this->assertStringContainsString('has no field "nope"', $results[0]['error']);
        $this->assertStringContainsString('Available: token, status, id', $results[0]['error']);
    }

    public function testReferenceToFailedCallNamesTheCauseAndTransitiveDependentsFail()
    {
        $results = $this->runJson([
            ['id' => 'boom', 'tool' => 'sample-fail', 'output' => false],
            ['id' => 'next', 'tool' => 'chain-get', 'params' => ['token' => '{{boom.x}}']],
            ['tool' => 'chain-get', 'params' => ['token' => '{{next.token}}']],
        ], $status);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertCount(3, $results);
        $this->assertStringContainsString('call boom failed: Sample tool failure', $results[1]['error']);
        $this->assertSame('depends on failed call next', $results[2]['error']);
        $this->assertFalse($results[2]['ok']);
    }

    public function testArrayInsideLongerStringFailsTheCall()
    {
        $results = $this->runJson([
            ['id' => 'list', 'tool' => 'chain-list', 'output' => false],
            ['tool' => 'sample-echo', 'params' => ['text' => 'x {{list.profiles}}']],
        ]);

        $this->assertFalse($results[0]['ok']);
        $this->assertStringContainsString('cannot be inserted into a longer string', $results[0]['error']);
    }

    public function testForwardReferenceIsRejectedBeforeAnythingRuns()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => json_encode([
            ['tool' => 'sample-fail'],
            ['tool' => 'chain-get', 'params' => ['token' => '{{later.token}}']],
            ['id' => 'later', 'tool' => 'chain-list'],
        ])]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('earlier call', $tester->getDisplay());
        $this->assertStringNotContainsString('Sample tool failure', $tester->getDisplay());
    }

    public function testUnknownIdIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => json_encode([
            ['id' => 'a', 'tool' => 'chain-list'],
            ['resource' => 'chain://profile/{{zzz.token}}'],
        ])]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('no call has the id "zzz"', $tester->getDisplay());
        $this->assertStringContainsString('Earlier ids: a', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $call
     */
    #[DataProvider('provideInvalidCallKeys')]
    public function testInvalidIdOutputOrPickIsRejected(array $call, string $message)
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => json_encode([$call])]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($message, $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidCallKeys(): iterable
    {
        yield 'uppercase id' => [['id' => 'Abc', 'tool' => 'sample-fail'], 'invalid "id"'];
        yield 'numeric id' => [['id' => 5, 'tool' => 'sample-fail'], 'invalid "id"'];
        yield 'non-bool output' => [['output' => 'no', 'tool' => 'sample-fail'], 'non-boolean "output"'];
        yield 'empty pick' => [['pick' => [], 'tool' => 'sample-fail'], 'invalid "pick"'];
        yield 'pick with bad path' => [['pick' => ['a[x]'], 'tool' => 'sample-fail'], 'Invalid path'];
        yield 'pick with non-string' => [['pick' => [1], 'tool' => 'sample-fail'], 'non-string entry'];
        yield 'unknown key' => [['name' => 'a', 'tool' => 'sample-fail'], 'unknown key "name"'];
    }

    public function testDuplicateIdIsRejected()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => json_encode([
            ['id' => 'a', 'tool' => 'chain-list'],
            ['id' => 'a', 'tool' => 'chain-list'],
        ])]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('reuses the id "a"', $tester->getDisplay());
    }

    public function testPrettyFormatHidesHiddenCallsAndCountsAllOfThem()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--json' => json_encode([
            ['id' => 'list', 'tool' => 'chain-list', 'output' => false],
            ['tool' => 'chain-get', 'params' => ['token' => '{{list.profiles[0].token}}'], 'pick' => ['nope']],
        ])]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('chain-list', $tester->getDisplay());
        $this->assertStringContainsString('Paths without a value: nope', $tester->getDisplay());
        $this->assertStringContainsString('2 of 2 calls succeeded.', $tester->getDisplay());
    }

    /**
     * @param list<array<string, mixed>> $calls
     *
     * @param-out int                    $status
     *
     * @return list<array<string, mixed>>
     */
    private function runJson(array $calls, ?int &$status = null): array
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--json' => json_encode($calls), '--format' => 'json']);
        $status = $tester->getStatusCode();

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded, $tester->getDisplay());

        return $decoded;
    }

    private function createCommand(): ToolsCallBatchCommand
    {
        $container = new ContainerBuilder();
        $container->set(SampleTool::class, new SampleTool());
        $container->set(SampleResources::class, new SampleResources());
        $container->set(ChainTool::class, new ChainTool());

        $logger = new NullLogger();
        $discoverer = new ReflectionDiscoverer($logger);
        $registry = new CapabilityRegistry(__DIR__.'/../..', ['_custom' => ['dirs' => ['tests/Command/Fixtures', 'tests/Command/BatchFixtures'], 'includes' => []]], [], $discoverer, $logger);
        $handlerInvoker = new HandlerInvoker($container);
        $invoker = new ToolInvoker($handlerInvoker);
        $reader = new ResourceReader($registry, $handlerInvoker);

        return new class($registry, $invoker, $reader) extends ToolsCallBatchCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
