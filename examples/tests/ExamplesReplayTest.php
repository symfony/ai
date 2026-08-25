<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Examples\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Runs every example that has a recorded fixture and asserts it still succeeds: deterministic HTTP => output.
 *
 * Because each example drives the real Platform pipeline
 * (ModelClient -> RawHttpResult -> ResultConverter), a converter regression
 * makes the example throw or change its output, failing this test - without any
 * API credentials. Cassettes are recorded occasionally against the real APIs
 * (`./runner --record`) and committed under tests/fixtures/.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ExamplesReplayTest extends TestCase
{
    #[DataProvider('provideRecordedExamples')]
    public function testExampleReplaysDeterministically(string $examplePath, ?string $fixture)
    {
        $process = new Process(['php', $examplePath], self::examplesDirectory(), ['CASSETTE' => 'replay']);
        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            \sprintf("Example \"%s\" failed on replay:\n%s%s", $examplePath, $process->getOutput(), $process->getErrorOutput()),
        );

        if (null === $fixture) {
            return;
        }

        $this->assertSame(
            $this->normalize((string) file_get_contents($fixture)),
            $this->normalize($process->getOutput()),
            \sprintf('Example "%s" produced output differing from its recorded fixture, consider running "./runner --record" for it.', $examplePath),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function provideRecordedExamples(): iterable
    {
        $cassettes = self::examplesDirectory().'/tests/fixtures';

        if (!is_dir($cassettes)) {
            return;
        }

        $finder = (new Finder())->files()->in($cassettes)->name('*.json')->sortByName();

        foreach ($finder as $cassette) {
            $relative = $cassette->getRelativePathname();
            $example = substr($relative, 0, -\strlen('.json')).'.php';
            $fixture = $cassette->getPath().'/'.$cassette->getFilenameWithoutExtension().'.out';

            yield $example => [$example, is_file($fixture) ? $fixture : null];
        }
    }

    private static function examplesDirectory(): string
    {
        return \dirname(__DIR__);
    }

    private function normalize(string $output): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $output));
        $output = implode("\n", array_map(static fn (string $line): string => rtrim($line, " \t"), $lines));

        return rtrim($output)."\n";
    }
}
