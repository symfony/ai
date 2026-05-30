<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Mistral;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Mock\Http\CassetteHttpClient;
use Symfony\AI\Platform\Mock\Http\HttpCassette;

/**
 * Drives the real Mistral bridge pipeline (Contract, ModelClient, Llm\ResultConverter) against a
 * committed cassette — proving record/replay exercises bridge internals offline.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverterCassetteTest extends TestCase
{
    public function testReplaysTextResponseThroughRealConverter()
    {
        $platform = Factory::createPlatform('test-key', new CassetteHttpClient(
            new HttpCassette(__DIR__.'/fixtures/mistral_text_response.json'),
            record: false,
        ));

        $result = $platform->invoke('mistral-large-latest', new MessageBag(Message::ofUser('Hello')));

        $this->assertSame('Hello from Mistral!', $result->asText());
    }

    public function testReplaysToolCallResponseThroughRealConverter()
    {
        $platform = Factory::createPlatform('test-key', new CassetteHttpClient(
            new HttpCassette(__DIR__.'/fixtures/mistral_tool_call_response.json'),
            record: false,
        ));

        $toolCalls = $platform->invoke('mistral-large-latest', new MessageBag(Message::ofUser('Weather in Paris?')))->asToolCalls();

        $this->assertCount(1, $toolCalls);
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());
    }
}
