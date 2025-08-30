Model Context Protocol SDK
==========================

Symfony AI MCP SDK is the low level library that enables communication between
a PHP application and an LLM model.

Installation
------------

Install the SDK using Composer:

.. code-block:: terminal

    $ composer require symfony/mcp-sdk

Usage
-----

The `Model Context Protocol`_ is built on top of JSON-RPC. There two types of
messages. A Notification and Request. The Notification is just a status update
that something has happened. There is never a response to a Notification. A Request
is a message that expects a response. There are 3 concepts/capabilities that you
may use. These are::

1. **Resources**: File-like data that can be read by clients (like API responses or file contents)
1. **Tools**: Functions that can be called by the LLM (with user approval)
1. **Prompts**: Pre-written templates that help users accomplish specific tasks

The SDK comes with NotificationHandlers and RequestHandlers which are expected
to be wired up in your application.

JsonRpcHandler
..............

The ``Symfony\AI\McpSdk\Server\JsonRpcHandler`` is the heart of the SDK. It is here
you inject the NotificationHandlers and RequestHandlers. It is recommended to use
the built-in handlers in ``Symfony\AI\McpSdk\Server\NotificationHandlers\*`` and
``Symfony\AI\McpSdk\Server\RequestHandlers\*``.

The ``Symfony\AI\McpSdk\Server\JsonRpcHandler`` is started and kept running by
the ``Symfony\AI\McpSdk\Server``

Transports
..........

The SDK supports multiple transports for sending and receiving messages. The
``Symfony\AI\McpSdk\Server`` is using the transport to fetch a message, then
give it to the ``Symfony\AI\McpSdk\Server\JsonRpcHandler`` and finally send the
response/error back to the transport. The SDK comes with a few transports::

1. **Symfony Console Transport**: Good for testing and for CLI applications
1. **Stream Transport**: It uses Server Side Events (SSE) and HTTP streaming

Capabilities
............

Any client would like to discover the capabilities of the server. Exactly what
the server supports is defined in the ``Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler``.
When the client connects, it sends an ``initialize`` request. The server responds
with its capabilities, which are defined by two main classes:

* ``Symfony\AI\McpSdk\Capability\Server\Implementation``: Describes the server software itself (e.g., its name and version).
* ``Symfony\AI\McpSdk\Capability\Server\ServerCapabilities``: Details the features the server supports, such as tools, prompts, or resources.

After that client see available capabilities and will ask the server to list the tools/resource/prompts etc. depending on ``ServerCapabilities`` you provided.
Also ``initialize`` request provides information about protocol version, instructions and ``_meta`` field.
You can find more information about ``Initialize`` response `InitializeResult <https://modelcontextprotocol.io/specification/2025-06-18/schema#initializeresult>`_

When you want to add a new tool for example:
**Tool** that can tell the current time, you need to provide some metadata to the
``Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler``::

    namespace App;

    use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;

    class CurrentTimeToolMetadata implements MetadataInterface
    {
        public function getName(): string
        {
            return 'Current time';
        }

        public function getDescription(): string
        {
            return 'Returns the current time in UTC';
        }

        public function getInputSchema(): array
        {
            return [
                'type' => 'object',
                'properties' => [
                    'format' => [
                        'type' => 'string',
                        'description' => 'The format of the time, e.g. "Y-m-d H:i:s"',
                        'default' => 'Y-m-d H:i:s',
                    ],
                ],
                'required' => [],
            ];
        }
    }

We would also need a class to actually execute the tool::

    namespace App;

    use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
    use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
    use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
    use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

    class CurrentTimeToolExecutor implements ToolExecutorInterface, IdentifierInterface
    {
        public function getName(): string
        {
            return 'Current time';
        }

        public function call(ToolCall $input): ToolCallResult
        {
            $format = $input->arguments['format'] ?? 'Y-m-d H:i:s';

            return new ToolCallResult(
                (new \DateTime('now', new \DateTimeZone('UTC')))->format($format)
            );
        }
    }

If you have multiple tools, you can put them in a ToolChain::

    $tools = new ToolChain([
        new CurrentTimeToolMetadata(),
        new CurrentTimeToolExecutor(),
    ]);

    $jsonRpcHandler = new Symfony\AI\McpSdk\Server\JsonRpcHandler(
        new Symfony\AI\McpSdk\Message\Factory(),
        [
            new ToolCallHandler($tools),
            new ToolListHandler($tools),
            // Other RequestHandlers ...
        ],
        [
            // Other NotificationHandlers ...
        ],
        new NullLogger()
    );

With this metadata and executor, the client can now call the tool.

Extending the SDK
-----------------

If you want to extend the SDK, you can create your own RequestHandlers and NotificationHandlers.
The provided one are very good defaults for most applications but they are not
a requirement.

If you do decide to use them, you get the benefit of having a well-defined interfaces
and value objects to work with. They will assure that you follow the `Model Context Protocol`_.
specification.

You also have the Transport abstraction that allows you to create your own transport
if non of the standard ones fit your needs.

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
