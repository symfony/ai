<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Adds the #[IsGranted] attributes of the tools that a request calls to the attributes of the MCP controller.
 *
 * Symfony checks them before the MCP server runs, as for any controller. A denial then reaches the firewall
 * instead of becoming a JSON-RPC error, so that clients get a 401 or a 403 challenge.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class IsGrantedToolListener implements EventSubscriberInterface
{
    /**
     * @param array<string, array<string, array{class-string, string}>> $tools the handler of each tool that has #[IsGranted] attributes, by tool name, by controller
     */
    public function __construct(
        private readonly array $tools,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $controller = $request->attributes->get('_controller');

        if (!\is_string($controller) || !isset($this->tools[$controller]) || !$request->isMethod('POST')) {
            return;
        }

        $attributes = [];
        foreach ($this->getCalledTools($request->getContent()) as $name) {
            if (!isset($this->tools[$controller][$name])) {
                continue;
            }

            [$class, $method] = $this->tools[$controller][$name];
            $class = new \ReflectionClass($class);

            foreach ([...$class->getAttributes(IsGranted::class), ...$class->getMethod($method)->getAttributes(IsGranted::class)] as $attribute) {
                $attributes[] = $attribute->newInstance();
            }
        }

        if ([] === $attributes) {
            return;
        }

        if (class_exists(ControllerAttributeEvent::class)) {
            $event->setController($event->getController(), [...$event->getAttributes('*'), ...$attributes]);

            return;
        }

        $grouped = $event->getAttributes();
        $grouped[IsGranted::class] = [...$grouped[IsGranted::class] ?? [], ...$attributes];
        // @phpstan-ignore argument.type (Symfony < 8.1 groups the attributes by class)
        $event->setController($event->getController(), $grouped);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    /**
     * @return iterable<string>
     */
    private function getCalledTools(string $body): iterable
    {
        $messages = json_decode($body, true);

        if (!\is_array($messages)) {
            return;
        }

        // a JSON-RPC batch is a list of messages
        foreach (array_is_list($messages) ? $messages : [$messages] as $message) {
            if (\is_array($message) && 'tools/call' === ($message['method'] ?? null) && \is_string($name = $message['params']['name'] ?? null)) {
                yield $name;
            }
        }
    }
}
