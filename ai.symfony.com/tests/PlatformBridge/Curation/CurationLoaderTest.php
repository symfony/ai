<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\PlatformBridge\Curation;

use App\PlatformBridge\Curation\CurationLoader;
use App\PlatformBridge\Exception\PlatformBridgeCurationException;
use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Region;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CurationLoaderTest extends TestCase
{
    private const CURATION = __DIR__.'/../../../config/platform_bridges.yaml';
    private const SPLITS = __DIR__.'/../../../../splitsh.json';
    private const ICONS = __DIR__.'/../../../assets/icons';

    private ?string $path = null;

    protected function tearDown(): void
    {
        if (null !== $this->path) {
            @unlink($this->path);
        }
    }

    public function testLoadCreatesTypedCurations()
    {
        $curation = $this->load(<<<'YAML'
            bridges:
                Ollama:
                    name: Ollama
                    kind: local-runtime
                    deployment: [local, cloud]
                    regions: [anywhere]
                    model_access: multi-vendor
                    capabilities: [chat, embeddings, chat]
                    website: https://ollama.com
            YAML)['Ollama'];

        $this->assertSame('Ollama', $curation->name);
        $this->assertSame(Kind::LocalRuntime, $curation->kind);
        $this->assertSame([Deployment::Local, Deployment::Cloud], $curation->deployments);
        $this->assertSame([], $curation->hostings);
        $this->assertSame([Region::Anywhere], $curation->regions);
        $this->assertSame(ModelAccess::MultiVendor, $curation->modelAccess);
        $this->assertSame([Capability::Chat, Capability::Embeddings], $curation->capabilities, 'Duplicates are removed.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCurations(): iterable
    {
        yield 'missing kind' => ["bridges:\n    Foo:\n        name: Foo", 'missing the "kind" key'];
        yield 'unknown kind' => ["bridges:\n    Foo:\n        kind: spaceship", 'invalid value "spaceship"'];
        yield 'unknown key' => ["bridges:\n    Foo:\n        kind: gateway\n        color: blue", 'unknown keys: "color"'];
        yield 'unknown capability' => ["bridges:\n    Foo:\n        kind: gateway\n        capabilities: [telepathy]", 'invalid value "telepathy"'];
        yield 'list expected' => ["bridges:\n    Foo:\n        kind: gateway\n        regions: europe", 'must be a list'];
        yield 'removed key' => ["bridges:\n    Foo:\n        kind: gateway\n        headquarters: US", 'unknown keys: "headquarters"'];
        yield 'insecure website' => ["bridges:\n    Foo:\n        kind: gateway\n        website: http://example.com", 'https://'];
        yield 'no bridges map' => ['foo: bar', 'must define a "bridges" map'];
        yield 'invalid yaml' => ["bridges: [\n", 'not valid YAML'];
    }

    #[DataProvider('invalidCurations')]
    public function testLoadRejectsInvalidCurations(string $yaml, string $message)
    {
        $this->expectException(PlatformBridgeCurationException::class);
        $this->expectExceptionMessage($message);

        $this->load($yaml);
    }

    public function testTheParsedFileIsCachedUntilItChanges()
    {
        $cache = new ArrayAdapter();
        $this->load("bridges:\n    Foo:\n        kind: gateway\n");
        $this->assertNotNull($this->path);

        $this->assertSame(Kind::Gateway, (new CurationLoader($this->path, $cache))->load()['Foo']->kind);

        file_put_contents($this->path, "bridges:\n    Foo:\n        kind: local-runtime\n");
        touch($this->path, time() + 10);
        clearstatcache();

        $this->assertSame(Kind::LocalRuntime, (new CurationLoader($this->path, $cache))->load()['Foo']->kind);
    }

    public function testLoadFailsWhenTheFileIsMissing()
    {
        $this->expectException(PlatformBridgeCurationException::class);

        (new CurationLoader('/does/not/exist.yaml'))->load();
    }

    public function testTheShippedCurationIsValid()
    {
        $this->assertNotEmpty((new CurationLoader(self::CURATION))->load());
    }

    public function testEveryBridgeOfTheMonorepoIsCurated()
    {
        if (!is_file(self::SPLITS)) {
            $this->markTestSkipped('The website is not checked out in the symfony/ai monorepo.');
        }

        $curations = (new CurationLoader(self::CURATION))->load();

        $missing = [];
        foreach (json_decode((string) file_get_contents(self::SPLITS), true, flags: \JSON_THROW_ON_ERROR)['subtrees'] as $path) {
            if (\is_string($path) && 1 === preg_match('#^src/platform/src/Bridge/([^/]+)$#', $path, $matches) && !isset($curations[$matches[1]])) {
                $missing[] = $matches[1];
            }
        }

        $this->assertSame([], $missing, 'Classify these bridges in config/platform_bridges.yaml.');
    }

    public function testCuratedIconsExist()
    {
        foreach ((new CurationLoader(self::CURATION))->load() as $directory => $curation) {
            if (null === $curation->icon) {
                continue;
            }

            [$prefix, $name] = explode(':', $curation->icon, 2);
            $this->assertFileExists(\sprintf('%s/%s/%s.svg', self::ICONS, $prefix, $name), \sprintf('The icon of the "%s" bridge is missing, add the logo to assets/icons/brands or import the icon with "bin/console ux:icons:import %s".', $directory, $curation->icon));
        }
    }

    public function testBuildingBlocksAreTheOnlyOnesWithoutDeploymentOrRegion()
    {
        foreach ((new CurationLoader(self::CURATION))->load() as $directory => $curation) {
            if (Kind::BuildingBlock === $curation->kind) {
                continue;
            }

            $this->assertNotSame([], $curation->deployments, \sprintf('The "%s" bridge must tell where it runs.', $directory));
            $this->assertNotSame([], $curation->hostings, \sprintf('The "%s" bridge must tell who hosts it.', $directory));
            $this->assertNotNull($curation->modelAccess, \sprintf('The "%s" bridge must tell whether it is provider-agnostic.', $directory));
        }
    }

    public function testLocalBridgesRunAnywhere()
    {
        foreach ((new CurationLoader(self::CURATION))->load() as $directory => $curation) {
            if (\in_array(Deployment::Local, $curation->deployments, true)) {
                $this->assertContains(Region::Anywhere, $curation->regions, \sprintf('The "%s" bridge runs locally, so it must match every region.', $directory));
            }
        }
    }

    public function testSelfHostedBridgesAndOnlyThemRunAnywhere()
    {
        foreach ((new CurationLoader(self::CURATION))->load() as $directory => $curation) {
            $this->assertSame(
                \in_array(Hosting::SelfHosted, $curation->hostings, true),
                \in_array(Region::Anywhere, $curation->regions, true),
                \sprintf('The "%s" bridge must list the "anywhere" region if and only if it can be self-hosted.', $directory),
            );
        }
    }

    /**
     * @return array<string, \App\PlatformBridge\Curation\Curation>
     */
    private function load(string $yaml): array
    {
        $this->path = sys_get_temp_dir().'/platform_bridges_'.bin2hex(random_bytes(4)).'.yaml';
        file_put_contents($this->path, $yaml);

        return (new CurationLoader($this->path))->load();
    }
}
