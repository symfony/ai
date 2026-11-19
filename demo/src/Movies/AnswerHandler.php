<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies;

use App\Movies\Data\MovieAnswer;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\UuidV7;
use Twig\Environment;

/**
 * Answers in the worker and pushes the commentary, the tool calls and the partially streamed {@see MovieAnswer}
 * to the browser, as Turbo Streams via Mercure.
 *
 * @phpstan-type Step array{type: 'commentary', text: string}|array{type: 'tool', name: string, arguments: array<string, mixed>}
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsMessageHandler]
final class AnswerHandler
{
    private const TEMPLATE = 'movies/_answer.html.twig';

    public function __construct(
        #[Autowire(service: 'ai.message_store.cache.shared_conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.movies')]
        private readonly AgentInterface $agent,
        private readonly Suggestions $suggestions,
        private readonly HubInterface $hub,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Answer $answer): void
    {
        $messages = $this->messageStore->load(UuidV7::fromString($answer->conversationId));

        // A redelivered message finds its question answered already
        if (!$messages->isLastMessageFrom(Role::User)) {
            return;
        }

        /** @var list<Step> $steps */
        $steps = [];
        $movies = [];

        try {
            $execution = $this->agent->call($messages, ['stream' => true, 'response_format' => MovieAnswer::class]);

            foreach ($execution as $update) {
                if (!$update instanceof Progress) {
                    continue;
                }

                $payload = $update->getPayload();

                if ($payload instanceof CommentaryStart) {
                    $steps[] = ['type' => 'commentary', 'text' => ''];
                    $this->publish($answer, 'step_added', ['step' => array_last($steps)]);
                }

                $last = array_key_last($steps);
                if ($payload instanceof CommentaryDelta && null !== $last && 'commentary' === $steps[$last]['type']) {
                    $steps[$last]['text'] .= $payload->getCommentary();
                    $this->publish($answer, 'step_updated', ['step' => $steps[$last]]);
                }

                if ($payload instanceof ToolCall) {
                    $steps[] = ['type' => 'tool', 'name' => $payload->getName(), 'arguments' => $payload->getArguments()];
                    $this->publish($answer, 'step_added', ['step' => array_last($steps)]);
                }

                if ($payload instanceof PartialObjectDelta) {
                    $partial = $payload->getObject();
                    \assert($partial instanceof MovieAnswer);

                    $this->publish($answer, 'answer_updated', [
                        'answer' => $partial->answer ?? '',
                        'suggestions' => $this->suggestions->resolve($partial->movies),
                    ]);
                }
            }

            $result = $execution->asObject(MovieAnswer::class);
            $movies = $result->movies;

            // the agent stored the answer as JSON, replacing it under its ID keeps the conversation readable
            $messages = $execution->getMessages();
            $answerId = $messages->latestAs(Role::Assistant)->getId();
            $response = Message::ofAssistant($result->answer)->withId($answerId);
            $messages = $messages->replace($answerId, $response);
        } catch (\Throwable $exception) {
            $response = Message::ofAssistant('Sorry, I could not search the collection. Please try again.');
            // continues after the tool results the agent stored before failing
            $messages = $this->messageStore->load($messages->getId())->with($response);
        }

        $response->getMetadata()->add('movies', $movies);
        $response->getMetadata()->add('steps', $steps);
        $this->messageStore->save($messages);
        $this->publish($answer, 'finished', ['message' => $response, 'suggestions' => $this->suggestions->resolve($movies)]);

        if (isset($exception)) {
            // Retrying would answer twice, the user can ask again instead
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function publish(Answer $answer, string $block, array $context): void
    {
        $html = $this->twig->load(self::TEMPLATE)->renderBlock($block, $context);

        $this->hub->publish(new Update($answer->topic, $html));
    }
}
