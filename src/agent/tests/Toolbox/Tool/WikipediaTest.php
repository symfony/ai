<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\Wikipedia;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(Wikipedia::class)]
final class WikipediaTest extends TestCase
{
    #[Test]
    public function searchWithResults(): void
    {
        $result = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/wikipedia-search-result.json');
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

        static::assertSame($expected, $actual);
    }

    #[Test]
    public function searchWithoutResults(): void
    {
        $result = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/wikipedia-search-empty.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->search('weird questions without results');
        $expected = 'No articles were found on Wikipedia.';

        static::assertSame($expected, $actual);
    }

    #[Test]
    public function articleWithResult(): void
    {
        $result = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/wikipedia-article.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('Secretary-General of the United Nations');
        $expected = <<<EOT
            This is the content of article "Secretary-General of the United Nations":
            The secretary-general of the United Nations (UNSG or UNSECGEN) is the chief administrative officer of the United Nations and head of the United Nations Secretariat, one of the six principal organs of the United Nations. And so on.
            EOT;

        static::assertSame($expected, $actual);
    }

    #[Test]
    public function articleWithRedirect(): void
    {
        $result = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/wikipedia-article-redirect.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('United Nations secretary-general');
        $expected = <<<EOT
            The article "United Nations secretary-general" redirects to article "Secretary-General of the United Nations".

            This is the content of article "Secretary-General of the United Nations":
            The secretary-general of the United Nations (UNSG or UNSECGEN) is the chief administrative officer of the United Nations and head of the United Nations Secretariat, one of the six principal organs of the United Nations. And so on.
            EOT;

        static::assertSame($expected, $actual);
    }

    #[Test]
    public function articleMissing(): void
    {
        $result = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/wikipedia-article-missing.json');
        $httpClient = new MockHttpClient($result);

        $wikipedia = new Wikipedia($httpClient);

        $actual = $wikipedia->article('Blah blah blah');
        $expected = 'No article with title "Blah blah blah" was found on Wikipedia.';

        static::assertSame($expected, $actual);
    }

    /**
     * This can be replaced by `JsonMockResponse::fromFile` when dropping Symfony 6.4.
     */
    private function jsonMockResponseFromFile(string $file): JsonMockResponse
    {
        return new JsonMockResponse(json_decode(file_get_contents($file), true));
    }
}
