<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Movie;

use App\DataFixtures\MovieFixtures;
use App\Movies\Answer;
use App\Movies\AnswerHandler;
use App\Movies\Data\MovieAnswer;
use App\Movies\Data\MovieSuggestion;
use App\Movies\MovieRepository;
use App\Movies\Suggestions;
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
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\UuidV7;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AnswerHandlerTest extends TestCase
{
    private const CONVERSATION_ID = '019a1b2c-3d4e-7f00-8a00-000000000002';
    private const TOPIC = 'https://symfony.com/ai/demo/movies/topic-1';

    /**
     * Stand-ins for the Turbo Streams, so the published updates can be compared as plain text.
     */
    private const TEMPLATE = <<<'TWIG'
        {% block step_added %}added: {{ step.type }} {{ step.type == 'tool' ? step.name ~ ' ' ~ step.arguments|json_encode : step.text }}{% endblock %}
        {% block step_updated %}updated: {{ step.text }}{% endblock %}
        {% block answer_updated %}answer: {{ answer }} [{{ suggestions|map(s => s.movie.title)|join(', ') }}]{% endblock %}
        {% block finished %}finished: {{ message.asText }} [{{ suggestions|map(s => s.movie.title)|join(', ') }}]{% endblock %}
        TWIG;

    /**
     * @var list<Update>
     */
    private array $published = [];

    public function testStepsAndPartialAnswersArePublished()
    {
        $store = $this->conversationWith(Message::ofUser('Movies by Christopher Nolan'));

        $handler = $this->createHandler($store, [
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryStart()),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta('Searching')),
            new Progress(Progress::STAGE_DELTA, payload: new CommentaryDelta(' his movies.')),
            new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'search_by_director', ['director' => 'Christopher Nolan'])),
            new Progress(Progress::STAGE_DELTA, payload: new PartialObjectDelta($this->answer('Here'), '')),
            new Progress(Progress::STAGE_DELTA, payload: new PartialObjectDelta($this->answer('Here is one.', ['1']), '')),
            new Progress(Progress::STAGE_DELTA, payload: new PartialObjectDelta($this->answer('Here is one.', ['1' => 'Mind-bending.']), '')),
            new Result(new ObjectResult($this->answer('Here is one.', ['1' => 'Mind-bending.'])), $this->load($store)->with(Message::ofAssistant('{"answer": "Here is one."}'))),
        ]);

        $handler(new Answer(self::CONVERSATION_ID, self::TOPIC));

        $this->assertSame([
            'added: commentary ',
            'updated: Searching',
            'updated: Searching his movies.',
            'added: tool search_by_director {"director":"Christopher Nolan"}',
            'answer: Here []',
            // the suggestion is still streamed, its id may not be complete yet
            'answer: Here is one. []',
            'answer: Here is one. [Inception]',
            'finished: Here is one. [Inception]',
        ], array_map(static fn (Update $update): string => $update->getData(), $this->published));

        foreach ($this->published as $update) {
            $this->assertSame([self::TOPIC], $update->getTopics());
        }

        $response = $this->load($store)->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertSame('Here is one.', $response->asText());
        $this->assertSame([
            ['type' => 'commentary', 'text' => 'Searching his movies.'],
            ['type' => 'tool', 'name' => 'search_by_director', 'arguments' => ['director' => 'Christopher Nolan']],
        ], $response->getMetadata()->get('steps'));
        $this->assertCount(1, $response->getMetadata()->get('movies'));
    }

    public function testAnsweredQuestionIsNotAnsweredAgain()
    {
        $store = $this->conversationWith(Message::ofUser('Movies by Christopher Nolan'), Message::ofAssistant('Inception.'));
        $handler = $this->createHandler($store, static function (): never {
            throw new \LogicException('The agent must not be called.');
        });

        $handler(new Answer(self::CONVERSATION_ID, self::TOPIC));

        $this->assertSame([], $this->published);
        $this->assertCount(2, $this->load($store));
    }

    public function testFailedAnswerEndsWithAnApology()
    {
        $store = $this->conversationWith(Message::ofUser('Movies by Christopher Nolan'));
        $handler = $this->createHandler($store, static function (): \Generator {
            yield new Progress(Progress::STAGE_TOOL_CALL, payload: new ToolCall('call_1', 'search_by_director', ['director' => 'Christopher Nolan']));

            throw new RuntimeException('The database is down.');
        });

        try {
            $handler(new Answer(self::CONVERSATION_ID, self::TOPIC));
            $this->fail('The failure must not be retried.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            $this->assertSame('The database is down.', $exception->getMessage());
        }

        $finished = array_last($this->published);
        $this->assertInstanceOf(Update::class, $finished);
        $this->assertSame('finished: Sorry, I could not search the collection. Please try again. []', $finished->getData());

        $response = $this->load($store)->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $response);
        $this->assertSame([['type' => 'tool', 'name' => 'search_by_director', 'arguments' => ['director' => 'Christopher Nolan']]], $response->getMetadata()->get('steps'));
    }

    /**
     * @param array<string, string>|list<string> $movies reasons by movie id, or ids of suggestions still lacking their reason
     */
    private function answer(string $text, array $movies = []): MovieAnswer
    {
        $answer = new MovieAnswer();
        $answer->answer = $text;

        foreach ($movies as $id => $reason) {
            $suggestion = new MovieSuggestion();
            if (array_is_list($movies)) {
                $suggestion->id = $reason;
            } else {
                $suggestion->id = (string) $id;
                $suggestion->reason = $reason;
            }
            $answer->movies[] = $suggestion;
        }

        return $answer;
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
    private function createHandler(InMemoryMessageStore $store, array|\Closure $updates): AnswerHandler
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
                return 'movies';
            }
        };

        $inception = MovieFixtures::fromFile(__DIR__.'/../../../fixtures/movies/inception.md');
        $movies = $this->createStub(MovieRepository::class);
        $movies->method('find')->willReturnCallback(static fn (mixed $id): ?object => 1 === $id ? $inception : null);

        $hub = new MockHub('https://hub.example.com/.well-known/mercure', new StaticTokenProvider('token'), function (Update $update): string {
            $this->published[] = $update;

            return 'id';
        });

        return new AnswerHandler($store, $agent, new Suggestions($movies), $hub, new Environment(new ArrayLoader(['movies/_answer.html.twig' => self::TEMPLATE]), ['autoescape' => false]));
    }
}
