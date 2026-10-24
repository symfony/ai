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
use Symfony\AI\Platform\Bridge\Ollama\Decision;
use Symfony\AI\Platform\Bridge\Ollama\DecisionModelClient;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DecisionModelClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new DecisionModelClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Decision('nimble')));
        $this->assertFalse($client->supports(new Ollama('llama3')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:11434/v1/systemone', $url);
            $this->assertNotContains('Authorization: Bearer', $options['headers']);

            $this->assertJsonStringEqualsJsonString(<<<'JSON'
                {
                    "model": "nimble",
                    "state": "Our checkout has returned 500 errors since 9am.",
                    "questions": {
                        "label": {"type": "choice", "instructions": "Which label fits this ticket?", "criteria": {"billing": "Payments and refunds", "bug": "Software errors"}},
                        "urgency": {"type": "score", "instructions": "How urgent is it?", "criteria": ["Routine", "Immediate"]}
                    }
                }
                JSON, $options['body']);

            return new MockResponse();
        }], 'http://127.0.0.1:11434');

        (new DecisionModelClient($httpClient))->request(new Decision('nimble'), [
            'state' => 'Our checkout has returned 500 errors since 9am.',
            'questions' => [
                'label' => ['type' => 'choice', 'instructions' => 'Which label fits this ticket?', 'criteria' => ['billing' => 'Payments and refunds', 'bug' => 'Software errors']],
                'urgency' => ['type' => 'score', 'instructions' => 'How urgent is it?', 'criteria' => ['Routine', 'Immediate']],
            ],
        ]);
    }

    public function testItKeepsNumericKeysAsJsonObjects()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertStringContainsString('"questions":{"0":{"type":"choice","instructions":"Which?","criteria":{"0":"First","1":null}}}', $options['body']);

            return new MockResponse();
        }], 'http://127.0.0.1:11434');

        (new DecisionModelClient($httpClient))->request(new Decision('nimble'), [
            'state' => 'text',
            'questions' => [
                ['type' => 'choice', 'instructions' => 'Which?', 'criteria' => ['First', null]],
            ],
        ]);
    }

    public function testItRejectsAPayloadWithoutQuestions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        (new DecisionModelClient(new MockHttpClient()))->request(new Decision('nimble'), ['state' => 'text']);
    }

    public function testItRejectsANonArrayQuestion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "label" must be an array, "string" given.');

        (new DecisionModelClient(new MockHttpClient()))->request(new Decision('nimble'), ['state' => 'text', 'questions' => ['label' => 'nope']]);
    }
}
