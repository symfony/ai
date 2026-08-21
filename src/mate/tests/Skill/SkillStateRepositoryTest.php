<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Exception\FileWriteException;
use Symfony\AI\Mate\Skill\SkillStateRepository;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillStateRepositoryTest extends TestCase
{
    use SkillFixtureTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skill-state-'.uniqid();
        mkdir($this->rootDir.'/mate', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testReadsEmptyArrayWhenFileIsMissing()
    {
        $this->assertFalse($this->repository()->exists());
        $this->assertSame([], $this->repository()->read());
    }

    public function testRoundTripsIntentAndFacts()
    {
        $config = [
            'vendor/pkg-a' => [
                'enabled' => true,
                'skills' => [
                    'system-information' => [
                        'enabled' => true,
                        'mode' => 'managed',
                        'state' => 'managed',
                        'source' => 'vendor/vendor/pkg-a/skills/system-information',
                        'source_hash' => 'sha256:aaa',
                        'hash' => 'sha256:bbb',
                        'targets' => [
                            '.agents/skills/mate-system-information',
                            '.claude/skills/mate-system-information',
                        ],
                    ],
                ],
            ],
            'vendor/pkg-b' => ['enabled' => false],
        ];

        $this->repository()->write($config);

        $this->assertSame($config, $this->repository()->read());
    }

    public function testOnlyEnabledAndModeAreEmittedBeforeTheFirstInstall()
    {
        $this->repository()->write([
            'vendor/pkg-a' => ['enabled' => true, 'skills' => ['demo' => ['enabled' => true, 'mode' => 'managed']]],
        ]);

        $content = file_get_contents($this->rootDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringContainsString("'mode' => 'managed',", $content);
        $this->assertStringNotContainsString("'state' =>", $content);
        $this->assertStringNotContainsString("'targets' =>", $content);
    }

    public function testRenderingIsByteStable()
    {
        $config = [
            'vendor/pkg-a' => [
                'enabled' => true,
                'skills' => [
                    'zebra' => ['enabled' => true, 'mode' => 'managed'],
                    'alpha' => ['enabled' => false, 'mode' => 'override'],
                ],
            ],
        ];

        $this->repository()->write($config);
        $first = file_get_contents($this->rootDir.'/mate/extensions.php');

        $this->repository()->write($this->repository()->read());
        $this->assertSame($first, file_get_contents($this->rootDir.'/mate/extensions.php'));

        // Skills are sorted, so hand-added entries do not reshuffle the file on the next write.
        $this->assertIsString($first);
        $this->assertLessThan(strpos($first, "'zebra'"), strpos($first, "'alpha'"));
    }

    public function testIdenticalContentDoesNotRewriteTheFile()
    {
        $config = ['vendor/pkg-a' => ['enabled' => true]];
        $this->repository()->write($config);

        $path = $this->rootDir.'/mate/extensions.php';
        touch($path, time() - 120);
        clearstatcache(true, $path);
        $before = filemtime($path);

        $this->repository()->write($config);
        clearstatcache(true, $path);

        $this->assertSame($before, filemtime($path));
    }

    public function testMigratesLegacyOverrideBooleanToMode()
    {
        file_put_contents($this->rootDir.'/mate/extensions.php', <<<'PHP'
            <?php

            return [
                'vendor/pkg-a' => [
                    'enabled' => true,
                    'skills' => [
                        'owned' => ['enabled' => true, 'override' => true],
                        'plain' => ['enabled' => true, 'override' => false],
                    ],
                ],
            ];
            PHP);

        $config = $this->repository()->read();

        $this->assertSame('override', $config['vendor/pkg-a']['skills']['owned']['mode']);
        $this->assertSame('managed', $config['vendor/pkg-a']['skills']['plain']['mode']);

        $this->repository()->write($config);
        $content = file_get_contents($this->rootDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringNotContainsString("'override' =>", $content);
    }

    public function testUnknownModeFallsBackToManaged()
    {
        file_put_contents($this->rootDir.'/mate/extensions.php', <<<'PHP'
            <?php

            return [
                'vendor/pkg-a' => ['enabled' => true, 'skills' => ['demo' => ['enabled' => true, 'mode' => 'nonsense']]],
            ];
            PHP);

        $this->assertSame('managed', $this->repository()->read()['vendor/pkg-a']['skills']['demo']['mode']);
    }

    public function testDropsMalformedEntries()
    {
        file_put_contents($this->rootDir.'/mate/extensions.php', <<<'PHP'
            <?php

            return [
                'vendor/pkg-a' => 'not-an-array',
                'vendor/pkg-b' => [
                    'enabled' => true,
                    'skills' => [
                        'good' => ['enabled' => true, 'mode' => 'managed'],
                        'bad' => 'not-an-array',
                    ],
                ],
            ];
            PHP);

        $config = $this->repository()->read();

        $this->assertArrayNotHasKey('vendor/pkg-a', $config);
        $this->assertSame(['good'], array_keys($config['vendor/pkg-b']['skills']));
    }

    public function testFactsWithoutAValidStateAreDiscarded()
    {
        file_put_contents($this->rootDir.'/mate/extensions.php', <<<'PHP'
            <?php

            return [
                'vendor/pkg-a' => [
                    'enabled' => true,
                    'skills' => ['demo' => ['enabled' => true, 'mode' => 'managed', 'state' => 'nonsense', 'source' => 'x']],
                ],
            ];
            PHP);

        $state = $this->repository()->read()['vendor/pkg-a']['skills']['demo'];

        $this->assertSame(['enabled' => true, 'mode' => 'managed'], $state);
    }

    public function testMaliciousNamesAndValuesCannotInjectCode()
    {
        $marker = $this->rootDir.'/pwned.txt';
        $payload = "'] = null; file_put_contents('".$marker."', 'x'); \$x = ['";

        $this->repository()->write([
            $payload => [
                'enabled' => true,
                'skills' => [
                    $payload => [
                        'enabled' => true,
                        'mode' => 'managed',
                        'state' => 'managed',
                        'source' => $payload,
                        'source_hash' => $payload,
                        'hash' => $payload,
                        'targets' => [$payload],
                    ],
                ],
            ],
        ]);

        $config = $this->repository()->read();

        $this->assertFileDoesNotExist($marker);
        $this->assertArrayHasKey($payload, $config);
        $this->assertSame($payload, $config[$payload]['skills'][$payload]['source']);
        $this->assertSame([$payload], $config[$payload]['skills'][$payload]['targets']);
    }

    public function testFindResolvesInstalledAndOriginalNames()
    {
        $this->repository()->write([
            'vendor/pkg-a' => ['enabled' => true, 'skills' => ['system-information' => ['enabled' => true, 'mode' => 'managed']]],
        ]);

        $byOriginal = $this->repository()->find('system-information');
        $byInstalled = $this->repository()->find('mate-system-information');

        $this->assertNotNull($byOriginal);
        $this->assertNotNull($byInstalled);
        $this->assertSame('vendor/pkg-a', $byOriginal['package']);
        $this->assertSame('system-information', $byInstalled['name']);
        $this->assertNull($this->repository()->find('does-not-exist'));
    }

    public function testFindAllReportsAmbiguousNames()
    {
        $this->repository()->write([
            'vendor/pkg-a' => ['enabled' => true, 'skills' => ['shared' => ['enabled' => true, 'mode' => 'managed']]],
            'vendor/pkg-b' => ['enabled' => true, 'skills' => ['shared' => ['enabled' => true, 'mode' => 'managed']]],
        ]);

        $this->assertCount(2, $this->repository()->findAll('shared'));
        $this->assertNull($this->repository()->find('shared'));
    }

    public function testDropEntryRemovesTheSkillAndTheEmptySkillsBlock()
    {
        $this->repository()->write([
            'vendor/pkg-a' => ['enabled' => true, 'skills' => ['demo' => ['enabled' => true, 'mode' => 'managed']]],
        ]);

        $this->repository()->dropEntry('vendor/pkg-a', 'demo');

        $this->assertSame(['vendor/pkg-a' => ['enabled' => true]], $this->repository()->read());
    }

    public function testWriteFailsLoudlyWhenTheDirectoryIsNotWritable()
    {
        if (0 === posix_getuid()) {
            $this->markTestSkipped('Running as root, permissions are not enforced.');
        }

        $this->repository()->write(['vendor/pkg-a' => ['enabled' => true]]);
        chmod($this->rootDir.'/mate', 0555);

        try {
            $this->expectException(FileWriteException::class);
            $this->repository()->write(['vendor/pkg-b' => ['enabled' => true]]);
        } finally {
            chmod($this->rootDir.'/mate', 0777);
        }
    }

    public function testWriteLeavesNoTemporaryFileBehindOnFailure()
    {
        if (0 === posix_getuid()) {
            $this->markTestSkipped('Running as root, permissions are not enforced.');
        }

        $this->repository()->write(['vendor/pkg-a' => ['enabled' => true]]);
        chmod($this->rootDir.'/mate', 0555);

        try {
            $this->repository()->write(['vendor/pkg-b' => ['enabled' => true]]);
        } catch (FileWriteException) {
            // expected
        } finally {
            chmod($this->rootDir.'/mate', 0777);
        }

        $this->assertSame(['extensions.php'], array_values(array_diff(scandir($this->rootDir.'/mate') ?: [], ['.', '..'])));
    }

    private function repository(): SkillStateRepository
    {
        return new SkillStateRepository($this->rootDir);
    }
}
