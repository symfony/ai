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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CookbookControllerTest extends WebTestCase
{
    public function testHomepageListsCookbookArticles()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#cookbook h2', 'Cookbook');
        $this->assertGreaterThan(0, $crawler->filter('#cookbook a[href^="/cookbook/"]')->count());
    }

    public function testArticleRendersGeneratedBody()
    {
        $client = static::createClient();
        $client->request('GET', '/cookbook/rag-implementation');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Build a RAG Pipeline');
        // body comes from the generated RST fragment
        $this->assertSelectorExists('.cookbook-article-body .section');
    }

    public function testUnknownArticleReturns404()
    {
        $client = static::createClient();
        $client->request('GET', '/cookbook/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testArticleBreadcrumbLinksToTheHomepageCookbookSection()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cookbook/rag-implementation');

        $this->assertResponseIsSuccessful();
        $hrefs = $crawler->filter('.cookbook-breadcrumb a')->each(static fn ($node) => $node->attr('href'));
        $this->assertContains('/#cookbook', $hrefs);
    }
}
