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
 * Decides which findings fail the build, based on the version the changes are released in.
 *
 * Before 1.0 and towards a new major version, breaks are allowed but need the "BC Break" label
 * (which in turn needs an UPGRADE.md entry). In a minor version of 1.x and later, they fail.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReleasePolicy
{
    public const BC_BREAK_LABEL = 'BC Break';

    private const TITLES = [
        Finding::BREAK => 'BC break',
        Finding::MAJOR_ONLY => 'Allowed in a major version only',
        Finding::DEPRECATION => 'Upgrade path',
        Finding::VIOLATION => 'Incorrect upgrade path',
        Finding::NOTICE => 'API change',
    ];

    public function __construct(
        private readonly string $root,
        private readonly Output $output,
    ) {
    }

    /**
     * The version the changes on the current branch will be released in, as "major.minor".
     */
    public static function upcomingVersion(string $root): string
    {
        [$major, $minor] = self::latestRelease($root);

        $upgrade = (string) @file_get_contents($root.'/UPGRADE.md');
        if (1 === preg_match('/^UPGRADE FROM \S+ to (\d+)\.(\d+)\s*$/m', $upgrade, $match)) {
            $target = [(int) $match[1], (int) $match[2]];
            if ($target > [$major, $minor]) {
                return $target[0].'.'.$target[1];
            }
        }

        return $major.'.'.($minor + 1);
    }

    /**
     * @param list<Finding> $findings
     * @param list<string>  $labels
     */
    public function evaluate(array $findings, string $baseRef, array $labels): int
    {
        $upcoming = self::upcomingVersion($this->root);
        [$latestMajor] = self::latestRelease($this->root);
        $major = 0 === $latestMajor || (int) $upcoming > $latestMajor;
        $labelled = \in_array(self::BC_BREAK_LABEL, $labels, true);

        $count = array_fill_keys(array_keys(self::TITLES), 0);
        foreach ($findings as $finding) {
            ++$count[$finding->type];
            $level = match ($finding->type) {
                Finding::BREAK, Finding::MAJOR_ONLY => $major || $labelled ? 'warning' : 'error',
                Finding::VIOLATION => 'error',
                default => 'notice',
            };
            if (Finding::MAJOR_ONLY === $finding->type && $major) {
                $level = 'notice';
            }

            $this->output->annotate($level, \sprintf('%s %s See %s', $finding->symbol.':', $finding->message, $finding->guide), $finding->file, $finding->line, self::TITLES[$finding->type]);
        }

        $failed = $count[Finding::VIOLATION] > 0;
        $breaks = $count[Finding::BREAK] + $count[Finding::MAJOR_ONLY];

        if ($breaks > 0 && !$labelled) {
            $failed = true;
            $this->output->annotate('error', $major
                ? \sprintf('This change breaks backward compatibility. Add the "%s" label and document the break in UPGRADE.md, or deprecate first, see %s.', self::BC_BREAK_LABEL, Guide::URL)
                : \sprintf('Version %s is a minor version, which must not break backward compatibility. Deprecate first, see %s.', $upcoming, Guide::URL), null, null, 'BC break');
        } elseif ($breaks > 0 && !$major) {
            $this->output->annotate('warning', \sprintf('Version %s is a minor version, the "%s" label accepts breaking backward compatibility as an exception.', $upcoming, self::BC_BREAK_LABEL), null, null, 'BC break accepted');
        }

        if ($count[Finding::DEPRECATION] > 0 && !$this->addsChangelogDeprecation($baseRef)) {
            $failed = true;
            $this->output->annotate('error', 'Every deprecation needs a "* Deprecate ..." entry (or "* Mark ... as @internal") in the CHANGELOG.md of its package, under the upcoming version.', null, null, 'Missing CHANGELOG.md entry');
        }

        $this->writeSummary($findings, $upcoming, $major);

        if ([] === $findings) {
            $this->output->writeln(\sprintf('No change to the public API (upcoming version %s).', $upcoming));
        }

        return $failed ? 1 : 0;
    }

    /**
     * @param list<Finding> $findings
     */
    private function writeSummary(array $findings, string $upcoming, bool $major): void
    {
        $summary = \sprintf("### Backward compatibility\n\nUpcoming version **%s** (%s).\n\n", $upcoming, $major ? 'breaks allowed with the "BC Break" label' : 'minor version, no breaks allowed');
        if ([] === $findings) {
            $this->output->summary($summary.'No change to the public API.');
            $this->output->flushSummary();

            return;
        }

        $summary .= "| Change | Symbol | Details |\n|---|---|---|\n";
        foreach ($findings as $finding) {
            $summary .= \sprintf("| %s | `%s` | %s [Guide](%s) |\n", self::TITLES[$finding->type], $finding->symbol, str_replace('|', '\|', $finding->message), $finding->guide);
        }

        $this->output->summary($summary);
        $this->output->flushSummary();
    }

    private function addsChangelogDeprecation(string $baseRef): bool
    {
        exec(\sprintf('git -C %s diff %s -- %s %s 2>/dev/null', escapeshellarg($this->root), escapeshellarg($baseRef), escapeshellarg(':(glob)src/**/CHANGELOG.md'), escapeshellarg(':(glob)src/*/CHANGELOG.md')), $lines);

        foreach ($lines as $line) {
            if (1 === preg_match('/^\+\s*\*\s+(Deprecate|Mark)\b/', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{int, int} major and minor of the latest release tag, [0, 0] without any
     */
    private static function latestRelease(string $root): array
    {
        $tags = false !== ($override = getenv('BC_LATEST_TAG')) && '' !== $override ? [$override] : [];
        if ([] === $tags) {
            exec(\sprintf('git -C %s tag --list --merged HEAD 2>/dev/null', escapeshellarg($root)), $tags);
        }

        $latest = [0, 0];
        foreach ($tags as $tag) {
            if (1 === preg_match('/^v?(\d+)\.(\d+)\.\d+$/', trim($tag), $match) && [(int) $match[1], (int) $match[2]] > $latest) {
                $latest = [(int) $match[1], (int) $match[2]];
            }
        }

        return $latest;
    }
}
