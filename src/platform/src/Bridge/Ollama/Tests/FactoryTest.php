<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FactoryTest extends TestCase
{
    public function testItEvaluatesQuestionsWithADecisionModel()
    {
        $httpClient = new MockHttpClient([
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://127.0.0.1:11434/api/show', $url);

                return new JsonMockResponse(['capabilities' => ['decision', 'tools', 'thinking', 'completion']]);
            },
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://127.0.0.1:11434/v1/systemone', $url);

                $body = json_decode($options['body'], true);
                $this->assertSame('nimble', $body['model']);
                $this->assertSame('Our checkout has returned 500 errors since 9am.', $body['state']);
                $this->assertSame('noul', $body['questions']['is_urgent']['type']);
                $this->assertSame('choice', $body['questions']['label']['type']);

                return new JsonMockResponse([
                    'model' => 'nimble',
                    'answers' => [
                        'is_urgent' => ['type' => 'noul', 'noul' => 0.97],
                        'label' => ['type' => 'choice', 'choice' => 'bug', 'probabilities' => ['billing' => 0.02, 'bug' => 0.98], 'confidence' => 0.89],
                    ],
                    'usage' => ['input_tokens' => 174, 'output_tokens' => 1],
                ]);
            },
        ]);

        $result = Factory::createPlatform('http://127.0.0.1:11434', httpClient: $httpClient)->invoke('nimble', new Evaluation('Our checkout has returned 500 errors since 9am.', [
            'is_urgent' => new NoulQuestion('Is this urgent?'),
            'label' => new ChoiceQuestion('Which label fits this ticket?', ['billing' => 'Payments and refunds', 'bug' => 'Software errors']),
        ]));

        $answers = $result->asObject();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(0.97, $answers->getNoul('is_urgent')->getProbability());
        $this->assertSame('bug', $answers->getChoice('label')->getChoice());

        $tokenUsage = $result->getMetadata()->get('token_usage');
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(174, $tokenUsage->getPromptTokens());
        $this->assertSame(1, $tokenUsage->getCompletionTokens());
    }

    public function testItEvaluatesImagesWithClef()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['capabilities' => ['decision', 'vision']]),
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://127.0.0.1:11434/v1/systemone', $url);
                $body = json_decode($options['body'], true);
                $this->assertSame('clef-flash', $body['model']);
                $this->assertSame(['caption' => 'Graduating class'], $body['state']);
                $this->assertSame([base64_encode('page image'), base64_encode('crop image')], $body['images']);
                $this->assertSame('noul', $body['questions']['portrait']['type']);

                return new JsonMockResponse(['model' => 'clef-flash', 'answers' => ['portrait' => ['type' => 'noul', 'noul' => 0.98]]]);
            },
        ]);
        $result = Factory::createPlatform('http://127.0.0.1:11434', httpClient: $httpClient)->invoke('clef-flash', new Evaluation(
            ['caption' => 'Graduating class'],
            ['portrait' => new NoulQuestion('Does the crop show a portrait?')],
            images: [new Image('page image', 'image/png'), new Image('crop image', 'image/jpeg')],
        ));

        $this->assertSame(0.98, $result->asObject(Answers::class)->getNoul('portrait')->getProbability());
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testItRejectsImagesForTextOnlyDecisionModels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['capabilities' => ['decision']]),
        ]);

        try {
            Factory::createPlatform('http://127.0.0.1:11434', httpClient: $httpClient)->invoke('nimble', new Evaluation(
                'A photo',
                ['portrait' => new NoulQuestion('Is this a portrait?')],
                images: [new Image('image bytes', 'image/png')],
            ));
            $this->fail('Text-only decision models must reject images.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The decision model "nimble" does not support image inputs.', $e->getMessage());
        }

        // Only model discovery is allowed; no decision request is sent.
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCreatePlatformUsesGivenModelCatalog()
    {
        $model = new Ollama('llama3.2');

        $catalog = $this->createMock(ModelCatalogInterface::class);
        $catalog->expects($this->once())
            ->method('getModel')
            ->with('llama3.2')
            ->willReturn($model);

        $platform = Factory::createPlatform('http://127.0.0.1:11434', httpClient: new MockHttpClient(), modelCatalog: $catalog);

        $this->assertSame($model, $platform->getModelCatalog()->getModel('llama3.2'));
    }

    public function testCreateModelCatalogUsesScopedHttpClient()
    {
        $requestedUrl = '';
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$headers): ResponseInterface {
            $requestedUrl = $url;
            $headers = $options['normalized_headers'] ?? [];

            return new MockResponse('{"models": []}');
        });

        Factory::createModelCatalog('http://127.0.0.1:11434', 'secret', $httpClient)->getModels();

        $this->assertSame('http://127.0.0.1:11434/api/tags', $requestedUrl);
        $this->assertSame(['Authorization: Bearer secret'], $headers['authorization'] ?? []);
    }
}
