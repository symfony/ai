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
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->requestHandlers = $requestHandlers instanceof \Traversable ? iterator_to_array($requestHandlers) : $requestHandlers;
        $this->notificationHandlers = $notificationHandlers instanceof \Traversable ? iterator_to_array($notificationHandlers) : $notificationHandlers;
    }

    /**
     * @return iterable<string|Response|Error|null>
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
            if ($message instanceof InvalidInputMessageException) {
                $this->logger->warning('Failed to create message', ['exception' => $message]);
                yield $this->encodeResponse(Error::invalidRequest(0, $message->getMessage()));
                continue;
            }

            $this->logger->info('Decoded incoming message', ['message' => $message]);

            try {
                if ($message instanceof Notification) {
                    yield $this->handleNotification($message);
                } elseif ($message instanceof Response || $message instanceof Error) {
                    yield $message;
                } else {
                    yield $this->encodeResponse($this->handleRequest($message));
                }
            } catch (\DomainException) {
                yield null;
            } catch (NotFoundExceptionInterface $e) {
                $this->logger->warning(\sprintf('Failed to create response: %s', $e->getMessage()), ['exception' => $e]);

                yield $this->encodeResponse(Error::methodNotFound($message->id, $e->getMessage()));
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning(\sprintf('Invalid argument: %s', $e->getMessage()), ['exception' => $e]);

                yield $this->encodeResponse(Error::invalidParams($message->id, $e->getMessage()));
            } catch (\Throwable $e) {
                $this->logger->critical(\sprintf('Uncaught exception: %s', $e->getMessage()), ['exception' => $e]);

                yield $this->encodeResponse(Error::internalError($message->id, $e->getMessage()));
            }
        }
    }

    /**
     * @throws \JsonException When JSON encoding fails
     */
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

    /**
     * @throws ExceptionInterface When a notification handler throws an exception
     */
    private function handleNotification(Notification $notification): null
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

        return null;
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
