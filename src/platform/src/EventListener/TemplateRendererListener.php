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

use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistryInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Renders message templates when template_vars option is provided.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TemplateRendererListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TemplateRendererRegistryInterface $rendererRegistry,
    ) {
    }

    public function __invoke(InvocationEvent $event): void
    {
        $options = $event->getOptions();
        if (!isset($options['template_vars'])) {
            return;
        }

        if (!\is_array($options['template_vars'])) {
            throw new InvalidArgumentException('The "template_vars" option must be an array.');
        }

        $input = $event->getInput();
        if (!$input instanceof MessageBag) {
            return;
        }

        $templateVars = $options['template_vars'];
        $renderedMessages = [];

        foreach ($input->getMessages() as $message) {
            $renderedMessages[] = $this->renderMessage($message, $templateVars);
        }

        $event->setInput(new MessageBag(...$renderedMessages));

        unset($options['template_vars']);
        $event->setOptions($options);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => '__invoke',
        ];
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    private function renderMessage(MessageInterface $message, array $templateVars): MessageInterface
    {
        if ($message instanceof SystemMessage) {
            $content = $message->getContent();
            if ($content instanceof Template) {
                $renderedContent = $this->rendererRegistry
                    ->getRenderer($content->getType())
                    ->render($content, $templateVars);

                return new SystemMessage($renderedContent);
            }
        }

        if ($message instanceof UserMessage) {
            $hasTemplate = false;
            $renderedContent = [];

            foreach ($message->getContent() as $content) {
                if ($content instanceof Template) {
                    $hasTemplate = true;
                    $renderedText = $this->rendererRegistry
                        ->getRenderer($content->getType())
                        ->render($content, $templateVars);

                    $renderedContent[] = new Text($renderedText);
                } else {
                    $renderedContent[] = $content;
                }
            }

            if ($hasTemplate) {
                return new UserMessage(...$renderedContent);
            }
        }

        return $message;
    }
}
