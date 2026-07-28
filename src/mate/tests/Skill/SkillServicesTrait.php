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

use Psr\Log\NullLogger;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Skill\Linker;
use Symfony\AI\Mate\Skill\LinkerInterface;
use Symfony\AI\Mate\Skill\SkillContentHasher;
use Symfony\AI\Mate\Skill\SkillDiscovery;
use Symfony\AI\Mate\Skill\SkillFrontmatter;
use Symfony\AI\Mate\Skill\SkillInstaller;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\AI\Mate\Skill\SkillStateRepository;

/**
 * Builds the real skill services against a temp root, so command tests need no mocks.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait SkillServicesTrait
{
    private function createManager(string $rootDir, ?LinkerInterface $linker = null): SkillManager
    {
        $logger = new NullLogger();
        $frontmatter = new SkillFrontmatter();
        $hasher = new SkillContentHasher();
        $repository = new SkillStateRepository($rootDir);

        return new SkillManager(
            $rootDir,
            new ComposerExtensionDiscovery($rootDir, $logger),
            new SkillDiscovery($rootDir, $frontmatter, $logger),
            $repository,
            new SkillInstaller($rootDir, $repository, $frontmatter, $hasher, $linker ?? new Linker(), $logger),
            $hasher,
            $frontmatter,
        );
    }

    /**
     * Writes a vendor/composer/installed.json declaring one package that ships a skills directory.
     */
    private function createInstalledPackage(string $rootDir, string $package = 'vendor/pkg-a', string $skillsDir = 'skills'): void
    {
        if (!is_dir($rootDir.'/vendor/composer')) {
            mkdir($rootDir.'/vendor/composer', 0777, true);
        }

        file_put_contents($rootDir.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                [
                    'name' => $package,
                    'type' => 'library',
                    'extra' => ['ai-mate' => ['skills' => [$skillsDir]]],
                ],
            ],
        ]));
    }
}
