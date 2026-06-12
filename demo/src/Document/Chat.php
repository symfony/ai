<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'document-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.document')]
        private readonly AgentInterface $agent,
        private readonly OcrExtractor $ocrExtractor,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
    }

    public function start(string $url): void
    {
        $ocr = $this->ocrExtractor->extract($url);
        $markdown = $ocr->getMarkdown();

        $system = <<<PROMPT
            You are a helpful assistant that answers questions about a document based on its OCR-extracted text.
            Only use information from the document below. If you can't answer a question, say so.

            Document URL: {$url}
            Extracted text:
            {$markdown}
            PROMPT;

        $messages = new MessageBag(
            Message::forSystem($system),
            Message::ofUser($url),
            Message::ofAssistant("I extracted the following text from the document via OCR:\n\n".$markdown."\n\nWhat would you like to know about it?"),
        );

        $this->reset();
        $this->saveMessages($messages);
    }

    public function submitMessage(string $message): void
    {
        $messages = $this->loadMessages();

        $messages->add(Message::ofUser($message));
        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $messages->add(Message::ofAssistant($result->getContent()));

        $this->saveMessages($messages);
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    private function saveMessages(MessageBag $messages): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $messages);
    }
}
