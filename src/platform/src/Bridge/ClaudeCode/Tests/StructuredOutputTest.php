<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ClaudeCode\Factory;
use Symfony\AI\Platform\Bridge\ClaudeCode\ModelClient;
use Symfony\AI\Platform\Bridge\ClaudeCode\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Andrei Fedoseev <skybleak333@gmail.com>
 */
final class StructuredOutputTest extends TestCase
{
    #[DataProvider('streamModes')]
    public function testReturnsTheValidatedCliResultAsTheRequestedClass(bool $stream): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test uses an executable PHP fixture.');
        }

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());
        $platform = Factory::createPlatform(
            cliBinary: __DIR__.'/Fixtures/structured-output-cli.php',
            eventDispatcher: $dispatcher,
        );

        $result = $platform->invoke('sonnet', 'Classify this report.', [
            'response_format' => Verdict::class,
            'stream' => $stream,
        ]);

        $this->assertSame('approve', $result->asObject(Verdict::class)->decision);
    }

    public function testPreservesEmptyObjectsInTheCliJson(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test uses an executable PHP fixture.');
        }

        $platform = Factory::createPlatform(cliBinary: __DIR__.'/Fixtures/structured-output-cli.php');

        $result = $platform->invoke('sonnet', 'Classify this report.', [
            'json_schema' => '{"type":"object","properties":{"decision":{"type":"string"},"details":{"type":"object"},"scores":{"type":"array"}}}',
        ]);

        $this->assertSame('{"decision":"approve","details":{},"scores":[]}', $result->asText());
    }

    public function testRejectsAFormatWithoutAJsonSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Claude Code expects a JSON Schema');

        (new ModelClient(\PHP_BINARY))->buildCommand('Classify this report.', [
            'response_format' => ['type' => 'json_object'],
        ]);
    }

    #[DataProvider('streamModes')]
    public function testRefusesAResultWithoutValidatedOutput(bool $stream): void
    {
        $event = ['type' => 'result', 'subtype' => 'success', 'result' => '{"decision":"approve"}'];
        $result = new InMemoryRawResult($event, [$event]);
        $converter = new ResultConverter();
        $options = ['stream' => $stream, 'json_schema' => '{"type":"object"}'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI result does not contain a "structured_output" field.');

        $converted = $converter->convert($result, $options);
        if ($converted instanceof StreamResult) {
            iterator_to_array($converted->getContent());
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function streamModes(): iterable
    {
        yield 'ordinary result' => [false];
        yield 'streamed result' => [true];
    }
}

final class Verdict
{
    public function __construct(public readonly string $decision)
    {
    }
}
