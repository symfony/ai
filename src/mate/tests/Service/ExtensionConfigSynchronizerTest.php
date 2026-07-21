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

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExtensionConfigSynchronizerTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-synchronizer-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $file = $this->rootDir.'/mate/extensions.php';
        if (is_file($file)) {
            unlink($file);
        }
        foreach ([$this->rootDir.'/mate', $this->rootDir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testWritesValidPackageNames()
    {
        $synchronizer = new ExtensionConfigSynchronizer($this->rootDir);

        $synchronizer->synchronize(['vendor/package-a' => ['dirs' => [], 'includes' => [], 'skills' => []]]);

        $extensions = include $this->rootDir.'/mate/extensions.php';

        $this->assertSame(['vendor/package-a' => ['enabled' => true]], $extensions);
    }

    public function testMaliciousPackageNameCannotInjectCode()
    {
        $synchronizer = new ExtensionConfigSynchronizer($this->rootDir);

        // A crafted package name that would break out of the single-quoted string literal
        // and inject additional array entries / PHP code under naive interpolation.
        $maliciousName = "evil', 'injected' => ['enabled' => true], 'x";

        $synchronizer->synchronize([$maliciousName => ['dirs' => [], 'includes' => [], 'skills' => []]]);

        $extensions = include $this->rootDir.'/mate/extensions.php';

        // The whole crafted string must round-trip as a single key, and no extra entry is created.
        $this->assertSame([$maliciousName => ['enabled' => true]], $extensions);
        $this->assertArrayNotHasKey('injected', $extensions);
    }
}
