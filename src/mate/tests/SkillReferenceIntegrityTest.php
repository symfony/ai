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

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Every directory a shipped skill can live in: the core package's own, plus one per bridge,
     * because a skill ships with the extension whose tools it drives.
     *
     * @var list<string>
     */
    private const SKILLS_DIRS = [
        __DIR__.'/../skills',
        __DIR__.'/../src/Bridge/Symfony/skills',
        __DIR__.'/../src/Bridge/Monolog/skills',
    ];

    /**
     * Tools that deliberately carry no workflow prose. Leaving a tool out has to be a
     * decision someone wrote down, not something that slipped through — so the reverse
     * check below fails for anything not listed here.
     *
     * @var list<string>
     */
    private const TOOLS_WITHOUT_GUIDANCE = [];

    /**
     * A skill is only useful where its tools exist. Shipping one from a package that does not
     * declare them installs a workflow the project cannot follow: `symfony/ai-mate` alone has a
     * single tool, and removing a bridge leaves its skill behind pointing at tools that are gone.
     * Whatever a skill tells an agent to run must therefore come from the skill's own package.
     */
    #[DataProvider('skillFileProvider')]
    public function testSkillsShipWithThePackageThatDeclaresTheirTools(string $dir, string $file)
    {
        $ownTools = $this->toolNamesIn($this->packageRootFor($dir));
        $violations = [];

        preg_match_all('/tools:call\s+([a-z0-9][a-z0-9-]*)/', (string) file_get_contents($file), $calls);
        foreach (array_unique($calls[1]) as $name) {
            if (!\in_array($name, $ownTools, true)) {
                $violations[] = $name;
            }
        }

        $this->assertSame([], $violations, \sprintf(
            'Skill "%s" runs tools its own package does not declare: %s. Move the skill to the package that declares them.',
            basename($dir),
            implode(', ', $violations)
        ));
    }

    public function testSourceScanFindsCapabilities()
    {
        $this->assertNotSame([], $this->toolNames(), 'No #[MateTool] names found; the source scan is broken.');
        $this->assertNotSame([], $this->collectorNames(), 'No collector names found; the formatter scan is broken.');
        $this->assertNotSame([], $this->resourceTemplates(), 'No #[MateResourceTemplate] uriTemplates found; the template scan is broken.');
        $this->assertNotSame([], iterator_to_array($this->skillFileProvider()), 'No SKILL.md files found.');
    }

    #[DataProvider('skillFileProvider')]
    public function testFrontmatterNameMatchesDirectory(string $dir, string $file)
    {
        $name = $this->frontmatterName((string) file_get_contents($file));

        $this->assertSame(basename($dir), $name, \sprintf('Skill "%s" frontmatter name must equal its directory name.', basename($dir)));
    }

    #[DataProvider('skillFileProvider')]
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

    #[DataProvider('skillFileProvider')]
    public function testReferencedResourceUrisAndCollectorsExist(string $dir, string $file)
    {
        $content = (string) file_get_contents($file);
        $templates = $this->resourceTemplates();
        $collectors = $this->collectorNames();
        $violations = [];

        preg_match_all('#[a-z][a-z0-9+.\-]*://[^\s`)]+#', $content, $uris);
        foreach (array_unique($uris[0]) as $uri) {
            // Documentation links are not resource URIs; the guard is for mistyped Mate schemes.
            if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
                continue;
            }

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
        foreach (self::SKILLS_DIRS as $skillsDir) {
            $dirs = glob($skillsDir.'/*', \GLOB_ONLYDIR) ?: [];
            foreach ($dirs as $dir) {
                $file = $dir.'/SKILL.md';
                if (is_file($file)) {
                    yield basename($dir) => [$dir, $file];
                }
            }
        }
    }

    /**
     * The other direction of the drift guard: a tool nobody documents is a tool nobody
     * finds. Adding a capability without mentioning it in a skill or in the extension
     * instructions leaves agents on the long path they already know, which is exactly the
     * failure this component exists to remove.
     */
    public function testEveryToolIsMentionedInGuidance()
    {
        $documented = '';

        $finder = (new Finder())->files()->in(self::SKILLS_DIRS)->name('SKILL.md');
        foreach ($finder as $file) {
            $documented .= $file->getContents();
        }

        $instructions = (new Finder())->files()->in(self::SRC_DIR)->name('INSTRUCTIONS.md');
        foreach ($instructions as $file) {
            $documented .= $file->getContents();
        }

        $undocumented = [];
        foreach ($this->toolNames() as $name) {
            if (!str_contains($documented, $name)) {
                $undocumented[] = $name;
            }
        }

        $undocumented = array_values(array_diff($undocumented, self::TOOLS_WITHOUT_GUIDANCE));

        $this->assertSame([], $undocumented, \sprintf(
            'These tools exist but no SKILL.md or INSTRUCTIONS.md mentions them: %s. Add a line where an agent would look, or list the tool in TOOLS_WITHOUT_GUIDANCE with a reason.',
            implode(', ', $undocumented)
        ));
    }

    /**
     * @return list<string>
     */
    private function toolNames(): array
    {
        $names = [];
        foreach ($this->sourceFiles() as $content) {
            preg_match_all("/#\\[MateTool\\(name:\\s*'([^']+)'/", $content, $m);
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
            preg_match_all("/#\\[MateResourceTemplate\\(\\s*uriTemplate:\\s*'([^']+)'/s", $content, $m);
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

    /**
     * The directory whose composer.json declares the skill: the bridge it sits in, or the core
     * package for everything else.
     */
    private function packageRootFor(string $skillDir): string
    {
        if (1 === preg_match('#^(.*/src/Bridge/[^/]+)/skills/#', $skillDir.'/', $m)) {
            return $m[1];
        }

        return self::SRC_DIR;
    }

    /**
     * @return list<string>
     */
    private function toolNamesIn(string $root): array
    {
        $finder = (new Finder())->files()->in($root)->name('*.php');
        // The core package's sources contain the bridges; their tools belong to them, not to it.
        if (self::SRC_DIR === $root) {
            $finder->notPath('Bridge');
        }

        $names = [];
        foreach ($finder as $file) {
            preg_match_all("/#\\[MateTool\\(name:\\s*'([^']+)'/", $file->getContents(), $m);
            foreach ($m[1] as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
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
