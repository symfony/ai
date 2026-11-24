<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Factory;
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;

/**
 * @author Andrei Fedoseev <skybleak333@gmail.com>
 */
final class StructuredOutputTest extends TestCase
{
    #[DataProvider('streamModes')]
    public function testConvertsTheFinalCliMessageIntoTheRequestedClass(bool $stream)
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

        $result = $platform->invoke('gpt-5.4-mini', 'Name a city in Spain.', [
            'response_format' => City::class,
            'stream' => $stream,
        ]);

        $this->assertSame('Valencia', $result->asObject(City::class)->name);
    }

    #[DataProvider('streamModes')]
    public function testTemporarySchemaIsRemovedAfterAFailedProcess(bool $stream)
    {
        $schemaPath = tempnam(sys_get_temp_dir(), 'codex-test-');
        $this->assertIsString($schemaPath);

        $process = new Process([\PHP_BINARY, '-r', 'exit(1);']);
        $process->start();
        $result = new RawProcessResult($process, $schemaPath);

        try {
            $this->expectException(RuntimeException::class);

            if ($stream) {
                iterator_to_array($result->getDataStream());
            } else {
                $result->getData();
            }
        } finally {
            $this->assertFileDoesNotExist($schemaPath);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function streamModes(): iterable
    {
        yield 'ordinary result' => [false];
        yield 'streamed result' => [true];
    }
}

final class City
{
    public function __construct(public readonly string $name)
    {
    }
}
