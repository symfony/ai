<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server;

use Psr\Log\LoggerInterface;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

/**
 * @final
 */
readonly class JsonRpcHandler
{
    /**
     * @var array<int, RequestHandlerInterface>
     */
    private array $requestHandlers;

    /**
     * @var array<int, NotificationHandlerInterface>
     */
    private array $notificationHandlers;

    /**
     * @param iterable<RequestHandlerInterface>      $requestHandlers
     * @param iterable<NotificationHandlerInterface> $notificationHandlers
     */
    public function __construct(
        private Factory $messageFactory,
        iterable $requestHandlers,
        iterable $notificationHandlers,
        private LoggerInterface $logger,
    ) {
        $this->requestHandlers = $requestHandlers instanceof \Traversable ? iterator_to_array($requestHandlers) : $requestHandlers;
        $this->notificationHandlers = $notificationHandlers instanceof \Traversable ? iterator_to_array($notificationHandlers) : $notificationHandlers;
    }

    /**
     * @throws \JsonException
     */
    public function process(string $message): ?string
    {
        $this->logger->info('Received message to process', ['message' => $message]);

        try {
            $message = $this->messageFactory->create($message);
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to decode json message', ['exception' => $e]);

            return $this->encodeResponse(Error::parseError($e->getMessage()));
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Failed to create message', ['exception' => $e]);

            return $this->encodeResponse(Error::invalidRequest(0, $e->getMessage()));
        }

        $this->logger->info('Decoded incoming message', ['message' => $message]);

        try {
            return $message instanceof Notification
                ? $this->handleNotification($message)
                : $this->encodeResponse($this->handleRequest($message));
        } catch (\DomainException) {
            return null;
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning(\sprintf('Failed to create response: %s', $e->getMessage()), ['exception' => $e]);

            return $this->encodeResponse(Error::methodNotFound($message->id ?? 0, $e->getMessage()));
        }
    }

    private function encodeResponse(Response|Error|null $response): ?string
    {
        if (null === $response) {
            $this->logger->warning('Response is null');

            return null;
        }

        $this->logger->info('Encoding response', ['response' => $response]);

        if ($response instanceof Response && [] === $response->result) {
            return json_encode($response, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT);
        }

        return json_encode($response, \JSON_THROW_ON_ERROR);
    }

    private function handleNotification(Notification $notification): null
    {
        foreach ($this->notificationHandlers as $handler) {
            if ($handler->supports($notification)) {
                return $handler->handle($notification);
            }
        }

        $this->logger->warning(\sprintf('No handler found for "%s".', $notification->method), ['notification' => $notification]);

        return null;
    }

    private function handleRequest(Request $request): Response|Error
    {
        foreach ($this->requestHandlers as $handler) {
            if ($handler->supports($request)) {
                return $handler->createResponse($request);
            }
        }

        throw new \InvalidArgumentException(\sprintf('No handler found for method "%s".', $request->method));
    }
}
