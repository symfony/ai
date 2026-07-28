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

/**
 * Filesystem helpers for building throwaway skill trees in temp directories.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait SkillFixtureTrait
{
    private function createSkill(string $skillsDir, string $name, string $description, string $body = 'Body.'): void
    {
        $skillDir = $skillsDir.'/'.$name;
        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0777, true);
        }

        $content = <<<MD
            ---
            name: {$name}
            description: {$description}
            ---

            {$body}
            MD;

        file_put_contents($skillDir.'/SKILL.md', $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (is_link($dir)) {
            unlink($dir);

            return;
        }

        if (!is_dir($dir)) {
            return;
        }

        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($entries as $entry) {
            $path = $dir.'/'.$entry;
            if (is_link($path) || is_file($path)) {
                unlink($path);

                continue;
            }

            $this->removeDirectory($path);
        }

        rmdir($dir);
    }
}
