<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\HuggingFaceContract;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline\ModelClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testSupportsPipelineModelsOnly()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->assertTrue($modelClient->supports(new Classification('test/model')));
        $this->assertTrue($modelClient->supports(new Embeddings('test/model')));
        $this->assertFalse($modelClient->supports(new Completions('test/model')));
        $this->assertFalse($modelClient->supports(new Model('test/model')));
    }

    public function testSendsStringInputToTheModelOfTheInferenceService()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new Classification('ProsusAI/finbert'), 'I like you.');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://router.huggingface.co/hf-inference/models/ProsusAI/finbert', $response->getRequestUrl());
        $this->assertContains('Authorization: Bearer test-api-key', $response->getRequestOptions()['headers']);
        $this->assertContains('Content-Type: application/json', $response->getRequestOptions()['headers']);
        $this->assertSame(['inputs' => 'I like you.'], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testSendsOptionsAsParameters()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new ZeroShotClassification('facebook/bart-large-mnli'), 'Hi there!', ['candidate_labels' => ['greeting', 'farewell']]);

        $this->assertSame([
            'inputs' => 'Hi there!',
            'parameters' => ['candidate_labels' => ['greeting', 'farewell']],
        ], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testSendsRerankingQueryAndTextsAsInputs()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new Reranker('cross-encoder/ms-marco-MiniLM-L6-v2'), ['query' => 'What is PHP?', 'texts' => ['PHP is a language', 'Bananas are yellow']]);

        $this->assertSame('https://router.huggingface.co/hf-inference/models/cross-encoder/ms-marco-MiniLM-L6-v2', $response->getRequestUrl());
        $this->assertSame([
            'inputs' => ['query' => 'What is PHP?', 'texts' => ['PHP is a language', 'Bananas are yellow']],
        ], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testRequestsEmbeddingsFromTheFeatureExtractionPipeline()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new Embeddings('sentence-transformers/all-MiniLM-L6-v2'), ['Hello', 'World']);

        $this->assertSame('https://router.huggingface.co/hf-inference/models/sentence-transformers/all-MiniLM-L6-v2/pipeline/feature-extraction', $response->getRequestUrl());
        $this->assertSame(['inputs' => ['Hello', 'World']], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testSendsFilesAsBinaryBody()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');
        $model = new Classification('google/vit-base-patch16-224');

        $payload = HuggingFaceContract::create()->createRequestPayload($model, Image::fromDataUrl('data:image/png;base64,'.base64_encode('fake-image')));
        $modelClient->request($model, $payload);

        $this->assertSame('fake-image', $response->getRequestOptions()['body']);
        $this->assertContains('Content-Type: image/png', $response->getRequestOptions()['headers']);
    }

    public function testUsesCustomBaseUrlWithoutTrailingSlash()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key', 'https://hf.example.com/');

        $modelClient->request(new Classification('ProsusAI/finbert'), 'I like you.');

        $this->assertSame('https://hf.example.com/hf-inference/models/ProsusAI/finbert', $response->getRequestUrl());
    }

    public function testReplacesMalformedUtf8InInputs()
    {
        $response = new MockResponse('[]');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new Classification('ProsusAI/finbert'), "tool output \xB1 here");

        $this->assertJson($response->getRequestOptions()['body']);
        $this->assertStringContainsString('tool output \ufffd here', $response->getRequestOptions()['body']);
    }
}
