<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Wikipedia;

use App\Wikipedia\Research;
use App\Wikipedia\ResearchHandler;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\UuidV7;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ResearchHandlerTest extends TestCase
{
    private const CONVERSATION_ID = '019a1b2c-3d4e-7f00-8a00-000000000001';
    private const TOPIC = 'https://symfony.com/ai/demo/wikipedia/topic-1';

    /**
     * Stand-ins for the Turbo Streams, so the published updates can be compared as plain text.
     */
    private const TEMPLATE = <<<'TWIG'
        {% block step_added %}added {{ index }}: {{ step.type }} {{ step.text }}{% endblock %}
        {% block step_updated %}updated {{ index }}: {{ step.text }}{% endblock %}
        {% block answer_updated %}answer: {{ answer }}{% endblock %}
        {% block finished %}finished: {{ message.asText }}{% endblock %}
        TWIG;

    /**
     * @var list<Update>
     */
    private array $published = [];

    public function testEveryStepOfTheResearchIsPublished()
    {
        $store = $this->conversationWith(Message::ofUser('Who created Symfony?'));
        $answer = Message::ofAssistant('Fabien Potencier.');
        $answer->getMetadata()->add('sources', ['Symfony']);

        $handler = $this->createHandler($store, [
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryStart()),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta('Let me')),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta(' look.')),
            new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'wikipedia_search', ['query' => 'Symfony'])),
            new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_2', 'wikipedia_article', ['title' => 'Symfony'])),
            new Progress(Progress::STAGE_DELTA, payload: new TextDelta('Fabien')),
            new Progress(Progress::STAGE_DELTA, payload: new TextDelta(' Potencier.')),
            new Result(new TextResult('Fabien Potencier.'), $this->load($store)->with($answer)),
        ]);

        $handler(new Research(self::CONVERSATION_ID, self::TOPIC));

        $this->assertSame([
            'added 0: commentary ',
            'updated 0: Let me',
            'updated 0: Let me look.',
            'added 1: tool Searching Wikipedia for "Symfony"',
            'added 2: tool Reading the article "Symfony"',
            'answer: Fabien',
            'answer: Fabien Potencier.',
            'finished: Fabien Potencier.',
        ], array_map(static fn (Update $update): string => $update->getData(), $this->published));

        foreach ($this->published as $update) {
            $this->assertSame([self::TOPIC], $update->getTopics());
        }

        $response = $this->load($store)->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertSame('Fabien Potencier.', $response->asText());
        $this->assertSame(['Symfony'], $response->getMetadata()->get('sources'));
        $this->assertSame([
            ['type' => 'commentary', 'text' => 'Let me look.'],
            ['type' => 'tool', 'text' => 'Searching Wikipedia for "Symfony"'],
            ['type' => 'tool', 'text' => 'Reading the article "Symfony"'],
        ], $response->getMetadata()->get('steps'));
    }

    public function testAnsweredQuestionIsNotResearchedAgain()
    {
        $store = $this->conversationWith(Message::ofUser('Who created Symfony?'), Message::ofAssistant('Fabien Potencier.'));
        $handler = $this->createHandler($store, static function (): never {
            throw new \LogicException('The agent must not be called.');
        });

        $handler(new Research(self::CONVERSATION_ID, self::TOPIC));

        $this->assertSame([], $this->published);
        $this->assertCount(2, $this->load($store));
    }

    public function testFailedResearchEndsWithAnApology()
    {
        $store = $this->conversationWith(Message::ofUser('Who created Symfony?'));
        $handler = $this->createHandler($store, static function (): \Generator {
            yield new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'wikipedia_search', ['query' => 'Symfony']));

            throw new RuntimeException('Wikipedia is down.');
        });

        try {
            $handler(new Research(self::CONVERSATION_ID, self::TOPIC));
            $this->fail('The failure must not be retried.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            $this->assertSame('Wikipedia is down.', $exception->getMessage());
        }

        $finished = array_last($this->published);
        $this->assertInstanceOf(Update::class, $finished);
        $this->assertSame('finished: Sorry, my research failed. Please try again.', $finished->getData());

        $response = $this->load($store)->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertSame([['type' => 'tool', 'text' => 'Searching Wikipedia for "Symfony"']], $response->getMetadata()->get('steps'));
    }

    private function conversationWith(UserMessage|AssistantMessage ...$messages): InMemoryMessageStore
    {
        $store = new InMemoryMessageStore();
        $store->save(MessageBag::forId(UuidV7::fromString(self::CONVERSATION_ID), ...$messages));

        return $store;
    }

    private function load(InMemoryMessageStore $store): MessageBag
    {
        return $store->load(UuidV7::fromString(self::CONVERSATION_ID));
    }

    /**
     * @param list<Progress|Result>|\Closure(): \Generator $updates
     */
    private function createHandler(InMemoryMessageStore $store, array|\Closure $updates): ResearchHandler
    {
        $factory = \is_array($updates) ? static fn (): \Generator => yield from $updates : $updates;

        $agent = new class($factory) implements AgentInterface {
            public function __construct(
                private readonly \Closure $factory,
            ) {
            }

            public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
            {
                return new Execution($this->factory, $options['stream'] ?? false);
            }

            public function getName(): string
            {
                return 'wikipedia';
            }
        };

        $hub = new MockHub('https://hub.example.com/.well-known/mercure', new StaticTokenProvider('token'), function (Update $update): string {
            $this->published[] = $update;

            return 'id';
        });

        return new ResearchHandler($store, $agent, $hub, new Environment(new ArrayLoader(['wikipedia/_research.html.twig' => self::TEMPLATE]), ['autoescape' => false]));
    }
}
