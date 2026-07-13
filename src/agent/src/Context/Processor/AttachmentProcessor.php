<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context\Processor;

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Message;

/**
 * Attaches platform content references (Document, Image, Audio, ...) supplied
 * as context items to the latest user message.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AttachmentProcessor implements ContextProcessorInterface
{
    public static function supportedTypes(): array
    {
        return [ContentInterface::class];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $contents = $request->getContext()->all(ContentInterface::class);
        if ([] === $contents) {
            return;
        }

        $messages = $request->getMessageBag();
        $userMessage = $messages->getUserMessage();
        if (null === $userMessage) {
            return;
        }

        $merged = Message::ofUser(...$userMessage->getContent(), ...$contents);
        $request->setMessageBag($messages->replace($userMessage->getId(), $merged));
    }
}
