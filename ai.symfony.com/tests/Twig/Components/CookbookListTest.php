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

use App\Twig\Components\CookbookList;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class CookbookListTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testRendersAllArticlesByDefault()
    {
        $titles = $this->titles($this->createLiveComponent(CookbookList::class)->render()->crawler());

        $this->assertContains('Build an MCP Server', $titles);
        $this->assertContains('Streaming Responses', $titles);
    }

    public function testFilteringByTagShowsOnlyMatchingArticles()
    {
        $component = $this->createLiveComponent(CookbookList::class);

        // "MCP" must also match the "MCP Bundle" component
        $component->call('toggleTag', ['tag' => 'MCP']);

        $titles = $this->titles($component->render()->crawler());

        $this->assertContains('Build an MCP Server', $titles);
        $this->assertNotContains('Streaming Responses', $titles);
    }

    public function testTogglingTheSameTagTwiceResetsTheFilter()
    {
        $component = $this->createLiveComponent(CookbookList::class);
        $component->call('toggleTag', ['tag' => 'MCP']);
        $component->call('toggleTag', ['tag' => 'MCP']);

        $this->assertContains('Streaming Responses', $this->titles($component->render()->crawler()));
    }

    public function testUnknownTagIsIgnored()
    {
        $component = $this->createLiveComponent(CookbookList::class);
        $component->call('toggleTag', ['tag' => 'Bogus']);

        $this->assertSame([], $component->component()->activeTags);
    }

    /**
     * @return list<string>
     */
    private function titles(Crawler $crawler): array
    {
        return $crawler->filter('article h3')->each(static fn (Crawler $node): string => trim($node->text()));
    }
}
