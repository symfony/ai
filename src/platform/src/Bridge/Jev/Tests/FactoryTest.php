<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Jev\Factory;
use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FactoryTest extends TestCase
{
    public function testCreateWithDefaults()
    {
        $platform = Factory::createPlatform('test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testInvokeConvertsChoiceResponse()
    {
        $body = file_get_contents(__DIR__.'/Fixtures/choice.json');
        $httpClient = new MockHttpClient([
            function (string $method, string $url, array $options) use ($body): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.typesafe.ai/v1/systemone', $url);
                $this->assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
                $payload = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
                $this->assertSame('jev-latest', $payload['model']);
                $this->assertSame('Help! My payouts have been failing for 3 days.', $payload['state']);
                $this->assertArrayHasKey('department', $payload['questions']);

                return new MockResponse($body, ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
            },
        ]);

        $platform = Factory::createPlatform('test-api-key', $httpClient);
        $result = $platform->invoke('jev-latest', 'Help! My payouts have been failing for 3 days.', [
            'questions' => [
                'department' => [
                    'type' => 'choice',
                    'instructions' => 'Which team should handle this?',
                    'criteria' => [
                        'billing' => 'Payments, invoicing, refunds',
                        'technical' => 'Bugs, outages, integrations',
                        'sales' => 'Pricing, upgrades, new accounts',
                    ],
                ],
            ],
        ])->asObject();

        $this->assertInstanceOf(EvaluationResult::class, $result);
        $this->assertSame('billing', $result->getChoice('department'));
        $this->assertSame(0.81, $result->getConfidence('department'));
        $this->assertSame(0.88, $result->getProbabilities('department')['billing']);
    }
}
