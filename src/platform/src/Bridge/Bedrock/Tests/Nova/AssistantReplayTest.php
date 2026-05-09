<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Nova;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * End-to-end replay test: feed a fixture Bedrock Nova response into NovaResultConverter,
 * build an assistant message via Message::ofAssistant($result), append the next
 * user/tool turn, and assert the byte-shape of the request that would be sent
 * back to Bedrock on turn 2.
 *
 * Pins the current converse-style shape (`messages: [{role, content: [...blocks]}]`,
 * `system: [{text}]`) and the current "tool-or-text" branching in the assistant
 * normalizer — if either changes, this test will fail and the new shape needs to
 * be confirmed against the actual Bedrock Converse API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantReplayTest extends TestCase
{
    /**
     * @param array<string, mixed> $providerResponse
     * @param array<string, mixed> $expectedReplayPayload
     */
    #[DataProvider('provideReplayScenarios')]
    public function testRoundTrip(array $providerResponse, callable $bagBuilder, array $expectedReplayPayload)
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode($providerResponse),
        ]);
        $result = (new NovaResultConverter())->convert(new RawBedrockResult($invokeResponse));

        $contract = Contract::create([
            new AssistantMessageNormalizer(),
            new MessageBagNormalizer(),
            new ToolCallMessageNormalizer(),
            new ToolNormalizer(),
            new UserMessageNormalizer(),
        ]);

        $bag = $bagBuilder($result);
        $payload = $contract->createRequestPayload(new Nova('nova-pro'), $bag);

        $this->assertEquals($expectedReplayPayload, $payload);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: callable, 2: array<string, mixed>}>
     */
    public static function provideReplayScenarios(): iterable
    {
        yield 'plain text turn replays as a single text content block' => [
            [
                'output' => ['message' => ['content' => [
                    ['text' => 'Paris.'],
                ]]],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is the capital of France?'),
                Message::ofAssistant($result),
                Message::ofUser('And of Germany?'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'What is the capital of France?']]],
                    ['role' => 'assistant', 'content' => [['text' => 'Paris.']]],
                    ['role' => 'user', 'content' => [['text' => 'And of Germany?']]],
                ],
            ],
        ];

        yield 'tool_use response replays as a toolUse block paired with toolResult' => [
            [
                'output' => ['message' => ['content' => [
                    ['text' => 'Calling the clock tool.'],
                    ['toolUse' => [
                        'toolUseId' => 'tooluse_abc',
                        'name' => 'clock',
                        'input' => ['tz' => 'UTC'],
                    ]],
                ]]],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What time is it?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('tooluse_abc', 'clock', ['tz' => 'UTC']), '12:00:00Z'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'What time is it?']]],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'toolUse' => [
                                    'toolUseId' => 'tooluse_abc',
                                    'name' => 'clock',
                                    'input' => ['tz' => 'UTC'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'toolResult' => [
                                    'toolUseId' => 'tooluse_abc',
                                    'content' => [['json' => '12:00:00Z']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'tool_use without arguments emits empty input object' => [
            [
                'output' => ['message' => ['content' => [
                    ['text' => 'Calling.'],
                    ['toolUse' => [
                        'toolUseId' => 'tooluse_xyz',
                        'name' => 'clock',
                        'input' => [],
                    ]],
                ]]],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Time?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('tooluse_xyz', 'clock', []), '12:00:00Z'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Time?']]],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'toolUse' => [
                                    'toolUseId' => 'tooluse_xyz',
                                    'name' => 'clock',
                                    'input' => new \stdClass(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'toolResult' => [
                                    'toolUseId' => 'tooluse_xyz',
                                    'content' => [['json' => '12:00:00Z']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'system message lifts to system field' => [
            [
                'output' => ['message' => ['content' => [
                    ['text' => 'Aye!'],
                ]]],
            ],
            static fn ($result) => new MessageBag(
                Message::forSystem('You are a pirate.'),
                Message::ofUser('Greet me.'),
                Message::ofAssistant($result),
                Message::ofUser('Again!'),
            ),
            [
                'system' => [['text' => 'You are a pirate.']],
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Greet me.']]],
                    ['role' => 'assistant', 'content' => [['text' => 'Aye!']]],
                    ['role' => 'user', 'content' => [['text' => 'Again!']]],
                ],
            ],
        ];
    }
}
