<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\EventListener;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\PlatformInvokationEvent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Converts string inputs to MessageBag for models that support INPUT_MESSAGES capability.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class StringToMessageBagListener
{
    public function __invoke(PlatformInvokationEvent $event): void
    {
        // Only process string inputs
        if (!\is_string($event->input)) {
            return;
        }

        // Only process models that support INPUT_MESSAGES capability
        if (!$event->model->supports(Capability::INPUT_MESSAGES)) {
            return;
        }

        // Convert string to MessageBag with a user message
        $event->input = new MessageBag(Message::ofUser($event->input));
    }
}
