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

use App\Wikipedia\Conversations;
use App\Wikipedia\Research;
use App\Wikipedia\ResearchHandler;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ResearchHandlerTest extends TestCase
{
    private const CHAT_ID = 'chat-1';

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
        $conversations = $this->conversationWith(Message::ofUser('Who created Symfony?'));
        $answer = new TextResult('Fabien Potencier.');
        $answer->getMetadata()->add('sources', ['Symfony']);

        $handler = $this->createHandler($conversations, [
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryStart()),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta('Let me')),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta(' look.')),
            new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'wikipedia_search', ['query' => 'Symfony'])),
            new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_2', 'wikipedia_article', ['title' => 'Symfony'])),
            new Progress(Progress::STAGE_DELTA, payload: new TextDelta('Fabien')),
            new Progress(Progress::STAGE_DELTA, payload: new TextDelta(' Potencier.')),
            new Result($answer),
        ]);

        $handler(new Research(self::CHAT_ID));

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
            $this->assertSame([Conversations::topic(self::CHAT_ID)], $update->getTopics());
        }

        $response = $conversations->load(self::CHAT_ID)->getMessages()[1];
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
        $conversations = $this->conversationWith(Message::ofUser('Who created Symfony?'), Message::ofAssistant('Fabien Potencier.'));
        $handler = $this->createHandler($conversations, static function (): never {
            throw new \LogicException('The agent must not be called.');
        });

        $handler(new Research(self::CHAT_ID));

        $this->assertSame([], $this->published);
        $this->assertCount(2, $conversations->load(self::CHAT_ID));
    }

    public function testFailedResearchEndsWithAnApology()
    {
        $conversations = $this->conversationWith(Message::ofUser('Who created Symfony?'));
        $handler = $this->createHandler($conversations, static function (): \Generator {
            yield new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'wikipedia_search', ['query' => 'Symfony']));

            throw new RuntimeException('Wikipedia is down.');
        });

        try {
            $handler(new Research(self::CHAT_ID));
            $this->fail('The failure must not be retried.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            $this->assertSame('Wikipedia is down.', $exception->getMessage());
        }

        $finished = array_last($this->published);
        $this->assertInstanceOf(Update::class, $finished);
        $this->assertSame('finished: Sorry, my research failed. Please try again.', $finished->getData());

        $response = $conversations->load(self::CHAT_ID)->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertSame([['type' => 'tool', 'text' => 'Searching Wikipedia for "Symfony"']], $response->getMetadata()->get('steps'));
    }

    private function conversationWith(UserMessage|AssistantMessage ...$messages): Conversations
    {
        $conversations = new Conversations(new ArrayAdapter());
        $conversations->save(self::CHAT_ID, new MessageBag(...$messages));

        return $conversations;
    }

    /**
     * @param list<Progress|Result>|\Closure(): \Generator $updates
     */
    private function createHandler(Conversations $conversations, array|\Closure $updates): ResearchHandler
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

        return new ResearchHandler($conversations, $agent, $hub, new Environment(new ArrayLoader(['wikipedia/_research.html.twig' => self::TEMPLATE]), ['autoescape' => false]));
    }
}
