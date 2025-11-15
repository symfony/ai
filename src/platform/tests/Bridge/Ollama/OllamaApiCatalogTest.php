<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Ollama;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\OllamaApiCatalog;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class OllamaApiCatalogTest extends TestCase
{
    public function testModelCatalogCanReturnModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'capabilities' => ['completion'],
            ]),
        ]);

        $modelCatalog = new OllamaApiCatalog('http://127.0.0.1:11434', $httpClient, new ModelCatalog());

        $model = $modelCatalog->getModel('foo');

        $this->assertInstanceOf(Ollama::class, $model);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
