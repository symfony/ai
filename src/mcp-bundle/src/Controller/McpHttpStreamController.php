<?php

declare(strict_types=1);

namespace Symfony\AI\McpBundle\Controller;

use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\StreamableResponse;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class McpHttpStreamController
{
    public function __construct(
        private Server\JsonRpcHandler    $handler,
        private Factory $messageFactory,
        private SessionFactory $sessionFactory,
    ) {
    }
    public function endpoint(Request $request, ?Session $session = null): Response
    {
        $message = $this->messageFactory->create($request->getContent());
        if ($session === null) {
            // Must be an "initialize" request. If not ==> 404.
            if ($message->method !== 'initialize') { // @todo do better
                return new Response(null, Response::HTTP_NOT_FOUND);
            }
            $session = $this->sessionFactory->get();
            $session->save();
        }

        // Handle the input
        // If response is streamable ==> open an SSE Stream and store all responses in session for later replay
        // If response is not ==> JSON

        $response = $this->handler->handleMessage($message);

        if ($message instanceof Notification) {
            return new Response(null, Response::HTTP_ACCEPTED);
        }
        if ($response instanceof StreamableResponse) {
            //$transport = new Server\Transport\StreamableHttp\StreamTransport($session->addNewStream(), $session, $response->responses);
            return new StreamedResponse(function () use ($session, $response) {
                $streamId = $session->addNewStream();
                foreach (($response->responses)() as $response) {
                    $eventId = Uuid::v4()->toString();
                    if (is_array($response)) {
                        $rawResponse = json_encode($response, \JSON_THROW_ON_ERROR);
                    } else {
                        $rawResponse = $this->handler->encodeResponse($response);
                    }
                    $session->addEventOnStream($streamId, $eventId, $rawResponse);
                    echo "id: $eventId\n";
                    echo "type: notification\n";
                    echo "data: " . $rawResponse . "\n\n";
                    if (false !== ob_get_length()) {
                        ob_flush();
                    }
                    flush();
                }
            }, headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Mcp-Session-Id' => $session->sessionIdentifier->sessionId->toString(),
            ]);
        }
        return new JsonResponse($this->handler->encodeResponse($response), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Mcp-Session-Id' => $session->sessionIdentifier->sessionId->toString(),
        ], true);
    }

    /**
     * Clients that no longer need a particular session (e.g., because the user is leaving the client application) SHOULD send an HTTP DELETE to the MCP endpoint with the Mcp-Session-Id header, to explicitly terminate the session.
     * @see{https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#session-management}
     *
     * @param Session $session
     * @return Response
     */
    public function deleteSession(Session $session): Response
    {
        $session->delete();
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param Session $session
     * @return Response
     */
    public function initiateSseFromStream(Request $request, Session $session): Response
    {
        if ($request->headers->has('Last-Event-ID')) {
            try {
                $session->getStreamIdForEvent($request->headers->get('Last-Event-ID'));
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
            $lastEventId = $request->headers->get('Last-Event-ID');
            return new StreamedResponse(function () use ($session, $lastEventId) {
                $i = 0;
                do {
                    $events = $session->getEventsAfterId($lastEventId);
                    $lastEvent = null;
                    foreach ($events as $event) {
                        $lastEventId = $event['id'];
                        $lastEvent = $event['event'];
                        echo 'id: ' . $lastEventId . \PHP_EOL;
                        echo 'data: ' . $lastEvent . \PHP_EOL . \PHP_EOL;
                        if (false !== ob_get_length()) {
                            ob_flush();
                        }
                        flush();
                    }
                    if ($events === []) {
                        usleep(1000);
                    }
                    // @todo we should detect here that the "real" response has been sent and close the stream
                } while (! ($lastEvent instanceof \Symfony\AI\McpSdk\Message\Response) && $i++ < 50);
            }, headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);


        } else {
            // At this point server cannot attach to this stream to send request / notifications, so act like we don't support
            return new Response(null, Response::HTTP_METHOD_NOT_ALLOWED);
        }
    }
}
