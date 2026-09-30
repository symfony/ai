<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\PlatformBridge\PlatformBridgeCatalog;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class PlatformBridgeControllerTest extends WebTestCase
{
    public function testThePageListsEveryPlatformBridge()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/bridges/platforms');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Platform Bridges');
        $this->assertCount(\count(static::getContainer()->get(PlatformBridgeCatalog::class)->getBridges()), $crawler->filter('article.platform-card'));
        // the page holds nothing personal, shared caches can serve it
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=600, public');
        $this->assertResponseNotHasHeader('Set-Cookie');
        // the icons are rendered once, outside of the Live Component
        $this->assertCount(1, $crawler->filter('svg symbol#pb-icon-tabler-copy'));
        $this->assertCount(0, $crawler->filter('[data-controller~="live"] symbol'));
    }

    public function testFiltersAreReadFromTheQueryString()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/bridges/platforms?region=europe&capability=embeddings,chat');

        $this->assertResponseIsSuccessful();
        $names = $this->names($crawler);
        $this->assertContains('Mistral AI', $names);
        $this->assertContains('Scaleway Generative APIs', $names);
        $this->assertNotContains('Anthropic', $names);
        $this->assertNotContains('Voyage AI', $names, 'Voyage does not chat.');
        // active filters follow the order of the facets, then the order of the query string
        $this->assertSame(['Capabilities Embeddings', 'Capabilities Chat', 'Data region Europe'], $crawler->filter('.platform-chip')->each(static fn (Crawler $node): string => preg_replace('/\s+/', ' ', trim($node->text()))));
    }

    public function testRemovedFiltersOfOldLinksAreIgnored()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/bridges/platforms?capability=streaming,chat&hq=europe&integration=no-sdk');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Capabilities Chat'], $crawler->filter('.platform-chip')->each(static fn (Crawler $node): string => preg_replace('/\s+/', ' ', trim($node->text()))));
        $this->assertContains('Anthropic', $this->names($crawler));
    }

    public function testUnknownFiltersAreIgnored()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/bridges/platforms?region=mars&kind[]=spaceship&sort=random');

        $this->assertResponseIsSuccessful();
        $this->assertCount(\count(static::getContainer()->get(PlatformBridgeCatalog::class)->getBridges()), $crawler->filter('article.platform-card'));
    }

    public function testPresetsLinkToTheirFilters()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/bridges/platforms');

        $this->assertSame('/bridges/platforms?runs=local', $crawler->filter('a.platform-preset[data-live-preset-param="offline"]')->attr('href'));
    }

    public function testTheHomepageLinksToThePage()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('#bridges a[href^="/bridges/platforms"]')->count());
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('article.platform-card .platform-card-title')->each(static fn (Crawler $node): string => trim($node->text()));
    }
}
