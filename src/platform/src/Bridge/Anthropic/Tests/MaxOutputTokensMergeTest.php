<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MaxOutputTokensMergeTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideMergeScenarios')]
    public function testModelAndInvocationOptionsResolveToMaxTokens(string $model, array $options, int $expectedMaxTokens)
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $requestOptions) use ($expectedMaxTokens) {
            $body = json_decode($requestOptions['body'], true);

            $this->assertSame($expectedMaxTokens, $body['max_tokens']);
            $this->assertArrayNotHasKey('max_output_tokens', $body);

            return new JsonMockResponse(['content' => []]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);
        $platform->invoke($model, new MessageBag(Message::ofUser('Hello')), $options);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, int}>
     */
    public static function provideMergeScenarios(): iterable
    {
        yield 'model default' => ['claude-sonnet-4-0', [], 1000];
        yield 'canonical option overrides the model default' => ['claude-sonnet-4-0', ['max_output_tokens' => 500], 500];
        yield 'max_tokens overrides the model default' => ['claude-sonnet-4-0', ['max_tokens' => 2000], 2000];
        yield 'max_tokens wins over the canonical option' => ['claude-sonnet-4-0', ['max_tokens' => 2000, 'max_output_tokens' => 500], 2000];
        yield 'canonical option configured on the model' => ['claude-sonnet-4-0?max_output_tokens=500', [], 500];
        yield 'canonical option overrides the one configured on the model' => ['claude-sonnet-4-0?max_output_tokens=500', ['max_output_tokens' => 300], 300];
        yield 'max_tokens overrides the canonical option configured on the model' => ['claude-sonnet-4-0?max_output_tokens=500', ['max_tokens' => 2000], 2000];
        yield 'max_tokens configured on the model' => ['claude-sonnet-4-0?max_tokens=2000', [], 2000];
    }
}
