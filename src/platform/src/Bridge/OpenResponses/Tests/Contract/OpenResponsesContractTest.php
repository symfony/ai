<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\OpenResponsesContract;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class OpenResponsesContractTest extends TestCase
{
    /**
     * Assistant messages must be serialized with `content` as a list of content
     * parts - strict Responses API implementations (e.g. litellm) reject a raw string.
     *
     * @see https://github.com/symfony/ai/issues/2346
     */
    public function testAssistantMessageContentIsSerializedAsContentParts()
    {
        $contract = OpenResponsesContract::create();

        $assistantMessage = Message::ofAssistant('Wie kann ich dir helfen?');
        $messages = new MessageBag(
            Message::forSystem('You are a helpful assistant.'),
            $assistantMessage,
            Message::ofUser('Hallo!'),
        );

        $payload = $contract->createRequestPayload(new ResponsesModel('test-model'), $messages);

        $this->assertSame([
            'input' => [
                [
                    'role' => 'assistant',
                    'type' => 'message',
                    'id' => 'msg_'.str_replace('-', '', $assistantMessage->getId()->toRfc4122()),
                    'status' => 'completed',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Wie kann ich dir helfen?', 'annotations' => []],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => 'Hallo!',
                ],
            ],
            'instructions' => 'You are a helpful assistant.',
        ], $payload);
    }
}
