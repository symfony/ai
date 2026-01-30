<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Ollama\Ollama;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class OllamaTest extends TestCase
{
    public function testWebSearchCanBePerformed()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    [
                        'title' => 'Ollama',
                        'url' => 'https://ollama.com',
                        'content' => 'Cloud models are now available...',
                    ],
                    [
                        'title' => 'What is Ollama? Introduction to the AI model management tool',
                        'url' => 'https://www.hostinger.com/tutorials/what-is-ollama',
                        'content' => 'Ollama is an open-source tool...',
                    ],
                ],
            ]),
        ]);

        $ollama = new Ollama($httpClient, 'foo');

        $response = $ollama->webSearch('Ollama');

        $this->assertSame([
            [
                'title' => 'Ollama',
                'url' => 'https://ollama.com',
                'content' => 'Cloud models are now available...',
            ],
            [
                'title' => 'What is Ollama? Introduction to the AI model management tool',
                'url' => 'https://www.hostinger.com/tutorials/what-is-ollama',
                'content' => 'Ollama is an open-source tool...',
            ],
        ], $response);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testWebSearchCanBePerformedWithMaxResults()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    [
                        'title' => 'Ollama',
                        'url' => 'https://ollama.com',
                        'content' => 'Cloud models are now available...',
                    ],
                ],
            ]),
        ]);

        $ollama = new Ollama($httpClient, 'foo');

        $response = $ollama->webSearch('Ollama', 1);

        $this->assertSame([
            [
                'title' => 'Ollama',
                'url' => 'https://ollama.com',
                'content' => 'Cloud models are now available...',
            ],
        ], $response);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testFetchCanBePerformed()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'title' => 'Ollama',
                'content' => '[Cloud models](https://ollama.com/blog/cloud-models) are now available in Ollama...',
                'links' => [
                    'https://ollama.com/',
                    'https://ollama.com/models',
                    'https://github.com/ollama/ollama',
                ],
            ]),
        ]);

        $ollama = new Ollama($httpClient, 'foo');

        $response = $ollama->fetchWebPage('ollama.com');

        $this->assertSame([
            'title' => 'Ollama',
            'content' => '[Cloud models](https://ollama.com/blog/cloud-models) are now available in Ollama...',
            'links' => [
                'https://ollama.com/',
                'https://ollama.com/models',
                'https://github.com/ollama/ollama',
            ],
        ], $response);

        $this->assertSame(1, $httpClient->getRequestsCount());

        $sources = $ollama->getSourceCollection()->all();
        $this->assertCount(1, $sources);
        $this->assertSame('Ollama', $sources[0]->getName());
        $this->assertSame('[Cloud models](https://ollama.com/blog/cloud-models) are now available in Ollama...', $sources[0]->getContent());
        $this->assertSame('https://ollama.com/, https://ollama.com/models, https://github.com/ollama/ollama', $sources[0]->getReference());
    }
}
