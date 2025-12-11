<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[CoversNothing]
final class SmokeTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to the Symfony AI Demo');
        $this->assertSelectorCount(8, '.card');
    }

    #[DataProvider('provideChats')]
    public function testChats(string $path, string $expectedHeadline)
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h4', $expectedHeadline);
        $this->assertSelectorCount(1, '#chat-submit');
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideChats(): iterable
    {
        yield 'Blog' => ['/blog', 'Retrieval Augmented Generation based on the Symfony blog'];
        yield 'Recipe' => ['/recipe', 'Cooking Recipes'];
        yield 'Wikipedia' => ['/wikipedia', 'Wikipedia Research'];
        yield 'YouTube' => ['/youtube', 'Chat about a YouTube Video'];
    }
}
