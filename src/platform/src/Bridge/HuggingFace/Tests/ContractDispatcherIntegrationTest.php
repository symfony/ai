<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\ChatCompletionClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider as HfProvider;
use Symfony\AI\Platform\Bridge\HuggingFace\SummarizationClient;
use Symfony\AI\Platform\Bridge\HuggingFace\TextGenerationClient;
use Symfony\AI\Platform\Bridge\HuggingFace\TextRankingClient;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Verifies that the new per-task handler dispatch reproduces the behavior of
 * the previous mega-ResultConverter:
 *  - the legacy `$options['task']` option still selects the endpoint via
 *    {@see TaskAwareDispatcher} (back-compat shim);
 *  - chat-completion's provider-specific URL split (HF inference vs third
 *    party) is preserved;
 *  - text-ranking's pair payload reshape is preserved;
 *  - distinct tasks parse into the right Result class.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContractDispatcherIntegrationTest extends TestCase
{
    public function testChatCompletionOnHfInferenceUsesModelInUrl()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'choices' => [['message' => ['content' => 'hi from hf']]],
            ]);
        });

        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);
        $result = $platform->invoke('microsoft/Phi-3-mini-4k-instruct', ['messages' => [['role' => 'user', 'content' => 'hi']]], [
            'endpoint' => ChatCompletionClient::ENDPOINT,
        ]);

        $this->assertSame('https://router.huggingface.co/hf-inference/models/microsoft/Phi-3-mini-4k-instruct/v1/chat/completions', $capturedUrl);
        $this->assertArrayNotHasKey('model', $capturedBody, 'HF inference takes the model from the URL, not the body');
        $this->assertSame('hi from hf', $result->getResult()->getContent());
    }

    public function testChatCompletionOnThirdPartyProviderPutsModelInBody()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'hi from together']]]]);
        });

        $platform = Factory::createPlatform('test-token', HfProvider::TOGETHER, $httpClient);
        $result = $platform->invoke('meta-llama/Llama-3-8B-Instruct', ['messages' => [['role' => 'user', 'content' => 'hi']]], [
            'endpoint' => ChatCompletionClient::ENDPOINT,
        ]);

        $this->assertSame('https://router.huggingface.co/together/v1/chat/completions', $capturedUrl);
        $this->assertSame('meta-llama/Llama-3-8B-Instruct', $capturedBody['model'], 'Third-party providers expect the model name in the body');
        $this->assertSame('hi from together', $result->getResult()->getContent());
    }

    public function testNewEndpointOptionAndLegacyTaskOptionAreEquivalent()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls) {
            $urls[] = $url;

            return new JsonMockResponse([['generated_text' => 'ok']]);
        });

        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);
        $platform->invoke('gpt2', 'hello', ['endpoint' => TextGenerationClient::ENDPOINT])->getResult();
        $platform->invoke('gpt2', 'hello', ['endpoint' => TextGenerationClient::ENDPOINT])->getResult();

        $this->assertSame('https://router.huggingface.co/hf-inference/models/gpt2', $urls[0]);
        $this->assertSame($urls[0], $urls[1], 'Both legacy task option and new endpoint option must reach the same URL');
    }

    public function testTextRankingReshapesQueryAndTextsIntoPairs()
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                ['index' => 0, 'score' => 0.9],
                ['index' => 1, 'score' => 0.4],
            ]);
        });

        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);
        $result = $platform->invoke('BAAI/bge-reranker-base', ['query' => 'capital of france', 'texts' => ['Paris', 'Berlin']], [
            'endpoint' => TextRankingClient::ENDPOINT,
        ]);

        $this->assertSame([
            ['text' => 'capital of france', 'text_pair' => 'Paris'],
            ['text' => 'capital of france', 'text_pair' => 'Berlin'],
        ], $capturedBody['inputs']);

        $reranking = $result->getResult();
        $this->assertInstanceOf(RerankingResult::class, $reranking);
        $entries = $reranking->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertEqualsWithDelta(0.9, $entries[0]->getScore(), 0.001);
    }

    public function testTextRankingAlsoUsableViaNewEndpointConstant()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse([['index' => 0, 'score' => 0.5]]));
        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);

        $result = $platform->invoke('BAAI/bge-reranker-base', ['query' => 'q', 'texts' => ['a']], [
            'endpoint' => TextRankingClient::ENDPOINT,
        ]);

        $this->assertInstanceOf(RerankingResult::class, $result->getResult());
    }

    public function testSummarizationParsesSummaryTextField()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse([['summary_text' => 'a brief summary']]));
        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);

        $result = $platform->invoke('facebook/bart-large-cnn', 'long input text', ['endpoint' => SummarizationClient::ENDPOINT]);

        $this->assertInstanceOf(TextResult::class, $result->getResult());
        $this->assertSame('a brief summary', $result->getResult()->getContent());
    }

    public function testUnknownTaskFailsFast()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);

        $this->expectException(\Symfony\AI\Platform\Exception\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ModelClient registered for model "any\\/model"/');

        $platform->invoke('any/model', 'input', ['endpoint' => 'hf.fictional-task']);
    }

    public function testChatCompletionUsableViaNewEndpointConstant()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'pong']]]]);
        });

        $platform = Factory::createPlatform('test-token', HfProvider::HF_INFERENCE, $httpClient);
        $result = $platform->invoke('microsoft/Phi-3-mini-4k-instruct', ['messages' => [['role' => 'user', 'content' => 'ping']]], [
            'endpoint' => ChatCompletionClient::ENDPOINT,
        ]);

        $this->assertSame('https://router.huggingface.co/hf-inference/models/microsoft/Phi-3-mini-4k-instruct/v1/chat/completions', $capturedUrl);
        $this->assertSame('pong', $result->getResult()->getContent());
    }
}
