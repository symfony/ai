<?php

declare(strict_types=1);

namespace Symfony\AI\McpBundle\Controller;

use iterable;
use Symfony\AI\McpSdk\Message\NotificationHandled;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class McpHttpStreamController
{
    public function __construct(
        private Server                   $server,
        private Server\JsonRpcHandler    $handler,
        private SessionFactory $sessionFactory,
    ) {
    }
    public function endpoint(Request $request, ?Session $session = null): Response
    {
        if ($session === null) {
            // Must be an "initialize" request. If not ==> 404.
            if (!$this->handler->isInitializeRequest($request->getContent())) {
                return new Response(null, Response::HTTP_NOT_FOUND);
            }
            $session = $this->sessionFactory->get();
            $session->save();
        }

        // Handle the input
        // If response is streamable ==> open an SSE Stream and store all responses in session for later replay
        // If response is not ==> JSON

        $response = $this->handler->processSingleMessage($request->getContent());

        if ($response instanceof iterable) {
            $transport = new Server\Transport\StreamableHttp\StreamTransport($request, $session, []);
            return new StreamedResponse(fn () => $this->server->connect($transport), headers: [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Mcp-Session-Id' => $session->sessionIdentifier->sessionId->toString(),
            ]);
        }
        if ($response instanceof NotificationHandled) {
            return new Response(null, Response::HTTP_ACCEPTED);
        }
        return new JsonResponse($this->handler->encodeResponse($response), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Mcp-Session-Id' => $session->sessionIdentifier->sessionId->toString(),
        ]);
        //$content = $request->g

        /*$transport = new Server\Transport\StreamableHttp\StreamTransport($request, $mcpSessionId->sessionId);

        return new StreamedResponse(fn () => $this->server->connect($transport), headers: [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Mcp-Session-Id' => $mcpSessionId->sessionId->toString(),
        ]);*/
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
            $events = $session->getEventsAfterId($request->headers->get('Last-Event-ID'));
        } else {
            // At this point server cannot attach to this stream to send request / notifications, so act like we don't support
            return new Response(null, Response::HTTP_METHOD_NOT_ALLOWED);
        }
    }
}
