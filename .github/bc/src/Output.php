<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * Writes GitHub Actions annotations in CI and plain lines locally.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Output
{
    private readonly bool $github;

    /** @var list<string> */
    private array $summary = [];

    public function __construct(?bool $github = null)
    {
        $this->github = $github ?? false !== getenv('GITHUB_ACTIONS');
    }

    /**
     * @param 'error'|'warning'|'notice' $level
     */
    public function annotate(string $level, string $message, ?string $file, ?int $line, string $title): void
    {
        if (!$this->github) {
            $location = null !== $file ? $file.(null !== $line ? ':'.$line : '').' ' : '';
            echo \sprintf("[%s] %s%s: %s\n", strtoupper($level), $location, $title, $message);

            return;
        }

        $properties = ['title='.self::escapeProperty($title)];
        if (null !== $file) {
            $properties[] = 'file='.self::escapeProperty($file);
            if (null !== $line) {
                $properties[] = 'line='.$line;
            }
        }

        echo \sprintf("::%s %s::%s\n", $level, implode(',', $properties), self::escapeData($message));
    }

    public function writeln(string $line = ''): void
    {
        echo $line."\n";
    }

    public function summary(string $markdown): void
    {
        $this->summary[] = $markdown;
    }

    public function flushSummary(): void
    {
        $file = getenv('GITHUB_STEP_SUMMARY');
        if (false === $file || '' === $file || [] === $this->summary) {
            return;
        }

        file_put_contents($file, implode("\n", $this->summary)."\n", \FILE_APPEND);
    }

    private static function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    private static function escapeProperty(string $value): string
    {
        return str_replace(['%', "\r", "\n", ':', ','], ['%25', '%0D', '%0A', '%3A', '%2C'], $value);
    }
}
