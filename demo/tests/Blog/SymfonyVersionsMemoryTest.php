<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Blog;

use App\Blog\SymfonyVersionsMemory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class SymfonyVersionsMemoryTest extends TestCase
{
    public function testLoadReturnsSymfonyVersionMemory()
    {
        $httpClient = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/versions.json'));
        $versionMemory = new SymfonyVersionsMemory($httpClient, new NullAdapter());
        $input = new Input('gpt-6', new MessageBag());

        $memories = $versionMemory->load($input);

        $this->assertCount(1, $memories);
        $this->assertInstanceOf(Memory::class, $memories[0]);

        $content = $memories[0]->getContent();
        $this->assertStringContainsString('LTS: 7.4.2', $content);
        $this->assertStringContainsString('Latest: 8.0.2', $content);
        $this->assertStringContainsString('In development: 8.1.0-DEV', $content);
    }
}
