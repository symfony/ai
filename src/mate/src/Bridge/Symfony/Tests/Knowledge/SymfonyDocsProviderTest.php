<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Knowledge;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Service\GitFetcher;
use Symfony\AI\Mate\Bridge\Symfony\Knowledge\SymfonyDocsProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SymfonyDocsProviderTest extends TestCase
{
    public function testExplicitBranchOverridesAutoDetection()
    {
        $provider = new SymfonyDocsProvider(new GitFetcher(), 'https://example.invalid/docs.git', '6.4');

        $this->assertStringContainsString('branch 6.4', $provider->getDescription());
    }

    public function testNullBranchAutoDetectsFromInstalledSymfony()
    {
        $version = $this->resolveInstalledMajorMinor();
        if (null === $version) {
            $this->markTestSkipped('No Symfony package is reported as installed via Composer\\InstalledVersions; cannot verify auto-detection.');
        }

        $provider = new SymfonyDocsProvider(new GitFetcher());

        $this->assertStringContainsString('branch '.$version, $provider->getDescription());
    }

    public function testNameAndFormatAreStable()
    {
        $provider = new SymfonyDocsProvider(new GitFetcher(), 'https://example.invalid/docs.git', '7.3');

        $this->assertSame('symfony', $provider->getName());
        $this->assertSame('Symfony Documentation', $provider->getTitle());
        $this->assertSame('rst', $provider->getFormat());
    }

    private function resolveInstalledMajorMinor(): ?string
    {
        foreach (['symfony/framework-bundle', 'symfony/runtime', 'symfony/http-kernel', 'symfony/dependency-injection'] as $package) {
            if (!InstalledVersions::isInstalled($package)) {
                continue;
            }

            $version = InstalledVersions::getVersion($package);
            if (null !== $version && 1 === preg_match('/^(\d+\.\d+)/', $version, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
