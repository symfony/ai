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

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Chat
{
    private const CHAT = 'document';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.session.conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.document')]
        private readonly AgentInterface $agent,
        private readonly OcrExtractor $ocrExtractor,
        #[Autowire(param: 'app.document.prompts.summary')]
        private readonly string $summaryPrompt,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function start(string $url): void
    {
        // Step 1 — transcribe the document with Mistral OCR (cached per URL).
        $ocr = $this->ocrExtractor->extract($url);
        $markdown = $ocr->getMarkdown();

        $system = <<<PROMPT
            You are a helpful assistant that answers questions about a document based on its OCR-extracted text.
            Only use information from the document below. If you can't answer a question, say so.
            Use markdown for formatting the answer.

            Document URL: {$url}
            Extracted text:
            {$markdown}
            PROMPT;

        // Step 2 — have the chat agent read the extracted text and open with a
        // meaningful summary. This is the orchestration on display: one capability
        // (OCR transcription) feeds another (the reasoning agent).
        $execution = $this->agent->call(new MessageBag(
            Message::forSystem($system),
            Message::ofUser($this->summaryPrompt),
        ));

        $this->reset();
        $this->messageStore->save(MessageBag::forId(
            $this->conversationIds->get(self::CHAT),
            Message::forSystem($system),
            $url,
            Message::ofAssistant($execution->getResult()),
        ));
    }

    public function submitMessage(string $message): void
    {
        $messages = MessageBag::forId($this->conversationIds->get(self::CHAT), $message);

        $this->agent->call($messages)->getResult();
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
