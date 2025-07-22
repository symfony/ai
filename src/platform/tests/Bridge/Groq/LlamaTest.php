<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Groq;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Groq\Llama;
use Symfony\AI\Platform\Bridge\Groq\Llama\ModelClient;
use Symfony\AI\Platform\Bridge\Groq\Llama\ResponseConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LlamaTest extends TestCase
{
    /**
     * @covers \Symfony\AI\Platform\Bridge\Groq\Llama
     */
    public function testModelSupportsExpectedCapabilities(): void
    {
        $model = new Llama();

        $this->assertTrue($model->supports(Capability::INPUT_MESSAGES));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertTrue($model->supports(Capability::OUTPUT_STREAMING));
        $this->assertTrue($model->supports(Capability::TOOL_CALLING));
    }

    /**
     * @covers \Symfony\AI\Platform\Bridge\Groq\Llama\ModelClient
     */
    public function testModelClientSupportsLlamaModel(): void
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'gsk_test');

        $this->assertTrue($modelClient->supports(new Llama()));
        $this->assertFalse($modelClient->supports(new class('test') extends Model {}));
    }

    /**
     * @covers \Symfony\AI\Platform\Bridge\Groq\Llama\ResponseConverter
     */
    public function testResponseConverterSupportsLlamaModel(): void
    {
        $responseConverter = new ResponseConverter();

        $this->assertTrue($responseConverter->supports(new Llama()));
        $this->assertFalse($responseConverter->supports(new class('test') extends Model {}));
    }

    /**
     * @covers \Symfony\AI\Platform\Bridge\Groq\Llama\ModelClient
     * @covers \Symfony\AI\Platform\Bridge\Groq\Llama\ResponseConverter
     */
    public function testModelClientRequest(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{ "choices": [{"message": {"content": "Hello!"}, "finish_reason": "stop"}]}'));
        $modelClient = new ModelClient($httpClient, 'gsk_test');
        $model = new Llama();

        $response = $modelClient->request($model, ['messages' => [['role' => 'user', 'content' => 'Hello']]], []);

        $this->assertSame('Hello!', (new ResponseConverter())->convert($response)->getContent());
    }
}
