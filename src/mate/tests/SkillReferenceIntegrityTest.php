<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Guards the skills against drift.
 *
 * The SKILL.md files carry the workflow prose that replaced the MCP schema
 * layer. Unlike a generated schema, prose can silently rot: rename a tool,
 * a collector or a resource template in the code and the skill keeps telling
 * an agent to call a name that no longer exists. Every tool name, resource
 * URI and collector name a skill references must still resolve in the source,
 * or this test fails.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillReferenceIntegrityTest extends TestCase
{
    private const SRC_DIR = __DIR__.'/../src';
    private const SKILLS_DIR = __DIR__.'/../skills';

    public function testSourceScanFindsCapabilities()
    {
        $this->assertNotSame([], $this->toolNames(), 'No #[AsTool] names found; the source scan is broken.');
        $this->assertNotSame([], $this->collectorNames(), 'No collector names found; the formatter scan is broken.');
        $this->assertNotSame([], $this->resourceTemplates(), 'No #[AsResourceTemplate] uriTemplates found; the template scan is broken.');
        $this->assertNotSame([], iterator_to_array($this->skillFileProvider()), 'No SKILL.md files found.');
    }

    /**
     * @dataProvider skillFileProvider
     */
    public function testFrontmatterNameMatchesDirectory(string $dir, string $file)
    {
        $name = $this->frontmatterName((string) file_get_contents($file));

        $this->assertSame(basename($dir), $name, \sprintf('Skill "%s" frontmatter name must equal its directory name.', basename($dir)));
    }

    /**
     * @dataProvider skillFileProvider
     */
    public function testReferencedToolsExist(string $dir, string $file)
    {
        $content = (string) file_get_contents($file);
        $tools = $this->toolNames();
        $slugs = $this->skillSlugs();
        $violations = [];

        // Explicit `tools:call <name>` invocations.
        preg_match_all('/tools:call\s+([a-z0-9][a-z0-9-]*)/', $content, $calls);
        foreach ($calls[1] as $name) {
            if (!\in_array($name, $tools, true)) {
                $violations[] = \sprintf('tools:call %s', $name);
            }
        }

        // Bare backticked tool-shaped tokens, excluding the skills' own slugs.
        preg_match_all('/`(server-info|(?:symfony|monolog)-[a-z0-9-]+)`/', $content, $bare);
        foreach ($bare[1] as $token) {
            if (\in_array($token, $slugs, true)) {
                continue;
            }
            if (!\in_array($token, $tools, true)) {
                $violations[] = \sprintf('`%s`', $token);
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), \sprintf('Skill "%s" references tools that no longer exist.', basename($dir)));
    }

    /**
     * @dataProvider skillFileProvider
     */
    public function testReferencedResourceUrisAndCollectorsExist(string $dir, string $file)
    {
        $content = (string) file_get_contents($file);
        $templates = $this->resourceTemplates();
        $collectors = $this->collectorNames();
        $violations = [];

        preg_match_all('#[a-z][a-z0-9+.\-]*://[^\s`)]+#', $content, $uris);
        foreach (array_unique($uris[0]) as $uri) {
            $normalized = preg_replace('/\{[^}]+\}|<[^>]+>/', 'PH', $uri);

            $matched = false;
            foreach ($templates as $template) {
                if (1 === preg_match($this->templateRegex($template), (string) $normalized)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $violations[] = \sprintf('%s (matches no resource template)', $uri);
            }

            // A profiler collector URI carries the collector as its last segment;
            // when it is a literal (not a {token}/<placeholder>), it must exist.
            if (1 === preg_match('#^symfony-profiler://profile/[^/]+/([^/]+)$#', $uri, $m)) {
                $segment = $m[1];
                $isPlaceholder = str_contains($segment, '{') || str_contains($segment, '<');
                if (!$isPlaceholder && !\in_array($segment, $collectors, true)) {
                    $violations[] = \sprintf('%s (unknown collector "%s")', $uri, $segment);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), \sprintf('Skill "%s" references resources or collectors that no longer exist.', basename($dir)));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function skillFileProvider(): iterable
    {
        $dirs = glob(self::SKILLS_DIR.'/*', \GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $file = $dir.'/SKILL.md';
            if (is_file($file)) {
                yield basename($dir) => [$dir, $file];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function toolNames(): array
    {
        $names = [];
        foreach ($this->sourceFiles() as $content) {
            preg_match_all("/#\\[AsTool\\(name:\\s*'([^']+)'/", $content, $m);
            foreach ($m[1] as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function collectorNames(): array
    {
        $names = [];
        $finder = (new Finder())->files()->in(self::SRC_DIR.'/Bridge/Symfony/Profiler/Service/Formatter')->name('*.php');
        foreach ($finder as $file) {
            preg_match_all("/function getName\\(\\):\\s*string\\s*\\{\\s*return\\s*'([^']+)';/", $file->getContents(), $m);
            foreach ($m[1] as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function resourceTemplates(): array
    {
        $templates = [];
        foreach ($this->sourceFiles() as $content) {
            preg_match_all("/#\\[AsResourceTemplate\\(\\s*uriTemplate:\\s*'([^']+)'/s", $content, $m);
            foreach ($m[1] as $template) {
                $templates[] = $template;
            }
        }

        return array_values(array_unique($templates));
    }

    /**
     * @return list<string>
     */
    private function skillSlugs(): array
    {
        $slugs = [];
        foreach (self::skillFileProvider() as $pair) {
            $slugs[] = basename($pair[0]);
        }

        return $slugs;
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        $finder = (new Finder())->files()->in(self::SRC_DIR)->name('*.php');
        foreach ($finder as $file) {
            yield $file->getContents();
        }
    }

    private function templateRegex(string $template): string
    {
        $parts = preg_split('/\{[^}]+\}/', $template) ?: [$template];
        $quoted = array_map(static fn (string $part): string => preg_quote($part, '#'), $parts);

        return '#^'.implode('[^/]+', $quoted).'$#';
    }

    private function frontmatterName(string $content): ?string
    {
        if (1 !== preg_match('/^---\s*\n(.*?)\n---/s', $content, $block)) {
            return null;
        }

        if (1 !== preg_match('/^name:\s*(.+)$/m', $block[1], $m)) {
            return null;
        }

        return trim($m[1]);
    }
}
