<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ComposerExtensionDiscoveryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/Fixtures';
    }

    public function testDiscoverPackagesWithAiMateConfig()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(2, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);

        // Check package-a structure
        $this->assertArrayHasKey('dirs', $extensions['vendor/package-a']);
        $this->assertArrayHasKey('includes', $extensions['vendor/package-a']);

        $this->assertContains('vendor/vendor/package-a/src', $extensions['vendor/package-a']['dirs']);
    }

    public function testIgnoresPackagesWithoutAiMateConfig()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/without-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testIgnoresPackagesWithoutExtraSection()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-extra-section',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testWhitelistFiltering()
    {
        $enabledExtensions = [
            'vendor/package-a',
        ];

        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover($enabledExtensions);

        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayNotHasKey('vendor/package-b', $extensions);
    }

    public function testWhitelistWithMultiplePackages()
    {
        $enabledExtensions = [
            'vendor/package-a',
            'vendor/package-b',
        ];

        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover($enabledExtensions);

        $this->assertCount(2, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);
    }

    public function testExtractsIncludeFiles()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-includes',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/package-with-includes', $extensions);

        $includes = $extensions['vendor/package-with-includes']['includes'];
        $this->assertNotEmpty($includes);
        $this->assertStringContainsString('config/config.php', $includes[0]);
    }

    public function testExtractsSkillsDir()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-skills',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertArrayHasKey('vendor/package-with-skills', $extensions);
        $this->assertArrayHasKey('skills', $extensions['vendor/package-with-skills']);
        $this->assertSame(['vendor/vendor/package-with-skills/skills'], $extensions['vendor/package-with-skills']['skills']);
    }

    public function testCoercesSingleSkillsStringToArray()
    {
        $rootDir = sys_get_temp_dir().'/mate-skills-string-'.uniqid();
        mkdir($rootDir.'/vendor/composer', 0755, true);
        mkdir($rootDir.'/vendor/acme/pkg/skills', 0755, true);
        file_put_contents($rootDir.'/vendor/composer/installed.json', '{"packages":[{"name":"acme/pkg","extra":{"ai-mate":{"skills":"skills"}}}]}');

        try {
            $discovery = new ComposerExtensionDiscovery($rootDir, new NullLogger());

            $extensions = $discovery->discover();

            $this->assertArrayHasKey('acme/pkg', $extensions);
            $this->assertSame(['vendor/acme/pkg/skills'], $extensions['acme/pkg']['skills']);
        } finally {
            (new Filesystem())->remove($rootDir);
        }
    }

    public function testIgnoresMissingSkillsDir()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-includes',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertArrayHasKey('vendor/package-with-includes', $extensions);
        $this->assertSame([], $extensions['vendor/package-with-includes']['skills']);
    }

    public function testDropsTraversingPackageSkillsDir()
    {
        $rootDir = sys_get_temp_dir().'/mate-skills-traversal-'.uniqid();
        mkdir($rootDir.'/vendor/composer', 0755, true);
        mkdir($rootDir.'/vendor/acme/pkg/skills', 0755, true);
        // Traversal target exists on disk, so only the segment-based guard — not the is_dir check — rejects it.
        mkdir($rootDir.'/vendor/acme/escape', 0755, true);
        file_put_contents($rootDir.'/vendor/composer/installed.json', '{"packages":[{"name":"acme/pkg","extra":{"ai-mate":{"skills":["../escape","skills"]}}}]}');

        try {
            $discovery = new ComposerExtensionDiscovery($rootDir, new NullLogger());

            $extensions = $discovery->discover();

            $this->assertArrayHasKey('acme/pkg', $extensions);
            $this->assertSame(['vendor/acme/pkg/skills'], $extensions['acme/pkg']['skills']);
        } finally {
            (new Filesystem())->remove($rootDir);
        }
    }

    public function testStripsAbsolutePackageSkillsDir()
    {
        $rootDir = sys_get_temp_dir().'/mate-skills-absolute-'.uniqid();
        mkdir($rootDir.'/vendor/composer', 0755, true);
        mkdir($rootDir.'/vendor/acme/pkg/abs', 0755, true);
        file_put_contents($rootDir.'/vendor/composer/installed.json', '{"packages":[{"name":"acme/pkg","extra":{"ai-mate":{"skills":["/abs"]}}}]}');

        try {
            $discovery = new ComposerExtensionDiscovery($rootDir, new NullLogger());

            $extensions = $discovery->discover();

            // A leading slash is neutralized and the path stays contained under the package's vendor dir.
            $this->assertArrayHasKey('acme/pkg', $extensions);
            $this->assertSame(['vendor/acme/pkg/abs'], $extensions['acme/pkg']['skills']);
        } finally {
            (new Filesystem())->remove($rootDir);
        }
    }

    public function testHandlesMissingInstalledJson()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-installed-json',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testHandlesPackagesWithoutType()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/mixed-types',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        // Should discover packages with ai-mate config regardless of type field
        $this->assertGreaterThanOrEqual(1, \count($extensions));
        $this->assertArrayHasKey('vendor/package-mixed', $extensions);
    }

    public function testDiscoverRootProjectReturnsEmptyWhenComposerJsonDoesNotExist()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-composer-json',
            new NullLogger()
        );

        $result = $discovery->discoverRootProject();

        $this->assertArrayHasKey('dirs', $result);
        $this->assertArrayHasKey('includes', $result);
        $this->assertArrayHasKey('skills', $result);
        $this->assertSame([], $result['dirs']);
        $this->assertSame([], $result['includes']);
        $this->assertSame([], $result['skills']);
    }

    public function testDiscoverRootProjectWithAiMateConfig()
    {
        // Create a temporary directory with a composer.json that has ai-mate config
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'ai-mate' => [
                    'scan-dirs' => ['src', 'lib'],
                    'includes' => ['config/mate.php'],
                ],
            ],
        ];

        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertArrayHasKey('dirs', $result);
            $this->assertArrayHasKey('includes', $result);
            $this->assertArrayHasKey('skills', $result);
            $this->assertSame(['src', 'lib'], $result['dirs']);
            $this->assertSame(['config/mate.php'], $result['includes']);
            $this->assertSame([], $result['skills']);
        } finally {
            unlink($tempDir.'/composer.json');
            rmdir($tempDir);
        }
    }

    public function testDiscoverRootProjectWithoutAiMateConfig()
    {
        // Create a temporary directory with a composer.json without ai-mate config
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
        ];

        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertArrayHasKey('dirs', $result);
            $this->assertArrayHasKey('includes', $result);
            $this->assertArrayHasKey('skills', $result);
            $this->assertSame([], $result['dirs']);
            $this->assertSame([], $result['includes']);
            $this->assertSame([], $result['skills']);
        } finally {
            unlink($tempDir.'/composer.json');
            rmdir($tempDir);
        }
    }

    public function testDiscoverRootProjectExtractsSkills()
    {
        $tempDir = sys_get_temp_dir().'/mate-root-skills-'.uniqid();
        mkdir($tempDir.'/skills', 0755, true);

        $composerJson = [
            'name' => 'test/project',
            'extra' => ['ai-mate' => ['skills' => ['skills']]],
        ];
        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertSame(['skills'], $result['skills']);
        } finally {
            (new Filesystem())->remove($tempDir);
        }
    }

    public function testDiscoverRootProjectDropsMissingAndTraversingSkills()
    {
        $tempDir = sys_get_temp_dir().'/mate-root-skills-invalid-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
            'extra' => ['ai-mate' => ['skills' => ['does-not-exist', '../escape']]],
        ];
        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertSame([], $result['skills']);
        } finally {
            (new Filesystem())->remove($tempDir);
        }
    }

    public function testDiscoverSkipsPackagesWithExtensionFalse()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-extension-false',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        // Should only discover the normal package, not the excluded one
        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/normal-package', $extensions);
        $this->assertArrayNotHasKey('vendor/excluded-package', $extensions);
    }

    public function testDiscoverIncludesPackagesWithExtensionTrue()
    {
        // Create a temporary directory with installed.json containing extension: true
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir.'/vendor/composer', 0755, true);
        mkdir($tempDir.'/vendor/vendor/test-package/src', 0755, true);

        $installedJson = [
            'packages' => [
                [
                    'name' => 'vendor/test-package',
                    'extra' => [
                        'ai-mate' => [
                            'extension' => true,
                            'scan-dirs' => ['src'],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($tempDir.'/vendor/composer/installed.json', json_encode($installedJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $extensions = $discovery->discover();

            // Package with explicit extension: true should be discovered
            $this->assertCount(1, $extensions);
            $this->assertArrayHasKey('vendor/test-package', $extensions);
        } finally {
            unlink($tempDir.'/vendor/composer/installed.json');
            rmdir($tempDir.'/vendor/vendor/test-package/src');
            rmdir($tempDir.'/vendor/vendor/test-package');
            rmdir($tempDir.'/vendor/vendor');
            rmdir($tempDir.'/vendor/composer');
            rmdir($tempDir.'/vendor');
            rmdir($tempDir);
        }
    }

    public function testDiscoverIncludesPackagesWithoutExtensionFlag()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        // Packages without extension field should be discovered (backward compatibility)
        $this->assertCount(2, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);
    }

    public function testDiscoverWithIncludeFilterIgnoresExtensionFalsePackages()
    {
        $enabledExtensions = [
            'vendor/excluded-package', // Explicitly try to enable it
            'vendor/normal-package',
        ];

        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-extension-false',
            new NullLogger()
        );

        $extensions = $discovery->discover($enabledExtensions);

        // Extension flag takes precedence over include filter
        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/normal-package', $extensions);
        $this->assertArrayNotHasKey('vendor/excluded-package', $extensions);
    }

    public function testRootProjectNotAffectedByExtensionFlag()
    {
        // Create a temporary directory with a composer.json that has extension: false
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'ai-mate' => [
                    'extension' => false,
                    'scan-dirs' => ['src', 'lib'],
                    'includes' => ['config/mate.php'],
                ],
            ],
        ];

        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            // Root project discovery should work regardless of extension flag
            $this->assertArrayHasKey('dirs', $result);
            $this->assertArrayHasKey('includes', $result);
            $this->assertArrayHasKey('skills', $result);
            $this->assertSame(['src', 'lib'], $result['dirs']);
            $this->assertSame(['config/mate.php'], $result['includes']);
            $this->assertSame([], $result['skills']);
        } finally {
            unlink($tempDir.'/composer.json');
            rmdir($tempDir);
        }
    }
}
