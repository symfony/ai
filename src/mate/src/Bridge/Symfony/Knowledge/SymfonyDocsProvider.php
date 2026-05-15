<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Knowledge;

use Composer\InstalledVersions;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\DocsProviderInterface;
use Symfony\AI\Mate\Bridge\Knowledge\Service\GitFetcher;

/**
 * Exposes the official Symfony documentation (https://github.com/symfony/symfony-docs)
 * as a knowledge provider.
 *
 * Only registered when the Knowledge bridge is installed (see config.php).
 *
 * The cloned docs branch defaults to the major.minor version of the host
 * application's installed Symfony (read from `vendor/composer/installed.json`
 * via `Composer\InstalledVersions`). When the version cannot be resolved (e.g.
 * the bridge runs outside a Composer-installed Symfony app), the
 * {@see DEFAULT_BRANCH} constant is used as a last resort.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SymfonyDocsProvider implements DocsProviderInterface
{
    /**
     * Fallback when nothing else can be detected. Updated alongside the
     * symfony-docs release schedule.
     */
    public const DEFAULT_BRANCH = '7.3';

    /**
     * Packages probed (in order) for the host Symfony version. The first one
     * Composer reports as installed wins — `framework-bundle` is the canonical
     * "what Symfony version do we have" marker for an application, the others
     * are progressively weaker fallbacks.
     */
    private const VERSION_PROBE_PACKAGES = [
        'symfony/framework-bundle',
        'symfony/runtime',
        'symfony/http-kernel',
        'symfony/dependency-injection',
    ];

    private string $branch;

    public function __construct(
        private GitFetcher $fetcher,
        private string $repositoryUrl = 'https://github.com/symfony/symfony-docs.git',
        ?string $branch = null,
    ) {
        $this->branch = $branch ?? self::detectBranch();
    }

    public function getName(): string
    {
        return 'symfony';
    }

    public function getTitle(): string
    {
        return 'Symfony Documentation';
    }

    public function getDescription(): string
    {
        return 'Official Symfony framework documentation, branch '.$this->branch.'.';
    }

    public function getFormat(): string
    {
        return 'rst';
    }

    public function sync(string $cacheDir): string
    {
        $repoDir = rtrim($cacheDir, '/').'/docs';
        $this->fetcher->fetch($this->repositoryUrl, $this->branch, $repoDir);

        return $repoDir.'/index.rst';
    }

    /**
     * Looks up the installed Symfony version via Composer's runtime API and
     * extracts the `major.minor` component — that's the branch naming
     * convention used by symfony-docs (e.g. `7.3`, `6.4`).
     */
    private static function detectBranch(): string
    {
        if (!class_exists(InstalledVersions::class)) {
            return self::DEFAULT_BRANCH;
        }

        foreach (self::VERSION_PROBE_PACKAGES as $package) {
            if (!InstalledVersions::isInstalled($package)) {
                continue;
            }

            $version = InstalledVersions::getVersion($package);
            if (null === $version) {
                continue;
            }

            if (1 === preg_match('/^(\d+\.\d+)/', $version, $matches)) {
                return $matches[1];
            }
        }

        return self::DEFAULT_BRANCH;
    }
}
