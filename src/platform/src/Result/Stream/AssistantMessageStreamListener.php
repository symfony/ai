<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\WebSearchComplete;

/**
 * Rebuilds the assistant message a provider would have returned unstreamed, from its deltas.
 *
 * Attach it to a {@see \Symfony\AI\Platform\Result\StreamResult} and read
 * {@see self::getAssistantMessage()} once the deltas of interest have been consumed.
 *
 * The delta vocabulary differs per bridge: not every bridge announces a thinking block before its
 * first chunk or a tool call before its arguments, and the Responses API emits a reasoning item's
 * signature only after the block has closed.
 *
 * A `ChoiceDelta` contributes nothing: it carries the deltas of several candidates, and one
 * assistant turn cannot be reassembled from alternatives to it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageStreamListener extends AbstractStreamListener
{
    /**
     * Parts in provider order; a null entry is a slot reserved by `ToolCallStart`.
     *
     * @var list<ContentInterface|null>
     */
    private array $content = [];

    /**
     * @var array<string, int>
     */
    private array $toolCallSlots = [];

    private ?int $openThinking = null;

    /**
     * Most recent thinking block, for providers that emit its signature after it closed.
     */
    private ?int $lastThinking = null;

    public function onStart(StartEvent $event): void
    {
        $this->reset();
    }

    public function onDelta(DeltaEvent $event): void
    {
        $delta = $event->getDelta();

        // a replacement stream cannot be read here without consuming it; StreamResult collects
        // those deltas as it yields them
        if (!$delta instanceof DeltaInterface) {
            return;
        }

        $this->accumulate($delta);
    }

    public function reset(): void
    {
        $this->content = [];
        $this->toolCallSlots = [];
        $this->openThinking = null;
        $this->lastThinking = null;
    }

    /**
     * Feeds a single delta in, for callers driving the reassembly themselves.
     */
    public function accumulate(DeltaInterface $delta): void
    {
        if ($delta instanceof TextDelta) {
            $this->appendText($delta->getText());

            return;
        }

        if ($delta instanceof ThinkingStart) {
            $this->openThinking();

            return;
        }

        if ($delta instanceof ThinkingDelta) {
            $index = $this->openThinking ?? $this->openThinking();
            $thinking = $this->thinkingAt($index);
            $this->content[$index] = new Thinking($thinking->getContent().$delta->getThinking(), $thinking->getSignature());

            return;
        }

        if ($delta instanceof ThinkingComplete) {
            $index = $this->openThinking ?? $this->openThinking();
            $thinking = $this->thinkingAt($index);
            $this->content[$index] = new Thinking($delta->getThinking(), $delta->getSignature() ?? $thinking->getSignature());
            $this->openThinking = null;
            $this->lastThinking = $index;

            return;
        }

        if ($delta instanceof ThinkingSignature) {
            $this->applySignature($delta->getSignature());

            return;
        }

        if ($delta instanceof WebSearchComplete) {
            $webSearch = $delta->getWebSearch();
            $this->content[] = new WebSearch(
                $webSearch->getQuery(),
                $webSearch->getId(),
                $webSearch->getStatus(),
                $webSearch->getQueries(),
                $webSearch->getSignature(),
            );
            $this->openThinking = null;

            return;
        }

        if ($delta instanceof ToolCallStart) {
            $id = $delta->getId();

            // an id that is empty or already announced cannot address a slot of its own
            if ('' !== $id && !isset($this->toolCallSlots[$id])) {
                $this->content[] = null;
                $this->toolCallSlots[$id] = array_key_last($this->content);
            }

            $this->openThinking = null;

            return;
        }

        if ($delta instanceof ToolCallComplete) {
            foreach ($delta->getToolCalls() as $toolCall) {
                $slot = $this->toolCallSlots[$toolCall->getId()] ?? null;

                // a bridge that never announced the call still needs it replayed
                if (null === $slot) {
                    $this->content[] = $toolCall;
                    continue;
                }

                $this->content[$slot] = $toolCall;
                unset($this->toolCallSlots[$toolCall->getId()]);
            }

            $this->openThinking = null;
        }
    }

    /**
     * The assistant turn as reassembled so far.
     */
    public function getAssistantMessage(): AssistantMessage
    {
        $content = [];

        foreach ($this->content as $part) {
            // providers reject empty content blocks on replay
            if (null === $part || $this->isEmpty($part)) {
                continue;
            }

            $content[] = $part;
        }

        return new AssistantMessage(...$content);
    }

    private function appendText(string $text): void
    {
        if ('' === $text) {
            return;
        }

        $this->openThinking = null;

        $index = array_key_last($this->content);
        $last = null === $index ? null : $this->content[$index];

        if ($last instanceof Text) {
            $this->content[$index] = new Text($last->getText().$text, $last->getSignature());

            return;
        }

        $this->content[] = new Text($text);
    }

    /**
     * The Responses API emits a reasoning item's signature after its block has closed, and emits one
     * even when no summary was streamed, so a signature with no block to attach to is an item of its
     * own rather than the opening of the next one.
     */
    private function applySignature(string $signature): void
    {
        $index = $this->openThinking;

        if (null === $index) {
            $index = $this->lastThinking;
            $candidate = null === $index ? null : $this->content[$index];

            if (!$candidate instanceof Thinking || null !== $candidate->getSignature()) {
                $index = $this->openThinking();
                $this->openThinking = null;
            }
        }

        $thinking = $this->thinkingAt($index);
        $this->content[$index] = new Thinking($thinking->getContent(), ($thinking->getSignature() ?? '').$signature);
        $this->lastThinking = $index;
    }

    private function openThinking(): int
    {
        $this->content[] = new Thinking('');

        return $this->openThinking = $this->lastThinking = array_key_last($this->content);
    }

    private function thinkingAt(int $index): Thinking
    {
        $thinking = $this->content[$index];
        \assert($thinking instanceof Thinking);

        return $thinking;
    }

    private function isEmpty(ContentInterface $part): bool
    {
        if ($part instanceof Thinking) {
            return '' === $part->getContent() && null === $part->getSignature();
        }

        if ($part instanceof Text) {
            return '' === $part->getText();
        }

        return false;
    }
}
