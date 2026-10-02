<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Twig\Components;

use App\PlatformBridge\Filter\Sort;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\PlatformBridgeCatalog;
use App\PlatformBridge\Taxonomy\Capability;
use App\Twig\Components\PlatformBridgeList;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PlatformBridgeListTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testRendersEveryBridgeByDefault()
    {
        $crawler = $this->createLiveComponent(PlatformBridgeList::class)->render()->crawler();

        $this->assertCount(\count($this->catalog()->getBridges()), $crawler->filter('article.platform-card'));
        $this->assertStringContainsString(\sprintf('%1$d of %1$d bridges', \count($this->catalog()->getBridges())), $crawler->filter('.platform-results')->text());
    }

    public function testTheMostDownloadedBridgesComeFirst()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);

        $downloads = [];
        foreach ($this->catalog()->getBridges() as $bridge) {
            $downloads[$bridge->name] = $bridge->downloads ?? -1;
        }

        $names = $this->names($component->render()->crawler());
        $rendered = array_map(static fn (string $name): int => $downloads[$name], $names);
        $sorted = $rendered;
        rsort($sorted);

        $this->assertSame(Sort::Popularity, $component->component()->sort);
        $this->assertSame($sorted, $rendered);
    }

    public function testTogglingAnOptionFiltersTheBridges()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'region', 'value' => 'europe']);

        $names = $this->names($component->render()->crawler());

        $this->assertContains('Mistral AI', $names);
        $this->assertContains('Ollama', $names, 'Self-hosted bridges match every region.');
        $this->assertNotContains('Anthropic', $names);
        $this->assertSame(['europe'], $component->component()->regions);
    }

    public function testTogglingAnOptionTwiceRemovesIt()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'region', 'value' => 'europe']);
        $component->call('toggle', ['facet' => 'region', 'value' => 'europe']);

        $this->assertSame([], $component->component()->regions);
        $this->assertCount(\count($this->catalog()->getBridges()), $component->render()->crawler()->filter('article.platform-card'));
    }

    public function testUnknownFacetsAndOptionsAreIgnored()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'region', 'value' => 'mars']);
        $component->call('toggle', ['facet' => 'color', 'value' => 'blue']);

        $this->assertSame([], $component->component()->regions);
        $this->assertCount(\count($this->catalog()->getBridges()), $component->render()->crawler()->filter('article.platform-card'));
    }

    public function testEverySelectedCapabilityMustBeSupported()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'capability', 'value' => 'embeddings']);
        $component->call('toggle', ['facet' => 'capability', 'value' => 'vision']);

        $names = $this->names($component->render()->crawler());

        $this->assertNotEmpty($names);
        foreach ($this->catalog()->getBridges() as $bridge) {
            $expected = $bridge->supports(Capability::Embeddings) && $bridge->supports(Capability::Vision);
            $this->assertSame($expected, \in_array($bridge->name, $names, true), $bridge->name);
        }
    }

    public function testPresetsReplaceTheSelection()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'region', 'value' => 'us']);
        $component->call('applyPreset', ['preset' => 'offline']);

        $list = $component->component();
        $this->assertSame([], $list->regions);
        $this->assertSame(['local'], $list->deployments);

        $crawler = $component->render()->crawler();
        $this->assertSame('Offline & private', trim($crawler->filter('.platform-preset.is-active')->text()));
        foreach ($this->names($crawler) as $name) {
            $this->assertContains($name, ['Docker Model Runner', 'Generic (OpenAI-compatible)', 'LM Studio', 'Ollama', 'Open Responses', 'TransformersPHP']);
        }
    }

    public function testClearAllResetsFiltersAndQuery()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->set('query', 'mistral');
        $component->call('toggle', ['facet' => 'hosting', 'value' => 'saas']);
        $component->call('clearAll');

        $this->assertSame('', $component->component()->query);
        $this->assertSame([], $component->component()->hostings);
        $this->assertCount(0, $component->render()->crawler()->filter('.platform-active-filters'));
    }

    public function testSearchMatchesNamesAndDescriptions()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->set('query', 'ollama');

        $this->assertSame(['Ollama'], $this->names($component->render()->crawler()));

        $component->set('query', 'music generation');

        $names = $this->names($component->render()->crawler());
        $this->assertContains('MiniMax', $names);
        $this->assertNotContains('Ollama', $names);
    }

    public function testSortByCapabilities()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('sortBy', ['sort' => 'capabilities']);

        $this->assertSame(Sort::Capabilities, $component->component()->sort);

        $max = max(array_map(static fn (PlatformBridge $bridge): int => \count($bridge->capabilities), $this->catalog()->getBridges()));
        $first = $this->names($component->render()->crawler())[0];
        foreach ($this->catalog()->getBridges() as $bridge) {
            if ($first === $bridge->name) {
                $this->assertCount($max, $bridge->capabilities);
            }
        }
    }

    public function testOptionsLeadingNowhereAreDisabled()
    {
        $component = $this->createLiveComponent(PlatformBridgeList::class);
        $component->call('toggle', ['facet' => 'runs', 'value' => 'local']);

        $crawler = $component->render()->crawler();

        $this->assertCount(1, $crawler->filter('button.platform-filter[data-live-facet-param="capability"][data-live-value-param="video-generation"][disabled]'));
        $this->assertCount(0, $crawler->filter('button.platform-filter[data-live-facet-param="runs"][data-live-value-param="local"][disabled]'));
    }

    public function testOptionsAreKeptAsCommaSeparatedListsInTheUrl()
    {
        $list = new PlatformBridgeList($this->catalog());

        $this->assertSame('europe,us', $list->dehydrateOptions(['europe', 'us']));
        $this->assertNull($list->dehydrateOptions([]));
        $this->assertSame(['europe', 'us'], $list->hydrateOptions('europe, us,europe,'));
        $this->assertSame(['europe'], $list->hydrateOptions(['europe', 42]));
        $this->assertSame([], $list->hydrateOptions(null));
        $this->assertNull($list->dehydrateSort(Sort::Popularity));
        $this->assertSame('name', $list->dehydrateSort(Sort::Name));
        $this->assertSame(Sort::Popularity, $list->hydrateSort('unknown'));
        $this->assertNull($list->dehydrateQuery(''));
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('article.platform-card .platform-card-title')->each(static fn (Crawler $node): string => trim($node->text()));
    }

    private function catalog(): PlatformBridgeCatalog
    {
        return static::getContainer()->get(PlatformBridgeCatalog::class);
    }
}
