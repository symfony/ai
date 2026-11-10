<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\PlatformBridge\Filter;

use App\PlatformBridge\Filter\BridgeFilter;
use App\PlatformBridge\Filter\Facet;
use App\PlatformBridge\Filter\Preset;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Region;
use PHPUnit\Framework\TestCase;

final class BridgeFilterTest extends TestCase
{
    public function testAnEmptyFilterKeepsEverything()
    {
        $filter = new BridgeFilter();

        $this->assertTrue($filter->isEmpty());
        $this->assertSame(['Mistral', 'Ollama', 'OpenAI', 'Voyage'], $this->names($filter->apply($this->bridges())));
    }

    public function testOptionsOfAFacetAreAlternatives()
    {
        $filter = new BridgeFilter(['kind' => ['local-runtime', 'model-provider']]);

        $this->assertSame(['Mistral', 'Ollama', 'OpenAI', 'Voyage'], $this->names($filter->apply($this->bridges())));

        $filter = new BridgeFilter(['region' => ['asia', 'us']]);

        $this->assertSame(['Ollama', 'OpenAI', 'Voyage'], $this->names($filter->apply($this->bridges())));
    }

    public function testFacetsAreCombined()
    {
        $filter = new BridgeFilter(['kind' => ['model-provider'], 'access' => ['single-vendor'], 'region' => ['europe']]);

        $this->assertSame(['Mistral', 'OpenAI'], $this->names($filter->apply($this->bridges())));
    }

    public function testCapabilitiesMustAllBeSupported()
    {
        $filter = new BridgeFilter(['capability' => ['chat', 'embeddings']]);

        $this->assertSame(['Mistral', 'Ollama', 'OpenAI'], $this->names($filter->apply($this->bridges())));

        $filter = new BridgeFilter(['capability' => ['chat', 'embeddings', 'speech-to-text']]);

        $this->assertSame(['OpenAI'], $this->names($filter->apply($this->bridges())));
    }

    public function testWhereABridgeRunsAndIsHostedMustAllBeSupported()
    {
        $this->assertSame(['Ollama'], $this->names((new BridgeFilter(['runs' => ['cloud', 'local']]))->apply($this->bridges())));
        $this->assertSame(['Ollama'], $this->names((new BridgeFilter(['hosting' => ['saas', 'self-hosted']]))->apply($this->bridges())));
    }

    public function testSelfHostedBridgesMatchEveryRegion()
    {
        $filter = new BridgeFilter(['region' => ['europe']]);

        $this->assertSame(['Mistral', 'Ollama', 'OpenAI'], $this->names($filter->apply($this->bridges())));
    }

    public function testUnknownFacetsAndOptionsAreIgnored()
    {
        $filter = new BridgeFilter(['region' => ['mars'], 'color' => ['blue']]);

        $this->assertTrue($filter->isEmpty());
        $this->assertSame([], $filter->getSelected(Facet::Region));
        $this->assertCount(4, $filter->apply($this->bridges()));
    }

    public function testTheSelfHostedRegionCannotBeSelected()
    {
        $this->assertSame([], (new BridgeFilter(['region' => ['anywhere']]))->getSelected(Facet::Region));
    }

    public function testQueryMatchesEveryWordOfTheNameOrDescription()
    {
        $this->assertSame(['Voyage'], $this->names((new BridgeFilter([], 'voyage'))->apply($this->bridges())));
        $this->assertSame(['Ollama'], $this->names((new BridgeFilter([], 'OPEN-WEIGHT machine'))->apply($this->bridges())), 'Descriptions are searched, case insensitively.');
        $this->assertSame(['Mistral', 'OpenAI'], $this->names((new BridgeFilter([], 'speech'))->apply($this->bridges())));
        $this->assertSame([], (new BridgeFilter([], 'voyage chat'))->apply($this->bridges()));
        $this->assertSame([], (new BridgeFilter([], 'runtime'))->apply($this->bridges()), 'Labels of the facets are not searched.');
    }

    public function testCountsTellWhatEachOptionWouldLeadTo()
    {
        $filter = new BridgeFilter(['runs' => ['cloud'], 'region' => ['us']]);
        $bridges = $this->bridges();

        // counts of a facet of alternatives ignore the selection of that facet, Ollama matches every region
        $this->assertSame(['worldwide' => 2, 'us' => 3, 'europe' => 3, 'asia' => 1], $filter->count($bridges, Facet::Region));
        // counts of a facet requiring all the options include its selection, only Ollama runs both in the cloud and locally
        $this->assertSame(['cloud' => 3, 'local' => 1], $filter->count($bridges, Facet::Deployment));
        $this->assertSame(2, $filter->count($bridges, Facet::Capability)['chat']);
    }

    public function testCountsOfCapabilitiesIncludeTheSelectedOnes()
    {
        $filter = new BridgeFilter(['capability' => ['embeddings']]);

        $counts = $filter->count($this->bridges(), Facet::Capability);

        $this->assertSame(4, $counts['embeddings']);
        $this->assertSame(3, $counts['chat']);
        $this->assertSame(1, $counts['speech-to-text']);
    }

    public function testEveryPresetSelectsKnownOptions()
    {
        foreach (Preset::cases() as $preset) {
            $filter = new BridgeFilter($preset->getSelection());

            $this->assertFalse($filter->isEmpty(), \sprintf('The "%s" preset selects nothing.', $preset->value));
            foreach ($preset->getSelection() as $facet => $values) {
                $this->assertSame($values, $filter->getSelected(Facet::from($facet)));
            }
        }
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
     * @return list<PlatformBridge>
     */
    private function bridges(): array
    {
        return [
            new PlatformBridge(
                directory: 'Mistral', slug: 'mistral', name: 'Mistral', summary: 'Chat, OCR and speech models.', package: 'symfony/ai-mistral-platform', sourceUrl: 'https://github.com',
                kind: Kind::ModelProvider, deployments: [Deployment::Cloud], hostings: [Hosting::Saas], regions: [Region::Europe],
                capabilities: [Capability::Chat, Capability::Embeddings, Capability::Vision], modelAccess: ModelAccess::SingleVendor,
            ),
            new PlatformBridge(
                directory: 'Ollama', slug: 'ollama', name: 'Ollama', summary: 'Open-weight models on your machine.', package: 'symfony/ai-ollama-platform', sourceUrl: 'https://github.com',
                kind: Kind::LocalRuntime, deployments: [Deployment::Local, Deployment::Cloud], hostings: [Hosting::SelfHosted, Hosting::Saas], regions: [Region::Anywhere],
                capabilities: [Capability::Chat, Capability::Embeddings], modelAccess: ModelAccess::MultiVendor,
            ),
            new PlatformBridge(
                directory: 'OpenAi', slug: 'open-ai', name: 'OpenAI', summary: 'GPT, Whisper and speech models.', package: 'symfony/ai-open-ai-platform', sourceUrl: 'https://github.com',
                kind: Kind::ModelProvider, deployments: [Deployment::Cloud], hostings: [Hosting::Saas], regions: [Region::UnitedStates, Region::Europe],
                capabilities: [Capability::Chat, Capability::Embeddings, Capability::SpeechToText], modelAccess: ModelAccess::SingleVendor,
            ),
            new PlatformBridge(
                directory: 'Voyage', slug: 'voyage', name: 'Voyage', summary: 'Embedding models.', package: 'symfony/ai-voyage-platform', sourceUrl: 'https://github.com',
                kind: Kind::ModelProvider, deployments: [Deployment::Cloud], hostings: [Hosting::Saas], regions: [Region::Worldwide, Region::UnitedStates],
                capabilities: [Capability::Embeddings], modelAccess: ModelAccess::SingleVendor,
            ),
        ];
    }
}
