<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Service\ExtensionConfigSynchronizer;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExtensionConfigSynchronizerTest extends TestCase
{
    use SkillFixtureTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-synchronizer-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testWritesValidPackageNames()
    {
        $this->synchronizer()->synchronize(['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]]);

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertSame(['vendor/package-a' => ['enabled' => true]], $extensions);
    }

    public function testMaliciousPackageNameCannotInjectCode()
    {
        // A crafted package name that would break out of the single-quoted string literal
        // and inject additional array entries / PHP code under naive interpolation.
        $maliciousName = "evil', 'injected' => ['enabled' => true], 'x";

        $this->synchronizer()->synchronize([$maliciousName => ['dirs' => [], 'includes' => [], 'skills' => []]]);

        $extensions = include $this->rootDir.'/mate/extensions.php';

        // The whole crafted string must round-trip as a single key, and no extra entry is created.
        $this->assertSame([$maliciousName => ['enabled' => true]], $extensions);
        $this->assertArrayNotHasKey('injected', $extensions);
    }

    public function testAddsNewlyDiscoveredSkillsWithDefaultIntent()
    {
        $this->synchronizer()->synchronize(
            ['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]],
            [$this->skill('vendor/package-a', 'system-information')],
        );

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertSame([
            'vendor/package-a' => [
                'enabled' => true,
                'skills' => [
                    'system-information' => ['enabled' => true, 'mode' => 'managed'],
                ],
            ],
        ], $extensions);
    }

    public function testPreservesExistingSkillIntent()
    {
        $this->writeExtensions(<<<'PHP'
            'vendor/package-a' => [
                'enabled' => false,
                'skills' => [
                    'system-information' => ['enabled' => false, 'mode' => 'override'],
                ],
            ],
            PHP);

        $this->synchronizer()->synchronize(
            ['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]],
            [$this->skill('vendor/package-a', 'system-information')],
        );

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertSame([
            'vendor/package-a' => [
                'enabled' => false,
                'skills' => [
                    'system-information' => ['enabled' => false, 'mode' => 'override'],
                ],
            ],
        ], $extensions);
    }

    public function testKeepsVanishedSkillEntriesForTheInstallerToPrune()
    {
        $this->writeExtensions(<<<'PHP'
            'vendor/package-a' => [
                'enabled' => true,
                'skills' => [
                    'gone' => [
                        'enabled' => true,
                        'mode' => 'managed',
                        'state' => 'managed',
                        'source' => 'vendor/vendor/package-a/skills/gone',
                        'source_hash' => 'sha256:aaa',
                        'hash' => 'sha256:bbb',
                        'targets' => ['.agents/skills/mate-gone', '.claude/skills/mate-gone'],
                    ],
                ],
            ],
            PHP);

        $this->synchronizer()->synchronize(
            ['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]],
            [],
        );

        $extensions = include $this->rootDir.'/mate/extensions.php';

        // Dropping it here would strip the recorded targets before the installer can delete them.
        $this->assertArrayHasKey('gone', $extensions['vendor/package-a']['skills']);
    }

    public function testPreservesFactsWrittenByTheInstaller()
    {
        $this->writeExtensions(<<<'PHP'
            'vendor/package-a' => [
                'enabled' => true,
                'skills' => [
                    'system-information' => [
                        'enabled' => true,
                        'mode' => 'managed',
                        'state' => 'managed',
                        'source' => 'vendor/vendor/package-a/skills/system-information',
                        'source_hash' => 'sha256:aaa',
                        'hash' => 'sha256:bbb',
                        'targets' => ['.agents/skills/mate-system-information', '.claude/skills/mate-system-information'],
                    ],
                ],
            ],
            PHP);

        $this->synchronizer()->synchronize(
            ['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]],
            [$this->skill('vendor/package-a', 'system-information')],
        );

        $extensions = include $this->rootDir.'/mate/extensions.php';
        $state = $extensions['vendor/package-a']['skills']['system-information'];

        $this->assertSame('managed', $state['state']);
        $this->assertSame('sha256:aaa', $state['source_hash']);
        $this->assertSame('sha256:bbb', $state['hash']);
        $this->assertSame(['.agents/skills/mate-system-information', '.claude/skills/mate-system-information'], $state['targets']);
    }

    public function testMigratesLegacyOverrideBoolean()
    {
        $this->writeExtensions(<<<'PHP'
            'vendor/package-a' => [
                'enabled' => true,
                'skills' => [
                    'system-information' => ['enabled' => true, 'override' => true],
                ],
            ],
            PHP);

        $this->synchronizer()->synchronize(
            ['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]],
            [$this->skill('vendor/package-a', 'system-information')],
        );

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertSame(
            ['enabled' => true, 'mode' => 'override'],
            $extensions['vendor/package-a']['skills']['system-information'],
        );
    }

    public function testEmitsCustomEntryForRootProjectSkills()
    {
        $this->synchronizer()->synchronize([], [$this->skill('_custom', 'project-notes')]);

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertArrayHasKey('_custom', $extensions);
        $this->assertSame(['project-notes' => ['enabled' => true, 'mode' => 'managed']], $extensions['_custom']['skills']);
    }

    private function writeExtensions(string $body): void
    {
        mkdir($this->rootDir.'/mate', 0777, true);
        file_put_contents($this->rootDir.'/mate/extensions.php', "<?php\n\nreturn [\n".$body."\n];\n");
    }

    private function synchronizer(): ExtensionConfigSynchronizer
    {
        return new ExtensionConfigSynchronizer(new SkillStateRepository($this->rootDir));
    }

    private function skill(string $package, string $originalName): DiscoveredSkill
    {
        return new DiscoveredSkill(
            package: $package,
            originalName: $originalName,
            installedName: 'mate-'.$originalName,
            source: 'skills/'.$originalName,
            absolutePath: $this->rootDir.'/skills/'.$originalName,
            description: 'Test skill.',
        );
    }
}
