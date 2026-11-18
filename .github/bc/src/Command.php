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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Command
{
    public function __construct(
        private readonly string $root,
        private readonly ApiExtractor $extractor,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        return match ($arguments[0] ?? null) {
            'dump' => $this->dump(),
            'check' => $this->check(),
            'compare' => $this->compare($arguments[1] ?? null),
            default => $this->usage(),
        };
    }

    private function dump(): int
    {
        $packages = Package::findAll($this->root);
        $api = $this->extractor->extract($this->root, $packages);
        $renderer = new SnapshotRenderer();

        $written = 0;
        foreach ($packages as $package) {
            $path = $this->root.'/'.$package->snapshotFile();
            $snapshot = $renderer->render($api, $package);
            if (!is_file($path) || file_get_contents($path) !== $snapshot) {
                file_put_contents($path, $snapshot);
                echo 'Updated '.$package->snapshotFile()."\n";
                ++$written;
            }
        }

        echo \sprintf("%d of %d snapshots updated.\n", $written, \count($packages));

        return 0;
    }

    private function check(): int
    {
        $packages = Package::findAll($this->root);
        $api = $this->extractor->extract($this->root, $packages);
        $renderer = new SnapshotRenderer();

        $outdated = [];
        foreach ($packages as $package) {
            $file = $package->snapshotFile();
            $path = $this->root.'/'.$file;
            if (!is_file($path) || file_get_contents($path) !== $renderer->render($api, $package)) {
                $outdated[] = $file;
            }
        }

        if ([] === $outdated) {
            echo \sprintf("All %d API snapshots are up to date.\n", \count($packages));

            return 0;
        }

        $output = new Output();
        foreach ($outdated as $file) {
            $output->annotate('error', 'The API snapshot is outdated. Run "php .github/bc/bc.php dump" and commit the result, so the API change shows in the review.', $file, null, 'Outdated API snapshot');
        }

        return 1;
    }

    private function compare(?string $baseRef): int
    {
        if (null === $baseRef) {
            return $this->usage();
        }

        $baseRoot = sys_get_temp_dir().'/symfony-ai-bc-'.bin2hex(random_bytes(4));
        mkdir($baseRoot);

        try {
            $this->extractTree($baseRef, $baseRoot);

            $base = $this->extractor->extract($baseRoot, Package::findAll($baseRoot));
            $head = $this->extractor->extract($this->root, Package::findAll($this->root));
        } finally {
            exec('rm -rf '.escapeshellarg($baseRoot));
        }

        $findings = (new Comparer($base, $head, ReleasePolicy::upcomingVersion($this->root)))->compare();
        $labels = json_decode(getenv('PR_LABELS') ?: '[]', true, flags: \JSON_THROW_ON_ERROR);

        return (new ReleasePolicy($this->root, new Output()))->evaluate($findings, $baseRef, $labels);
    }

    private function extractTree(string $ref, string $target): void
    {
        $command = \sprintf('git -C %s archive %s src | tar -x -C %s', escapeshellarg($this->root), escapeshellarg($ref), escapeshellarg($target));
        exec($command, $output, $code);
        if (0 !== $code) {
            throw new \RuntimeException(\sprintf('Could not extract "%s" from git.', $ref));
        }
    }

    private function usage(): int
    {
        fwrite(\STDERR, "usage: php .github/bc/bc.php dump|check|compare <base-ref>\n");

        return 2;
    }
}
