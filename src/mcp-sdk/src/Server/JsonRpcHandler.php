<?php

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
use Psr\Log\NullLogger;
use Symfony\AI\McpSdk\Exception\ExceptionInterface;
use Symfony\AI\McpSdk\Exception\HandlerNotFoundException;
use Symfony\AI\McpSdk\Exception\InvalidInputMessageException;
use Symfony\AI\McpSdk\Exception\NotFoundExceptionInterface;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\NotificationHandled;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\AI\McpSdk\Message\StreamableResponse;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;

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
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->requestHandlers = $requestHandlers instanceof \Traversable ? iterator_to_array($requestHandlers) : $requestHandlers;
        $this->notificationHandlers = $notificationHandlers instanceof \Traversable ? iterator_to_array($notificationHandlers) : $notificationHandlers;
    }

    /**
     * @return iterable<string|null>
     *
     * @throws ExceptionInterface When a handler throws an exception during message processing
     * @throws \JsonException     When JSON encoding of the response fails
     */
    public function process(string $input): iterable
    {
        $this->logger->info('Received message to process', ['message' => $input]);

        try {
            $messages = $this->messageFactory->create($input);
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to decode json message', ['exception' => $e]);

            yield $this->encodeResponse(Error::parseError($e->getMessage()));

            return;
        }

        foreach ($messages as $message) {
            $response = $this->handleMessage($message);
            if (null === $response) {
                continue;
            }
            if ($response instanceof StreamableResponse) {
                foreach($response->responses as $response) {
                    yield $this->encodeResponse($response);
                }
            } elseif ($response instanceof NotificationHandled) {
                yield null;
            } else {
                yield $this->encodeResponse($response);
            }
        }
    }

    public function isInitializeRequest(string $input): bool
    {
        // @todo we should prevent multiple calls to messageFactory for the same message
        $this->logger->info('Received message to process', ['message' => $input]);
        try {
            $messages = $this->messageFactory->create($input);
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to decode json message', ['exception' => $e]);

            return false;
        }
        if (!isset($messages[0]) || !$messages[0] instanceof Request) {
            return false;
        }
        $request = $messages[0];

        foreach ($this->requestHandlers as $handler) {
            if ($handler->supports($request)) {
                return $handler instanceof InitializeHandler;
            }
        }

        return false;
    }

    /**
     * @throws \JsonException
     */
    public function processSingleMessage(string $message): null|string|\iterable
    {
        $this->logger->info('Received message to process', ['message' => $message]);

        try {
            $messages = $this->messageFactory->create($message);
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to decode json message', ['exception' => $e]);
            return $this->encodeResponse(Error::parseError($e->getMessage()));
        }

        if (!isset($messages[0]) || !$messages[0] instanceof Request || count($messages) > 1) {
            $this->logger->warning('Bad input received. Should be a single message. Received: ' . $message);
            return $this->encodeResponse(Error::parseError('Bad input received. Should be a single message. Received: ' . $message));
        }
        $message = $messages[0];
        $response = $this->handleMessage($message);
        if (null === $response) {
            return null;
        }
        if ($response instanceof StreamableResponse) {
            foreach($response->responses as $response) {
                yield $this->encodeResponse($response);
            }
        } elseif ($response instanceof NotificationHandled) {
            return $response;
        } else {
            return $this->encodeResponse($response);
        }
    }

    /**
     * @param $message
     * @return Error|Response|null
     * @throws \JsonException
     */
    private function handleMessage($message): Error|Response|StreamableResponse|NotificationHandled|null
    {
        if ($message instanceof InvalidInputMessageException) {
            $this->logger->warning('Failed to create message', ['exception' => $message]);
            return Error::invalidRequest(0, $message->getMessage());
        }

        $this->logger->info('Decoded incoming message', ['message' => $message]);

        try {
            return $message instanceof Notification
                ? $this->handleNotification($message)
                : $this->handleRequest($message);
        } catch (\DomainException) {
            return null;
        } catch (NotFoundExceptionInterface $e) {
            $this->logger->warning(\sprintf('Failed to create response: %s', $e->getMessage()), ['exception' => $e]);

            return Error::methodNotFound($message->id, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning(\sprintf('Invalid argument: %s', $e->getMessage()), ['exception' => $e]);

            return Error::invalidParams($message->id, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Uncaught exception: %s', $e->getMessage()), ['exception' => $e]);

            return Error::internalError($message->id, $e->getMessage());
        }
    }

    /**
     * @throws \JsonException When JSON encoding fails
     */
    public function encodeResponse(Response|Error|null $response): ?string
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

    /**
     * @throws ExceptionInterface When a notification handler throws an exception
     */
    private function handleNotification(Notification $notification): NotificationHandled
    {
        $handled = false;
        foreach ($this->notificationHandlers as $handler) {
            if ($handler->supports($notification)) {
                $handler->handle($notification);
                $handled = true;
            }
        }

        if (!$handled) {
            $this->logger->warning(\sprintf('No handler found for "%s".', $notification->method), ['notification' => $notification]);
        }

        return new NotificationHandled();
    }

    /**
     * @throws NotFoundExceptionInterface When no handler is found for the request method
     * @throws ExceptionInterface         When a request handler throws an exception
     */
    private function handleRequest(Request $request): Response|Error
    {
        foreach ($this->requestHandlers as $handler) {
            if ($handler->supports($request)) {
                return $handler->createResponse($request);
            }
        }

        throw new HandlerNotFoundException(\sprintf('No handler found for method "%s".', $request->method));
    }
}
