<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\PlatformBridge;

use App\PlatformBridge\Curation\CurationLoader;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\PlatformBridgeCatalog;
use App\PlatformBridge\Taxonomy\Kind;
use PHPUnit\Framework\TestCase;

final class PlatformBridgeCatalogTest extends TestCase
{
    private string $curationPath;
    private string $dataPath;

    protected function setUp(): void
    {
        $this->curationPath = sys_get_temp_dir().'/platform_bridges_'.bin2hex(random_bytes(4)).'.yaml';
        $this->dataPath = sys_get_temp_dir().'/platform_bridges_'.bin2hex(random_bytes(4)).'.json';

        file_put_contents($this->curationPath, <<<'YAML'
            bridges:
                OpenAi:
                    name: OpenAI
                    summary: GPT models.
                    kind: model-provider
                    deployment: [cloud]
                    hosting: [saas]
                    regions: [us, europe]
                    model_access: single-vendor
                    capabilities: [chat, embeddings]
                    website: https://openai.com
                    symfony_docs: components/platform/openai-server-tools
                LmStudio:
                    name: LM Studio
                    kind: local-runtime
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->curationPath);
        @unlink($this->dataPath);
    }

    public function testBridgesCombineTheDataFileAndTheCuration()
    {
        $this->writeData(['OpenAi' => ['package' => 'symfony/ai-open-ai-platform', 'downloads' => 734_471]]);

        $openAi = $this->find($this->catalog()->getBridges(), 'OpenAi');

        $this->assertSame('OpenAI', $openAi->name);
        $this->assertSame('GPT models.', $openAi->summary);
        $this->assertSame(Kind::ModelProvider, $openAi->kind);
        $this->assertSame(734_471, $openAi->downloads);
        $this->assertSame('symfony/ai-open-ai-platform', $openAi->package);
        $this->assertSame('open-ai', $openAi->slug);
        $this->assertSame('https://github.com/symfony/ai/tree/main/src/platform/src/Bridge/OpenAi', $openAi->sourceUrl);
        $this->assertSame('https://symfony.com/doc/current/ai/components/platform/openai-server-tools.html', $openAi->symfonyDocsUrl);
        $this->assertSame('https://openai.com', $openAi->websiteUrl);
    }

    public function testBridgesOnlyKnownByTheDataFileAreListedAsNotClassified()
    {
        $this->writeData(['Voyage' => ['package' => 'symfony/ai-voyage-platform', 'downloads' => null]]);

        $voyage = $this->find($this->catalog()->getBridges(), 'Voyage');

        $this->assertFalse($voyage->isClassified());
        $this->assertSame('Voyage', $voyage->name);
        $this->assertSame('', $voyage->summary);
        $this->assertNull($voyage->downloads, 'A bridge not on Packagist yet has no downloads.');
    }

    public function testBridgesOnlyKnownByTheCurationAreListedWithTheirPackage()
    {
        $this->writeData(['OpenAi' => ['package' => 'symfony/ai-open-ai-platform', 'downloads' => 734_471]]);

        $lmStudio = $this->find($this->catalog()->getBridges(), 'LmStudio');

        $this->assertSame('symfony/ai-lm-studio-platform', $lmStudio->package);
        $this->assertNull($lmStudio->downloads);
    }

    public function testTheCurationIsEnoughBeforeTheFirstUpdate()
    {
        $catalog = $this->catalog();

        $this->assertSame(['LM Studio', 'OpenAI'], $this->names($catalog->getBridges()));
        $this->assertNull($catalog->getUpdatedAt());
    }

    public function testAnUnreadableDataFileIsIgnored()
    {
        file_put_contents($this->dataPath, '{"bridges": ');

        $this->assertSame(['LM Studio', 'OpenAI'], $this->names($this->catalog()->getBridges()));
    }

    public function testMalformedEntriesOfTheDataFileAreIgnored()
    {
        $this->writeData(['OpenAi' => ['package' => 'symfony/ai-open-ai-platform', 'downloads' => 'many'], 'Broken' => 'yes']);

        $bridges = $this->catalog()->getBridges();

        $this->assertSame(['LM Studio', 'OpenAI'], $this->names($bridges));
        $this->assertNull($this->find($bridges, 'OpenAi')->downloads);
    }

    public function testTheDateOfTheLastUpdateIsExposed()
    {
        $this->writeData([]);

        $this->assertEquals(new \DateTimeImmutable('2026-09-30T05:00:00+00:00'), $this->catalog()->getUpdatedAt());
    }

    private function catalog(): PlatformBridgeCatalog
    {
        return new PlatformBridgeCatalog(new CurationLoader($this->curationPath), $this->dataPath);
    }

    /**
     * @param array<string, mixed> $bridges
     */
    private function writeData(array $bridges): void
    {
        file_put_contents($this->dataPath, json_encode(['updatedAt' => '2026-09-30T05:00:00+00:00', 'bridges' => $bridges], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<PlatformBridge> $bridges
     *
     * @return list<string>
     */
    private function names(array $bridges): array
    {
        return array_map(static fn (PlatformBridge $bridge): string => $bridge->name, $bridges);
    }

    /**
     * @param list<PlatformBridge> $bridges
     */
    private function find(array $bridges, string $directory): PlatformBridge
    {
        foreach ($bridges as $bridge) {
            if ($directory === $bridge->directory) {
                return $bridge;
            }
        }

        $this->fail(\sprintf('The "%s" bridge was not listed.', $directory));
    }
}
