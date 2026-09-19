<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class FactoryTest extends TestCase
{
    public function testItCreatesProviderWithDefaultName()
    {
        $this->assertSame('typesafe', Factory::createProvider('test-key', new MockHttpClient())->getName());
    }

    public function testItCreatesProviderWithCustomName()
    {
        $this->assertSame('typesafe-eu', Factory::createProvider('test-key', new MockHttpClient(), name: 'typesafe-eu')->getName());
    }

    public function testItEvaluatesQuestions()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): JsonMockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('jev-latest', $body['model']);
            $this->assertSame('My payouts have been failing for 3 days.', $body['state']);
            $this->assertSame(['is_urgent', 'department'], array_keys($body['questions']));

            return new JsonMockResponse([
                'model' => 'jev-1.13.0',
                'answers' => [
                    'is_urgent' => ['type' => 'noul', 'noul' => 0.92],
                    'department' => ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.15, 'technical' => 0.85], 'confidence' => 0.82],
                ],
                'usage' => ['input_tokens' => 312, 'output_tokens' => 48],
            ]);
        }]);

        $result = Factory::createPlatform('test-key', $httpClient)->invoke('jev-latest', new Evaluation('My payouts have been failing for 3 days.', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => 'Bugs']),
        ]));

        $answers = $result->asObject();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertTrue($answers->getNoul('is_urgent')->isTrue());
        $this->assertSame('technical', $answers->getChoice('department')->getChoice());

        $tokenUsage = $result->getMetadata()->get('token_usage');
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(312, $tokenUsage->getPromptTokens());
    }

    public function testItEvaluatesPlainArrayInput()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'model' => 'jev-1.13.0',
            'answers' => ['is_urgent' => ['type' => 'noul', 'noul' => 0.08]],
        ]));

        $result = Factory::createPlatform('test-key', $httpClient)->invoke('jev-latest', [
            'state' => 'Thanks, everything works now.',
            'questions' => ['is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?']],
        ]);

        $answers = $result->asObject();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertFalse($answers->getNoul('is_urgent')->isTrue());
    }
}
