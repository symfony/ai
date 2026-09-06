<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command\Trait;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders a decoded tool result as human-readable text, shared by `tools:call` and
 * `tools:call-batch` so their `--format=pretty` output stays consistent.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait RendersToolResultTrait
{
    /**
     * Size (in bytes of compact JSON) above which `--format=pretty` rendering is skipped in
     * favor of automatic JSON output, since a large nested value renders as an unreadable
     * wall of wrapped text otherwise. A real case that triggered this measured 35 KB.
     */
    private const PRETTY_RENDER_SIZE_THRESHOLD = 8192;

    private function isTooLargeToRenderPretty(mixed $result): bool
    {
        return \strlen((string) json_encode($result, \JSON_UNESCAPED_SLASHES)) > self::PRETTY_RENDER_SIZE_THRESHOLD;
    }

    private function renderJsonFallback(mixed $result, SymfonyStyle $io, OutputInterface $output): void
    {
        $io->note('Pretty rendering was skipped because the result is too large to display readably. Use "--format=pretty" to force it anyway, or "--format=toon" for a token-efficient alternative.');
        $output->writeln(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * `SymfonyStyle::definitionList()` pads every value to the width of the widest one in
     * the list, so a single long value bloats every other row with whitespace.
     *
     * @param array<string, mixed> $result
     */
    private function renderPrettyList(array $result, SymfonyStyle $io): void
    {
        foreach ($result as $key => $value) {
            $io->text(\sprintf('<info>%s</info>: %s', $key, $this->formatValue($value)));
        }
    }

    private function renderPretty(mixed $result, SymfonyStyle $io): void
    {
        if (\is_array($result)) {
            if (array_is_list($result)) {
                foreach ($result as $item) {
                    $io->text($this->formatValue($item));
                }
            } else {
                $this->renderPrettyList($result, $io);
            }
        } elseif (\is_string($result)) {
            $io->text($result);
        } elseif (\is_bool($result)) {
            $io->text($result ? 'true' : 'false');
        } elseif (null === $result) {
            $io->text('<comment>null</comment>');
        } else {
            $io->text((string) $result);
        }
    }

    private function formatValue(mixed $value): string
    {
        if (\is_array($value)) {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        return (string) $value;
    }
}
