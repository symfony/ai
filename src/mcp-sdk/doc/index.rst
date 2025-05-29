Model Context Protocol SDK
==========================

Symfony AI MCP SDK is the low level library that enables communication between
a PHP application and an LLM model.

Installation
------------

Install the bundle using Composer:

.. code-block:: terminal

    $ composer require symfony/mcp-sdk

Usage
-----

The `Model Context Protocol`_ is built on top of JSON-RPC. There two types of
messages. A Notification and Request. The Notification is just a status update
that something has happened. There is never a response to a Notification. A Request
is a message that expects a response. There are 3 concepts that you may request.
These are::

1. **Resources**: File-like data that can be read by clients (like API responses or file contents)
1. **Tools**: Functions that can be called by the LLM (with user approval)
1. **Prompts**: Pre-written templates that help users accomplish specific tasks

The SDK comes with NotificationHandlers and RequestHandlers which are expected
to be wired up in your application.

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
