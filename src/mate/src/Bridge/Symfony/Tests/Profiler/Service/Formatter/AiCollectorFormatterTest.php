<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Profiler\DataCollector as AiDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\AiCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AiCollectorFormatterTest extends TestCase
{
    private AiCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new AiCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('ai', $this->formatter->getName());
    }

    public function testGetSummary()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[]],
            'tools' => [[], []],
            'tool_calls' => [[]],
            'messages' => [[]],
            'chats' => [[]],
            'agents' => [[]],
            'stores' => [[], []],
        ]);

        $summary = $this->formatter->getSummary($collector);

        $this->assertSame([
            'platform_call_count' => 1,
            'tool_count' => 2,
            'tool_call_count' => 1,
            'message_count' => 1,
            'chat_count' => 1,
            'agent_call_count' => 1,
            'store_call_count' => 2,
        ], $summary);
    }

    public function testFormatNormalizesAiCollectorData()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[
                'model' => 'gpt-4.1',
                'input' => $this->createMessageBag(),
                'options' => [
                    'stream' => false,
                    'tools' => [$this->createTool()],
                ],
                'result_type' => 'tool_calls',
                'result' => [$this->createToolCall('call-2', 'search_docs', ['query' => 'formatter'])],
                'metadata' => $this->createMetadata(['request_id' => 'req-123']),
            ]],
            'tools' => [$this->createTool()],
            'tool_calls' => [$this->createToolResult()],
            'messages' => [[
                'bag' => $this->createMessageBag(),
                'saved_at' => new \DateTimeImmutable('2026-04-17T10:00:00+00:00'),
            ]],
            'chats' => [[
                'action' => 'submit',
                'message' => $this->createUserMessage(),
                'submitted_at' => new \DateTimeImmutable('2026-04-17T10:01:00+00:00'),
            ]],
            'agents' => [[
                'messages' => $this->createMessageBag(),
                'options' => ['temperature' => 0.2],
                'called_at' => new \DateTimeImmutable('2026-04-17T10:02:00+00:00'),
            ]],
            'stores' => [[
                'method' => 'query',
                'query' => $this->createTextQuery(),
                'options' => ['limit' => 5],
                'called_at' => new \DateTimeImmutable('2026-04-17T10:03:00+00:00'),
            ]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['platform_call_count']);
        $this->assertSame(1, $result['tool_count']);
        $this->assertSame(1, $result['tool_call_count']);
        $this->assertSame(1, $result['message_count']);
        $this->assertSame(1, $result['chat_count']);
        $this->assertSame(1, $result['agent_call_count']);
        $this->assertSame(1, $result['store_call_count']);

        $this->assertSame('gpt-4.1', $result['platform_calls'][0]['model']);
        $this->assertSame('tool_calls', $result['platform_calls'][0]['result_type']);
        $this->assertSame('user', $result['platform_calls'][0]['input']['messages'][0]['role']);
        $this->assertSame('assistant', $result['platform_calls'][0]['input']['messages'][1]['role']);
        $this->assertSame('search_docs', $result['platform_calls'][0]['options']['tools'][0]['name']);
        $this->assertSame('req-123', $result['platform_calls'][0]['metadata']['request_id']);

        $this->assertSame('search_docs', $result['tools'][0]['name']);
        $this->assertSame('App\\Tool\\SearchTool', $result['tools'][0]['reference']['class']);

        $this->assertSame('search_docs', $result['tool_calls'][0]['tool_call']['name']);
        $this->assertSame('Documentation', $result['tool_calls'][0]['sources'][0]['name']);

        $this->assertSame('2026-04-17T10:00:00+00:00', $result['messages'][0]['saved_at']);
        $this->assertSame(2, $result['messages'][0]['bag']['message_count']);

        $this->assertSame('submit', $result['chats'][0]['action']);
        $this->assertSame('Tell me more', $result['chats'][0]['message']['content'][0]['text']);

        $this->assertSame('2026-04-17T10:02:00+00:00', $result['agents'][0]['called_at']);
        $this->assertSame(0.2, $result['agents'][0]['options']['temperature']);

        $this->assertSame('query', $result['stores'][0]['method']);
        $this->assertSame('text', $result['stores'][0]['query']['type']);
        $this->assertSame(['formatters', 'collectors'], $result['stores'][0]['query']['texts']);
    }

    public function testFormatFallsBackToObjectSummaryForUnknownObjects()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[
                'model' => 'gpt-4.1',
                'input' => new class {
                },
                'options' => [],
                'result_type' => 'text',
                'result' => new class {
                },
                'metadata' => [],
            ]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('object', $result['platform_calls'][0]['input']['type']);
        $this->assertSame('object', $result['platform_calls'][0]['result']['type']);
    }

    public function testFormatReturnsEmptySectionsWhenCollectorDataIsMissing()
    {
        $collector = $this->createAiCollector([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['platform_call_count']);
        $this->assertSame([], $result['platform_calls']);
        $this->assertSame([], $result['tools']);
        $this->assertSame([], $result['tool_calls']);
        $this->assertSame([], $result['messages']);
        $this->assertSame([], $result['chats']);
        $this->assertSame([], $result['agents']);
        $this->assertSame([], $result['stores']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAiCollector(array $data): AiDataCollector
    {
        $collector = (new \ReflectionClass(AiDataCollector::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DataCollector::class, 'data'))->setValue($collector, $data);

        return $collector;
    }

    private function createMessageBag(): object
    {
        return new class {
            public function getId(): object
            {
                return new class {
                    public function toRfc4122(): string
                    {
                        return '0195f64c-1ba3-7b2b-a4ee-c3adf0a9f001';
                    }
                };
            }

            /** @return list<object> */
            public function getMessages(): array
            {
                return [
                    new class {
                        public function getRole(): object
                        {
                            return new class {
                                public string $value = 'user';
                            };
                        }

                        /** @return list<object> */
                        public function getContent(): array
                        {
                            return [
                                new class {
                                    public function getText(): string
                                    {
                                        return 'Find formatter coverage';
                                    }
                                },
                            ];
                        }
                    },
                    new class {
                        public function getRole(): object
                        {
                            return new class {
                                public string $value = 'assistant';
                            };
                        }

                        public function getContent(): string
                        {
                            return 'Calling a tool';
                        }

                        public function hasToolCalls(): bool
                        {
                            return true;
                        }

                        /** @return list<object> */
                        public function getToolCalls(): array
                        {
                            return [
                                new class {
                                    public function getId(): string
                                    {
                                        return 'call-1';
                                    }

                                    public function getName(): string
                                    {
                                        return 'search_docs';
                                    }

                                    /** @return array<string, string> */
                                    public function getArguments(): array
                                    {
                                        return ['query' => 'formatter'];
                                    }
                                },
                            ];
                        }

                        public function hasThinkingContent(): bool
                        {
                            return false;
                        }
                    },
                ];
            }
        };
    }

    private function createTool(): object
    {
        return new class {
            public function getName(): string
            {
                return 'search_docs';
            }

            public function getDescription(): string
            {
                return 'Search project documentation';
            }

            public function getReference(): object
            {
                return new class {
                    public function getClass(): string
                    {
                        return 'App\\Tool\\SearchTool';
                    }

                    public function getMethod(): string
                    {
                        return '__invoke';
                    }
                };
            }

            /** @return array<string, mixed> */
            public function getParameters(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                    ],
                ];
            }
        };
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function createToolCall(string $id, string $name, array $arguments): object
    {
        return new class($id, $name, $arguments) {
            /** @param array<string, mixed> $arguments */
            public function __construct(
                private readonly string $id,
                private readonly string $name,
                private readonly array $arguments,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->name;
            }

            /** @return array<string, mixed> */
            public function getArguments(): array
            {
                return $this->arguments;
            }
        };
    }

    private function createToolResult(): object
    {
        return new class($this->createToolCall('call-3', 'search_docs', ['query' => 'bridge'])) {
            public function __construct(
                private readonly object $toolCall,
            ) {
            }

            public function getToolCall(): object
            {
                return $this->toolCall;
            }

            /** @return array<string, int> */
            public function getResult(): array
            {
                return ['matches' => 4];
            }

            public function getSources(): object
            {
                return new class {
                    /** @return list<object> */
                    public function all(): array
                    {
                        return [
                            new class {
                                public function getName(): string
                                {
                                    return 'Documentation';
                                }

                                public function getReference(): string
                                {
                                    return 'docs/components/mate.rst';
                                }

                                public function getContent(): string
                                {
                                    return 'Collector formatter guidance';
                                }
                            },
                        ];
                    }
                };
            }
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createMetadata(array $values): object
    {
        return new class($values) {
            /** @param array<string, mixed> $values */
            public function __construct(
                private readonly array $values,
            ) {
            }

            /** @return array<string, mixed> */
            public function all(): array
            {
                return $this->values;
            }
        };
    }

    private function createUserMessage(): object
    {
        return new class {
            public function getRole(): object
            {
                return new class {
                    public string $value = 'user';
                };
            }

            /** @return list<object> */
            public function getContent(): array
            {
                return [
                    new class {
                        public function getText(): string
                        {
                            return 'Tell me more';
                        }
                    },
                ];
            }
        };
    }

    private function createTextQuery(): object
    {
        return new class {
            /** @return list<string> */
            public function getTexts(): array
            {
                return ['formatters', 'collectors'];
            }

            public function getText(): string
            {
                return 'formatters collectors';
            }
        };
    }
}
