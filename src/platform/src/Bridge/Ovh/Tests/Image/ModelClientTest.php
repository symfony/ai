<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\Image;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\Image;
use Symfony\AI\Platform\Bridge\Ovh\Image\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsImageModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Image('stable-diffusion-xl-base-v10')));
        $this->assertFalse($client->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/images/generations', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);

            $this->assertSame([
                'model' => 'stable-diffusion-xl-base-v10',
                'prompt' => 'A cat in a spacesuit',
            ], json_decode($options['body'], true));

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new Image('stable-diffusion-xl-base-v10'), 'A cat in a spacesuit');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItKeepsTheModelAndPromptOverOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame([
                'size' => '512x512',
                'model' => 'stable-diffusion-xl-base-v10',
                'prompt' => 'A cat',
            ], json_decode($options['body'], true));

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new Image('stable-diffusion-xl-base-v10'), 'A cat', ['size' => '512x512', 'model' => 'other', 'prompt' => 'other']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://example.com/v1/images/generations', $url);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key', 'https://example.com/'))->request(new Image('stable-diffusion-xl-base-v10'), 'A cat');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsANonStringPrompt()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The image prompt must be a string, but "array" was given');

        $client->request(new Image('stable-diffusion-xl-base-v10'), ['prompt' => 'A cat']);
    }
}
