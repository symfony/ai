<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Wikipedia\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class WikipediaTest extends TestCase
{
    public function testSearchWithResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-result.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->search('current secretary of the united nations');
        $expected = <<<EOT
            Articles with the following titles were found on Wikipedia:
             - Under-Secretary-General of the United Nations
             - United Nations secretary-general selection
             - List of current permanent representatives to the United Nations
             - United Nations
             - United Nations Secretariat
             - Flag of the United Nations
             - List of current members of the United States House of Representatives
             - Member states of the United Nations
             - Official languages of the United Nations
             - United States Secretary of State

            Use the title of the article with tool "wikipedia_article" to load the content.
            EOT;

        $this->assertSame($expected, $actual);
    }

    public function testSearchWithoutResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-empty.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->search('weird questions without results');
        $expected = 'No articles were found on Wikipedia.';

        $this->assertSame($expected, $actual);
    }

    public function testArticleWithResult()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/article.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('Secretary-General of the United Nations');
        $expected = <<<EOT
            This is the content of article "Secretary-General of the United Nations":
            The secretary-general of the United Nations (UNSG or UNSECGEN) is the chief administrative officer of the United Nations and head of the United Nations Secretariat, one of the six principal organs of the United Nations. And so on.
            EOT;

        $this->assertSame($expected, $actual);
    }

    public function testArticleWithRedirect()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/article-redirect.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('United Nations secretary-general');
        $expected = <<<EOT
            The article "United Nations secretary-general" redirects to article "Secretary-General of the United Nations".

            This is the content of article "Secretary-General of the United Nations":
            The secretary-general of the United Nations (UNSG or UNSECGEN) is the chief administrative officer of the United Nations and head of the United Nations Secretariat, one of the six principal organs of the United Nations. And so on.
            EOT;

        $this->assertSame($expected, $actual);
    }

    public function testArticleMissing()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/article-missing.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('Blah blah blah');
        $expected = 'No article with title "Blah blah blah" was found on Wikipedia.';

        $this->assertSame($expected, $actual);
    }
}
