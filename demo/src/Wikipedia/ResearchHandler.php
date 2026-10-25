<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Twig\Environment;

/**
 * Researches the answer in the worker and pushes every step to the browser, as a Turbo Stream via Mercure.
 *
 * @phpstan-type Step array{type: 'commentary'|'tool', text: string}
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsMessageHandler]
final class ResearchHandler
{
    private const TEMPLATE = 'wikipedia/_research.html.twig';

    public function __construct(
        private readonly Conversations $conversations,
        #[Autowire(service: 'ai.agent.wikipedia')]
        private readonly AgentInterface $agent,
        private readonly HubInterface $hub,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Research $research): void
    {
        $messages = $this->conversations->load($research->chatId);

        // A redelivered message finds its question answered already
        if (!$messages->isLastMessageFrom(Role::User)) {
            return;
        }

        /** @var list<Step> $steps */
        $steps = [];
        $answer = '';

        try {
            $execution = $this->agent->call($messages, ['stream' => true]);

            foreach ($execution as $update) {
                if (!$update instanceof Progress) {
                    continue;
                }

                $payload = $update->getPayload();

                if ($payload instanceof CommentaryStart) {
                    $steps[] = ['type' => 'commentary', 'text' => ''];
                    $this->publish($research, 'step_added', ['step' => array_last($steps), 'index' => array_key_last($steps)]);
                }

                if ($payload instanceof CommentaryDelta && [] !== $steps) {
                    $steps[array_key_last($steps)]['text'] .= $payload->getCommentary();
                    $this->publish($research, 'step_updated', ['step' => array_last($steps), 'index' => array_key_last($steps)]);
                }

                if ($payload instanceof ToolCall) {
                    $steps[] = ['type' => 'tool', 'text' => $this->describe($payload)];
                    $this->publish($research, 'step_added', ['step' => array_last($steps), 'index' => array_key_last($steps)]);
                }

                if ($payload instanceof TextDelta) {
                    $answer .= $payload->getText();
                    $this->publish($research, 'answer_updated', ['answer' => $answer]);
                }
            }

            $result = $execution->getResult();
            $response = Message::ofAssistant($result);
            $response->getMetadata()->add('sources', $result->getMetadata()->get('sources', []));
        } catch (\Throwable $exception) {
            $response = Message::ofAssistant('Sorry, my research failed. Please try again.');
        }

        $response->getMetadata()->add('steps', $steps);
        $messages->add($response);
        $this->conversations->save($research->chatId, $messages);
        $this->publish($research, 'finished', ['message' => $response]);

        if (isset($exception)) {
            // Retrying would answer twice, the user can ask again instead
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), previous: $exception);
        }
    }

    private function describe(ToolCall $toolCall): string
    {
        $arguments = $toolCall->getArguments();

        return match ($toolCall->getName()) {
            'wikipedia_search' => \sprintf('Searching Wikipedia for "%s"', $arguments['query'] ?? ''),
            'wikipedia_article' => \sprintf('Reading the article "%s"', $arguments['title'] ?? ''),
            default => \sprintf('Calling "%s"', $toolCall->getName()),
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function publish(Research $research, string $block, array $context): void
    {
        $html = $this->twig->load(self::TEMPLATE)->renderBlock($block, $context);

        $this->hub->publish(new Update(Conversations::topic($research->chatId), $html));
    }
}
